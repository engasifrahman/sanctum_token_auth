<?php
namespace Tests\Unit\Models;

use Mockery;
use Tests\TestCase;
use App\Models\Role;
use Mockery\MockInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class RoleTest extends TestCase
{
    /**
     * Store a Role instance for testing.
     *
     * @var MockInterface
     */
    private MockInterface $roleMock;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a mock for the Role model
        $this->roleMock = Mockery::mock(Role::class)->makePartial();
    }

    /**
     * Test the 'users' relationship method returns a BelongsToMany instance.
     *
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     *
     * @return void
     */
    public function testMockUsersRelation()
    {
        // Assert that the users method returns a BelongsToMany instance.
        // We're not testing the relationship logic here, just that the method
        // returns the correct type of relationship object.
        $this->assertInstanceOf(BelongsToMany::class, $this->roleMock->users());
    }

    /**
     * Test the getRoleIdsByNames method.
     *
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     *
     * @return void
     */
    public function testGetRoleIdsByNamesMethod()
    {
        // Arrange
        $requestedRoleNames = ['Admin', 'Super Admin'];
        $excpectedRoleIds = [1, 2];

        // Mock the query builder
        $builderMock = Mockery::mock(Builder::class);
        $builderMock->shouldReceive('pluck')->once()->with('id')->andReturn(collect($excpectedRoleIds));
        $this->roleMock->shouldReceive('whereIn')->once()->with('name', $requestedRoleNames)->andReturn($builderMock);

        // Act
        $result = $this->roleMock->getRoleIdsByNames($requestedRoleNames);

        // Assert
        $this->assertEquals($excpectedRoleIds, $result);
    }

    /**
     * Clean up Mockery mocks after each test.
     *
     * @return void
     */
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
