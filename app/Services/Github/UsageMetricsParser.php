<?php

namespace App\Services\Github;

/**
 * Parses a single per-user record from the GitHub Copilot "users-1-day" usage
 * metrics report (NDJSON).
 *
 * The record is flat: code metrics live at the top level and in totals_by_* arrays.
 * This class flattens those into the columns the app stores and exposes per-dimension
 * breakdowns for the dashboard charts.
 */
class UsageMetricsParser
{
    /**
     * Resolve the user identity for a CopilotUser upsert.
     *
     * @return array{github_id: string, github_login: string, name: ?string, avatar_url: ?string}
     */
    public function identity(array $row): array
    {
        $login = (string) ($row['user_login'] ?? $row['login'] ?? $row['username'] ?? '');
        $id = (string) ($row['user_id'] ?? $row['id'] ?? '');

        return [
            'github_id' => $id,
            'github_login' => $login,
            'name' => $row['name'] ?? null,
            'avatar_url' => $row['avatar_url'] ?? null,
        ];
    }

    /**
     * Flatten a record into the stored summary columns.
     *
     * @return array{
     *     code_suggestions: int, code_acceptances: int,
     *     lines_suggested: int, lines_accepted: int,
     *     chat_interactions: int, engaged: bool
     * }
     */
    public function summarize(array $row): array
    {
        $suggestions = (int) ($row['code_generation_activity_count'] ?? 0);
        $acceptances = (int) ($row['code_acceptance_activity_count'] ?? 0);
        $linesSugg = (int) ($row['loc_suggested_to_add_sum'] ?? 0);
        $linesAcc = (int) ($row['loc_added_sum'] ?? 0);
        $interactions = (int) ($row['user_initiated_interaction_count'] ?? 0);

        $engaged = (bool) ($row['used_agent'] ?? false)
            || (bool) ($row['used_chat'] ?? false)
            || (bool) ($row['used_cli'] ?? false)
            || (bool) ($row['used_copilot_coding_agent'] ?? false)
            || (bool) ($row['used_copilot_cloud_agent'] ?? false)
            || $suggestions > 0
            || $interactions > 0;

        return [
            'code_suggestions' => $suggestions,
            'code_acceptances' => $acceptances,
            'lines_suggested' => $linesSugg,
            'lines_accepted' => $linesAcc,
            'chat_interactions' => $interactions,
            'engaged' => $engaged,
        ];
    }

    /**
     * Extract extended fields stored alongside the summary.
     *
     * @return array<string, mixed>
     */
    public function extras(array $row): array
    {
        $cli = is_array($row['totals_by_cli'] ?? null) ? $row['totals_by_cli'] : [];
        $tokenUsage = is_array($cli['token_usage'] ?? null) ? $cli['token_usage'] : [];
        $phase = is_array($row['ai_adoption_phase'] ?? null) ? $row['ai_adoption_phase'] : [];

        return [
            'user_initiated_interactions' => (int) ($row['user_initiated_interaction_count'] ?? 0),
            'lines_deleted' => (int) ($row['loc_deleted_sum'] ?? 0),
            'loc_suggested_to_delete' => (int) ($row['loc_suggested_to_delete_sum'] ?? 0),

            'used_agent' => (bool) ($row['used_agent'] ?? false),
            'used_chat' => (bool) ($row['used_chat'] ?? false),
            'used_cli' => (bool) ($row['used_cli'] ?? false),
            'used_copilot_coding_agent' => (bool) ($row['used_copilot_coding_agent'] ?? false),
            'used_copilot_cloud_agent' => (bool) ($row['used_copilot_cloud_agent'] ?? false),
            'used_code_review_active' => (bool) ($row['used_copilot_code_review_active'] ?? false),
            'used_code_review_passive' => (bool) ($row['used_copilot_code_review_passive'] ?? false),

            'adoption_phase_number' => isset($phase['phase_number']) ? (int) $phase['phase_number'] : null,
            'adoption_phase' => isset($phase['phase']) ? (string) $phase['phase'] : null,

            'cli_session_count' => (int) ($cli['session_count'] ?? 0),
            'cli_request_count' => (int) ($cli['request_count'] ?? 0),
            'cli_prompt_count' => (int) ($cli['prompt_count'] ?? 0),
            'cli_output_tokens' => (int) ($tokenUsage['output_tokens_sum'] ?? 0),
            'cli_prompt_tokens' => (int) ($tokenUsage['prompt_tokens_sum'] ?? 0),
        ];
    }

    /**
     * Accumulate code metrics grouped by a dimension (language|editor|model|feature).
     *
     * Source arrays per dimension (one source to avoid double-counting):
     *   editor   → totals_by_ide              (key: ide)
     *   model    → totals_by_model_feature    (key: model)
     *   feature  → totals_by_feature          (key: feature)
     *   language → totals_by_language_feature (key: language)
     *
     * `totals_by_model_feature` is used for the model dimension (rather than
     * totals_by_language_model) because it is the only per-model source that
     * carries user_initiated_interaction_count and that also covers CLI-only
     * usage — language_model collapses CLI activity into an "others" bucket.
     *
     * @return array<string, array{
     *     total_code_suggestions: int, total_code_acceptances: int,
     *     total_code_lines_suggested: int, total_code_lines_accepted: int,
     *     total_interactions: int
     * }>
     */
    public function breakdownItems(array $row, string $dimension): array
    {
        [$sourceKey, $groupField] = match ($dimension) {
            'editor' => ['totals_by_ide', 'ide'],
            'model' => ['totals_by_model_feature', 'model'],
            'feature' => ['totals_by_feature', 'feature'],
            default => ['totals_by_language_feature', 'language'],
        };

        $source = $row[$sourceKey] ?? [];
        if (! is_array($source)) {
            return [];
        }

        $totals = [];

        foreach ($source as $el) {
            if (! is_array($el)) {
                continue;
            }

            $key = (string) ($el[$groupField] ?? 'unknown');

            if (! isset($totals[$key])) {
                $totals[$key] = [
                    'total_code_suggestions' => 0,
                    'total_code_acceptances' => 0,
                    'total_code_lines_suggested' => 0,
                    'total_code_lines_accepted' => 0,
                    'total_interactions' => 0,
                ];
            }

            $this->sumInto($totals[$key], $el);
        }

        return $totals;
    }

    private function sumInto(array &$bucket, array $el): void
    {
        $bucket['total_code_suggestions'] += (int) ($el['code_generation_activity_count'] ?? 0);
        $bucket['total_code_acceptances'] += (int) ($el['code_acceptance_activity_count'] ?? 0);
        $bucket['total_code_lines_suggested'] += (int) ($el['loc_suggested_to_add_sum'] ?? 0);
        $bucket['total_code_lines_accepted'] += (int) ($el['loc_added_sum'] ?? 0);
        $bucket['total_interactions'] += (int) ($el['user_initiated_interaction_count'] ?? 0);
    }
}
