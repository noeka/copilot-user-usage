@extends('layouts.app')

@section('title', 'Organisation Overview')

@section('content')
<div class="page-header">
    <h1>{{ config('copilot.org') }} — Organisation Overview</h1>
    <p>Copilot usage across all members — {{ $period->label() }}</p>
</div>

@include('partials.period-selector')

<div class="stats-grid" style="margin-bottom:24px;">
    <div class="stat-card">
        <div class="label">Active users</div>
        <div class="value" style="color:var(--green)">{{ $summary['active_users'] }}</div>
    </div>
    <div class="stat-card">
        <div class="label">Interactions</div>
        <div class="value" style="color:var(--purple)">{{ number_format($summary['user_initiated_interactions'] ?? $summary['chat_interactions']) }}</div>
        <div class="sub">user-initiated requests</div>
    </div>
    <div class="stat-card">
        <div class="label">Lines added</div>
        <div class="value">{{ number_format($summary['lines_accepted']) }}</div>
        <div class="sub">{{ number_format($summary['lines_deleted'] ?? 0) }} removed</div>
    </div>
    <div class="stat-card">
        <div class="label">Code generations</div>
        <div class="value" style="color:var(--accent)">{{ number_format($summary['code_suggestions']) }}</div>
    </div>
</div>

@if(count($timeSeries) > 0)
<div class="card" style="margin-bottom:24px;">
    <div class="card-header">Lines accepted — org-wide trend</div>
    <div class="card-body chart-wrap">
        @php
            $trendData = array_map(fn($r) => [$r['label'], $r['lines_accepted']], $timeSeries);
        @endphp
        {!! \Noeka\Svgraph\Chart::bar($trendData)->theme($chartTheme)->color('#3fb950')->axes()->grid() !!}
    </div>
</div>
@endif

@include('partials.token-usage')

<div class="charts-grid" style="margin-bottom:24px;">
    @if(count($byLanguage) > 0)
    <div class="card">
        <div class="card-header">Top languages (org-wide)</div>
        <div class="card-body chart-wrap">
            @php
                $langData = array_map(fn($r) => [$r['label'], $r['lines_accepted']], $byLanguage);
            @endphp
            {!! \Noeka\Svgraph\Chart::bar($langData)->theme($chartTheme)->axes()->grid() !!}
        </div>
    </div>
    @endif

    @php
        $modelData = array_values(array_filter(
            array_map(fn($r) => [$r['label'], $r['interactions']], $byModel ?? []),
            fn($r) => $r[1] > 0
        ));
    @endphp
    @if(count($modelData) > 0)
    <div class="card">
        <div class="card-header">By model — org-wide (interactions)</div>
        <div class="card-body chart-wrap">
            {!! \Noeka\Svgraph\Chart::bar($modelData)->theme($chartTheme)->rainbow()->horizontal()->axes()->grid() !!}
        </div>
    </div>
    @endif

    @if(isset($byFeature) && count($byFeature) > 0)
    <div class="card">
        <div class="card-header">By feature — org-wide (lines accepted)</div>
        <div class="card-body chart-wrap">
            @php
                $featureData = array_map(fn($r) => [$r['label'], $r['lines_accepted']], $byFeature);
            @endphp
            {!! \Noeka\Svgraph\Chart::bar($featureData)->theme($chartTheme)->axes()->grid() !!}
        </div>
    </div>
    @endif
</div>

<div class="card">
    <div class="card-header">Member leaderboard</div>
    <div class="card-body" style="padding:0;">
        @if(count($leaderboard) > 0)
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Member</th>
                    <th>Interactions</th>
                    <th>Lines added</th>
                    <th>Generations</th>
                    <th>Active days</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @foreach($leaderboard as $i => $row)
                <tr>
                    <td style="color:var(--muted); width:32px;">{{ $i + 1 }}</td>
                    <td>
                        @if($row['user']?->avatar_url)
                            <img src="{{ $row['user']->avatar_url }}" alt="" class="avatar">
                        @endif
                        {{ $row['user']?->github_login ?? '—' }}
                    </td>
                    <td><strong>{{ number_format($row['chat']) }}</strong></td>
                    <td>{{ number_format($row['lines_accepted']) }}</td>
                    <td>{{ number_format($row['suggestions']) }}</td>
                    <td>{{ $row['active_days'] }}</td>
                    <td>
                        @if($row['user'])
                        <a href="{{ route('org.member', $row['user']->github_login) }}" class="btn btn-sm">View</a>
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @else
            <div class="empty-state">
                <h3>No data yet</h3>
                <p>Run <code>php artisan copilot:sync-usage --backfill=30</code> to seed data.</p>
            </div>
        @endif
    </div>
</div>
@endsection
