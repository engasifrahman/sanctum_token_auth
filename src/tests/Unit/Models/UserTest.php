<?php

namespace Tests\Unit\Models;

use Mockery;
use Tests\TestCase;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

class UserTest extends TestCase
{
    /**
     * Mocked User instance for testing.
     *
     * @var MockInterface
     */
    protected $userMock;

    /**
     * Runs before each test to set up our mocks.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Create a user mock with partial mocking to allow some real methods.
        $this->userMock = Mockery::mock(User::class)->makePartial();
    }

    /**
     * Tests that the roles() method returns a BelongsToMany relationship.
     *
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     *
     * @return void
     */
    public function testRolesRelationship(): void
    {
        // Assert that the roles method returns a BelongsToMany instance.
        // We're not testing the relationship logic here, just that the method
        // returns the correct type of relationship object.
        $this->assertInstanceOf(BelongsToMany::class, $this->userMock->roles());
    }

    /**
     * Tests the role_names accessor.
     *
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     *
     * @return void
     */
    public function testRoleNamesAccessor(): void
    {
        // Arrange
        // Create a mock collection of roles for the user.
        $rolesCollection = new EloquentCollection([
            (object) ['name' => 'Admin'],
            (object) ['name' => 'Editor'],
        ]);
        // Set the mocked roles on the user.
        $this->userMock->setRelation('roles', $rolesCollection);

        // Act
        $roleNames = $this->userMock->role_names;

        // Assert
        $this->assertIsArray($roleNames);
        $this->assertEquals(['Admin', 'Editor'], $roleNames);
    }

    /**
     * Tests the hasRole method with a single role.
     *
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     *
     * @return void
     */
    public function testHasRoleWithSingleRole(): void
    {
        // Arrange
        // Mock the roles relationship and the query builder.
        $relationMock = Mockery::mock(BelongsToMany::class);
        $this->userMock->shouldReceive('roles')->andReturn($relationMock);
        $relationMock->shouldReceive('whereRaw')
            ->once()
            ->with('LOWER(name) IN (?)', ['admin'])
            ->andReturnSelf();
        $relationMock->shouldReceive('exists')
            ->once()
            ->andReturn(true);

        // Act
        $result = $this->userMock->hasRole('Admin');

        // Assert
        $this->assertTrue($result);
    }

    /**
     * Tests the hasRole method with multiple roles.
     *
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     *
     * @return void
     */
    public function testHasRoleWithMultipleRoles(): void
    {
        // Arrange
        $relationMock = Mockery::mock(BelongsToMany::class);
        $this->userMock->shouldReceive('roles')->andReturn($relationMock);
        $relationMock->shouldReceive('whereRaw')
            ->once()
            ->with('LOWER(name) IN (?,?)', ['admin', 'editor'])
            ->andReturnSelf();
        $relationMock->shouldReceive('exists')
            ->once()
            ->andReturn(true);

        // Act
        $result = $this->userMock->hasRole(['Admin', 'Editor']);

        // Assert
        $this->assertTrue($result);
    }

    /**
     * Tests that hasRole is case-insensitive and trims whitespace.
     *
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     *
     * @return void
     */
    public function testHasRoleIsCaseInsensitiveAndTrims(): void
    {
        // Arrange
        $relationMock = Mockery::mock(BelongsToMany::class);
        $relationMock->shouldReceive('whereRaw')
            ->once()
            ->with('LOWER(name) IN (?,?,?)', ['admin', 'editor', 'guest'])
            ->andReturnSelf();
        $relationMock->shouldReceive('exists')
            ->once()
            ->andReturn(true);
        $this->userMock->shouldReceive('roles')->andReturn($relationMock);

        // Act
        $result = $this->userMock->hasRole([' Admin ', 'editor', 'Guest']);

        // Assert
        $this->assertTrue($result);
    }

    /**
     * Tests that isAdministrator() returns true for Admin or Super Admin roles.
     *
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     *
     * @return void
     */
    public function testIsAdministratorReturnsTrueForAdminAndSuperAdmin(): void
    {
        // Arrange
        $this->userMock->shouldReceive('hasRole')
            ->once()
            ->with(['Admin', 'Super Admin'])
            ->andReturn(true);

        // Act & Assert
        $this->assertTrue($this->userMock->isAdministrator());
    }

    /**
     * Tests that isAdministrator() returns false for other roles.
     *
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     *
     * @return void
     */
    public function testIsAdministratorReturnsFalseForOtherRoles(): void
    {
        // Arrange
        $this->userMock->shouldReceive('hasRole')
            ->once()
            ->with(['Admin', 'Super Admin'])
            ->andReturn(false);

        // Act & Assert
        $this->assertFalse($this->userMock->isAdministrator());
    }

    /**
     * Tests that isOnlyAdmin() correctly calls hasRole.
     *
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     *
     * @return void
     */
    public function testIsOnlyAdminMethod(): void
    {
        // Arrange
        $this->userMock->shouldReceive('hasRole')
            ->once()
            ->with('Admin')
            ->andReturn(true);

        // Act & Assert
        $this->assertTrue($this->userMock->isOnlyAdmin());
    }

    /**
     * Tests that isSuperAdmin() correctly calls hasRole.
     *
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     *
     * @return void
     */
    public function testIsSuperAdminMethod(): void
    {
        // Arrange
        $this->userMock->shouldReceive('hasRole')
            ->once()
            ->with('Super Admin')
            ->andReturn(true);

        // Act & Assert
        $this->assertTrue($this->userMock->isSuperAdmin());
    }

    /**
     * Tests that isSubscriber() correctly calls hasRole.
     *
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     *
     * @return void
     */
    public function testIsSubscriberMethod(): void
    {
        // Arrange
        $this->userMock->shouldReceive('hasRole')
            ->once()
            ->with('Subscriber')
            ->andReturn(true);

        // Act & Assert
        $this->assertTrue($this->userMock->isSubscriber());
    }

    /**
     * Tests that isUser() correctly calls hasRole.
     *
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     *
     * @return void
     */
    public function testIsUserMethod(): void
    {
        // Arrange
        $this->userMock->shouldReceive('hasRole')
            ->once()
            ->with('User')
            ->andReturn(true);

        // Act & Assert
        $this->assertTrue($this->userMock->isUser());
    }

    /**
     * Tests that casts() returns the expected attribute casting array.
     *
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     *
     * @return void
     */
    public function testCastsMethod(): void
    {
        // Arrange
        // Create a closure that calls the protected casts() method
        $closure = function (): array {
            return $this->casts();
        };

        // Act
        // Call the closure with $this bound to the mocked user
        $result = $closure->call($this->userMock);


        // Assert
        $this->assertIsArray($result);
        $this->assertSame([
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ], $result);
    }

    /**
     * Runs after each test to clean up mocks.
     *
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     *
     * @return void
     */
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
