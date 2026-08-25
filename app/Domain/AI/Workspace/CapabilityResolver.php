<?php

namespace App\Domain\AI\Workspace;

use App\Domain\AI\Agents\AgentManifest;
use App\Domain\AI\Agents\AgentRegistry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Decides what each workspace tab offers for the context the user is actually in.
 *
 * Three rules, all of them about not wasting the user's attention:
 *
 *  1. A suggestion whose binding no longer exists is dropped. If a row points at an
 *     agent that was disabled or a template that was unpublished, showing it would
 *     produce a button that fails on click.
 *  2. `requires_entity` suggestions are hidden on list pages. Student-specific
 *     actions must not appear on a fees list, which is exactly what the brief asks
 *     for and what a naive "show everything" resolver gets wrong.
 *  3. Role and permission filters run before anything is returned, so the panel is
 *     never the place a user first discovers they are not allowed to do something.
 */
class CapabilityResolver
{
    public const CAPABILITIES = ['conversational', 'generative', 'agent', 'workflow', 'ontology'];

    /**
     * Six. The brief asks not to overwhelm the user, and a suggestion list long enough
     * to scroll is a list nobody reads.
     */
    private const MAX_CONVERSATIONAL = 6;

    public function __construct(
        private readonly AgentRegistry $agents,
        private readonly PageTypeResolver $pageTypes,
    ) {
    }

    /**
     * Everything the workspace should render, keyed by capability.
     *
     * @return array<string, array<int, array>>
     */
    public function resolve(AiContext $context): array
    {
        $resolved = [];

        foreach (self::CAPABILITIES as $capability) {
            if (! $context->supports($capability)) {
                continue;
            }

            $resolved[$capability] = match ($capability) {
                'agent' => $this->agentSuggestions($context),
                'workflow' => $this->workflowSuggestions($context),
                'ontology' => $this->ontologySuggestions($context),
                // Conversation is the one tab that must never open empty, so what the
                // administrator configured is topped up with prompts derived from what
                // the page is actually showing.
                'conversational' => $this->conversationalSuggestions($context),
                default => $this->configuredSuggestions($context, $capability),
            };
        }

        return $resolved;
    }

    /**
     * Conversational and Gen AI suggestions come straight from `ai_suggestions`,
     * with prompts rendered against the current record.
     */
    /**
     * The actions a page type offers, independent of module.
     *
     * This is what makes "Analysis on every dashboard" a single implementation rather
     * than one per module. The action binds to a shared template — `k12.analyse.list`
     * serves the fee defaulter list and the library loan list alike, because the
     * template is rendered against the page snapshot rather than against a module.
     *
     * Dropped silently if the template is not published, on the same principle as
     * everywhere else here: a button that cannot run is worse than no button.
     *
     * @param  string  $kind  'analysis' | 'generation'
     * @param  string  $actionType  the action_type the frontend dispatches on
     */
    private function pageTypeActions(AiContext $context, string $kind, string $actionType): array
    {
        $action = $kind === 'analysis'
            ? $this->pageTypes->analysisFor($context->pageType)
            : $this->pageTypes->generationFor($context->pageType);

        if ($action === null || ! $this->templateExists($action['template'], $context)) {
            return [];
        }

        // An analysis of nothing is not worth offering. A detail page is exempt: the
        // record itself is the subject, and it needs no rows on screen.
        if ($kind === 'analysis'
            && $context->pageType !== 'detail'
            && ! $context->page->hasRecords()
            && $context->page->metrics === []) {
            return [];
        }

        return [[
            'key' => $actionType . ':' . $context->pageType,
            'label' => $action['label'],
            'description' => $this->describePageTypeAction($context, $kind),
            'icon' => $kind === 'analysis' ? 'bar-chart' : 'sparkles',
            'action_type' => $actionType,
            'action_ref' => $action['template'],
            'requires_entity' => $context->pageType === 'detail',
            'payload' => ['page_type' => $context->pageType],
            'source' => 'page_type',
        ]];
    }

    /** Says what the action will actually look at, so it is not a leap of faith. */
    private function describePageTypeAction(AiContext $context, string $kind): ?string
    {
        if ($kind !== 'analysis') {
            return null;
        }

        $parts = [];

        if ($context->page->recordCount > 0) {
            $parts[] = $context->page->recordCount . ' ' . $this->recordNoun($context);
        }

        if ($context->page->metrics !== []) {
            $parts[] = count($context->page->metrics) . ' figures';
        }

        if ($context->page->isFiltered()) {
            $parts[] = 'the current filters';
        }

        return $parts === [] ? null : 'Looks at ' . implode(', ', $parts) . ' on this page.';
    }

    private function configuredSuggestions(AiContext $context, string $capability): array
    {
        $rows = $this->suggestionRows($context, $capability);
        $suggestions = $capability === 'generative'
            ? $this->pageTypeActions($context, 'generation', 'generate')
            : [];

        foreach ($rows as $row) {
            // A generate action must point at a template that is actually published.
            if ($row->action_type === 'generate' && ! $this->templateExists($row->action_ref, $context)) {
                continue;
            }

            $suggestions[] = [
                'key' => $row->action_ref ?: ('suggestion-' . $row->id),
                'label' => $row->label,
                'description' => $row->description,
                'icon' => $row->icon,
                'action_type' => $row->action_type,
                'action_ref' => $row->action_ref,
                'prompt' => $context->renderPrompt($row->prompt),
                'payload' => $this->decode($row->payload),
                'requires_entity' => (bool) $row->requires_entity,
                'source' => 'configured',
            ];
        }

        return $suggestions;
    }

    /**
     * The conversational tab: configured prompts first, then prompts derived from what
     * the page reported it is showing.
     *
     * The configured rows are the ones an administrator curated for this module and
     * they lead. The derived ones exist because a config row cannot know that the list
     * is filtered to Standard 8, that three rows are ticked, or that this is one of the
     * forty-odd route folders nobody has mapped yet. Without them the panel opens with
     * the same four static prompts everywhere, which is precisely the behaviour this
     * replaces.
     *
     * Deliberately rule-based rather than generated: this runs on every panel open and
     * every navigation, and paying for a model round-trip to produce "Summarise these
     * 42 records" would be a poor trade.
     */
    private function conversationalSuggestions(AiContext $context): array
    {
        $suggestions = $this->configuredSuggestions($context, 'conversational');
        $seen = [];

        foreach ($suggestions as $suggestion) {
            $seen[$this->promptKey($suggestion['prompt'] ?? $suggestion['label'])] = true;
        }

        foreach ($this->derivedConversational($context) as $derived) {
            if (count($suggestions) >= self::MAX_CONVERSATIONAL) {
                break;
            }

            $key = $this->promptKey($derived['prompt']);

            // A curated row saying the same thing wins; the derived twin is dropped
            // rather than shown beside it.
            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $suggestions[] = [
                'key' => $derived['key'],
                'label' => $derived['label'],
                'description' => $derived['description'] ?? null,
                'icon' => $derived['icon'] ?? null,
                'action_type' => 'prompt',
                'action_ref' => null,
                'prompt' => $derived['prompt'],
                'payload' => [],
                'requires_entity' => false,
                'source' => 'derived',
            ];
        }

        return array_slice($suggestions, 0, self::MAX_CONVERSATIONAL);
    }

    /**
     * Prompts read off the current page, most specific first.
     *
     * Ordering is the whole point. A user looking at one student wants questions about
     * that student before questions about the module; a user who has just filtered a
     * list wants questions about the filtered set before generic ones. The caller takes
     * the first few, so what comes first is what gets shown.
     *
     * @return array<int, array{key:string, label:string, prompt:string, description?:string|null, icon?:string|null}>
     */
    private function derivedConversational(AiContext $context): array
    {
        $page = $context->page;
        $subject = $context->entityLabel ?? ($context->entityKey ? $this->humanize($context->entityKey) : null);
        $noun = $this->recordNoun($context);
        $derived = [];

        // 1. A single record on screen. The strongest context there is.
        if ($context->hasEntity()) {
            $derived[] = [
                'key' => 'derived:summarise-record',
                'label' => $subject ? "Summarise {$subject}" : 'Summarise this record',
                'prompt' => $subject
                    ? "Give me a short summary of {$subject}."
                    : 'Give me a short summary of the record on this page.',
                'icon' => 'file-text',
            ];
            $derived[] = [
                'key' => 'derived:recent-changes',
                'label' => 'What changed recently?',
                'prompt' => $subject
                    ? "What has changed recently for {$subject}?"
                    : 'What has changed recently for this record?',
                'icon' => 'history',
            ];
            $derived[] = [
                'key' => 'derived:next-step',
                'label' => 'What should I do next?',
                'prompt' => $subject
                    ? "Based on the current status, what should I do next for {$subject}?"
                    : 'Based on the current status, what should I do next here?',
                'icon' => 'arrow-right',
            ];

            if ($context->supports('ontology')) {
                $derived[] = [
                    'key' => 'derived:related-records',
                    'label' => 'Show related records',
                    'prompt' => $subject
                        ? "What records are connected to {$subject}?"
                        : 'What records are connected to this one?',
                    'icon' => 'share-2',
                ];
            }
        }

        // 2. Rows the user has explicitly ticked — a narrower intent than the filter.
        $selectedCount = count($context->selectedRecords);

        if ($selectedCount > 0) {
            $derived[] = [
                'key' => 'derived:summarise-selection',
                'label' => "Summarise the {$selectedCount} selected",
                'prompt' => "Summarise the {$selectedCount} {$noun} I have selected on this page.",
                'icon' => 'check-square',
            ];

            if ($selectedCount > 1) {
                $derived[] = [
                    'key' => 'derived:compare-selection',
                    'label' => 'Compare the selected records',
                    'prompt' => "Compare the {$selectedCount} {$noun} I have selected and tell me how they differ.",
                    'icon' => 'columns',
                ];
            }
        }

        // 3. An active search or filter — the user has already said what they care about.
        if ($page->searchQuery !== null && $page->searchQuery !== '') {
            $derived[] = [
                'key' => 'derived:search-results',
                'label' => 'Summarise these results',
                'prompt' => "Summarise the results for \"{$page->searchQuery}\" on this page.",
                'icon' => 'search',
            ];
        }

        if ($page->filters !== []) {
            $filters = $page->describeFilters();

            $derived[] = [
                'key' => 'derived:explain-filters',
                'label' => 'Explain what this view shows',
                'prompt' => "This page is filtered by {$filters}. Explain what that view is showing and anything notable in it.",
                'icon' => 'filter',
            ];
        }

        // 4b. The choices the page offers. On a catalogue or directory — nothing
        //     selected, nothing filtered — these are the only page-specific questions
        //     there are, and without them such a page falls through to the generic
        //     floor no matter how much real data is on screen.
        //
        //     Two values per facet: one grade and one category read as an invitation,
        //     eight of each read as a dropdown someone pasted into the panel.
        //     Taken round-robin rather than facet by facet. The cap is six suggestions
        //     and curated rows come first, so reading the grade list to exhaustion
        //     would push categories off the end entirely — the user would see four
        //     grades and never learn they can ask about a category at all.
        for ($round = 0; $round < 2; $round++) {
            foreach ($page->facets as $facet) {
                if (! isset($facet['values'][$round])) {
                    continue;
                }

                $value = $facet['values'][$round];
                $prompt = $facet['question'] !== null
                    ? str_replace('{value}', $value, $facet['question'])
                    : "What {$noun} are available for {$facet['label']} {$value}?";

                $derived[] = [
                    'key' => 'derived:facet:' . $facet['key'] . ':' . $value,
                    // The prompt is the label: these are already short questions, and a
                    // separate label would only be the same sentence written twice.
                    'label' => $prompt,
                    'prompt' => $prompt,
                    'icon' => 'filter',
                ];
            }
        }

        // A count question, when the page offers a dimension to count along.
        if ($page->facets !== [] && $page->hasRecords()) {
            $first = $page->facets[0];

            $derived[] = [
                'key' => 'derived:facet-breakdown',
                'label' => 'Break these down by ' . strtolower($first['label']),
                'prompt' => "How many {$noun} are there for each {$first['label']}?",
                'icon' => 'bar-chart',
            ];
        }

        // 4. KPI tiles. Two at most — a dashboard with nine tiles must not produce nine
        //    near-identical prompts and crowd out everything else.
        foreach (array_slice($page->metrics, 0, 2) as $metric) {
            $value = $metric['value'] . ($metric['unit'] ? ' ' . $metric['unit'] : '');

            $derived[] = [
                'key' => 'derived:metric:' . $metric['key'],
                'label' => "Explain {$metric['label']}",
                'prompt' => "{$metric['label']} is showing {$value}. Explain what is driving that and whether it needs attention.",
                'icon' => 'trending-up',
            ];
        }

        // 5. A list of records.
        if ($page->hasRecords()) {
            $count = $page->recordCount;

            $derived[] = [
                'key' => 'derived:summarise-list',
                'label' => $count > 0 ? "Summarise these {$count} {$noun}" : "Summarise these {$noun}",
                'prompt' => $count > 0
                    ? "Summarise the {$count} {$noun} shown on this page."
                    : "Summarise the {$noun} shown on this page.",
                'icon' => 'list',
            ];
            $derived[] = [
                'key' => 'derived:needs-attention-list',
                'label' => 'Which of these need attention?',
                'prompt' => "Which of the {$noun} on this page need attention first, and why?",
                'icon' => 'alert-circle',
            ];
        }

        // 6. Page-shape defaults.
        //
        //    Keyed on the *resolved* type rather than the declared one, so a page that
        //    never adopted the context provider still gets prompts suited to its shape
        //    — which is most of the estate.
        $derived = array_merge($derived, match ($context->pageType) {
            'dashboard' => [
                [
                    'key' => 'derived:dashboard-summary',
                    'label' => "Summarise today's activity",
                    'prompt' => 'Summarise what has happened across the institute today.',
                    'icon' => 'activity',
                ],
                [
                    'key' => 'derived:dashboard-attention',
                    'label' => 'What needs my attention?',
                    'prompt' => 'What needs my attention right now?',
                    'icon' => 'bell',
                ],
                [
                    'key' => 'derived:dashboard-approvals',
                    'label' => 'Show pending approvals',
                    'prompt' => 'What is waiting on my approval?',
                    'icon' => 'check-circle',
                ],
            ],
            'report' => [
                [
                    'key' => 'derived:report-explain',
                    'label' => 'Explain this report',
                    'prompt' => 'Explain what this report is showing and what stands out in it.',
                    'icon' => 'bar-chart',
                ],
            ],
            'form' => [
                [
                    'key' => 'derived:form-help',
                    'label' => 'Help me fill this in',
                    'prompt' => 'What information do I need to complete this form, and what do the fields mean?',
                    'icon' => 'edit',
                ],
                [
                    'key' => 'derived:form-values',
                    'label' => 'Suggest values for these fields',
                    'prompt' => 'Based on what is already filled in, suggest sensible values for the remaining fields and explain why.',
                    'icon' => 'wand',
                ],
            ],
            'settings' => [
                [
                    'key' => 'derived:settings-explain',
                    'label' => 'Explain these settings',
                    'prompt' => 'Explain what the settings on this page control and what would change if I altered them.',
                    'icon' => 'settings',
                ],
            ],
            default => [],
        });

        // 7. Last resort. An unmapped route with a silent page still gets something
        //    useful, which is the difference between an assistant and an empty box.
        $derived[] = [
            'key' => 'derived:page-help',
            'label' => 'What can I do on this page?',
            'prompt' => $context->moduleLabel
                ? "What can I do in {$context->moduleLabel}, and what can you help me with here?"
                : 'What is this page for, and what can you help me with here?',
            'icon' => 'help-circle',
        ];
        $derived[] = [
            'key' => 'derived:my-attention',
            'label' => 'What needs my attention today?',
            'prompt' => 'What needs my attention today?',
            'icon' => 'bell',
        ];

        return $derived;
    }

    /**
     * What to call the things on this page, in a sentence.
     *
     * "Summarise these 42 records" is correct and lifeless; "Summarise these 42
     * students" is what a person would say.
     */
    private function recordNoun(AiContext $context): string
    {
        $key = $context->entityKey ?? $context->moduleKey;

        if ($key === null) {
            return 'records';
        }

        $word = strtolower(str_replace('_', ' ', $key));

        // Already plural, or a module label that reads as a set.
        if (str_ends_with($word, 's')) {
            return $word;
        }

        return str_ends_with($word, 'y')
            ? substr($word, 0, -1) . 'ies'
            : $word . 's';
    }

    /** Normalised so a configured row and a derived twin collide rather than duplicate. */
    private function promptKey(?string $value): string
    {
        return preg_replace('/[^a-z0-9]+/', '', strtolower((string) $value)) ?? '';
    }

    private function humanize(string $value): string
    {
        return ucfirst(str_replace('_', ' ', $value));
    }

    /**
     * Agents relevant here — configured for the module, still enabled, and runnable
     * by this role. The label comes from the suggestion row where one exists, so a
     * teacher reads "Analyze academic risk" rather than the agent key.
     */
    private function agentSuggestions(AiContext $context): array
    {
        $rows = $this->suggestionRows($context, 'agent');
        // The page-type analysis action leads, because it is the one that works
        // everywhere. Registered agents follow it where a module has them.
        $suggestions = $this->pageTypeActions($context, 'analysis', 'analyse');

        foreach ($rows as $row) {
            $manifest = $row->action_ref
                ? $this->agents->find($row->action_ref, $context->scope->selectedInstituteId)
                : null;

            // Silently drop a suggestion whose agent is gone or off-limits — a
            // button that cannot run is worse than no button.
            if (! $manifest || ! $manifest->permitsRole($context->scope->role)) {
                continue;
            }

            $suggestions[] = [
                'key' => $manifest->key,
                'label' => $row->label,
                'description' => $row->description ?: $manifest->purpose,
                'icon' => $row->icon,
                'action_type' => 'run_agent',
                'action_ref' => $manifest->key,
                'requires_entity' => (bool) $row->requires_entity,
                // Shown in the panel so the user knows what the agent may and may not do.
                'max_verb' => $manifest->maxVerb->value,
                'may_execute' => $manifest->mayExecuteActions,
                'payload' => $this->agentPayload($context),
            ];
        }

        return $suggestions;
    }

    /**
     * Workflows that can be started here, plus any run already in flight for this
     * record so the panel can show progress instead of offering a duplicate start.
     */
    private function workflowSuggestions(AiContext $context): array
    {
        $rows = $this->suggestionRows($context, 'workflow');
        $suggestions = [];

        foreach ($rows as $row) {
            $definition = $this->workflowDefinition($row->action_ref, $context);

            if (! $definition) {
                continue;
            }

            $allowedRoles = $this->decode($definition->allowed_roles);

            if ($allowedRoles !== [] && ! in_array($context->scope->role, $allowedRoles, true)) {
                continue;
            }

            $suggestions[] = [
                'key' => $definition->workflow_key,
                'label' => $row->label,
                'description' => $row->description ?: $definition->description,
                'icon' => $row->icon,
                'action_type' => 'start_workflow',
                'action_ref' => $definition->workflow_key,
                'requires_entity' => (bool) $row->requires_entity,
                // Most workflows here are started by approving a recommendation
                // rather than from a button, and the panel says so.
                'trigger_type' => $definition->trigger_type,
                'requires_approval' => (bool) $definition->requires_approval,
                'is_consequential' => (bool) $definition->is_consequential,
            ];
        }

        return $suggestions;
    }

    /**
     * Relationship views for the current record. Only offered when there is a record
     * to walk from — a graph view of "nothing in particular" helps no one.
     */
    private function ontologySuggestions(AiContext $context): array
    {
        if (! Schema::hasTable('ai_ontology_views')) {
            return [];
        }

        $query = DB::table('ai_ontology_views')
            ->where('status', 1)
            ->where(function ($inner) use ($context) {
                $inner->whereNull('sub_institute_id')
                    ->orWhere('sub_institute_id', $context->scope->selectedInstituteId);
            });

        if ($context->moduleKey !== null) {
            $query->where(function ($inner) use ($context) {
                $inner->whereNull('module_key')->orWhere('module_key', $context->moduleKey);
            });
        }

        // A view is anchored to an entity type; without a resolved entity there is
        // nothing to anchor it to.
        if ($context->hasEntity()) {
            $query->where('root_entity_key', $context->entityKey);
        } else {
            return [];
        }

        return $query->orderBy('sort_order')
            ->get()
            ->filter(function ($row) use ($context) {
                $roles = $this->decode($row->allowed_roles);

                return $roles === [] || in_array($context->scope->role, $roles, true);
            })
            ->map(fn ($row) => [
                'key' => $row->view_key,
                'label' => $row->label,
                'description' => $row->description,
                'action_type' => 'ontology_view',
                'action_ref' => $row->view_key,
                'requires_entity' => true,
                'root_entity_key' => $row->root_entity_key,
                'hops' => count($this->decode($row->path)),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, object>
     */
    private function suggestionRows(AiContext $context, string $capability): array
    {
        if (! Schema::hasTable('ai_suggestions') || $context->moduleKey === null) {
            return [];
        }

        return DB::table('ai_suggestions')
            ->where('status', 1)
            ->where('module_key', $context->moduleKey)
            ->where('capability', $capability)
            ->where(function ($inner) use ($context) {
                $inner->whereNull('sub_institute_id')
                    ->orWhere('sub_institute_id', $context->scope->selectedInstituteId);
            })
            ->orderBy('sort_order')
            ->get()
            ->filter(function ($row) use ($context) {
                // Entity-specific actions stay hidden until there is an entity.
                if ($row->requires_entity && ! $context->hasEntity()) {
                    return false;
                }

                $roles = $this->decode($row->allowed_roles);

                return $roles === [] || in_array($context->scope->role, $roles, true);
            })
            ->values()
            ->all();
    }

    private function agentPayload(AiContext $context): array
    {
        if (! $context->hasEntity()) {
            return [];
        }

        return array_filter([
            'subject_entity_key' => $context->entityKey,
            'subject_id' => is_numeric($context->entityId) ? (int) $context->entityId : null,
        ], fn ($value) => $value !== null);
    }

    private function templateExists(?string $templateKey, AiContext $context): bool
    {
        if ($templateKey === null || $templateKey === '' || ! Schema::hasTable('ai_templates')) {
            return false;
        }

        return DB::table('ai_templates')
            ->where('template_key', $templateKey)
            ->where('status', 'published')
            ->where(function ($inner) use ($context) {
                $inner->whereNull('sub_institute_id')
                    ->orWhere('sub_institute_id', $context->scope->selectedInstituteId);
            })
            ->exists();
    }

    private function workflowDefinition(?string $workflowKey, AiContext $context): ?object
    {
        if ($workflowKey === null || $workflowKey === '' || ! Schema::hasTable('workflow_definitions')) {
            return null;
        }

        return DB::table('workflow_definitions')
            ->where('workflow_key', $workflowKey)
            ->where('status', 1)
            ->where(function ($inner) use ($context) {
                $inner->whereNull('sub_institute_id')
                    ->orWhere('sub_institute_id', $context->scope->selectedInstituteId);
            })
            ->orderByRaw('sub_institute_id IS NULL ASC')
            ->first();
    }

    private function decode(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (! is_string($value) || $value === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }
}
