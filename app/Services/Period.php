<?php

namespace App\Services;

use Carbon\Carbon;
use Carbon\CarbonInterface;

enum Period: string
{
    case Day   = 'day';
    case Week  = 'week';
    case Month = 'month';
    case Year  = 'year';
    case All   = 'all';

    public function label(): string
    {
        return match ($this) {
            self::Day   => 'Today',
            self::Week  => 'This week',
            self::Month => 'This month',
            self::Year  => 'This year',
            self::All   => 'All time',
        };
    }

    /** Returns [from, to] Carbon dates (inclusive). */
    public function dateRange(): array
    {
        $today = Carbon::today();

        return match ($this) {
            self::Day   => [$today->copy(), $today->copy()],
            self::Week  => [$today->copy()->startOfWeek(), $today->copy()],
            self::Month => [$today->copy()->startOfMonth(), $today->copy()],
            self::Year  => [$today->copy()->startOfYear(), $today->copy()],
            self::All   => [Carbon::parse('2025-10-10'), $today->copy()],
        };
    }

    /** Sortable group key for time-series bucketing (DB-agnostic, computed in PHP). */
    public function bucketKey(CarbonInterface $date): string
    {
        return match ($this) {
            self::Year, self::All => $date->format('Y-m'),
            default               => $date->format('Y-m-d'),
        };
    }

    /** Human-friendly label for a bucket. */
    public function bucketLabel(CarbonInterface $date): string
    {
        return match ($this) {
            self::Year, self::All => $date->format('M Y'),
            default               => $date->format('M j'),
        };
    }

    public static function fromRequest(?string $value, self $default = self::Month): self
    {
        if ($value === null) {
            return $default;
        }

        return self::tryFrom($value) ?? $default;
    }
}
