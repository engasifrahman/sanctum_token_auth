<?php

namespace Tests\Feature\API\v1\Auth;

use Tests\TestCase;
use App\Models\Role;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Event;
use Illuminate\Auth\Events\Registered;
use Illuminate\Foundation\Testing\RefreshDatabase;

class RegisterControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test validation failures for invalid input data.
     *
     * @return void
     */
    public function testValidationFailsForInvalidData(): void
    {
        // Arrange
        $payload = [
            'name'                  => '',
            'email'                 => 'not-an-email',
            'password'              => '123',
            'password_confirmation' => '456',
            'roles'                 => ['NonExistingRole'],
        ];

        // Act
        $response = $this->postJson(route('v1.auth.register'), $payload);

        // Assert
        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['name', 'email', 'password', 'roles.0']);
    }

    /**
     * Test that a user can register with valid data.
     *
     * @return void
     */
    public function testItRegistersAUserWithValidData(): void
    {
        // Arrange
        Event::fake([Registered::class]);
        $userRole = Role::factory()->user()->create();

        $payload = [
            'name'                  => 'John Doe',
            'email'                 => 'John.Doe@example.com',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
            'roles'                 => ['User'],
        ];

        // Act
        $response = $this->postJson(route('v1.auth.register'), $payload);

        // Assert
        $response->assertOk()
                 ->assertJson([
                     'status' => true,
                     'message' => 'User registered successfully. Please verify your email.',
                 ]);

        $this->assertDatabaseHas('users', ['email' => 'john.doe@example.com']);
        $user = User::where('email', 'john.doe@example.com')->first();
        $this->assertTrue(Hash::check('password123', $user->password));
        $this->assertTrue($user->roles->contains('id', $userRole->id));
        Event::assertDispatched(Registered::class);
    }

    /**
     * Test failure when assigning admin roles without authentication.
     *
     * @return void
     */
    public function testItFailsWhenAssigningAdminRolesWithoutAuthentication(): void
    {
        // Arrange
        Role::factory()->admin()->create();
        $payload = [
            'name'                  => 'Jane Doe',
            'email'                 => 'jane@example.com',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
            'roles'                 => ['Admin'],
        ];

        // Act
        $response = $this->postJson(route('v1.auth.register'), $payload);

        // Assert
        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['roles']);
    }

    /**
     * Test failure when non-admin tries to assign admin roles.
     *
     * @return void
     */
    public function testItFailsWhenNonAdminAssignsAdminRoles(): void
    {
        // Arrange
        Role::factory()->admin()->create();
        $userRole = Role::factory()->user()->create();
        $nonAdmin = User::factory()->create();
        $nonAdmin->roles()->attach($userRole);
        Sanctum::actingAs($nonAdmin);

        $payload = [
            'name'                  => 'Mark Doe',
            'email'                 => 'mark@example.com',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
            'roles'                 => ['Admin'],
        ];

        // Act
        $response = $this->withHeaders([
            'Authorization' => 'Bearer test-token',
        ])->postJson(route('v1.auth.register'), $payload);

        // Assert
        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['roles'])
                 ->assertJsonFragment([
                     'roles' => ['Only existing administrators can assign Admin or Super Admin roles.']
                 ]);
    }

    /**
     * Test that an admin can assign admin roles to another user.
     *
     * @return void
     */
    public function testItAllowsAdminToAssignAdminRoles(): void
    {
        // Arrange
        Role::factory()->user()->create();
        $adminRole = Role::factory()->admin()->create();
        $admin = User::factory()->create();
        $admin->roles()->attach($adminRole);
        Sanctum::actingAs($admin);

        $payload = [
            'name'                  => 'Alice Doe',
            'email'                 => 'alice@example.com',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
            'roles'                 => ['Admin'],
        ];

        // Act
        $response = $this->withHeaders([
            'Authorization' => 'Bearer test-token',
        ])->postJson(route('v1.auth.register'), $payload);

        // Assert
        $response->assertOk();
        $this->assertDatabaseHas('users', ['email' => 'alice@example.com']);
        $this->assertTrue(
            User::where('email', 'alice@example.com')->first()->roles->contains('id', $adminRole->id)
        );
    }

    /**
     * Test failure when admin role is selected along with another role.
     *
     * @return void
     */
    public function testItFailsWhenAdminRoleSelectedWithAnotherRole(): void
    {
        // Arrange
        Role::factory()->user()->create();
        $adminRole = Role::factory()->admin()->create();
        $admin = User::factory()->create();
        $admin->roles()->attach($adminRole);
        Sanctum::actingAs($admin);

        $payload = [
            'name'                  => 'Sam Doe',
            'email'                 => 'sam@example.com',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
            'roles'                 => ['Admin', 'User'],
        ];

        // Act
        $response = $this->withHeaders([
            'Authorization' => 'Bearer test-token',
        ])->postJson(route('v1.auth.register'), $payload);

        // Assert
        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['roles'])
                 ->assertJsonFragment([
                     'roles' => ['If Admin or Super Admin is selected, no other roles are allowed.']
                 ]);
    }

    /**
     * Test that the subscriber role requires the user role.
     *
     * @return void
     */
    public function testSubscriberRoleRequiresUserRole(): void
    {
        // Arrange
        Role::factory()->subscriber()->create();
        $payload = [
            'name'                  => 'Bob Doe',
            'email'                 => 'bob@example.com',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
            'roles'                 => ['Subscriber'], // Missing User
        ];

        // Act
        $response = $this->postJson(route('v1.auth.register'), $payload);

        // Assert
        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['roles'])
                 ->assertJsonFragment([
                     'roles' => ['Subscriber role cannot be registered without the User role.']
                 ]);
    }

}
