<?php

namespace Tests\Feature;

use App\Http\Controllers\api\ApiLmsCourseController;
use App\Http\Controllers\api\ApiQuestionBankController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class QuestionBankTypeResolutionTest extends TestCase
{
    /**
     * Requirement 3 & 4 & 7:
     * Question Bank displays all 10 actual question types for Chapter 41675 with exact counts:
     * MCQ: 5, Single Choice Set: 3, True / False: 3, Fill in the Blanks: 4, Flash Cards: 4,
     * Drag and Drop: 4, Drag the Words: 4, Mark the Words: 3, Match Following / Memory Game: 2,
     * Image Hotspots: 3. Total: 35.
     */
    public function test_question_bank_displays_all_10_actual_types_with_accurate_counts_for_chapter_41675(): void
    {
        $controller = new ApiLmsCourseController();
        $request = Request::create('/api/lms-question-bank', 'POST', [
            'chapter_id' => 41675,
        ]);

        $response = $controller->getQuestionBank($request);
        $json = json_decode($response->getContent(), true);

        $this->assertTrue($json['status']);
        $questions = $json['data'] ?? [];
        $this->assertCount(35, $questions);

        $countsByType = [];
        $countsByCode = [];
        foreach ($questions as $q) {
            $type = $q['question_type_raw'] ?? '';
            $code = $q['question_type_code'] ?? '';
            $countsByType[$type] = ($countsByType[$type] ?? 0) + 1;
            $countsByCode[$code] = ($countsByCode[$code] ?? 0) + 1;

            // Grading engine binary compatibility preserved
            $this->assertContains($q['question_type'], ['MCQ', 'Narrative']);
        }

        $expectedTypeCounts = [
            'Multiple Choice Question (MCQ)' => 5,
            'Single Choice Set' => 3,
            'True / False' => 3,
            'Fill in the Blanks' => 4,
            'Flash Cards' => 4,
            'Drag and Drop' => 4,
            'Drag the Words' => 4,
            'Mark the Words' => 3,
            'Match Following / Memory Game' => 2,
            'Image Hotspots' => 3,
        ];

        ksort($expectedTypeCounts);
        ksort($countsByType);
        $this->assertEquals($expectedTypeCounts, $countsByType);

        $expectedCodeCounts = [
            'mcq' => 5,
            'single_choice_set' => 3,
            'true_false' => 3,
            'fill_blank' => 4,
            'flash_card' => 4,
            'drag_drop' => 4,
            'drag_text' => 4,
            'mark_the_words' => 3,
            'match_following' => 2,
            'image_hotspots' => 3,
        ];

        ksort($expectedCodeCounts);
        ksort($countsByCode);
        $this->assertEquals($expectedCodeCounts, $countsByCode);
    }

    /**
     * Requirement 10:
     * Validation across all four Grade 4 EVS Water chapters (41675, 41676, 41677, 41678)
     * under tenant 341. Total: 140 questions (35 per chapter).
     */
    public function test_accurate_counts_across_all_four_grade_4_evs_water_chapters(): void
    {
        $chapterIds = [41675, 41676, 41677, 41678];
        $totalQuestionsAcross4Chapters = 0;
        $aggregatedTypeCounts = [];

        $controller = new ApiLmsCourseController();

        foreach ($chapterIds as $cid) {
            $request = Request::create('/api/lms-question-bank', 'POST', [
                'chapter_id' => $cid,
            ]);
            $response = $controller->getQuestionBank($request);
            $questions = json_decode($response->getContent(), true)['data'] ?? [];

            $this->assertCount(35, $questions, "Chapter $cid must contain exactly 35 questions");
            $totalQuestionsAcross4Chapters += count($questions);

            foreach ($questions as $q) {
                $type = $q['question_type_raw'];
                $aggregatedTypeCounts[$type] = ($aggregatedTypeCounts[$type] ?? 0) + 1;
            }
        }

        $this->assertSame(140, $totalQuestionsAcross4Chapters);

        $expectedAggregated = [
            'Multiple Choice Question (MCQ)' => 20,
            'Single Choice Set' => 12,
            'True / False' => 12,
            'Fill in the Blanks' => 16,
            'Flash Cards' => 16,
            'Drag and Drop' => 16,
            'Drag the Words' => 16,
            'Mark the Words' => 12,
            'Match Following / Memory Game' => 8,
            'Image Hotspots' => 12,
        ];

        ksort($expectedAggregated);
        ksort($aggregatedTypeCounts);
        $this->assertEquals($expectedAggregated, $aggregatedTypeCounts);
    }

    /**
     * Requirement 5:
     * Filtering each format returns only questions belonging to that format.
     */
    public function test_filtering_by_each_question_type_label_returns_only_that_format(): void
    {
        $controller = new ApiLmsCourseController();

        $formats = [
            'Multiple Choice Question (MCQ)' => 5,
            'Single Choice Set' => 3,
            'True / False' => 3,
            'Fill in the Blanks' => 4,
            'Flash Cards' => 4,
            'Drag and Drop' => 4,
            'Drag the Words' => 4,
            'Mark the Words' => 3,
            'Match Following / Memory Game' => 2,
            'Image Hotspots' => 3,
        ];

        foreach ($formats as $label => $expectedCount) {
            $request = Request::create('/api/lms-question-bank', 'POST', [
                'chapter_id' => 41675,
                'question_type' => $label,
            ]);
            $response = $controller->getQuestionBank($request);
            $questions = json_decode($response->getContent(), true)['data'] ?? [];

            $this->assertCount($expectedCount, $questions, "Filtering by label '$label' must return $expectedCount questions");

            foreach ($questions as $q) {
                $this->assertSame($label, $q['question_type_raw']);
            }
        }
    }

    /**
     * Filtering by machine code returns only questions belonging to that format.
     */
    public function test_filtering_by_each_machine_code_returns_only_that_format(): void
    {
        $controller = new ApiLmsCourseController();

        $codes = [
            'mcq' => ['expected_count' => 5, 'expected_label' => 'Multiple Choice Question (MCQ)'],
            'single_choice_set' => ['expected_count' => 3, 'expected_label' => 'Single Choice Set'],
            'true_false' => ['expected_count' => 3, 'expected_label' => 'True / False'],
            'fill_blank' => ['expected_count' => 4, 'expected_label' => 'Fill in the Blanks'],
            'flash_card' => ['expected_count' => 4, 'expected_label' => 'Flash Cards'],
            'drag_drop' => ['expected_count' => 4, 'expected_label' => 'Drag and Drop'],
            'drag_text' => ['expected_count' => 4, 'expected_label' => 'Drag the Words'],
            'mark_the_words' => ['expected_count' => 3, 'expected_label' => 'Mark the Words'],
            'match_following' => ['expected_count' => 2, 'expected_label' => 'Match Following / Memory Game'],
            'image_hotspots' => ['expected_count' => 3, 'expected_label' => 'Image Hotspots'],
        ];

        foreach ($codes as $code => $spec) {
            $request = Request::create('/api/lms-question-bank', 'POST', [
                'chapter_id' => 41675,
                'question_type_code' => $code,
            ]);
            $response = $controller->getQuestionBank($request);
            $questions = json_decode($response->getContent(), true)['data'] ?? [];

            $this->assertCount($spec['expected_count'], $questions, "Filtering by code '$code' must return {$spec['expected_count']} questions");

            foreach ($questions as $q) {
                $this->assertSame($spec['expected_label'], $q['question_type_raw']);
                $this->assertSame($code, $q['question_type_code']);
            }
        }
    }

    /**
     * Requirement 9:
     * Empty results and unknown types.
     */
    public function test_filtering_by_unknown_or_nonexistent_type_returns_empty_results(): void
    {
        $controller = new ApiLmsCourseController();

        $request = Request::create('/api/lms-question-bank', 'POST', [
            'chapter_id' => 41675,
            'question_type' => 'NonExistentFormat999',
        ]);
        $response = $controller->getQuestionBank($request);
        $json = json_decode($response->getContent(), true);

        $this->assertTrue($json['status']);
        $this->assertSame([], $json['data']);
    }

    /**
     * Requirement 9:
     * Chapter and tenant scoping.
     */
    public function test_chapter_and_tenant_scoping(): void
    {
        $controller = new ApiLmsCourseController();

        // Valid chapter
        $req1 = Request::create('/api/lms-question-bank', 'POST', ['chapter_id' => 41675]);
        $res1 = $controller->getQuestionBank($req1);
        $q1 = json_decode($res1->getContent(), true)['data'] ?? [];
        $this->assertCount(35, $q1);
        foreach ($q1 as $q) {
            $this->assertSame(41675, $q['chapter_id']);
        }

        // Non-existent chapter
        $req2 = Request::create('/api/lms-question-bank', 'POST', ['chapter_id' => 999999999]);
        $res2 = $controller->getQuestionBank($req2);
        $q2 = json_decode($res2->getContent(), true)['data'] ?? [];
        $this->assertEmpty($q2);
    }

    /**
     * Requirement 9:
     * Existing question types remaining backward-compatible.
     * Legacy multiple (1) and narrative (2) filter correctly.
     */
    public function test_backward_compatibility_with_numeric_and_legacy_types(): void
    {
        $controller = new ApiLmsCourseController();

        // Numeric 1 (MCQ group)
        $reqNum1 = Request::create('/api/lms-question-bank', 'POST', [
            'chapter_id' => 41675,
            'question_type' => 1,
        ]);
        $qNum1 = json_decode($controller->getQuestionBank($reqNum1)->getContent(), true)['data'] ?? [];
        $this->assertCount(18, $qNum1);
        foreach ($qNum1 as $q) {
            $this->assertSame('MCQ', $q['question_type']);
        }

        // Numeric 2 (Narrative group)
        $reqNum2 = Request::create('/api/lms-question-bank', 'POST', [
            'chapter_id' => 41675,
            'question_type' => 2,
        ]);
        $qNum2 = json_decode($controller->getQuestionBank($reqNum2)->getContent(), true)['data'] ?? [];
        $this->assertCount(17, $qNum2);
        foreach ($qNum2 as $q) {
            $this->assertSame('Narrative', $q['question_type']);
        }

        // String 'multiple'
        $reqStrMult = Request::create('/api/lms-question-bank', 'POST', [
            'chapter_id' => 41675,
            'question_type' => 'multiple',
        ]);
        $qStrMult = json_decode($controller->getQuestionBank($reqStrMult)->getContent(), true)['data'] ?? [];
        $this->assertCount(18, $qStrMult);

        // String 'narrative'
        $reqStrNarr = Request::create('/api/lms-question-bank', 'POST', [
            'chapter_id' => 41675,
            'question_type' => 'narrative',
        ]);
        $qStrNarr = json_decode($controller->getQuestionBank($reqStrNarr)->getContent(), true)['data'] ?? [];
        $this->assertCount(17, $qStrNarr);
    }

    /**
     * Requirement 1 & 7:
     * /api/question-bank/question-types endpoint returns accurate counts and friendly labels.
     */
    public function test_question_bank_question_types_options_endpoint_returns_friendly_labels_and_counts(): void
    {
        $controller = new ApiQuestionBankController();
        $request = Request::create('/api/question-bank/question-types', 'POST', [
            'chapter_id' => 41675,
        ]);

        $response = $controller->questionTypes($request);
        $json = json_decode($response->getContent(), true);

        $this->assertTrue($json['status']);
        $types = $json['data'] ?? [];

        $presentTypes = array_filter($types, fn ($t) => ($t['total'] ?? 0) > 0);
        $this->assertCount(10, $presentTypes);

        $typeMap = [];
        foreach ($presentTypes as $t) {
            $typeMap[$t['code']] = [
                'label' => $t['label'],
                'total' => $t['total'],
            ];
        }

        $expected = [
            'mcq' => ['label' => 'Multiple Choice Question (MCQ)', 'total' => 5],
            'single_choice_set' => ['label' => 'Single Choice Set', 'total' => 3],
            'true_false' => ['label' => 'True / False', 'total' => 3],
            'fill_blank' => ['label' => 'Fill in the Blanks', 'total' => 4],
            'flash_card' => ['label' => 'Flash Cards', 'total' => 4],
            'drag_drop' => ['label' => 'Drag and Drop', 'total' => 4],
            'drag_text' => ['label' => 'Drag the Words', 'total' => 4],
            'mark_the_words' => ['label' => 'Mark the Words', 'total' => 3],
            'match_following' => ['label' => 'Match Following / Memory Game', 'total' => 2],
            'image_hotspots' => ['label' => 'Image Hotspots', 'total' => 3],
        ];

        foreach ($expected as $code => $spec) {
            $this->assertArrayHasKey($code, $typeMap);
            $this->assertSame($spec['label'], $typeMap[$code]['label']);
            $this->assertSame($spec['total'], $typeMap[$code]['total']);
        }
    }

    /**
     * Requirement 8:
     * Preservation of Question IDs, question text, answers, and properties.
     */
    public function test_question_data_preserves_ids_content_and_grading_type(): void
    {
        $rows = DB::table('lms_question_master')
            ->where('chapter_id', 41675)
            ->get();

        $this->assertCount(35, $rows);

        foreach ($rows as $row) {
            $this->assertGreaterThan(0, $row->id);
            $this->assertNotEmpty($row->question_title);
            $this->assertNotEmpty($row->answer);
            $this->assertContains($row->question_type_id, [1, 2]);
        }
    }
}
