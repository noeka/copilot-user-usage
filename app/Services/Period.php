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

    /** Chart bucket granularity for this period. */
    public function bucketFormat(): string
    {
        return match ($this) {
            self::Day   => 'H:00',
            self::Week  => 'D d',
            self::Month => 'M j',
            self::Year  => 'M Y',
            self::All   => 'M Y',
        };
    }

    /** SQL date_trunc / strftime bucket for grouping. */
    public function sqlBucket(): string
    {
        return match ($this) {
            self::Day   => '%Y-%m-%d',
            self::Week  => '%Y-%W',
            self::Month => '%Y-%m',
            self::Year  => '%Y-%m',
            self::All   => '%Y-%m',
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
