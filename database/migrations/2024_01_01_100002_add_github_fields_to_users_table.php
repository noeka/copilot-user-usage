<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('github_id')->nullable()->unique()->after('id');
            $table->string('github_login')->nullable()->unique()->after('github_id');
            $table->string('avatar_url')->nullable()->after('email');
            $table->boolean('is_admin')->default(false)->after('avatar_url');
            $table->string('password')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['github_id', 'github_login', 'avatar_url', 'is_admin']);
        });
    }
};
