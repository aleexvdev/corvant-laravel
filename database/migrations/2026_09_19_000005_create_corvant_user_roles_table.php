<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('corvant_user_roles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->foreignId('role_id')->constrained('corvant_roles')->cascadeOnDelete();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('corvant_users')->cascadeOnDelete();
            $table->unique(['user_id', 'role_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('corvant_user_roles');
    }
};
