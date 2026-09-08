<?php

namespace App\Brain\Intelligence;

use App\Brain\Support\SchemaCache;
use Illuminate\Support\Facades\DB;

/**
 * The executable half of the loop: one ESO definition per standing remedy, and
 * the autonomy policy that governs whether it may run.
 *
 * WHAT AN ESO IS HERE. In the reference Brain an ESO (Executable Standard
 * Operation) is a named, versioned procedure with declared inputs, trust level
 * and escalation path — the thing a decision authorises. RuleCatalogue already
 * holds a human-authored remedy for every rule; this class projects those
 * remedies into hpbrain_eso_definitions so a recommendation has something real
 * to point at when someone approves it.
 *
 * PROVENANCE IS 'authored', NOT 'generated', AND THAT IS THE HONEST LABEL. The
 * procedure text comes from a human decision recorded in RuleCatalogue, not from
 * a model and not from a random generator. Nothing here invents an operation the
 * organization has not agreed to.
 *
 * EVERY ESO SHIPS AT trust_level 'suggest', WHICH IS THE POINT. The reference's
 * autonomy ladder is observe|suggest|approve|autonomous. Nothing in this
 * installation may execute against the LMS unattended, because none of these
 * procedures has an execution history to earn a higher level with — so the
 * policy below pins them at 'suggest' and requires a named human decision.
 * Shipping them at 'autonomous' would be a fabricated governance signal on a
 * screen that governs real operations.
 */
final class AutomationCatalogue
{
    private const ACTOR = 'brain.pipeline';

    public function __construct(private readonly string $tenantId)
    {
    }

    /** @return array{esos: array{written: int, updated: int}, policies: array{written: int, updated: int}} */
    public function sync(): array
    {
        return [
            'esos' => $this->syncEsoDefinitions(),
            'policies' => $this->syncPolicies(),
        ];
    }

    /**
     * One ESO per distinct remedy in the approved catalogue.
     *
     * Keyed by eso_code (derived from the rule key) so a nightly run updates the
     * definition rather than versioning a duplicate of it.
     *
     * @return array{written: int, updated: int}
     */
    private function syncEsoDefinitions(): array
    {
        if (! SchemaCache::hasTable('hpbrain_eso_definitions')) {
            return ['written' => 0, 'updated' => 0];
        }

        $written = 0;
        $updated = 0;
        $now = now()->format('Y-m-d H:i:s');

        foreach (RuleCatalogue::CAUSES as $ruleKey => $cause) {
            $code = 'ESO-'.strtoupper(str_replace('_', '-', $ruleKey));

            $row = [
                'tenant_id' => $this->tenantId,
                'org_id' => 'org-'.$this->tenantId.'-'.$this->tenantId,
                'eso_code' => $code,
                'name' => $this->truncate((string) $cause['action'], 250),
                'status' => 'active',
                'provenance' => 'authored',
                'is_cognitive_primitive' => 0,
                'trigger_description' => sprintf(
                    'Raised when rule "%s" matches over the live LMS record for this institute.',
                    $ruleKey
                ),
                'applicable_contexts' => json_encode([$cause['family']]),
                'gap_types' => json_encode([$cause['family']]),
                'objective' => $cause['category'],
                'inputs' => json_encode([
                    ['name' => 'signal_id', 'type' => 'uuid', 'source' => 'hpbrain_signals'],
                    ['name' => 'evidence', 'type' => 'array', 'source' => 'hpbrain_evidence'],
                ]),
                'outputs' => json_encode([
                    ['name' => 'records_corrected', 'type' => 'integer'],
                ]),
                'preconditions' => json_encode(['An open signal exists for rule '.$ruleKey.'.']),
                'procedure_steps' => json_encode([
                    ['order' => 1, 'step' => 'Open the signal and read the evidence rows naming the affected records.'],
                    ['order' => 2, 'step' => (string) $cause['action']],
                    ['order' => 3, 'step' => 'Re-run brain:intelligence; the signal resolves when the rule no longer matches.'],
                ]),
                // Human only. No agent class is listed because none has an
                // execution history in this installation to justify one.
                'allowed_executor_classes' => json_encode(['human']),
                'trust_level' => 'suggest',
                'routing_criteria' => json_encode(['role' => 'tenant_admin']),
                'escalation_path' => json_encode(['tenant_admin']),
                'gotchas' => json_encode([
                    'The counts are exact scans, not samples — a partial fix leaves the signal open with a smaller count.',
                ]),
                'evidence_hooks' => json_encode(['rule' => $ruleKey, 'table' => 'hpbrain_signals']),
                'created_by' => self::ACTOR,
                'updated_date' => $now,
            ];

            $existing = DB::table('hpbrain_eso_definitions')
                ->where('tenant_id', $this->tenantId)->where('eso_code', $code)->first();

            if ($existing) {
                DB::table('hpbrain_eso_definitions')->where('id', $existing->id)
                    ->update(SchemaCache::only('hpbrain_eso_definitions', $row));
                $updated++;

                continue;
            }

            DB::table('hpbrain_eso_definitions')->insert(SchemaCache::only('hpbrain_eso_definitions', $row + [
                'id' => Uuid::v4(),
                'version' => 1,
                'prerequisites' => '[]',
                'constraints_policies' => '[]',
                'scaffolding' => '{}',
                'assessment' => '{}',
                'resources' => '[]',
                'composed_of' => '[]',
                'composes_into' => '[]',
                'created_date' => $now,
            ]));
            $written++;
        }

        return ['written' => $written, 'updated' => $updated];
    }

    /**
     * The autonomy policy every ESO here runs under.
     *
     * One policy, not one per ESO, because the constraint is the same for all of
     * them and duplicating it 27 times would let the copies drift apart — which
     * is precisely how a governance control stops governing.
     *
     * @return array{written: int, updated: int}
     */
    private function syncPolicies(): array
    {
        if (! SchemaCache::hasTable('hpbrain_policies')) {
            return ['written' => 0, 'updated' => 0];
        }

        $now = now()->format('Y-m-d H:i:s');

        $row = [
            'tenant_id' => $this->tenantId,
            'name' => 'LMS remediation — human approval required',
            'scope' => 'brain.recommendations',
            'policy_type' => 'executor_autonomy',
            'allowed_executor_classes' => json_encode(['human']),
            'trust_levels' => json_encode(['observe', 'suggest']),
            'routing_criteria' => json_encode(['role' => 'tenant_admin', 'minimum_confidence' => 0.45]),
            'escalation_path' => json_encode(['tenant_admin']),
            'approval_gates' => json_encode([
                ['gate' => 'named_human_decision', 'required' => true],
            ]),
            'rules' => json_encode([
                'No recommendation may be executed without a decision recorded against a named LMS user.',
                'No executor class other than "human" is permitted, because no agent in this installation has an execution history to earn a higher trust level.',
                'Recommendations below confidence 0.45 are categorised "watch" and are not executable.',
            ]),
            'data_access_rules' => json_encode([
                'Every read is scoped to the acting user\'s sub_institute_id; cross-institute reads are not permitted.',
            ]),
            'regulatory_constraints' => json_encode([
                'Student statutory identifiers are counted but never copied into Brain evidence rows.',
            ]),
            'status' => 'active',
            'created_by' => self::ACTOR,
            'updated_date' => $now,
        ];

        $existing = DB::table('hpbrain_policies')
            ->where('tenant_id', $this->tenantId)->where('scope', 'brain.recommendations')->first();

        if ($existing) {
            DB::table('hpbrain_policies')->where('id', $existing->id)
                ->update(SchemaCache::only('hpbrain_policies', $row));

            return ['written' => 0, 'updated' => 1];
        }

        DB::table('hpbrain_policies')->insert(SchemaCache::only('hpbrain_policies', $row + [
            'id' => Uuid::v4(),
            'version' => 1,
            'created_date' => $now,
        ]));

        return ['written' => 1, 'updated' => 0];
    }

    private function truncate(string $value, int $length): string
    {
        return strlen($value) <= $length ? $value : (substr($value, 0, $length - 1).'…');
    }
}
