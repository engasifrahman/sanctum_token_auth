<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Role;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Class RoleMiddlewareFeatureTest
 *
 * Feature tests for role-based route access.
 * Tests verify that users with correct roles can access routes,
 * and users with incorrect roles are denied.
 */
class RoleMiddlewareFeatureTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test Admin route accessible by Admin user.
     *
     * @return void
     */
    public function testAdminRouteAccessibleByAdmin()
    {
        // Arrange
        $adminRole = Role::factory()->admin()->create();
        $admin = User::factory()->create();
        $admin->roles()->attach($adminRole);
        Sanctum::actingAs($admin);

        // Act
        $response = $this->getJson('api/v1/admin');

        // Assert
        $response->assertStatus(200)
                 ->assertJsonFragment(['id' => $admin->id]);
    }

    /**
     * Test Admin route forbidden for non-Admin users.
     *
     * @return void
     */
    public function testAdminRouteForbiddenForNonAdmin()
    {
        // Arrange
        $userRole = Role::factory()->user()->create();
        $user = User::factory()->create();
        $user->roles()->attach($userRole);
        Sanctum::actingAs($user);

        // Act
        $response = $this->getJson('api/v1/admin');

        // Assert
        $response->assertStatus(403);
    }

    /**
     * Test User route accessible by User role.
     *
     * @return void
     */
    public function testUserRouteAccessibleByUser()
    {
        // Arrange
        $userRole = Role::factory()->user()->create();
        $user = User::factory()->create();
        $user->roles()->attach($userRole);
        Sanctum::actingAs($user);

        // Act
        $response = $this->getJson('api/v1/user');

        // Assert
        $response->assertStatus(200)
                 ->assertJsonFragment(['id' => $user->id]);
    }

    /**
     * Test User route forbidden for non-User roles.
     *
     * @return void
     */
    public function testUserRouteForbiddenForNonUser()
    {
        // Arrange
        $subscriberRole = Role::factory()->subscriber()->create();
        $subscriber = User::factory()->create();
        $subscriber->roles()->attach($subscriberRole);
        Sanctum::actingAs($subscriber);

        // Act
        $response = $this->getJson('api/v1/user');

        // Assert
        $response->assertStatus(403);
    }

    /**
     * Test Subscriber route accessible by Subscriber role.
     *
     * @return void
     */
    public function testSubscriberRouteAccessibleBySubscriber()
    {
        // Arrange
        $subscriberRole = Role::factory()->subscriber()->create();
        $subscriber = User::factory()->create();
        $subscriber->roles()->attach($subscriberRole);
        Sanctum::actingAs($subscriber);

        // Act
        $response = $this->getJson('api/v1/subscriber');

        // Assert
        $response->assertStatus(200)
                 ->assertJsonFragment(['id' => $subscriber->id]);
    }

    /**
     * Test Subscriber route forbidden for non-Subscriber roles.
     *
     * @return void
     */
    public function testSubscriberRouteForbiddenForNonSubscriber()
    {
        // Arrange
        $adminRole = Role::factory()->admin()->create();
        $admin = User::factory()->create();
        $admin->roles()->attach($adminRole);
        Sanctum::actingAs($admin);

        // Act
        $response = $this->getJson('api/v1/subscriber');

        // Assert
        $response->assertStatus(403);
    }
}
