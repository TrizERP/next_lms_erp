<?php

namespace Tests\Unit\QuestionGeneration;

use App\Services\QuestionGeneration\QuestionFormatRegistry;
use Tests\TestCase;
use Tests\Unit\QuestionGeneration\Support\FakeCatalogue;
use Tests\Unit\QuestionGeneration\Support\TestableGenerationService;

/**
 * Several formats in one request: validation, the even split, per-format results,
 * partial failure and tenant isolation.
 *
 * NO DATABASE, no network: TestableGenerationService replaces the I/O edges and the
 * catalogue is FakeCatalogue::live() (the live lms_question_type_id / default_marks).
 * Formats run in REGISTRY order -- mcq, true_false, fill_blank, match_following,
 * assertion_reason, numerical, very_short, short, long, case_study -- which is the
 * order the queued model responses must be in.
 */
class QuestionGenerationMultiFormatTest extends TestCase
{
    private const MARKER = "EXAMPLE ROW (structure only; do not reuse its content)\n";

    private function registry(): QuestionFormatRegistry
    {
        return new QuestionFormatRegistry(FakeCatalogue::live());
    }

    private function service(): TestableGenerationService
    {
        $service = new TestableGenerationService($this->registry());
        $service->forInstitute(7);

        return $service;
    }

    private function input(array $codes, int $total, array $override = []): array
    {
        return $override + [
            'concept_id' => 123, 'subject_id' => 3, 'standard_id' => 8, 'chapter_id' => 11,
            'question_format_codes' => $codes, 'total_questions' => $total,
            'sub_institute_id' => 7, 'created_by' => 55,
        ];
    }

    private function mcqRow(int $i): array
    {
        return [
            'question_title' => "A diver descends 3 m every minute. What is the change in depth after {$i} minutes?",
            'description' => 'Tests multiplication at Apply.',
            'subconcept' => 'Sign rule',
            'points' => 1, 'multiple_answer' => 0, 'hint_text' => null,
            'learning_outcome' => ['Multiplies integers'],
            'answer' => [
                'v' => 'ans-2.0', 'question_type' => 'mcq', 'sub_type' => 'MCQ',
                'bloom_level' => 'Apply', 'dok_level' => 2, 'difficulty' => 'Medium',
                'options' => [
                    ['label' => 'A', 'text' => '+12 m', 'is_correct' => false],
                    ['label' => 'B', 'text' => '-12 m', 'is_correct' => true],
                    ['label' => 'C', 'text' => '-7 m', 'is_correct' => false],
                    ['label' => 'D', 'text' => '+7 m', 'is_correct' => false],
                ],
                'correct_option' => 'B', 'knowledge_refs' => ['k'],
            ],
        ];
    }

    /** $n distinct valid rows for a format. */
    private function rows(string $code, int $n): array
    {
        if ($code === 'mcq') {
            return array_map(fn ($i) => $this->mcqRow($i), range(1, $n));
        }

        $rules = $this->registry()->get($code)->constructionRules(5);
        $base = json_decode(trim(substr($rules, strpos($rules, self::MARKER) + strlen(self::MARKER))), true);
        $rows = [];
        for ($i = 1; $i <= $n; $i++) {
            $row = $base;
            $row['question_title'] .= " (item {$i})";
            if ($code === 'assertion_reason') {
                $row['answer']['assertion'] = "The product of {$i} and -3 is negative.";
            }
            if ($code === 'match_following') {
                $row['answer']['pairs'][0]['left'] = "Positive multiplied by positive {$i}";
            }
            if ($code === 'case_study') {
                $row['answer']['stimulus'] .= " Reading number {$i} is recorded.";
            }
            if ($code === 'mark_the_words') {
                $row['answer']['passage'] .= " Item {$i} ends here.";
            }
            $rows[] = $row;
        }

        return $rows;
    }

    private function queue(TestableGenerationService $service, string $code, int $n): void
    {
        $service->responses[] = ['content' => json_encode([
            'semantic_concept_key' => 'K', 'question_type' => $code, 'underfilled' => false, 'reason' => null,
            'rows' => $this->rows($code, $n),
        ])];
    }

    // ---- the split ---------------------------------------------------------

    public function test_the_total_is_split_evenly_with_the_remainder_to_the_first_formats(): void
    {
        $registry = $this->registry();
        $formats = fn (array $codes) => $registry->resolveMany($codes);

        $this->assertSame(['mcq' => 5, 'true_false' => 5, 'fill_blank' => 5], $registry->distribute(15, $formats(['mcq', 'true_false', 'fill_blank'])));
        $this->assertSame(['mcq' => 7, 'true_false' => 7, 'fill_blank' => 6], $registry->distribute(20, $formats(['mcq', 'true_false', 'fill_blank'])));
        $this->assertSame(['mcq' => 5, 'true_false' => 5, 'fill_blank' => 5, 'match_following' => 5], $registry->distribute(20, $formats(['mcq', 'true_false', 'fill_blank', 'match_following'])));
        $this->assertSame(['mcq' => 1, 'true_false' => 1], $registry->distribute(2, $formats(['mcq', 'true_false'])));
        $this->assertSame(['mcq' => 7], $registry->distribute(7, $formats(['mcq'])));
    }

    public function test_the_split_always_sums_to_the_total(): void
    {
        $registry = $this->registry();
        $all = $registry->resolveMany(array_column($registry->generatable(), 'code'));

        for ($k = 1; $k <= count($all); $k++) {
            for ($total = $k; $total <= 50; $total++) {
                $split = $registry->distribute($total, array_slice($all, 0, $k));
                $this->assertSame($total, array_sum($split), "{$total} over {$k}");
                $this->assertGreaterThanOrEqual(1, min($split));
                $this->assertLessThanOrEqual(1, max($split) - min($split), 'shares differ by at most one');
            }
        }
    }

    public function test_the_split_does_not_depend_on_click_order(): void
    {
        $registry = $this->registry();
        $a = $registry->resolveMany(['fill_blank', 'mcq', 'true_false']);
        $b = $registry->resolveMany(['true_false', 'fill_blank', 'mcq']);

        $this->assertSame(['mcq', 'true_false', 'fill_blank'], array_map(fn ($f) => $f->code(), $a));
        $this->assertSame($registry->distribute(20, $a), $registry->distribute(20, $b));
    }

    public function test_nothing_to_split_returns_nothing(): void
    {
        $this->assertSame([], $this->registry()->distribute(10, []));
        $this->assertSame([], $this->registry()->distribute(0, $this->registry()->resolveMany(['mcq'])));
    }

    // ---- resolving the selection ------------------------------------------

    public function test_every_code_meets_the_single_format_rules(): void
    {
        $registry = $this->registry();
        $errors = null;

        $this->assertSame([], $registry->resolveMany(['mcq', 'flash_card'], $errors), 'one unimplemented code rejects all');
        $this->assertCount(1, $errors);
        $this->assertStringContainsString('"flash_card" is not supported', $errors[0]);

        $this->assertSame([], $registry->resolveMany(['narrative'], $errors), 'the legacy alias is not a selectable format');
        $this->assertSame([], $registry->resolveMany(['mcq', 'antonym', 'flash_card'], $errors));
        $this->assertCount(2, $errors, 'every refusal is reported, not just the first');
    }

    public function test_an_implemented_but_uncatalogued_code_is_refused(): void
    {
        $registry = new QuestionFormatRegistry(new FakeCatalogue(['mcq' => FakeCatalogue::entry('mcq', 'Multiple Choice', 1, 1)]));
        $errors = null;

        $this->assertSame([], $registry->resolveMany(['mcq', 'true_false'], $errors));
        $this->assertStringContainsString('not in the question type catalogue', $errors[0]);
    }

    public function test_duplicates_collapse_and_case_is_ignored(): void
    {
        $formats = $this->registry()->resolveMany(['MCQ', ' mcq ', 'true_false', 'TRUE_FALSE']);

        $this->assertSame(['mcq', 'true_false'], array_map(fn ($f) => $f->code(), $formats));
    }

    public function test_an_empty_selection_is_refused(): void
    {
        $errors = null;

        $this->assertSame([], $this->registry()->resolveMany([], $errors));
        $this->assertSame(['Select at least one question format.'], $errors);
        $this->assertSame([], $this->registry()->resolveMany(['', '  '], $errors));
        $this->assertNotSame([], $errors);
    }

    // ---- generating several formats ---------------------------------------

    public function test_several_formats_each_get_their_share_and_keep_their_own_format(): void
    {
        $service = $this->service();
        // 7 over mcq, true_false, fill_blank => 3 / 2 / 2, in registry order.
        $this->queue($service, 'mcq', 3);
        $this->queue($service, 'true_false', 2);
        $this->queue($service, 'fill_blank', 2);

        $result = $service->generate($this->input(['fill_blank', 'mcq', 'true_false'], 7, ['question_type_id' => 99]));

        $this->assertTrue($result['status'], $result['message'] ?? '');
        $data = $result['data'];
        $this->assertSame(7, $data['requested']);
        $this->assertSame(7, $data['generated']);
        $this->assertSame(7, $data['inserted']);
        $this->assertSame(['mcq', 'true_false', 'fill_blank'], $data['question_format_codes']);
        $this->assertNull($data['question_format_code'], 'no single code when several were asked for');
        $this->assertCount(7, $data['questions']);
        $this->assertCount(3, $service->calls, 'one model call per format at these sizes');

        $this->assertSame(
            ['mcq' => 3, 'true_false' => 2, 'fill_blank' => 2],
            array_column($data['formats'], 'requested', 'question_format_code')
        );
        $this->assertSame(['mcq', 'mcq', 'mcq', 'true_false', 'true_false', 'fill_blank', 'fill_blank'], array_column($data['questions'], 'question_type'));

        // Each run was persisted under its own format and its own catalogue type id.
        $this->assertSame(['mcq', 'true_false', 'fill_blank'], array_map(fn ($p) => $p['ctx']['format_code'], $service->persisted));
        $this->assertSame([1, 1, 2], array_map(fn ($p) => $p['ctx']['question_type_id'], $service->persisted), 'type ids come from the catalogue; the client 99 is ignored');
    }

    public function test_each_formats_call_uses_that_formats_own_prompt(): void
    {
        $service = $this->service();
        $this->queue($service, 'mcq', 1);
        $this->queue($service, 'numerical', 1);

        $service->generate($this->input(['numerical', 'mcq'], 2));

        $this->assertStringContainsString('## CONSTRUCTION RULES (MCQ)', $service->calls[0]['user']);
        $this->assertStringNotContainsString('FORMAT CONTRACT', $service->calls[0]['user']);
        $this->assertStringContainsString('Every row you write is a `numerical` item', $service->calls[1]['user']);
    }

    public function test_every_available_format_at_once(): void
    {
        $service = $this->service();
        // drag_drop sources its own rows (a picture and a vision model), so it is covered
        // by DragDropFormatTest; this test is about the formats the text model writes.
        $codes = array_values(array_diff(array_column($this->registry()->generatable(), 'code'), ['drag_drop']));
        $this->assertCount(14, $codes);

        // 28 over 14 formats => 2 each (case_study batches 2, everything else fits one call).
        foreach ($codes as $code) {
            $this->queue($service, $code, 2);
        }

        $result = $service->generate($this->input($codes, 28));

        $this->assertTrue($result['status'], $result['message'] ?? '');
        $this->assertSame(28, $result['data']['generated']);
        $this->assertCount(14, $result['data']['formats']);
        $this->assertSame($codes, array_column($result['data']['formats'], 'question_format_code'));
        $this->assertSame($codes, array_map(fn ($p) => $p['ctx']['format_code'], $service->persisted));
        $this->assertFalse($result['data']['underfilled']);
    }

    public function test_a_single_format_in_an_array_behaves_like_the_single_code(): void
    {
        $viaArray = $this->service();
        $this->queue($viaArray, 'true_false', 3);
        $many = $viaArray->generate($this->input(['true_false'], 3));

        $viaCode = $this->service();
        $this->queue($viaCode, 'true_false', 3);
        $one = $viaCode->generate($this->input([], 3, ['question_format_codes' => null, 'question_format_code' => 'true_false']));

        $this->assertTrue($many['status']);
        $this->assertSame($one['data']['generated'], $many['data']['generated']);
        $this->assertSame($one['data']['question_type_id'], $many['data']['question_type_id']);
        $this->assertSame('true_false', $many['data']['question_format_code']);
        $this->assertSame($viaCode->calls[0]['user'], $viaArray->calls[0]['user'], 'the same prompt reaches the model');
    }

    // ---- refusals before any model call -----------------------------------

    public function test_one_bad_code_rejects_the_whole_request_before_any_model_call(): void
    {
        $service = $this->service();
        $result = $service->generate($this->input(['mcq', 'flash_card', 'true_false'], 6));

        $this->assertFalse($result['status']);
        $this->assertStringContainsString('"flash_card" is not supported', $result['message']);
        $this->assertSame([], $service->calls);
        $this->assertSame([], $service->persisted);
    }

    public function test_zero_formats_is_refused(): void
    {
        $service = $this->service();
        $result = $service->generate($this->input([], 5));

        $this->assertFalse($result['status']);
        $this->assertStringContainsString('Select at least one question format', $result['message']);
        $this->assertSame([], $service->calls);
    }

    public function test_fewer_questions_than_formats_is_refused(): void
    {
        $service = $this->service();
        $result = $service->generate($this->input(['mcq', 'true_false', 'fill_blank'], 2));

        $this->assertFalse($result['status']);
        $this->assertStringContainsString('at least the number of selected formats (3)', $result['message']);
        $this->assertSame([], $service->calls);
    }

    public function test_the_fifty_question_ceiling_still_applies(): void
    {
        $result = $this->service()->generate($this->input(['mcq', 'true_false'], 51));

        $this->assertFalse($result['status']);
        $this->assertStringContainsString('may not exceed 50', $result['message']);
    }

    public function test_a_custom_bloom_mix_needs_a_single_format(): void
    {
        $service = $this->service();
        $quota = [['level' => 'Understand', 'count' => 2]];

        $refused = $service->generate($this->input(['mcq', 'true_false'], 2, ['quota' => $quota]));
        $this->assertFalse($refused['status']);
        $this->assertStringContainsString('single question format', $refused['message']);
        $this->assertSame([], $service->calls);

        // One format keeps its custom mix.
        $this->queue($service, 'true_false', 2);
        $allowed = $service->generate($this->input(['true_false'], 2, ['quota' => $quota]));
        $this->assertTrue($allowed['status'], $allowed['message'] ?? '');
    }

    // ---- tenant isolation --------------------------------------------------

    public function test_a_concept_from_another_school_is_forbidden_for_every_format(): void
    {
        $service = $this->service();
        $service->conceptTenant = 8;

        $result = $service->generate($this->input(['mcq', 'true_false'], 4));

        $this->assertFalse($result['status']);
        $this->assertSame('forbidden', $result['code']);
        $this->assertSame([], $service->calls, 'no prompt is built for another school');
        $this->assertSame([], $service->persisted);
    }

    public function test_no_tenant_is_forbidden(): void
    {
        $result = $this->service()->generate($this->input(['mcq', 'true_false'], 4, ['sub_institute_id' => null]));

        $this->assertFalse($result['status']);
        $this->assertSame('forbidden', $result['code']);
    }

    // ---- partial failure ---------------------------------------------------

    public function test_a_failing_format_does_not_discard_the_others(): void
    {
        $service = $this->service();
        $this->queue($service, 'mcq', 2);
        $service->responses[] = ['error' => 'DeepSeek request failed: 500'];   // true_false
        $this->queue($service, 'fill_blank', 2);

        $result = $service->generate($this->input(['mcq', 'true_false', 'fill_blank'], 6));

        $this->assertTrue($result['status'], 'two formats produced questions');
        $data = $result['data'];
        $this->assertSame(6, $data['requested']);
        $this->assertSame(4, $data['generated']);
        $this->assertSame(4, $data['inserted']);
        $this->assertTrue($data['underfilled']);
        $this->assertStringContainsString('True / False could not be generated', $result['message']);

        $byCode = array_column($data['formats'], null, 'question_format_code');
        $this->assertTrue($byCode['mcq']['status']);
        $this->assertFalse($byCode['true_false']['status']);
        $this->assertStringContainsString('500', $byCode['true_false']['message']);
        $this->assertSame(0, $byCode['true_false']['inserted']);
        $this->assertSame(2, $byCode['true_false']['requested']);
        $this->assertTrue($byCode['fill_blank']['status']);

        $this->assertSame(['mcq', 'fill_blank'], array_map(fn ($p) => $p['ctx']['format_code'], $service->persisted));
    }

    public function test_when_every_format_fails_the_request_fails_and_says_why(): void
    {
        $service = $this->service();
        $service->responses = [['error' => 'timeout A'], ['content' => 'not json']];

        $result = $service->generate($this->input(['mcq', 'true_false'], 4));

        $this->assertFalse($result['status']);
        $this->assertStringContainsString('No question could be generated', $result['message']);
        $this->assertStringContainsString('Multiple Choice: Batch 1 failed: timeout A', $result['message']);
        $this->assertCount(2, $result['data']['formats']);
        $this->assertSame([], $service->persisted);
    }

    public function test_an_underfilled_format_is_reported_per_format(): void
    {
        $service = $this->service();
        $this->queue($service, 'mcq', 2);
        $this->queue($service, 'true_false', 1);   // asked for 2, model wrote 1

        $result = $service->generate($this->input(['mcq', 'true_false'], 4));

        $this->assertTrue($result['status']);
        $this->assertSame(3, $result['data']['generated']);
        $this->assertTrue($result['data']['underfilled']);
        $byCode = array_column($result['data']['formats'], null, 'question_format_code');
        $this->assertSame(2, $byCode['true_false']['requested']);
        $this->assertSame(1, $byCode['true_false']['generated']);
        $this->assertStringContainsString('Generated 3 of 4', $result['message']);
    }

    public function test_a_wrong_format_response_fails_only_that_format(): void
    {
        $service = $this->service();
        $this->queue($service, 'mcq', 2);
        $service->responses[] = ['content' => json_encode(['semantic_concept_key' => 'K', 'question_type' => 'fill_blank', 'rows' => $this->rows('fill_blank', 2)])];

        $result = $service->generate($this->input(['mcq', 'true_false'], 4));

        $this->assertTrue($result['status']);
        $byCode = array_column($result['data']['formats'], null, 'question_format_code');
        $this->assertTrue($byCode['mcq']['status']);
        $this->assertFalse($byCode['true_false']['status']);
        $this->assertStringContainsString('"fill_blank" but "true_false" was requested', $byCode['true_false']['message']);
    }

    // ---- aggregation -------------------------------------------------------

    public function test_counts_and_tokens_are_summed_across_formats(): void
    {
        $service = $this->service();
        $this->queue($service, 'mcq', 2);
        $this->queue($service, 'true_false', 2);

        $result = $service->generate($this->input(['mcq', 'true_false'], 4));

        $this->assertSame(20, $result['data']['input_tokens'], '10 per call, two calls');
        $this->assertSame(40, $result['data']['output_tokens']);
        $this->assertSame(2, $result['data']['batches']);
        $this->assertSame(0, $result['data']['skipped_invalid']);
        $this->assertSame(4, count($result['data']['question_ids']));
    }

    public function test_invalid_row_reasons_are_labelled_with_their_format(): void
    {
        $service = $this->service();
        $this->queue($service, 'mcq', 1);
        $bad = $this->rows('true_false', 2);
        $bad[1]['answer']['question_type'] = 'mcq';
        $service->responses[] = ['content' => json_encode(['semantic_concept_key' => 'K', 'question_type' => 'true_false', 'rows' => $bad])];

        $result = $service->generate($this->input(['mcq', 'true_false'], 3));

        $this->assertSame(1, $result['data']['skipped_invalid']);
        $this->assertStringStartsWith('true_false: row 1', $result['data']['invalid_reasons'][0]);
    }
}
