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
     * @var User|Mockery\MockInterface
     */
    protected $user;

    /**
     * Runs before each test to set up our mocks.
     *
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Create a user mock with partial mocking to allow some real methods.
        $this->user = Mockery::mock(User::class)->makePartial();
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
        $this->assertInstanceOf(BelongsToMany::class, $this->user->roles());
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
        $this->user->setRelation('roles', $rolesCollection);

        // Act
        $roleNames = $this->user->role_names;

        // Assert
        $this->assertIsArray($roleNames);
        $this->assertEquals(['Admin', 'Editor'], $roleNames);
    }

    /**
     * Tests the hasRole method with a single string role.
     *
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     *
     * @return void
     */
    public function testHasRoleWithSingleString(): void
    {
        // Arrange
        // Mock the roles relationship and the query builder.
        $relationMock = Mockery::mock(BelongsToMany::class);
        $this->user->shouldReceive('roles')->andReturn($relationMock);
        $relationMock->shouldReceive('whereRaw')
            ->once()
            ->with('LOWER(name) IN (?)', ['admin'])
            ->andReturnSelf();
        $relationMock->shouldReceive('exists')
            ->once()
            ->andReturn(true);

        // Act
        $result = $this->user->hasRole('Admin');

        // Assert
        $this->assertTrue($result);
    }

    /**
     * Tests the hasRole method with an array of roles.
     *
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     *
     * @return void
     */
    public function testHasRoleWithArrayOfRoles(): void
    {
        // Arrange
        $relationMock = Mockery::mock(BelongsToMany::class);
        $this->user->shouldReceive('roles')->andReturn($relationMock);
        $relationMock->shouldReceive('whereRaw')
            ->once()
            ->with('LOWER(name) IN (?,?)', ['admin', 'editor'])
            ->andReturnSelf();
        $relationMock->shouldReceive('exists')
            ->once()
            ->andReturn(true);

        // Act
        $result = $this->user->hasRole(['Admin', 'Editor']);

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
        $this->user->shouldReceive('roles')->andReturn($relationMock);
        $relationMock->shouldReceive('whereRaw')
            ->once()
            ->with('LOWER(name) IN (?,?,?)', ['admin', 'editor', 'guest'])
            ->andReturnSelf();
        $relationMock->shouldReceive('exists')
            ->once()
            ->andReturn(true);

        // Act
        $result = $this->user->hasRole([' Admin ', 'editor', 'Guest']);

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
        $this->user->shouldReceive('hasRole')
            ->once()
            ->with(['Admin', 'Super Admin'])
            ->andReturn(true);

        // Act & Assert
        $this->assertTrue($this->user->isAdministrator());
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
        $this->user->shouldReceive('hasRole')
            ->once()
            ->with(['Admin', 'Super Admin'])
            ->andReturn(false);

        // Act & Assert
        $this->assertFalse($this->user->isAdministrator());
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
        $this->user->shouldReceive('hasRole')
            ->once()
            ->with('Admin')
            ->andReturn(true);

        // Act & Assert
        $this->assertTrue($this->user->isOnlyAdmin());
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
        $this->user->shouldReceive('hasRole')
            ->once()
            ->with('Super Admin')
            ->andReturn(true);

        // Act & Assert
        $this->assertTrue($this->user->isSuperAdmin());
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
        $this->user->shouldReceive('hasRole')
            ->once()
            ->with('Subscriber')
            ->andReturn(true);

        // Act & Assert
        $this->assertTrue($this->user->isSubscriber());
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
        $this->user->shouldReceive('hasRole')
            ->once()
            ->with('User')
            ->andReturn(true);

        // Act & Assert
        $this->assertTrue($this->user->isUser());
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
