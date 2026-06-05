<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailyUsage extends Model
{
    protected $fillable = [
        'copilot_user_id',
        'usage_date',
        'code_suggestions',
        'code_acceptances',
        'lines_suggested',
        'lines_accepted',
        'chat_interactions',
        'engaged',
        'raw',
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
    ];

    protected function casts(): array
    {
        return [
            'usage_date'                  => 'date',
            'engaged'                     => 'boolean',
            'raw'                         => 'array',
            'used_agent'                  => 'boolean',
            'used_chat'                   => 'boolean',
            'used_cli'                    => 'boolean',
            'used_copilot_coding_agent'   => 'boolean',
            'used_copilot_cloud_agent'    => 'boolean',
            'used_code_review_active'     => 'boolean',
            'used_code_review_passive'    => 'boolean',
            'adoption_phase_number'       => 'integer',
        ];
    }

    public function copilotUser(): BelongsTo
    {
        return $this->belongsTo(CopilotUser::class);
    }

    /** Acceptance rate (0–100). */
    public function acceptanceRate(): float
    {
        if ($this->code_suggestions === 0) {
            return 0.0;
        }

        return round($this->code_acceptances / $this->code_suggestions * 100, 1);
    }
}
