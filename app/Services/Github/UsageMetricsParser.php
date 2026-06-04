<?php

namespace App\Services\Github;

/**
 * Parses a single per-user record from the GitHub Copilot "users-1-day" usage
 * metrics report (NDJSON).
 *
 * The record is nested, mirroring the documented /copilot/metrics schema: code
 * metrics live under copilot_ide_code_completions.editors[].models[].languages[]
 * and chat under copilot_ide_chat.editors[].models[]. This class flattens that
 * tree into the columns the app stores, and exposes per-dimension breakdowns.
 *
 * Field lookups keep tolerant fallbacks to older/flat key names so an
 * unexpected payload shape degrades gracefully rather than producing zeros.
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
        $id    = (string) ($row['user_id'] ?? $row['id'] ?? '');

        return [
            'github_id'    => $id,
            'github_login' => $login,
            'name'         => $row['name'] ?? null,
            'avatar_url'   => $row['avatar_url'] ?? null,
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
        $suggestions = 0;
        $acceptances = 0;
        $linesSugg   = 0;
        $linesAcc    = 0;

        foreach ($this->codeLeaves($row) as $leaf) {
            $suggestions += $this->metric($leaf, 'total_code_suggestions', 'code_suggestions', 'suggestions');
            $acceptances += $this->metric($leaf, 'total_code_acceptances', 'code_acceptances', 'acceptances');
            $linesSugg   += $this->metric($leaf, 'total_code_lines_suggested', 'total_lines_suggested', 'lines_suggested');
            $linesAcc    += $this->metric($leaf, 'total_code_lines_accepted', 'total_lines_accepted', 'lines_accepted');
        }

        $chat = 0;
        foreach ($this->chatLeaves($row) as $leaf) {
            $chat += $this->metric($leaf, 'total_chats', 'total_chat_interactions', 'chat_interactions');
        }

        return [
            'code_suggestions'  => $suggestions,
            'code_acceptances'  => $acceptances,
            'lines_suggested'   => $linesSugg,
            'lines_accepted'    => $linesAcc,
            'chat_interactions' => $chat,
            // No per-user engagement boolean exists in the schema; derive one.
            'engaged'           => (bool) ($row['is_engaged'] ?? $row['engaged'] ?? ($suggestions > 0 || $chat > 0)),
        ];
    }

    /**
     * Accumulate code metrics grouped by a dimension (language|editor|model).
     *
     * @return array<string, array{
     *     total_code_suggestions: int, total_code_acceptances: int,
     *     total_code_lines_suggested: int, total_code_lines_accepted: int
     * }>
     */
    public function breakdownItems(array $row, string $dimension): array
    {
        $totals = [];

        foreach ($this->codeLeaves($row) as $leaf) {
            $key = match ($dimension) {
                'editor'   => $leaf['editor'],
                'model'    => $leaf['model'],
                'language' => $leaf['language'],
                default    => $leaf['language'],
            };

            if (! isset($totals[$key])) {
                $totals[$key] = [
                    'total_code_suggestions'     => 0,
                    'total_code_acceptances'     => 0,
                    'total_code_lines_suggested' => 0,
                    'total_code_lines_accepted'  => 0,
                ];
            }

            $totals[$key]['total_code_suggestions']     += $this->metric($leaf, 'total_code_suggestions', 'code_suggestions', 'suggestions');
            $totals[$key]['total_code_acceptances']     += $this->metric($leaf, 'total_code_acceptances', 'code_acceptances', 'acceptances');
            $totals[$key]['total_code_lines_suggested'] += $this->metric($leaf, 'total_code_lines_suggested', 'total_lines_suggested', 'lines_suggested');
            $totals[$key]['total_code_lines_accepted']  += $this->metric($leaf, 'total_code_lines_accepted', 'total_lines_accepted', 'lines_accepted');
        }

        return $totals;
    }

    /**
     * Walk copilot_ide_code_completions.editors[].models[].languages[], yielding
     * each language leaf annotated with its editor/model/language names.
     *
     * @return iterable<array<string, mixed>>
     */
    private function codeLeaves(array $row): iterable
    {
        $editors = $row['copilot_ide_code_completions']['editors'] ?? [];
        if (! is_array($editors)) {
            return;
        }

        foreach ($editors as $editor) {
            if (! is_array($editor)) {
                continue;
            }
            $editorName = $editor['name'] ?? 'unknown';

            foreach (($editor['models'] ?? []) as $model) {
                if (! is_array($model)) {
                    continue;
                }
                $modelName = $model['name'] ?? 'unknown';

                foreach (($model['languages'] ?? []) as $language) {
                    if (! is_array($language)) {
                        continue;
                    }

                    yield $language + [
                        'editor'   => $editorName,
                        'model'    => $modelName,
                        'language' => $language['name'] ?? 'unknown',
                    ];
                }
            }
        }
    }

    /**
     * Walk copilot_ide_chat.editors[].models[], yielding each model leaf.
     *
     * @return iterable<array<string, mixed>>
     */
    private function chatLeaves(array $row): iterable
    {
        $editors = $row['copilot_ide_chat']['editors'] ?? [];
        if (! is_array($editors)) {
            return;
        }

        foreach ($editors as $editor) {
            if (! is_array($editor)) {
                continue;
            }

            foreach (($editor['models'] ?? []) as $model) {
                if (is_array($model)) {
                    yield $model;
                }
            }
        }
    }

    /**
     * Read the first present key from a leaf and cast to int.
     */
    private function metric(array $leaf, string ...$keys): int
    {
        foreach ($keys as $key) {
            if (isset($leaf[$key])) {
                return (int) $leaf[$key];
            }
        }

        return 0;
    }
}
