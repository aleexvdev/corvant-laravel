<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('corvant_users', function (Blueprint $table) {
            $table->timestamp('email_verified_at')->nullable()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('corvant_users', function (Blueprint $table) {
            $table->dropColumn('email_verified_at');
        });
    }
};
