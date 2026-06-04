<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_usages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('copilot_user_id')->constrained()->cascadeOnDelete();
            $table->date('usage_date');

            // Extracted summary columns for fast queries
            $table->unsignedInteger('code_suggestions')->default(0);
            $table->unsignedInteger('code_acceptances')->default(0);
            $table->unsignedInteger('lines_suggested')->default(0);
            $table->unsignedInteger('lines_accepted')->default(0);
            $table->unsignedInteger('chat_interactions')->default(0);
            $table->boolean('engaged')->default(false);

            // Raw NDJSON record for future-proofing
            $table->json('raw');

            $table->timestamps();

            $table->unique(['copilot_user_id', 'usage_date']);
            $table->index('usage_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_usages');
    }
};
