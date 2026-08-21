<?php

namespace App\Services\PAL\Gamification;

use App\Models\PAL\Gamification\Badge;
use App\Models\PAL\Gamification\CareerQuestState;
use App\Models\PAL\Gamification\ChallengeModeScore;
use App\Models\PAL\Gamification\GamificationNotification;
use App\Models\PAL\Gamification\LearnerBadge;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * New PAL → Gamification: the badge system (document §3).
 *
 * A badge is a RULE evaluated against real activity, never a stored fact about
 * a learner that somebody typed in. `evaluate()` builds one signal pack from
 * LearnerActivitySource and runs every catalogue rule against it; a rule that
 * passes and has not been awarded yet writes exactly one award row.
 *
 * Three properties of the document are load-bearing here:
 *
 *   §3.1  every badge maps to an HPC domain, a CASEL competency or an NCDG
 *         goal — badges are portfolio evidence, not decoration, so the mapping
 *         travels with every award.
 *   §3.3  badges never expire and are never compared between students. The
 *         catalogue therefore has no scarcity mechanic and this class has no
 *         concept of a peer.
 *   §10.3 a teacher may nullify an award they judge to have been gamed. That
 *         revokes the row; it never deletes the history.
 *
 * A badge whose evidence this estate does not produce yet simply never awards.
 * That is the honest empty state the spec asks for — no badge is ever granted
 * as a default or a welcome gift.
 */
class BadgeService
{
    public function __construct(
        private readonly LearnerActivitySource $activity,
        private readonly StreakService $streaks,
    ) {
    }

    /**
     * Evaluate the whole catalogue for a learner and award what has been earned.
     *
     * @return array<int,array<string,mixed>> badges newly awarded on this pass
     */
    public function evaluate(int $learnerId): array
    {
        $signals = $this->signals($learnerId);
        $held = $this->heldKeys($learnerId);
        $awarded = [];

        foreach ($this->catalogue() as $badge) {
            // Challenge Mode badges are evaluated only for opted-in learners,
            // and never leak into the regular learning path (§6.1).
            if ($badge->challenge_mode_only && ! $signals['challenge_mode']['opted_in']) {
                continue;
            }

            foreach ($this->matches($badge, $signals) as $match) {
                $scopeKey = (string) ($match['scope_key'] ?? '');
                if (isset($held[$badge->badge_id . '|' . $scopeKey])) {
                    continue;
                }

                $award = LearnerBadge::create([
                    'learner_id' => $learnerId,
                    'badge_id' => $badge->badge_id,
                    'scope_key' => $scopeKey,
                    'awarded_at' => now(),
                    'context' => $match['context'] ?? [],
                ]);

                $message = $this->renderMessage($badge, $match);

                GamificationNotification::create([
                    'learner_id' => $learnerId,
                    'type' => 'badge',
                    'level' => 'large',
                    'title' => $badge->name,
                    'body' => $message,
                    'context' => [
                        'badge_id' => $badge->badge_id,
                        'category' => $badge->category,
                        'scope_key' => $scopeKey,
                    ] + (array) ($match['context'] ?? []),
                ]);

                $held[$badge->badge_id . '|' . $scopeKey] = true;
                $awarded[] = $this->present($badge, $award, $message);
            }
        }

        return $awarded;
    }

    /**
     * The learner's badge collection: everything in the catalogue, marked
     * earned or not, with the mapping that makes each one HPC evidence.
     */
    public function collection(int $learnerId): array
    {
        $this->evaluate($learnerId);

        $awards = LearnerBadge::where('learner_id', $learnerId)
            ->orderByDesc('awarded_at')
            ->get()
            ->groupBy('badge_id');

        $categories = (array) config('pal_gamification.badge_categories', []);
        $earned = [];
        $available = [];

        foreach ($this->catalogue() as $badge) {
            $badgeAwards = $awards->get($badge->badge_id, collect())
                ->filter(fn (LearnerBadge $a) => $a->revoked_at === null);

            $entry = [
                'badge_id' => $badge->badge_id,
                'name' => $badge->name,
                'category' => $badge->category,
                'category_label' => (string) ($categories[$badge->category]['label'] ?? ucfirst($badge->category)),
                'description' => $badge->description,
                'rarity' => $badge->rarity,
                'scope' => $badge->scope,
                'hpc_domain' => $badge->hpc_domain,
                'casel_domain' => $badge->casel_domain,
                'ncdg_goal' => $badge->ncdg_goal,
                'hpc_evidence_weight' => (float) $badge->hpc_evidence_weight,
                'challenge_mode_only' => (bool) $badge->challenge_mode_only,
                'earned' => $badgeAwards->isNotEmpty(),
                'times_earned' => $badgeAwards->count(),
                'awards' => $badgeAwards->map(fn (LearnerBadge $a) => [
                    'scope_key' => $a->scope_key,
                    'awarded_at' => $a->awarded_at?->toIso8601String(),
                    'context' => $a->context ?? [],
                    'student_message' => $this->renderMessage($badge, ['context' => $a->context ?? []]),
                ])->values()->all(),
            ];

            if ($entry['earned']) {
                $earned[] = $entry;
            } else {
                $available[] = $entry;
            }
        }

        // Newest award first — the collection reads as a timeline.
        usort($earned, function ($a, $b) {
            return strcmp(
                (string) ($b['awards'][0]['awarded_at'] ?? ''),
                (string) ($a['awards'][0]['awarded_at'] ?? '')
            );
        });

        $byCategory = [];
        foreach ($categories as $key => $meta) {
            $byCategory[] = [
                'key' => $key,
                'label' => (string) ($meta['label'] ?? ucfirst($key)),
                'blurb' => (string) ($meta['blurb'] ?? ''),
                'earned' => count(array_filter($earned, fn ($b) => $b['category'] === $key)),
                'total' => count(array_filter(
                    array_merge($earned, $available),
                    fn ($b) => $b['category'] === $key
                )),
            ];
        }

        return [
            'total_earned' => count($earned),
            'total_available' => count($earned) + count($available),
            'earned' => $earned,
            'available' => $available,
            'categories' => $byCategory,
            'revoked' => LearnerBadge::where('learner_id', $learnerId)
                ->whereNotNull('revoked_at')
                ->get()
                ->map(fn (LearnerBadge $a) => [
                    'badge_id' => $a->badge_id,
                    'scope_key' => $a->scope_key,
                    'revoked_at' => $a->revoked_at?->toIso8601String(),
                    'revoke_reason' => $a->revoke_reason,
                ])->all(),
        ];
    }

    /** One badge definition plus this learner's standing on it. */
    public function detail(int $learnerId, string $badgeId): ?array
    {
        $collection = $this->collection($learnerId);
        foreach (array_merge($collection['earned'], $collection['available']) as $badge) {
            if ($badge['badge_id'] === $badgeId) {
                $definition = Badge::where('badge_id', $badgeId)->first();
                $badge['trigger'] = $definition?->trigger_config ?? [];
                $badge['student_message_template'] = $definition?->student_message;

                return $badge;
            }
        }

        return null;
    }

    /** §10.3 teacher override — the award survives as audit, revoked. */
    public function revoke(int $learnerId, string $badgeId, string $scopeKey, int $revokedBy, string $reason): bool
    {
        $award = LearnerBadge::where('learner_id', $learnerId)
            ->where('badge_id', $badgeId)
            ->where('scope_key', $scopeKey)
            ->whereNull('revoked_at')
            ->first();

        if ($award === null) {
            return false;
        }

        $award->update([
            'revoked_at' => now(),
            'revoked_by' => $revokedBy,
            'revoke_reason' => $reason,
        ]);

        return true;
    }

    // =====================================================================
    // Rule evaluation
    // =====================================================================

    /**
     * Run one badge's rule and return every scope it is earned for. A global
     * badge returns at most one match; a concept- or subject-scoped badge can
     * return several.
     *
     * @return array<int,array<string,mixed>>
     */
    private function matches(Badge $badge, array $signals): array
    {
        $trigger = (array) ($badge->trigger_config ?? []);
        $type = (string) ($trigger['type'] ?? 'never');

        return match ($type) {
            'concepts_at_tier' => $this->once(
                ($signals['tier_counts'][$trigger['tier'] ?? 'mountain'] ?? 0) >= (int) ($trigger['count'] ?? 1),
                ['tier_counts' => $signals['tier_counts'], 'concept' => $signals['first_at_tier'][$trigger['tier'] ?? 'mountain'] ?? null]
            ),

            'subject_concepts_at_tier' => $this->subjectMatches($signals, (string) ($trigger['tier'] ?? 'sky'), (int) ($trigger['count'] ?? 5)),

            'all_hpc_lenses_at_tier' => $this->once(
                $signals['hpc_lenses_at_sky'] >= 3,
                ['lenses' => $signals['hpc_lens_evidence']]
            ),

            'net_fluency_at_least' => $this->conceptMatches(
                $signals['concepts'],
                fn ($c) => $c['best_net_fluency'] !== null && $c['best_net_fluency'] >= (float) ($trigger['threshold'] ?? 0.8)
            ),

            'consecutive_fluency' => $this->conceptMatches(
                $signals['concepts'],
                fn ($c) => ($signals['fluency_runs'][$c['concept_ref']] ?? 0) >= (int) ($trigger['sessions'] ?? 5),
                fn ($c) => ['run' => $signals['fluency_runs'][$c['concept_ref']] ?? 0]
            ),

            'challenge_mode_percentile' => $this->once(
                $signals['challenge_mode']['best_percentile'] !== null
                    && $signals['challenge_mode']['best_percentile'] >= (int) ($trigger['percentile'] ?? 90),
                ['percentile' => $signals['challenge_mode']['best_percentile']]
            ),

            'streak_days' => $this->once(
                $signals['longest_streak'] >= (int) ($trigger['days'] ?? 3),
                ['longest_streak' => $signals['longest_streak']]
            ),

            'return_after_gap' => $this->once(
                $signals['longest_return_gap'] >= (int) ($trigger['gap_days'] ?? 5),
                ['gap_days' => $signals['longest_return_gap']]
            ),

            'misconception_resolved_same_session' => $this->once(
                $signals['misconception_recoveries'] >= 1,
                ['recoveries' => $signals['misconception_recoveries']]
            ),

            'persisted_after_errors' => $this->once($signals['persisted_after_errors'], []),

            'self_directed_content' => $this->once(
                $signals['self_directed_opens'] >= (int) ($trigger['count'] ?? 1),
                ['opens' => $signals['self_directed_opens']]
            ),

            'single_concept_minutes' => $this->once(
                $signals['longest_single_concept_minutes'] >= (float) ($trigger['minutes'] ?? 20),
                ['minutes' => $signals['longest_single_concept_minutes']]
            ),

            'cross_curricular_completion' => $this->once(
                count($signals['cross_curricular']) >= (int) ($trigger['count'] ?? 1),
                ['links' => $signals['cross_curricular'][0] ?? null]
            ),

            'interest_pathway_activated' => $this->once(
                $signals['activated_pathways'] !== [],
                ['pathways' => $signals['activated_pathways']]
            ),

            'peer_teaching_sessions' => $this->once(
                $signals['peer_teaching_sessions'] >= (int) ($trigger['count'] ?? 1),
                ['sessions' => $signals['peer_teaching_sessions']]
            ),

            'team_challenge_contribution' => $this->once(
                $signals['completed_team_challenges'] >= (int) ($trigger['count'] ?? 1),
                ['challenges' => $signals['completed_team_challenges']]
            ),

            'unresolved_tutor_question' => $this->once(
                $signals['unresolved_tutor_questions'] >= (int) ($trigger['count'] ?? 1),
                []
            ),

            'career_scenarios_completed' => $this->once(
                count($signals['career_scenarios']) >= (int) ($trigger['count'] ?? 1),
                ['career' => $signals['career_scenarios'][0]['career_cluster'] ?? null]
            ),

            'riasec_profile_ready' => $this->once($signals['riasec_ready'], ['signals' => $signals['riasec']['total']]),

            'framework_tag_mastered' => $this->once(
                $this->frameworkMastered($signals, (string) ($trigger['framework_type'] ?? ''), (string) ($trigger['framework_tag'] ?? '')),
                ['framework_tag' => $trigger['framework_tag'] ?? null]
            ),

            'vocational_concept_at_tier' => $this->once(
                $signals['vocational_at_sky'] >= (int) ($trigger['count'] ?? 1),
                ['count' => $signals['vocational_at_sky']]
            ),

            'career_report_generated' => $this->once($signals['career_report_generated'], []),

            default => [],
        };
    }

    /** @return array<int,array<string,mixed>> */
    private function once(bool $condition, array $context): array
    {
        return $condition ? [['scope_key' => '', 'context' => $context]] : [];
    }

    /** @return array<int,array<string,mixed>> */
    private function conceptMatches(array $concepts, callable $predicate, ?callable $context = null): array
    {
        $out = [];
        foreach ($concepts as $concept) {
            if (! $predicate($concept)) {
                continue;
            }
            $out[] = [
                'scope_key' => (string) $concept['concept_ref'],
                'context' => array_merge([
                    'concept' => $concept['concept_label'],
                    'concept_ref' => $concept['concept_ref'],
                    'subject' => $concept['subject_name'],
                ], $context ? (array) $context($concept) : []),
            ];
        }

        return $out;
    }

    /** @return array<int,array<string,mixed>> */
    private function subjectMatches(array $signals, string $tier, int $count): array
    {
        $out = [];
        foreach ($signals['subject_tier_counts'] as $subjectKey => $tiers) {
            if (($tiers[$tier] ?? 0) < $count) {
                continue;
            }
            $out[] = [
                'scope_key' => (string) $subjectKey,
                'context' => [
                    'subject' => $signals['subject_names'][$subjectKey] ?? (string) $subjectKey,
                    'count' => $tiers[$tier],
                    'tier' => $tier,
                ],
            ];
        }

        return $out;
    }

    private function frameworkMastered(array $signals, string $type, string $tag): bool
    {
        foreach ($signals['framework_progress'] as $row) {
            if (strtolower($row['framework_type']) === strtolower($type)
                && strtolower($row['framework_tag']) === strtolower($tag)
                && in_array(strtolower($row['status']), ['mastered', 'proficient', 'achieved'], true)) {
                return true;
            }
        }

        return false;
    }

    // =====================================================================
    // The signal pack
    // =====================================================================

    /**
     * Everything the rules need, measured once from real data.
     *
     * @return array<string,mixed>
     */
    public function signals(int $learnerId): array
    {
        $concepts = $this->activity->conceptRecords($learnerId);
        $tierKeys = array_keys((array) config('pal_gamification.mastery_tiers', []));

        $tierCounts = array_fill_keys($tierKeys, 0);
        $firstAtTier = array_fill_keys($tierKeys, null);
        $subjectTierCounts = [];
        $subjectNames = [];

        foreach ($concepts as $concept) {
            $tier = (string) $concept['tier'];
            $tierCounts[$tier] = ($tierCounts[$tier] ?? 0) + 1;
            $firstAtTier[$tier] ??= $concept['concept_label'];

            $subjectKey = (string) ($concept['subject_id'] ?? 'unmapped');
            $subjectNames[$subjectKey] = (string) ($concept['subject_name'] ?: 'Unmapped subject');
            $subjectTierCounts[$subjectKey][$tier] = ($subjectTierCounts[$subjectKey][$tier] ?? 0) + 1;
        }

        $frameworkProgress = $this->activity->frameworkProgress($learnerId);
        $riasec = $this->activity->riasecSignals($learnerId);
        $careerScenarios = $this->activity->careerScenarioCompletions($learnerId);
        $questState = CareerQuestState::where('learner_id', $learnerId)->first();

        return [
            'concepts' => array_values($concepts),
            'tier_counts' => $tierCounts,
            'first_at_tier' => $firstAtTier,
            'subject_tier_counts' => $subjectTierCounts,
            'subject_names' => $subjectNames,
            'fluency_runs' => $this->fluencyRuns($learnerId),
            'hpc_lens_evidence' => $this->hpcLensEvidence($learnerId, $concepts),
            'hpc_lenses_at_sky' => count(array_filter($this->hpcLensEvidence($learnerId, $concepts), fn ($v) => $v['at_sky'])),
            'longest_streak' => $this->streaks->longestStreak($learnerId),
            'longest_return_gap' => $this->streaks->longestReturnGap($learnerId),
            'misconception_recoveries' => $this->activity->misconceptionRecoveries($learnerId),
            'persisted_after_errors' => $this->activity->persistedAfterEarlyErrors($learnerId, 2, 5),
            'self_directed_opens' => $this->activity->selfDirectedOpens($learnerId),
            'longest_single_concept_minutes' => $this->activity->longestSingleConceptMinutes($learnerId),
            'cross_curricular' => $this->activity->crossCurricularCompletions($learnerId),
            'activated_pathways' => $this->activatedPathways($frameworkProgress),
            'peer_teaching_sessions' => $this->activity->peerTeachingSessions($learnerId),
            'completed_team_challenges' => $this->completedTeamChallenges($learnerId),
            'unresolved_tutor_questions' => $this->activity->unresolvedTutorQuestions($learnerId),
            'career_scenarios' => $careerScenarios,
            'riasec' => $riasec,
            'riasec_ready' => $this->riasecReady($learnerId, $riasec),
            'framework_progress' => $frameworkProgress,
            'vocational_at_sky' => $this->vocationalAtSky($learnerId, $concepts),
            'career_report_generated' => $questState?->report_generated_at !== null,
            'challenge_mode' => $this->challengeModeSignals($learnerId),
        ];
    }

    /**
     * The longest run of consecutive attempts on one concept whose net fluency
     * stayed above the badge threshold — "sharp and accurate for 5 sessions in
     * a row", measured over the real attempt sequence.
     *
     * @return array<string,int> concept_ref => longest run
     */
    private function fluencyRuns(int $learnerId, float $threshold = 0.75): array
    {
        $runs = [];
        foreach ($this->activity->attempts($learnerId)->groupBy('concept_ref') as $ref => $attempts) {
            $current = 0;
            $best = 0;
            foreach ($attempts->values() as $attempt) {
                if ((float) $attempt['net_fluency'] >= $threshold) {
                    $current++;
                    $best = max($best, $current);
                } else {
                    $current = 0;
                }
            }
            $runs[(string) $ref] = $best;
        }

        return $runs;
    }

    /**
     * HPC lens evidence (Awareness / Sensitivity / Creativity).
     *
     * The lens a concept exercises is carried by the ULUs the learner engaged
     * with, so a lens only reaches Sky when a Sky-level concept is genuinely
     * linked to a ULU tagged for that lens. Where no ULU carries the tag, the
     * lens simply has no evidence — never an assumed one.
     *
     * @return array<string,array<string,mixed>>
     */
    private function hpcLensEvidence(int $learnerId, array $concepts): array
    {
        $lenses = (array) config('pal_content.hpc_lenses', ['Awareness', 'Sensitivity', 'Creativity']);
        $evidence = [];
        foreach ($lenses as $lens) {
            $evidence[$lens] = ['lens' => $lens, 'concepts' => 0, 'at_sky' => false];
        }

        $skyLabels = collect($concepts)
            ->filter(fn ($c) => $c['tier'] === 'sky')
            ->pluck('concept_label')
            ->map(fn ($v) => strtolower(trim((string) $v)))
            ->filter()
            ->all();

        foreach ($this->activity->engagedUlus($learnerId) as $ulu) {
            $lens = data_get($ulu, 'career_layer.hpc_lens');
            if (! is_string($lens) || ! isset($evidence[$lens])) {
                continue;
            }
            $evidence[$lens]['concepts']++;
            $conceptLabel = strtolower(trim((string) $ulu['academic_concept']));
            foreach ($skyLabels as $skyLabel) {
                if ($skyLabel !== '' && (str_contains($conceptLabel, $skyLabel) || str_contains($skyLabel, $conceptLabel))) {
                    $evidence[$lens]['at_sky'] = true;
                    break;
                }
            }
        }

        return $evidence;
    }

    /** Music / sports / finance pathways with real evidence behind them. */
    private function activatedPathways(array $frameworkProgress): array
    {
        $domains = ['music', 'sports', 'finance'];
        $active = [];
        foreach ($frameworkProgress as $row) {
            $type = strtolower($row['framework_type']);
            if (in_array($type, $domains, true) && $row['evidence_count'] > 0) {
                $active[$type] = true;
            }
        }

        return array_keys($active);
    }

    private function completedTeamChallenges(int $learnerId): int
    {
        if (! Schema::hasTable('pal_team_challenge_contributions')) {
            return 0;
        }

        return (int) DB::table('pal_team_challenge_contributions as c')
            ->join('pal_team_challenges as t', 't.id', '=', 'c.challenge_id')
            ->where('c.learner_id', $learnerId)
            ->where('c.contributed', true)
            ->where('t.status', 'completed')
            ->count();
    }

    private function riasecReady(int $learnerId, array $riasec): bool
    {
        $cfg = (array) config('pal_gamification.career_quest', []);
        $learner = $this->activity->learner($learnerId);
        $grade = $learner['grade_number'] ?? null;

        if ($grade !== null && $grade < (int) ($cfg['riasec_min_grade'] ?? 5)) {
            return false;
        }

        $distinct = count(array_filter($riasec['counts'], fn ($n) => $n > 0));

        return $riasec['total'] >= (int) ($cfg['riasec_min_signals'] ?? 8)
            && $distinct >= (int) ($cfg['riasec_min_distinct_types'] ?? 2);
    }

    /**
     * Concepts at Sky that are linked, through an engaged ULU, to a named real
     * skill — the estate's stand-in for an NSQF-mapped competency.
     */
    private function vocationalAtSky(int $learnerId, array $concepts): int
    {
        $skyLabels = collect($concepts)
            ->filter(fn ($c) => $c['tier'] === 'sky')
            ->pluck('concept_label')
            ->map(fn ($v) => strtolower(trim((string) $v)))
            ->filter()
            ->all();

        if ($skyLabels === []) {
            return 0;
        }

        $count = 0;
        foreach ($this->activity->engagedUlus($learnerId) as $ulu) {
            if (empty($ulu['real_skill_name'])) {
                continue;
            }
            $label = strtolower(trim((string) $ulu['academic_concept']));
            foreach ($skyLabels as $skyLabel) {
                if ($skyLabel !== '' && (str_contains($label, $skyLabel) || str_contains($skyLabel, $label))) {
                    $count++;
                    break;
                }
            }
        }

        return $count;
    }

    /** Opt-in state and best weekly percentile — Challenge Mode badges only. */
    private function challengeModeSignals(int $learnerId): array
    {
        $optIn = DB::table('pal_challenge_mode_optins')
            ->where('learner_id', $learnerId)
            ->value('opted_in');

        if (! $optIn) {
            return ['opted_in' => false, 'best_percentile' => null];
        }

        $learner = $this->activity->learner($learnerId);
        $best = null;

        $scores = ChallengeModeScore::where('learner_id', $learnerId)->get();
        foreach ($scores as $score) {
            $peers = ChallengeModeScore::where('week_start', $score->week_start)
                ->where('standard_id', $learner['standard_id'] ?? null)
                ->when(! empty($learner['division_id']), fn ($q) => $q->where('division_id', $learner['division_id']))
                ->pluck('score');

            if ($peers->count() < 2) {
                continue;
            }

            $below = $peers->filter(fn ($p) => (int) $p < (int) $score->score)->count();
            $percentile = (int) round(($below / max(1, $peers->count() - 1)) * 100);
            $best = $best === null ? $percentile : max($best, $percentile);
        }

        return ['opted_in' => true, 'best_percentile' => $best];
    }

    // =====================================================================
    // Presentation
    // =====================================================================

    /** @return \Illuminate\Support\Collection<int,Badge> */
    private function catalogue()
    {
        return Badge::active()->orderBy('sort_order')->get();
    }

    /** @return array<string,bool> "badge|scope" => true */
    private function heldKeys(int $learnerId): array
    {
        return LearnerBadge::where('learner_id', $learnerId)
            ->get()
            ->mapWithKeys(fn (LearnerBadge $a) => [$a->badge_id . '|' . $a->scope_key => true])
            ->all();
    }

    /** Fill the catalogue's message placeholders from the award's real context. */
    private function renderMessage(Badge $badge, array $match): string
    {
        $context = (array) ($match['context'] ?? []);
        $links = (array) ($context['links'] ?? []);

        return strtr((string) $badge->student_message, [
            ':concept' => (string) ($context['concept'] ?? 'this concept'),
            ':subject' => (string) ($context['subject'] ?? 'this subject'),
            ':career' => (string) ($context['career'] ?? 'that career'),
            ':from' => (string) ($context['subject'] ?? ($links[0] ?? 'one subject')),
            ':to' => (string) ($links[1] ?? ($links[0] ?? 'another subject')),
        ]);
    }

    private function present(Badge $badge, LearnerBadge $award, string $message): array
    {
        return [
            'badge_id' => $badge->badge_id,
            'name' => $badge->name,
            'category' => $badge->category,
            'rarity' => $badge->rarity,
            'hpc_domain' => $badge->hpc_domain,
            'casel_domain' => $badge->casel_domain,
            'ncdg_goal' => $badge->ncdg_goal,
            'scope_key' => $award->scope_key,
            'awarded_at' => $award->awarded_at?->toIso8601String(),
            'student_message' => $message,
        ];
    }
}
