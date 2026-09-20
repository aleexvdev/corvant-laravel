<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('corvant_users', function (Blueprint $table): void {
            $table->text('totp_secret')->nullable();
            $table->text('pending_totp_secret')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('corvant_users', function (Blueprint $table): void {
            $table->dropColumn(['totp_secret', 'pending_totp_secret']);
        });
    }
};
