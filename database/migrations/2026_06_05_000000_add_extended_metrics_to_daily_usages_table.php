<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('daily_usages', function (Blueprint $table) {
            $table->unsignedInteger('user_initiated_interactions')->default(0);
            $table->unsignedInteger('lines_deleted')->default(0);
            $table->unsignedInteger('loc_suggested_to_delete')->default(0);

            $table->boolean('used_agent')->default(false);
            $table->boolean('used_chat')->default(false);
            $table->boolean('used_cli')->default(false);
            $table->boolean('used_copilot_coding_agent')->default(false);
            $table->boolean('used_copilot_cloud_agent')->default(false);
            $table->boolean('used_code_review_active')->default(false);
            $table->boolean('used_code_review_passive')->default(false);

            $table->unsignedTinyInteger('adoption_phase_number')->nullable();
            $table->string('adoption_phase')->nullable();

            $table->unsignedInteger('cli_session_count')->default(0);
            $table->unsignedInteger('cli_request_count')->default(0);
            $table->unsignedInteger('cli_prompt_count')->default(0);
            $table->unsignedBigInteger('cli_output_tokens')->default(0);
            $table->unsignedBigInteger('cli_prompt_tokens')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('daily_usages', function (Blueprint $table) {
            $table->dropColumn([
                'user_initiated_interactions',
                'lines_deleted',
                'loc_suggested_to_delete',
                'used_agent',
                'used_chat',
                'used_cli',
                'used_copilot_coding_agent',
                'used_copilot_cloud_agent',
                'used_code_review_active',
                'used_code_review_passive',
                'adoption_phase_number',
                'adoption_phase',
                'cli_session_count',
                'cli_request_count',
                'cli_prompt_count',
                'cli_output_tokens',
                'cli_prompt_tokens',
            ]);
        });
    }
};
