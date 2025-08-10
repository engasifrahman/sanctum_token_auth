<?php

namespace Tests\Unit\Middleware;

use Mockery;
use Closure;
use Illuminate\Support\Str;
use Tests\TestCase;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Http\Middleware\RequestIdMiddleware;
use Symfony\Component\HttpFoundation\HeaderBag;

class RequestIdMiddlewareTest extends TestCase
{
    /**
     * @var Closure
     */
    protected $next;

    /**
     * @var Mockery\MockInterface
     */
    protected $headersMock;

    /**
     * Runs before each test to set up our mocks.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Create a mock Closure that simply returns a successful response.
        $this->next = function (Request $request) {
            return new Response();
        };

        // Create a mock for the HeaderBag.
        $this->headersMock = Mockery::mock(HeaderBag::class);
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

    /**
     * Tests that a new UUID is generated and set if no X-Request-Id header exists.
     *
     * @return void
     */
    public function testHandleSetsNewRequestIdIfNoneExists(): void
    {
        // Arrange
        $request = Mockery::mock(Request::class);

        // Mockery should return our HeaderBag mock when the headers property is accessed.
        $request->headers = $this->headersMock;
        $request->shouldReceive('header')->once()->with('X-Request-Id')->andReturn(null);

        // Set the expectation on the HeaderBag mock itself.
        $this->headersMock->shouldReceive('set')->once()->with('X-Request-Id', Mockery::on(function ($arg) {
            return Str::isUuid($arg);
        }));

        $middleware = new RequestIdMiddleware();

        // Act
        $response = $middleware->handle($request, $this->next);

        // Assert
        $this->assertNotNull($response->headers->get('X-Request-Id'));
        $this->assertTrue(Str::isUuid($response->headers->get('X-Request-Id')));
    }

    /**
     * Tests that an existing X-Request-Id header is used and not replaced.
     *
     * @return void
     */
    public function testHandleUsesExistingRequestIdIfPresent(): void
    {
        // Arrange
        $existingId = (string) Str::uuid();
        $request = Mockery::mock(Request::class);

        // Mockery should return our HeaderBag mock when the headers property is accessed.
        $request->headers = $this->headersMock;
        $request->shouldReceive('header')->once()->with('X-Request-Id')->andReturn($existingId);

        // Set the expectation on the HeaderBag mock itself.
        $this->headersMock->shouldReceive('set')->once()->with('X-Request-Id', $existingId);

        $middleware = new RequestIdMiddleware();

        // Act
        $response = $middleware->handle($request, $this->next);

        // Assert
        $this->assertEquals($existingId, $response->headers->get('X-Request-Id'));
    }
}
