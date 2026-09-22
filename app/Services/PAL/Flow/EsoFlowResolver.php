<?php

namespace App\Services\PAL\Flow;

use App\Domain\Eso\Flow\EsoFlowPlan;
use InvalidArgumentException;

/**
 * Works out which flow an institute is running, and hands back a resolved,
 * immutable plan.
 *
 * ---------------------------------------------------------------------------
 * RESOLUTION ORDER
 * ---------------------------------------------------------------------------
 *   1. A learner's PINNED version, when they carry one. Beats everything,
 *      including a reassignment — that is what pinning means.
 *   2. The institute's assigned profile's active version.
 *   3. The default profile's active version.
 *   4. config/pal_flow.php, merged with the profile's shipped delta.
 *
 * Layer 4 is not a fallback of last resort, it is the normal case on most of
 * this estate: 408 of 996 migrations are pending, so hosts without the flow
 * tables are expected, and they resolve the shipped `standard` flow exactly as
 * they did before any of this existed.
 *
 * Per-institute parameter deltas are NOT in scope for v1 — a school picks a
 * profile and gets exactly that — but mergeDelta() already takes an arbitrary
 * delta, so adding a `pal_flow_settings` layer later is one more call here.
 *
 * ---------------------------------------------------------------------------
 * WHY IT VALIDATES ON READ
 * ---------------------------------------------------------------------------
 * A profile is validated when it is written, so re-checking looks redundant.
 * It is not: version rows are JSON in a database that migrations, seeders and
 * direct SQL can all reach, and config/pal_flow.php drifts against the handler
 * classes on every deploy. Validating on read means a malformed flow FAILS
 * LOUDLY instead of silently resolving into a shape nobody intended — the same
 * choice StepHandlerRegistry makes, because a silently skipped stage is a
 * learner who never gets taught.
 *
 * It costs no query. The validator reads config and handler metadata only.
 */
class EsoFlowResolver
{
    /** @var array<string, EsoFlowPlan> resolved plans, per request */
    private array $memo = [];

    public function __construct(
        private readonly EsoFlowValidator $validator,
        private readonly EsoFlowRegistry $registry,
    ) {
    }

    /**
     * The flow this institute is running.
     *
     * @param  int|null  $pinnedVersionId  the version a learner is already
     *         pinned to (learner_node_state.flow_version_id, step 6). NULL
     *         means no pin — the learner is new, or their state predates flow
     *         versioning — and resolves to the current assignment.
     */
    public function resolve(int $subInstituteId, ?int $pinnedVersionId = null): EsoFlowPlan
    {
        $key = $subInstituteId . ':' . ($pinnedVersionId ?? 'none');

        return $this->memo[$key] ??= $this->resolveUncached($subInstituteId, $pinnedVersionId);
    }

    /** Resolve a named profile directly. Used by the admin surface and tests. */
    public function profile(string $profileKey): EsoFlowPlan
    {
        return $this->memo['profile:' . $profileKey] ??= $this->fromProfile($profileKey);
    }

    /**
     * A profile's fully resolved structure, for storing as a version.
     *
     * Deliberately reads config and NOT the database: this is what a version
     * row is seeded FROM, so reading the stored version here would be circular.
     *
     * @return array{stages:array<string,array<string,mixed>>, phases:array<string,array<string,mixed>>}
     */
    public function structureFor(string $profileKey): array
    {
        $profiles = (array) config('pal_flow.profiles', []);

        if (! isset($profiles[$profileKey])) {
            throw new InvalidArgumentException("Unknown flow profile '{$profileKey}'.");
        }

        return $this->validator->validateStructure($this->mergeDelta(
            $this->shippedStructure(),
            (array) ($profiles[$profileKey]['delta'] ?? [])
        ));
    }

    private function resolveUncached(int $subInstituteId, ?int $pinnedVersionId): EsoFlowPlan
    {
        // 1. A pin beats everything. Note it does NOT filter on status: a
        //    learner pinned to a version superseded months ago must still
        //    resolve that version, which is the whole point.
        if ($pinnedVersionId !== null) {
            $pinned = $this->registry->version($pinnedVersionId);

            if ($pinned !== null) {
                return $this->fromStored($pinned['definition'], $this->profileKeyFor($subInstituteId), $pinned['id']);
            }

            // A pin naming a version that no longer exists is not fatal — the
            // learner still needs a screen. Falling through to the current
            // assignment is the safe direction: it can only move them onto a
            // flow that exists.
        }

        // ONE query: the assignment and its active version, or the default's,
        // resolved together. Two separate lookups here cost two round trips on
        // the path of every learner action.
        $flow = $this->registry->flowFor($subInstituteId);

        if ($flow !== null) {
            return $this->fromStored($flow['definition'], $flow['profile_key'], $flow['id']);
        }

        // No flow tables on this host, or nothing published: resolve the
        // shipped default from config exactly as before any of this existed.
        return $this->fromProfile($this->defaultProfileKey());
    }

    /**
     * Which profile this institute is assigned.
     *
     * Null from the registry means either "no assignment row" or "no flow
     * tables on this host", and both resolve the default.
     */
    private function profileKeyFor(int $subInstituteId): string
    {
        return $this->registry->assignedProfileKey($subInstituteId) ?? $this->defaultProfileKey();
    }

    private function defaultProfileKey(): string
    {
        foreach ((array) config('pal_flow.profiles', []) as $key => $profile) {
            if (($profile['is_default'] ?? false) === true) {
                return (string) $key;
            }
        }

        throw new InvalidArgumentException(
            'config/pal_flow.php declares no default profile. Exactly one profile must set '
            . "'is_default' => true, or an unassigned institute has no flow to run."
        );
    }

    /**
     * Build a plan from a STORED version.
     *
     * The stored definition is the whole structure, not a delta, so it is used
     * as-is rather than merged over today's config — that is what lets a
     * pinned learner keep the flow they started under after the catalogue has
     * moved on.
     *
     * @param  array<string,mixed>  $definition
     */
    private function fromStored(array $definition, string $profileKey, int $versionId): EsoFlowPlan
    {
        $clean = $this->validator->validateStructure($definition);

        [$conceptStages, $nodeStages] = $this->splitByScope($clean['stages']);

        return new EsoFlowPlan(
            conceptStages: $conceptStages,
            nodeStages: $nodeStages,
            phases: $clean['phases'],
            profileKey: $profileKey,
            versionId: $versionId,
        );
    }

    /** Build a plan from config alone — no stored version, so no version id. */
    private function fromProfile(string $profileKey): EsoFlowPlan
    {
        $clean = $this->structureFor($profileKey);

        [$conceptStages, $nodeStages] = $this->splitByScope($clean['stages']);

        return new EsoFlowPlan(
            conceptStages: $conceptStages,
            nodeStages: $nodeStages,
            phases: $clean['phases'],
            profileKey: $profileKey,
            versionId: null,
        );
    }

    /**
     * The shipped catalogue reduced to just the mutable fields.
     *
     * Label, handler and scope are deliberately dropped: they are not part of
     * what a profile may express, so they never enter the merge and a delta
     * cannot reach them.
     *
     * @return array{stages:array<string,array<string,mixed>>, phases:array<string,array<string,mixed>>}
     */
    private function shippedStructure(): array
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

    /**
     * Apply a profile delta over the shipped structure.
     *
     * Per key and per field, never wholesale: a delta that sets only `enabled`
     * must leave rank and params alone, or every profile would have to restate
     * the entire catalogue and would go stale the moment a default changed.
     * Params merge one level deeper for the same reason.
     *
     * @param  array{stages:array<string,array<string,mixed>>, phases:array<string,array<string,mixed>>}  $base
     * @param  array<string,mixed>  $delta
     * @return array{stages:array<string,array<string,mixed>>, phases:array<string,array<string,mixed>>}
     */
    private function mergeDelta(array $base, array $delta): array
    {
        foreach (['stages', 'phases'] as $section) {
            foreach ((array) ($delta[$section] ?? []) as $key => $changes) {
                if (! isset($base[$section][$key])) {
                    // Left in place rather than skipped: validateStructure()
                    // reports it as an unknown key, with a message saying the
                    // set is closed.
                    $base[$section][$key] = (array) $changes;
                    continue;
                }

                foreach ((array) $changes as $field => $value) {
                    if ($field === 'params') {
                        $base[$section][$key]['params'] = array_merge(
                            $base[$section][$key]['params'] ?? [],
                            (array) $value
                        );
                        continue;
                    }

                    $base[$section][$key][$field] = $value;
                }
            }
        }

        return $base;
    }

    /**
     * Concept stages and node stages run in separate pipelines at separate
     * granularities, so their ranks are only ever compared within a scope.
     *
     * @param  array<string, array{rank:int, enabled:bool, params:array<string,mixed>}>  $stages
     * @return array{0:array<string,array{rank:int,enabled:bool,params:array<string,mixed>}>, 1:array<string,array{rank:int,enabled:bool,params:array<string,mixed>}>}
     */
    private function splitByScope(array $stages): array
    {
        $catalogue = (array) config('pal_flow.stages', []);
        $concept = [];
        $node = [];

        foreach ($stages as $key => $row) {
            $scope = (string) ($catalogue[$key]['scope'] ?? 'concept');

            if ($scope === 'node') {
                $node[$key] = $row;
                continue;
            }

            $concept[$key] = $row;
        }

        return [$concept, $node];
    }

    /** Drop memoised plans. Needed by tests, and after an assignment changes. */
    public function forget(): void
    {
        $this->memo = [];
    }
}
