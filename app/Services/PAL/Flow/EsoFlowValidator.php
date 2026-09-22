<?php

namespace App\Services\PAL\Flow;

use App\Domain\Eso\Flow\EsoFlowStageMeta;
use App\Domain\Eso\Flow\EsoFlowStageRegistry;
use App\Services\PAL\Support\SettingCoercer;
use InvalidArgumentException;

/**
 * Every write to a flow profile passes through here.
 *
 * Mirrors ArchitectureRegistry::sanitiseRecords() and keeps its two
 * invariants, which are what make a configurable engine safe to operate:
 *
 *   1. A STORED OVERRIDE ONLY EVER HOLDS EDITABLE FIELDS. A stage's tier,
 *      label and handler class are re-read from code and config on every
 *      request and can never be written by a client. So a deploy that revises
 *      the catalogue reaches every institute immediately, and a caller cannot
 *      rewrite the engine by POSTing a fabricated structure.
 *
 *   2. THE STAGE SET IS FIXED. Ten stages and three phases. A write may
 *      retune or reorder them, never add or delete one.
 *
 * It differs from sanitiseRecords() in one way, deliberately: a MISSING key is
 * rejected as well as an unknown one. sanitiseRecords() tolerates partial
 * record lists, but a flow is a total order — a partial one has no meaning,
 * and a missing LOCKED stage is indistinguishable from an attempt to delete
 * it.
 *
 * Every rejection names the offending key and says WHY, because these messages
 * are surfaced verbatim to an administrator (the contract at
 * ArchitectureRegistry.php:102-105) and the "why" is the part that stops them
 * filing a bug against a rule that is protecting them.
 */
class EsoFlowValidator
{
    /**
     * Phase rules, held in CODE for the same reason stage tiers live on their
     * handlers: a tier in config can be typo'd into something dangerous.
     *
     * Phases have no handler class to carry tier(), so they are declared here
     * instead of in config/pal_flow.php.
     *
     * `learn` is pinned first for a mechanical reason. practiceComplete()
     * returns false while taught_at is null and checkSettled() turns on
     * cfu_attempts, which only recordCheckUnderstanding() increments — so a
     * flow that practises or checks before teaching deadlocks on its first
     * resolve.
     *
     * `practice` may be REORDERED but never DISABLED. Practice is the only
     * thing that records mastery evidence: disabling it would leave the
     * evidence floor permanently unreachable and no learner could ever master
     * a concept. That is not a flow a school can opt into, however it is
     * phrased.
     *
     * @var array<string, array{fixed_rank:bool, may_disable:bool, must_follow:array<int,string>}>
     */
    private const PHASE_RULES = [
        'learn' => ['fixed_rank' => true, 'may_disable' => false, 'must_follow' => []],
        'practice' => ['fixed_rank' => false, 'may_disable' => false, 'must_follow' => ['learn']],
        'check' => ['fixed_rank' => false, 'may_disable' => true, 'must_follow' => ['learn']],
    ];

    public function __construct(private readonly EsoFlowStageRegistry $registry)
    {
    }

    /**
     * Validate and normalise a whole submitted flow structure.
     *
     * @param  array{stages?:array<string,mixed>, phases?:array<string,mixed>}  $submitted
     * @return array{stages:array<string,array{rank:int,enabled:bool,params:array<string,mixed>}>, phases:array<string,array{rank:int,enabled:bool,params:array<string,mixed>}>}
     *
     * @throws InvalidArgumentException naming the exact offending key
     */
    public function validateStructure(array $submitted): array
    {
        $stages = $this->validateStages((array) ($submitted['stages'] ?? []));
        $phases = $this->validatePhases((array) ($submitted['phases'] ?? []));

        return ['stages' => $stages, 'phases' => $phases];
    }

    // ══════════════════════════════════════════════════════════════════════
    // Stages
    // ══════════════════════════════════════════════════════════════════════

    /**
     * @param  array<string,mixed>  $submitted
     * @return array<string, array{rank:int, enabled:bool, params:array<string,mixed>}>
     */
    private function validateStages(array $submitted): array
    {
        $catalogue = $this->catalogue();
        $handlers = $this->registry->all();

        $this->assertExactKeySet(array_keys($catalogue), array_keys($submitted), 'flow stage');

        $clean = [];

        foreach ($catalogue as $key => $shipped) {
            $row = (array) $submitted[$key];
            $handler = $handlers[$key] ?? null;

            if ($handler === null) {
                throw new InvalidArgumentException("No handler is registered for flow stage '{$key}'.");
            }

            $enabled = $this->resolveEnabled($row, $shipped, $handler->tier(), $key, (string) ($shipped['label'] ?? $key));
            $rank = $this->resolveStageRank($row, $shipped, $handler->tier(), $key, (string) ($shipped['label'] ?? $key));

            $clean[$key] = [
                'rank' => $rank,
                'enabled' => $enabled,
                'params' => $this->coerceParams($shipped, (array) ($row['params'] ?? []), $key),
            ];
        }

        $this->assertMustFollow($clean, array_map(
            static fn (EsoFlowStageMeta $h): array => $h->mustFollow(),
            $handlers
        ), $catalogue);

        $this->assertRequires($clean, array_map(
            static fn (EsoFlowStageMeta $h): array => $h->requires(),
            $handlers
        ));

        return $clean;
    }

    /**
     * @param  array<string,mixed>  $row
     * @param  array<string,mixed>  $shipped
     */
    private function resolveEnabled(array $row, array $shipped, string $tier, string $key, string $label): bool
    {
        $enabled = array_key_exists('enabled', $row)
            ? SettingCoercer::coerce(['type' => 'toggle'], $row['enabled'], null, "{$label} enabled")
            : (bool) ($shipped['enabled'] ?? true);

        if ($tier === EsoFlowStageMeta::TIER_LOCKED && $enabled !== true) {
            throw new InvalidArgumentException(
                "'{$key}' cannot be switched off. Its position in the resolve order encodes a "
                . 'correctness rule rather than a preference, so every institute runs it.'
            );
        }

        return $enabled;
    }

    /**
     * @param  array<string,mixed>  $row
     * @param  array<string,mixed>  $shipped
     */
    private function resolveStageRank(array $row, array $shipped, string $tier, string $key, string $label): int
    {
        $shippedRank = (int) ($shipped['rank'] ?? 0);

        if (! array_key_exists('rank', $row)) {
            return $shippedRank;
        }

        $rank = (int) SettingCoercer::coerce(['type' => 'number', 'step' => 1], $row['rank'], null, "{$label} position");

        // No stage is ORDERABLE today — reordering happens between the three
        // phases, not between cascade stages. The check is written against the
        // tier rather than against that fact, so introducing an orderable
        // stage later needs no change here.
        if ($tier !== EsoFlowStageMeta::TIER_ORDERABLE && $rank !== $shippedRank) {
            throw new InvalidArgumentException(
                "'{$key}' is fixed at position {$shippedRank} and cannot be moved."
            );
        }

        return $rank;
    }

    // ══════════════════════════════════════════════════════════════════════
    // Phases
    // ══════════════════════════════════════════════════════════════════════

    /**
     * @param  array<string,mixed>  $submitted
     * @return array<string, array{rank:int, enabled:bool, params:array<string,mixed>}>
     */
    private function validatePhases(array $submitted): array
    {
        $shippedPhases = (array) config('pal_flow.phases', []);

        $this->assertExactKeySet(array_keys($shippedPhases), array_keys($submitted), 'learning phase');

        $clean = [];

        foreach ($shippedPhases as $key => $shipped) {
            $row = (array) $submitted[$key];
            $rules = self::PHASE_RULES[$key] ?? ['fixed_rank' => true, 'may_disable' => false, 'must_follow' => []];
            $label = (string) ($shipped['label'] ?? $key);

            $enabled = array_key_exists('enabled', $row)
                ? SettingCoercer::coerce(['type' => 'toggle'], $row['enabled'], null, "{$label} enabled")
                : (bool) ($shipped['enabled'] ?? true);

            if (! $enabled && ! $rules['may_disable']) {
                throw new InvalidArgumentException($this->phaseDisableMessage($key));
            }

            $rank = array_key_exists('rank', $row)
                ? (int) SettingCoercer::coerce(['type' => 'number', 'step' => 1], $row['rank'], null, "{$label} position")
                : (int) ($shipped['rank'] ?? 0);

            if ($rules['fixed_rank'] && $rank !== (int) ($shipped['rank'] ?? 0)) {
                throw new InvalidArgumentException(
                    "'{$key}' must stay first. Practice cannot complete and the check cannot settle "
                    . 'before a node has been taught, so a flow that moves it deadlocks on the first resolve.'
                );
            }

            $clean[$key] = [
                'rank' => $rank,
                'enabled' => $enabled,
                'params' => $this->coerceParams($shipped, (array) ($row['params'] ?? []), $key),
            ];
        }

        $this->assertPhasePermutation($clean, $shippedPhases);

        $this->assertMustFollow(
            $clean,
            array_map(static fn (array $r): array => $r['must_follow'], self::PHASE_RULES),
            $shippedPhases
        );

        return $clean;
    }

    private function phaseDisableMessage(string $key): string
    {
        if ($key === 'practice') {
            return "'practice' cannot be switched off. Practice is the only thing that records "
                . 'mastery evidence, so a flow without it leaves the evidence floor permanently '
                . 'unreachable and no learner could ever master a concept.';
        }

        return "'{$key}' cannot be switched off.";
    }

    /**
     * The reorderable phases must be a REARRANGEMENT of the shipped positions.
     *
     * Comparing the sorted rank multisets catches duplicates, gaps and
     * out-of-set values in one comparison — a submission that puts both
     * practice and check at position 2 is not a permutation, and without this
     * the order between them would come down to PHP's sort stability.
     *
     * @param  array<string, array{rank:int, enabled:bool, params:array<string,mixed>}>  $clean
     * @param  array<string, array<string,mixed>>  $shippedPhases
     */
    private function assertPhasePermutation(array $clean, array $shippedPhases): void
    {
        $movable = array_keys(array_filter(
            self::PHASE_RULES,
            static fn (array $r): bool => $r['fixed_rank'] === false
        ));

        $submittedRanks = [];
        $shippedRanks = [];

        foreach ($movable as $key) {
            if (! isset($clean[$key], $shippedPhases[$key])) {
                continue;
            }
            $submittedRanks[] = $clean[$key]['rank'];
            $shippedRanks[] = (int) ($shippedPhases[$key]['rank'] ?? 0);
        }

        sort($submittedRanks);
        sort($shippedRanks);

        if ($submittedRanks !== $shippedRanks) {
            throw new InvalidArgumentException(
                'The learning phases must be a rearrangement of the shipped positions ('
                . implode(', ', $shippedRanks) . '), not arbitrary numbers. Received: '
                . implode(', ', $submittedRanks) . '.'
            );
        }
    }

    // ══════════════════════════════════════════════════════════════════════
    // Cross-cutting rules
    // ══════════════════════════════════════════════════════════════════════

    /**
     * Both directions: nothing unknown, nothing missing.
     *
     * @param  array<int,string>  $expected
     * @param  array<int,string>  $received
     */
    private function assertExactKeySet(array $expected, array $received, string $noun): void
    {
        $unknown = array_diff($received, $expected);
        if ($unknown !== []) {
            throw new InvalidArgumentException(
                "Unknown {$noun} '" . implode("', '", $unknown) . "'. The set is fixed; a profile may "
                . 'disable or reorder what exists but cannot introduce anything new.'
            );
        }

        $missing = array_diff($expected, $received);
        if ($missing !== []) {
            throw new InvalidArgumentException(
                ucfirst($noun) . " '" . implode("', '", $missing) . "' is missing. A flow is a total "
                . 'order, so every entry must be present — disable one rather than omitting it.'
            );
        }
    }

    /**
     * The partial order a pure permutation check cannot express.
     *
     * A rearrangement can be a valid rearrangement and still be nonsense:
     * mastery_verdict ahead of node_loop grades a concept before a single node
     * has been served. Ranks alone say nothing about that.
     *
     * @param  array<string, array{rank:int, enabled:bool, params:array<string,mixed>}>  $clean
     * @param  array<string, array<int,string>>  $mustFollow
     * @param  array<string, array<string,mixed>>  $catalogue
     */
    private function assertMustFollow(array $clean, array $mustFollow, array $catalogue): void
    {
        foreach ($mustFollow as $key => $predecessors) {
            if (! isset($clean[$key])) {
                continue;
            }

            foreach ($predecessors as $predecessor) {
                if (! isset($clean[$predecessor])) {
                    continue;
                }

                if ($clean[$predecessor]['rank'] >= $clean[$key]['rank']) {
                    $label = (string) ($catalogue[$key]['label'] ?? $key);
                    throw new InvalidArgumentException(
                        "'{$key}' must resolve after '{$predecessor}'. {$label} depends on what "
                        . "'{$predecessor}' decides, so running it first would act on state that "
                        . 'has not been worked out yet.'
                    );
                }
            }
        }
    }

    /**
     * A stage that borrows another stage's output cannot outlive it.
     *
     * @param  array<string, array{rank:int, enabled:bool, params:array<string,mixed>}>  $clean
     * @param  array<string, array<int,string>>  $requires
     */
    private function assertRequires(array $clean, array $requires): void
    {
        foreach ($requires as $key => $dependencies) {
            if (! ($clean[$key]['enabled'] ?? false)) {
                continue;
            }

            foreach ($dependencies as $dependency) {
                if (! ($clean[$dependency]['enabled'] ?? false)) {
                    throw new InvalidArgumentException(
                        "'{$key}' needs '{$dependency}' enabled — it resolves to that stage's action, "
                        . 'so running it alone would serve something this flow says is switched off.'
                    );
                }
            }
        }
    }

    /**
     * Parameters, against the field descriptors the catalogue declares.
     *
     * A parameter with NO descriptor is rejected rather than stored. That is
     * how the mastery rule stays out of reach: config/pal_flow.php declares no
     * field for any threshold or evidence floor, so there is nothing here to
     * accept one against even if a hand-written profile row tried to set it.
     *
     * @param  array<string,mixed>  $shipped
     * @param  array<string,mixed>  $submitted
     * @return array<string,mixed>
     */
    private function coerceParams(array $shipped, array $submitted, string $key): array
    {
        $defaults = (array) ($shipped['params'] ?? []);
        $descriptors = [];

        foreach ((array) ($shipped['fields'] ?? []) as $field) {
            $descriptors[(string) ($field['key'] ?? '')] = $field;
        }

        $unknown = array_diff(array_keys($submitted), array_keys($descriptors));
        if ($unknown !== []) {
            throw new InvalidArgumentException(
                "'{$key}' has no setting called '" . implode("', '", $unknown) . "'. "
                . 'Only the settings this stage declares can be changed.'
            );
        }

        $clean = $defaults;

        foreach ($submitted as $name => $value) {
            $descriptor = $descriptors[$name];
            $label = (string) ($descriptor['label'] ?? $name);
            $fallback = $defaults[$name] ?? null;

            // A null on a nullable default means "inherit", which is a real
            // instruction rather than an absent one — see practice's
            // min_items_override.
            if ($value === null && array_key_exists($name, $defaults) && $defaults[$name] === null) {
                $clean[$name] = null;
                continue;
            }

            $clean[$name] = SettingCoercer::coerce($descriptor, $value, $fallback, $label);
        }

        return $clean;
    }

    /** @return array<string, array<string,mixed>> */
    private function catalogue(): array
    {
        return (array) config('pal_flow.stages', []);
    }
}
