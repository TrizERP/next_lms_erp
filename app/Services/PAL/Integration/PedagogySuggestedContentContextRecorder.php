<?php

namespace App\Services\PAL\Integration;

use App\Domain\AI\Support\AiAuditLogger;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Records a Suggested Content recommendation as an existing `ai_audit_logs` event.
 *
 * PedagogySuggestedContentService already computes weak concepts, their root-cause
 * blockers (CoherenceMapRepository::rootBlockers(), Neo4j-backed) and the content
 * actually shown, then returns all of it straight to the frontend. Nothing durable
 * remembered why a given suggestion was made — this class does not change that
 * computation, it only writes one existing-shape audit event per call, referencing
 * concept/content ids rather than copying them, so "what was suggested, for which
 * concept, on what evidence" is reconstructable later instead of visible only in
 * that one HTTP response.
 *
 * Deliberately NOT routed through RecommendationDrafter/CaseBuilder/DecisionGate:
 * those model consequential actions a teacher must approve before anything happens
 * (see CreateAcademicInterventionAction's own docblock — reached only after a named
 * teacher's approval is already in `ai_decisions`). A routine content suggestion
 * shown to a student on page load is not that kind of decision — forcing it through
 * an approval queue would either stall the feature or flood teachers with noise for
 * every student's every page view.
 */
class PedagogySuggestedContentContextRecorder
{
    public function __construct(private readonly AiAuditLogger $audit)
    {
    }

    /**
     * @param  array<int, array<string, mixed>>  $weakConcepts  Each item already carries
     *         concept_id, concept_name, mastery_level, root_cause, graph_content —
     *         see PedagogySuggestedContentService::getSuggestions().
     * @param  array<string, array<int, array<string, mixed>>>  $contentData  The
     *         categorised content as returned to the caller (remediation/practice/
     *         enrichment/misconception_content).
     */
    public function record(
        int $learnerId,
        int $subInstituteId,
        ?int $chapterId,
        ?int $subjectId,
        ?int $standardId,
        string $studentLevel,
        array $weakConcepts,
        array $contentData
    ): void {
        try {
            $evidence = array_map(function (array $item) {
                return [
                    'concept_id' => $item['concept_id'] ?? null,
                    'concept_name' => $item['concept_name'] ?? null,
                    'mastery_level' => $item['mastery_level'] ?? null,
                    'root_cause_concept_ids' => $this->extractIds($item['root_cause'] ?? []),
                    'graph_content_ids' => $this->extractIds($item['graph_content'] ?? []),
                ];
            }, $weakConcepts);

            $usedKnowledgeGraph = ! empty(array_filter($evidence, function (array $item) {
                return $item['root_cause_concept_ids'] !== [] || $item['graph_content_ids'] !== [];
            }));

            $contentCounts = array_map('count', $contentData);

            $this->audit->record(AiAuditLogger::RECOMMENDATION_DRAFTED, null, [
                'actor_type' => 'system',
                'subject_entity_key' => 'student',
                'subject_id' => $learnerId,
                'related_type' => 'pal_suggested_content',
                'message' => sprintf(
                    'Suggested content assembled for chapter %s: %d weak concept(s), %d item(s) across %d categories.',
                    $chapterId ?? 'n/a',
                    count($weakConcepts),
                    array_sum($contentCounts),
                    count(array_filter($contentCounts))
                ),
                'payload' => [
                    'chapter_id' => $chapterId,
                    'subject_id' => $subjectId,
                    'standard_id' => $standardId,
                    'student_level' => $studentLevel,
                    'weak_concepts' => $evidence,
                    'content_counts' => $contentCounts,
                ],
                'knowledge_graph_used' => $usedKnowledgeGraph,
                'sub_institute_id' => $subInstituteId,
            ]);
        } catch (Throwable $exception) {
            // Observability must never break the live suggested-content path — the same
            // defensive stance PedagogySuggestedContentService already takes around its
            // own CoherenceMapRepository calls.
            Log::info('[pedagogy-suggested-content] context recording failed', [
                'learner_id' => $learnerId,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows  Each row an `id`-keyed
     *         reference (rootBlockers() and contentFor() both shape their rows
     *         this way).
     * @return array<int, int>
     */
    private function extractIds(array $rows): array
    {
        return array_values(array_filter(array_map(function ($row) {
            return is_array($row) && isset($row['id']) ? (int) $row['id'] : null;
        }, $rows)));
    }
}
