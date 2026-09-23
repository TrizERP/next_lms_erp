<?php

namespace App\Services\Mcp;

use App\Services\PAL\Coherence\CoherenceMapRepository;
use App\Services\PAL\ContentModel\ContentModelCoverageService;
use App\Services\PAL\Gamification\GamificationService;
use App\Services\PAL\Gamification\GamificationVisibility;
use Throwable;

/**
 * New PAL's three MCP reads, each delegating to the existing PAL service that already
 * backs the real `/pal/new/**` screens — nothing here re-derives a query those services
 * already own, and nothing here reaches the older `pal` module's tables.
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
}
