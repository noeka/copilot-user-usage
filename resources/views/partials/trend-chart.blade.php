{{-- $timeSeries: array of [label, suggestions, acceptances, lines_accepted, chat] --}}
@php
    $linesData = array_map(fn($r) => [$r['label'], $r['lines_accepted']], $timeSeries);
    $chatData  = array_map(fn($r) => [$r['label'], $r['chat']], $timeSeries);
    $hasData = count($linesData) > 0;
@endphp

<div class="card" style="margin-bottom:16px;">
    <div class="card-header">Lines accepted per {{ $period->value }}</div>
    <div class="card-body chart-wrap">
        @if($hasData)
            {!! \Noeka\Svgraph\Chart::bar($linesData)
                ->axes()
                ->grid() !!}
        @else
            <div class="empty-state"><p>No data for this period.</p></div>
        @endif
    </div>
</div>

<div class="charts-grid">
    <div class="card">
        <div class="card-header">Code suggestions vs acceptances</div>
        <div class="card-body chart-wrap">
            @if($hasData)
                @php
                    $multiData = array_map(fn($r) => [$r['label'], $r['suggestions'], $r['acceptances']], $timeSeries);
                @endphp
                {!! \Noeka\Svgraph\Chart::line($multiData)->axes()->grid()->smooth() !!}
            @else
                <div class="empty-state"><p>No data for this period.</p></div>
            @endif
        </div>
    </div>

    <div class="card">
        <div class="card-header">Chat interactions</div>
        <div class="card-body chart-wrap">
            @if(array_sum(array_column($timeSeries, 'chat')) > 0)
                {!! \Noeka\Svgraph\Chart::bar($chatData)->axes()->grid() !!}
            @else
                <div class="empty-state"><p>No chat data for this period.</p></div>
            @endif
        </div>
    </div>
</div>
