<?php

namespace App\Services\PAL\LabSpec;

/**
 * Which experiment the Application Lab offers a student, for the scope they chose, as their concept-level learning grows.
 *
 * It is one pure function over plain data (no database, no chapter named anywhere), so it works for every chapter and every
 * combination of concepts:
 *
 *   inputs   the chapter's approved design labs, its 3D missions, the scope (studied concepts chosen, or the whole chapter,
 *            which the caller has already refused unless every concept is studied), what the student has done before, and the
 *            experiment already begun for this scope
 *   output   one design lab for "Design Your Own Experiment" and one mission for the "Interactive Science Mission", or null
 *            for either, each with a state; plus the reason when there is none
 *
 * Rules
 *   - Eligibility: a lab whose level is "chapter" is offered only for a whole-chapter scope; any other lab is eligible when the
 *     scope touches one of the concepts it assesses (a lab naming none fits any scope). A whole-chapter scope may use any lab,
 *     preferring chapter-level ones.
 *   - Fit: the more of a lab's concepts the scope covers, and the fewer it assesses that the scope does not, the better.
 *   - A begun experiment is never swapped away; a finished one is not offered again while another suitable one exists.
 *   - The two cards show different experiments whenever different ones exist: the pair is chosen together, and two labs with the same
 *     key are one experiment. When there is only one, both cards show it (3D mission and design lab) and the answer says it is shared.
 *   - With nothing new, the fallback is stated (all done / none yet), never a claim that a new experiment is available.
 */
class LabSelector
{
    /**
     * @param  array<int,array<string,mixed>>  $designLabs   [key, title, version, concepts[], scope_level]
     * @param  array<int,array<string,mixed>>  $missionLabs  [key, title, concepts[], scope_level]
     * @param  array<string,mixed>  $scope  [type: concepts|chapter, concepts[]]
     * @param  array<int,array<string,mixed>>  $history  [format: design|mission, key, status: in_progress|submitted|evaluated]
     * @param  array<string,mixed>|null  $scopeDesign  [key, status] of the design record for this very scope
     * @return array<string,mixed>
     */
    public static function select(array $designLabs, array $missionLabs, array $scope, array $history, ?array $scopeDesign = null): array
    {
        $finished = fn (string $k) => count(array_filter($history, fn ($h) => $h['key'] === $k && in_array($h['status'], ['submitted', 'evaluated'], true))) > 0;
        $isFinished = fn (?array $h) => $h !== null && in_array($h['status'], ['submitted', 'evaluated'], true);
        $mission = [];
        foreach ($history as $h) {
            if ($h['format'] === 'mission') {
                $mission[$h['key']] = $h;
            }
        }

        // ---- the options for each card, each with a score (higher is chosen) ----------------------------------
        $designOpts = [['lab' => null, 'state' => null, 'score' => 0.0]];
        foreach ($designLabs as $lab) {
            $fit = self::fit($lab, $scope);
            if ($scopeDesign !== null && $scopeDesign['key'] === $lab['key']) {
                $designOpts[] = $scopeDesign['status'] === 'in_progress'
                    ? ['lab' => $lab, 'state' => 'in_progress', 'score' => 1000 + $fit]
                    : ['lab' => $lab, 'state' => 'completed', 'score' => 20 + $fit];
            } elseif (self::eligible($lab, $scope) && ! $finished($lab['key'])) {
                $designOpts[] = ['lab' => $lab, 'state' => 'new', 'score' => 100 + $fit];
            }
        }
        $missionOpts = [['lab' => null, 'state' => null, 'score' => 0.0]];
        foreach ($missionLabs as $lab) {
            $fit = self::fit($lab, $scope);
            $h = $mission[$lab['key']] ?? null;
            if ($h !== null && $h['status'] === 'in_progress') {
                $missionOpts[] = ['lab' => $lab, 'state' => 'in_progress', 'score' => 1000 + $fit];
            } elseif ($isFinished($h)) {
                if (self::eligible($lab, $scope)) {
                    $missionOpts[] = ['lab' => $lab, 'state' => 'completed', 'score' => 20 + $fit];
                }
            } elseif (self::eligible($lab, $scope) && ! $finished($lab['key'])) {
                $missionOpts[] = ['lab' => $lab, 'state' => 'new', 'score' => 100 + $fit];
            }
        }

        // ---- choose the pair together: never the same experiment twice ----------------------------------------
        $best = null;
        foreach ($designOpts as $d) {
            foreach ($missionOpts as $m) {
                if ($d['lab'] !== null && $m['lab'] !== null && $d['lab']['key'] === $m['lab']['key']) {
                    continue;
                }
                $rank = [
                    round($d['score'] + $m['score'], 6),
                    $d['lab'] !== null ? 1 : 0,
                    $m['lab'] !== null ? 1 : 0,
                ];
                $tie = ($d['lab']['key'] ?? '').'|'.($m['lab']['key'] ?? '');
                if ($best === null || $rank > $best['rank'] || ($rank === $best['rank'] && strcmp($tie, $best['tie']) < 0)) {
                    $best = ['rank' => $rank, 'tie' => $tie, 'd' => $d, 'm' => $m];
                }
            }
        }
        $d = $best['d'];
        $m = $best['m'];

        // A card left empty only because the one experiment it could show is on the other card: both formats show it. The two cards
        // are made different whenever a different experiment exists (that is what the pair above chose); when none does, a student still
        // gets the 3D mission AND the design lab of the same experiment, as before, and is told it is the same one.
        $shared = false;
        if ($d['lab'] === null && count($designOpts) > 1) {
            $d = array_reduce(array_slice($designOpts, 1), fn ($c, $o) => $c === null || $o['score'] > $c['score'] ? $o : $c);
            $shared = true;
        } elseif ($m['lab'] === null && count($missionOpts) > 1) {
            $m = array_reduce(array_slice($missionOpts, 1), fn ($c, $o) => $c === null || $o['score'] > $c['score'] ? $o : $c);
            $shared = true;
        }

        // ---- is there another new experiment after these? ------------------------------------------------------
        $taken = array_filter([$d['lab']['key'] ?? null, $m['lab']['key'] ?? null]);
        $moreDesign = count(array_filter($designLabs, fn ($l) => self::eligible($l, $scope) && ! $finished($l['key']) && ! in_array($l['key'], $taken, true)));
        $moreMission = count(array_filter($missionLabs, fn ($l) => self::eligible($l, $scope) && ! $finished($l['key']) && ! isset($mission[$l['key']]) && ! in_array($l['key'], $taken, true)));

        return [
            'design' => $d['lab'] === null ? null : ['key' => $d['lab']['key'], 'version' => $d['lab']['version'] ?? null, 'title' => $d['lab']['title'] ?? $d['lab']['key'], 'state' => $d['state']],
            'mission' => $m['lab'] === null ? null : ['key' => $m['lab']['key'], 'title' => $m['lab']['title'] ?? $m['lab']['key'], 'state' => $m['state']],
            'shared' => $shared,
            'more_design' => $moreDesign,
            'more_mission' => $moreMission,
            'design_note' => $d['lab'] === null ? self::note($designLabs, $scope, $finished, $m['lab']['key'] ?? null) : null,
            'mission_note' => $m['lab'] === null ? self::note($missionLabs, $scope, $finished, $d['lab']['key'] ?? null) : null,
        ];
    }

    /**
     * Is a lab offered for a scope? ("chapter"-level labs only for a whole-chapter scope; others when the scope touches their concepts.)
     *
     * @param  array<string,mixed>  $lab
     * @param  array<string,mixed>  $scope
     */
    public static function eligible(array $lab, array $scope): bool
    {
        if (($scope['type'] ?? '') === 'chapter') {
            return true;
        }
        if (($lab['scope_level'] ?? 'concepts') === 'chapter') {
            return false;
        }
        $concepts = array_map('mb_strtolower', (array) ($lab['concepts'] ?? []));
        if ($concepts === []) {
            return true;
        }

        return array_intersect($concepts, array_map('mb_strtolower', (array) ($scope['concepts'] ?? []))) !== [];
    }

    /**
     * How well a lab fits a scope: more of its concepts covered is better, assessing concepts the scope does not include is worse,
     * and for a whole-chapter scope a chapter-level lab is best. Always between about -5 and 40.
     *
     * @param  array<string,mixed>  $lab
     * @param  array<string,mixed>  $scope
     */
    public static function fit(array $lab, array $scope): float
    {
        $level = ($lab['scope_level'] ?? 'concepts') === 'chapter';
        if (($scope['type'] ?? '') === 'chapter') {
            return $level ? 30.0 : 10.0;
        }
        $concepts = array_map('mb_strtolower', (array) ($lab['concepts'] ?? []));
        if ($concepts === []) {
            return 5.0;
        }
        $covered = count(array_intersect($concepts, array_map('mb_strtolower', (array) ($scope['concepts'] ?? []))));

        return round(10 * $covered / count($concepts) - 0.5 * (count($concepts) - $covered), 3);
    }

    /**
     * Why a card has no experiment, in words a student can read. Never says a new experiment is available.
     *
     * @param  array<int,array<string,mixed>>  $labs
     * @param  array<string,mixed>  $scope
     */
    private static function note(array $labs, array $scope, callable $finished, ?string $otherCardKey): ?string
    {
        $eligible = array_filter($labs, fn ($l) => self::eligible($l, $scope));
        if ($eligible === []) {
            return 'No approved experiment matches these concepts yet.';
        }
        $new = array_filter($eligible, fn ($l) => ! $finished($l['key']));
        if ($new === []) {
            return 'You have completed every approved experiment for these concepts. There is no new one yet.';
        }

        return $otherCardKey !== null ? 'The other experiment for these concepts is open on the other card.' : null;
    }
}
