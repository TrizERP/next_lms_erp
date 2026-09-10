<?php

namespace App\Brain\Intelligence;

use App\Brain\Support\SchemaCache;
use Illuminate\Support\Facades\DB;

/**
 * The whole loop, in the order the reference Brain runs it:
 *
 *   vivek_erp  →  Rules  →  Signal  →  Evidence  →  Reasoning  →  Hypothesis
 *              →  Recommendation  →  Knowledge / Mental model  →  Telemetry
 *
 * ONE ENTRY POINT, because the stages are not independent. Reasoning over a
 * signal that has not been raised yet produces nothing; harvesting knowledge
 * from cases that have not been reasoned over produces nothing. Exposing the
 * stages separately would let a caller run them out of order and conclude the
 * engine was broken.
 *
 * IT IS IDEMPOTENT, and that is what makes it safe to schedule. Signals refresh
 * rather than duplicate (SignalWriter), cases skip signals already reasoned over
 * (Reasoner), and knowledge assets are keyed by root-cause family so a nightly
 * run sharpens the organizational memory instead of growing a duplicate copy of
 * it. Running this ten times in a row leaves the same row count as running it
 * once, with fresher numbers.
 */
final class IntelligencePipeline
{
    private const ACTOR = 'brain.pipeline';

    public function __construct(private readonly string $tenantId, private readonly ?string $syear = null)
    {
    }

    /**
     * @return array{tenantId: string, rules: array, signals: array, reasoning: array, knowledge: array, elapsedMs: int}
     */
    public function run(): array
    {
        $started = microtime(true);

        $writer = new SignalWriter($this->tenantId);
        $rules = new LmsSignalRules($this->tenantId, $writer, $this->syear);

        $outcomes = [];
        $created = 0;
        $refreshed = 0;

        foreach ($rules->applicable() as $ruleKey => $rule) {
            try {
                $result = $rule();
            } catch (\Throwable $e) {
                // One rule that cannot read its table must not abort the other
                // twenty-five. The failure is reported in the result rather than
                // swallowed, so a broken rule is visible on the Ingestion screen
                // instead of silently producing "no findings".
                $outcomes[] = [
                    'rule' => $ruleKey,
                    'created' => false,
                    'refreshed' => false,
                    'reason' => 'error',
                    'error' => $e->getMessage(),
                ];

                continue;
            }

            $created += $result['created'] ? 1 : 0;
            $refreshed += ! empty($result['refreshed']) ? 1 : 0;

            $outcomes[] = [
                'rule' => $ruleKey,
                'created' => (bool) $result['created'],
                'refreshed' => (bool) ($result['refreshed'] ?? false),
                'signalId' => $result['signalId'] ?? null,
                'reason' => $result['reason'] ?? null,
                'hasApprovedCause' => RuleCatalogue::for($ruleKey) !== null,
            ];
        }

        $reasoning = (new Reasoner($this->tenantId))->reasonOverOpenSignals();
        $knowledge = $this->harvestKnowledge();
        $this->reinforceMentalModels();
        // The executable half: a named procedure for every standing remedy, and
        // the policy that says a human has to authorise it.
        $automation = (new AutomationCatalogue($this->tenantId))->sync();
        $this->linkRecommendationsToEsos();

        $elapsed = (int) round((microtime(true) - $started) * 1000);
        $this->telemetry('intelligence.run', $created + $refreshed);

        return [
            'tenantId' => $this->tenantId,
            'rules' => [
                'evaluated' => count($outcomes),
                'signalsCreated' => $created,
                'signalsRefreshed' => $refreshed,
                'outcomes' => $outcomes,
            ],
            'signals' => $this->signalSummary(),
            'reasoning' => $reasoning,
            'knowledge' => $knowledge,
            'automation' => $automation,
            'elapsedMs' => $elapsed,
        ];
    }

    /**
     * Point each recommendation at the ESO that would carry it out.
     *
     * Without this link a recommendation is advice with no executable form, and
     * the Automation screen cannot show what approving it would actually run.
     * The join is by rule key, which both sides derive from the same signal.
     */
    private function linkRecommendationsToEsos(): void
    {
        if (! SchemaCache::hasTable('hpbrain_recommendations')
            || ! SchemaCache::hasTable('hpbrain_eso_definitions')
            || ! SchemaCache::hasColumn('hpbrain_recommendations', 'eso_id')) {
            return;
        }

        $esoByCode = DB::table('hpbrain_eso_definitions')
            ->where('tenant_id', $this->tenantId)->pluck('id', 'eso_code');

        $rows = DB::table('hpbrain_recommendations as r')
            ->join('hpbrain_reasoning_steps as s', 's.id', '=', 'r.reasoning_step_id')
            ->join('hpbrain_signals as sg', 'sg.id', '=', 's.signal_id')
            ->where('r.tenant_id', $this->tenantId)
            ->whereNull('r.eso_id')
            ->whereNotNull('sg.rule_key')
            ->get(['r.id as recommendation_id', 'sg.rule_key']);

        foreach ($rows as $row) {
            $code = 'ESO-'.strtoupper(str_replace('_', '-', (string) $row->rule_key));
            if (! isset($esoByCode[$code])) {
                continue;
            }

            DB::table('hpbrain_recommendations')
                ->where('id', $row->recommendation_id)
                ->update(['eso_id' => $esoByCode[$code]]);
        }
    }

    /**
     * Organizational memory: one knowledge asset per root-cause family this
     * institute has actually experienced.
     *
     * KEYED BY FAMILY, NOT BY CASE, and that is the whole point of it. A case is
     * one occurrence; a family is what the organization has learnt about itself.
     * Six separate ownership-gap findings should sharpen ONE piece of knowledge
     * — "ownership is assigned late here, across departments and reporting
     * lines" — not create six near-identical notes. So the asset is upserted by
     * family and its confidence and reuse_count track how often the pattern has
     * recurred.
     *
     * @return array{written: int, updated: int, families: array<int, string>}
     */
    private function harvestKnowledge(): array
    {
        if (! SchemaCache::hasTable('hpbrain_knowledge_assets') || ! SchemaCache::hasTable('hpbrain_hypotheses')) {
            return ['written' => 0, 'updated' => 0, 'families' => []];
        }

        $families = DB::table('hpbrain_hypotheses as h')
            ->join('hpbrain_cases as c', 'c.id', '=', 'h.case_id')
            ->where('h.tenant_id', $this->tenantId)
            ->select(
                'h.root_cause_family',
                DB::raw('COUNT(DISTINCT h.case_id) as occurrences'),
                DB::raw('AVG(h.confidence) as mean_confidence')
            )
            ->groupBy('h.root_cause_family')
            ->get();

        $written = 0;
        $updated = 0;
        $now = now()->format('Y-m-d H:i:s');

        foreach ($families as $family) {
            $familyKey = (string) $family->root_cause_family;

            $cases = DB::table('hpbrain_hypotheses as h')
                ->join('hpbrain_cases as c', 'c.id', '=', 'h.case_id')
                ->where('h.tenant_id', $this->tenantId)
                ->where('h.root_cause_family', $familyKey)
                ->orderByDesc('h.confidence')
                ->limit(12)
                ->get(['c.title', 'c.id as case_id', 'h.statement', 'h.confidence']);

            $actions = [];
            foreach (RuleCatalogue::CAUSES as $rule => $cause) {
                if ($cause['family'] === $familyKey) {
                    $actions[$cause['action']] = true;
                }
            }

            $content = $this->composeKnowledge($familyKey, $cases, array_keys($actions));

            $existing = DB::table('hpbrain_knowledge_assets')
                ->where('tenant_id', $this->tenantId)
                ->where('category', $familyKey)
                ->where('created_by', self::ACTOR)
                ->first();

            $row = [
                'tenant_id' => $this->tenantId,
                'title' => $this->familyTitle($familyKey).' — organizational pattern',
                'category' => $familyKey,
                'content' => $content,
                'tags' => json_encode(array_values(array_unique(array_merge(
                    ['root-cause', $familyKey],
                    $cases->pluck('case_id')->take(5)->map(fn ($id) => 'case:'.$id)->all()
                )))),
                'confidence' => round((float) $family->mean_confidence, 4),
                'status' => 'active',
                'created_by' => self::ACTOR,
                'updated_date' => $now,
            ];

            if ($existing) {
                DB::table('hpbrain_knowledge_assets')->where('id', $existing->id)->update(SchemaCache::only(
                    'hpbrain_knowledge_assets',
                    $row + ['reuse_count' => (int) $family->occurrences]
                ));
                $updated++;

                continue;
            }

            DB::table('hpbrain_knowledge_assets')->insert(SchemaCache::only('hpbrain_knowledge_assets', $row + [
                'id' => Uuid::v4(),
                'related_person_ids' => '[]',
                'related_capability_ids' => '[]',
                'reuse_count' => (int) $family->occurrences,
                'created_date' => $now,
            ]));
            $written++;
        }

        return [
            'written' => $written,
            'updated' => $updated,
            'families' => $families->pluck('root_cause_family')->map(fn ($f) => (string) $f)->all(),
        ];
    }

    /**
     * A mental model per root-cause family, reinforced each time the family
     * recurs.
     *
     * hpbrain_mental_models.reinforcement_count is what makes this a model rather
     * than a note: it records how many times the organization has seen the
     * pattern hold. The reference Brain uses that count to decide which models
     * are trusted enough to drive automation, which is why it is maintained here
     * even though nothing reads it yet.
     */
    private function reinforceMentalModels(): void
    {
        if (! SchemaCache::hasTable('hpbrain_mental_models') || ! SchemaCache::hasTable('hpbrain_hypotheses')) {
            return;
        }

        $now = now()->format('Y-m-d H:i:s');

        $families = DB::table('hpbrain_hypotheses')
            ->where('tenant_id', $this->tenantId)
            ->select('root_cause_family', DB::raw('COUNT(*) as occurrences'), DB::raw('AVG(confidence) as mean_confidence'))
            ->groupBy('root_cause_family')
            ->get();

        foreach ($families as $family) {
            $familyKey = (string) $family->root_cause_family;

            $rules = array_keys(array_filter(
                RuleCatalogue::CAUSES,
                fn ($cause) => $cause['family'] === $familyKey
            ));

            $existing = DB::table('hpbrain_mental_models')
                ->where('tenant_id', $this->tenantId)->where('domain', $familyKey)->first();

            $row = [
                'tenant_id' => $this->tenantId,
                'name' => $this->familyTitle($familyKey),
                'description' => sprintf(
                    'Observed %d time%s in this institute across %d rule%s: %s.',
                    (int) $family->occurrences,
                    ((int) $family->occurrences) === 1 ? '' : 's',
                    count($rules),
                    count($rules) === 1 ? '' : 's',
                    implode(', ', $rules)
                ),
                'domain' => $familyKey,
                'rules' => json_encode($rules),
                'confidence' => round((float) $family->mean_confidence, 4),
                'reinforcement_count' => (int) $family->occurrences,
                'status' => 'active',
                'created_by' => self::ACTOR,
                'updated_date' => $now,
            ];

            if ($existing) {
                DB::table('hpbrain_mental_models')->where('id', $existing->id)
                    ->update(SchemaCache::only('hpbrain_mental_models', $row));

                continue;
            }

            DB::table('hpbrain_mental_models')->insert(SchemaCache::only('hpbrain_mental_models', $row + [
                'id' => Uuid::v4(),
                'version' => 1,
                'created_date' => $now,
            ]));
        }
    }

    private function composeKnowledge(string $family, $cases, array $actions): string
    {
        $lines = [];
        $lines[] = sprintf('Root-cause family: %s', str_replace('_', ' ', $family));
        $lines[] = '';
        $lines[] = sprintf('This institute has produced %d evidenced finding%s in this family.', $cases->count(), $cases->count() === 1 ? '' : 's');
        $lines[] = '';
        $lines[] = 'Findings:';

        foreach ($cases as $case) {
            $lines[] = sprintf('  • %s (confidence %.2f)', (string) $case->title, (float) $case->confidence);
        }

        if ($actions !== []) {
            $lines[] = '';
            $lines[] = 'Standing remedies for this family:';
            foreach ($actions as $action) {
                $lines[] = '  • '.$action;
            }
        }

        $statement = $cases->first()->statement ?? null;
        if ($statement) {
            $lines[] = '';
            $lines[] = 'Working explanation: '.$statement;
        }

        return implode("\n", $lines);
    }

    private function familyTitle(string $family): string
    {
        return ucwords(str_replace('_', ' ', $family));
    }

    /** @return array<string, int> */
    private function signalSummary(): array
    {
        if (! SchemaCache::hasTable('hpbrain_signals')) {
            return [];
        }

        return DB::table('hpbrain_signals')
            ->where('tenant_id', $this->tenantId)
            ->select('severity', DB::raw('COUNT(*) as total'))
            ->groupBy('severity')
            ->pluck('total', 'severity')
            ->map(fn ($v) => (int) $v)
            ->all();
    }

    private function telemetry(string $event, float $value): void
    {
        try {
            if (! SchemaCache::hasTable('hpbrain_telemetry_events')) {
                return;
            }

            DB::table('hpbrain_telemetry_events')->insert(SchemaCache::only('hpbrain_telemetry_events', [
                'id' => Uuid::v4(),
                'tenant_id' => $this->tenantId,
                'org_id' => 'org-'.$this->tenantId.'-'.$this->tenantId,
                'event_type' => $event,
                'entity_type' => 'Tenant',
                'entity_id' => $this->tenantId,
                'metric_name' => 'signals_written',
                'metric_value' => $value,
                'unit' => 'signals',
                'metadata' => '{}',
                'recorded_date' => now(),
            ]));
        } catch (\Throwable $e) {
            // Telemetry is observability, not the operation.
        }
    }
}
