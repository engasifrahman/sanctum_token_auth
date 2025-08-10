<?php

namespace Tests\Unit\Controllers\API\v1\Auth;

use Mockery;
use Exception;
use Tests\TestCase;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\Event;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\Http\Controllers\API\v1\Auth\EmailVerificationController;

class EmailVerificationControllerTest extends TestCase
{
    /**
     * Set up tests.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Mock the Log facade to prevent writing to a real log
        Log::shouldReceive('info')->byDefault();
        Log::shouldReceive('warning')->byDefault();
        Log::shouldReceive('error')->byDefault();
    }

    /**
     * Create a mock user.
     *
     * @param int $id
     * @param string $email
     * @param bool $isVerified
     * @return Mockery\MockInterface
     */
    private function mockUser(int $id, string $email, bool $isVerified = false): Mockery\MockInterface
    {
        $user = Mockery::mock();
        $user->id = $id;
        $user->email = $email;

        $user->shouldReceive('hasVerifiedEmail')->andReturn($isVerified);

        // We use Mockery::mock('alias:' . User::class) in tests, so we need to mock the static findOrFail
        $alias = Mockery::mock('alias:' . User::class);
        $alias->shouldReceive('findOrFail')->with($id)->andReturn($user);
        $alias->shouldReceive('where')->andReturnSelf();
        $alias->shouldReceive('first')->andReturn($user);

        return $user;
    }

    /**
     * Test a successful email verification.
     *
     * @return void
     */
    public function testEmailVerificationSucceeds(): void
    {
        // Arrange
        Event::fake(); // Don't fire the actual event
        $userEmail = 'test@example.com';
        $userId = 1;
        $hash = sha1($userEmail);

        // Mock a user who is not yet verified
        $userMock = $this->mockUser($userId, $userEmail, isVerified: false);
        $userMock->shouldReceive('markEmailAsVerified')->once()->andReturn(true);

        $controller = new EmailVerificationController();

        // Act
        $response = $controller->verifyEmailLink(new Request(), $userId, $hash);

        // Assert
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        $this->assertEquals('Email verified successfully.', $response->getData()->message);
        Event::assertDispatched(Verified::class, function ($e) use ($userMock) {
            return $e->user->id === $userMock->id;
        });
    }

    /**
     * Test that verification fails if the email is already verified.
     *
     * @return void
     */
    public function testEmailVerificationFailsIfAlreadyVerified(): void
    {
        // Arrange
        $userEmail = 'verified@example.com';
        $userId = 1;
        $hash = sha1($userEmail);

        // Mock an already-verified user
        $this->mockUser($userId, $userEmail, isVerified: true);

        $controller = new EmailVerificationController();

        // Act
        $response = $controller->verifyEmailLink(new Request(), $userId, $hash);

        // Assert
        $this->assertEquals(Response::HTTP_CONFLICT, $response->getStatusCode());
        $this->assertEquals('Email already verified.', $response->getData()->message);
    }

    /**
     * Test that verification fails if an invalid hash is provided.
     *
     * @return void
     */
    public function testEmailVerificationFailsOnInvalidHash(): void
    {
        // Arrange
        $userEmail = 'test@example.com';
        $userId = 1;
        $invalidHash = 'invalid-hash-string';

        // Mock an unverified user
        $this->mockUser($userId, $userEmail, isVerified: false);

        $controller = new EmailVerificationController();

        // Act
        $response = $controller->verifyEmailLink(new Request(), $userId, $invalidHash);

        // Assert
        $this->assertEquals(Response::HTTP_FORBIDDEN, $response->getStatusCode());
        $this->assertEquals('Invalid verification link.', $response->getData()->message);
    }

    /**
     * Test that verification fails if the user is not found.
     *
     * @return void
     */
    public function testEmailVerificationFailsIfUserNotFound(): void
    {
        // Arrange
        $userId = 999;
        $hash = 'some-hash';

        // Mock User::findOrFail to throw an exception
        $alias = Mockery::mock('alias:' . User::class);
        $alias->shouldReceive('findOrFail')->once()->with($userId)->andThrow(new ModelNotFoundException());

        $controller = new EmailVerificationController();

        // Act
        try {
            $controller->verifyEmailLink(new Request(), $userId, $hash);
        } catch (ModelNotFoundException $e) {
            // Assert
            $this->assertInstanceOf(ModelNotFoundException::class, $e);
            return;
        }

        $this->fail('ModelNotFoundException was not thrown.');
    }

    /**
     * Test that verification fails if markEmailAsVerified() returns false.
     *
     * @return void
     */
    public function testEmailVerificationFailsIfMarkEmailAsVerifiedReturnsFalse(): void
    {
        // Arrange
        $userEmail = 'test@example.com';
        $userId = 1;
        $hash = sha1($userEmail);

        // Mock an unverified user that returns false on markEmailAsVerified()
        $userMock = $this->mockUser($userId, $userEmail, isVerified: false);
        $userMock->shouldReceive('markEmailAsVerified')->once()->andReturn(false);

        $controller = new EmailVerificationController();

        // Act
        $response = $controller->verifyEmailLink(new Request(), $userId, $hash);

        // Assert
        $this->assertEquals(Response::HTTP_INTERNAL_SERVER_ERROR, $response->getStatusCode());
        $this->assertEquals('Failed to verify email. Please try again.', $response->getData()->message);
    }

    /**
     * Test that verification fails gracefully if markEmailAsVerified() throws an exception.
     *
     * @return void
     */
    public function testEmailVerificationFailsOnMarkEmailAsVerifiedException(): void
    {
        // Arrange
        $userEmail = 'test@example.com';
        $userId = 1;
        $hash = sha1($userEmail);

        // Mock an unverified user that throws an exception on markEmailAsVerified()
        $userMock = $this->mockUser($userId, $userEmail, isVerified: false);
        $userMock->shouldReceive('markEmailAsVerified')->once()->andThrow(new Exception());

        $controller = new EmailVerificationController();

        // Act
        $response = $controller->verifyEmailLink(new Request(), $userId, $hash);

        // Assert
        $this->assertEquals(Response::HTTP_INTERNAL_SERVER_ERROR, $response->getStatusCode());
        $this->assertEquals('An unexpected server error occurred during email verification. Please try again later.', $response->getData()->message);
    }

    /**
     * Test a successful resend verification email request.
     *
     * @return void
     */
    public function testResendVerificationSucceeds(): void
    {
        // Arrange
        $userEmail = 'not-verified@example.com';
        $userId = 1;
        $request = new Request(['email' => $userEmail]);

        // Mock an unverified user
        $userMock = $this->mockUser($userId, $userEmail, isVerified: false);
        $userMock->shouldReceive('sendEmailVerificationNotification')->once();

        $controller = new EmailVerificationController();

        // Act
        $response = $controller->resendVerificationEmail($request);

        // Assert
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        $this->assertEquals('Verification link sent.', $response->getData()->message);
    }

    /**
     * Test that resend fails if the user is not found.
     *
     * @return void
     */
    public function testResendVerificationFailsIfUserNotFound(): void
    {
        // Arrange
        $userEmail = 'not-found@example.com';
        $request = new Request(['email' => $userEmail]);

        // Mock User::where()->first() to return null
        $alias = Mockery::mock('alias:' . User::class);
        $alias->shouldReceive('where')->with('email', $userEmail)->andReturnSelf();
        $alias->shouldReceive('first')->andReturn(null);

        $controller = new EmailVerificationController();

        // Act
        $response = $controller->resendVerificationEmail($request);

        // Assert
        $this->assertEquals(Response::HTTP_NOT_FOUND, $response->getStatusCode());
        $this->assertEquals('User not found.', $response->getData()->message);
    }

    /**
     * Test that resend fails if the email is already verified.
     *
     * @return void
     */
    public function testResendVerificationFailsIfAlreadyVerified(): void
    {
        // Arrange
        $userEmail = 'verified@example.com';
        $userId = 1;
        $request = new Request(['email' => $userEmail]);

        // Mock an already-verified user
        $this->mockUser($userId, $userEmail, isVerified: true);

        $controller = new EmailVerificationController();

        // Act
        $response = $controller->resendVerificationEmail($request);

        // Assert
        $this->assertEquals(Response::HTTP_CONFLICT, $response->getStatusCode());
        $this->assertEquals('Email already verified.', $response->getData()->message);
    }

    /**
     * Test that resend fails gracefully if an exception occurs during notification.
     *
     * @return void
     */
    public function testResendVerificationFailsOnSendEmailNotificationException(): void
    {
        // Arrange
        $userEmail = 'test@example.com';
        $userId = 1;
        $request = new Request(['email' => $userEmail]);

        // Mock a user that throws an exception on sendEmailVerificationNotification
        $userMock = $this->mockUser($userId, $userEmail, isVerified: false);
        $userMock->shouldReceive('sendEmailVerificationNotification')->once()->andThrow(new Exception());

        $controller = new EmailVerificationController();

        // Act
        $response = $controller->resendVerificationEmail($request);

        // Assert
        $this->assertEquals(Response::HTTP_INTERNAL_SERVER_ERROR, $response->getStatusCode());
        $this->assertEquals('Failed to send verification link. Please try again later.', $response->getData()->message);
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
