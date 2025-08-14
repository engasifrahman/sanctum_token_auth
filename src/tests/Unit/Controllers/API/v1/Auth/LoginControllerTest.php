<?php

namespace Tests\Unit\Controllers\API\v1\Auth;

use Mockery;
use Exception;
use Tests\TestCase;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use App\Http\Requests\API\v1\Auth\LoginRequest;
use App\Http\Controllers\API\v1\Auth\LoginController;

class LoginControllerTest extends TestCase
{
    /**
     * Set up for all tests in this class.
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
    }

    /**
     * Creates a LoginRequest with the given payload.
     *
     * @param array $payload
     * @return LoginRequest
     */
    private function prepareRequest(array $payload): LoginRequest
    {
        $request = new LoginRequest();
        $request->replace($payload);
        $request->setContainer(app())->setRedirector(app('redirect'));
        return $request;
    }

    /**
     * Mocks User::where()->first() to return the given user instance.
     *
     * @param mixed $userInstance
     * @return void
     */
    private function mockUserQuery($userInstance): void
    {
        $userMock = Mockery::mock('alias:' . User::class);
        $userMock->shouldReceive('where')
            ->with('email', Mockery::type('string'))
            ->andReturnSelf();
        $userMock->shouldReceive('first')
            ->andReturn($userInstance);
    }

    /**
     * Creates and mocks a user and request for refresh token tests.
     *
     * @param string $userEmail The email of the user to mock.
     * @param bool $shouldTokenDeletionFail Whether the token deletion should throw an exception.
     * @param bool $shouldTokenCreationFail Whether the token creation should throw an exception.
     * @return Request The mocked request object.
     */
    private function createAndMockUserAndRequestForRefreshToken(string $userEmail, bool $shouldTokenDeletionFail, bool $shouldTokenCreationFail): Request
    {
        // Mock the user object
        $userMock = Mockery::mock();
        $userMock->id = 1;
        $userMock->name = 'Test User';
        $userMock->email = $userEmail;

        // Mock the currentAccessToken and delete calls
        $userMock->shouldReceive('currentAccessToken')->once()->andReturnSelf();
        if ($shouldTokenDeletionFail) {
            $userMock->shouldReceive('delete')->once()->andThrow(new Exception('Token deletion failed'));
        } else {
            $userMock->shouldReceive('delete')->once()->andReturn(true);
        }

        if (!$shouldTokenDeletionFail) {
            // Mock the createToken call
            if ($shouldTokenCreationFail) {
                $userMock->shouldReceive('createToken')->once()->with($userEmail)->andThrow(new Exception('Token creation failed'));
            } else {
                $plainTextToken = bcrypt($userEmail);
                $userMock->shouldReceive('createToken')->once()->with($userEmail)->andReturn((object) ['plainTextToken' => $plainTextToken]);
            }
        }

        // Mock the request object
        $request = Mockery::mock(Request::class);
        $request->shouldReceive('user')->once()->andReturn($userMock);

        return $request;
    }

    /**
     * Login must fail if email is incorrect.
     *
     * @return void
     */
    public function testLoginFailsWithWrongEmail(): void
    {
        // Arrange
        $payload = ['email' => 'wrong@example.com', 'password' => 'secret'];
        $request = $this->prepareRequest($payload);

        $this->mockUserQuery(null);

        // Act
        $response = (new LoginController())->login($request);

        // Assert
        $this->assertEquals(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
        $this->assertEquals('Invalid credentials.', $response->getData()->message);
    }

    /**
     * Login must fail if password is incorrect.
     *
     * @return void
     */
    public function testLoginFailsWithWrongPassword(): void
    {
        // Arrange
        $payload = ['email' => 'correct@example.com', 'password' => 'secret'];
        $request = $this->prepareRequest($payload);

        $user = Mockery::mock();
        $user->id = 1;
        $user->email = $payload['email'];
        $user->password = bcrypt('different_password');
        $this->mockUserQuery($user);

        // Act
        $response = (new LoginController())->login($request);

        // Assert
        $this->assertEquals(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
        $this->assertEquals('Invalid credentials.', $response->getData()->message);
    }

    /**
     * Login must fail if email is not verified.
     *
     * @return void
     */
    public function testLoginFailsIfEmailNotVerified(): void
    {
        // Arrange
        $payload = ['email' => 'correct@example.com', 'password' => 'secret'];
        $request = $this->prepareRequest($payload);

        $user = Mockery::mock();
        $user->id = 1;
        $user->email = $payload['email'];
        $user->password = bcrypt($payload['password']);
        $user->shouldReceive('hasVerifiedEmail')->andReturn(false);
        $this->mockUserQuery($user);

        // Act
        $response = (new LoginController())->login($request);

        // Assert
        $this->assertEquals(Response::HTTP_FORBIDDEN, $response->getStatusCode());
        $this->assertEquals('Please verify your email first.', $response->getData()->message);
    }

    /**
     * Login must fail if exception occurs during token creation.
     *
     * @return void
     */
    public function testLoginFailsOnExceptionDuringTokenCreation(): void
    {
        // Arrange
        $payload = ['email' => 'correct@example.com', 'password' => 'secret'];
        $request = $this->prepareRequest($payload);

        $user = Mockery::mock();
        $user->id = 1;
        $user->email = $payload['email'];
        $user->role_names = ['User'];
        $user->password = bcrypt($payload['password']);
        $user->shouldReceive('hasVerifiedEmail')->andReturn(true);
        $user->shouldReceive('createToken')->andThrow(new Exception('Token creation failed'));
        $this->mockUserQuery($user);

        // Act
        $response = (new LoginController())->login($request);

        // Assert
        $this->assertEquals(Response::HTTP_INTERNAL_SERVER_ERROR, $response->getStatusCode());
        $this->assertEquals(
            'An unexpected error occurred during login. Please try again later.',
            $response->getData()->message
        );
    }

    /**
     * Login must succeed when credentials and verification are valid.
     *
     * @return void
     */
    public function testLoginSucceedsWhenValid(): void
    {
        // Arrange
        $payload = ['email' => 'correct@example.com', 'password' => 'secret'];
        $request = $this->prepareRequest($payload);
        $plainTextToken = bcrypt($payload['email']);

        $onlyUserData = [
            'id' => 1,
            'name' => 'Test User',
            'email' => $payload['email'],
            'role_names' => ['User'],
        ];

        // Build expected response
        $expected = (object) [
            'access_token' => $plainTextToken,
            'token_type' => 'Bearer',
            'expires_in' => config('sanctum.expiration') * 60,
            'user' => (object) $onlyUserData,
        ];

        $user = Mockery::mock();
        $user->id = 1;
        $user->email = $payload['email'];
        $user->role_names = ['User'];
        $user->password = bcrypt($payload['password']);
        $user->shouldReceive('hasVerifiedEmail')->andReturn(true);
        $user->shouldReceive('createToken')->andReturn((object) ['plainTextToken' => $plainTextToken]);
        $user->shouldReceive('only')->with(['id', 'name', 'email', 'role_names'])->andReturn($onlyUserData);
        $this->mockUserQuery($user);

        // Act
        $response = (new LoginController())->login($request);
        $data = $response->getData()->data;

        // Assert
        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $this->assertEquals('Login successful.', $response->getData()->message);
        $this->assertEquals($expected, $data);
    }

    /**
     * Refresh token must fail if an exception occurs during token deletion.
     *
     * @return void
     */
    public function testRefreshTokenFailsOnTokenDeletionException(): void
    {
        // Arrange
        $userEmail = 'test@example.com';
        $request = $this->createAndMockUserAndRequestForRefreshToken(
            $userEmail,
            $shouldTokenDeletionFail = true,
            $shouldTokenCreationFail = false
        );

        // Act
        $response = (new LoginController())->refreshToken($request);
        $data = $response->getData();

        // Assert
        $this->assertEquals(Response::HTTP_INTERNAL_SERVER_ERROR, $response->getStatusCode());
        $this->assertEquals('An unexpected error occurred during refresh token generation. Please try again later.', $data->message);
    }

    /**
     * Refresh token creation must succeed with a new token.
     *
     * @return void
     */
    public function testRefreshTokenCreationSucceeds(): void
    {
        // Arrange
        $userEmail = 'test@example.com';
        $request = $this->createAndMockUserAndRequestForRefreshToken(
            $userEmail,
            $shouldTokenDeletionFail = false,
            $shouldTokenCreationFail = false
        );

        // Act
        $response = (new LoginController())->refreshToken($request);
        $data = $response->getData();

        // Assert
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        $this->assertEquals('Refresh token created successfully.', $data->message);
    }

    /**
     * Refresh token must fail if an exception occurs during new token creation.
     *
     * @return void
     */
    public function testRefreshTokenFailsOnTokenCreationException(): void
    {
        // Arrange
        $userEmail = 'test@example.com';
        $request = $this->createAndMockUserAndRequestForRefreshToken(
            $userEmail,
            $shouldTokenDeletionFail = false,
            $shouldTokenCreationFail = true
        );

        // Act
        $response = (new LoginController())->refreshToken($request);
        $data = $response->getData();

        // Assert
        $this->assertEquals(Response::HTTP_INTERNAL_SERVER_ERROR, $response->getStatusCode());
        $this->assertEquals('An unexpected error occurred during refresh token generation. Please try again later.', $data->message);
    }

    /**
     * Cleanup Mockery mocks after each test.
     *
     * @return void
     */
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
