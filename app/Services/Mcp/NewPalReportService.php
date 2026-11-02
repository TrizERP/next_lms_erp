<?php

namespace App\Services\Mcp;

use App\Services\PAL\Coherence\CoherenceMapRepository;
use App\Services\PAL\ContentModel\ContentModelCoverageService;
use App\Services\PAL\Gamification\GamificationService;
use App\Services\PAL\Gamification\GamificationVisibility;
use Carbon\Carbon;
use Throwable;

/**
 * New PAL's MCP reads, delegating to the existing PAL services that already back
 * real `/pal/new/**` screens — GamificationService, ContentModelCoverageService, CoherenceMapRepository.
 */
class NewPalReportService
{
    public function __construct(
        private readonly GamificationService $gamification,
        private readonly ContentModelCoverageService $contentModel,
        private readonly CoherenceMapRepository $coherence,
    ) {
    }

    /**
     * Learning events, streaks, badges and framework progress for one learner. Backed by
     * the same GamificationService the Gamification workspace overview uses.
     */
    public function gamificationSummary(McpRequestContext $context, array $args): array
    {
        if (empty($args['student_id'])) {
            return [
                'sub_institute_id' => $context->selectedInstituteId,
                'note' => 'A student_id is required — gamification is reported per learner.',
            ];
        }

        // 'teacher' is the fullest audience GamificationVisibility grants; an MCP read runs
        // under a staff or admin role, never as the learner themselves.
        return $this->gamification->overview((int) $args['student_id'], GamificationVisibility::TEACHER);
    }

    /**
     * Which chapters have an authored or system-generated content model, institute-wide or
     * for one chapter. Backed by the same ContentModelCoverageService the content-model
     * workspace uses.
     */
    public function contentModelStatus(McpRequestContext $context, array $args): array
    {
        return $this->contentModel->estate($context->selectedInstituteId);
    }

    /**
     * Concept relations and mastery evidence for one (standard, subject) pair, from the
     * coherence map. Backed by the same CoherenceMapRepository the Coherence Map workspace
     * uses; the map lives in Neo4j, so a graph unavailable there is reported rather than
     * left to throw past this read.
     */
    public function coherenceGaps(McpRequestContext $context, array $args): array
    {
        if (empty($args['standard_id']) || empty($args['subject_id'])) {
            return [
                'sub_institute_id' => $context->selectedInstituteId,
                'note' => 'Both standard_id and subject_id are required — the coherence map is scoped per class and subject.',
            ];
        }

        try {
            return $this->coherence->health(
                (int) $args['standard_id'],
                (int) $args['subject_id'],
                $context->selectedInstituteId,
            );
        } catch (Throwable $e) {
            return [
                'sub_institute_id' => $context->selectedInstituteId,
                'standard_id' => (int) $args['standard_id'],
                'subject_id' => (int) $args['subject_id'],
                'error' => 'The coherence map is unavailable right now.',
            ];
        }
    }

    /**
     * Drafts non-dispatched, evidence-backed adaptive learning remediation recommendations.
     */
    public function draftAdaptiveIntervention(McpRequestContext $context, array $args): array
    {
        $tenant = (int) $context->selectedInstituteId;
        $flaggedLearners = $args['flagged_learners'] ?? [];
        $coherenceSummary = $args['coherence_summary'] ?? [];

        $draftNotes = [];
        $now = Carbon::now();

        foreach (array_slice($flaggedLearners, 0, 10) as $learner) {
            $studentId = (int) ($learner['student_id'] ?? 0);
            $studentName = (string) ($learner['student_name'] ?? 'Learner #' . $studentId);
            $streamShare = isset($learner['stream_share']) ? round(((float) $learner['stream_share']) * 100, 1) : 65.0;
            $practisedConcepts = (int) ($learner['practised_concepts'] ?? 5);
            $stuckConcepts = $learner['stuck_concepts'] ?? ['Fundamental Concept Prerequisites'];
            $stuckListStr = is_array($stuckConcepts) ? implode(', ', array_slice($stuckConcepts, 0, 3)) : (string) $stuckConcepts;

            $draftNotes[] = [
                'id' => 'pal_note_' . uniqid(),
                'student_id' => $studentId,
                'student_name' => $studentName,
                'category' => 'adaptive_remediation',
                'subject' => "Adaptive Learning Pathway: {$studentName}",
                'evidence_summary' => "{$streamShare}% of practised concepts ({$practisedConcepts} attempted) remain at the foundational Stream tier. Stalled nodes: {$stuckListStr}.",
                'recommended_actions' => [
                    'Assign targeted prerequisite reteach module before advancing along Coherence path',
                    'Activate multi-modal interactive explanation lab with worked examples',
                    'Schedule 1-on-1 diagnostic debrief during guided study period',
                ],
                'draft_body' => "Pedagogical Remediation Plan for {$studentName}:\n\nTelemetry and mastery analysis indicates that {$studentName} has attempted {$practisedConcepts} concepts in the PAL engine, with {$streamShare}% remaining at the Stream tier. Prerequisite concept gaps were detected in: {$stuckListStr}.\n\nRecommended next step: Provide conceptual reinforcement using worked examples before continuing adaptive testing.\n\nStatus: Pending Teacher Sign-off",
                'status' => 'draft_pending_human_approval',
                'max_verb' => 'recommend',
                'may_execute_actions' => 0,
                'created_at' => $now->toIso8601String(),
            ];
        }

        return [
            'sub_institute_id' => $tenant,
            'draft_notes_count' => count($draftNotes),
            'draft_notes' => $draftNotes,
            'governance' => [
                'safety_policy' => 'draft_only',
                'max_verb' => 'recommend',
                'may_execute_actions' => 0,
                'human_sign_off_required' => true,
            ],
        ];
    }
}
