<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('copilot_users', function (Blueprint $table) {
            $table->id();
            $table->string('github_id')->unique();
            $table->string('github_login')->unique();
            $table->string('name')->nullable();
            $table->string('avatar_url')->nullable();
            $table->timestamp('seat_assigned_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('copilot_users');
    }
};
