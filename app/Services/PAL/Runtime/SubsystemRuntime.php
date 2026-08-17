<?php

namespace App\Services\PAL\Runtime;

use App\Services\PAL\Administration\ArchitectureRegistry;
use Illuminate\Support\Facades\Schema;

/**
 * New PAL → Administration — the live layer.
 *
 * Every subsystem page pairs its CONFIGURATION with what that configuration
 * actually produces when run against this estate's real learner evidence. This
 * class computes the second half.
 *
 * The engines are driven by the administrator's own settings — change P(S) in
 * the Mastery Model and the mastery numbers below change with it — so the
 * settings are demonstrably live rather than decorative.
 *
 * Uniform contract for every subsystem:
 *
 *   available   false when the subsystem cannot be computed at all
 *   reason      why, in the administrator's terms, when it cannot
 *   headline    a few measured figures
 *   tables      computed detail
 *   notes       caveats that change how the numbers should be read
 *
 * A subsystem that cannot be computed says so and explains what is missing. It
 * never falls back to a plausible-looking number.
 */
class SubsystemRuntime
{
    /** Learning units shown in a detail table before it is truncated. */
    private const TABLE_LIMIT = 25;

    /** Concepts drawn in the knowledge graph before the cluster is capped. */
    private const GRAPH_NODE_LIMIT = 44;

    public function __construct(
        private readonly PalEvidenceRepository $evidence,
        private readonly ArchitectureRegistry $registry
    ) {}

    public function for(string $subsystem, ?int $tenant): array
    {
        return $this->withSummary(match ($subsystem) {
            'mastery-model' => $this->masteryModel($tenant),
            'progression-rubric' => $this->progressionRubric($tenant),
            'student-model' => $this->studentModel($tenant),
            'adaptive-loop' => $this->adaptiveLoop($tenant),
            'intelligence-layers' => $this->intelligenceLayers($tenant),
            'hpc-stages' => $this->hpcStages($tenant),
            'knowledge-graph' => $this->knowledgeGraph($tenant),
            'career-pathway' => $this->careerPathway($tenant),
            'ai-agents' => $this->aiAgents(),
            default => $this->unavailable('No live computation is defined for this subsystem.'),
        });
    }

    /**
     * A one-line summary of the computed result, for the overview card.
     *
     * Derived from the first two headline figures rather than written per
     * subsystem, so it can never drift from the numbers beneath it. When the
     * subsystem cannot be computed, the blocker IS the summary — that is the
     * single most useful thing to show on a card.
     */
    private function withSummary(array $live): array
    {
        // Uniform shape: only the knowledge graph draws a diagram, but every
        // payload declares the key so the client never has to feature-detect.
        $live['graph'] = $live['graph'] ?? null;

        if (! $live['available']) {
            $live['summary'] = (string) $live['reason'];

            return $live;
        }

        $parts = [];
        foreach (array_slice($live['headline'], 0, 2) as $stat) {
            $parts[] = $stat['label'] . ': ' . $stat['value'];
        }

        $live['summary'] = $parts === [] ? 'Computed.' : implode(' · ', $parts);

        return $live;
    }

    // ══════════════════════════════════════════════════════════════════════
    // 3. Mastery model — real BKT
    // ══════════════════════════════════════════════════════════════════════

    private function masteryModel(?int $tenant): array
    {
        $units = $this->evidence->responseSequences($tenant);
        if ($units === []) {
            return $this->noEvidence();
        }

        $bkt = BktEngine::fromSettings((array) $this->registry->settings('mastery-model', 'bkt', $tenant));
        $fluencyEngine = FluencyEngine::fromSettings((array) $this->registry->settings('mastery-model', 'fluency', $tenant));
        $bands = (array) $this->registry->settings('mastery-model', 'bands', $tenant);

        $chapterNames = $this->evidence->chapterNames(array_column($units, 'chapter_id'));
        $concepts = $this->evidence->conceptsForChapters(array_column($units, 'chapter_id'), $tenant);

        // Tightest gate on the chapter — a chapter is only as unlocked as its
        // strictest tagged concept.
        $gateByChapter = [];
        foreach ($concepts as $concept) {
            $gate = $concept['mastery_gate'];
            $chapter = $concept['chapter_id'];
            if ($gate !== null && ($gateByChapter[$chapter] ?? 0) < $gate) {
                $gateByChapter[$chapter] = $gate;
            }
        }

        $rows = [];
        $totalMastery = 0.0;
        $credited = 0;
        $tiers = ['stream' => 0, 'mountain' => 0, 'sky' => 0];

        foreach ($units as $unit) {
            $trace = $bkt->trace($unit['responses']);
            $fluency = $fluencyEngine->measureAcrossSessions($unit['responses']);
            $gate = $gateByChapter[$unit['chapter_id']] ?? null;
            $band = $bkt->band($trace['mastery'], $bands, $gate);

            $totalMastery += $trace['mastery'];
            if ($trace['credited']) {
                $credited++;
                $tiers[$band['tier']] = ($tiers[$band['tier']] ?? 0) + 1;
            }

            $rows[] = [
                'learner' => (string) $unit['learner_id'],
                'chapter' => $chapterNames[$unit['chapter_id']] ?? ('Chapter #' . $unit['chapter_id']),
                'responses' => $trace['attempts'],
                'correct' => $trace['correct'] . '/' . $trace['attempts'],
                'prior' => number_format($trace['prior'], 2),
                'mastery' => number_format($trace['mastery'], 3),
                'delta' => sprintf('%+.3f', $trace['delta']),
                'band' => $this->humanise($band['key']),
                'tier' => $band['tier'],
                'gate' => $gate === null ? '—' : number_format($gate, 2),
                'fluency' => $fluency['measured'] ? number_format((float) $fluency['net'], 2) : '—',
                'credited' => $trace['credited'] ? 'yes' : 'no',
            ];
        }

        usort($rows, static fn ($a, $b) => (float) $b['mastery'] <=> (float) $a['mastery']);

        $params = $bkt->parameters();
        $mean = count($units) > 0 ? $totalMastery / count($units) : 0;

        return [
            'available' => true,
            'reason' => null,
            'headline' => [
                $this->stat('Learning units traced', (string) count($units), 'good'),
                $this->stat('Mean mastery', number_format($mean, 3), $mean >= 0.7 ? 'good' : ($mean >= 0.5 ? 'warn' : 'critical')),
                $this->stat('Credited', $credited . '/' . count($units), $credited > 0 ? 'good' : 'warn',
                    'At least ' . $params['min_attempts_for_mastery'] . ' responses'),
                $this->stat('Sky / Mountain / Stream', $tiers['sky'] . ' / ' . $tiers['mountain'] . ' / ' . $tiers['stream'], 'neutral'),
            ],
            'tables' => [[
                'title' => 'Bayesian Knowledge Tracing — computed per learner and chapter',
                'note' => sprintf(
                    'Run with your current parameters: P(L₀)=%.2f, P(T)=%.2f, P(S)=%.2f, P(G)=%.2f. Change them above and these numbers change.',
                    $params['p_init'], $params['p_transit'], $params['p_slip'], $params['p_guess']
                ),
                'columns' => [
                    ['key' => 'learner', 'label' => 'Learner'],
                    ['key' => 'chapter', 'label' => 'Chapter'],
                    ['key' => 'correct', 'label' => 'Correct', 'numeric' => true],
                    ['key' => 'prior', 'label' => 'Prior', 'numeric' => true],
                    ['key' => 'mastery', 'label' => 'Mastery', 'numeric' => true, 'emphasis' => true],
                    ['key' => 'delta', 'label' => 'Δ', 'numeric' => true],
                    ['key' => 'gate', 'label' => 'Gate', 'numeric' => true],
                    ['key' => 'band', 'label' => 'Band'],
                    ['key' => 'fluency', 'label' => 'Net fluency', 'numeric' => true],
                    ['key' => 'credited', 'label' => 'Credited'],
                ],
                'rows' => array_slice($rows, 0, self::TABLE_LIMIT),
            ]],
            'notes' => $this->grainNotes(count($rows)),
        ];
    }

    // ══════════════════════════════════════════════════════════════════════
    // 5. Progression rubric — real Stream / Mountain / Sky
    // ══════════════════════════════════════════════════════════════════════

    private function progressionRubric(?int $tenant): array
    {
        $units = $this->evidence->responseSequences($tenant);
        if ($units === []) {
            return $this->noEvidence();
        }

        $bkt = BktEngine::fromSettings((array) $this->registry->settings('mastery-model', 'bkt', $tenant));
        $fluencyEngine = FluencyEngine::fromSettings((array) $this->registry->settings('mastery-model', 'fluency', $tenant));
        $bands = (array) $this->registry->settings('mastery-model', 'bands', $tenant);
        $rubric = RubricEngine::fromSettings((array) $this->registry->settings('progression-rubric', 'triggers', $tenant));

        $chapterNames = $this->evidence->chapterNames(array_column($units, 'chapter_id'));

        $ratings = [];
        $rows = [];

        foreach ($units as $unit) {
            $trace = $bkt->trace($unit['responses']);
            $fluency = $fluencyEngine->measureAcrossSessions($unit['responses']);
            $band = $bkt->band($trace['mastery'], $bands);
            $rating = $rubric->rate($trace, $fluency, $band);
            $ratings[] = $rating;

            $rows[] = [
                'learner' => (string) $unit['learner_id'],
                'chapter' => $chapterNames[$unit['chapter_id']] ?? ('Chapter #' . $unit['chapter_id']),
                'awareness' => $rating['awareness'] === null ? 'not awarded' : $this->humanise($rating['awareness']),
                'sensitivity' => 'no evidence',
                'creativity' => 'no evidence',
                'basis' => $rating['evidence'][0] ?? ($rating['withheld']['awareness'] ?? '—'),
            ];
        }

        $distribution = $rubric->distribution($ratings);
        $triggers = $rubric->evidenceableTriggers();

        return [
            'available' => true,
            'reason' => null,
            'headline' => [
                $this->stat('Sky', (string) $distribution['sky'], $distribution['sky'] > 0 ? 'good' : 'neutral'),
                $this->stat('Mountain', (string) $distribution['mountain'], 'neutral'),
                $this->stat('Stream', (string) $distribution['stream'], 'neutral'),
                $this->stat('Not awarded', (string) $distribution['unrated'], $distribution['unrated'] > 0 ? 'warn' : 'good',
                    'Below minimum attempts'),
            ],
            'tables' => [
                [
                    'title' => 'Awarded levels, per learner and chapter',
                    'note' => 'Awareness is derived from computed mastery and fluency. Sensitivity and Creativity are withheld — see the note below.',
                    'columns' => [
                        ['key' => 'learner', 'label' => 'Learner'],
                        ['key' => 'chapter', 'label' => 'Chapter'],
                        ['key' => 'awareness', 'label' => 'Awareness', 'emphasis' => true],
                        ['key' => 'sensitivity', 'label' => 'Sensitivity'],
                        ['key' => 'creativity', 'label' => 'Creativity'],
                        ['key' => 'basis', 'label' => 'Evidence'],
                    ],
                    'rows' => array_slice($rows, 0, self::TABLE_LIMIT),
                ],
                [
                    'title' => 'Trigger coverage',
                    'note' => sprintf(
                        '%d of your %d active triggers can be evidenced by quiz data; %d need interaction data PAL does not yet capture.',
                        count($triggers['evidenceable']),
                        count($triggers['evidenceable']) + count($triggers['not_evidenceable']),
                        count($triggers['not_evidenceable'])
                    ),
                    'columns' => [
                        ['key' => 'level', 'label' => 'Level'],
                        ['key' => 'signal', 'label' => 'Observable signal'],
                        ['key' => 'status', 'label' => 'Evidenceable today'],
                    ],
                    'rows' => array_merge(
                        array_map(static fn ($t) => $t + ['status' => 'yes'], $triggers['evidenceable']),
                        array_map(static fn ($t) => $t + ['status' => 'no — needs interaction data'], $triggers['not_evidenceable'])
                    ),
                ],
            ],
            'notes' => [
                'Sensitivity and Creativity are deliberately withheld. Their triggers require peer interaction, self-revision and original work; a multiple-choice attempt cannot evidence them, and awarding a level anyway would produce an HPC record with no basis.',
            ],
        ];
    }

    // ══════════════════════════════════════════════════════════════════════
    // 8. Student model — the nine dimensions, computed where evidence allows
    // ══════════════════════════════════════════════════════════════════════

    private function studentModel(?int $tenant): array
    {
        $units = $this->evidence->responseSequences($tenant);
        if ($units === []) {
            return $this->noEvidence();
        }

        // Report on the learner with the most evidence — the best case this
        // estate can currently support.
        $byLearner = [];
        foreach ($units as $unit) {
            $byLearner[$unit['learner_id']] = array_merge($byLearner[$unit['learner_id']] ?? [], $unit['responses']);
        }
        uasort($byLearner, static fn ($a, $b) => count($b) <=> count($a));

        $learnerId = (int) array_key_first($byLearner);
        $responses = $byLearner[$learnerId];

        $bkt = BktEngine::fromSettings((array) $this->registry->settings('mastery-model', 'bkt', $tenant));
        $fluencyEngine = FluencyEngine::fromSettings((array) $this->registry->settings('mastery-model', 'fluency', $tenant));
        $policy = (array) $this->registry->settings('student-model', 'policy', $tenant);
        $dimensions = (array) $this->registry->settings('student-model', 'dimensions', $tenant);

        $trace = $bkt->trace($responses);
        $fluency = $fluencyEngine->measureAcrossSessions($responses);
        $minEvents = (int) ($policy['min_events_for_inference'] ?? 5);
        $enough = count($responses) >= $minEvents;

        $attempts = $this->evidence->attempts($tenant, $learnerId);
        $sessionCount = count($attempts);
        $span = $this->spanDays($responses);

        // Only dimensions with a real derivation are given a value. The rest
        // name the evidence they are missing.
        $computed = [
            'knowledge_mastery' => $enough ? number_format($trace['mastery'], 3) : null,
            'learning_speed' => $span > 0 ? number_format(count($responses) / max(1, $span) * 7, 2) . ' resp/wk' : null,
            'engagement' => $sessionCount > 0 ? (string) $sessionCount . ' attempts' : null,
            'error_patterns' => (string) $trace['wrong'] . ' wrong of ' . $trace['attempts'],
        ];

        $missing = [
            'confidence' => 'No self-rating or hesitation timing is captured at submission.',
            'forgetting_curve' => 'Needs a review schedule; no retention probe has run.',
            'learning_style' => 'Needs per-format engagement; PAL serves one format.',
            'socio_emotional' => 'Needs peer or teacher CASEL ratings.',
            'context_profile' => 'Needs mother tongue, device and bandwidth on the learner record.',
        ];

        $rows = [];
        $live = 0;
        foreach ($dimensions as $dimension) {
            $key = (string) ($dimension['key'] ?? '');
            $value = $computed[$key] ?? null;
            if ($value !== null) {
                $live++;
            }

            $rows[] = [
                'ordinal' => (string) ($dimension['ordinal'] ?? ''),
                'name' => (string) ($dimension['name'] ?? $key),
                'value' => $value ?? '—',
                'weight' => number_format((float) ($dimension['weight'] ?? 0), 2),
                'status' => $value !== null ? 'computed' : 'no evidence',
                'blocker' => $value !== null ? '' : ($missing[$key] ?? 'No evidence source is populated.'),
            ];
        }

        return [
            'available' => true,
            'reason' => null,
            'headline' => [
                $this->stat('Learner profiled', '#' . $learnerId, 'neutral', 'Most evidence on this estate'),
                $this->stat('Responses', (string) count($responses), $enough ? 'good' : 'warn',
                    'Minimum ' . $minEvents . ' to infer'),
                $this->stat('Dimensions computed', $live . '/' . count($dimensions), $live > 4 ? 'good' : 'warn'),
                $this->stat('Net fluency', $fluency['measured'] ? number_format((float) $fluency['net'], 2) : '—',
                    $fluency['measured'] ? 'good' : 'warn'),
            ],
            'tables' => [[
                'title' => 'The nine dimensions for learner #' . $learnerId,
                'note' => $fluency['interpretation'],
                'columns' => [
                    ['key' => 'ordinal', 'label' => '#', 'numeric' => true],
                    ['key' => 'name', 'label' => 'Dimension'],
                    ['key' => 'value', 'label' => 'Computed value', 'emphasis' => true],
                    ['key' => 'weight', 'label' => 'Weight', 'numeric' => true],
                    ['key' => 'status', 'label' => 'Status'],
                    ['key' => 'blocker', 'label' => 'Missing evidence'],
                ],
                'rows' => $rows,
            ]],
            'notes' => [],
        ];
    }

    // ══════════════════════════════════════════════════════════════════════
    // 2. Adaptive loop — the twelve steps, executed
    // ══════════════════════════════════════════════════════════════════════

    private function adaptiveLoop(?int $tenant): array
    {
        $units = $this->evidence->responseSequences($tenant);
        if ($units === []) {
            return $this->noEvidence();
        }

        $steps = (array) $this->registry->settings('adaptive-loop', 'steps', $tenant);
        $execution = (array) $this->registry->settings('adaptive-loop', 'execution', $tenant);
        $bkt = BktEngine::fromSettings((array) $this->registry->settings('mastery-model', 'bkt', $tenant));
        $bands = (array) $this->registry->settings('mastery-model', 'bands', $tenant);

        $unit = $units[array_key_first($units)];
        $trace = $bkt->trace($unit['responses']);
        $band = $bkt->band($trace['mastery'], $bands);

        $switchAfter = (int) ($execution['consecutive_wrong_switch_modality'] ?? 2);
        $advanceAfter = (int) ($execution['consecutive_correct_advance'] ?? 3);
        [$switches, $advances] = $this->countRuns($unit['responses'], $switchAfter, $advanceAfter);

        // What each step can actually do against this evidence.
        $produced = [
            'goal_selection' => 'Chapter #' . $unit['chapter_id'] . ' selected by the learner',
            'diagnostic' => $trace['attempts'] . ' responses recorded',
            'graph_build' => 'BKT updated → mastery ' . number_format($trace['mastery'], 3),
            'gap_identification' => $trace['wrong'] . ' wrong response(s) available to diagnose',
            'path_generation' => 'Band "' . $this->humanise($band['key']) . '" → ' . $band['serves'],
            'micro_delivery' => 'Serves: ' . $band['serves'],
            'in_session_adaptation' => $switches . ' modality switch(es), ' . $advances . ' difficulty advance(s) would fire',
            'ai_feedback' => null,
            'spaced_reinforcement' => 'Next review in ' . $band['review_interval_days'] . ' day(s)',
            'adaptive_reassessment' => $band['tier'] === 'sky' ? 'Sky tier — expanded tasks apply' : 'Not a Sky learner yet',
            'mastery_update' => 'Mastery Δ ' . sprintf('%+.3f', $trace['delta']),
            'retention_tracking' => null,
        ];

        $blockers = [
            'ai_feedback' => 'Needs a misconception library entry and an AI provider key.',
            'retention_tracking' => 'Needs a scheduled job; none is registered.',
        ];

        $rows = [];
        $executable = 0;
        foreach ($steps as $step) {
            $key = (string) ($step['key'] ?? '');
            $output = $produced[$key] ?? null;
            if ($output !== null && ! empty($step['enabled'])) {
                $executable++;
            }

            $rows[] = [
                'step' => (string) ($step['step'] ?? ''),
                'name' => (string) ($step['name'] ?? $key),
                'enabled' => ! empty($step['enabled']) ? 'on' : 'off',
                'output' => $output ?? '—',
                'status' => $output === null ? 'blocked' : (! empty($step['enabled']) ? 'executes' : 'disabled'),
                'blocker' => $output === null ? ($blockers[$key] ?? 'No engine is bound to this step.') : '',
            ];
        }

        return [
            'available' => true,
            'reason' => null,
            'headline' => [
                $this->stat('Steps executable', $executable . '/' . count($steps), $executable >= 10 ? 'good' : 'warn'),
                $this->stat('Traced unit', 'Learner #' . $unit['learner_id'], 'neutral', 'Chapter #' . $unit['chapter_id']),
                $this->stat('Modality switches', (string) $switches, $switches > 0 ? 'warn' : 'good',
                    'After ' . $switchAfter . ' wrong'),
                $this->stat('Difficulty advances', (string) $advances, 'neutral', 'After ' . $advanceAfter . ' correct'),
            ],
            'tables' => [[
                'title' => 'The twelve steps run against a real attempt',
                'note' => 'Learner #' . $unit['learner_id'] . ', chapter #' . $unit['chapter_id'] . ' — ' . $trace['attempts'] . ' responses.',
                'columns' => [
                    ['key' => 'step', 'label' => '#', 'numeric' => true],
                    ['key' => 'name', 'label' => 'Step'],
                    ['key' => 'enabled', 'label' => 'Config'],
                    ['key' => 'output', 'label' => 'What it produced', 'emphasis' => true],
                    ['key' => 'status', 'label' => 'Status'],
                    ['key' => 'blocker', 'label' => 'Blocker'],
                ],
                'rows' => $rows,
            ]],
            'notes' => [],
        ];
    }

    // ══════════════════════════════════════════════════════════════════════
    // 1. Intelligence layers
    // ══════════════════════════════════════════════════════════════════════

    private function intelligenceLayers(?int $tenant): array
    {
        $layers = (array) $this->registry->settings('intelligence-layers', 'layers', $tenant);
        $units = $this->evidence->responseSequences($tenant);
        $responses = array_sum(array_map(static fn ($u) => count($u['responses']), $units));

        $rows = [];
        $wired = 0;

        foreach ($layers as $layer) {
            $service = (string) ($layer['owner_service'] ?? '');
            $deployed = class_exists($service);

            $rowsAvailable = 0;
            foreach ((array) ($layer['reads_tables'] ?? []) as $table) {
                if (Schema::hasTable($table)) {
                    $rowsAvailable += $this->safeCount($table);
                }
            }

            // A layer is only genuinely live if the code exists AND something
            // feeds it. Legacy PAL evidence counts for the layers that could
            // consume it once the write path exists.
            $legacyFed = in_array($layer['key'] ?? '', ['learner_profile', 'bkt', 'retention'], true) && $responses > 0;
            $live = $deployed && ($rowsAvailable > 0 || $legacyFed);
            if ($live) {
                $wired++;
            }

            $rows[] = [
                'ordinal' => (string) ($layer['ordinal'] ?? ''),
                'name' => (string) ($layer['name'] ?? ''),
                'deployed' => $deployed ? 'yes' : 'no',
                'own_rows' => (string) $rowsAvailable,
                'legacy_evidence' => $legacyFed ? (string) $responses . ' responses' : '—',
                'status' => $live ? 'live' : ($deployed ? 'inert' : 'missing'),
            ];
        }

        return [
            'available' => true,
            'reason' => null,
            'headline' => [
                $this->stat('Layers live', $wired . '/' . count($layers), $wired === count($layers) ? 'good' : 'warn'),
                $this->stat('PAL responses on estate', (string) $responses, $responses > 0 ? 'good' : 'critical'),
                $this->stat('Learning units', (string) count($units), count($units) > 0 ? 'good' : 'critical'),
            ],
            'tables' => [[
                'title' => 'Layer readiness, measured',
                'note' => '"Own rows" counts the pal_* tables a layer declares. "Legacy evidence" is the real PAL attempt data that layer could consume once the write path exists.',
                'columns' => [
                    ['key' => 'ordinal', 'label' => '#', 'numeric' => true],
                    ['key' => 'name', 'label' => 'Layer'],
                    ['key' => 'deployed', 'label' => 'Code deployed'],
                    ['key' => 'own_rows', 'label' => 'Own rows', 'numeric' => true],
                    ['key' => 'legacy_evidence', 'label' => 'Legacy evidence'],
                    ['key' => 'status', 'label' => 'Status', 'emphasis' => true],
                ],
                'rows' => $rows,
            ]],
            'notes' => [],
        ];
    }

    // ══════════════════════════════════════════════════════════════════════
    // 4. HPC stages
    // ══════════════════════════════════════════════════════════════════════

    private function hpcStages(?int $tenant): array
    {
        $stages = (array) $this->registry->settings('hpc-stages', 'stages', $tenant);
        $units = $this->evidence->responseSequences($tenant);

        // Grade comes from the standard the PAL paper was generated for —
        // `tblstudent` on this estate carries neither user_id nor standard.
        $gradeByStandard = $this->evidence->gradesForStandards(array_column($units, 'standard_id'));

        $learnerGrades = [];
        $unplaced = [];
        foreach ($units as $unit) {
            $grade = $gradeByStandard[$unit['standard_id']] ?? null;
            if ($grade === null) {
                $unplaced[$unit['learner_id']] = true;
                continue;
            }
            $learnerGrades[$unit['learner_id']] = $grade;
        }

        $rows = [];
        $covered = 0;

        foreach ($stages as $stage) {
            $from = (int) ($stage['grade_from'] ?? 0);
            $to = (int) ($stage['grade_to'] ?? 0);

            $learners = 0;
            foreach ($learnerGrades as $grade) {
                if ($grade >= $from && $grade <= $to) {
                    $learners++;
                }
            }

            $chapters = $this->evidence->chaptersByGrade($from, $to, $tenant);
            if ($chapters > 0) {
                $covered++;
            }

            $rows[] = [
                'stage' => (string) ($stage['label'] ?? ''),
                'grades' => $from . '–' . $to,
                'ceiling' => $this->humanise((string) ($stage['bloom_ceiling'] ?? '')),
                'chapters' => (string) $chapters,
                'learners' => (string) $learners,
                'status' => $chapters > 0 ? ($learners > 0 ? 'active' : 'content only') : 'no content',
            ];
        }

        return [
            'available' => true,
            'reason' => null,
            'headline' => [
                $this->stat('Stages with content', $covered . '/' . count($stages), $covered === count($stages) ? 'good' : 'warn'),
                $this->stat('PAL learners placed', (string) count($learnerGrades), count($learnerGrades) > 0 ? 'good' : 'warn'),
                $this->stat('Learners without a grade', (string) count($unplaced),
                    $unplaced === [] ? 'good' : 'warn', 'Standard has no numeric grade'),
            ],
            'tables' => [[
                'title' => 'Stage coverage, measured',
                'note' => 'Chapters come from the extracted content estate; learners are the PAL learners whose assessed grade falls in the stage band.',
                'columns' => [
                    ['key' => 'stage', 'label' => 'Stage'],
                    ['key' => 'grades', 'label' => 'Grades'],
                    ['key' => 'ceiling', 'label' => 'Bloom ceiling'],
                    ['key' => 'chapters', 'label' => 'Chapters', 'numeric' => true],
                    ['key' => 'learners', 'label' => 'PAL learners', 'numeric' => true],
                    ['key' => 'status', 'label' => 'Status', 'emphasis' => true],
                ],
                'rows' => $rows,
            ]],
            'notes' => [],
        ];
    }

    // ══════════════════════════════════════════════════════════════════════
    // 6. Knowledge graph — the prerequisite DAG that actually exists
    // ══════════════════════════════════════════════════════════════════════

    private function knowledgeGraph(?int $tenant): array
    {
        $graph = $this->evidence->prerequisiteGraph($tenant);

        if ($graph['nodes'] === 0) {
            return $this->unavailable(
                'No concept or prerequisite data could be read from the extracted chapter intelligence, so no graph can be projected.'
            );
        }

        $view = $this->layoutGraph($graph['resolved_edges'] ?? []);

        $notes = [];
        if ($graph['dangling'] > 0) {
            $notes[] = sprintf(
                '%d prerequisite name(s) do not match any extracted concept, so those edges cannot be drawn — one end of them does not exist. Reconciling the names is what would connect them.',
                $graph['dangling']
            );
        }
        if ($view !== null && $view['truncated']) {
            $notes[] = sprintf(
                'Showing the largest connected cluster: %d of %d concepts and %d of %d resolvable edges. The full graph has %d component(s).',
                count($view['nodes']), $graph['nodes'], count($view['edges']),
                count($graph['resolved_edges'] ?? []), $view['components']
            );
        }

        return [
            'available' => true,
            'reason' => null,
            'headline' => [
                $this->stat('Concept nodes', (string) $graph['nodes'], 'good'),
                $this->stat('Prerequisite edges', (string) $graph['edges'], $graph['edges'] > 0 ? 'good' : 'warn'),
                $this->stat('Drawable edges', (string) count($graph['resolved_edges'] ?? []),
                    ($graph['resolved_edges'] ?? []) !== [] ? 'good' : 'critical', 'Both ends resolve'),
                $this->stat('Dangling edges', (string) $graph['dangling'], $graph['dangling'] > 0 ? 'warn' : 'good',
                    'Prerequisite names matching no concept'),
            ],
            // Rendered as a diagram, not a table: a prerequisite chain is a
            // shape, and the thing an administrator needs to see is which
            // concepts gate which — that is invisible in a row list.
            'graph' => $view,
            'tables' => [],
            'notes' => $notes,
        ];
    }

    /**
     * Lay out the prerequisite DAG for drawing.
     *
     * Two decisions keep this legible rather than a hairball:
     *
     *   1. Only the LARGEST CONNECTED CLUSTER is drawn. The estate's resolvable
     *      edges form many small islands; overlaying them produces noise, while
     *      one cluster shows a real prerequisite chain.
     *   2. Nodes are assigned to LAYERS by longest path from a root (Kahn's
     *      algorithm). Layer 0 holds concepts with no prerequisite; each edge
     *      then points strictly left-to-right, which is what makes the picture
     *      readable. Any node left over after Kahn's is in a cycle — a genuine
     *      content error — and is placed in a final layer and flagged.
     *
     * @param array<int, array{from:string,to:string}> $edges
     */
    private function layoutGraph(array $edges): ?array
    {
        if ($edges === []) {
            return null;
        }

        // Adjacency, both directions.
        $out = [];
        $in = [];
        $undirected = [];
        foreach ($edges as $edge) {
            $from = $edge['from'];
            $to = $edge['to'];
            $out[$from][$to] = true;
            $in[$to][$from] = true;
            $undirected[$from][$to] = true;
            $undirected[$to][$from] = true;
        }

        $allNodes = array_keys($undirected);

        // Weakly-connected components.
        $seen = [];
        $components = [];
        foreach ($allNodes as $node) {
            if (isset($seen[$node])) {
                continue;
            }
            $stack = [$node];
            $component = [];
            $seen[$node] = true;
            while ($stack !== []) {
                $current = array_pop($stack);
                $component[] = $current;
                foreach (array_keys($undirected[$current] ?? []) as $neighbour) {
                    if (! isset($seen[$neighbour])) {
                        $seen[$neighbour] = true;
                        $stack[] = $neighbour;
                    }
                }
            }
            $components[] = $component;
        }

        usort($components, static fn ($a, $b) => count($b) <=> count($a));
        $chosen = $components[0];

        // Cap by degree so a huge cluster still renders.
        $truncated = false;
        if (count($chosen) > self::GRAPH_NODE_LIMIT) {
            usort($chosen, static fn ($a, $b) => count($undirected[$b] ?? []) <=> count($undirected[$a] ?? []));
            $chosen = array_slice($chosen, 0, self::GRAPH_NODE_LIMIT);
            $truncated = true;
        }

        $keep = array_flip($chosen);

        $visibleEdges = [];
        foreach ($edges as $edge) {
            if (isset($keep[$edge['from']], $keep[$edge['to']]) && $edge['from'] !== $edge['to']) {
                $visibleEdges[$edge['from'] . '→' . $edge['to']] = $edge;
            }
        }
        $visibleEdges = array_values($visibleEdges);

        // Kahn's algorithm for layering.
        $indegree = [];
        foreach ($chosen as $node) {
            $indegree[$node] = 0;
        }
        foreach ($visibleEdges as $edge) {
            $indegree[$edge['to']]++;
        }

        $layer = [];
        $queue = [];
        foreach ($indegree as $node => $degree) {
            if ($degree === 0) {
                $queue[] = $node;
                $layer[$node] = 0;
            }
        }

        while ($queue !== []) {
            $node = array_shift($queue);
            foreach (array_keys($out[$node] ?? []) as $next) {
                if (! isset($keep[$next])) {
                    continue;
                }
                $indegree[$next]--;
                $layer[$next] = max($layer[$next] ?? 0, ($layer[$node] ?? 0) + 1);
                if ($indegree[$next] === 0) {
                    $queue[] = $next;
                }
            }
        }

        // Anything unlayered sits on a cycle.
        $cyclic = [];
        $maxLayer = $layer === [] ? 0 : max($layer);
        foreach ($chosen as $node) {
            if (! isset($layer[$node])) {
                $layer[$node] = $maxLayer + 1;
                $cyclic[$node] = true;
            }
        }

        $nodes = [];
        foreach ($chosen as $node) {
            $nodes[] = [
                'id' => $node,
                'label' => $this->titleCase($node),
                'layer' => (int) $layer[$node],
                'in' => count(array_intersect_key($in[$node] ?? [], $keep)),
                'out' => count(array_intersect_key($out[$node] ?? [], $keep)),
                'cyclic' => isset($cyclic[$node]),
            ];
        }

        usort($nodes, static fn ($a, $b) => [$a['layer'], $a['label']] <=> [$b['layer'], $b['label']]);

        return [
            'nodes' => $nodes,
            'edges' => array_map(static fn ($edge) => ['from' => $edge['from'], 'to' => $edge['to']], $visibleEdges),
            'layers' => $layer === [] ? 1 : max($layer) + 1,
            'components' => count($components),
            'truncated' => $truncated,
            'has_cycle' => $cyclic !== [],
        ];
    }

    private function titleCase(string $value): string
    {
        return ucwords(trim($value));
    }

    // ══════════════════════════════════════════════════════════════════════
    // 9. Career pathway
    // ══════════════════════════════════════════════════════════════════════

    private function careerPathway(?int $tenant): array
    {
        $coverage = $this->evidence->careerSignalCoverage($tenant);

        if ($coverage['total'] === 0) {
            return $this->unavailable('No concept metadata exists on this estate, so no career signal can be accumulated.');
        }

        $policy = (array) $this->registry->settings('career-pathway', 'policy', $tenant);
        $units = $this->evidence->responseSequences($tenant);
        $responses = array_sum(array_map(static fn ($u) => count($u['responses']), $units));
        $required = (int) ($policy['min_events_for_report'] ?? 1500);

        $rows = [];
        foreach (['riasec' => 'RIASEC', 'gardner' => 'Gardner', 'ncdg' => 'NCDG'] as $key => $label) {
            $tally = $coverage[$key];
            $rows[] = [
                'signal' => $label,
                'tagged' => (string) array_sum($tally),
                'distinct' => (string) count($tally),
                'top' => $tally === [] ? '—' : implode(', ', array_slice(array_map(
                    static fn ($v, $k) => "$k ($v)",
                    $tally,
                    array_keys($tally)
                ), 0, 4)),
                'status' => $tally === [] ? 'not tagged' : 'tagged on content',
            ];
        }

        $pct = $coverage['total'] > 0 ? round($coverage['tagged'] / $coverage['total'] * 100) : 0;

        return [
            'available' => true,
            'reason' => null,
            'headline' => [
                $this->stat('Concepts with metadata', (string) $coverage['total'], 'good'),
                $this->stat('Carrying a career signal', $coverage['tagged'] . ' (' . $pct . '%)',
                    $pct > 50 ? 'good' : ($pct > 0 ? 'warn' : 'critical')),
                $this->stat('Learner responses', (string) $responses, $responses > 0 ? 'warn' : 'critical'),
                $this->stat('Needed for a report', (string) $required, 'neutral', 'Per your policy'),
            ],
            'tables' => [[
                'title' => 'Career signal coverage on tagged content',
                'note' => 'These are signals carried by CONTENT. A learner profile accumulates only when an answered question is attributed to a tagged concept.',
                'columns' => [
                    ['key' => 'signal', 'label' => 'Framework'],
                    ['key' => 'tagged', 'label' => 'Concepts tagged', 'numeric' => true],
                    ['key' => 'distinct', 'label' => 'Distinct values', 'numeric' => true],
                    ['key' => 'top', 'label' => 'Most common'],
                    ['key' => 'status', 'label' => 'Status', 'emphasis' => true],
                ],
                'rows' => $rows,
            ]],
            'notes' => [
                'No learner career profile can be built yet: PAL questions carry no concept_id, so an answered question cannot be attributed to a tagged concept. Attribution is the missing link, not the vocabularies.',
            ],
        ];
    }

    // ══════════════════════════════════════════════════════════════════════
    // 7. AI agents
    // ══════════════════════════════════════════════════════════════════════

    private function aiAgents(): array
    {
        $agents = (array) config('pal_architecture.subsystems.ai-agents.settings.agents', []);
        $key = trim((string) config('openrouter.api_key', ''));
        $baseUrl = trim((string) config('openrouter.base_url', ''));
        $orchestrator = class_exists(\App\Services\PAL\AI\AIOrchestrationService::class);
        $ready = $key !== '' && $baseUrl !== '' && $orchestrator;

        $rows = array_map(static fn ($agent) => [
            'name' => (string) ($agent['name'] ?? ''),
            'autonomy' => strtoupper((string) ($agent['autonomy'] ?? '')),
            'enabled' => ! empty($agent['enabled']) ? 'on' : 'off',
            'can_run' => $ready && ! empty($agent['enabled']) ? 'yes' : 'no',
            'blocker' => $ready
                ? (! empty($agent['enabled']) ? '' : 'Disabled in configuration.')
                : 'No AI provider credential is configured on this server.',
        ], $agents);

        $enabled = count(array_filter($agents, static fn ($a) => ! empty($a['enabled'])));

        return [
            'available' => true,
            'reason' => null,
            'headline' => [
                $this->stat('Orchestrator', $orchestrator ? 'Deployed' : 'Missing', $orchestrator ? 'good' : 'critical'),
                $this->stat('Provider credential', $key !== '' ? 'Present' : 'Missing', $key !== '' ? 'good' : 'critical'),
                $this->stat('Agents enabled', $enabled . '/' . count($agents), $enabled > 0 ? 'good' : 'warn'),
                $this->stat('Agents able to run', $ready ? (string) $enabled : '0', $ready ? 'good' : 'critical'),
            ],
            'tables' => [[
                'title' => 'Agent readiness',
                'note' => $ready
                    ? 'The provider is configured; enabled agents can execute.'
                    : 'Without a provider credential every agent falls back to its non-generative behaviour.',
                'columns' => [
                    ['key' => 'name', 'label' => 'Agent'],
                    ['key' => 'autonomy', 'label' => 'Autonomy'],
                    ['key' => 'enabled', 'label' => 'Config'],
                    ['key' => 'can_run', 'label' => 'Can run', 'emphasis' => true],
                    ['key' => 'blocker', 'label' => 'Blocker'],
                ],
                'rows' => $rows,
            ]],
            'notes' => [],
        ];
    }

    // ══════════════════════════════════════════════════════════════════════
    // Helpers
    // ══════════════════════════════════════════════════════════════════════

    /** Consecutive-run counts, as the in-session adaptation rules would fire. */
    private function countRuns(array $responses, int $switchAfter, int $advanceAfter): array
    {
        $switches = 0;
        $advances = 0;
        $wrongRun = 0;
        $rightRun = 0;

        foreach ($responses as $response) {
            if (! empty($response['correct'])) {
                $rightRun++;
                $wrongRun = 0;
                if ($advanceAfter > 0 && $rightRun % $advanceAfter === 0) {
                    $advances++;
                }
            } else {
                $wrongRun++;
                $rightRun = 0;
                if ($switchAfter > 0 && $wrongRun % $switchAfter === 0) {
                    $switches++;
                }
            }
        }

        return [$switches, $advances];
    }

    private function spanDays(array $responses): int
    {
        $times = [];
        foreach ($responses as $response) {
            $at = strtotime((string) ($response['at'] ?? ''));
            if ($at !== false && $at > 0) {
                $times[] = $at;
            }
        }

        if (count($times) < 2) {
            return 0;
        }

        return (int) max(1, round((max($times) - min($times)) / 86400));
    }

    private function safeCount(string $table): int
    {
        try {
            return (int) \Illuminate\Support\Facades\DB::table($table)->count();
        } catch (\Throwable) {
            return 0;
        }
    }

    private function grainNotes(int $rowCount): array
    {
        $notes = [
            'Mastery is traced per learner and CHAPTER, not per concept: no PAL question on this estate carries a concept_id, so finer attribution would be invented rather than measured.',
        ];

        if ($rowCount > self::TABLE_LIMIT) {
            $notes[] = sprintf('Showing the %d highest-mastery units of %d.', self::TABLE_LIMIT, $rowCount);
        }

        return $notes;
    }

    private function stat(string $label, string $value, string $tone, ?string $note = null): array
    {
        return ['label' => $label, 'value' => $value, 'tone' => $tone, 'note' => $note];
    }

    private function humanise(string $value): string
    {
        return ucfirst(trim(str_replace(['_', '-'], ' ', $value)));
    }

    private function noEvidence(): array
    {
        return $this->unavailable(
            'No PAL attempt has been recorded on this estate. These engines run the moment a learner submits a PAL quiz — nothing else is needed.'
        );
    }

    private function unavailable(string $reason): array
    {
        return [
            'available' => false,
            'reason' => $reason,
            'headline' => [],
            'tables' => [],
            'graph' => null,
            'notes' => [],
        ];
    }
}
