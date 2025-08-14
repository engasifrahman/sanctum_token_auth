<?php

namespace Tests\Unit\Controllers\API\v1\Auth;

use Mockery;
use Tests\TestCase;
use App\Models\Role;
use App\Models\User;
use Mockery\Exception;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Event;
use Illuminate\Auth\Events\Registered;
use App\Http\Requests\API\v1\Auth\RegistrationRequest;
use App\Http\Controllers\API\v1\Auth\RegisterController;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class RegisterControllerTest extends TestCase
{
    /** @var array Default test payload */
    protected array $defaultPayload;

    /** Setup default payload before each test
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Mock Log facade to avoid writing real logs
        Log::shouldReceive('info')->byDefault();
        Log::shouldReceive('warning')->byDefault();
        Log::shouldReceive('error')->byDefault();

        // Mock the Log facade to prevent any DB connection.
        DB::shouldReceive('beginTransaction')->byDefault();
        DB::shouldReceive('commit')->byDefault();
        DB::shouldReceive('rollBack')->byDefault();

        // Common payload for registration tests
        $this->defaultPayload = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ];
    }

    /** Prepare RegistrationRequest with payload
     * @param array|null $payload
     * @return RegistrationRequest
     */
    protected function prepareRequest(?array $payload = null): RegistrationRequest
    {
        $request = new RegistrationRequest();
        $request->replace($payload ?? $this->defaultPayload);
        $request->setContainer(app())->setRedirector(app('redirect'));
        return $request;
    }

    /**
     * Mock User::create method
     * @param array|null $payload
     * @param array|null $roleIds
     * @param bool $returnNull
     * @param bool $throwException
     * @return void
     */
    protected function mockUserCreate(?array $payload = null, ?array $roleIds = null, bool $returnNull = false, bool $throwException = false): void
    {
        $payload = $payload ?? $this->defaultPayload;
        $userCreateParams = [
            'name' => $payload['name'] ?? null,
            'email' => $payload['email'] ?? null,
            'password' => $payload['password'] ?? null,
        ];

        $userMock = Mockery::mock(User::class);
        if ($returnNull) {
            $userMock->shouldReceive('create')->once()->with($userCreateParams)->andReturn(null);
        } elseif ($throwException) {
            $userMock->shouldReceive('create')->once()->with($userCreateParams)->andThrow(new Exception('Database error'));
        } else {
            $dependencyMock = Mockery::mock();
            $dependencyMock->id = 1;
            $dependencyMock->name = $payload['name'] ?? null;
            $dependencyMock->email = $payload['email'] ?? null;
            $dependencyMock->role_names = $payload['roles'] ?? [];

            if ($roleIds !== null) {
                $relationMock = Mockery::mock(BelongsToMany::class);
                $relationMock->shouldReceive('sync')->once()->with($roleIds)->andReturn(true);
                $dependencyMock->shouldReceive('roles')->once()->andReturn($relationMock);
            }

            $userMock->shouldReceive('create')->once()->with($userCreateParams)->andReturn($dependencyMock);
        }

        $this->app->instance(User::class, $userMock);
    }

    /** Fail registration when User::create returns null
     *
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     *
     * @return void
     */
    public function testFailedRegistrationWhenUserInstanceIsEmpty(): void
    {
        // Arrange
        $request = $this->prepareRequest();
        $this->mockUserCreate(returnNull: true);

        $controller = $this->app->make(RegisterController::class);

        // Act
        $response = $controller->__invoke($request);

        // Assert
        $this->assertEquals(Response::HTTP_INTERNAL_SERVER_ERROR, $response->getStatusCode());
        $this->assertStringContainsString('Registration failed. Please try again later.', $response->getContent());
    }

    /** Successful registration without roles
     *
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     *
     * @return void
     */
    public function testSuccessfulRegistrationWithoutdRoles(): void
    {
        // Arrange
        $request = $this->prepareRequest();
        $this->mockUserCreate();

        Event::shouldReceive('dispatch')->once()->with(Mockery::type(Registered::class));

        $controller = $this->app->make(RegisterController::class);

        // Act
        $response = $controller->__invoke($request);

        // Assert
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        $this->assertStringContainsString('User registered successfully', $response->getContent());
    }

    /** Successful registration with roles
     *
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     *
     * @return void
     */
    public function testSuccessfulRegistrationWithRoles(): void
    {
        // Arrange
        $payload = $this->defaultPayload + ['roles' => ['User', 'Subscriber']];
        $request = $this->prepareRequest($payload);

        $roleIds = [1, 2];
        $roleMock = Mockery::mock(Role::class);
        $roleMock->shouldReceive('getRoleIdsByNames')
                ->once()
                ->with($payload['roles'])
                ->andReturn($roleIds);

        // Bind the mock to the container
        $this->app->instance(Role::class, $roleMock);

        $this->mockUserCreate(payload: $payload, roleIds: $roleIds);

        Event::shouldReceive('dispatch')->once()->with(Mockery::type(Registered::class));

        // Inject or resolve controller
        $controller = $this->app->make(RegisterController::class);

        // Act
        $response = $controller->__invoke($request);

        // Assert
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        $this->assertStringContainsString('User registered successfully', $response->getContent());
    }

    /** Fail registration when exception is thrown
     *
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     *
     * @return void
     */
    public function testFailedRegistrationWhenExceptionOccured(): void
    {
        // Arrange
        $request = $this->prepareRequest();
        $this->mockUserCreate(throwException: true);

        $controller = $this->app->make(RegisterController::class);

        // Act
        $response = $controller->__invoke($request);

        // Assert
        $this->assertEquals(Response::HTTP_INTERNAL_SERVER_ERROR, $response->getStatusCode());
        $this->assertStringContainsString('Registration failed. Please try again later.', $response->getContent());
    }

    /** Cleanup Mockery mocks
     * @return void
     */
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
