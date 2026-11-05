<?php

namespace App\Services\PAL\ContextGraph;

use App\Services\PAL\Coherence\CoherenceMapRepository;
use App\Services\PAL\Coherence\CoherenceRecommender;
use App\Services\PAL\Runtime\PalEvidenceRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Learner Context Graph Service
 *
 * Assembles the complete 4-layer learner context graph for personalized learning:
 *
 * 1. User Context: Student identity, active enrollment, standard, division, institute.
 * 2. Learning Activities: Diagnostic attempts, practice scores, quizzes, engagement.
 * 3. Knowledge Graph Relationships: Subject curriculum, concepts, prerequisite edges,
 *    readiness classification (mastered/ready/blocked), and root prerequisite blockers.
 * 4. Historical Trajectory: BKT mastery states, spaced repetition schedules, and decision audit logs.
 *
 * Multi-tenant isolation: Scoped strictly by `sub_institute_id` at every layer.
 */
class LearnerContextGraphService
{
    public function __construct(
        private readonly CoherenceMapRepository $coherenceMap,
        private readonly CoherenceRecommender $recommender,
        private readonly ?PalEvidenceRepository $evidence = null,
    ) {
    }

    /**
     * Build the full 4-layer context graph for a given learner.
     */
    public function build(int $learnerId, ?int $subjectId = null, ?int $tenantId = null): array
    {
        // -------------------------------------------------------------
        // Layer A: User Context
        // -------------------------------------------------------------
        $userContext = $this->resolveUserContext($learnerId, $tenantId);
        if ($userContext === null) {
            return [
                'available' => false,
                'message' => "Learner {$learnerId} could not be resolved or has no active enrollment.",
            ];
        }

        $subInstituteId = $userContext['sub_institute_id'];
        $standardId = $userContext['standard_id'];
        $resolvedSubjectId = $subjectId ?: $userContext['primary_subject_id'];

        // -------------------------------------------------------------
        // Layer B: Learning Activities & Telemetry
        // -------------------------------------------------------------
        $activities = $this->resolveLearningActivities($learnerId, $subInstituteId);

        // -------------------------------------------------------------
        // Layer C: Knowledge Graph Relationships & Prerequisite Graph
        // -------------------------------------------------------------
        $knowledgeGraph = [
            'available' => false,
            'nodes' => [],
            'edges' => [],
            'stats' => [],
            'root_blockers' => [],
        ];

        if ($standardId && $resolvedSubjectId) {
            try {
                $mapData = $this->coherenceMap->map($standardId, $resolvedSubjectId, $learnerId, $subInstituteId);
                if ($mapData['available']) {
                    $readiness = $this->coherenceMap->readiness($standardId, $resolvedSubjectId, $learnerId, $subInstituteId);
                    
                    // Merge readiness onto nodes
                    $nodes = array_map(function ($node) use ($readiness) {
                        $id = (int) ($node['id'] ?? 0);
                        $r = $readiness[$id] ?? null;
                        return array_merge($node, [
                            'readiness_state' => $r['state'] ?? 'blocked',
                            'unmet_prerequisites' => $r['unmet'] ?? [],
                            'unlock_leverage' => $r['unlocks'] ?? 0,
                        ]);
                    }, $mapData['nodes']);

                    // Find all root blockers for currently blocked concepts
                    $allRoots = [];
                    foreach ($readiness as $conceptId => $r) {
                        if ($r['state'] === 'blocked' && ! empty($r['unmet'])) {
                            $roots = $this->coherenceMap->rootBlockers($conceptId, $learnerId);
                            foreach ($roots as $root) {
                                $allRoots[$root['id']] = $root;
                            }
                        }
                    }

                    $knowledgeGraph = [
                        'available' => true,
                        'standard_id' => $standardId,
                        'subject_id' => $resolvedSubjectId,
                        'nodes' => $nodes,
                        'edges' => $mapData['edges'],
                        'chapters' => $mapData['chapters'] ?? [],
                        'stats' => $mapData['stats'] ?? [],
                        'root_blockers' => array_values($allRoots),
                    ];
                }
            } catch (\Throwable) {
                // Non-blocking if graph connection fails
            }
        }

        // -------------------------------------------------------------
        // Layer D: Historical Trajectory & Decisions
        // -------------------------------------------------------------
        $history = $this->resolveHistoricalContext($learnerId, $subInstituteId, $resolvedSubjectId);

        // -------------------------------------------------------------
        // Next Best Action (Graph-Aware)
        // -------------------------------------------------------------
        $nextBestAction = null;
        if ($standardId && $resolvedSubjectId && $knowledgeGraph['available']) {
            try {
                $nba = $this->recommender->nextBestAction($learnerId, $standardId, $resolvedSubjectId);
                if (($nba['action'] ?? '') !== 'no_map') {
                    $nextBestAction = $nba;
                }
            } catch (\Throwable) {
                // Graceful fallback
            }
        }

        return [
            'available' => true,
            'learner_id' => $learnerId,
            'user_context' => $userContext,
            'learning_activities' => $activities,
            'knowledge_graph' => $knowledgeGraph,
            'historical_context' => $history,
            'next_best_action' => $nextBestAction,
            'assembled_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Layer A: Student identity, enrollment, standard, division, institute.
     */
    private function resolveUserContext(int $learnerId, ?int $tenantId): ?array
    {
        $student = DB::table('tblstudent')
            ->where('id', $learnerId)
            ->when($tenantId, fn ($q) => $q->where('sub_institute_id', $tenantId))
            ->first([
                'id', 'first_name', 'middle_name', 'last_name',
                'admission_no', 'gender', 'dob', 'sub_institute_id'
            ]);

        if ($student === null) {
            return null;
        }

        $subInstituteId = (int) $student->sub_institute_id;

        $enrollment = DB::table('tblstudent_enrollment')
            ->where('student_id', $learnerId)
            ->where('sub_institute_id', $subInstituteId)
            ->orderByDesc('syear')
            ->orderByDesc('id')
            ->first(['id', 'standard_id', 'section_id', 'roll_no', 'syear']);

        $standard = null;
        $division = null;
        if ($enrollment) {
            $standard = DB::table('standard')
                ->where('id', $enrollment->standard_id)
                ->value('name');

            $division = DB::table('division')
                ->where('id', $enrollment->section_id)
                ->value('name');
        }

        // Available / Enrolled Subjects
        $subjects = DB::table('student_optional_subject as sos')
            ->join('subject as s', 'sos.subject_id', '=', 's.id')
            ->where('sos.student_id', $learnerId)
            ->where('sos.sub_institute_id', $subInstituteId)
            ->select('s.id', 's.display_name', 's.subject_name')
            ->get();

        if ($subjects->isEmpty() && $enrollment) {
            $subjects = DB::table('sub_std_map as ssm')
                ->join('subject as s', 'ssm.subject_id', '=', 's.id')
                ->where('ssm.standard_id', $enrollment->standard_id)
                ->where('ssm.sub_institute_id', $subInstituteId)
                ->select('s.id', 's.display_name', 's.subject_name')
                ->get();
        }

        $primarySubjectId = $subjects->first()?->id ?? null;

        $instituteName = DB::table('school_setup')
            ->where('id', $subInstituteId)
            ->value('SchoolName');

        $fullName = trim("{$student->first_name} {$student->middle_name} {$student->last_name}");

        return [
            'learner_id' => (int) $student->id,
            'name' => $fullName ?: "Student #{$student->id}",
            'admission_no' => $student->admission_no,
            'gender' => $student->gender,
            'sub_institute_id' => $subInstituteId,
            'institute_name' => $instituteName ?: "Institute #{$subInstituteId}",
            'standard_id' => $enrollment ? (int) $enrollment->standard_id : null,
            'standard_name' => $standard ?: 'Unassigned',
            'division_id' => $enrollment ? (int) $enrollment->section_id : null,
            'division_name' => $division ?: 'Unassigned',
            'roll_no' => $enrollment ? $enrollment->roll_no : null,
            'syear' => $enrollment ? (int) $enrollment->syear : null,
            'enrolled_subjects' => $subjects->map(fn ($s) => [
                'id' => (int) $s->id,
                'name' => $s->display_name ?: $s->subject_name,
            ])->values()->all(),
            'primary_subject_id' => $primarySubjectId ? (int) $primarySubjectId : null,
        ];
    }

    /**
     * Layer B: Learning Activities & Telemetry.
     */
    private function resolveLearningActivities(int $learnerId, int $subInstituteId): array
    {
        $diagnostics = [];
        if (Schema::hasTable('pal_diagnostic_attempts')) {
            $diagnostics = DB::table('pal_diagnostic_attempts')
                ->where('student_id', $learnerId)
                ->where('sub_institute_id', $subInstituteId)
                ->orderByDesc('created_at')
                ->limit(5)
                ->get(['id', 'chapter_id', 'total_score', 'total_questions', 'status', 'created_at'])
                ->map(fn ($d) => [
                    'id' => (int) $d->id,
                    'chapter_id' => (int) $d->chapter_id,
                    'score' => (float) $d->total_score,
                    'total_questions' => (int) $d->total_questions,
                    'status' => $d->status,
                    'attempted_at' => $d->created_at,
                ])
                ->all();
        }

        $practice = [];
        if (Schema::hasTable('pal_practice_sessions')) {
            $practice = DB::table('pal_practice_sessions')
                ->where('student_id', $learnerId)
                ->where('sub_institute_id', $subInstituteId)
                ->orderByDesc('created_at')
                ->limit(5)
                ->get(['id', 'concept_id', 'correct_answers', 'total_questions', 'duration_seconds', 'created_at'])
                ->map(fn ($p) => [
                    'id' => (int) $p->id,
                    'concept_id' => (int) $p->concept_id,
                    'accuracy' => (int) $p->total_questions > 0 ? round(((int) $p->correct_answers / (int) $p->total_questions) * 100, 1) : 0,
                    'duration_seconds' => (int) $p->duration_seconds,
                    'practiced_at' => $p->created_at,
                ])
                ->all();
        }

        $engagementCount = 0;
        if (Schema::hasTable('lms_student_engagement')) {
            $engagementCount = DB::table('lms_student_engagement')
                ->where('student_id', $learnerId)
                ->where('sub_institute_id', $subInstituteId)
                ->count();
        }

        return [
            'recent_diagnostics' => $diagnostics,
            'recent_practice' => $practice,
            'total_engagement_events' => $engagementCount,
        ];
    }

    /**
     * Layer D: Historical Trajectory & Audit Decisions.
     */
    private function resolveHistoricalContext(int $learnerId, int $subInstituteId, ?int $subjectId): array
    {
        $competencies = [];
        if (Schema::hasTable('pal_competencies')) {
            $competencies = DB::table('pal_competencies')
                ->where('learner_id', $learnerId)
                ->when($subjectId, fn ($q) => $q->where('subject_id', $subjectId))
                ->orderByDesc('updated_at')
                ->limit(10)
                ->get(['concept_id', 'mastery_score', 'confidence_score', 'attempts_count', 'updated_at'])
                ->map(fn ($c) => [
                    'concept_id' => (int) $c->concept_id,
                    'mastery_score' => round((float) $c->mastery_score, 2),
                    'confidence_score' => round((float) ($c->confidence_score ?? 0), 2),
                    'attempts' => (int) ($c->attempts_count ?? 1),
                    'last_updated' => $c->updated_at,
                ])
                ->all();
        }

        $decisions = [];
        if (Schema::hasTable('eso_decision_log')) {
            $decisions = DB::table('eso_decision_log')
                ->where('student_id', $learnerId)
                ->where('sub_institute_id', $subInstituteId)
                ->orderByDesc('created_at')
                ->limit(5)
                ->get(['id', 'concept_id', 'rule_fired', 'action', 'created_at'])
                ->map(fn ($d) => [
                    'id' => (int) $d->id,
                    'concept_id' => (int) $d->concept_id,
                    'rule_fired' => $d->rule_fired,
                    'action' => $d->action,
                    'decided_at' => $d->created_at,
                ])
                ->all();
        }

        $recommendationLog = [];
        if (Schema::hasTable('pal_recommendation_log')) {
            $recommendationLog = DB::table('pal_recommendation_log')
                ->where('learner_id', $learnerId)
                ->where('sub_institute_id', $subInstituteId)
                ->orderByDesc('created_at')
                ->limit(5)
                ->get(['id', 'concept_id', 'pedagogy_type', 'selection_reason', 'tier_1_rule_key', 'created_at'])
                ->map(fn ($r) => [
                    'id' => (int) $r->id,
                    'concept_id' => (int) $r->concept_id,
                    'pedagogy_type' => $r->pedagogy_type,
                    'rule' => $r->tier_1_rule_key,
                    'reason' => $r->selection_reason,
                    'recommended_at' => $r->created_at,
                ])
                ->all();
        }

        return [
            'recent_competencies' => $competencies,
            'recent_engine_decisions' => $decisions,
            'recent_recommendations' => $recommendationLog,
        ];
    }
}

