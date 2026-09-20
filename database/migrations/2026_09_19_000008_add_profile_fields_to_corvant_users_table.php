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
            $table->string('avatar_url')->nullable();
            $table->string('locale')->nullable();
            $table->string('timezone')->nullable();
            $table->string('phone')->nullable();
            $table->string('pending_email')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('corvant_users', function (Blueprint $table) {
            $table->dropColumn([
                'avatar_url',
                'locale',
                'timezone',
                'phone',
                'pending_email',
            ]);
        });
    }
};
