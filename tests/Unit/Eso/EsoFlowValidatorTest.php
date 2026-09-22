<?php

namespace Tests\Unit\Eso;

use App\Domain\Eso\Flow\EsoFlowContext;
use App\Domain\Eso\Flow\EsoFlowStage;
use App\Domain\Eso\Flow\EsoFlowStageRegistry;
use App\Services\PAL\Flow\EsoFlowResolver;
use App\Services\PAL\Flow\EsoFlowValidator;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * The composition rules that keep a configurable engine safe to operate.
 *
 * No database: the validator reads config and the handler metadata only, so
 * these run in milliseconds and do not touch the shared dev DB.
 *
 * The first test is the one that matters for rollout step 2. Everything in
 * this step is meant to be dead code — nothing calls it, so it cannot change
 * behaviour — but "dead" is worth nothing if the structure it declares is
 * wrong. Asserting that the shipped `standard` plan reproduces the hardcoded
 * cascade key for key is what makes step 3 a refactor rather than a rewrite.
 */
class EsoFlowValidatorTest extends TestCase
{
    // ══════════════════════════════════════════════════════════════════════
    // The parity anchor
    // ══════════════════════════════════════════════════════════════════════

    /**
     * The shipped default reproduces nextAction()'s hardcoded order exactly.
     *
     * Transcribed from EsoPolicyService::nextAction() (line 1018) and
     * phaseFor() (line 1444). If someone reorders config/pal_flow.php without
     * reordering the engine — or the other way round — this is what catches it,
     * and it catches it before step 3 moves any code.
     */
    public function test_the_standard_profile_reproduces_the_hardcoded_cascade(): void
    {
        $plan = app(EsoFlowResolver::class)->profile('standard');

        $this->assertSame([
            'nodes_present',        // line 1022  no_nodes_defined
            'diagnostic_entry',     // line 1035  D1
            'prerequisite_gate',    // line 1044  D2
            'misconception_scan',   // line 1079  D3, hoisted concept-wide
            'node_loop',            // line 1089  the per-node pipeline
            'mastery_verdict',      // line 1177  D4, terminal
        ], $plan->conceptStageKeys());

        $this->assertSame([
            'retrieval_due',        // line 1103  D5
            'stale_mastery',        // line 1116  derived staleness
            'settled_skip',         // line 1141  continue
            'phase_machine',        // line 1148  teachOrPracticeAction()
        ], $plan->nodeStageKeys());

        $this->assertSame(['learn', 'practice', 'check'], $plan->phaseOrder());
    }

    public function test_every_shipped_profile_resolves(): void
    {
        $expected = [
            'standard' => ['learn', 'practice', 'check'],
            'diagnostic_free' => ['learn', 'practice', 'check'],
            'no_cfu' => ['learn', 'practice'],
            'check_first' => ['learn', 'check', 'practice'],
        ];

        foreach ($expected as $profileKey => $phases) {
            $plan = app(EsoFlowResolver::class)->profile($profileKey);

            $this->assertSame($phases, $plan->phaseOrder(), "Profile '{$profileKey}' phase order.");
        }

        // The one structural stage change any profile makes.
        $this->assertNotContains(
            'diagnostic_entry',
            app(EsoFlowResolver::class)->profile('diagnostic_free')->conceptStageKeys()
        );
    }

    public function test_an_unknown_profile_is_refused(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Unknown flow profile 'mastery_after_learn'");

        app(EsoFlowResolver::class)->profile('mastery_after_learn');
    }

    // ══════════════════════════════════════════════════════════════════════
    // The fixed set
    // ══════════════════════════════════════════════════════════════════════

    public function test_an_unknown_stage_is_refused_rather_than_appended(): void
    {
        $submitted = $this->shipped();
        $submitted['stages']['teleport_to_mastery'] = ['rank' => 250, 'enabled' => true];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Unknown flow stage 'teleport_to_mastery'");

        $this->validator()->validateStructure($submitted);
    }

    /**
     * A MISSING key is refused too, which is where this diverges from
     * ArchitectureRegistry::sanitiseRecords().
     *
     * That method tolerates partial record lists. A flow cannot: it is a total
     * order, and an omitted LOCKED stage is indistinguishable from an attempt
     * to delete one.
     */
    public function test_a_missing_stage_is_refused_rather_than_defaulted(): void
    {
        $submitted = $this->shipped();
        unset($submitted['stages']['mastery_verdict']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Flow stage 'mastery_verdict' is missing");

        $this->validator()->validateStructure($submitted);
    }

    // ══════════════════════════════════════════════════════════════════════
    // Tiers
    // ══════════════════════════════════════════════════════════════════════

    public function test_a_locked_stage_cannot_be_switched_off(): void
    {
        $submitted = $this->shipped();
        $submitted['stages']['mastery_verdict']['enabled'] = false;

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("'mastery_verdict' cannot be switched off");

        $this->validator()->validateStructure($submitted);
    }

    /**
     * The D3 hoist, defended at the configuration layer.
     *
     * Moving misconception_scan back below the node loop is exactly the bug
     * the hoist fixed (EsoPolicyService.php:1067-1078) — a due retrieval would
     * outrank a confirmed misconception and the engine would test retention
     * while an error stood uncorrected. A profile must not be able to ask for
     * it.
     */
    public function test_a_locked_stage_cannot_be_moved(): void
    {
        $submitted = $this->shipped();
        $submitted['stages']['misconception_scan']['rank'] = 600;

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("'misconception_scan' is fixed at position 400");

        $this->validator()->validateStructure($submitted);
    }

    public function test_a_toggleable_stage_may_be_switched_off(): void
    {
        $submitted = $this->shipped();
        $submitted['stages']['diagnostic_entry']['enabled'] = false;

        $clean = $this->validator()->validateStructure($submitted);

        $this->assertFalse($clean['stages']['diagnostic_entry']['enabled']);
    }

    public function test_a_toggleable_stage_still_cannot_be_moved(): void
    {
        $submitted = $this->shipped();
        $submitted['stages']['diagnostic_entry']['rank'] = 450;

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("'diagnostic_entry' is fixed at position 200");

        $this->validator()->validateStructure($submitted);
    }

    // ══════════════════════════════════════════════════════════════════════
    // Dependencies
    // ══════════════════════════════════════════════════════════════════════

    public function test_stale_mastery_cannot_run_without_retrieval(): void
    {
        $submitted = $this->shipped();
        $submitted['stages']['retrieval_due']['enabled'] = false;
        $submitted['stages']['stale_mastery']['enabled'] = true;

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("'stale_mastery' needs 'retrieval_due' enabled");

        $this->validator()->validateStructure($submitted);
    }

    public function test_disabling_both_retention_stages_together_is_allowed(): void
    {
        $submitted = $this->shipped();
        $submitted['stages']['retrieval_due']['enabled'] = false;
        $submitted['stages']['stale_mastery']['enabled'] = false;

        $clean = $this->validator()->validateStructure($submitted);

        $this->assertFalse($clean['stages']['retrieval_due']['enabled']);
        $this->assertFalse($clean['stages']['stale_mastery']['enabled']);
    }

    // ══════════════════════════════════════════════════════════════════════
    // Phases
    // ══════════════════════════════════════════════════════════════════════

    public function test_practice_and_check_may_be_swapped(): void
    {
        $submitted = $this->shipped();
        $submitted['phases']['check']['rank'] = 200;
        $submitted['phases']['practice']['rank'] = 300;

        $clean = $this->validator()->validateStructure($submitted);

        $this->assertSame(200, $clean['phases']['check']['rank']);
        $this->assertSame(300, $clean['phases']['practice']['rank']);
    }

    /**
     * A rearrangement must be a rearrangement, not arbitrary numbers.
     *
     * Without the multiset check, both phases at position 2 would resolve in
     * whatever order PHP's sort happened to produce.
     */
    public function test_phase_positions_must_be_a_rearrangement(): void
    {
        $submitted = $this->shipped();
        $submitted['phases']['check']['rank'] = 200;
        $submitted['phases']['practice']['rank'] = 200;

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('must be a rearrangement of the shipped positions');

        $this->validator()->validateStructure($submitted);
    }

    public function test_learn_cannot_be_moved_out_of_first_place(): void
    {
        $submitted = $this->shipped();
        $submitted['phases']['learn']['rank'] = 250;

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("'learn' must stay first");

        $this->validator()->validateStructure($submitted);
    }

    /**
     * The refusal that protects the mastery rule from the side.
     *
     * Practice is the only thing that records mastery evidence. A flow without
     * it leaves the evidence floor permanently unreachable, so every learner
     * would be stranded short of mastery forever — the same outcome as setting
     * the floor to infinity, reached by a different route.
     */
    public function test_practice_cannot_be_switched_off(): void
    {
        $submitted = $this->shipped();
        $submitted['phases']['practice']['enabled'] = false;

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Practice is the only thing that records mastery evidence');

        $this->validator()->validateStructure($submitted);
    }

    public function test_the_check_may_be_switched_off(): void
    {
        $submitted = $this->shipped();
        $submitted['phases']['check']['enabled'] = false;

        $clean = $this->validator()->validateStructure($submitted);

        $this->assertFalse($clean['phases']['check']['enabled']);
    }

    // ══════════════════════════════════════════════════════════════════════
    // Parameters
    // ══════════════════════════════════════════════════════════════════════

    /**
     * THIS is what keeps the mastery rule out of reach.
     *
     * config/pal_flow.php declares no field descriptor for any threshold or
     * evidence floor, so a profile row that tried to set one has nothing to be
     * validated against and is refused outright. The rule is protected by the
     * absence of a descriptor rather than by a special case — there is no
     * branch here to forget.
     */
    public function test_a_parameter_with_no_descriptor_is_refused(): void
    {
        $submitted = $this->shipped();
        $submitted['stages']['mastery_verdict']['params']['knowledge_threshold'] = 0.5;

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("'mastery_verdict' has no setting called 'knowledge_threshold'");

        $this->validator()->validateStructure($submitted);
    }

    public function test_a_parameter_is_held_to_its_declared_bounds(): void
    {
        $submitted = $this->shipped();
        $submitted['phases']['check']['params']['item_count'] = 99;

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('cannot be above 5');

        $this->validator()->validateStructure($submitted);
    }

    public function test_a_valid_parameter_is_coerced_and_kept(): void
    {
        $submitted = $this->shipped();
        $submitted['phases']['check']['params']['item_count'] = '3';

        $clean = $this->validator()->validateStructure($submitted);

        // Coerced to an integer, not left as the submitted string: these
        // values are JSON round-tripped and then used as counts.
        $this->assertSame(3, $clean['phases']['check']['params']['item_count']);
        // Untouched siblings keep their shipped defaults.
        $this->assertSame(2, $clean['phases']['check']['params']['max_cycles']);
    }

    public function test_a_null_on_a_nullable_parameter_means_inherit(): void
    {
        $submitted = $this->shipped();
        $submitted['phases']['practice']['params']['min_items_override'] = null;

        $clean = $this->validator()->validateStructure($submitted);

        $this->assertNull($clean['phases']['practice']['params']['min_items_override']);
    }

    // ══════════════════════════════════════════════════════════════════════
    // mustFollow, in isolation
    // ══════════════════════════════════════════════════════════════════════

    /**
     * The partial-order rule, tested against a stubbed catalogue.
     *
     * Worth being straight about why: with the catalogue as shipped, NO stage
     * is ORDERABLE — reordering happens between the three phases — so the
     * fixed-rank check refuses a bad order before mustFollow() is ever
     * consulted. The rule is currently defence in depth rather than the active
     * guard.
     *
     * It is kept, and tested here, because it becomes the ONLY guard the
     * moment any stage is made orderable, and a rule that has never been
     * executed is a rule nobody should rely on. Stubbing the catalogue is what
     * lets it be exercised honestly rather than asserted by inspection.
     */
    public function test_must_follow_rejects_a_valid_permutation_that_is_still_nonsense(): void
    {
        config(['pal_flow.stages' => [
            'first' => ['scope' => 'concept', 'rank' => 100, 'label' => 'First', 'enabled' => true, 'params' => [], 'fields' => []],
            'second' => ['scope' => 'concept', 'rank' => 200, 'label' => 'Second', 'enabled' => true, 'params' => [], 'fields' => []],
        ]]);

        $registry = new EsoFlowStageRegistry([
            'first' => StubOrderableFirstStage::class,
            'second' => StubOrderableSecondStage::class,
        ]);

        // A clean swap: both ranks come from the shipped set, so a permutation
        // check alone passes this. Only the partial order catches that
        // 'second' has been lifted above the stage it depends on.
        $submitted = [
            'stages' => [
                'first' => ['rank' => 200, 'enabled' => true, 'params' => []],
                'second' => ['rank' => 100, 'enabled' => true, 'params' => []],
            ],
            'phases' => $this->shipped()['phases'],
        ];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("'second' must resolve after 'first'");

        (new EsoFlowValidator($registry))->validateStructure($submitted);
    }

    // ── helpers ──────────────────────────────────────────────────────────

    private function validator(): EsoFlowValidator
    {
        return app(EsoFlowValidator::class);
    }

    /**
     * The shipped catalogue, in the shape a client would submit it.
     *
     * @return array{stages:array<string,array<string,mixed>>, phases:array<string,array<string,mixed>>}
     */
    private function shipped(): array
    {
        $reduce = static function (array $rows): array {
            $out = [];
            foreach ($rows as $key => $row) {
                $out[$key] = [
                    'rank' => (int) ($row['rank'] ?? 0),
                    'enabled' => (bool) ($row['enabled'] ?? true),
                    'params' => (array) ($row['params'] ?? []),
                ];
            }

            return $out;
        };

        return [
            'stages' => $reduce((array) config('pal_flow.stages', [])),
            'phases' => $reduce((array) config('pal_flow.phases', [])),
        ];
    }
}

/** Stub: orderable, no constraints — the predecessor. */
class StubOrderableFirstStage implements EsoFlowStage
{
    public function key(): string
    {
        return 'first';
    }

    public function tier(): string
    {
        return self::TIER_ORDERABLE;
    }

    public function mustFollow(): array
    {
        return [];
    }

    public function requires(): array
    {
        return [];
    }

    public function isConditional(): bool
    {
        return false;
    }

    public function resolve(EsoFlowContext $ctx, array $params): ?array
    {
        return null;
    }
}

/** Stub: orderable, and declares that it must resolve AFTER 'first'. */
class StubOrderableSecondStage implements EsoFlowStage
{
    public function key(): string
    {
        return 'second';
    }

    public function tier(): string
    {
        return self::TIER_ORDERABLE;
    }

    public function mustFollow(): array
    {
        return ['first'];
    }

    public function requires(): array
    {
        return [];
    }

    public function isConditional(): bool
    {
        return false;
    }

    public function resolve(EsoFlowContext $ctx, array $params): ?array
    {
        return null;
    }
}
