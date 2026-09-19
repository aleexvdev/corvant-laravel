<?php

declare(strict_types=1);

namespace Corvant\Adapters\Persistence;

use Illuminate\Database\Eloquent\Model;

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
    ];

    protected $hidden = [
        'password',
    ];
}
