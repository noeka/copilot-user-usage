<?php

namespace App\Http\Controllers;

use App\Services\Period;
use App\Services\UsageReportService;
use Illuminate\Http\Request;

class OrgController extends Controller
{
    public function __construct(private UsageReportService $report) {}

    public function index(Request $request)
    {
        $period = Period::fromRequest($request->query('period'));

        $summary    = $this->report->summary(null, $period);
        $timeSeries = $this->report->timeSeries(null, $period);
        $leaderboard = $this->report->leaderboard($period);
        $byLanguage = $this->report->breakdown(null, 'language', $period);
        $byFeature  = $this->report->breakdown(null, 'feature', $period);

        return view('org.index', [
            'period'      => $period,
            'summary'     => $summary,
            'timeSeries'  => $timeSeries,
            'leaderboard' => $leaderboard,
            'byLanguage'  => $byLanguage,
            'byFeature'   => $byFeature,
        ]);
    }
}
