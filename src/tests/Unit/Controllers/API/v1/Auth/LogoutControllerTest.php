<?php

namespace Tests\Unit\Controllers\API\v1\Auth;

use Mockery;
use Exception;
use Tests\TestCase;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\API\v1\Auth\LogoutController;

class LogoutControllerTest extends TestCase
{
    /**
     * Runs before each test to set up our mocks.
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
     * Creates and configures a mock user and request for logout scenarios.
     *
     * @param bool $shouldTokenDeletionFail True if we want the token deletion to fail.
     * @return Request The mocked request object.
     */
    private function createAndMockLogoutRequest(bool $shouldTokenDeletionFail): Request
    {
        // Set up a fake user
        $user = Mockery::mock();
        $user->id = 1;
        $user->email = 'test@example.com';

        // Mock the token deletion process
        $user->shouldReceive('currentAccessToken')->once()->andReturnSelf();
        if ($shouldTokenDeletionFail) {
            $user->shouldReceive('delete')->once()->andThrow(new Exception('Failed to delete token.'));
        } else {
            $user->shouldReceive('delete')->once()->andReturn(true);
        }

        // Mock the incoming request and assign our fake user to it.
        $request = Mockery::mock(Request::class);
        $request->shouldReceive('user')->once()->andReturn($user);

        return $request;
    }

    /**
     * Tests a successful logout.
     *
     * @return void
     */
    public function testSuccessfulLogout(): void
    {
        // Arrange
        $request = $this->createAndMockLogoutRequest(false);

        // Act
        $controller = new LogoutController();
        $response = $controller->__invoke($request);

        // Assert
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        $this->assertEquals('Logged out successfully.', $response->getData()->message);
    }

    /**
     * Tests that logout fails gracefully if token deletion throws an exception.
     *
     * @return void
     */
    public function testLogoutFailsOnException(): void
    {
        // Arrange
        $request = $this->createAndMockLogoutRequest(true);

        // Act
        $controller = new LogoutController();
        $response = $controller->__invoke($request);

        // Assert
        $this->assertEquals(Response::HTTP_INTERNAL_SERVER_ERROR, $response->getStatusCode());
        $this->assertEquals('Failed to log out. An internal server error occurred.', $response->getData()->message);
    }
}
