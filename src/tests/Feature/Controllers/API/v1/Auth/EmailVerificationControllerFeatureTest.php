<?php

namespace Tests\Feature\API\v1\Auth;

use Tests\TestCase;
use App\Models\User;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\Event;
use Illuminate\Foundation\Testing\RefreshDatabase;

class EmailVerificationControllerFeatureTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Prepare a signed URL for email verification.
     *
     * @param  int|null  $id
     * @param  string|null  $email
     * @return string
     */
    private function prepareSignedUrl(int|null $id = null, string|null $email = null): string
    {
       return URL::temporarySignedRoute(
            'v1.auth.verify-email', // This is the name of your API verification route
            Carbon::now()->addMinutes(config('auth.verification.expire', 60)),
            [
                'id' => $id,
                'hash' => sha1($email),
            ]
        );
    }

    /**
     * Test successful email verification via verification link.
     *
     * @return void
     */
    public function testVerifyEmailLinkSuccessfully()
    {
        // Arrange
        Event::fake();
        $user = User::factory()->unverified()->create();
        $temporarySignedRoute = $this->prepareSignedUrl($user->id, $user->email);

        // Act
        $response = $this->postJson($temporarySignedRoute);

        // Assert
        $response->assertStatus(200)
                 ->assertJson(['message' => 'Email verified successfully.']);

        $this->assertTrue($user->fresh()->hasVerifiedEmail());
        Event::assertDispatched(Verified::class);
    }

    /**
     * Test verification link returns conflict when email already verified.
     *
     * @return void
     */
    public function testVerifyEmailLinkAlreadyVerified()
    {
        // Arrange
        $user = User::factory()->create();
        $temporarySignedRoute = $this->prepareSignedUrl($user->id, $user->email);

        // Act
        $response = $this->postJson($temporarySignedRoute);

        // Assert
        $response->assertStatus(Response::HTTP_CONFLICT)
                 ->assertJson(['message' => 'Email already verified.']);
    }

    /**
     * Test verification link fails when an invalid hash is provided.
     *
     * @return void
     */
    public function testVerifyEmailLinkFailsWithInvalidHash()
    {
        // Arrange
        $user = User::factory()->unverified()->create();
        $invalidEmail = 'invalidEmail';

        $temporarySignedRoute = $this->prepareSignedUrl($user->id, $invalidEmail);

        // Act
        $response = $this->postJson($temporarySignedRoute);

        // Assert
        $response->assertStatus(Response::HTTP_FORBIDDEN)
                 ->assertJson(['message' => 'Invalid verification link.']);

        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    /**
     * Test resending verification email successfully.
     *
     * @return void
     */
    public function testResendVerificationEmailSuccessfully()
    {
        // Arrange
        $user = User::factory()->unverified()->create();

        // Act
        $response = $this->postJson(route('v1.auth.resend-verification-email'), [
            'email' => $user->email,
        ]);

        // Assert
        $response->assertStatus(200)
                 ->assertJson(['message' => 'Verification link sent.']);
    }

    /**
     * Test resending verification email fails if user not found.
     *
     * @return void
     */
    public function testResendVerificationEmailFailsIfUserNotFound()
    {
        // Arrange
        $nonExistentEmail = 'notfound@example.com';

        // Act
        $response = $this->postJson(route('v1.auth.resend-verification-email'), [
            'email' => $nonExistentEmail,
        ]);

        // Assert
        $response->assertStatus(Response::HTTP_NOT_FOUND)
                 ->assertJson(['message' => 'User not found.']);
    }

    /**
     * Test resending verification email is skipped if email already verified.
     *
     * @return void
     */
    public function testResendVerificationEmailSkippedIfAlreadyVerified()
    {
        // Arrange
        $user = User::factory()->create();

        // Act
        $response = $this->postJson(route('v1.auth.resend-verification-email'), [
            'email' => $user->email,
        ]);

        // Assert
        $response->assertStatus(Response::HTTP_CONFLICT)
                 ->assertJson(['message' => 'Email already verified.']);
    }

    /**
     * Test resending verification email fails validation with invalid email.
     *
     * @return void
     */
    public function testResendVerificationEmailFailsValidation()
    {
        // Arrange
        $invalidEmail = 'not-an-email';

        // Act
        $response = $this->postJson(route('v1.auth.resend-verification-email'), [
            'email' => $invalidEmail,
        ]);

        // Assert
        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
                 ->assertJsonValidationErrors(['email']);
    }
}
