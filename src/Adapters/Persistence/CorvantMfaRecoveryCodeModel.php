<?php

declare(strict_types=1);

namespace Corvant\Adapters\Persistence;

use Illuminate\Database\Eloquent\Model;

final class CorvantMfaRecoveryCodeModel extends Model
{
    protected $table = 'corvant_mfa_recovery_codes';

    protected $fillable = [
        'user_id',
        'code_hash',
        'used_at',
    ];

    protected $casts = [
        'used_at' => 'datetime',
    ];
}
