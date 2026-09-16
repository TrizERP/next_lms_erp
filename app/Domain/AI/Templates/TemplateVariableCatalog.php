<?php

namespace App\Domain\AI\Templates;

/**
 * The variables the runtime fills in by itself, and what each one holds.
 *
 * WHY THIS IS WRITTEN DOWN
 *
 * A template is a string with `{{placeholders}}` in it, and an author has no way to
 * discover which placeholders will actually be filled. Get one wrong and the failure
 * is quiet in the worst way: `TemplateRegistry::render()` only raises on a *required*
 * variable, so an optional `{{total_outstanding}}` that nothing supplies reaches the
 * model as the literal text `{{total_outstanding}}` and the model invents a number to
 * go with it. That is the grounding failure the safety rules exist to prevent, caused
 * by a typo.
 *
 * So this list is the contract, taken from `WorkspaceController::pageVariables()` and
 * `generate()` — the two methods that build the variable bag for every workspace
 * generation. If either gains a variable, it belongs here too; if one is renamed here
 * without being renamed there, the editor is lying and the templates it produces break
 * silently.
 *
 * `grounding` MARKS THE ONES THAT CARRY DATA
 *
 * `records` and `metrics` are the facts. A template whose user prompt omits both is
 * asking the model to summarise a page it was never shown, which it will cheerfully
 * do from general knowledge. The editor uses this flag to warn about exactly that.
 */
class TemplateVariableCatalog
{
    /**
     * @var array<int, array{key:string, label:string, description:string, grounding:bool}>
     */
    private const VARIABLES = [
        [
            'key' => 'records',
            'label' => 'Records',
            'description' => 'The rows on screen, one per line, as "- Label (field: value, …)". The text is "none listed" when the page reported no rows.',
            'grounding' => true,
        ],
        [
            'key' => 'metrics',
            'label' => 'Figures',
            'description' => 'The totals and counters on screen, as "Label: value". The text is "none reported" when there are none.',
            'grounding' => true,
        ],
        [
            'key' => 'record_count',
            'label' => 'Total records',
            'description' => 'How many records the page or module holds in total — which can be far more than the rows listed.',
            'grounding' => false,
        ],
        [
            'key' => 'rows_shown',
            'label' => 'Rows shown',
            'description' => 'How many rows are actually in {{records}}.',
            'grounding' => false,
        ],
        [
            'key' => 'is_partial',
            'label' => 'Partial view',
            'description' => '"yes" when {{records}} is a window onto a larger set, "no" when it is everything. Include it, or the model will report a sample as the whole.',
            'grounding' => false,
        ],
        [
            'key' => 'page_title',
            'label' => 'Page title',
            'description' => 'The heading of the screen the request came from.',
            'grounding' => false,
        ],
        [
            'key' => 'page_type',
            'label' => 'Page type',
            'description' => 'dashboard, list, report or detail — what kind of screen this is.',
            'grounding' => false,
        ],
        [
            'key' => 'filters',
            'label' => 'Active filters',
            'description' => 'The filters in force, as "Label: value". The text is "none" when nothing is filtered.',
            'grounding' => false,
        ],
        [
            'key' => 'search_query',
            'label' => 'Search text',
            'description' => 'What the user typed into the page search, if anything.',
            'grounding' => false,
        ],
        [
            'key' => 'data_source',
            'label' => 'Data source',
            'description' => '"the page", "the module" or "none" — where the records came from. Useful in a safety rule that must not describe missing data as empty data.',
            'grounding' => false,
        ],
        [
            'key' => 'module',
            'label' => 'Module label',
            'description' => 'The module the request came from, as a human label — "Fees", "Attendance".',
            'grounding' => false,
        ],
        [
            'key' => 'entity_label',
            'label' => 'Record label',
            'description' => 'The name of the selected record, when one is selected. Empty on a list or report screen.',
            'grounding' => false,
        ],
    ];

    /**
     * @return array<int, array{key:string, label:string, description:string, grounding:bool}>
     */
    public function all(): array
    {
        return self::VARIABLES;
    }

    /** @return array<int, string> */
    public function keys(): array
    {
        return array_column(self::VARIABLES, 'key');
    }

    /** @return array<int, string> The ones that carry the data a summary must rest on. */
    public function groundingKeys(): array
    {
        return array_column(
            array_filter(self::VARIABLES, fn (array $variable) => $variable['grounding']),
            'key'
        );
    }

    /**
     * Placeholders used in a prompt that nothing will ever fill.
     *
     * Only reports names outside the catalogue *and* outside the template's own
     * declared variables — a template may legitimately declare an extra variable that
     * its caller passes by hand, and flagging those would make the warning noise.
     *
     * @param array<int, array<string, mixed>> $declared
     * @return array<int, string>
     */
    public function unresolvable(string $prompt, array $declared = []): array
    {
        preg_match_all('/\{\{\s*([a-zA-Z0-9_.]+)\s*\}\}/', $prompt, $matches);

        $used = array_unique($matches[1] ?? []);
        $known = array_merge($this->keys(), array_filter(array_column($declared, 'key')));

        return array_values(array_diff($used, $known));
    }

    /**
     * Every placeholder a prompt refers to, in the order it first mentions them.
     *
     * @return array<int, string>
     */
    public function used(string $prompt): array
    {
        preg_match_all('/\{\{\s*([a-zA-Z0-9_.]+)\s*\}\}/', $prompt, $matches);

        return array_values(array_unique($matches[1] ?? []));
    }
}
