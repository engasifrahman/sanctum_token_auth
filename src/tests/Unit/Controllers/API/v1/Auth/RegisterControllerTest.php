<?php

namespace Tests\Unit\Controllers\API\v1\Auth;

use Mockery;
use Exception;
use Tests\TestCase;
use App\Models\Role;
use App\Models\User;
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
    protected function mockUserCreate(?array $payload = null, ?array $roleIds = null, bool $returnNull = false, bool $throwExcetion = false): void
    {
        $payload = $payload ?? $this->defaultPayload;

        $userMock = null;
        if (!$returnNull && !$throwExcetion) {
            $userMock = Mockery::mock();
            $userMock->id = 1;
            $userMock->name = $payload['name'];
            $userMock->email = $payload['email'];

            if ($roleIds !== null) {
                $relationMock = Mockery::mock(BelongsToMany::class);
                $relationMock->shouldReceive('sync')->once()->with($roleIds)->andReturn(true);
                $userMock->shouldReceive('roles')->once()->andReturn($relationMock);
            }
        }

        $aliasMock = Mockery::mock('alias:' . User::class)
            ->shouldReceive('create')
            ->once()
            ->with([
                'name' => $payload['name'] ?? null,
                'email' => $payload['email'] ?? null,
                'password' => $payload['password'] ?? null,
            ]);

        if ($throwExcetion) {
            $aliasMock->andThrow(new Exception('Database error'));
        } else {
            $aliasMock->andReturn($userMock);
        }
    }

    /** Expect a successful registration flow
     * @param int $logInfoCount
     * @return void
     */
    protected function expectSuccessfulRegistration(int $logInfoCount = 4): void
    {
        Log::shouldReceive('info')->times($logInfoCount);
        Log::shouldReceive('error')->never();
        DB::shouldReceive('beginTransaction')->once();
        DB::shouldReceive('commit')->once();
        DB::shouldReceive('rollBack')->never();
        Event::shouldReceive('dispatch')->once()->with(Mockery::type(Registered::class));
    }

    /** Expect a failed registration flow
     * @return void
     */
    protected function expectFailedRegistration(): void
    {
        Log::shouldReceive('info')->once();
        Log::shouldReceive('error')->once();
        DB::shouldReceive('beginTransaction')->once();
        DB::shouldReceive('commit')->never();
        DB::shouldReceive('rollBack')->once();
    }

    /** Fail registration when User::create returns null
     * @return void
     */
    public function testFailedRegistrationWhenUserInstanceIsEmpty(): void
    {
        // Arrange
        $request = $this->prepareRequest();
        $this->mockUserCreate(returnNull: true);

        Log::shouldReceive('info')->once();
        Log::shouldReceive('error')->once();
        DB::shouldReceive('beginTransaction')->once();
        DB::shouldReceive('commit')->never();
        DB::shouldReceive('rollBack')->never();

        $controller = new RegisterController();

        // Act
        $response = $controller->__invoke($request);

        // Assert
        $this->assertEquals(Response::HTTP_INTERNAL_SERVER_ERROR, $response->getStatusCode());
        $this->assertStringContainsString('Registration failed. Please try again later.', $response->getContent());
    }

    /** Successful registration without roles
     * @return void
     */
    public function testSuccessfulRegistrationWithoutdRoles(): void
    {
        // Arrange
        $request = $this->prepareRequest();
        $this->mockUserCreate();

        $this->expectSuccessfulRegistration();

        $controller = new RegisterController();

        // Act
        $response = $controller->__invoke($request);

        // Assert
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        $this->assertStringContainsString('User registered successfully', $response->getContent());
    }

    /** Successful registration with roles
     * @return void
     */
    public function testSuccessfulRegistrationWithRoles(): void
    {
        // Arrange
        $payload = $this->defaultPayload + ['roles' => ['User', 'Subscriber']];
        $request = $this->prepareRequest($payload);

        $roleIds = [1, 2];
        Mockery::mock('alias:' . Role::class)
            ->shouldReceive('getRoleIdsByNames')
            ->once()
            ->with($payload['roles'])
            ->andReturn($roleIds);

        $this->mockUserCreate(payload: $payload, roleIds: $roleIds);

        $this->expectSuccessfulRegistration(5);

        $controller = new RegisterController();

        // Act
        $response = $controller->__invoke($request);

        // Assert
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        $this->assertStringContainsString('User registered successfully', $response->getContent());
    }

    /** Fail registration when exception is thrown
     * @return void
     */
    public function testFailedRegistrationWhenExceptionOccured(): void
    {
        // Arrange
        $request = $this->prepareRequest();
        $this->mockUserCreate(throwExcetion: true);

        $this->expectFailedRegistration();

        $controller = new RegisterController();

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
