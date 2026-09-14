<?php

namespace App\Services\Eso;

use App\Models\Eso\LearnerNodeState;
use App\Models\Eso\ResponseLog;
use App\Models\PAL\MisconceptionLibrary;
use Illuminate\Support\Facades\DB;

/**
 * Assembles the grounding and governance context for the AI Tutor.
 *
 * This service builds no content and answers no questions. It resolves what a
 * tutor is allowed to say to one learner about one concept, and what material
 * it is allowed to say it from:
 *
 *   - the concept's authored learning content, as the ONLY permitted source
 *     for an explanation (nothing here is generated, so a tutor grounded on it
 *     cannot invent a definition the curriculum does not contain);
 *   - the misconceptions ESO has actually flagged for this learner, so a
 *     correction addresses the error they made rather than a generic one;
 *   - the governance state that decides whether the tutor may explain directly
 *     or must stay Socratic.
 *
 * Why the governance lives HERE and not in a prompt: a rule written into a
 * system prompt is a request, and the model is free to ignore it under
 * pressure from a determined student. Resolved server-side and returned as
 * data, it is a fact the caller has to act on — and it is auditable, because
 * the attempt count it turns on is a row count, not a judgement.
 *
 * Every field is derived from existing ESO state. Nothing new is recorded.
 */
class AiTutorContextService
{
    public function __construct(
        private readonly EsoPolicyService $policy,
        private readonly EsoLearningContentResolver $learningContent
    ) {
    }

    /**
     * The tutor's context for one learner on one concept, or null when the
     * concept is not ESO-ready (no K/A/S nodes) and therefore has nothing to
     * ground a tutor on.
     *
     * @return array<string, mixed>|null
     */
    public function forConcept(int $learnerId, int $conceptId, int $subInstituteId): ?array
    {
        $concept = DB::table('lms_concept')
            ->where('id', $conceptId)
            ->first(['id', 'name', 'chapter_id']);

        if ($concept === null) {
            return null;
        }

        $nodes = $this->policy->esoNodesForConcept($conceptId, $subInstituteId);

        if ($nodes->isEmpty()) {
            return null;
        }

        $states = LearnerNodeState::forStudent($learnerId)
            ->whereIn('node_id', $nodes->pluck('id'))
            ->get()
            ->keyBy('node_id');

        return [
            'concept' => [
                'id' => (int) $concept->id,
                'name' => $concept->name,
                'chapter_id' => (int) $concept->chapter_id,
            ],
            'mastery' => [
                'bkt_estimate' => $this->policy->conceptMasteryEstimate($learnerId, $conceptId, $subInstituteId),
            ],
            'misconceptions' => $this->activeMisconceptions($states),
            'grounding' => $this->grounding($nodes, $states, $subInstituteId),
            'governance' => $this->governance($learnerId, $conceptId),
        ];
    }

    /**
     * The misconceptions ESO currently has flagged for this learner on this
     * concept — not the whole library for the concept.
     *
     * A tutor handed every misconception a concept can produce would guess at
     * which one applies. `active_misconception_id` is the one D3 actually
     * detected from a distractor this learner picked, so a correction built
     * on it addresses a mistake they demonstrably made.
     *
     * @param  \Illuminate\Support\Collection<int, LearnerNodeState>  $states
     * @return array<int, array<string, mixed>>
     */
    private function activeMisconceptions($states): array
    {
        $ids = $states
            ->pluck('active_misconception_id')
            ->filter()
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return [];
        }

        return MisconceptionLibrary::whereIn('id', $ids)
            ->get(['id', 'tag', 'description', 'error_pattern', 'corrective_action'])
            ->map(fn (MisconceptionLibrary $m) => [
                'id' => (int) $m->id,
                'tag' => $m->tag,
                'description' => $m->description,
                'error_pattern' => $m->error_pattern,
                // The authored remedy. A tutor should deliver THIS rather than
                // improvise a correction of its own.
                'corrective_action' => $m->corrective_action,
            ])
            ->all();
    }

    /**
     * The authored material the tutor may explain from, per node.
     *
     * Resolved through EsoLearningContentResolver — the same resolver ESO's own
     * teach step uses — so the tutor and the lesson cannot describe a concept
     * differently. A node whose content model has nothing contributes nothing
     * rather than a placeholder: an empty grounding list is a truthful signal
     * that there is nothing authored to teach from, and the caller can decline
     * to open a tutor session rather than invite the model to improvise.
     *
     * @param  \Illuminate\Support\Collection<int, mixed>  $nodes
     * @param  \Illuminate\Support\Collection<int, LearnerNodeState>  $states
     * @return array<int, array<string, mixed>>
     */
    private function grounding($nodes, $states, ?int $subInstituteId): array
    {
        $grounding = [];

        foreach ($nodes as $node) {
            $state = $states->get($node->id);

            if ($state === null) {
                // forNode() reads the learner's state to pick a variant. With
                // no state there is no attempt history to choose from, so the
                // node is skipped rather than served an arbitrary variant.
                continue;
            }

            $content = $this->learningContent->forNode($node, $state, $subInstituteId);

            if ($content === null) {
                continue;
            }

            $grounding[] = [
                'node_id' => (int) $node->id,
                'node_type' => $node->node_type,
                'label' => $node->label,
                'content' => $content,
            ];
        }

        return $grounding;
    }

    /**
     * What the tutor is permitted to do for this learner right now.
     *
     * Two clauses, and the difference between them is the point:
     *
     *  - `mode` gates EXPLANATION, and unlocks with genuine attempts. A learner
     *    who has tried twice unassisted has earned a direct explanation; one
     *    who has just opened the page gets Socratic questioning instead.
     *  - `assessment_answers` gates ANSWERS, and never unlocks. No number of
     *    attempts entitles a student to be told the answer to a scored item.
     *
     * Collapsing these into one flag would create the loophole the rule exists
     * to close: two throwaway attempts would buy the answer key.
     *
     * "Genuine" means unassisted — `hint_used = false`. An attempt a student
     * was walked through does not evidence an attempt to reach it themselves,
     * which is the thing being asked for.
     *
     * @return array<string, mixed>
     */
    private function governance(int $learnerId, int $conceptId): array
    {
        $required = (int) config('pal_content.ai_tutor.min_genuine_attempts_for_direct_answer', 2);

        $genuineAttempts = ResponseLog::forStudent($learnerId)
            ->forConcept($conceptId)
            ->where('hint_used', false)
            ->count();

        $directAllowed = $genuineAttempts >= $required;

        return [
            'mode' => $directAllowed ? 'direct_explanation_allowed' : 'socratic_only',
            'genuine_attempts' => $genuineAttempts,
            'attempts_required' => $required,
            'attempts_remaining' => max(0, $required - $genuineAttempts),
            // Unconditional, and deliberately not derived from anything above.
            'assessment_answers' => 'never',
            'rules' => [
                $directAllowed
                    ? 'The learner has logged enough unassisted attempts. A direct explanation of the concept is permitted, grounded only in the material under `grounding`.'
                    : sprintf(
                        'Socratic questioning only. Ask questions that lead the learner toward the answer; do not state it. %d more unassisted attempt(s) unlock direct explanation.',
                        max(0, $required - $genuineAttempts)
                    ),
                'Never reveal the answer to an assessment item, regardless of attempts logged.',
                'Explain only from `grounding`. If it is empty, say the material is not available rather than composing an explanation.',
                'Address a misconception under `misconceptions` using its `corrective_action`, not an improvised correction.',
            ],
        ];
    }
}
