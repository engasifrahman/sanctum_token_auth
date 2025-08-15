<?php

namespace Tests\Feature\API\v1\Auth;

use Tests\TestCase;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Illuminate\Support\Facades\Hash;
use Illuminate\Foundation\Testing\RefreshDatabase;

class LoginControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The password used for testing purposes.
     *
     * @var string
     */
    private string $testPassword;

    /**
     * Set up the test environment.
     * This method runs before each test case.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->testPassword = 'password123';
    }

    /**
     * Tests that a user can successfully log in with the correct credentials.
     *
     * @return void
     */
    public function testItLogsInAUserWithCorrectCredentials(): void
    {
        // Arrange
        $user = User::factory()->create([
            'email' => 'testuser@example.com',
            'password' => Hash::make($this->testPassword),
        ]);
        $user->markEmailAsVerified();

        $payload = [
            'email' => 'testuser@example.com',
            'password' => $this->testPassword,
        ];

        // Act
        $response = $this->postJson(route('v1.auth.login'), $payload);

        // Assert
        $response->assertOk()
            ->assertJsonStructure([
                'status',
                'message',
                'data' => [
                    'access_token',
                    'token_type',
                    'expires_in',
                    'user' => [
                        'id',
                        'name',
                        'email',
                    ],
                ],
            ])
            ->assertJsonFragment([
                'email' => 'testuser@example.com',
                'message' => 'Login successful.',
            ]);
    }

    /**
     * Tests that login fails when a user provides incorrect credentials.
     *
     * @return void
     */
    public function testItFailsWithIncorrectCredentials(): void
    {
        // Arrange
        User::factory()->create([
            'email' => 'testuser@example.com',
            'password' => Hash::make($this->testPassword),
        ]);

        $payload = [
            'email' => 'testuser@example.com',
            'password' => 'wrong-password',
        ];

        // Act
        $response = $this->postJson(route('v1.auth.login'), $payload);

        // Assert
        $response->assertUnauthorized()
            ->assertJsonFragment([
                'message' => 'Invalid credentials.',
            ]);
    }

    /**
     * Tests that login fails when a user's email is not yet verified.
     *
     * @return void
     */
    public function testItFailsWhenEmailIsNotVerified(): void
    {
        // Arrange
        User::factory()->unverified()->create([
            'email' => 'unverified@example.com',
            'password' => Hash::make($this->testPassword),
        ]);

        $payload = [
            'email' => 'unverified@example.com',
            'password' => $this->testPassword,
        ];

        // Act
        $response = $this->postJson(route('v1.auth.login'), $payload);

        // Assert
        $response->assertForbidden()
            ->assertJsonFragment([
                'message' => 'Please verify your email first.',
            ]);
    }

    /**
     * Tests that login fails for a user who does not exist in the database.
     *
     * @return void
     */
    public function testItFailsWithNonExistentUser(): void
    {
        // Arrange
        $payload = [
            'email' => 'nonexistent@example.com',
            'password' => $this->testPassword,
        ];

        // Act
        $response = $this->postJson(route('v1.auth.login'), $payload);

        // Assert
        $response->assertUnauthorized()
            ->assertJsonFragment([
                'message' => 'Invalid credentials.',
            ]);
    }

    /**
     * Tests that an authenticated user can successfully create a new refresh token.
     *
     * @return void
     */
    public function testItRefreshesATokenForAnAuthenticatedUser(): void
    {
        // Arrange
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        // Act
        $response = $this->postJson(route('v1.auth.refresh-token'));

        // Assert
        $response->assertOk()
            ->assertJsonStructure([
                'status',
                'message',
                'data' => [
                    'access_token',
                    'token_type',
                    'expires_in',
                ],
            ])
            ->assertJsonFragment([
                'message' => 'Refresh token created successfully.',
            ]);
    }

    /**
     * Tests that the refresh token route fails if the user is not authenticated.
     *
     * @return void
     */
    public function testItFailsToRefreshTokenWithoutAuthentication(): void
    {
        // Arrange (No authentication token provided)
        $payload = [];

        // Act
        $response = $this->postJson(route('v1.auth.refresh-token'), $payload);

        // Assert
        $response->assertUnauthorized()
            ->assertJsonFragment([
                'message' => 'Unauthenticated.',
            ]);
    }
}
