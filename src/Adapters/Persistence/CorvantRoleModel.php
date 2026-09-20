<?php

declare(strict_types=1);

namespace Corvant\Adapters\Persistence;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

final class CorvantRoleModel extends Model
{
    protected $table = 'corvant_roles';

    protected $fillable = [
        'tenant_id',
        'name',
        'permissions',
    ];

    protected $casts = [
        'permissions' => 'array',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(CorvantTenantModel::class, 'tenant_id');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(
            CorvantUserModel::class,
            'corvant_user_roles',
            'role_id',
            'user_id',
        )->withTimestamps();
    }
}
