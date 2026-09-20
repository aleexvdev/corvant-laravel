<?php

declare(strict_types=1);

namespace Corvant\Adapters\Persistence;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Corvant-owned identity storage (table corvant_users).
 * Consumer app User models integrate via future Authenticatable adapters — not this table.
 */
final class CorvantUserModel extends Model
{
    protected $table = 'corvant_users';

    protected $fillable = [
        'email',
        'password',
        'name',
        'email_verified_at',
        'avatar_url',
        'locale',
        'timezone',
        'phone',
        'pending_email',
        'totp_secret',
        'pending_totp_secret',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'totp_secret' => 'encrypted',
        'pending_totp_secret' => 'encrypted',
    ];

    protected $hidden = [
        'password',
        'totp_secret',
        'pending_totp_secret',
    ];

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(
            CorvantRoleModel::class,
            'corvant_user_roles',
            'user_id',
            'role_id',
        )->withTimestamps();
    }
}
