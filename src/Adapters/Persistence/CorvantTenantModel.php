<?php

declare(strict_types=1);

namespace Corvant\Adapters\Persistence;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

final class CorvantTenantModel extends Model
{
    protected $table = 'corvant_tenants';

    protected $fillable = [
        'name',
        'slug',
    ];

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(
            CorvantUserModel::class,
            'corvant_tenant_user',
            'tenant_id',
            'user_id',
        )->withTimestamps();
    }
}
