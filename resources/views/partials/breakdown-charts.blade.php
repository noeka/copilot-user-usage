{{-- $byLanguage, $byEditor — arrays from UsageReportService::breakdown() --}}
<div class="charts-grid">
    <div class="card">
        <div class="card-header">Top languages (lines accepted)</div>
        <div class="card-body chart-wrap">
            @if(count($byLanguage) > 0)
                @php
                    $langData = array_map(fn($r) => [$r['label'], $r['lines_accepted']], $byLanguage);
                @endphp
                {!! \Noeka\Svgraph\Chart::bar($langData)->axes()->grid() !!}
            @else
                <div class="empty-state"><p>No language data yet.</p></div>
            @endif
        </div>
    </div>

    <div class="card">
        <div class="card-header">By editor</div>
        <div class="card-body chart-wrap">
            @if(count($byEditor) > 0)
                @php
                    $editorData = array_map(fn($r) => [$r['label'], $r['lines_accepted']], $byEditor);
                @endphp
                {!! \Noeka\Svgraph\Chart::donut($editorData) !!}
            @else
                <div class="empty-state"><p>No editor data yet.</p></div>
            @endif
        </div>
    </div>
</div>
