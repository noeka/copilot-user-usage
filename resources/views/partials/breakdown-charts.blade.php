{{-- $byLanguage, $byEditor, $byFeature, $byModel — arrays from UsageReportService::breakdown() --}}
<div class="charts-grid">
    <div class="card">
        <div class="card-header">Top languages (lines accepted)</div>
        <div class="card-body chart-wrap">
            @if(count($byLanguage) > 0)
                @php
                    $langData = array_map(fn($r) => [$r['label'], $r['lines_accepted']], $byLanguage);
                @endphp
                {!! \Noeka\Svgraph\Chart::bar($langData)->theme($chartTheme)->axes()->grid() !!}
            @else
                <div class="empty-state"><p>No language data yet.</p></div>
            @endif
        </div>
    </div>

    <div class="card">
        <div class="card-header">By model (interactions)</div>
        <div class="card-body chart-wrap">
            @php
                $modelData = array_values(array_filter(
                    array_map(fn($r) => [$r['label'], $r['interactions']], $byModel ?? []),
                    fn($r) => $r[1] > 0
                ));
            @endphp
            @if(count($modelData) > 0)
                {!! \Noeka\Svgraph\Chart::bar($modelData)->theme($chartTheme)->rainbow()->horizontal()->axes()->grid() !!}
            @else
                <div class="empty-state"><p>No model data yet.</p></div>
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
                {!! \Noeka\Svgraph\Chart::donut($editorData)->theme($chartTheme) !!}
            @else
                <div class="empty-state"><p>No editor data yet.</p></div>
            @endif
        </div>
    </div>

    <div class="card">
        <div class="card-header">By feature (lines accepted)</div>
        <div class="card-body chart-wrap">
            @if(isset($byFeature) && count($byFeature) > 0)
                @php
                    $featureData = array_map(fn($r) => [$r['label'], $r['lines_accepted']], $byFeature);
                @endphp
                {!! \Noeka\Svgraph\Chart::bar($featureData)->theme($chartTheme)->axes()->grid() !!}
            @else
                <div class="empty-state"><p>No feature data yet.</p></div>
            @endif
        </div>
    </div>
</div>
