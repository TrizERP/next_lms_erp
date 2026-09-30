<?php

use App\Domain\Governance\Verb;
use App\Domain\PAL\CoherenceRisk\CoherenceRiskAgent;
use App\Domain\PAL\CoherenceRisk\CompoundingRootBlockerDetector;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Seeds PAL's first governed agent: the Coherence Risk agent, which flags a
 * student whose weak concepts trace back to root blockers scattered across
 * multiple chapters (CompoundingRootBlockerDetector, built on
 * CoherenceMapRepository::rootBlockers() — Phases 3-5's already-verified
 * traversal, not new Cypher).
 *
 * No new ontology entities are registered here. `learning_concept` (->
 * lms_concept), `student`, `chapter`, `standard`, `division`, `enrollment`
 * already exist in the core ontology seed (2026_08_20_000007) — this
 * agent's `allowed_entities` reuses them as-is. This is the deferred Phase 9
 * of the PAL Neo4j roadmap: the generic AgentRunner/ontology layer supplies
 * governance (role gate, evidence/case/recommendation persistence, the human
 * approval gate); it does not re-implement the graph traversal, which stays
 * in CoherenceMapRepository where it is already proven.
 *
 * No workflow is registered: this agent's recommendation is a reviewable
 * flag for a teacher, not a trigger for a further automated action. See
 * CoherenceRiskAgent's own docblock for why binding one now would be
 * speculative.
 *
 * Idempotent.
 */
return new class extends Migration
{
    private const AGENT_KEY = 'pal_coherence_risk';

    public function up(): void
    {
        $this->seedSignalDefinition();
        $this->seedAgent();
    }

    public function down(): void
    {
        if (Schema::hasTable('ai_agents')) {
            DB::table('ai_agents')->where('agent_key', self::AGENT_KEY)->whereNull('sub_institute_id')->delete();
        }

        if (Schema::hasTable('ai_signal_definitions')) {
            DB::table('ai_signal_definitions')
                ->where('signal_key', CompoundingRootBlockerDetector::KEY)
                ->whereNull('sub_institute_id')
                ->delete();
        }
    }

    private function seedSignalDefinition(): void
    {
        if (! Schema::hasTable('ai_signal_definitions')) {
            return;
        }

        $payload = [
            'label' => 'Compounding root blockers',
            'domain' => 'pal',
            'subject_entity_key' => 'student',
            'description' => 'Several of the student\'s weak concepts trace back to unmastered root blockers '
                . 'spread across more than one chapter — a systemic gap rather than a local one.',
            'detector_class' => CompoundingRootBlockerDetector::class,
            'severity_scale' => 'risk_score',
            // Same band shape ThresholdRegistry falls back to for every other
            // signal; a tenant may override via this row without touching code.
            'thresholds' => json_encode(['bands' => ['critical' => 0.75, 'high' => 0.5, 'moderate' => 0.25]]),
            'inputs' => json_encode(['pal_concept_mastery', 'lms_concept', 'chapter_master']),
            'requires_evidence' => true,
            'status' => 1,
            'updated_at' => now(),
        ];

        $this->upsert('ai_signal_definitions', ['signal_key' => CompoundingRootBlockerDetector::KEY], $payload, [
            'signal_key' => CompoundingRootBlockerDetector::KEY,
        ]);
    }

    private function seedAgent(): void
    {
        if (! Schema::hasTable('ai_agents')) {
            return;
        }

        $payload = [
            'name' => 'Coherence Risk Agent',
            'domain' => 'pal',
            'purpose' => 'Flag students whose weak concepts share root causes spread across multiple chapters, '
                . 'for a teacher to review.',
            'description' => 'Runs the compounding-root-blocker detector over CoherenceMapRepository::rootBlockers(), '
                . 'builds a case per flagged student, composes an evidence-backed explanation, and drafts a review '
                . 'recommendation. Cannot execute any action — only recommend.',
            'runner_class' => CoherenceRiskAgent::class,
            'agent_type' => 'domain',

            'allowed_tools' => json_encode([]),
            'allowed_entities' => json_encode([
                'student', 'enrollment', 'standard', 'division', 'learning_concept', 'chapter',
                'signal', 'evidence', 'case', 'hypothesis', 'explanation', 'recommendation',
            ]),
            'allowed_signal_keys' => json_encode([
                CompoundingRootBlockerDetector::KEY,
            ]),

            // The ceiling. It may recommend; it may not execute — no
            // authorized_workflow_keys at all, since this agent binds none.
            'max_verb' => Verb::Recommend->value,
            'may_execute_actions' => false,
            'authorized_workflow_keys' => '',

            'input_schema' => json_encode([
                'type' => 'object',
                'properties' => [
                    'student_id' => ['type' => 'integer'],
                    'student_ids' => ['type' => 'array', 'items' => ['type' => 'integer']],
                    'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 200],
                ],
            ]),
            'output_schema' => json_encode([
                'type' => 'object',
                'required' => ['students_flagged', 'cases'],
                'properties' => [
                    'students_flagged' => ['type' => 'integer'],
                    'cases' => ['type' => 'array'],
                    'confidence' => ['type' => 'number'],
                ],
            ]),
            'required_permissions' => json_encode(['lms:student:read']),
            'allowed_roles' => json_encode(['admin', 'staff']),

            'min_confidence' => 0.4,
            'min_evidence_count' => 1,
            'timeout_seconds' => 60,
            'max_retries' => 1,
            'config' => json_encode(['case_type' => CoherenceRiskAgent::CASE_TYPE]),
            'status' => 1,
            'updated_at' => now(),
        ];

        $this->upsert('ai_agents', ['agent_key' => self::AGENT_KEY], $payload, [
            'agent_key' => self::AGENT_KEY,
        ]);
    }

    /**
     * Insert-or-update against the platform baseline (sub_institute_id IS NULL),
     * never touching a tenant's own override. Mirrors
     * 2026_08_20_000009_seed_academic_risk_intelligence.php's helper exactly.
     */
    private function upsert(string $table, array $match, array $payload, array $insertOnly): ?int
    {
        $query = DB::table($table)->whereNull('sub_institute_id');

        foreach ($match as $column => $value) {
            $query->where($column, $value);
        }

        $existing = $query->first();

        if ($existing) {
            DB::table($table)->where('id', $existing->id)->update($payload);

            return (int) $existing->id;
        }

        return (int) DB::table($table)->insertGetId($payload + $insertOnly + [
            'sub_institute_id' => null,
            'client_id' => null,
            'created_at' => now(),
        ]);
    }
};
