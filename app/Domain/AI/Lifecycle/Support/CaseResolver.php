<?php

namespace App\Domain\AI\Lifecycle\Support;

use App\Domain\AI\Cases\CaseBuilder;
use App\Domain\AI\Lifecycle\StageContext;
use App\Domain\Ontology\EntityResolver;

/**
 * Which case a turn is about.
 *
 * A read route — "why is she at risk?", "what evidence supports this?" — has to find an
 * existing case before any of the reporting stages have anything to report. Resolution
 * is memoised on the context so the four stages that need it do not each run the same
 * lookup, and so the trace does not show one question resolving the same case four
 * times.
 *
 * The order of precedence matters and is deliberately narrow-to-broad: an explicit case
 * id beats a student named in this sentence, which beats a subject inherited from an
 * earlier turn. A caller who said which record they meant should never be second-guessed
 * by conversation memory.
 */
class CaseResolver
{
    public function __construct(
        private readonly CaseBuilder $cases,
        private readonly EntityResolver $entities,
    ) {
    }

    /**
     * @return array{case:array<string, mixed>, student_id:int, student_name:string}|null
     */
    public function resolve(StageContext $context): ?array
    {
        if ($context->has('resolved_case')) {
            return $context->get('resolved_case');
        }

        $resolved = $this->lookUp($context);
        $context->set('resolved_case', $resolved);

        if ($resolved !== null) {
            $context->focusCase = $resolved['case'];
        }

        return $resolved;
    }

    /**
     * @return array{case:array<string, mixed>, student_id:int, student_name:string}|null
     */
    private function lookUp(StageContext $context): ?array
    {
        // A case the agent just opened is already the subject of this turn.
        if ($context->focusCase !== null && isset($context->focusCase['case_id'])) {
            return $this->describe($context, $context->focusCase, (int) ($context->focusCase['student_id'] ?? 0));
        }

        $intent = $context->intent;
        $caseId = $intent?->slot('case_id');

        if ($caseId !== null) {
            $case = $this->cases->find((int) $caseId, $context->scope);

            if ($case !== null) {
                return $this->describe($context, $case, (int) ($case['subject_id'] ?? 0));
            }
        }

        $studentId = $this->subjectId($context);

        if ($studentId === null) {
            return null;
        }

        $caseType = $context->module->caseType;

        // Status is deliberately not filtered.
        //
        // This asked only for `open` cases, and a case moves to `in_progress` the moment
        // anyone acts on it — which is exactly when people start asking about it. Every
        // case in this estate is `in_progress`, so no follow-up could find its subject
        // and the whole chain answered "I need to know which student you mean" one turn
        // after naming them.
        //
        // A resolved or rejected case is still the answer to "why was she flagged?", so
        // those are found too; the ordering below simply prefers a live one when a
        // student has more than one.
        $candidates = [];

        foreach ($this->cases->list($context->scope, $caseType, CaseBuilder::ANY_STATUS, null, 100) as $candidate) {
            if ((int) ($candidate['subject_id'] ?? 0) === $studentId) {
                $candidates[] = $candidate;
            }
        }

        if ($candidates === []) {
            return null;
        }

        usort($candidates, static function (array $a, array $b) {
            $rank = static fn (array $case) => match ($case['status'] ?? '') {
                'open', 'in_progress' => 0,
                'resolved' => 1,
                default => 2,
            };

            return [$rank($a), -(int) ($b['id'] ?? 0)] <=> [$rank($b), -(int) ($a['id'] ?? 0)];
        });

        return $this->describe($context, $candidates[0], $studentId);
    }

    /**
     * The student this turn is about, from the sentence or from what MCP resolved.
     */
    private function subjectId(StageContext $context): ?int
    {
        $fromIntent = $context->intent?->slot('student_id');

        if ($fromIntent !== null) {
            return (int) $fromIntent;
        }

        $resolved = $context->get('resolved_student');

        if (is_array($resolved) && isset($resolved['student_id'])) {
            return (int) $resolved['student_id'];
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $case
     * @return array{case:array<string, mixed>, student_id:int, student_name:string}
     */
    private function describe(StageContext $context, array $case, int $studentId): array
    {
        $studentId = $studentId > 0
            ? $studentId
            : (int) ($case['subject_id'] ?? $case['student_id'] ?? 0);

        return [
            'case' => $case,
            'student_id' => $studentId,
            'student_name' => $this->nameFor($context, $case, $studentId),
        ];
    }

    /**
     * @param  array<string, mixed>  $case
     */
    private function nameFor(StageContext $context, array $case, int $studentId): string
    {
        // Prefer the name MCP hydrated: it is the one the rest of the answer will quote,
        // and a decision recorded against a person should name them consistently.
        $hydrated = $context->get('resolved_student');

        if (is_array($hydrated) && ! empty($hydrated['student_name'])) {
            return (string) $hydrated['student_name'];
        }

        foreach (['student_name', 'subject_label'] as $key) {
            if (! empty($case[$key])) {
                return (string) $case[$key];
            }
        }

        if ($studentId > 0) {
            $entity = $this->entities->resolveOne('student', $studentId, $context->scope);

            if (! empty($entity['label'])) {
                return (string) $entity['label'];
            }
        }

        return $context->intent?->slot('student_name') ?? ('Student #' . $studentId);
    }
}
