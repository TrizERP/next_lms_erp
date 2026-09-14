<?php

namespace App\Services\PAL\Pedagogy;

use App\Services\PAL\Framework\FrameworkCatalogService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Reads the authored Pedagogy Engine rules so the live selection path can run
 * on them instead of on a parallel hardcoded copy.
 *
 * Background: `pal_pedagogy_engine_rules` holds 52 authored rows across
 * tier-1..tier-5, and until now nothing outside the read-only dashboard API
 * ever queried them — PedagogySelectorEngine carried its own hardcoded
 * five-tier logic with different thresholds, a different band count and a
 * different mastery metric. Two engines, one of them authored and reviewable,
 * the other the one students actually got. This class is the bridge that makes
 * the authored one load-bearing.
 *
 * Only Tier 1 (mastery bands) is sourced here so far, because Tier 1 is the
 * tier that decides what content type a learner is served and is the one whose
 * thresholds diverged most sharply from the live code. Tiers 2-5 remain in
 * PedagogySelectorEngine for now and are the natural next step.
 *
 * Nothing here parses the human-readable `condition` prose — the numbers come
 * from `rule_meta.mastery_range`, which the seeder writes as structured
 * {min,max}. A rule without that structure is skipped rather than guessed at.
 */
class PedagogyRuleBands
{
    private const TABLE = 'pal_pedagogy_engine_rules';

    /** @var array<int, array<string, mixed>>|null */
    private ?array $cachedBands = null;

    public function __construct(
        private readonly FrameworkCatalogService $catalog
    ) {
    }

    /**
     * Tier 1's mastery bands, ascending, as the authored rules define them.
     *
     * Returns an empty array when the rules are unavailable — the table has
     * not been migrated, or has not been seeded. Callers treat empty as "fall
     * back to the previous behaviour" rather than as "no bands apply", so an
     * unseeded environment keeps working exactly as it did before.
     *
     * @return array<int, array{rule_key:string, min:float, max:float, pedagogy:?string, pedagogy_tags:array<int,string>, content_type:?string, bloom_ceiling:?string, difficulty:?int, scaffolding:?string, label:?string}>
     */
    public function masteryBands(): array
    {
        if ($this->cachedBands !== null) {
            return $this->cachedBands;
        }

        if (! Schema::hasTable(self::TABLE)) {
            return $this->cachedBands = [];
        }

        $rows = DB::table(self::TABLE)
            ->where('section_key', 'tier-1')
            // A deactivated rule is one an author has taken out of service.
            // Serving it anyway would make the dashboard and the student flow
            // disagree again, which is the whole failure this class closes.
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get(['rule_key', 'group_label', 'pedagogy', 'scaffolding', 'rule_meta']);

        $bands = [];

        foreach ($rows as $row) {
            $meta = $this->decodeMeta($row->rule_meta);
            $range = $meta['mastery_range'] ?? null;

            // A Tier 1 rule with no structured range cannot be executed
            // against a number. Skipped deliberately: inferring a boundary
            // from the prose condition would make the engine silently
            // disagree with what an author wrote and reviewed.
            if (! is_array($range) || ! isset($range['min'], $range['max'])) {
                continue;
            }

            $bands[] = [
                'rule_key' => (string) $row->rule_key,
                'min' => (float) $range['min'],
                'max' => (float) $range['max'],
                'pedagogy' => $row->pedagogy,
                'pedagogy_tags' => $this->normalizePedagogyTags($row->pedagogy),
                'content_type' => $meta['content_type'] ?? null,
                'bloom_ceiling' => $meta['bloom_ceiling'] ?? null,
                'difficulty' => isset($meta['difficulty']) ? (int) $meta['difficulty'] : null,
                'scaffolding' => $row->scaffolding,
                'label' => $row->group_label,
            ];
        }

        usort($bands, fn ($a, $b) => $a['min'] <=> $b['min']);

        return $this->cachedBands = $bands;
    }

    /**
     * The Tier 1 band a BKT estimate falls in, or null when the rules are
     * unavailable or the estimate sits outside every authored range.
     *
     * $bkt is the 0.0-1.0 estimate, NOT a 0-100 score. Passing a percentage
     * here would match the top band for every learner, so the range is
     * asserted rather than trusted.
     */
    public function resolveMasteryBand(?float $bkt): ?array
    {
        // No evidence is not a band. ADR-001 §5: absence of evidence means
        // diagnose, and none of the authored bands describe that state.
        if ($bkt === null) {
            return null;
        }

        if ($bkt < 0.0 || $bkt > 1.0) {
            return null;
        }

        foreach ($this->masteryBands() as $band) {
            if ($bkt >= $band['min'] && $bkt <= $band['max']) {
                return $band;
            }
        }

        return null;
    }

    /** True when there is an authored rule set to run on at all. */
    public function available(): bool
    {
        return $this->masteryBands() !== [];
    }

    /**
     * The Tier 4 rule for one learning style, or null when the style is
     * unknown or unauthored.
     *
     * Tier 4's own summary is explicit that it "does not change what is taught
     * - it changes the format it arrives in", so what comes back here is a
     * format preference (an H5P priority order and an avoid list), never a
     * replacement pedagogy. Callers must not use it to override a Tier 1
     * decision.
     *
     * Matched on `rule_key` (style-visual, style-read-write, ...) rather than
     * by reading the `condition` prose, for the same reason the mastery bands
     * come from rule_meta: the executable form must not be a re-derivation of
     * the human-readable one.
     *
     * @return array{rule_key:string, learning_style:string, h5p_priority:array<int,string>, avoid:array<int,string>, pedagogy_tags:array<int,string>}|null
     */
    public function learningStyleRule(?string $style): ?array
    {
        $style = strtolower(trim((string) $style));
        if ($style === '' || ! preg_match('/^[a-z][a-z0-9_]*$/', $style)) {
            return null;
        }

        if (! Schema::hasTable(self::TABLE)) {
            return null;
        }

        $row = DB::table(self::TABLE)
            ->where('section_key', 'tier-4')
            ->where('is_active', true)
            ->where('rule_key', 'style-' . str_replace('_', '-', $style))
            ->first(['rule_key', 'pedagogy', 'rule_meta']);

        if ($row === null) {
            return null;
        }

        $meta = $this->decodeMeta($row->rule_meta);

        $priority = array_values(array_filter(
            (array) ($meta['h5p_priority'] ?? []),
            fn ($h) => is_string($h) && $h !== ''
        ));

        // A style rule with no priority order expresses no format preference,
        // so there is nothing to apply.
        if ($priority === []) {
            return null;
        }

        return [
            'rule_key' => (string) $row->rule_key,
            'learning_style' => $style,
            'h5p_priority' => $priority,
            'avoid' => array_values(array_filter(
                (array) ($meta['avoid'] ?? []),
                fn ($a) => is_string($a) && $a !== ''
            )),
            'pedagogy_tags' => $this->normalizePedagogyTags($row->pedagogy),
        ];
    }

    /**
     * A rule may name more than one pedagogy ("activity_based or flashcard")
     * and may use a label outside the canonical 12. Resolved through the same
     * catalog PedagogyEngineService uses, so both readers of these rows agree
     * on what a pedagogy string means.
     *
     * @return array<int, string>
     */
    private function normalizePedagogyTags(?string $pedagogy): array
    {
        if (! $pedagogy) {
            return [];
        }

        $candidates = preg_split('/\s*(?:,| or | and |\/)\s*/i', $pedagogy) ?: [];

        $tags = [];
        foreach ($candidates as $candidate) {
            $candidate = trim(preg_replace('/\(.*?\)/', '', (string) $candidate));
            // Machine-style tags only; prose like "Unchanged" or "Teacher
            // decides" must not collapse onto a real pedagogy.
            if ($candidate === '' || ! preg_match('/^[a-z][a-z0-9_]*$/', $candidate)) {
                continue;
            }

            // normalizePedagogy() answers "inquiry_based" both for a genuine
            // alias (concept_based is one, declared in pal_v4.aliases) and for
            // a label it simply does not recognise. Only the first is an
            // author's intent being honoured; the second would serve a
            // pedagogy nobody wrote. isKnownPedagogy() exists to tell them
            // apart, so an unrecognised label is dropped and the band falls
            // through rather than quietly becoming the default.
            if (! $this->catalog->isKnownPedagogy($candidate)) {
                continue;
            }

            $tags[] = $this->catalog->normalizePedagogy($candidate);
        }

        return array_values(array_unique($tags));
    }

    /** @return array<string, mixed> */
    private function decodeMeta(mixed $raw): array
    {
        if (is_array($raw)) {
            return $raw;
        }

        if (! is_string($raw) || $raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : [];
    }
}
