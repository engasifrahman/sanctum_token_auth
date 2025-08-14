<?php

namespace Tests\Unit\Controllers\API\v1\Auth;

use Mockery;
use Tests\TestCase;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use App\Http\Controllers\API\v1\Auth\PasswordController;

class PasswordControllerTest extends TestCase
{
    /**
     * @var array
     */
    private array $payload;

    /**
     * Set up mocks before each test.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Mock Log facade to avoid writing real logs
        Log::shouldReceive('info')->byDefault();
        Log::shouldReceive('warning')->byDefault();
        Log::shouldReceive('error')->byDefault();

        // Common payload for password reset tests
        $this->payload = [
            'token' => 'some-token',
            'email' => 'test@example.com',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ];
    }

    /**
     * A common method to mock the Password::sendResetLink method for testing.
     *
     * @param string $status The status to be returned by the facade.
     * @param string $email The email to use for the request.
     * @return Request The mocked request object.
     */
    private function mockSendResetLink(string $status, string $email = 'test@example.com'): Request
    {
        Password::shouldReceive('sendResetLink')
            ->once()
            ->with(['email' => $email])
            ->andReturn($status);

        return new Request(['email' => $email]);
    }

    /**
     * A common method to mock the Password::reset method for testing.
     *
     * @param string $status The status to be returned by the facade.
     * @param array $data The data for the request.
     * @return Request The mocked request object.
     */
    private function mockPasswordReset(string $status, array $data): Request
    {
        Password::shouldReceive('reset')
            ->once()
            ->andReturn($status);

        return new Request($data);
    }

    /**
     * Tests a successful forgot password request.
     *
     * @return void
     */
    public function testForgotPasswordSucceeds(): void
    {
        // Arrange
        $request = $this->mockSendResetLink(Password::RESET_LINK_SENT);
        $controller = new PasswordController();

        // Act
        $response = $controller->forgotPassword($request);

        // Assert
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        $this->assertStringContainsString('password reset link has been sent', $response->getData()->message);
    }

    /**
     * Tests that forgot password fails with a throttling message.
     *
     * @return void
     */
    public function testForgotPasswordFailsOnThrottling(): void
    {
        // Arrange
        $request = $this->mockSendResetLink(Password::RESET_THROTTLED);
        $controller = new PasswordController();

        // Act
        $response = $controller->forgotPassword($request);

        // Assert
        $this->assertEquals(Response::HTTP_TOO_MANY_REQUESTS, $response->getStatusCode());
        $this->assertStringContainsString('Too many password reset attempts', $response->getData()->message);
    }

    /**
     * Tests that forgot password fails with an unexpected status.
     *
     * @return void
     */
    public function testForgotPasswordFailsOnUnexpectedStatus(): void
    {
        // Arrange
        $request = $this->mockSendResetLink('some_other_status');
        $controller = new PasswordController();

        // Act
        $response = $controller->forgotPassword($request);

        // Assert
        $this->assertEquals(Response::HTTP_INTERNAL_SERVER_ERROR, $response->getStatusCode());
        $this->assertStringContainsString('Could not send password reset link', $response->getData()->message);
    }

    /**
     * Tests a successful password reset.
     *
     * @return void
     */
    public function testResetPasswordSucceeds(): void
    {
        // Arrange
        $request = $this->mockPasswordReset(Password::PASSWORD_RESET, $this->payload);
        $controller = new PasswordController();

        // Act
        $response = $controller->resetPassword($request);

        // Assert
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        $this->assertStringContainsString('Your password has been reset successfully.', $response->getData()->message);
    }

    /**
     * Tests that password reset fails with an invalid token.
     *
     * @return void
     */
    public function testResetPasswordFailsOnInvalidToken(): void
    {
        // Arrange
        $payload = array_merge($this->payload, ['token' => 'invalid-token']);
        $request = $this->mockPasswordReset(Password::INVALID_TOKEN, $payload);
        $controller = new PasswordController();

        // Act
        $response = $controller->resetPassword($request);

        // Assert
        $this->assertEquals(Response::HTTP_FORBIDDEN, $response->getStatusCode());
        $this->assertStringContainsString('The password reset token is invalid or has expired.', $response->getData()->message);
    }

    /**
     * Tests that password reset fails with an invalid user.
     *
     * @return void
     */
    public function testResetPasswordFailsOnInvalidUser(): void
    {
        // Arrange
        $payload = array_merge($this->payload, ['email' => 'non-existent@example.com']);
        $request = $this->mockPasswordReset(Password::INVALID_USER, $payload);
        $controller = new PasswordController();

        // Act
        $response = $controller->resetPassword($request);

        // Assert
        $this->assertEquals(Response::HTTP_FORBIDDEN, $response->getStatusCode());
        $this->assertStringContainsString('The password reset token is invalid or has expired.', $response->getData()->message);
    }

    /**
     * Tests that password reset fails with a throttling message.
     *
     * @return void
     */
    public function testResetPasswordFailsOnThrottling(): void
    {
        // Arrange
        $request = $this->mockPasswordReset(Password::RESET_THROTTLED, $this->payload);
        $controller = new PasswordController();

        // Act
        $response = $controller->resetPassword($request);

        // Assert
        $this->assertEquals(Response::HTTP_TOO_MANY_REQUESTS, $response->getStatusCode());
        $this->assertStringContainsString('Too many password reset attempts', $response->getData()->message);
    }

    /**
     * Tests that password reset fails with an unexpected status.
     *
     * @return void
     */
    public function testResetPasswordFailsOnUnexpectedStatus(): void
    {
        // Arrange
        $request = $this->mockPasswordReset('some_other_status', $this->payload);
        $controller = new PasswordController();

        // Act
        $response = $controller->resetPassword($request);

        // Assert
        $this->assertEquals(Response::HTTP_INTERNAL_SERVER_ERROR, $response->getStatusCode());
        $this->assertStringContainsString('Could not reset password. Please try again.', $response->getData()->message);
    }

    /**
     * Tests that the password reset callback correctly updates the user's password.
     *
     * @return void
     */
    public function testResetPasswordCallbackUpdatesUserCorrectly(): void
    {
        // Arrange
        $newPassword = 'new-password';
        $hashedPassword = 'hashed-new-password';

        // Mock the user model to verify that forceFill and save are called.
        $user = Mockery::mock();
        $user->shouldReceive('forceFill')->once()->with(['password' => $hashedPassword])->andReturnSelf();
        $user->shouldReceive('save')->once()->andReturn(true);

        // Mock the Hash facade to return a predetermined hash.
        Hash::shouldReceive('make')->once()->with($newPassword)->andReturn($hashedPassword);

        // Mock the Password facade to call the provided closure (the callback).
        Password::shouldReceive('reset')
            ->once()
            ->andReturnUsing(function ($credentials, $callback) use ($user, $newPassword) {
                $callback($user, $newPassword);
                return Password::PASSWORD_RESET;
            });

        // The request data isn't used by the mock, but we need it for the controller call.
        $request = new Request($this->payload);

        // Act
        $controller = new PasswordController();
        $response = $controller->resetPassword($request);

        // Assert
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        $this->assertStringContainsString('Your password has been reset successfully.', $response->getData()->message);
    }

    /**
     * Clean up mocks after each test.
     *
     * @return void
     */
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
