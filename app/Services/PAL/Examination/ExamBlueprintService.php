<?php

namespace App\Services\PAL\Examination;

use App\Models\PAL\QuestionMetadata;
use App\Services\PAL\Content\PalVocabulary;
use Illuminate\Support\Facades\DB;

/**
 * Board exam blueprint feasibility.
 *
 * Answers one question: for this board's paper pattern, does the tagged item
 * pool actually contain enough board-compliant questions of each type — and if
 * not, exactly where is it short?
 *
 * WHAT THIS DELIBERATELY DOES NOT DO: emit a paper. Selecting the items would
 * require knowing which of them are reserved for the exam and therefore
 * withheld from PAL's adaptive practice, and that rule is an open decision
 * (tracker #6, "Needs Team Decision"). Generating a paper now would silently
 * settle it — and would risk serving a student an item they had already met as
 * practice. Counting the pool needs no such rule, so counting is what this
 * does. The generator that emits items belongs after #6, not before it.
 *
 * Read-only.
 */
class ExamBlueprintService
{
    /**
     * Can this blueprint be filled from the tagged pool, and where does it fall
     * short?
     *
     * @param  array<int, int>|null  $chapterIds  narrow the pool to a syllabus scope
     * @return array<string, mixed>
     */
    public function feasibility(
        int $subInstituteId,
        string $board,
        int $standardId,
        ?int $subjectId = null,
        string $blueprintKey = 'standard_theory_80',
        ?array $chapterIds = null
    ): array {
        $blueprint = config("pal_exam_blueprint.blueprints.{$board}.{$blueprintKey}");

        if ($blueprint === null) {
            return [
                'error' => 'unknown_blueprint',
                'message' => "No blueprint '{$blueprintKey}' is configured for board '{$board}'.",
                'available_blueprints' => $this->availableFor($board),
            ];
        }

        $scopeChapterIds = $chapterIds ?? $this->chaptersFor($subInstituteId, $standardId, $subjectId);

        $sections = [];
        $shortfallMarks = 0.0;
        $plannedMarks = 0.0;

        foreach ($blueprint['sections'] as $section) {
            $category = $section['blueprint_category'];

            // A category outside the registered vocabulary is a configuration
            // error, not an empty section. Reported as such so it cannot be
            // mistaken for "we have no items of this type".
            if (! PalVocabulary::isBlueprintCategory($category)) {
                $sections[] = [
                    'section' => $section['section'],
                    'blueprint_category' => $category,
                    'error' => 'unregistered_category',
                    'message' => "'{$category}' is not a registered blueprint category.",
                ];

                continue;
            }

            $required = (int) $section['count'];
            $marksEach = (float) $section['marks_each'];
            $sectionMarks = $required * $marksEach;
            $plannedMarks += $sectionMarks;

            $available = $this->poolFor($subInstituteId, $board, $category, $marksEach, $scopeChapterIds);
            $shortfall = max(0, $required - $available['count']);
            $shortfallMarks += $shortfall * $marksEach;

            $sections[] = [
                'section' => $section['section'],
                'blueprint_category' => $category,
                'required' => $required,
                'marks_each' => $marksEach,
                'section_marks' => $sectionMarks,
                'available' => $available['count'],
                'shortfall' => $shortfall,
                'feasible' => $shortfall === 0,
                // How much of the available pool carries derived psychometrics.
                // Not a blueprint requirement — a paper made of uncalibrated
                // items is still a valid paper — but it is what tells an author
                // whether the difficulty spread below means anything.
                'calibrated_available' => $available['calibrated'],
                'difficulty_available' => $available['by_difficulty'],
            ];
        }

        $realSections = array_values(array_filter($sections, fn ($s) => ! isset($s['error'])));

        return [
            'board' => $board,
            'blueprint_key' => $blueprintKey,
            'blueprint_label' => $blueprint['label'] ?? null,
            'scope' => [
                'standard_id' => $standardId,
                'subject_id' => $subjectId,
                'chapters' => count($scopeChapterIds),
            ],
            'marks' => [
                'blueprint_total' => (float) ($blueprint['total_marks'] ?? 0),
                // What the sections actually add up to. Reported separately
                // because a pattern whose sections do not sum to its stated
                // total is a configuration bug worth surfacing rather than
                // silently trusting either number.
                'sections_total' => $plannedMarks,
                'unfillable' => $shortfallMarks,
            ],
            'feasible' => $realSections !== [] && ! collect($realSections)->contains(fn ($s) => $s['shortfall'] > 0),
            'sections' => $sections,
            'difficulty_targets' => config('pal_exam_blueprint.difficulty_spread'),
        ];
    }

    /** @return array<int, string> */
    public function availableFor(string $board): array
    {
        return array_keys(config("pal_exam_blueprint.blueprints.{$board}", []));
    }

    /**
     * Board-compliant items of one category in scope.
     *
     * Fitness here is the BOARD half only — board set, category set, positive
     * marks — mirroring QuestionMetadata::isFitForBoardExam(). Calibration is
     * counted but never required: an uncalibrated item is a perfectly valid
     * exam question, and requiring psychometrics would exclude every newly
     * authored item from the paper it was written for (see #8).
     *
     * @param  array<int, int>  $chapterIds
     * @return array{count:int, calibrated:int, by_difficulty:array<int|string, int>}
     */
    private function poolFor(
        int $subInstituteId,
        string $board,
        string $category,
        float $marksEach,
        array $chapterIds
    ): array {
        $query = QuestionMetadata::query()
            ->forTenant($subInstituteId)
            ->forExamination()
            ->where('board', $board)
            ->where('blueprint_category', $category)
            // The item has to be worth what the slot is worth. A 5-mark long
            // answer cannot fill a 3-mark slot without changing the paper.
            ->where('marks', $marksEach);

        // An empty scope means "this standard has no chapters", not "no scope".
        // Skipping the filter counted the whole institute's item bank and could
        // report a paper as feasible from out-of-syllabus items.
        $query->whereIn('chapter_ref_id', $chapterIds ?: [0]);

        $rows = $query->get(['id', 'difficulty_1_to_5', 'discrimination_index', 'response_count', 'psychometrics_derived_at']);

        $byDifficulty = [];
        foreach ($rows as $row) {
            $key = $row->difficulty_1_to_5 === null ? 'unrated' : (int) $row->difficulty_1_to_5;
            $byDifficulty[$key] = ($byDifficulty[$key] ?? 0) + 1;
        }

        return [
            'count' => $rows->count(),
            'calibrated' => $rows->filter(fn ($r) => $r->isFitForPalDiagnostic())->count(),
            'by_difficulty' => $byDifficulty,
        ];
    }

    /** @return array<int, int> */
    private function chaptersFor(int $subInstituteId, int $standardId, ?int $subjectId): array
    {
        return DB::table('chapter_master')
            ->where('sub_institute_id', $subInstituteId)
            ->where('standard_id', $standardId)
            ->when($subjectId !== null, fn ($q) => $q->where('subject_id', $subjectId))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }
}
