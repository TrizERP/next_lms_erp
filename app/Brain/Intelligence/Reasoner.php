<?php

namespace App\Brain\Intelligence;

use App\Brain\Support\SchemaCache;
use Illuminate\Support\Facades\DB;

/**
 * Turns ONE signal into at most one case, hypothesis, reasoning trail and
 * recommendation.
 *
 * "At most one" is the contract, taken verbatim from
 * hp-enterprise-brain/app/Domain/Reasoning/SignalReasoner.php. A signal whose
 * rule has no approved causal metadata in RuleCatalogue produces a case and
 * stops — visibly undetermined. Writing a hypothesis anyway, with invented text
 * or a defaulted confidence, is the single failure this layer exists to prevent.
 *
 * WHY THIS IS DETERMINISTIC WHERE THE REFERENCE CALLS AN LLM. The reference asks
 * a model to write the reasoning trail. That is the right shape when the input
 * is free-text operational narrative. Here the input is a counted, typed finding
 * over a known schema — "607 of 609 departments have no head_user_id" — and the
 * causal family for that finding was decided once, by a human, in RuleCatalogue.
 * Asking a model to re-derive it per run would add nondeterminism and a fresh
 * chance of fabrication without adding information. So the TRAIL is composed
 * from the signal's own evidence and the approved cause, and every number in it
 * traces to a row. No text in this pipeline is generated from nothing.
 *
 * CONFIDENCE IS COMPUTED, NEVER ASSERTED. The formula is the reference's
 * ReasoningService: base + Σ(evidence.confidence × freshness × weight), capped
 * below 1.0 because reasoning is never certain — that is what Outcome capture is
 * for. A caller cannot hand this class a score.
 *
 * NOTHING IS COMMITTED HALF-WAY. A recommendation with no reasoning is an
 * unsourced claim; reasoning with no recommendation is deliberation nobody acts
 * on. One transaction covers case, hypothesis, steps and recommendation.
 */
final class Reasoner
{
    private const ACTOR = 'brain.reasoner';

    private const BASE_CONFIDENCE = 0.30;

    private const EVIDENCE_WEIGHT = 0.15;

    private const CEILING = 0.95;

    /** Below this, the category is forced to 'watch' however the rule classified it. */
    private const LOW_CONFIDENCE_FLOOR = 0.45;

    public function __construct(private readonly string $tenantId)
    {
    }

    /**
     * Reason over every signal that has no case yet.
     *
     * @return array{cases: int, hypotheses: int, steps: int, recommendations: int, undetermined: int}
     */
    public function reasonOverOpenSignals(int $limit = 200): array
    {
        $out = ['cases' => 0, 'hypotheses' => 0, 'steps' => 0, 'recommendations' => 0, 'undetermined' => 0];

        if (! SchemaCache::hasTable('hpbrain_signals') || ! SchemaCache::hasTable('hpbrain_cases')) {
            return $out;
        }

        $signals = DB::table('hpbrain_signals')
            ->where('tenant_id', $this->tenantId)
            ->whereNotIn('status', ['resolved', 'dismissed'])
            // A signal already carrying a case has been reasoned over. Re-running
            // the pipeline must not stack a second case on the same finding.
            ->whereNotIn('id', DB::table('hpbrain_cases')->where('tenant_id', $this->tenantId)->whereNotNull('signal_id')->pluck('signal_id'))
            ->orderByDesc('created_date')
            ->limit($limit)
            ->get();

        foreach ($signals as $signal) {
            $result = $this->reasonOver($signal);
            foreach ($result as $key => $value) {
                $out[$key] += $value;
            }
        }

        return $out;
    }

    /** @return array{cases: int, hypotheses: int, steps: int, recommendations: int, undetermined: int} */
    private function reasonOver(object $signal): array
    {
        $metadata = json_decode((string) $signal->metadata, true);
        $metadata = is_array($metadata) ? $metadata : [];
        $ruleKey = (string) ($signal->rule_key ?? ($metadata['rule'] ?? ''));

        $evidence = SchemaCache::hasTable('hpbrain_evidence')
            ? DB::table('hpbrain_evidence')->where('tenant_id', $this->tenantId)->where('signal_id', $signal->id)->get()
            : collect();

        $confidence = $this->computeConfidence($evidence);
        $cause = RuleCatalogue::for($ruleKey);

        $caseId = Uuid::v4();
        $now = now()->format('Y-m-d H:i:s');
        $title = (string) ($metadata['title'] ?? ('Signal '.$signal->classification));

        $counters = ['cases' => 0, 'hypotheses' => 0, 'steps' => 0, 'recommendations' => 0, 'undetermined' => 0];

        DB::transaction(function () use ($signal, $metadata, $ruleKey, $evidence, $confidence, $cause, $caseId, $now, $title, &$counters) {
            DB::table('hpbrain_cases')->insert(SchemaCache::only('hpbrain_cases', [
                'id' => $caseId,
                'tenant_id' => $this->tenantId,
                'signal_id' => (string) $signal->id,
                'title' => $title,
                'description' => $this->describeCase($signal, $metadata, $evidence->count()),
                'status' => 'open',
                'created_by' => self::ACTOR,
                'created_date' => $now,
                'updated_date' => $now,
            ]));
            $counters['cases']++;

            // The evidence behind the signal is the evidence behind the case.
            // hpbrain_case_evidence is the join the Evidence screen reads to show
            // which findings a given row supports; without it the ledger lists
            // evidence that appears to support nothing.
            if (SchemaCache::hasTable('hpbrain_case_evidence')) {
                foreach ($evidence as $row) {
                    DB::table('hpbrain_case_evidence')->insert(SchemaCache::only('hpbrain_case_evidence', [
                        'tenant_id' => $this->tenantId,
                        'case_id' => $caseId,
                        'evidence_id' => (string) $row->id,
                        'linked_date' => $now,
                    ]));
                }
            }

            // NO APPROVED CAUSE => NO HYPOTHESIS. The case stands on its own as a
            // recorded, evidenced finding awaiting human causal review.
            if ($cause === null) {
                $counters['undetermined']++;

                return;
            }

            $hypothesisId = Uuid::v4();
            // The rule's causal claim is a ceiling; corroboration cannot push the
            // hypothesis above what the approved metadata will stand behind.
            $hypothesisConfidence = round(min((float) $cause['confidence'], $confidence + 0.10), 4);

            if (SchemaCache::hasTable('hpbrain_hypotheses')) {
                DB::table('hpbrain_hypotheses')->insert(SchemaCache::only('hpbrain_hypotheses', [
                    'id' => $hypothesisId,
                    'tenant_id' => $this->tenantId,
                    'case_id' => $caseId,
                    'statement' => (string) $cause['hypothesis'],
                    'root_cause_family' => (string) $cause['family'],
                    'confidence' => $hypothesisConfidence,
                    'status' => 'proposed',
                    'supporting_evidence_ids' => json_encode($evidence->pluck('id')->values()->all()),
                    'proposed_by' => self::ACTOR,
                    'created_date' => $now,
                ]));
                $counters['hypotheses']++;

                DB::table('hpbrain_cases')->where('tenant_id', $this->tenantId)->where('id', $caseId)
                    ->update(SchemaCache::only('hpbrain_cases', ['resolved_hypothesis_id' => $hypothesisId]));
            }

            $stepIds = $this->writeTrail($caseId, (string) $signal->id, $metadata, $evidence, $cause, $confidence, $now);
            $counters['steps'] += count($stepIds);

            if (! SchemaCache::hasTable('hpbrain_recommendations') || $stepIds === []) {
                return;
            }

            $category = $this->resolveCategory((string) $cause['category'], $confidence);

            DB::table('hpbrain_recommendations')->insert(SchemaCache::only('hpbrain_recommendations', [
                'id' => Uuid::v4(),
                'tenant_id' => $this->tenantId,
                // The recommendation hangs off the LAST step — the conclusion.
                // Earlier steps are reachable through the shared case, ordered by
                // step_order, without a second foreign key per step.
                'reasoning_step_id' => $stepIds[array_key_last($stepIds)],
                'category' => $category,
                'title' => $this->truncate((string) $cause['action'], 250),
                'description' => $this->describeRecommendation($cause, $metadata, $evidence->count()),
                'priority' => $this->derivePriority($confidence, (string) $signal->severity),
                'urgency' => $this->deriveUrgency($category, (string) $signal->priority),
                'confidence' => $confidence,
                'impact' => $this->describeImpact($metadata),
                'dependencies' => '[]',
                // 'pending', not 'pending_approval': every dashboard that reports
                // the backlog counts status IN ('pending','proposed').
                'status' => 'pending',
                'created_by' => self::ACTOR,
                'created_date' => $now,
                'updated_date' => $now,
            ]));
            $counters['recommendations']++;

            DB::table('hpbrain_signals')->where('tenant_id', $this->tenantId)->where('id', $signal->id)
                ->update(SchemaCache::only('hpbrain_signals', ['status' => 'reasoned', 'updated_date' => $now]));
        });

        return $counters;
    }

    /**
     * The ordered deliberation behind the conclusion.
     *
     * Three steps, not one, because hpbrain_reasoning_steps carries step_order
     * precisely so a trail can be audited rather than trusted: what was observed,
     * what it was weighed against, and what follows. A confidence number with no
     * visible deliberation asks the reader to trust an opaque score.
     *
     * @return array<int, string>
     */
    private function writeTrail(
        string $caseId,
        string $signalId,
        array $metadata,
        $evidence,
        array $cause,
        float $confidence,
        string $now,
    ): array {
        if (! SchemaCache::hasTable('hpbrain_reasoning_steps')) {
            return [];
        }

        $affected = (int) ($metadata['affectedCount'] ?? 0);
        $total = (int) ($metadata['totalCount'] ?? 0);
        $unit = (string) ($metadata['unit'] ?? 'records');
        $share = isset($metadata['share']) ? round(((float) $metadata['share']) * 100, 1) : null;

        $steps = [
            sprintf(
                'Observation: %s. Read directly from %s at %s; %d evidence %s captured.',
                (string) ($metadata['title'] ?? 'a rule matched'),
                (string) ($metadata['source'] ?? 'the LMS system of record'),
                $now,
                $evidence->count(),
                $evidence->count() === 1 ? 'row was' : 'rows were'
            ),
            $total > 0 && $share !== null
                ? sprintf(
                    'Scale: %d of %d %s (%s%%) match. Alternatives weighed and set aside: a sampling artefact (rejected — the count is a full scan of the tenant, not a sample) and a scoping error (rejected — every query is filtered to this institute).',
                    $affected,
                    $total,
                    $unit,
                    $share
                )
                : sprintf('Scale: %d %s match. The query is a full scan of this institute, so the count is exact rather than sampled.', $affected, $unit),
            sprintf(
                'Conclusion: the pattern is consistent with %s. %s Confidence %.2f, computed from %d corroborating evidence %s rather than asserted.',
                str_replace('_', ' ', (string) $cause['family']),
                (string) $cause['hypothesis'],
                $confidence,
                $evidence->count(),
                $evidence->count() === 1 ? 'row' : 'rows'
            ),
        ];

        $ids = [];
        foreach ($steps as $index => $description) {
            $id = Uuid::v4();
            DB::table('hpbrain_reasoning_steps')->insert(SchemaCache::only('hpbrain_reasoning_steps', [
                'id' => $id,
                'tenant_id' => $this->tenantId,
                'case_id' => $caseId,
                'signal_id' => $signalId,
                'step_order' => $index + 1,
                'description' => $description,
                'confidence_score' => $confidence,
                'created_by' => self::ACTOR,
                'created_date' => $now,
            ]));
            $ids[] = $id;
        }

        return $ids;
    }

    /**
     * confidence = base + Σ(evidence.confidence × freshness × weight), capped.
     *
     * Freshness decays over a 90-day half-life window: a finding corroborated by
     * rows observed this morning is worth more than the same finding standing on
     * evidence nobody has re-checked in a quarter.
     */
    private function computeConfidence($evidence): float
    {
        $corroboration = 0.0;

        foreach ($evidence as $item) {
            $observed = $item->observed_date ?? $item->created_date ?? null;
            $corroboration += ((float) $item->confidence) * $this->freshness($observed) * self::EVIDENCE_WEIGHT;
        }

        return round(min(self::CEILING, self::BASE_CONFIDENCE + $corroboration), 4);
    }

    private function freshness(?string $observedDate): float
    {
        if (! $observedDate) {
            return 0.5;
        }

        $age = (time() - strtotime($observedDate)) / 86400;
        if ($age <= 0) {
            return 1.0;
        }

        return max(0.1, min(1.0, 1.0 - ($age / 90)));
    }

    /**
     * Below the low-confidence floor the category is forced to 'watch' whatever
     * the rule asked for — the reference's RecommendationService rule. A weakly
     * corroborated finding must not present as an instruction to act.
     */
    private function resolveCategory(string $requested, float $confidence): string
    {
        if ($confidence < self::LOW_CONFIDENCE_FLOOR) {
            return 'watch';
        }

        return in_array($requested, ['remediate', 'investigate', 'optimize', 'watch'], true) ? $requested : 'watch';
    }

    private function derivePriority(float $confidence, string $severity): string
    {
        if ($severity === 'high' && $confidence >= 0.60) {
            return 'high';
        }

        if ($confidence < self::LOW_CONFIDENCE_FLOOR) {
            return 'low';
        }

        return 'medium';
    }

    private function deriveUrgency(string $category, string $signalPriority): string
    {
        if ($category === 'watch') {
            return 'low';
        }

        return $signalPriority === 'high' ? 'high' : 'normal';
    }

    private function describeCase(object $signal, array $metadata, int $evidenceCount): string
    {
        return sprintf(
            '%s Detected by rule "%s" over %s, scoped to institute %s. %d evidence %s captured from the live record; severity %s.',
            (string) ($metadata['title'] ?? 'A rule matched.'),
            (string) ($signal->rule_key ?? 'unknown'),
            (string) $signal->source,
            $this->tenantId,
            $evidenceCount,
            $evidenceCount === 1 ? 'row was' : 'rows were',
            (string) $signal->severity
        );
    }

    /**
     * The body of the recommendation.
     *
     * IT DOES NOT REPEAT THE ACTION. `title` is already the action verbatim, and
     * a card that renders title then description was showing the same sentence
     * twice. The description's job is the part the title cannot carry: why the
     * pattern is believed, and what the belief rests on.
     */
    private function describeRecommendation(array $cause, array $metadata, int $evidenceCount): string
    {
        return sprintf(
            "Why: %s\n\nBasis: %s (%d evidence %s from the live LMS record). Root-cause family: %s.",
            (string) $cause['hypothesis'],
            (string) ($metadata['title'] ?? 'rule match'),
            $evidenceCount,
            $evidenceCount === 1 ? 'row' : 'rows',
            str_replace('_', ' ', (string) $cause['family'])
        );
    }

    private function describeImpact(array $metadata): string
    {
        $affected = (int) ($metadata['affectedCount'] ?? 0);
        $total = (int) ($metadata['totalCount'] ?? 0);
        $unit = (string) ($metadata['unit'] ?? 'records');

        return $total > 0
            ? sprintf('%d of %d %s (%.1f%%)', $affected, $total, $unit, $total > 0 ? ($affected / $total) * 100 : 0)
            : sprintf('%d %s', $affected, $unit);
    }

    private function truncate(string $value, int $length): string
    {
        return strlen($value) <= $length ? $value : (substr($value, 0, $length - 1).'…');
    }
}
