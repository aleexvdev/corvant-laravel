<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('corvant_audit_logs', function (Blueprint $table): void {
            $table->id();
            // Audit rows are retained when the actor is deleted (nullOnDelete, not cascade).
            $table->foreignId('user_id')->nullable()->constrained('corvant_users')->nullOnDelete();
            $table->foreignId('tenant_id')->nullable()->constrained('corvant_tenants')->nullOnDelete();
            $table->string('event');
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('corvant_audit_logs');
    }
};
