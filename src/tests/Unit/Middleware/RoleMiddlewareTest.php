<?php

namespace Tests\Unit\Middleware;

use Closure;
use Mockery;
use Tests\TestCase;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Http\Middleware\RoleMiddleware;
use Illuminate\Auth\AuthenticationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class RoleMiddlewareTest extends TestCase
{
    /**
     * @var Mockery\MockInterface
     */
    protected $userMock;

    /**
     * @var Closure
     */
    protected $next;

    /**
     * Runs before each test to set up our mocks.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Create a mock user object.
        $this->userMock = Mockery::mock();

        // Create a mock Closure that simply returns a successful response.
        $this->next = function (Request $request) {
            return new Response('Success');
        };
    }

    /**
     * A helper to create a mocked request with a user and a specific hasRole response.
     *
     * @param array $roles The roles to check against.
     * @param bool $hasRole The boolean value the hasRole method should return.
     * @return Request The mocked request object.
     */
    private function createMockedRequestWithUserAndRole(array $roles, bool $hasRole): Request
    {
        $request = Mockery::mock(Request::class);
        $request->shouldReceive('user')->once()->andReturn($this->userMock);

        $this->userMock->shouldReceive('hasRole')
            ->once()
            ->with($roles)
            ->andReturn($hasRole);

        return $request;
    }

    /**
     * Tests that an AuthenticationException is thrown if no user is authenticated.
     *
     * @return void
     */
    public function testMiddlewareThrowsExceptionIfUserNotAuthenticated(): void
    {
        // Arrange
        $request = Mockery::mock(Request::class);
        $request->shouldReceive('user')->once()->andReturn(null);

        $middleware = new RoleMiddleware();

        // Expect the AuthenticationException to be thrown.
        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('Unauthenticated.');

        // Act
        $middleware->handle($request, $this->next, 'admin');
    }

    /**
     * Tests that the middleware allows the request if the user has the required role.
     *
     * @return void
     */
    public function testMiddlewareAllowsRequestIfUserHasRole(): void
    {
        // Arrange
        $request = $this->createMockedRequestWithUserAndRole(['admin'], true);
        $middleware = new RoleMiddleware();

        // Act
        $response = $middleware->handle($request, $this->next, 'admin');

        // Assert
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        $this->assertEquals('Success', $response->getContent());
    }

    /**
     * Tests that the middleware throws an exception if the user does not have the required role.
     *
     * @return void
     */
    public function testMiddlewareThrowsExceptionIfUserDoesNotHaveRole(): void
    {
        // Arrange
        $request = $this->createMockedRequestWithUserAndRole(['admin'], false);
        $middleware = new RoleMiddleware();

        // Expect the AccessDeniedHttpException to be thrown.
        $this->expectException(AccessDeniedHttpException::class);
        $this->expectExceptionMessage('You do not have permission to access this resource.');

        // Act
        $middleware->handle($request, $this->next, 'admin');
    }

    /**
     * Tests that the middleware works with multiple roles.
     *
     * @return void
     */
    public function testMiddlewareWorksWithMultipleRoles(): void
    {
        // Arrange
        $request = $this->createMockedRequestWithUserAndRole(['admin', 'editor'], true);
        $middleware = new RoleMiddleware();

        // Act
        $response = $middleware->handle($request, $this->next, 'admin|editor');

        // Assert
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        $this->assertEquals('Success', $response->getContent());
    }

    /**
     * Runs after each test to clean up mocks.
     *
     * @return void
     */
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
