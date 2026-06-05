{{--
    Variables expected:
    $summary  — array from UsageReportService::summary()
    $timeSeries — array of buckets (label, suggestions, acceptances, lines_accepted, chat, ...)

    Cards lead with interactions and lines added: for chat/agent/CLI usage the
    completion "acceptance rate" is ~0%, so it is shown only as a secondary note
    when there are real completion acceptances, never as a headline.
--}}
@php
    // timeSeries 'chat' bucket carries the user-initiated interaction count.
    $sparkPoints  = array_map(fn($r) => [$r['label'], $r['chat']], $timeSeries);
    $interactions = $summary['user_initiated_interactions'] ?? $summary['chat_interactions'] ?? 0;
    $acceptances  = $summary['code_acceptances'] ?? 0;
@endphp

<div class="stats-grid">
    <div class="stat-card">
        <div class="label">Interactions</div>
        <div class="value" style="color: var(--purple)">{{ number_format($interactions) }}</div>
        <div class="sub">user-initiated requests</div>
        @if(count($sparkPoints) > 1)
        <div class="sparkline">
            {!! \Noeka\Svgraph\Chart::sparkline($sparkPoints)->theme($chartTheme)->stroke('#bc8cff') !!}
        </div>
        @endif
    </div>

    <div class="stat-card">
        <div class="label">Lines added</div>
        <div class="value" style="color: var(--green)">{{ number_format($summary['lines_accepted']) }}</div>
        <div class="sub">{{ number_format($summary['lines_deleted'] ?? 0) }} removed</div>
    </div>

    <div class="stat-card">
        <div class="label">Code generations</div>
        <div class="value" style="color: var(--accent)">{{ number_format($summary['code_suggestions']) }}</div>
        <div class="sub">
            @if($acceptances > 0)
                {{ $summary['acceptance_rate'] }}% accepted as completions
            @else
                code suggestions made
            @endif
        </div>
    </div>

    <div class="stat-card">
        <div class="label">Active days</div>
        <div class="value">{{ number_format($summary['active_days']) }}</div>
        <div class="sub">&nbsp;</div>
    </div>
</div>
