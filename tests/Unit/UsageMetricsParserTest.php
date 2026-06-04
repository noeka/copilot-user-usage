<?php

namespace Tests\Unit;

use App\Services\Github\UsageMetricsParser;
use Tests\TestCase;

class UsageMetricsParserTest extends TestCase
{
    /**
     * A representative nested users-1-day record: two languages across two
     * editors, with one language (python) appearing under both editors so we
     * can assert the parser accumulates rather than overwrites.
     */
    private function sampleRow(): array
    {
        return [
            'date'       => '2026-06-03',
            'user_login' => 'alice',
            'user_id'    => 123,
            'copilot_ide_code_completions' => [
                'editors' => [
                    [
                        'name'   => 'vscode',
                        'models' => [
                            [
                                'name'      => 'default',
                                'languages' => [
                                    ['name' => 'python', 'total_code_suggestions' => 100, 'total_code_acceptances' => 40, 'total_code_lines_suggested' => 200, 'total_code_lines_accepted' => 80],
                                    ['name' => 'php',    'total_code_suggestions' => 30,  'total_code_acceptances' => 10, 'total_code_lines_suggested' => 60,  'total_code_lines_accepted' => 25],
                                ],
                            ],
                        ],
                    ],
                    [
                        'name'   => 'neovim',
                        'models' => [
                            [
                                'name'      => 'default',
                                'languages' => [
                                    ['name' => 'python', 'total_code_suggestions' => 20, 'total_code_acceptances' => 5, 'total_code_lines_suggested' => 40, 'total_code_lines_accepted' => 15],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            'copilot_ide_chat' => [
                'editors' => [
                    [
                        'name'   => 'vscode',
                        'models' => [
                            ['name' => 'default', 'total_chats' => 7],
                            ['name' => 'gpt-4o',  'total_chats' => 3],
                        ],
                    ],
                ],
            ],
        ];
    }

    public function test_identity_prefers_user_login_and_user_id(): void
    {
        $identity = (new UsageMetricsParser())->identity($this->sampleRow());

        $this->assertSame('123', $identity['github_id']);
        $this->assertSame('alice', $identity['github_login']);
    }

    public function test_identity_falls_back_to_legacy_keys(): void
    {
        $identity = (new UsageMetricsParser())->identity(['login' => 'bob', 'id' => 9]);

        $this->assertSame('9', $identity['github_id']);
        $this->assertSame('bob', $identity['github_login']);
    }

    public function test_summarize_sums_nested_code_and_chat_metrics(): void
    {
        $summary = (new UsageMetricsParser())->summarize($this->sampleRow());

        // suggestions: 100 + 30 + 20
        $this->assertSame(150, $summary['code_suggestions']);
        // acceptances: 40 + 10 + 5
        $this->assertSame(55, $summary['code_acceptances']);
        // lines suggested: 200 + 60 + 40
        $this->assertSame(300, $summary['lines_suggested']);
        // lines accepted: 80 + 25 + 15
        $this->assertSame(120, $summary['lines_accepted']);
        // chats: 7 + 3
        $this->assertSame(10, $summary['chat_interactions']);
        $this->assertTrue($summary['engaged']);
    }

    public function test_summarize_returns_zeros_and_not_engaged_for_empty_record(): void
    {
        $summary = (new UsageMetricsParser())->summarize(['user_login' => 'idle']);

        $this->assertSame(0, $summary['code_suggestions']);
        $this->assertSame(0, $summary['chat_interactions']);
        $this->assertFalse($summary['engaged']);
    }

    public function test_breakdown_by_language_accumulates_across_editors(): void
    {
        $items = (new UsageMetricsParser())->breakdownItems($this->sampleRow(), 'language');

        // python appears under both vscode and neovim: 100+20 suggestions, 80+15 lines accepted
        $this->assertSame(120, $items['python']['total_code_suggestions']);
        $this->assertSame(95, $items['python']['total_code_lines_accepted']);
        $this->assertSame(30, $items['php']['total_code_suggestions']);
    }

    public function test_breakdown_by_editor(): void
    {
        $items = (new UsageMetricsParser())->breakdownItems($this->sampleRow(), 'editor');

        $this->assertSame(130, $items['vscode']['total_code_suggestions']); // 100 + 30
        $this->assertSame(20, $items['neovim']['total_code_suggestions']);
    }
}
