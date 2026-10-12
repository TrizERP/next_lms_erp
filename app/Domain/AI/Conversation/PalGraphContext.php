<?php

namespace App\Domain\AI\Conversation;

use App\Services\Mcp\McpRequestContext;
use App\Services\PAL\Coherence\GraphRagRetriever;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Grounds a conversational-AI turn in the PAL coherence graph, when — and
 * only when — the turn is plainly about one learner and one concept.
 *
 * This is deliberately NOT a pipeline stage. `GeneralAnswerService` and
 * `ConversationalNarrator` are the only two places in the twelve-stage
 * lifecycle that call an LLM on free text, and both already have a
 * governance discipline of their own — GeneralAnswerService never asserts a
 * school fact, and the Narrator discards any reply citing a number its input
 * data did not contain. This class's whole job is to hand both of them one
 * more honest fact source: the same retrieval `GraphRagRetriever` already
 * does for `CoherenceMapController::explain()`.
 *
 * There is no PAL module registered in this pipeline (checked
 * `config('ai.module_keywords')` and `App\Domain\AI\Lifecycle\Modules\*` —
 * neither mentions "concept", "misconception" or "coherence"), so a
 * PAL-shaped question has nowhere else to be answered from today; it falls
 * straight through to `GeneralAnswerService` as a question "the ERP has no
 * data for" unless this class intercepts it there.
 *
 * Zero LLM calls in this class, matching GraphRagRetriever's own contract. A
 * graph outage, a missing concept match, or a turn with no pinned student all
 * resolve to the same thing — null — which both callers already treat as
 * "nothing to add", never as an error.
 *
 * TENANT ISOLATION: every lookup is scoped to `$scope->selectedInstituteId`,
 * resolved by `McpContextHydrator` from the caller's JWT before this class
 * ever runs — never from request input. The pinned student id IS
 * caller-supplied (the same `payload.student_id` convention
 * `GenerativeAiStage::pinnedIds()` already uses elsewhere in this pipeline),
 * so it is verified against that tenant's own enrolment before being used
 * for anything — the same thing `CoherenceMapController::scopeForLearner()`
 * does for the one existing GraphRagRetriever caller.
 *
 * SCOPE, STATED HONESTLY: this only grounds a turn that arrives with a
 * `payload.student_id` already pinned (set by a console button, never typed
 * free text) AND names a concept whose exact title appears in the question.
 * A freeform "why is Rahul stuck on photosynthesis?" with no pinned id will
 * not match today — resolving a name in running text to one student record
 * is a different, harder problem this class does not attempt. That is a
 * deliberately narrow, cheap guard: the check costs nothing on the
 * overwhelming majority of turns that have no pinned student at all, and it
 * never risks grounding a reply in the wrong learner's data by guessing.
 */
class PalGraphContext
{
    /** Below this, a concept name is too short/common to match on safely ("set", "add", "mix"). */
    private const MIN_CONCEPT_NAME_LENGTH = 4;

    public function __construct(private readonly GraphRagRetriever $graphRag)
    {
    }

    /**
     * @return array{concept_id:int, concept_name:string, student_id:int, context:array<string,mixed>}|null
     */
    public function forQuestion(string $question, McpRequestContext $scope, ?int $pinnedStudentId): ?array
    {
        $question = trim($question);

        if ($question === '' || $pinnedStudentId === null || $pinnedStudentId <= 0) {
            return null;
        }

        $tenant = $scope->selectedInstituteId;

        try {
            if (! $this->studentBelongsToTenant($pinnedStudentId, $tenant)) {
                return null;
            }

            $concept = $this->matchConcept($question, $tenant);

            if ($concept === null) {
                return null;
            }

            $context = $this->graphRag->contextFor($pinnedStudentId, (int) $concept->id, $tenant);
        } catch (Throwable) {
            // A graph outage, or anything else retrieval can throw, degrades to
            // "nothing to ground this turn with" rather than surfacing as an
            // error — the exact fallback both callers already apply to a null
            // return, and the same contract explain() keeps for its own
            // GraphRagRetriever call.
            return null;
        }

        return [
            'concept_id' => (int) $concept->id,
            'concept_name' => (string) $concept->name,
            'student_id' => $pinnedStudentId,
            'context' => $context,
        ];
    }

    /**
     * The grounding text both callers append to their own prompt — written
     * once here so the "use only these facts" discipline cannot drift between
     * the two call sites.
     *
     * @param  array{concept_id:int, concept_name:string, student_id:int, context:array<string,mixed>}  $graphContext
     */
    public function groundingPrompt(array $graphContext): string
    {
        $context = $graphContext['context'];

        $lines = [sprintf(
            'GRAPH FACTS about "%s" for student #%d, retrieved from the knowledge graph. Use only these '
            . 'facts for anything you say about this concept — never invent a prerequisite, mastery figure, '
            . 'or misconception that is not listed here. If nothing below indicates a problem, say plainly '
            . 'that no blocker was found rather than guessing one.',
            $graphContext['concept_name'],
            $graphContext['student_id']
        )];

        if ($context['blocked']) {
            $lines[] = 'Unmastered root prerequisite(s), weakest first:';

            foreach ($context['root_blockers'] as $blocker) {
                $lines[] = sprintf(
                    '- "%s" (mastery %.2f, needs %.2f)',
                    $blocker['name'] ?? 'unnamed concept',
                    (float) ($blocker['mastery'] ?? 0),
                    (float) ($blocker['gate'] ?? 0.7)
                );
            }
        } else {
            $lines[] = 'No unmastered prerequisite was found for this concept in the knowledge graph.';
        }

        if ($context['misconceptions'] !== []) {
            $lines[] = 'Known misconceptions linked to this concept:';

            foreach ($context['misconceptions'] as $misconception) {
                $lines[] = '- ' . ($misconception['description'] ?: $misconception['error_pattern'] ?: $misconception['tag']);
            }
        }

        if ($context['assessing_questions'] !== []) {
            $lines[] = sprintf('%d question(s) in the bank assess this concept directly.', count($context['assessing_questions']));
        }

        return implode("\n", $lines);
    }

    private function studentBelongsToTenant(int $studentId, int $tenant): bool
    {
        return DB::table('tblstudent_enrollment')
            ->where('student_id', $studentId)
            ->where('sub_institute_id', $tenant)
            ->exists();
    }

    /**
     * The longest concept name for this tenant whose exact title appears
     * verbatim in the question. Pushed into SQL rather than pulled into PHP
     * and scanned there — a tenant can hold several thousand concepts (6,944
     * for sub_institute 1, measured live), and this runs only on turns that
     * already have a pinned student, which is the actual cost guard.
     */
    private function matchConcept(string $question, int $tenant): ?object
    {
        return DB::table('lms_concept')
            ->where('sub_institute_id', $tenant)
            ->whereRaw('CHAR_LENGTH(name) >= ?', [self::MIN_CONCEPT_NAME_LENGTH])
            ->whereRaw("? LIKE CONCAT('%', name, '%')", [$question])
            ->orderByRaw('CHAR_LENGTH(name) DESC')
            ->select('id', 'name')
            ->first();
    }
}
