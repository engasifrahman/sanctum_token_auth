<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Role extends Model
{
    use HasFactory;

    /**
     * Get the roles that belong to the User.
     *
     * This defines a many-to-many relationship between the User and Role models.
     *
     * @return BelongsToMany
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_user', 'role_id', 'user_id');
    }

    /**
     * Get role IDs by role names.
     *
     * @param array<string> $names
     * @return array<int>
     */
    public function getRoleIdsByNames(array $names): array
    {
        return $this->whereIn('name', $names)->pluck('id')->all();
    }
}
