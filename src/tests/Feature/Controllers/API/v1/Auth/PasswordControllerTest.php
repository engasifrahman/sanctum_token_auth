<?php

namespace Tests\Feature\API\v1\Auth;

use Tests\TestCase;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;

class PasswordControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that a valid email receives a password reset link.
     *
     * @return void
     */
    public function testForgotPasswordSucceedsWithValidEmail(): void
    {
        // Arrange
        Notification::fake();
        $user = User::factory()->create();
        $data = ['email' => $user->email];

        // Act
        $response = $this->postJson(route('v1.auth.forgot-password'), $data);

        // Assert
        $response->assertOk()
            ->assertJson([
                'status' => true,
                'message' => 'If your email address exists in our system, a password reset link has been sent to it.',
            ]);

        Notification::assertSentTo($user, ResetPassword::class);
    }

    /**
     * Test that an invalid email returns a validation error.
     *
     * @return void
     */
    public function testForgotPasswordFailsWithInvalidEmail(): void
    {
        // Arrange
        Notification::fake();
        $data = ['email' => 'invalid-email'];

        // Act
        $response = $this->postJson(route('v1.auth.forgot-password'), $data);

        // Assert
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);

        Notification::assertNothingSent();
    }

    /**
     * Test that forgot password fails when throttled.
     *
     * @return void
     */
    public function testForgotPasswordFailsWhenThrottled(): void
    {
        // Arrange
        $user = User::factory()->create();
        $data = ['email' => $user->email];

        // Act
        // Hit endpoint repeatedly until throttled
        for ($i = 0; $i < 6; $i++) {
            $this->postJson(route('v1.auth.forgot-password'), $data);
        }

        // Final attempt should be throttled
        $response = $this->postJson(route('v1.auth.forgot-password'), $data);

        // Assert
        $response->assertStatus(429)
            ->assertJson([
                'status' => false,
                'message' => 'Too many password reset attempts. Please try again later.',
            ]);
    }

    /**
     * Test that forgot password handles an unexpected broker status.
     *
     * @return void
     */
    public function testForgotPasswordFailsWithUnexpectedStatus(): void
    {
        // Arrange
        $data = ['email' => 'test@example.com'];

        // Act
        $response = $this->postJson(route('v1.auth.forgot-password'), $data);

        // Assert
        $response->assertStatus(500)
            ->assertJson([
                'status' => false,
                'message' => 'Could not send password reset link. Please try again.',
            ]);
    }

    /**
     * Test that a password can be successfully reset with valid token and data.
     *
     * @return void
     */
    public function testResetPasswordSucceedsWithValidToken(): void
    {
        // Arrange
        $user = User::factory()->create();
        $token = app('auth.password.broker')->createToken($user);
        $data = [
            'email' => $user->email,
            'token' => $token,
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ];

        // Act
        $response = $this->postJson(route('v1.auth.reset-password'), $data);

        // Assert
        $response->assertOk()
            ->assertJson([
                'status' => true,
                'message' => 'Your password has been reset successfully.',
            ]);

        $this->assertTrue(Hash::check('new-password', $user->fresh()->password));
    }

    /**
     * Test that reset password fails with an invalid token.
     *
     * @return void
     */
    public function testResetPasswordFailsWithInvalidToken(): void
    {
        // Arrange
        $user = User::factory()->create();
        $data = [
            'email' => $user->email,
            'token' => 'invalid-token',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ];

        // Act
        $response = $this->postJson(route('v1.auth.reset-password'), $data);

        // Assert
        $response->assertStatus(403)
            ->assertJson([
                'status' => false,
                'message' => 'The password reset token is invalid or has expired.',
            ]);

        $this->assertFalse(Hash::check('new-password', $user->fresh()->password));
    }
}
