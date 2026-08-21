<?php

namespace App\Services\PAL\Gamification;

use App\Models\PAL\Gamification\CareerQuestState;
use App\Models\PAL\Gamification\GamificationNotification;

/**
 * New PAL → Gamification: the Career Quest (document §5).
 *
 * The Career Quest answers the question every student asks and few products
 * address: why am I learning this? It reframes the whole journey as one story —
 * "you are on the path to becoming X, here is what that path looks like and
 * where you are on it".
 *
 * Everything in here is DERIVED. The stage comes from the learner's real grade;
 * the RIASEC profile from accumulated signal evidence; the pathway ranking from
 * that profile plus the career clusters of the ULUs the learner has actually
 * engaged with; skill progress from real mastery. The only stored facts are the
 * learner's own declarations — a non-binding interest, and later a chosen path.
 *
 * When the evidence is not there yet, the API says so. A Grade 3 learner does
 * not get a RIASEC profile; a learner with two signals does not get a ranked
 * career list built out of two signals. "Not ready yet" is a real answer.
 */
class CareerQuestService
{
    public function __construct(
        private readonly LearnerActivitySource $activity,
        private readonly PersonalBestService $personalBests,
    ) {
    }

    /**
     * The learner's whole quest state.
     *
     * @return array<string,mixed>
     */
    public function quest(int $learnerId): array
    {
        $learner = $this->activity->learner($learnerId);
        if ($learner === null) {
            return ['available' => false, 'reason' => 'learner_not_found'];
        }

        $grade = $learner['grade_number'];
        $stage = $this->stageFor($grade);
        $state = CareerQuestState::firstOrNew(['learner_id' => $learnerId]);

        $riasec = $this->riasecProfile($learnerId, $grade);
        $pathways = $stage['shows_pathways'] && $riasec['ready']
            ? $this->rankPathways($learnerId, $riasec)
            : [];

        $concepts = $this->activity->conceptRecords($learnerId);
        $scenarios = $this->activity->careerScenarioCompletions($learnerId);

        $primary = $state->chosen_primary_pathway ?: ($pathways[0]['key'] ?? null);
        $skillProgress = $primary !== null
            ? $this->skillProgress($learnerId, $primary, $concepts, $state)
            : null;

        return [
            'available' => true,
            'learner' => [
                'grade_number' => $grade,
                'grade_label' => $learner['standard_name'] ?: $learner['grade_name'],
            ],
            'stage' => $stage,
            'quest_level' => $this->questLevel($learnerId, $concepts),
            'islands' => $stage['key'] === 'explorer' ? $this->islands($learnerId) : [],
            'riasec' => $riasec,
            'pathways' => $pathways,
            'primary_pathway' => $primary,
            'skill_progress' => $skillProgress,
            'career_exposure' => $this->careerExposure($scenarios),
            'interest_declaration' => [
                'declared' => $state->interest_declaration ?: [],
                'declared_at' => $state->declared_at?->toIso8601String(),
                'invited' => $stage['key'] === 'skill_builder'
                    && count($scenarios) >= (int) ($stage['interest_declaration_after_scenarios'] ?? 5),
                'scenarios_completed' => count($scenarios),
                'scenarios_required' => (int) ($stage['interest_declaration_after_scenarios'] ?? 0),
            ],
            'report' => [
                'eligible' => (bool) ($stage['generates_pathway_report'] ?? false),
                'generated_at' => $state->report_generated_at?->toIso8601String(),
                'snapshot' => $state->report_snapshot,
            ],
            'quest_message' => (string) $stage['quest_message'],
        ];
    }

    /**
     * The three embedded touch points from §5.3 — what the learner sees during
     * a session rather than on a separate screen.
     */
    public function progress(int $learnerId): array
    {
        $quest = $this->quest($learnerId);
        if (! ($quest['available'] ?? false)) {
            return $quest;
        }

        $concepts = $this->activity->conceptRecords($learnerId);
        $pathwayKey = $quest['primary_pathway'];
        $clusterMap = $this->conceptClusterMap($learnerId);

        $contributions = [];
        if ($pathwayKey !== null) {
            $clusters = (array) config("pal_gamification.career_quest.pathways.{$pathwayKey}.clusters", []);
            foreach ($concepts as $ref => $concept) {
                $conceptClusters = $clusterMap[$ref] ?? [];
                if (array_intersect($conceptClusters, $clusters) === []) {
                    continue;
                }
                $contributions[] = [
                    'concept_ref' => (string) $ref,
                    'concept_label' => $concept['concept_label'],
                    'subject_name' => $concept['subject_name'],
                    'tier' => $concept['tier'],
                    'mastery' => (float) $concept['mastery'],
                    'mastered' => (float) $concept['mastery'] >= (float) config('pal_gamification.mastery_tiers.mountain.min_mastery', 0.70),
                    'clusters' => array_values($conceptClusters),
                ];
            }
        }

        return [
            'available' => true,
            'pathway' => $pathwayKey,
            'pathway_label' => $pathwayKey
                ? (string) config("pal_gamification.career_quest.pathways.{$pathwayKey}.label", $pathwayKey)
                : null,
            'skill_progress' => $quest['skill_progress'],
            'contributing_concepts' => $contributions,
            'weekly_summary' => $this->weeklySummary($learnerId),
        ];
    }

    /** The learner's own, non-binding interest declaration (§5.2, preparatory stage). */
    public function declareInterest(int $learnerId, array $interests): array
    {
        $allowed = array_keys((array) config('pal_gamification.career_quest.pathways', []));
        $clean = array_values(array_intersect(
            array_map(fn ($v) => strtolower(trim((string) $v)), $interests),
            $allowed
        ));

        $state = CareerQuestState::updateOrCreate(
            ['learner_id' => $learnerId],
            ['interest_declaration' => $clean, 'declared_at' => now()]
        );

        return [
            'declared' => $state->interest_declaration ?: [],
            'declared_at' => $state->declared_at?->toIso8601String(),
        ];
    }

    /** A learner choosing which suggested path to follow. Reversible by design. */
    public function choosePathway(int $learnerId, string $pathway): array
    {
        if (config("pal_gamification.career_quest.pathways.{$pathway}") === null) {
            return ['error' => 'That pathway is not in the catalogue.'];
        }

        $state = CareerQuestState::updateOrCreate(
            ['learner_id' => $learnerId],
            ['chosen_primary_pathway' => $pathway]
        );

        return ['primary_pathway' => $state->chosen_primary_pathway];
    }

    /**
     * Generate the Career Pathway Report (§5.2, secondary stage).
     *
     * Only at the Career Builder stage, and only from evidence that exists. The
     * snapshot is stored so the report a learner was shown does not silently
     * change underneath them.
     */
    public function generateReport(int $learnerId): array
    {
        $quest = $this->quest($learnerId);
        if (! ($quest['available'] ?? false)) {
            return ['error' => 'This learner has no career quest yet.'];
        }
        if (! ($quest['stage']['generates_pathway_report'] ?? false)) {
            return ['error' => 'The Career Pathway Report belongs to the Career Builder stage (Grade 9 and up).'];
        }
        if (! ($quest['riasec']['ready'] ?? false)) {
            return ['error' => 'There is not enough career evidence yet to write an honest report.'];
        }

        $snapshot = [
            'generated_at' => now()->toIso8601String(),
            'riasec' => $quest['riasec'],
            'pathways' => $quest['pathways'],
            'skill_progress' => $quest['skill_progress'],
            'career_exposure' => $quest['career_exposure'],
        ];

        CareerQuestState::updateOrCreate(
            ['learner_id' => $learnerId],
            ['report_generated_at' => now(), 'report_snapshot' => $snapshot]
        );

        GamificationNotification::create([
            'learner_id' => $learnerId,
            'type' => 'career_quest',
            'level' => 'milestone',
            'title' => 'Career Pathway Report ready',
            'body' => 'Years of learning just became a map of where you could go next. Read it.',
            'context' => ['pathways' => array_column($quest['pathways'], 'key')],
        ]);

        return ['report' => $snapshot];
    }

    // =====================================================================
    // Derivation
    // =====================================================================

    /** The stage for a real grade. Unknown grade → the earliest, safest stage. */
    public function stageFor(?int $grade): array
    {
        $stages = (array) config('pal_gamification.career_quest.stages', []);

        if ($grade !== null) {
            foreach ($stages as $stage) {
                if ($grade >= (int) $stage['grade_min'] && $grade <= (int) $stage['grade_max']) {
                    return $stage + ['grade_known' => true];
                }
            }
        }

        $first = reset($stages) ?: [];

        return $first + ['grade_known' => false];
    }

    /**
     * The RIASEC profile, or an honest statement of why there isn't one yet.
     *
     * Two independent gates: the grade floor (§5) and the evidence floor. A
     * profile built from one or two signals would be a guess presented as
     * insight, which is exactly what a career tool must not do to a child.
     */
    public function riasecProfile(int $learnerId, ?int $grade): array
    {
        $cfg = (array) config('pal_gamification.career_quest', []);
        $signals = $this->activity->riasecSignals($learnerId);
        $types = (array) ($cfg['riasec_types'] ?? []);

        $minGrade = (int) ($cfg['riasec_min_grade'] ?? 5);
        $minSignals = (int) ($cfg['riasec_min_signals'] ?? 8);
        $minDistinct = (int) ($cfg['riasec_min_distinct_types'] ?? 2);

        $distinct = count(array_filter($signals['counts'], fn ($n) => $n > 0));
        $gradeOk = $grade === null || $grade >= $minGrade;
        $ready = $gradeOk && $signals['total'] >= $minSignals && $distinct >= $minDistinct;

        arsort($signals['counts']);
        $ranked = [];
        foreach ($signals['counts'] as $type => $count) {
            $ranked[] = [
                'type' => $type,
                'label' => (string) ($types[$type]['label'] ?? $type),
                'blurb' => (string) ($types[$type]['blurb'] ?? ''),
                'signals' => (int) $count,
                'share' => $signals['total'] > 0 ? round(($count / $signals['total']) * 100, 1) : 0.0,
            ];
        }

        return [
            'ready' => $ready,
            'reason' => $ready ? null : (! $gradeOk
                ? 'grade_below_minimum'
                : ($signals['total'] < $minSignals ? 'not_enough_signals' : 'not_enough_distinct_signals')),
            'signals_total' => $signals['total'],
            'signals_required' => $minSignals,
            'distinct_types' => $distinct,
            'distinct_required' => $minDistinct,
            'min_grade' => $minGrade,
            'evidence_sources' => $signals['sources'],
            'types' => $ranked,
            'top' => $ready && $ranked !== [] && $ranked[0]['signals'] > 0 ? $ranked[0] : null,
        ];
    }

    /**
     * Rank the pathway catalogue for this learner.
     *
     * Score = RIASEC affinity (how much of the learner's own signal evidence
     * points at this pathway's types) + cluster engagement (how many concepts
     * they have practised belong to the pathway's career clusters). Both halves
     * are the learner's own record; nothing is seeded from the catalogue.
     */
    public function rankPathways(int $learnerId, array $riasec): array
    {
        $catalogue = (array) config('pal_gamification.career_quest.pathways', []);
        $counts = [];
        foreach ($riasec['types'] as $type) {
            $counts[$type['type']] = (int) $type['signals'];
        }
        $totalSignals = max(1, array_sum($counts));

        $clusterMap = $this->conceptClusterMap($learnerId);
        $clusterCounts = [];
        foreach ($clusterMap as $clusters) {
            foreach ($clusters as $cluster) {
                $clusterCounts[$cluster] = ($clusterCounts[$cluster] ?? 0) + 1;
            }
        }
        $totalClusters = max(1, array_sum($clusterCounts));

        $ranked = [];
        foreach ($catalogue as $key => $pathway) {
            $affinity = 0;
            foreach ((array) $pathway['riasec'] as $type) {
                $affinity += $counts[$type] ?? 0;
            }
            $engagement = 0;
            foreach ((array) $pathway['clusters'] as $cluster) {
                $engagement += $clusterCounts[$cluster] ?? 0;
            }

            $affinityShare = $affinity / $totalSignals;
            $engagementShare = $engagement / $totalClusters;
            $score = round(($affinityShare * 0.6 + $engagementShare * 0.4) * 100, 1);

            if ($affinity === 0 && $engagement === 0) {
                // No evidence at all points here — do not suggest it.
                continue;
            }

            $ranked[] = [
                'key' => (string) $key,
                'label' => (string) $pathway['label'],
                'skills_blurb' => (string) $pathway['skills_blurb'],
                'riasec' => (array) $pathway['riasec'],
                'clusters' => (array) $pathway['clusters'],
                'match_score' => $score,
                'riasec_signals' => $affinity,
                'concepts_engaged' => $engagement,
                'why' => $this->whyThisPathway($pathway, $counts, $clusterCounts),
            ];
        }

        usort($ranked, fn ($a, $b) => $b['match_score'] <=> $a['match_score']);

        $limit = (int) config('pal_gamification.career_quest.stages.pathway_seeker.pathway_suggestions', 3);

        return array_slice($ranked, 0, max(1, $limit));
    }

    private function whyThisPathway(array $pathway, array $riasecCounts, array $clusterCounts): string
    {
        $types = (array) config('pal_gamification.career_quest.riasec_types', []);
        $strongest = null;
        foreach ((array) $pathway['riasec'] as $type) {
            if ($strongest === null || ($riasecCounts[$type] ?? 0) > ($riasecCounts[$strongest] ?? 0)) {
                $strongest = $type;
            }
        }

        $clusterHits = 0;
        foreach ((array) $pathway['clusters'] as $cluster) {
            $clusterHits += $clusterCounts[$cluster] ?? 0;
        }

        $parts = [];
        if ($strongest !== null && ($riasecCounts[$strongest] ?? 0) > 0) {
            $parts[] = 'your learning keeps showing a ' . strtolower((string) ($types[$strongest]['label'] ?? $strongest)) . ' signal';
        }
        if ($clusterHits > 0) {
            $parts[] = $clusterHits . ' concept' . ($clusterHits === 1 ? '' : 's') . ' you have worked on belong here';
        }

        return $parts === []
            ? 'There is not much evidence pointing here yet.'
            : 'Because ' . implode(', and ', $parts) . '.';
    }

    /**
     * Which career clusters each practised concept belongs to, resolved through
     * the ULUs the learner has actually engaged with.
     *
     * @return array<string,array<int,string>> concept_ref => clusters
     */
    private function conceptClusterMap(int $learnerId): array
    {
        $concepts = $this->activity->conceptRecords($learnerId);
        $ulus = $this->activity->engagedUlus($learnerId);
        $map = [];

        foreach ($concepts as $ref => $concept) {
            $label = strtolower(trim((string) $concept['concept_label']));
            if ($label === '') {
                continue;
            }
            foreach ($ulus as $ulu) {
                $cluster = (string) ($ulu['career_cluster'] ?? '');
                if ($cluster === '') {
                    continue;
                }
                $uluConcept = strtolower(trim((string) $ulu['academic_concept']));
                $uluTitle = strtolower(trim((string) $ulu['title']));
                if (str_contains($uluConcept, $label) || str_contains($label, $uluConcept) || str_contains($uluTitle, $label)) {
                    $map[(string) $ref][$cluster] = $cluster;
                }
            }
        }

        return array_map('array_values', $map);
    }

    /**
     * Skill progress toward one pathway: how many of the concepts that feed it
     * the learner has actually mastered.
     */
    private function skillProgress(int $learnerId, string $pathwayKey, array $concepts, CareerQuestState $state): array
    {
        $clusters = (array) config("pal_gamification.career_quest.pathways.{$pathwayKey}.clusters", []);
        $clusterMap = $this->conceptClusterMap($learnerId);
        $threshold = (float) config('pal_gamification.mastery_tiers.mountain.min_mastery', 0.70);

        $relevant = [];
        foreach ($concepts as $ref => $concept) {
            if (array_intersect($clusterMap[$ref] ?? [], $clusters) === []) {
                continue;
            }
            $relevant[] = [
                'concept_ref' => (string) $ref,
                'concept_label' => $concept['concept_label'],
                'mastered' => (float) $concept['mastery'] >= $threshold,
                'tier' => $concept['tier'],
            ];
        }

        $mastered = count(array_filter($relevant, fn ($c) => $c['mastered']));
        // The target is the estate's own linked skill count where one exists,
        // falling back to the configured default only when nothing is mapped.
        $target = $state->skills_target_primary
            ?: (count($relevant) > 0
                ? count($relevant)
                : (int) config('pal_gamification.career_quest.default_skill_target', 20));

        return [
            'pathway' => $pathwayKey,
            'pathway_label' => (string) config("pal_gamification.career_quest.pathways.{$pathwayKey}.label", $pathwayKey),
            'mastered' => $mastered,
            'target' => $target,
            'percent' => $target > 0 ? (int) round(min(100, ($mastered / $target) * 100)) : 0,
            'skills' => $relevant,
            'target_source' => $state->skills_target_primary
                ? 'institute'
                : (count($relevant) > 0 ? 'linked_concepts' : 'default'),
        ];
    }

    /**
     * The five HPC "islands" of the Explorer stage (Grades 1–2), each flagged
     * once the learner has real evidence in that domain.
     */
    private function islands(int $learnerId): array
    {
        $keys = (array) config('pal_gamification.career_quest.stages.explorer.islands', []);
        $evidence = [];
        foreach ($this->activity->frameworkProgress($learnerId) as $row) {
            $evidence[strtolower($row['framework_tag'])] = ($evidence[strtolower($row['framework_tag'])] ?? 0) + $row['evidence_count'];
        }

        $conceptCount = count($this->activity->conceptRecords($learnerId));

        return array_map(fn ($key) => [
            'key' => $key,
            'label' => ucwords(str_replace('_', ' ', $key)),
            // Cognitive is evidenced by practised concepts; the rest need a
            // framework signal, which a fresh estate will simply not have.
            'flag_planted' => $key === 'cognitive'
                ? $conceptCount > 0
                : ($evidence[$key] ?? 0) > 0,
        ], $keys);
    }

    /**
     * Quest level — a level the learner climbs by mastering things, not by
     * spending time. Derived, never stored, so it can never drift from reality.
     */
    private function questLevel(int $learnerId, array $concepts): int
    {
        $tierCounts = $this->personalBests->tierCounts($concepts);

        return (int) (($tierCounts['mountain'] ?? 0) + (($tierCounts['sky'] ?? 0) * 2));
    }

    /** Which career clusters the learner has actually been exposed to. */
    private function careerExposure(array $scenarios): array
    {
        $byCluster = [];
        foreach ($scenarios as $scenario) {
            $cluster = (string) ($scenario['career_cluster'] ?? '');
            if ($cluster === '') {
                continue;
            }
            $byCluster[$cluster] ??= [
                'cluster' => $cluster,
                'label' => ucwords(str_replace('_', ' ', $cluster)),
                'count' => 0,
                'titles' => [],
            ];
            $byCluster[$cluster]['count']++;
            $title = data_get($scenario, 'career_layer.career_title');
            if (is_string($title) && $title !== '' && ! in_array($title, $byCluster[$cluster]['titles'], true)) {
                $byCluster[$cluster]['titles'][] = $title;
            }
        }

        return array_values($byCluster);
    }

    /** §5.3 point 3 — what moved this week, in career terms. */
    private function weeklySummary(int $learnerId): array
    {
        $since = now()->startOfWeek();
        $attempts = $this->activity->attempts($learnerId)
            ->filter(fn ($a) => $a['occurred_at'] !== null && $a['occurred_at']->greaterThanOrEqualTo($since));

        return [
            'week_start' => $since->toDateString(),
            'concepts_touched' => $attempts->pluck('concept_ref')->unique()->count(),
            'sessions' => $attempts->count(),
            'concepts' => $attempts->pluck('concept_label')->unique()->values()->all(),
        ];
    }
}
