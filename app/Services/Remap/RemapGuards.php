<?php

namespace App\Services\Remap;

use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Invariant assertions used by every write path.
 *
 * These exist because the apply commands run unattended. Each one is a
 * cheap check that turns a silent data corruption into a loud abort.
 */
class RemapGuards
{
    private array $scope;

    public function __construct(?array $scope = null)
    {
        $this->scope = $scope ?? config('remap.scope');
    }

    /**
     * A crosswalk decision is still safe to apply.
     *
     * Re-read at apply time rather than trusted from when the decision
     * was made: the chapter set could have been re-seeded again between
     * build-crosswalk and apply.
     */
    public function assertApplicable(object $decision): void
    {
        if (empty($decision->new_chapter_id)) {
            throw new RuntimeException("crosswalk {$decision->id}: no new_chapter_id");
        }

        if (!in_array($decision->review_status, ['approved_auto', 'low_confidence'], true)) {
            throw new RuntimeException(
                "crosswalk {$decision->id}: review_status '{$decision->review_status}' is not applicable"
            );
        }

        $chapter = DB::table('chapter_master')->where('id', $decision->new_chapter_id)->first();

        if (!$chapter) {
            throw new RuntimeException(
                "crosswalk {$decision->id}: target chapter {$decision->new_chapter_id} no longer exists"
            );
        }

        if ((int) $chapter->standard_id !== (int) $this->scope['standard_id']) {
            throw new RuntimeException(
                "crosswalk {$decision->id}: target chapter {$chapter->id} is standard {$chapter->standard_id}, expected {$this->scope['standard_id']}"
            );
        }

        if ((int) $chapter->sub_institute_id !== (int) $this->scope['sub_institute_id']) {
            throw new RuntimeException(
                "crosswalk {$decision->id}: target chapter {$chapter->id} belongs to tenant {$chapter->sub_institute_id}"
            );
        }

        // The recorded subject must still match the chapter's own
        // subject, otherwise the cross-subject decision has drifted.
        if ((int) $chapter->subject_id !== (int) $decision->new_subject_id) {
            throw new RuntimeException(
                "crosswalk {$decision->id}: recorded subject {$decision->new_subject_id} != chapter subject {$chapter->subject_id}"
            );
        }
    }

    /**
     * Every row about to be written belongs to the tenant and standard
     * we are allowed to touch. Guards against a query change silently
     * widening scope into tenant 1000.
     */
    public function assertRowsInScope(string $table, array $ids): void
    {
        if (!$ids) {
            return;
        }

        $bad = DB::table($table)
            ->whereIn('id', $ids)
            ->where(function ($q) {
                $q->where('sub_institute_id', '<>', $this->scope['sub_institute_id'])
                  ->orWhere('standard_id', '<>', $this->scope['standard_id']);
            })
            ->count();

        if ($bad > 0) {
            throw new RuntimeException("{$table}: {$bad} row(s) outside tenant/standard scope in this batch");
        }
    }

    /**
     * A concept may only be attached to a question sitting on that
     * concept's own chapter, subject, standard and tenant.
     *
     * This invariant holds at 100% for the already-correct Science set
     * and must still hold afterwards.
     */
    public function assertConceptMatchesQuestion(object $concept, object $question): void
    {
        foreach (['chapter_id', 'subject_id', 'standard_id', 'sub_institute_id'] as $column) {
            if ((int) $concept->{$column} !== (int) $question->{$column}) {
                throw new RuntimeException(
                    "concept {$concept->id} {$column}={$concept->{$column}} does not match question {$question->id} {$column}={$question->{$column}}"
                );
            }
        }
    }

    /**
     * Confirm the assumptions this pipeline is built on still describe
     * the database. Run by remap:preflight before anything else.
     *
     * @return array<int, string> problems found; empty means all clear
     */
    public function schemaAssertions(): array
    {
        $problems = [];

        $required = [
            'lms_question_master'  => ['id', 'subject_id', 'chapter_id', 'concept_id', 'standard_id', 'sub_institute_id', 'deleted_at'],
            'content_master'       => ['id', 'subject_id', 'chapter_id', 'concept_id', 'standard_id', 'sub_institute_id', 'show_hide'],
            'lms_teacher_resource' => ['id', 'subject_id', 'chapter_id', 'standard_id', 'sub_institute_id', 'status'],
            'chapter_master'       => ['id', 'subject_id', 'standard_id', 'chapter_name', 'key_concepts', 'sort_order'],
            'lms_concept'          => ['id', 'name', 'chapter_id', 'subject_id', 'standard_id', 'sub_institute_id', 'concept_show_hide'],
        ];

        foreach ($required as $table => $columns) {
            if (!\Schema::hasTable($table)) {
                $problems[] = "missing table: {$table}";
                continue;
            }

            foreach ($columns as $column) {
                if (!\Schema::hasColumn($table, $column)) {
                    $problems[] = "missing column: {$table}.{$column}";
                }
            }
        }

        // Teacher resources must NOT gain a concept. Asserting the
        // column's absence means a future schema change cannot silently
        // open that door.
        if (\Schema::hasTable('lms_teacher_resource') && \Schema::hasColumn('lms_teacher_resource', 'concept_id')) {
            $problems[] = 'lms_teacher_resource.concept_id now exists; CR/TW must never receive concepts -- review before running';
        }

        return $problems;
    }
}
