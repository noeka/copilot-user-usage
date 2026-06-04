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
        <div class="label">Lines accepted</div>
        <div class="value">{{ number_format($summary['lines_accepted']) }}</div>
    </div>
    <div class="stat-card">
        <div class="label">Acceptance rate</div>
        <div class="value" style="color:var(--accent)">{{ $summary['acceptance_rate'] }}%</div>
    </div>
    <div class="stat-card">
        <div class="label">Chat interactions</div>
        <div class="value" style="color:var(--purple)">{{ number_format($summary['chat_interactions']) }}</div>
    </div>
</div>

@if(count($timeSeries) > 0)
<div class="card" style="margin-bottom:24px;">
    <div class="card-header">Lines accepted — org-wide trend</div>
    <div class="card-body chart-wrap">
        @php
            $trendData = array_map(fn($r) => [$r['label'], $r['lines_accepted']], $timeSeries);
        @endphp
        {!! \Noeka\Svgraph\Chart::bar($trendData)->axes()->grid() !!}
    </div>
</div>
@endif

@if(count($byLanguage) > 0)
<div class="card" style="margin-bottom:24px;">
    <div class="card-header">Top languages (org-wide)</div>
    <div class="card-body chart-wrap">
        @php
            $langData = array_map(fn($r) => [$r['label'], $r['lines_accepted']], $byLanguage);
        @endphp
        {!! \Noeka\Svgraph\Chart::bar($langData)->axes()->grid() !!}
    </div>
</div>
@endif

<div class="card">
    <div class="card-header">Member leaderboard</div>
    <div class="card-body" style="padding:0;">
        @if(count($leaderboard) > 0)
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Member</th>
                    <th>Lines accepted</th>
                    <th>Suggestions</th>
                    <th>Acceptance</th>
                    <th>Chat</th>
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
                    <td><strong>{{ number_format($row['lines_accepted']) }}</strong></td>
                    <td>{{ number_format($row['suggestions']) }}</td>
                    <td>
                        <span class="badge {{ $row['acceptance_rate'] >= 30 ? 'badge-green' : 'badge-blue' }}">
                            {{ $row['acceptance_rate'] }}%
                        </span>
                    </td>
                    <td>{{ number_format($row['chat']) }}</td>
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
