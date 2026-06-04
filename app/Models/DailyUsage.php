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
    ];

    protected function casts(): array
    {
        return [
            'usage_date'       => 'date',
            'engaged'          => 'boolean',
            'raw'              => 'array',
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
