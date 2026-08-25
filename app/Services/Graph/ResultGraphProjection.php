<?php

namespace App\Services\Graph;

use App\Services\Graph\Contracts\GraphProjection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * One online-exam attempt in MariaDB -> the graph.
 *
 *   (:Student)-[:HAS_RESULT]->(:Result)-[:FOR_ASSESSMENT]->(:Assessment)
 *   (:Student)-[:ATTEMPTED]->(:Assessment)
 *
 * WHY THIS IS NOT A PLAIN COLUMN MAP. `lms_online_exam.student_id` is a PERSON
 * (tblstudent.id), but :Student is an ENROLMENT (tblstudent_enrollment.id).
 * Confirmed against the live data on 2026-08-21: of 147,882 attempts, 147,865
 * join tblstudent and only 12,653 join tblstudent_enrollment — so the column is
 * unambiguously the person.
 *
 * The k12 ingest script bridged that by matching on the `student_id` PROPERTY
 * of :Student (`MATCH (stu:Student {student_id: row.student_id})`), which fans
 * one attempt out across every enrolment the person holds — that is why the
 * live graph carries 517,021 HAS_RESULT edges for 147,886 :Result nodes, about
 * 3.5 per attempt. The outbox can only MERGE on a label's unique key, so the
 * fan-out is resolved here instead, producing exactly the same edges.
 *
 * ATTEMPTED is emitted directly rather than derived by the post-pass in
 * k12_cypher.txt ("12.linked student to Assessment"), so a new attempt is
 * complete the moment it drains instead of waiting for a batch job.
 */
class ResultGraphProjection implements GraphProjection
{
    public function __construct(private readonly GraphOutbox $outbox)
    {
    }

    public function tables(): array
    {
        return ['lms_online_exam'];
    }

    public function labels(): array
    {
        return ['Result'];
    }

    public function enqueue(string $table, int $recordId, array $hints = []): array
    {
        $exam = DB::table('lms_online_exam')
            ->where('id', $recordId)
            ->first(['id', 'student_id', 'question_paper_id', 'total_right', 'total_wrong', 'obtain_marks']);

        if (! $exam) {
            throw new RuntimeException("lms_online_exam row {$recordId} not found");
        }

        $personId = $this->intOrNull($exam->student_id);
        $paperId = $this->intOrNull($exam->question_paper_id);
        $marks = $this->intOrNull($exam->obtain_marks);

        $log = [$this->outbox->node('Result', $recordId, array_filter([
            'resultId'          => $recordId,
            'student_id'        => $personId,
            'question_paper_id' => $paperId,
            'total_right'       => $this->intOrNull($exam->total_right),
            'total_wrong'       => $this->intOrNull($exam->total_wrong),
            'obtain_marks'      => $marks,
            // The ingest script writes "Result:" + an INTEGER here, so the
            // property is a string built from the integer, not the raw value.
            'displayLabel'      => 'Result:' . (string) ($marks ?? 0),
        ], fn ($v) => $v !== null))];

        $queue = [];

        // (:Result)-[:FOR_ASSESSMENT]->(:Assessment)
        if ($paperId !== null) {
            $queue[] = $this->outbox->relationship(
                'Result', $recordId, 'FOR_ASSESSMENT', 'Assessment', $paperId
            );
        }

        // The person's enrolments each carry the attempt, matching the shape
        // the CSV ingest produced.
        foreach ($this->enrollmentsOf($personId) as $enrollmentId) {
            $queue[] = $this->outbox->relationship(
                'Student', $enrollmentId, 'HAS_RESULT', 'Result', $recordId
            );

            if ($paperId !== null) {
                $queue[] = $this->outbox->relationship(
                    'Student', $enrollmentId, 'ATTEMPTED', 'Assessment', $paperId
                );
            }
        }

        return ['log' => $log, 'queue' => $queue];
    }

    public function delete(string $table, int $recordId, array $hints = []): array
    {
        // DETACH DELETE on the :Result takes HAS_RESULT and FOR_ASSESSMENT with
        // it. ATTEMPTED is deliberately left alone: it records that the student
        // sat the assessment, which remains true even once one attempt row is
        // removed, and other attempts may still support it.
        return [
            'log'   => [$this->outbox->node('Result', $recordId, [], 'DELETE')],
            'queue' => [],
        ];
    }

    public function entityKey(string $table, int $recordId, array $hints = []): string
    {
        return 'result:' . $recordId;
    }

    public function enqueueNode(string $label, int $nodeId): array
    {
        if ($label !== 'Result' || ! DB::table('lms_online_exam')->where('id', $nodeId)->exists()) {
            return ['log' => [], 'queue' => []];
        }

        return $this->enqueue('lms_online_exam', $nodeId);
    }

    // -----------------------------------------------------------------------

    /** @return int[] every enrolment id belonging to this person */
    private function enrollmentsOf(?int $personId): array
    {
        if ($personId === null) {
            return [];
        }

        return DB::table('tblstudent_enrollment')
            ->where('student_id', $personId)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    private function intOrNull($value): ?int
    {
        return ($value === null || $value === '') ? null : (int) $value;
    }
}
