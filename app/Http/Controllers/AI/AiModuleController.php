<?php

namespace App\Http\Controllers\AI;

use App\Domain\AI\Support\AiAuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * What one module's AI has done, and what is holding it back.
 *
 * WHY A MODULE-SCOPED READER EXISTS AT ALL
 *
 * `CapabilityController` answers "how much of this capability does the school have",
 * which is the right question for the central console and the wrong one for a module's
 * own AI Stack. A Fees administrator opening Usage & Cost is not asking how many
 * conversations the estate has had; they are asking how much of it was Fees, what it
 * cost them, and which rule stopped the thing that did not work. Neither question can
 * be answered by filtering a capability payload in the browser: the numbers are
 * aggregates over tables the browser never sees, and the module dimension lives on
 * different columns in each one.
 *
 * NOTHING WAS ADDED TO THE DATABASE FOR THIS
 *
 * Every module filter below is a column that already existed:
 *
 *   ai_conversations.module_key      — which module a conversation happened in
 *   ai_generated_reports.module_key  — which module a saved report belongs to
 *   ai_templates.module_key          — which module a prompt or layout serves, and so,
 *                                      through ai_generation_requests.template_id,
 *                                      which generations were a module's
 *   ai_api_keys.ai_module            — which module a credential and its quota serve
 *
 * There is no module column on `ai_interaction_logs` or `ai_audit_logs`, and none is
 * invented: those are reported as estate-wide where they are reported at all, and
 * labelled as such, rather than being filtered by a guess at what a `menu_type` means.
 *
 * MONEY IS NEVER ESTIMATED
 *
 * Cost is `tokens × the rate on the model row`. Both halves are frequently absent on a
 * real estate — nothing currently writes `prompt_tokens`, and every `ai_models` rate
 * ships null because a guessed rate is worse than no rate on a screen an administrator
 * uses to explain a bill. So this endpoint returns the tokens it has, the rate it
 * found, and a `cost` that is null with a stated reason when it cannot multiply them.
 * It must never fall back to a sample figure.
 *
 * TENANT SCOPE IS NEVER A PARAMETER. `$scope->selectedInstituteId` comes from the
 * caller's JWT via McpContextHydrator. The module key is the only thing read from the
 * URL, and it cannot widen access — at worst it names a module with no rows.
 */
class AiModuleController extends AiController
{
    /** How many recent rows a detail list carries. Enough to see a pattern. */
    private const RECENT = 25;

    /**
     * The audit logger is the ledger writer.
     *
     * Injected rather than resolved inline so `recordActivity` cannot quietly acquire a
     * second way to write history - there is one writer, and it is the one every other
     * part of the intelligence layer already uses.
     */
    public function __construct(private readonly AiAuditLogger $audit)
    {
    }

    /**
     * Usage and cost for one module.
     */
    public function usage(Request $request, string $module)
    {
        try {
            $scope = $this->scope($request);
            $institute = $scope->selectedInstituteId;

            return $this->success('Module usage resolved.', [
                'module' => $this->moduleIdentity($module, $institute),
                'conversations' => $this->conversationUsage($module, $institute),
                'generation' => $this->generationUsage($module, $institute),
                'reports' => $this->reportUsage($module, $institute),
                'provider' => $this->providerUsage($module, $institute),
                'recent_turns' => $this->recentTurns($module, $institute),
                'daily' => $this->dailySeries($module, $institute),
            ]);
        } catch (Throwable $exception) {
            return $this->handle($exception);
        }
    }

    /**
     * The limits one module's AI operates within, and what they actually stopped.
     *
     * Read-only on purpose. Each guardrail here is configured where it belongs — a
     * template's safety rules on the template, a disclosure requirement on the policy,
     * a tool's risk in the tool registry — and a second place to edit them would be a
     * second source of truth. What this adds is the half that cannot be configured:
     * the record of refusals.
     */
    public function guardrails(Request $request, string $module)
    {
        try {
            $scope = $this->scope($request);
            $institute = $scope->selectedInstituteId;

            return $this->success('Module guardrails resolved.', [
                'module' => $this->moduleIdentity($module, $institute),
                'capabilities' => $this->moduleCapabilities($module, $institute),
                'review' => $this->reviewPosture($module, $institute),
                'refusals' => $this->refusals($module, $institute),
                'refusal_counts' => $this->refusalCounts($module, $institute),
            ]);
        } catch (Throwable $exception) {
            return $this->handle($exception);
        }
    }

    // ---------------------------------------------------------------------
    // Identity
    // ---------------------------------------------------------------------

    /**
     * The module as the database knows it, or a `registered: false` stub.
     *
     * A stub rather than a 404: a module can legitimately have a screen before it has
     * an `ai_modules` row, and the screen should be able to say "Fees is not registered
     * as an AI module" instead of failing to load.
     */
    private function moduleIdentity(string $module, int|string|null $institute): array
    {
        if (! Schema::hasTable('ai_modules')) {
            return ['key' => $module, 'label' => $module, 'registered' => false, 'capabilities' => []];
        }

        $row = DB::table('ai_modules')
            ->where('module_key', $module)
            ->where(function ($query) use ($institute) {
                $query->where('sub_institute_id', $institute)
                    ->orWhereNull('sub_institute_id');
            })
            // The institute's own row wins over the platform baseline.
            ->orderByRaw('sub_institute_id IS NULL ASC')
            ->first();

        if ($row === null) {
            return ['key' => $module, 'label' => $module, 'registered' => false, 'capabilities' => []];
        }

        return [
            'key' => (string) $row->module_key,
            'label' => (string) $row->label,
            'registered' => true,
            'status' => (int) $row->status,
            'scope' => $row->sub_institute_id === null ? 'platform' : 'institute',
            'capabilities' => $this->decodeCapabilities($row->capabilities ?? null),
        ];
    }

    /** @return array<string, bool> */
    private function decodeCapabilities(mixed $raw): array
    {
        $decoded = is_string($raw) ? json_decode($raw, true) : $raw;

        if (! is_array($decoded)) {
            return [];
        }

        $capabilities = [];
        foreach ($decoded as $key => $enabled) {
            $capabilities[(string) $key] = (bool) $enabled;
        }

        return $capabilities;
    }

    // ---------------------------------------------------------------------
    // Usage
    // ---------------------------------------------------------------------

    /**
     * Conversations held in this module, and the turns inside them.
     *
     * The truest measure of module AI usage this product has: a turn is one question
     * somebody actually asked from one of the module's pages.
     */
    private function conversationUsage(string $module, int|string|null $institute): array
    {
        if (! Schema::hasTable('ai_conversations')) {
            return ['available' => false, 'reason' => 'ai_conversations is not on this estate.'];
        }

        $conversations = DB::table('ai_conversations')
            ->where('sub_institute_id', $institute)
            ->where('module_key', $module);

        $totals = (clone $conversations)
            ->selectRaw('count(*) total, coalesce(sum(turn_count), 0) turns, count(distinct user_id) users, max(last_turn_at) last_activity, min(created_at) first_activity')
            ->first();

        $byStatus = (clone $conversations)
            ->selectRaw('status, count(*) c')
            ->groupBy('status')
            ->pluck('c', 'status')
            ->map(fn ($count) => (int) $count)
            ->all();

        $turnStats = ['available' => false];

        if (Schema::hasTable('ai_conversation_turns')) {
            $ids = (clone $conversations)->pluck('id');

            if ($ids->isNotEmpty()) {
                $turns = DB::table('ai_conversation_turns')->whereIn('conversation_id', $ids);

                $aggregate = (clone $turns)
                    ->selectRaw('count(*) total, avg(duration_ms) avg_ms, max(duration_ms) max_ms')
                    ->first();

                $turnStats = [
                    'available' => true,
                    'total' => (int) ($aggregate->total ?? 0),
                    'avg_duration_ms' => $aggregate->avg_ms === null ? null : (int) round((float) $aggregate->avg_ms),
                    'max_duration_ms' => $aggregate->max_ms === null ? null : (int) $aggregate->max_ms,
                    'by_status' => (clone $turns)
                        ->selectRaw('status, count(*) c')
                        ->groupBy('status')
                        ->pluck('c', 'status')
                        ->map(fn ($count) => (int) $count)
                        ->all(),
                    'by_intent' => (clone $turns)
                        ->selectRaw('intent_key, count(*) c')
                        ->groupBy('intent_key')
                        ->orderByDesc('c')
                        ->limit(12)
                        ->get()
                        ->map(fn ($row) => ['intent' => $row->intent_key ?? 'unclassified', 'count' => (int) $row->c])
                        ->all(),
                ];
            } else {
                $turnStats = ['available' => true, 'total' => 0, 'avg_duration_ms' => null, 'max_duration_ms' => null, 'by_status' => [], 'by_intent' => []];
            }
        }

        return [
            'available' => true,
            'total' => (int) ($totals->total ?? 0),
            'turns_recorded_on_conversation' => (int) ($totals->turns ?? 0),
            'distinct_users' => (int) ($totals->users ?? 0),
            'first_activity' => $totals->first_activity ?? null,
            'last_activity' => $totals->last_activity ?? null,
            'by_status' => $byStatus,
            'turn_detail' => $turnStats,
        ];
    }

    /**
     * Generations requested through this module's own templates, with tokens and cost.
     *
     * The module is reached through `ai_templates.module_key`, which is the only link
     * between a generation and a module that exists — a request stores a template id,
     * and the template knows whose it is.
     */
    private function generationUsage(string $module, int|string|null $institute): array
    {
        foreach (['ai_templates', 'ai_generation_requests'] as $table) {
            if (! Schema::hasTable($table)) {
                return ['available' => false, 'reason' => "{$table} is not on this estate."];
            }
        }

        $templateIds = DB::table('ai_templates')
            ->where('module_key', $module)
            ->where(function ($query) use ($institute) {
                $query->where('sub_institute_id', $institute)
                    ->orWhereNull('sub_institute_id');
            })
            ->pluck('id');

        if ($templateIds->isEmpty()) {
            return [
                'available' => true,
                'total' => 0,
                'by_status' => [],
                'tokens' => $this->emptyTokens('This module has no templates, so nothing has been generated through it.'),
            ];
        }

        $requests = DB::table('ai_generation_requests')
            ->where('sub_institute_id', $institute)
            ->whereIn('template_id', $templateIds);

        $byStatus = (clone $requests)
            ->selectRaw('status, count(*) c')
            ->groupBy('status')
            ->pluck('c', 'status')
            ->map(fn ($count) => (int) $count)
            ->all();

        return [
            'available' => true,
            'total' => (int) (clone $requests)->count(),
            'by_status' => $byStatus,
            'tokens' => $this->tokenUsage((clone $requests)->pluck('id'), $institute, $module),
        ];
    }

    /**
     * Tokens, latency and money for a set of generation requests.
     *
     * Every figure is either measured or null-with-a-reason. `cost` is the sum of what
     * the outputs themselves recorded when they recorded it; otherwise it is computed
     * from the model's own rate, and when there is no rate it stays null. There is no
     * third branch that guesses.
     */
    private function tokenUsage(\Illuminate\Support\Collection $requestIds, int|string|null $institute, string $module): array
    {
        if (! Schema::hasTable('ai_generation_outputs')) {
            return $this->emptyTokens('ai_generation_outputs is not on this estate.');
        }

        if ($requestIds->isEmpty()) {
            return $this->emptyTokens('Nothing has been generated through this module yet.');
        }

        $aggregate = DB::table('ai_generation_outputs')
            ->whereIn('request_id', $requestIds)
            ->selectRaw(
                'count(*) outputs,'
                . ' sum(prompt_tokens) prompt_tokens,'
                . ' sum(completion_tokens) completion_tokens,'
                . ' sum(cost_estimate) recorded_cost,'
                . ' avg(latency_ms) avg_latency,'
                . ' sum(case when reviewed = 1 then 1 else 0 end) reviewed'
            )
            ->first();

        $prompt = $aggregate->prompt_tokens === null ? null : (int) $aggregate->prompt_tokens;
        $completion = $aggregate->completion_tokens === null ? null : (int) $aggregate->completion_tokens;
        $recorded = $aggregate->recorded_cost === null ? null : (float) $aggregate->recorded_cost;

        $rate = $this->modelRate($module, $institute);

        [$cost, $costSource, $costReason] = $this->resolveCost($prompt, $completion, $recorded, $rate);

        return [
            'outputs' => (int) ($aggregate->outputs ?? 0),
            'reviewed' => (int) ($aggregate->reviewed ?? 0),
            'prompt_tokens' => $prompt,
            'completion_tokens' => $completion,
            'avg_latency_ms' => $aggregate->avg_latency === null ? null : (int) round((float) $aggregate->avg_latency),
            'rate' => $rate,
            'cost' => $cost,
            'cost_source' => $costSource,
            'cost_reason' => $costReason,
        ];
    }

    /**
     * @return array{0: float|null, 1: string, 2: string|null}
     */
    private function resolveCost(?int $prompt, ?int $completion, ?float $recorded, ?array $rate): array
    {
        if ($recorded !== null && $recorded > 0) {
            return [$recorded, 'recorded', null];
        }

        if ($prompt === null && $completion === null) {
            return [null, 'unavailable', 'No token counts are recorded for this module, so cost cannot be computed.'];
        }

        if ($rate === null || ($rate['input_per_1k'] === null && $rate['output_per_1k'] === null)) {
            return [
                null,
                'unavailable',
                'No price is configured for this module\'s model, so tokens are reported without a money figure.',
            ];
        }

        $cost = (($prompt ?? 0) / 1000) * (float) ($rate['input_per_1k'] ?? 0)
            + (($completion ?? 0) / 1000) * (float) ($rate['output_per_1k'] ?? 0);

        return [round($cost, 6), 'computed', null];
    }

    /** @return array<string, mixed> */
    private function emptyTokens(string $reason): array
    {
        return [
            'outputs' => 0,
            'reviewed' => 0,
            'prompt_tokens' => null,
            'completion_tokens' => null,
            'avg_latency_ms' => null,
            'rate' => null,
            'cost' => null,
            'cost_source' => 'unavailable',
            'cost_reason' => $reason,
        ];
    }

    /**
     * The published rate for whatever model this module resolves to.
     *
     * Read from `ai_models`, which is the catalogue Model Management writes. Returns
     * null when the module is not bound to a model at all — the honest answer, and the
     * one that makes the Models tab the place to go and fix it.
     */
    private function modelRate(string $module, int|string|null $institute): ?array
    {
        if (! Schema::hasTable('ai_api_keys') || ! Schema::hasColumn('ai_api_keys', 'ai_module')) {
            return null;
        }

        $binding = DB::table('ai_api_keys')
            ->where('ai_module', $module)
            ->where('status', 1)
            ->where(function ($query) use ($institute) {
                $query->where('sub_institute_id', $institute)
                    ->orWhereNull('sub_institute_id');
            })
            ->orderByRaw('sub_institute_id IS NULL ASC')
            ->first();

        if ($binding === null || ! Schema::hasTable('ai_models')) {
            return null;
        }

        $model = DB::table('ai_models')
            ->where('provider', $binding->api_type)
            ->when($binding->model !== null && $binding->model !== '', fn ($query) => $query->where('model_id', $binding->model))
            ->where(function ($query) use ($institute) {
                $query->where('sub_institute_id', $institute)
                    ->orWhereNull('sub_institute_id');
            })
            ->orderByRaw('sub_institute_id IS NULL ASC')
            ->first();

        if ($model === null) {
            return null;
        }

        return [
            'provider' => (string) $model->provider,
            'model_id' => (string) $model->model_id,
            'label' => (string) $model->label,
            'input_per_1k' => $model->input_cost_per_1k === null ? null : (float) $model->input_cost_per_1k,
            'output_per_1k' => $model->output_cost_per_1k === null ? null : (float) $model->output_cost_per_1k,
        ];
    }

    /** Saved reports filed against this module. */
    private function reportUsage(string $module, int|string|null $institute): array
    {
        if (! Schema::hasTable('ai_generated_reports')) {
            return ['available' => false, 'reason' => 'ai_generated_reports is not on this estate.'];
        }

        $reports = DB::table('ai_generated_reports')
            ->where('sub_institute_id', $institute)
            ->where('module_key', $module);

        $aggregate = (clone $reports)
            ->selectRaw('count(*) total, coalesce(sum(row_count), 0) rows_reported, max(created_at) last_created')
            ->first();

        return [
            'available' => true,
            'total' => (int) ($aggregate->total ?? 0),
            'rows_reported' => (int) ($aggregate->rows_reported ?? 0),
            'last_created' => $aggregate->last_created ?? null,
            'by_tool' => (clone $reports)
                ->selectRaw('source_tool, count(*) c')
                ->groupBy('source_tool')
                ->orderByDesc('c')
                ->limit(10)
                ->get()
                ->map(fn ($row) => ['tool' => $row->source_tool ?? 'unknown', 'count' => (int) $row->c])
                ->all(),
        ];
    }

    /**
     * The credential and quota this module runs on.
     *
     * The quota half matters on this screen specifically: `api_limit` is a daily call
     * ceiling, and a module sharing an unbound credential shares that ceiling with
     * every other module — which is the real answer to "why did Fees AI stop working
     * this afternoon".
     */
    private function providerUsage(string $module, int|string|null $institute): array
    {
        if (! Schema::hasTable('ai_api_keys')) {
            return ['available' => false, 'reason' => 'ai_api_keys is not on this estate.'];
        }

        $hasModuleColumn = Schema::hasColumn('ai_api_keys', 'ai_module');

        $binding = $hasModuleColumn
            ? DB::table('ai_api_keys')
                ->where('ai_module', $module)
                ->where('status', 1)
                ->where(function ($query) use ($institute) {
                    $query->where('sub_institute_id', $institute)
                        ->orWhereNull('sub_institute_id');
                })
                ->orderByRaw('sub_institute_id IS NULL ASC')
                ->first()
            : null;

        $calls = null;

        if ($binding !== null && Schema::hasTable('ai_daily_used_api')) {
            $calls = DB::table('ai_daily_used_api')
                ->where('parent_id', $binding->id)
                ->orderByDesc('date')
                ->limit(14)
                ->get()
                ->map(fn ($row) => ['date' => (string) $row->date, 'count' => (int) $row->count])
                ->all();
        }

        return [
            'available' => true,
            'bound' => $binding !== null,
            'provider' => $binding->api_type ?? null,
            'model' => $binding->model ?? null,
            'daily_limit' => $binding === null ? null : ($binding->api_limit === null ? null : (int) $binding->api_limit),
            'scope' => $binding === null ? null : ($binding->sub_institute_id === null ? 'platform' : 'institute'),
            'daily_calls' => $calls,
        ];
    }

    /** The module's most recent questions — the detail behind the counts. */
    private function recentTurns(string $module, int|string|null $institute): array
    {
        if (! Schema::hasTable('ai_conversations') || ! Schema::hasTable('ai_conversation_turns')) {
            return [];
        }

        return DB::table('ai_conversation_turns as t')
            ->join('ai_conversations as c', 'c.id', '=', 't.conversation_id')
            ->where('c.sub_institute_id', $institute)
            ->where('c.module_key', $module)
            ->orderByDesc('t.id')
            ->limit(self::RECENT)
            ->get([
                't.id',
                't.question',
                't.intent_key',
                't.intent_confidence',
                't.status',
                't.duration_ms',
                't.user_id',
                't.created_at',
                'c.conversation_reference',
            ])
            ->map(fn ($row) => [
                'id' => (int) $row->id,
                // Truncated: this is a usage list, not a transcript reader, and a long
                // question would push every other column off the screen.
                'question' => mb_strimwidth((string) $row->question, 0, 160, '…'),
                'intent' => $row->intent_key,
                'confidence' => $row->intent_confidence === null ? null : (float) $row->intent_confidence,
                'status' => (string) $row->status,
                'duration_ms' => $row->duration_ms === null ? null : (int) $row->duration_ms,
                'user_id' => $row->user_id === null ? null : (int) $row->user_id,
                'conversation' => (string) $row->conversation_reference,
                'created_at' => $row->created_at,
            ])
            ->all();
    }

    /** Turns per day for the last month, for a trend rather than a single total. */
    private function dailySeries(string $module, int|string|null $institute): array
    {
        if (! Schema::hasTable('ai_conversations') || ! Schema::hasTable('ai_conversation_turns')) {
            return [];
        }

        return DB::table('ai_conversation_turns as t')
            ->join('ai_conversations as c', 'c.id', '=', 't.conversation_id')
            ->where('c.sub_institute_id', $institute)
            ->where('c.module_key', $module)
            ->where('t.created_at', '>=', now()->subDays(30))
            ->selectRaw('date(t.created_at) day, count(*) turns')
            ->groupBy('day')
            ->orderBy('day')
            ->get()
            ->map(fn ($row) => ['date' => (string) $row->day, 'turns' => (int) $row->turns])
            ->all();
    }

    // ---------------------------------------------------------------------
    // Guardrails
    // ---------------------------------------------------------------------

    /** Which AI capabilities are switched on for this module, from `ai_modules`. */
    private function moduleCapabilities(string $module, int|string|null $institute): array
    {
        return $this->moduleIdentity($module, $institute)['capabilities'];
    }

    /**
     * How much of this module's generated output a person has to read.
     *
     * `requires_review` on a template is the strongest guardrail the product has: it
     * decides whether a model's words can reach a parent without anybody looking.
     */
    private function reviewPosture(string $module, int|string|null $institute): array
    {
        if (! Schema::hasTable('ai_templates')) {
            return ['available' => false, 'reason' => 'ai_templates is not on this estate.'];
        }

        $templates = DB::table('ai_templates')
            ->where('module_key', $module)
            ->where(function ($query) use ($institute) {
                $query->where('sub_institute_id', $institute)
                    ->orWhereNull('sub_institute_id');
            });

        $aggregate = (clone $templates)
            ->selectRaw(
                'count(*) total,'
                . ' sum(case when requires_review = 1 then 1 else 0 end) requires_review,'
                . ' sum(case when allow_as_evidence = 1 then 1 else 0 end) allowed_as_evidence,'
                . " sum(case when status = 'published' then 1 else 0 end) published"
            )
            ->first();

        return [
            'available' => true,
            'templates' => (int) ($aggregate->total ?? 0),
            'published' => (int) ($aggregate->published ?? 0),
            'requires_review' => (int) ($aggregate->requires_review ?? 0),
            'allowed_as_evidence' => (int) ($aggregate->allowed_as_evidence ?? 0),
        ];
    }

    /**
     * Requests this module's guardrails actually refused, with the reason recorded.
     *
     * A guardrails screen that only listed configuration would be a screen of good
     * intentions. These are the rows where a rule fired: a generation blocked by the
     * safety layer, or refused for having no grounding to rest on.
     */
    private function refusals(string $module, int|string|null $institute): array
    {
        if (! Schema::hasTable('ai_templates') || ! Schema::hasTable('ai_generation_requests')) {
            return [];
        }

        $templateIds = DB::table('ai_templates')
            ->where('module_key', $module)
            ->where(function ($query) use ($institute) {
                $query->where('sub_institute_id', $institute)
                    ->orWhereNull('sub_institute_id');
            })
            ->pluck('id');

        if ($templateIds->isEmpty()) {
            return [];
        }

        return DB::table('ai_generation_requests')
            ->where('sub_institute_id', $institute)
            ->whereIn('template_id', $templateIds)
            // Anything that is not a clean completion. `status` is the governance
            // verdict, so this needs no list of failure names to keep in sync.
            ->whereNotIn('status', ['completed', 'pending', 'running'])
            ->orderByDesc('id')
            ->limit(self::RECENT)
            ->get(['id', 'request_reference', 'template_key', 'purpose', 'status', 'error_message', 'provider', 'model', 'requested_by', 'requested_by_role', 'created_at'])
            ->map(fn ($row) => [
                'id' => (int) $row->id,
                'reference' => (string) $row->request_reference,
                'template_key' => $row->template_key,
                'purpose' => $row->purpose,
                'status' => (string) $row->status,
                'reason' => $row->error_message,
                'provider' => $row->provider,
                'model' => $row->model,
                'requested_by' => $row->requested_by === null ? null : (int) $row->requested_by,
                'requested_by_role' => $row->requested_by_role,
                'created_at' => $row->created_at,
            ])
            ->all();
    }

    /** Refusals by verdict, so a pattern is visible without paging the list. */
    private function refusalCounts(string $module, int|string|null $institute): array
    {
        if (! Schema::hasTable('ai_templates') || ! Schema::hasTable('ai_generation_requests')) {
            return [];
        }

        $templateIds = DB::table('ai_templates')
            ->where('module_key', $module)
            ->where(function ($query) use ($institute) {
                $query->where('sub_institute_id', $institute)
                    ->orWhereNull('sub_institute_id');
            })
            ->pluck('id');

        if ($templateIds->isEmpty()) {
            return [];
        }

        return DB::table('ai_generation_requests')
            ->where('sub_institute_id', $institute)
            ->whereIn('template_id', $templateIds)
            ->selectRaw('status, count(*) c')
            ->groupBy('status')
            ->orderByDesc('c')
            ->get()
            ->map(fn ($row) => ['status' => (string) $row->status, 'count' => (int) $row->c])
            ->all();
    }

    // ---------------------------------------------------------------------
    // Activity — the execution ledger
    // ---------------------------------------------------------------------

    /**
     * Record one thing a module's AI did, as it happens.
     *
     * WHY THIS IS AN AUDIT ROW AND NOT A NEW TABLE
     *
     * `ai_audit_logs` already is an execution ledger: who acted, on what subject, with
     * what outcome, against which related record, with a JSON payload and a timestamp.
     * A second table would mean an investigation into "what did the system do about this
     * student" had to join two vocabularies, which is exactly what `AiAuditLogger`'s own
     * docblock says it exists to avoid. So a Fees collection that used the Fees agent is
     * one more row beside the agent runs and governance refusals already in there.
     *
     * The event type is `module.<module>.<operation>`, which is what makes these rows
     * findable as a set and keeps one module's ledger separate from another's.
     *
     * THE AI RECORDS ARE RESOLVED HERE, NOT TRUSTED FROM THE CALLER
     *
     * A caller may name a template or prompt by id; this looks it up in `ai_templates`
     * and stores the name the database actually holds. Two things follow. A ledger entry
     * can never claim a template that does not exist, and an id belonging to another
     * module is refused outright — which is the rule that keeps a Fees operation from
     * being recorded against an Admissions template.
     *
     * Agents are named rather than resolved, because a module agent lives in the Agent
     * Management engine's own store and not in this database. What is stored is the id
     * and name that engine issued, which is still a real configured record.
     *
     * WRITES ARE BEST-EFFORT BY DESIGN
     *
     * `AiAuditLogger::record()` never throws: losing a ledger line is bad, failing a
     * parent's fee receipt because the log table was locked is worse. A caller that gets
     * `recorded: false` back has still had its fee collected.
     */
    public function recordActivity(Request $request, string $module)
    {
        try {
            $scope = $this->scope($request);
            $institute = $scope->selectedInstituteId;

            $data = $request->validate([
                'operation' => ['required', 'string', 'max:60', 'regex:/^[a-z0-9_.-]+$/'],
                'operation_label' => 'nullable|string|max:120',
                'capability' => 'nullable|string|max:40',
                'status' => 'required|string|in:completed,failed,denied,skipped',
                'message' => 'nullable|string|max:2000',
                'subject_entity_key' => 'nullable|string|max:100',
                'subject_id' => 'nullable|integer|min:1',
                'subject_label' => 'nullable|string|max:150',
                'reference' => 'nullable|string|max:120',
                // AI Stack records this operation used. Each is optional: an operation
                // that used no template legitimately reports none, and a ledger row
                // saying "no template" is more useful than one that invents a name.
                'template_id' => 'nullable|integer|min:1',
                'prompt_id' => 'nullable|integer|min:1',
                'agent_id' => 'nullable|string|max:60',
                'agent_name' => 'nullable|string|max:120',
                'agent_run_id' => 'nullable|string|max:60',
                'workflow' => 'nullable|string|max:120',
                'tool' => 'nullable|string|max:120',
                'result' => 'nullable|array',
            ]);

            $used = [];

            foreach (['template_id' => 'template', 'prompt_id' => 'prompt'] as $field => $slot) {
                if (! isset($data[$field])) {
                    continue;
                }

                $row = $this->resolveTemplate((int) $data[$field], $module, $institute);

                if ($row === null) {
                    return $this->failure(
                        "That {$slot} does not exist for the {$module} module, so the activity was not recorded.",
                        422
                    );
                }

                $used[$slot] = $row;
            }

            if (($data['agent_id'] ?? null) !== null || ($data['agent_name'] ?? null) !== null) {
                $used['agent'] = [
                    'id' => $data['agent_id'] ?? null,
                    'name' => $data['agent_name'] ?? null,
                    'run_id' => $data['agent_run_id'] ?? null,
                    // Said plainly, because it is the one record in this payload that
                    // did not come out of this database.
                    'source' => 'agent_management_engine',
                ];
            }

            foreach (['workflow', 'tool'] as $field) {
                if (($data[$field] ?? null) !== null) {
                    $used[$field] = $data[$field];
                }
            }

            $id = $this->audit->record("module.{$module}.{$data['operation']}", $scope, [
                'actor_label' => $this->actorLabel($scope->userId),
                'subject_entity_key' => $data['subject_entity_key'] ?? null,
                'subject_id' => $data['subject_id'] ?? null,
                // The related record is the AI artefact the operation ran on, when there
                // was one — so the ledger joins back to what it names.
                'related_type' => isset($used['template']) || isset($used['prompt']) ? 'ai_templates' : null,
                'related_id' => $used['template']['id'] ?? $used['prompt']['id'] ?? null,
                'outcome' => $this->outcomeOf($data['status']),
                'message' => $data['message'] ?? ($data['operation_label'] ?? $data['operation']),
                'payload' => [
                    'module' => $module,
                    'operation' => $data['operation'],
                    'operation_label' => $data['operation_label'] ?? null,
                    'capability' => $data['capability'] ?? null,
                    'status' => $data['status'],
                    'subject_label' => $data['subject_label'] ?? null,
                    'reference' => $data['reference'] ?? null,
                    'used' => $used,
                    'result' => $data['result'] ?? null,
                ],
            ]);

            return $this->success(
                $id === null ? 'The activity could not be recorded.' : 'Activity recorded.',
                ['recorded' => $id !== null, 'id' => $id],
                $id === null ? 202 : 201
            );
        } catch (Throwable $exception) {
            return $this->handle($exception);
        }
    }

    /** The audit vocabulary's word for a ledger status. */
    private function outcomeOf(string $status): string
    {
        return match ($status) {
            'completed' => 'success',
            'denied' => 'rejected',
            default => 'failure',
        };
    }

    /**
     * The module's execution ledger, newest first.
     *
     * Reads back exactly what `recordActivity` wrote. Scoped by the same event-type
     * prefix it writes, so one module's ledger can never show another's.
     */
    public function activity(Request $request, string $module)
    {
        try {
            $scope = $this->scope($request);
            $institute = $scope->selectedInstituteId;

            if (! Schema::hasTable('ai_audit_logs')) {
                return $this->success('No ledger on this estate.', [
                    'module' => $this->moduleIdentity($module, $institute),
                    'available' => false,
                    'reason' => 'ai_audit_logs is not on this estate.',
                    'total' => 0,
                    'entries' => [],
                    'by_operation' => [],
                ]);
            }

            $prefix = "module.{$module}.";

            $query = DB::table('ai_audit_logs')
                ->where(function ($inner) use ($institute) {
                    $inner->where('sub_institute_id', $institute)
                        ->orWhereNull('sub_institute_id');
                })
                ->where('event_type', 'like', $prefix . '%');

            if ($request->filled('operation')) {
                $query->where('event_type', $prefix . $request->input('operation'));
            }

            if ($request->filled('outcome')) {
                $query->where('outcome', $request->input('outcome'));
            }

            if ($request->filled('subject_id')) {
                $query->where('subject_id', (int) $request->input('subject_id'));
            }

            $entries = (clone $query)
                ->orderByDesc('id')
                ->limit($this->limit($request))
                ->get()
                ->map(fn ($row) => $this->presentEntry($row, $prefix))
                ->all();

            $byOperation = (clone $query)
                ->selectRaw('event_type, outcome, count(*) c')
                ->groupBy('event_type', 'outcome')
                ->get()
                ->map(fn ($row) => [
                    'operation' => substr((string) $row->event_type, strlen($prefix)),
                    'outcome' => (string) $row->outcome,
                    'count' => (int) $row->c,
                ])
                ->all();

            return $this->success('Module activity resolved.', [
                'module' => $this->moduleIdentity($module, $institute),
                'available' => true,
                'total' => (int) (clone $query)->count(),
                'entries' => $entries,
                'by_operation' => $byOperation,
            ]);
        } catch (Throwable $exception) {
            return $this->handle($exception);
        }
    }

    /** One ledger row, with its payload decoded. */
    private function presentEntry(object $row, string $prefix): array
    {
        $payload = $row->payload ? json_decode((string) $row->payload, true) : null;
        $payload = is_array($payload) ? $payload : [];

        return [
            'id' => (int) $row->id,
            'operation' => substr((string) $row->event_type, strlen($prefix)),
            'operation_label' => $payload['operation_label'] ?? null,
            'capability' => $payload['capability'] ?? null,
            'status' => $payload['status'] ?? ($row->outcome === 'success' ? 'completed' : 'failed'),
            'outcome' => $row->outcome,
            'message' => $row->message,
            'actor_id' => $row->actor_id === null ? null : (int) $row->actor_id,
            'actor_label' => $row->actor_label,
            'subject_entity_key' => $row->subject_entity_key,
            'subject_id' => $row->subject_id === null ? null : (int) $row->subject_id,
            'subject_label' => $payload['subject_label'] ?? null,
            'reference' => $payload['reference'] ?? null,
            'used' => is_array($payload['used'] ?? null) ? $payload['used'] : [],
            'result' => $payload['result'] ?? null,
            'created_at' => $row->created_at,
        ];
    }

    /**
     * A template or prompt, but only if it belongs to this module.
     *
     * The module check is the whole point: it is what stops a Fees operation being
     * recorded against another module's template, whether by a bug or by a hand-edited
     * request.
     */
    private function resolveTemplate(int $id, string $module, int|string|null $institute): ?array
    {
        if (! Schema::hasTable('ai_templates')) {
            return null;
        }

        $row = DB::table('ai_templates')
            ->where('id', $id)
            ->where('module_key', $module)
            ->where(function ($query) use ($institute) {
                $query->where('sub_institute_id', $institute)
                    ->orWhereNull('sub_institute_id');
            })
            ->first();

        if ($row === null) {
            return null;
        }

        return [
            'id' => (int) $row->id,
            'key' => (string) $row->template_key,
            'name' => (string) $row->name,
            'kind' => (string) ($row->kind ?? 'prompt'),
            'version' => (int) ($row->version ?? 1),
            'status' => (string) $row->status,
        ];
    }

    /** The acting user's name, so the ledger reads without a join. */
    private function actorLabel(int|string|null $userId): ?string
    {
        if ($userId === null || ! Schema::hasTable('users')) {
            return null;
        }

        $row = DB::table('users')->where('id', $userId)->first(['first_name', 'last_name']);

        if ($row === null) {
            return null;
        }

        $name = trim(((string) ($row->first_name ?? '')) . ' ' . ((string) ($row->last_name ?? '')));

        return $name === '' ? null : $name;
    }
}
