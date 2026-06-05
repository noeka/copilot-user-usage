<?php

namespace App\Http\Controllers;

use App\Models\CopilotUser;
use App\Services\Period;
use App\Services\UsageReportService;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(private UsageReportService $report) {}

    public function index(Request $request)
    {
        $period = Period::fromRequest($request->query('period'));

        $githubId = $request->user()->github_id;
        $copilotUser = CopilotUser::where('github_id', $githubId)->first();

        $summary = $this->report->summary($copilotUser, $period);
        $timeSeries = $this->report->timeSeries($copilotUser, $period);
        $byLanguage = $this->report->breakdown($copilotUser, 'language', $period);
        $byEditor = $this->report->breakdown($copilotUser, 'editor', $period);
        $byFeature = $this->report->breakdown($copilotUser, 'feature', $period);
        $byModel = $this->report->breakdown($copilotUser, 'model', $period);

        return view('dashboard', [
            'period' => $period,
            'copilotUser' => $copilotUser,
            'summary' => $summary,
            'timeSeries' => $timeSeries,
            'byLanguage' => $byLanguage,
            'byEditor' => $byEditor,
            'byFeature' => $byFeature,
            'byModel' => $byModel,
        ]);
    }
}
