<?php

namespace Tests\Unit\Eso;

use App\Services\PAL\Flow\EsoFlowRegistry;
use App\Services\PAL\Flow\EsoFlowResolver;
use App\Services\PAL\Flow\EsoFlowValidator;
use Tests\TestCase;

/**
 * A host without the flow tables must still teach.
 *
 * ---------------------------------------------------------------------------
 * WHY THIS IS NOT A HYPOTHETICAL
 * ---------------------------------------------------------------------------
 * This estate carries 408 pending migrations across 996 files. Hosts are
 * genuinely behind, by months in places, and
 * 2026_09_21_100000_create_pal_flow_profile_tables.php will be unrun on some
 * of them for a long time.
 *
 * So "the flow tables are absent" is not an edge case to be defensive about —
 * it is the state most of the estate is in right now, and it has to be the
 * ordinary path rather than a degraded one. An engine that threw, or that
 * resolved an empty flow, would take down learning on exactly the hosts
 * furthest behind on maintenance.
 *
 * The rule these tests pin: with no tables, every institute resolves the
 * shipped `standard` flow from config, with a NULL version id, and the
 * structure is identical to what a host WITH the tables resolves. Nothing a
 * learner is served depends on whether the migration has run.
 *
 * No database access: the registry is replaced with a stub that reports the
 * tables absent, which is exactly what a behind host's registry does.
 */
class EsoFlowRegistryDegradationTest extends TestCase
{
    public function test_an_estate_without_the_flow_tables_still_resolves_the_standard_flow(): void
    {
        $plan = $this->resolverWithoutTables()->resolve(subInstituteId: 341);

        $this->assertSame('standard', $plan->profileKey());

        // NULL is the honest answer, not a placeholder: there is no stored
        // version to pin a learner to, so learner_node_state.flow_version_id
        // stays null and means "predates flow versioning" for ever.
        $this->assertNull($plan->versionId());
    }

    /**
     * The structure is the same with or without the tables.
     *
     * This is the assertion that actually matters. A host behind on migrations
     * must serve the same lesson, in the same order, as one that is current —
     * otherwise running a migration silently changes what students are taught,
     * which is the opposite of what the versioning is for.
     */
    public function test_the_resolved_structure_is_identical_with_and_without_the_tables(): void
    {
        $without = $this->resolverWithoutTables()->resolve(subInstituteId: 341);
        $with = app(EsoFlowResolver::class)->resolve(subInstituteId: 341);

        $this->assertSame($with->conceptStageKeys(), $without->conceptStageKeys());
        $this->assertSame($with->nodeStageKeys(), $without->nodeStageKeys());
        $this->assertSame($with->phaseOrder(), $without->phaseOrder());
        $this->assertSame($with->profileKey(), $without->profileKey());
    }

    /**
     * A pin that cannot be read falls forward, it does not fail.
     *
     * A learner carrying flow_version_id = 7 on a host that has no version
     * table still needs a screen. Resolving the current default is the safe
     * direction — it can only move them onto a flow that exists — and it is
     * what every learner on that host is already getting.
     */
    public function test_a_pinned_learner_on_a_host_without_the_tables_still_resolves(): void
    {
        $plan = $this->resolverWithoutTables()->resolve(subInstituteId: 341, pinnedVersionId: 7);

        $this->assertSame('standard', $plan->profileKey());
        $this->assertNull($plan->versionId());
        $this->assertSame(['learn', 'practice', 'check'], $plan->phaseOrder());
    }

    /**
     * A resolver whose registry reports the tables absent.
     *
     * The stub overrides available() alone, so every other method takes its
     * real early-return path — which is the behaviour a behind host actually
     * has, rather than a mock of what it might do.
     */
    private function resolverWithoutTables(): EsoFlowResolver
    {
        $registry = new class extends EsoFlowRegistry
        {
            public function available(): bool
            {
                return false;
            }
        };

        return new EsoFlowResolver(app(EsoFlowValidator::class), $registry);
    }
}
