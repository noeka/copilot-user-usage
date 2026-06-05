@extends('layouts.app')

@section('title', 'My Usage')

@section('content')
<div class="page-header">
    <h1>
        @if($copilotUser?->avatar_url)
            <img src="{{ $copilotUser->avatar_url }}" alt="" style="width:32px;height:32px;border-radius:50%;vertical-align:middle;margin-right:8px;">
        @endif
        {{ auth()->user()->name ?? auth()->user()->github_login }}
    </h1>
    <p>Your Copilot usage — {{ $period->label() }}</p>
</div>

@include('partials.period-selector')

@if(! $copilotUser)
    <div class="empty-state card" style="padding:48px;">
        <h3>No usage data yet</h3>
        <p>Your account has not been synced yet. Data is imported daily.<br>
           Ask your admin to run <code>php artisan copilot:sync-usage --backfill=30</code> to seed historical data.</p>
    </div>
@else
    @include('partials.summary-cards')
    @include('partials.trend-chart')
    @include('partials.token-usage')
    @include('partials.breakdown-charts')
@endif
@endsection
