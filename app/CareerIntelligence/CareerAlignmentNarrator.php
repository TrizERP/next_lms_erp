<?php

namespace App\CareerIntelligence;

use App\Domain\AI\Support\ModelClient;
use Throwable;

/**
 * The Career Explorer / Career Alignment narrative — a short, bounded
 * explanation of why `CaiCoreService::evaluate()` returned what it returned,
 * following exactly the retrieval-then-generation split
 * `CoherenceMapController::explain()` established (zero LLM calls in
 * retrieval, one LLM call here, grounded in nothing but what `evaluate()`
 * actually returned) — the same split `CareerQuestNarrator` already applies
 * to the Career Quest snapshot.
 *
 * UNLIKE `CareerQuestNarrator`, this class does NOT call `CaiCoreService`
 * itself. `CareerQuestNarrator::narrate()` narrates a SECOND, independent
 * graph read (CAI-CORE) alongside `CareerQuestService::quest()`'s own
 * already-computed RIASEC/pathway data, so it owns that one graph call.
 * Here, `evaluate()`'s result IS the entire base payload
 * `lmsCounsellingController::careerAlignment()` already returns to the
 * client — there is no second data source — so the controller computes it
 * once (and is the one that must turn a graph outage into a clean response,
 * since a failed `evaluate()` call means there is no base payload either,
 * narrative or otherwise) and hands the already-retrieved facts in here
 * purely for narration. That is also why this class exposes only three
 * statuses (`ok` / `insufficient_evidence` / `model_unavailable`) rather than
 * the fourth (`graph_unavailable`) `explain()` and `CareerQuestNarrator`
 * carry: a graph failure here never reaches this class at all, since the
 * controller's own `evaluate()` call already failed first.
 *
 * "K-12 curriculum data" grounding: `evaluate()`'s own `break_point`
 * (current_stream / required_stream / missing_subjects / required_exams /
 * deadline) is already derived from the student's real class enrolment and
 * subject list (`ErpSubjectEnrolmentAdapter`, consulted inside `evaluate()`
 * itself) — that IS the K-12 curriculum data `lmsCounsellingController` has
 * access to for this endpoint; there is no concept-level (`lms_concept`)
 * linkage anywhere in the Career Explorer / Career Alignment flow to ground
 * on beyond that subject/stream granularity.
 */
class CareerAlignmentNarrator
{
    public function __construct(private readonly ModelClient $modelClient)
    {
    }

    /**
     * @param  array<string, mixed>  $alignment  CaiCoreService::evaluate()'s own
     *                                            return shape — already real,
     *                                            computed data; nothing here is
     *                                            invented by this class.
     * @return array{status:string, narrative:?string, message:?string}
     */
    public function narrate(array $alignment, int|string|null $subInstituteId): array
    {
        $alignmentKnown = ($alignment['alignment_status'] ?? 'INSUFFICIENT_DATA') !== 'INSUFFICIENT_DATA';

        if (! $alignmentKnown) {
            return $this->result('insufficient_evidence', null,
                $alignment['insufficient_data_reason']
                    ?? 'There is not enough career-alignment evidence yet to write an honest narrative.');
        }

        $client = $this->modelClient->forInstitute($subInstituteId);

        if (! $client->isConfigured()) {
            return $this->result('model_unavailable', null, 'No AI model is configured for this institute.');
        }

        try {
            // Same generous ceiling as CoherenceMapController::explain() and
            // CareerQuestNarrator, and for the same measured reason: the configured
            // model spends output-token budget on internal reasoning before any
            // visible text.
            $narrative = $client->chat(
                [
                    ['role' => 'system', 'content' => $this->systemPrompt()],
                    ['role' => 'user', 'content' => $this->userPrompt($alignment)],
                ],
                model: null,
                maxTokens: 2048,
                temperature: 0.3,
            );
        } catch (Throwable $exception) {
            return $this->result('model_unavailable', null, 'The narrative could not be generated.');
        }

        $narrative = trim((string) $narrative);

        if ($narrative === '') {
            return $this->result('model_unavailable', null, 'The narrative could not be generated.');
        }

        return $this->result('ok', $narrative, null);
    }

    /**
     * @return array{status:string, narrative:?string, message:?string}
     */
    private function result(string $status, ?string $narrative, ?string $message): array
    {
        return ['status' => $status, 'narrative' => $narrative, 'message' => $message];
    }

    private function systemPrompt(): string
    {
        return 'You are writing a short, plain-language explanation for a student browsing the Career '
            . 'Explorer about how the career they have in mind lines up with their current class subjects '
            . 'and stream. Use only the facts given in the message — never invent a required subject, exam, '
            . 'stream, deadline, or alignment status that is not present in the input. Keep the answer to '
            . '3-5 sentences, addressed directly to the student, plain and encouraging even when flagging a gap.';
    }

    /**
     * @param  array<string, mixed>  $alignment
     */
    private function userPrompt(array $alignment): string
    {
        $lines = [];

        $ambition = $alignment['stated_ambition'] ?? null;

        if (is_array($ambition) && ! empty($ambition['occupation_name'])) {
            $lines[] = sprintf('Stated career aspiration: %s.', $ambition['occupation_name']);
        }

        $lines[] = sprintf('Alignment check result: %s.', $alignment['alignment_status']);

        if (! empty($alignment['misalignment_codes']) && is_array($alignment['misalignment_codes'])) {
            $lines[] = 'Misalignment code(s): ' . implode(', ', $alignment['misalignment_codes']) . '.';
        }

        $breakPoint = $alignment['break_point'] ?? null;

        if (is_array($breakPoint)) {
            if (! empty($breakPoint['current_stream'])) {
                $lines[] = sprintf('Current stream: %s.', $breakPoint['current_stream']);
            }

            if (! empty($breakPoint['required_stream'])) {
                $lines[] = sprintf('Required stream for this career: %s.', $breakPoint['required_stream']);
            }

            if (! empty($breakPoint['missing_subjects']) && is_array($breakPoint['missing_subjects'])) {
                $lines[] = 'Required subject(s) the student is not currently enrolled in: '
                    . implode(', ', $breakPoint['missing_subjects']) . '.';
            }

            if (! empty($breakPoint['required_exams']) && is_array($breakPoint['required_exams'])) {
                $examNames = array_values(array_filter(array_map(
                    static fn ($exam) => is_array($exam) ? ($exam['name'] ?? null) : null,
                    $breakPoint['required_exams']
                )));

                if ($examNames !== []) {
                    $lines[] = 'Exam(s) this career requires: ' . implode(', ', $examNames) . '.';
                }
            }

            if (! empty($breakPoint['deadline_date'])) {
                $deadlineLine = sprintf('Stream-change deadline: %s.', $breakPoint['deadline_date']);

                if (isset($breakPoint['days_remaining']) && $breakPoint['days_remaining'] !== null) {
                    $deadlineLine .= sprintf(' (%d day(s) remaining).', (int) $breakPoint['days_remaining']);
                }

                $lines[] = $deadlineLine;
            }
        }

        $lines[] = 'Explain, in plain language for the student, what this alignment result means for their '
            . 'current subjects and stream — grounded only in the facts above.';

        return implode("\n", $lines);
    }
}
