<?php

namespace Tests\Feature\API\v1\Auth;

use Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Feature test for the LogoutController.
 */
class LogoutControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that an authenticated user can successfully log out.
     *
     * @return void
     */
    public function testAuthenticatedUserCanLogoutSuccessfully(): void
    {
        // Arrange
        $user = User::factory()->create();
        $token = $user->createToken('test_token')->plainTextToken;

        // Ensure the user has an access token before the logout attempt.
        $this->assertNotNull($token);

        // Act
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson(route('v1.auth.logout'));

        // Assert
        $response->assertOk()
                 ->assertJson([
                     'status'  => 'success',
                     'message' => 'Logged out successfully.'
                 ]);

        // Assert
        $user->refresh();
        $this->assertNull($user->currentAccessToken());
    }

    /**
     * Test that an unauthenticated user cannot log out and receives a 401 Unauthorized response.
     *
     * @return void
     */
    public function testUnauthenticatedUserCannotLogout(): void
    {
        // Arrange
        // No authentication is needed, as we are testing the unauthorized case.
        // The user is not logged in by default.

        // Act
        $response = $this->postJson(route('v1.auth.logout'));

        // Assert
        $response->assertUnauthorized()
                 ->assertJson([
                     'message' => 'Unauthenticated.'
                 ]);
    }
}
