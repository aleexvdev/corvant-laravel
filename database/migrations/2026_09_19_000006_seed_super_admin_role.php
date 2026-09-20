<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('corvant_roles')->insert([
            'tenant_id' => null,
            'name' => 'SuperAdmin',
            'permissions' => json_encode([], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('corvant_roles')
            ->whereNull('tenant_id')
            ->where('name', 'SuperAdmin')
            ->delete();
    }
};
