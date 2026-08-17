<?php

namespace App\Services\PAL\Gamification;

/**
 * New PAL → Gamification: the §9 visibility matrix, enforced server-side.
 *
 * §9 is called "the most important governance document in the gamification
 * system" for a reason: the whole design rests on a struggling student never
 * being shown where they stand against anyone else. A rule that lives in the
 * UI is a rule that is one careless render away from being broken, so the
 * matrix is applied HERE — the API simply does not emit a field the audience
 * may not see.
 *
 * Audience is derived from the authenticated JWT. A staff member may preview a
 * narrower audience (to see exactly what a parent's digest contains), but no
 * caller can ever widen their own: a student is always a student.
 */
class GamificationVisibility
{
    public const STUDENT = 'student';
    public const TEACHER = 'teacher';
    public const PARENT = 'parent';
    public const ADMIN = 'admin';

    /**
     * Resolve the audience for a request.
     *
     * @param array  $auth       the `pal_auth` attribute set by PalApiAuth
     * @param string $requested  optional narrowing (staff previewing a view)
     */
    public function audience(array $auth, string $requested = ''): string
    {
        $role = (string) ($auth['role'] ?? '');

        // Students are always students — nothing they can send changes that.
        if ($role === self::STUDENT || ! empty($auth['is_student'])) {
            return self::STUDENT;
        }

        $base = $role === 'admin' ? self::ADMIN : self::TEACHER;

        $requested = strtolower(trim($requested));
        // Staff may only preview a NARROWER audience, never a wider one.
        if (in_array($requested, [self::STUDENT, self::PARENT, self::TEACHER], true)) {
            if ($base === self::ADMIN || $requested !== self::TEACHER) {
                return $requested;
            }
        }

        return $base;
    }

    /** The grain this audience gets for a matrix row: full / summary / aggregate / none / … */
    public function grain(string $dataKey, string $audience): string
    {
        $row = (array) config("pal_gamification.visibility.{$dataKey}", []);

        return (string) ($row[$audience] ?? 'none');
    }

    public function allows(string $dataKey, string $audience): bool
    {
        return $this->grain($dataKey, $audience) !== 'none';
    }

    /**
     * The one question every screen has to answer: may this audience see any
     * form of comparison between this learner and their classmates?
     *
     * Only Challenge Mode (opt-in) and teacher/admin views ever may.
     */
    public function allowsPeerComparison(string $audience): bool
    {
        return in_array($audience, [self::TEACHER, self::ADMIN], true);
    }

    /**
     * Strip a learner payload down to what this audience may hold.
     *
     * The keys map to the §9.1 matrix rows. Anything not permitted is REMOVED
     * from the array rather than nulled, so a client cannot accidentally render
     * an empty field where a forbidden one used to be and imply it exists.
     *
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    public function filterLearnerPayload(array $payload, string $audience): array
    {
        $map = [
            'mastery' => 'own_mastery',
            'badges' => 'own_badges',
            'streak' => 'own_streak',
            'personal_bests' => 'own_personal_bests',
            'career_quest' => 'own_career_quest',
            'class_aggregate' => 'class_aggregate_mastery',
            'team_challenges' => 'team_challenge_progress',
            'challenge_mode' => 'challenge_mode_scores',
        ];

        foreach ($map as $field => $dataKey) {
            if (! array_key_exists($field, $payload)) {
                continue;
            }

            $grain = $this->grain($dataKey, $audience);
            if ($grain === 'none') {
                unset($payload[$field]);
                continue;
            }

            $payload[$field] = $this->applyGrain($field, $payload[$field], $grain);
        }

        $payload['audience'] = $audience;
        $payload['visibility'] = $this->describe($audience);

        return $payload;
    }

    /**
     * Reduce one section to its permitted grain.
     *
     * `summary` and `milestones` are the parent-facing grains from §9.2: the
     * parent digest carries progress and milestones, never percentages, wrong
     * answers or any comparison.
     */
    private function applyGrain(string $field, $value, string $grain)
    {
        if (! is_array($value)) {
            return $value;
        }

        if ($grain === 'full' || $grain === 'per_student' || $grain === 'aggregate' || $grain === 'opt_in') {
            return $value;
        }

        if ($grain === 'count') {
            return ['count' => is_array($value) && array_key_exists('total', $value)
                ? $value['total']
                : (is_array($value) ? count($value) : 0)];
        }

        if ($grain === 'current' && $field === 'streak') {
            // §9.2 — a parent sees the habit, not the pressure mechanics.
            return array_intersect_key($value, array_flip([
                'current_streak', 'longest_streak', 'last_active_date', 'active_today', 'headline',
            ]));
        }

        if ($grain === 'milestones' && $field === 'badges') {
            $earned = is_array($value['earned'] ?? null) ? $value['earned'] : [];

            return [
                'total_earned' => $value['total_earned'] ?? count($earned),
                'earned' => array_map(fn ($badge) => array_intersect_key($badge, array_flip([
                    'badge_id', 'name', 'category', 'description', 'awarded_at', 'hpc_domain', 'casel_domain', 'ncdg_goal',
                ])), array_values($earned)),
            ];
        }

        if ($grain === 'summary') {
            if ($field === 'mastery') {
                // No per-concept scores and no percentages for a parent (§9.2).
                return array_intersect_key($value, array_flip([
                    'tier_counts', 'concepts_tracked', 'headline', 'recent_milestones',
                ]));
            }
            if ($field === 'personal_bests') {
                return array_intersect_key($value, array_flip(['recent', 'headline']));
            }
        }

        return $value;
    }

    /** The matrix as this audience experiences it — surfaced so the UI can explain itself. */
    public function describe(string $audience): array
    {
        $matrix = (array) config('pal_gamification.visibility', []);
        $out = [];
        foreach ($matrix as $dataKey => $row) {
            $out[$dataKey] = (string) ($row[$audience] ?? 'none');
        }

        return $out;
    }
}
