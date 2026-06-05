<?php

namespace App\Services;

use App\Models\DailyUsage;
use Carbon\Carbon;
use Carbon\CarbonInterface;

enum Period: string
{
    case Day = 'day';
    case Week = 'week';
    case Month = 'month';
    case Year = 'year';
    case All = 'all';

    public function label(): string
    {
        return match ($this) {
            self::Day => 'Latest day',
            self::Week => 'This week',
            self::Month => 'This month',
            self::Year => 'This year',
            self::All => 'All time',
        };
    }

    /** Returns [from, to] Carbon dates (inclusive). */
    public function dateRange(): array
    {
        $today = Carbon::today();

        return match ($this) {
            // GitHub's metrics report has no current-day data, so "Latest day"
            // resolves to the most recent day we actually have data for
            // (usually yesterday), falling back to yesterday when empty.
            self::Day => [$this->latestDataDate(), $this->latestDataDate()],
            self::Week => [$today->copy()->startOfWeek(), $today->copy()],
            self::Month => [$today->copy()->startOfMonth(), $today->copy()],
            self::Year => [$today->copy()->startOfYear(), $today->copy()],
            self::All => [Carbon::parse('2025-10-10'), $today->copy()],
        };
    }

    /** The most recent day with stored usage data, or yesterday as a fallback. */
    private function latestDataDate(): CarbonInterface
    {
        $latest = DailyUsage::max('usage_date');

        return $latest ? Carbon::parse($latest) : Carbon::yesterday();
    }

    /** Sortable group key for time-series bucketing (DB-agnostic, computed in PHP). */
    public function bucketKey(CarbonInterface $date): string
    {
        return match ($this) {
            self::Year, self::All => $date->format('Y-m'),
            default => $date->format('Y-m-d'),
        };
    }

    /** Human-friendly label for a bucket. */
    public function bucketLabel(CarbonInterface $date): string
    {
        return match ($this) {
            self::Year, self::All => $date->format('M Y'),
            default => $date->format('M j'),
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
