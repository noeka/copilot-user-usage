<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CopilotUser extends Model
{
    protected $fillable = [
        'github_id',
        'github_login',
        'name',
        'avatar_url',
        'seat_assigned_at',
    ];

    protected function casts(): array
    {
        return [
            'seat_assigned_at' => 'datetime',
        ];
    }

    public function dailyUsages(): HasMany
    {
        return $this->hasMany(DailyUsage::class);
    }
}
