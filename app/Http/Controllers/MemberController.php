<?php

namespace App\Http\Controllers;

use App\Models\CopilotUser;
use App\Services\Period;
use App\Services\UsageReportService;
use Illuminate\Http\Request;

class MemberController extends Controller
{
    public function __construct(private UsageReportService $report) {}

    public function show(Request $request, string $login)
    {
        $copilotUser = CopilotUser::where('github_login', $login)->firstOrFail();
        $period = Period::fromRequest($request->query('period'));

        $summary    = $this->report->summary($copilotUser, $period);
        $timeSeries = $this->report->timeSeries($copilotUser, $period);
        $byLanguage = $this->report->breakdown($copilotUser, 'language', $period);
        $byEditor   = $this->report->breakdown($copilotUser, 'editor', $period);
        $byFeature  = $this->report->breakdown($copilotUser, 'feature', $period);

        return view('org.member', [
            'period'      => $period,
            'copilotUser' => $copilotUser,
            'summary'     => $summary,
            'timeSeries'  => $timeSeries,
            'byLanguage'  => $byLanguage,
            'byEditor'    => $byEditor,
            'byFeature'   => $byFeature,
        ]);
    }
}
