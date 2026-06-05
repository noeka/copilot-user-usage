<?php

namespace App\Support;

use Noeka\Svgraph\Theme;

/**
 * Chart theme tuned to GitHub's Primer dark palette so the SVG charts blend
 * into the dashboard's dark UI (see the CSS custom properties in
 * resources/views/layouts/app.blade.php).
 */
class ChartTheme
{
    public static function github(): Theme
    {
        return new Theme(
            // Multi-series / partition palette — GitHub Primer accent colors.
            palette: [
                '#58a6ff', // blue   (accent)
                '#3fb950', // green
                '#bc8cff', // purple
                '#d29922', // yellow
                '#f85149', // red
                '#db61a2', // pink
                '#39c5cf', // cyan
                '#e3b341', // amber
            ],
            stroke: '#58a6ff',
            strokeWidth: 2.0,
            fill: '#58a6ff',
            textColor: '#8b949e',   // --muted
            fontFamily: 'inherit',
            fontSize: '0.75rem',
            gridColor: '#30363d',   // --border
            axisColor: '#484f58',
            trackColor: '#30363d',
            tooltipBackground: '#161b22', // --surface
            tooltipTextColor: '#e6edf3',  // --text
            tooltipBorderRadius: '6px',
        );
    }
}
