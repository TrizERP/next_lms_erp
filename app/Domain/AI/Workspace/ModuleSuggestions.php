<?php

namespace App\Domain\AI\Workspace;

use App\Services\Mcp\McpRequestContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * What a module suggests asking next, read from `ai_suggestions`.
 *
 * This exists because the follow-up chips under an answer used to be two hard-coded
 * sentences — "Which students are at academic risk?" and "What has the system learned?"
 * — offered after *every* turn the pipeline could not follow up on its own. On the
 * Student Profiles screen that was reasonable. On the Fees screen it invited a user
 * looking at unpaid invoices to go and read about academic risk, which is both useless
 * and quietly misleading about what the assistant is for.
 *
 * The rows are the same ones the workspace panel offers as opening prompts, so a
 * module's vocabulary is configured once and shows up in both places. Administrators
 * curate them per module and per tenant; nothing here is compiled in.
 *
 * Deliberately narrow: it answers "what else is worth asking here", and nothing else.
 * `CapabilityResolver` does the richer job of merging these with prompts derived from
 * what is on screen, and needs an `AiContext` to do it — which a mid-turn answer does
 * not have and should not have to build.
 */
class ModuleSuggestions
{
    /** Two chips. More than that under an answer is a menu, not a suggestion. */
    private const LIMIT = 2;

    /**
     * Prompts worth offering after a turn in this module.
     *
     * Returns an empty list rather than a default when the module has none configured:
     * the caller decides whether "no suggestion" is better than a wrong one, and for a
     * module nobody has curated yet it usually is.
     *
     * @param  array<int, string>  $exclude  Prompts already offered on this turn.
     * @return array<int, string>
     */
    public function forModule(
        ?string $moduleKey,
        McpRequestContext $scope,
        array $exclude = []
    ): array {
        if ($moduleKey === null || $moduleKey === '' || ! Schema::hasTable('ai_suggestions')) {
            return [];
        }

        $seen = [];

        foreach ($exclude as $prompt) {
            $seen[$this->key($prompt)] = true;
        }

        $rows = DB::table('ai_suggestions')
            ->where('status', 1)
            ->where('capability', 'conversational')
            ->where('module_key', $moduleKey)
            // A tenant may override the estate-wide set; both are eligible.
            ->where(function ($inner) use ($scope) {
                $inner->whereNull('sub_institute_id')
                    ->orWhere('sub_institute_id', $scope->selectedInstituteId);
            })
            // A suggestion that only makes sense with a record on screen is not a
            // follow-up to a conversational turn, which may have no record at all.
            ->where(function ($inner) {
                $inner->whereNull('requires_entity')->orWhere('requires_entity', 0);
            })
            ->orderBy('sort_order')
            ->get(['prompt', 'label']);

        $prompts = [];

        foreach ($rows as $row) {
            $prompt = trim((string) ($row->prompt ?: $row->label));

            // A prompt with an unrendered placeholder — "Summarise {student}" — has no
            // record to render against here, and offering it verbatim shows the user a
            // template. Skip it rather than ask a question with a brace in it.
            if ($prompt === '' || str_contains($prompt, '{')) {
                continue;
            }

            $key = $this->key($prompt);

            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $prompts[] = $prompt;

            if (count($prompts) >= self::LIMIT) {
                break;
            }
        }

        return $prompts;
    }

    private function key(string $prompt): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/u', ' ', $prompt) ?? $prompt));
    }
}
