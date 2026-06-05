<?php

namespace Tests\Unit;

use App\Services\Github\UsageMetricsParser;
use Tests\TestCase;

class UsageMetricsParserTest extends TestCase
{
    /**
     * A representative flat users-1-day record with two languages appearing
     * under multiple features (so we can assert the parser accumulates).
     */
    private function sampleRow(): array
    {
        return [
            'day' => '2026-06-04',
            'user_login' => 'alice',
            'user_id' => 123,

            'user_initiated_interaction_count' => 10,
            'code_generation_activity_count' => 150,
            'code_acceptance_activity_count' => 55,
            'loc_suggested_to_add_sum' => 300,
            'loc_suggested_to_delete_sum' => 5,
            'loc_added_sum' => 120,
            'loc_deleted_sum' => 40,

            'used_agent' => true,
            'used_chat' => true,
            'used_cli' => false,
            'used_copilot_coding_agent' => false,
            'used_copilot_cloud_agent' => false,

            'ai_adoption_phase' => [
                'phase_number' => 2,
                'phase' => 'Phase 2',
                'version' => 'v1',
            ],

            'totals_by_ide' => [
                [
                    'ide' => 'vscode',
                    'code_generation_activity_count' => 130,
                    'code_acceptance_activity_count' => 55,
                    'loc_suggested_to_add_sum' => 300,
                    'loc_suggested_to_delete_sum' => 0,
                    'loc_added_sum' => 120,
                    'loc_deleted_sum' => 40,
                ],
                [
                    'ide' => 'neovim',
                    'code_generation_activity_count' => 20,
                    'code_acceptance_activity_count' => 0,
                    'loc_suggested_to_add_sum' => 0,
                    'loc_suggested_to_delete_sum' => 5,
                    'loc_added_sum' => 0,
                    'loc_deleted_sum' => 0,
                ],
            ],

            'totals_by_feature' => [
                [
                    'feature' => 'chat_panel_custom_mode',
                    'code_generation_activity_count' => 100,
                    'code_acceptance_activity_count' => 0,
                    'loc_suggested_to_add_sum' => 300,
                    'loc_suggested_to_delete_sum' => 0,
                    'loc_added_sum' => 0,
                    'loc_deleted_sum' => 0,
                ],
                [
                    'feature' => 'agent_edit',
                    'code_generation_activity_count' => 50,
                    'code_acceptance_activity_count' => 55,
                    'loc_suggested_to_add_sum' => 0,
                    'loc_suggested_to_delete_sum' => 5,
                    'loc_added_sum' => 120,
                    'loc_deleted_sum' => 40,
                ],
            ],

            // python appears under two features (accumulation test)
            'totals_by_language_feature' => [
                [
                    'language' => 'python',
                    'feature' => 'chat_panel_custom_mode',
                    'code_generation_activity_count' => 80,
                    'code_acceptance_activity_count' => 0,
                    'loc_suggested_to_add_sum' => 200,
                    'loc_suggested_to_delete_sum' => 0,
                    'loc_added_sum' => 0,
                    'loc_deleted_sum' => 0,
                ],
                [
                    'language' => 'python',
                    'feature' => 'agent_edit',
                    'code_generation_activity_count' => 40,
                    'code_acceptance_activity_count' => 0,
                    'loc_suggested_to_add_sum' => 0,
                    'loc_suggested_to_delete_sum' => 0,
                    'loc_added_sum' => 95,
                    'loc_deleted_sum' => 40,
                ],
                [
                    'language' => 'php',
                    'feature' => 'agent_edit',
                    'code_generation_activity_count' => 30,
                    'code_acceptance_activity_count' => 55,
                    'loc_suggested_to_add_sum' => 100,
                    'loc_suggested_to_delete_sum' => 0,
                    'loc_added_sum' => 25,
                    'loc_deleted_sum' => 0,
                ],
            ],

            // gpt-5.3-codex appears under two features (accumulation test);
            // the model dimension reads this source, not totals_by_language_model.
            'totals_by_model_feature' => [
                [
                    'model' => 'gpt-5.3-codex',
                    'feature' => 'chat_panel_agent_mode',
                    'user_initiated_interaction_count' => 7,
                    'code_generation_activity_count' => 6,
                    'code_acceptance_activity_count' => 0,
                    'loc_suggested_to_add_sum' => 0,
                    'loc_suggested_to_delete_sum' => 0,
                    'loc_added_sum' => 0,
                    'loc_deleted_sum' => 0,
                ],
                [
                    'model' => 'gpt-5.3-codex',
                    'feature' => 'agent_edit',
                    'user_initiated_interaction_count' => 3,
                    'code_generation_activity_count' => 4,
                    'code_acceptance_activity_count' => 0,
                    'loc_suggested_to_add_sum' => 0,
                    'loc_suggested_to_delete_sum' => 0,
                    'loc_added_sum' => 120,
                    'loc_deleted_sum' => 0,
                ],
                [
                    'model' => 'claude-4.5-haiku',
                    'feature' => 'copilot_cli',
                    'user_initiated_interaction_count' => 5,
                    'code_generation_activity_count' => 0,
                    'code_acceptance_activity_count' => 0,
                    'loc_suggested_to_add_sum' => 0,
                    'loc_suggested_to_delete_sum' => 0,
                    'loc_added_sum' => 0,
                    'loc_deleted_sum' => 0,
                ],
            ],
        ];
    }

    public function test_identity_prefers_user_login_and_user_id(): void
    {
        $identity = (new UsageMetricsParser)->identity($this->sampleRow());

        $this->assertSame('123', $identity['github_id']);
        $this->assertSame('alice', $identity['github_login']);
    }

    public function test_identity_falls_back_to_legacy_keys(): void
    {
        $identity = (new UsageMetricsParser)->identity(['login' => 'bob', 'id' => 9]);

        $this->assertSame('9', $identity['github_id']);
        $this->assertSame('bob', $identity['github_login']);
    }

    public function test_summarize_reads_flat_top_level_metrics(): void
    {
        $summary = (new UsageMetricsParser)->summarize($this->sampleRow());

        $this->assertSame(150, $summary['code_suggestions']);
        $this->assertSame(55, $summary['code_acceptances']);
        $this->assertSame(300, $summary['lines_suggested']);
        $this->assertSame(120, $summary['lines_accepted']);
        $this->assertSame(10, $summary['chat_interactions']);
        $this->assertTrue($summary['engaged']);
    }

    public function test_summarize_returns_zeros_and_not_engaged_for_empty_record(): void
    {
        $summary = (new UsageMetricsParser)->summarize(['user_login' => 'idle']);

        $this->assertSame(0, $summary['code_suggestions']);
        $this->assertSame(0, $summary['chat_interactions']);
        $this->assertFalse($summary['engaged']);
    }

    public function test_breakdown_by_language_accumulates_across_features(): void
    {
        $items = (new UsageMetricsParser)->breakdownItems($this->sampleRow(), 'language');

        // python appears under two features: 80+40 suggestions, 0+95 lines accepted
        $this->assertSame(120, $items['python']['total_code_suggestions']);
        $this->assertSame(95, $items['python']['total_code_lines_accepted']);
        $this->assertSame(30, $items['php']['total_code_suggestions']);
    }

    public function test_breakdown_by_editor(): void
    {
        $items = (new UsageMetricsParser)->breakdownItems($this->sampleRow(), 'editor');

        $this->assertSame(130, $items['vscode']['total_code_suggestions']);
        $this->assertSame(20, $items['neovim']['total_code_suggestions']);
        $this->assertSame(120, $items['vscode']['total_code_lines_accepted']);
    }

    public function test_breakdown_by_model_reads_model_feature_with_interactions(): void
    {
        $items = (new UsageMetricsParser)->breakdownItems($this->sampleRow(), 'model');

        // gpt-5.3-codex across two features: 7+3 interactions, 6+4 generations, 120 lines.
        $this->assertSame(10, $items['gpt-5.3-codex']['total_interactions']);
        $this->assertSame(10, $items['gpt-5.3-codex']['total_code_suggestions']);
        $this->assertSame(120, $items['gpt-5.3-codex']['total_code_lines_accepted']);
        // CLI-only model usage is captured (it has interactions but no code lines).
        $this->assertSame(5, $items['claude-4.5-haiku']['total_interactions']);
    }

    public function test_breakdown_by_feature(): void
    {
        $items = (new UsageMetricsParser)->breakdownItems($this->sampleRow(), 'feature');

        $this->assertSame(100, $items['chat_panel_custom_mode']['total_code_suggestions']);
        $this->assertSame(50, $items['agent_edit']['total_code_suggestions']);
        $this->assertSame(120, $items['agent_edit']['total_code_lines_accepted']);
    }

    public function test_extras_reads_extended_fields(): void
    {
        $row = $this->sampleRow();
        $row['totals_by_cli'] = [
            'session_count' => 2,
            'request_count' => 50,
            'prompt_count' => 5,
            'token_usage' => [
                'output_tokens_sum' => 10000,
                'prompt_tokens_sum' => 500000,
            ],
        ];

        $extras = (new UsageMetricsParser)->extras($row);

        $this->assertSame(10, $extras['user_initiated_interactions']);
        $this->assertSame(40, $extras['lines_deleted']);
        $this->assertTrue($extras['used_agent']);
        $this->assertTrue($extras['used_chat']);
        $this->assertFalse($extras['used_cli']);
        $this->assertFalse($extras['used_code_review_active']);
        $this->assertSame(2, $extras['adoption_phase_number']);
        $this->assertSame('Phase 2', $extras['adoption_phase']);
        $this->assertSame(2, $extras['cli_session_count']);
        $this->assertSame(10000, $extras['cli_output_tokens']);
        $this->assertSame(500000, $extras['cli_prompt_tokens']);
    }

    public function test_extras_returns_cli_zeros_when_no_cli(): void
    {
        $extras = (new UsageMetricsParser)->extras(['user_login' => 'idle']);

        $this->assertSame(0, $extras['cli_session_count']);
        $this->assertSame(0, $extras['cli_output_tokens']);
        $this->assertFalse($extras['used_code_review_active']);
        $this->assertFalse($extras['used_code_review_passive']);
        $this->assertNull($extras['adoption_phase']);
        $this->assertNull($extras['adoption_phase_number']);
    }

    public function test_breakdown_returns_empty_for_missing_source(): void
    {
        $items = (new UsageMetricsParser)->breakdownItems(['user_login' => 'idle'], 'language');

        $this->assertSame([], $items);
    }
}
