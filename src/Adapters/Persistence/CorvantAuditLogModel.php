<?php

declare(strict_types=1);

namespace Corvant\Adapters\Persistence;

use Illuminate\Database\Eloquent\Model;

final class CorvantAuditLogModel extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'corvant_audit_logs';

    protected $fillable = [
        'user_id',
        'tenant_id',
        'event',
        'metadata',
        'created_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];
}
