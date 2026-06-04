@extends('layouts.app')

@section('title', $copilotUser->github_login . ' — Usage')

@section('content')
<div class="page-header">
    <div style="display:flex;align-items:center;gap:12px;margin-bottom:4px;">
        <a href="{{ route('org.index', ['period' => $period->value]) }}" style="color:var(--muted);font-size:13px;">← Organisation</a>
    </div>
    <h1>
        @if($copilotUser->avatar_url)
            <img src="{{ $copilotUser->avatar_url }}" alt="" style="width:32px;height:32px;border-radius:50%;vertical-align:middle;margin-right:8px;">
        @endif
        {{ $copilotUser->name ?? $copilotUser->github_login }}
        <span style="color:var(--muted);font-size:14px;font-weight:400;">@{{ $copilotUser->github_login }}</span>
    </h1>
    <p>Copilot usage — {{ $period->label() }}</p>
</div>

@include('partials.period-selector')
@include('partials.summary-cards')
@include('partials.trend-chart')
@include('partials.breakdown-charts')
@endsection
