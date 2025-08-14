<?php

namespace Database\Factories;

use App\Models\Role;
use Illuminate\Database\Eloquent\Factories\Factory;

class RoleFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Role::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => ucfirst($this->faker->unique()->word()),
        ];
    }

    /**
     * State for Admin role.
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    public function admin(): Factory
    {
        return $this->state(fn () => [
            'name' => 'Admin',
        ]);
    }

    /**
     * State for Super Admin role.
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    public function superAdmin(): Factory
    {
        return $this->state(fn () => ['name' => 'Super Admin']);
    }

    /**
     * State for User role.
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    public function user(): Factory
    {
        return $this->state(fn () => [
            'name' => 'User',
        ]);
    }

    /**
     * State for Subscriber role.
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    public function subscriber(): Factory
    {
        return $this->state(fn () => [
            'name' => 'Subscriber',
        ]);
    }
}
