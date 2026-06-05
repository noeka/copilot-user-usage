{{--
    Variables expected:
    $summary  — array from UsageReportService::summary()
    $timeSeries — array of [label, suggestions, acceptances, ...] points
--}}
@php
    $sparkPoints = array_map(fn($r) => [$r['label'], $r['lines_accepted']], $timeSeries);
@endphp

<div class="stats-grid">
    <div class="stat-card">
        <div class="label">Lines accepted</div>
        <div class="value" style="color: var(--green)">{{ number_format($summary['lines_accepted']) }}</div>
        <div class="sub">of {{ number_format($summary['lines_suggested']) }} suggested</div>
        @if(count($sparkPoints) > 1)
        <div class="sparkline">
            {!! \Noeka\Svgraph\Chart::sparkline($sparkPoints)->theme($chartTheme)->stroke('#3fb950') !!}
        </div>
        @endif
    </div>

    <div class="stat-card">
        <div class="label">Acceptance rate</div>
        <div class="value" style="color: var(--accent)">{{ $summary['acceptance_rate'] }}%</div>
        <div class="sub">{{ number_format($summary['code_acceptances']) }} / {{ number_format($summary['code_suggestions']) }} suggestions</div>
        <div class="progress-bar" style="margin-top:10px;">
            <div class="progress-bar-fill" style="width: {{ min(100, $summary['acceptance_rate']) }}%"></div>
        </div>
    </div>

    <div class="stat-card">
        <div class="label">Interactions</div>
        <div class="value" style="color: var(--purple)">{{ number_format($summary['user_initiated_interactions'] ?? $summary['chat_interactions']) }}</div>
        <div class="sub">&nbsp;</div>
    </div>

    <div class="stat-card">
        <div class="label">Active days</div>
        <div class="value">{{ number_format($summary['active_days']) }}</div>
        <div class="sub">&nbsp;</div>
    </div>
</div>
