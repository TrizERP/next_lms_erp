<?php

namespace Tests\Unit\QuestionGeneration;

use App\Services\QuestionGeneration\QuestionFormatRegistry;
use Tests\TestCase;
use Tests\Unit\QuestionGeneration\Support\FakeCatalogue;
use Tests\Unit\QuestionGeneration\Support\StubFormat;
use Tests\Unit\QuestionGeneration\Support\StubRegistry;
use Tests\Unit\QuestionGeneration\Support\TestableGenerationService;

/**
 * The format seam in QuestionGenerationService: format resolution, the generic
 * prompt/validation path, server-side question_type_id, the dual-written format
 * code, tenant isolation and every failure mode the generator reports.
 *
 * NO DATABASE, no network: TestableGenerationService replaces the four I/O edges.
 * phpunit.xml points at the live shared vivek_erp, so nothing here may reach it.
 */
class QuestionGenerationFormatSeamTest extends TestCase
{
    private function service(array $catalogueRows = []): TestableGenerationService
    {
        $catalogue = FakeCatalogue::live();
        $entries = $catalogue->entries() + [
            'stub_form' => FakeCatalogue::entry('stub_form', 'Stub form', 7, null),
        ];
        foreach ($catalogueRows as $code => $row) {
            $entries[$code] = $row;
        }

        $service = new TestableGenerationService(new StubRegistry(new FakeCatalogue($entries)));
        $service->forInstitute(7);

        return $service;
    }

    private function input(array $override = []): array
    {
        return $override + [
            'concept_id' => 123,
            'subject_id' => 3,
            'standard_id' => 8,
            'chapter_id' => 11,
            'question_format_code' => 'stub_form',
            'total_questions' => 2,
            'sub_institute_id' => 7,
            'created_by' => 55,
        ];
    }

    private function stubRow(array $answer = [], array $row = []): array
    {
        return array_replace_recursive([
            'question_title' => 'Stub question about the concept?',
            'description' => 'Tests recall.',
            'subconcept' => 'Sign rule',
            'points' => 2,
            'multiple_answer' => 0,
            'hint_text' => null,
            'learning_outcome' => ['Multiplies integers'],
            'answer' => [
                'v' => 'ans-2.0', 'question_type' => 'stub_form',
                'bloom_level' => 'Understand', 'dok_level' => 1, 'difficulty' => 'Easy',
                'model_answer' => 'negative',
            ],
        ], $row, ['answer' => $answer]);
    }

    private function wrapper(array $rows, string $type = 'stub_form', array $extra = []): string
    {
        return json_encode($extra + [
            'semantic_concept_key' => 'K', 'question_type' => $type, 'underfilled' => false, 'reason' => null, 'rows' => $rows,
        ]);
    }

    // ---- format resolution ------------------------------------------------

    public function test_a_format_code_runs_the_generic_path_and_stores_the_catalogue_type_id(): void
    {
        $service = $this->service();
        $service->responses = [['content' => $this->wrapper([$this->stubRow(), $this->stubRow(['bloom_level' => 'Remember'], ['question_title' => 'Second stub question here?'])])]];

        // The client's own question_type_id (99) must be ignored.
        $result = $service->generate($this->input(['question_type_id' => 99]));

        $this->assertTrue($result['status'], $result['message'] ?? '');
        $ctx = $service->persisted[0]['ctx'];
        $this->assertSame(7, $ctx['question_type_id'], 'type id must come from the catalogue, not the client');
        $this->assertSame('stub_form', $ctx['format_code']);
        $this->assertTrue($ctx['scope_dedup']);
        $this->assertSame('stub_form', $result['data']['question_format_code']);
        $this->assertSame(7, $result['data']['question_type_id']);
        $this->assertSame('stub_form', $result['data']['questions'][0]['question_type']);
    }

    public function test_the_generic_prompt_names_the_one_format_and_carries_its_rules_and_schema(): void
    {
        $service = $this->service();
        $service->responses = [['content' => $this->wrapper([$this->stubRow(), $this->stubRow()])]];
        $service->generate($this->input());

        $user = $service->calls[0]['user'];
        $this->assertStringContainsString('TASK: Write 2 stub rows', $user);
        $this->assertStringContainsString('## FORMAT CONTRACT', $user);
        $this->assertStringContainsString('"stub_form"', $user);
        $this->assertStringContainsString('STUB RULES for 2 rows.', $user);
        $this->assertStringContainsString('{"stub":"schema"}', $user);
        $this->assertStringContainsString('## QUOTA TABLE (binding)', $user);
        $this->assertStringContainsString('## DEDUP CORPUS', $user);
        $this->assertSame(0.4, $service->calls[0]['opts']['temperature']);
    }

    public function test_the_mcq_alias_keeps_the_legacy_prompt_and_records_the_mcq_form(): void
    {
        $service = $this->service();
        $service->responses = [['content' => $this->wrapper([], 'mcq')]];
        $service->generate($this->input(['question_format_code' => null, 'question_type' => 'mcq']));

        $this->assertStringContainsString('## CONSTRUCTION RULES (MCQ)', $service->calls[0]['user']);
        $this->assertStringNotContainsString('## FORMAT CONTRACT', $service->calls[0]['user']);
        $this->assertSame([1], array_column($service->dedupCalls, 'questionTypeId'));
        $this->assertNull($service->dedupCalls[0]['formatCode'], 'legacy MCQ dedup stays scoped by type id');
    }

    public function test_the_legacy_narrative_alias_records_no_catalogue_form(): void
    {
        $service = $this->service();
        $service->responses = [['content' => $this->wrapper([], 'narrative')]];
        $service->generate($this->input(['question_format_code' => null, 'question_type' => 'narrative']));

        $this->assertStringContainsString('## CONSTRUCTION RULES (NARRATIVE)', $service->calls[0]['user']);
        $this->assertSame(2, $service->dedupCalls[0]['questionTypeId']);
    }

    public function test_dedup_for_a_format_run_is_scoped_to_the_format(): void
    {
        $service = $this->service();
        $service->corpus = ['An existing stem'];
        $service->responses = [['content' => $this->wrapper([$this->stubRow(), $this->stubRow()])]];
        $service->generate($this->input());

        $this->assertSame('stub_form', $service->dedupCalls[0]['formatCode']);
        $this->assertStringContainsString('- An existing stem', $service->calls[0]['user']);
    }

    // ---- request rejection ------------------------------------------------

    public function test_an_unknown_format_is_rejected_before_any_model_call(): void
    {
        $service = $this->service();
        $result = $service->generate($this->input(['question_format_code' => 'proof']));

        $this->assertFalse($result['status']);
        $this->assertStringContainsString('not supported', $result['message']);
        $this->assertSame([], $service->calls);
    }

    public function test_an_implemented_but_uncatalogued_format_is_rejected(): void
    {
        $catalogue = FakeCatalogue::live()->entries();
        $service = new TestableGenerationService(new StubRegistry(new FakeCatalogue($catalogue)));
        $service->forInstitute(7);

        $result = $service->generate($this->input()); // stub_form is not in this catalogue

        $this->assertFalse($result['status']);
        $this->assertStringContainsString('not in the question type catalogue', $result['message']);
    }

    public function test_the_legacy_narrative_cannot_be_requested_by_code(): void
    {
        $result = $this->service()->generate($this->input(['question_format_code' => 'narrative']));

        $this->assertFalse($result['status']);
        $this->assertStringContainsString('not supported', $result['message']);
    }

    public function test_a_bad_legacy_question_type_keeps_its_original_message(): void
    {
        $result = $this->service()->generate($this->input(['question_format_code' => null, 'question_type' => 'essay']));

        $this->assertSame('question_type must be "mcq" or "narrative".', $result['message']);
    }

    public function test_more_than_fifty_questions_are_refused(): void
    {
        $service = $this->service();
        $result = $service->generate($this->input(['total_questions' => 51]));

        $this->assertFalse($result['status']);
        $this->assertStringContainsString('may not exceed 50', $result['message']);
        $this->assertSame([], $service->calls);
    }

    public function test_an_explicit_quota_over_fifty_is_refused(): void
    {
        $service = $this->service();
        $result = $service->generate($this->input([
            'total_questions' => 10,
            'quota' => [['level' => 'Remember', 'count' => 30], ['level' => 'Understand', 'count' => 30]],
        ]));

        $this->assertFalse($result['status']);
        $this->assertStringContainsString('may not total more than 50', $result['message']);
    }

    public function test_an_explicit_quota_at_a_level_the_format_excludes_is_refused_not_dropped(): void
    {
        $result = $this->service()->generate($this->input([
            'quota' => [['level' => 'Create', 'count' => 2]],
        ]));

        $this->assertFalse($result['status']);
        $this->assertStringContainsString('cannot be written at the Create level', $result['message']);
    }

    // ---- tenant isolation -------------------------------------------------

    public function test_a_concept_owned_by_another_school_is_forbidden_whatever_the_format(): void
    {
        $service = $this->service();
        $service->conceptTenant = 8;
        $result = $service->generate($this->input());

        $this->assertFalse($result['status']);
        $this->assertSame('forbidden', $result['code']);
        $this->assertSame([], $service->calls, 'no prompt may be built for another school');
    }

    public function test_no_tenant_on_the_request_is_forbidden(): void
    {
        $service = $this->service();
        $result = $service->generate($this->input(['sub_institute_id' => null]));

        $this->assertFalse($result['status']);
        $this->assertSame('forbidden', $result['code']);
    }

    // ---- model output failures -------------------------------------------

    public function test_a_response_naming_another_format_is_refused_whole(): void
    {
        $service = $this->service();
        $service->responses = [['content' => $this->wrapper([$this->stubRow()], 'true_false')]];
        $result = $service->generate($this->input());

        $this->assertFalse($result['status']);
        $this->assertStringContainsString('"true_false" but "stub_form" was requested', $result['message']);
        $this->assertSame([], $service->persisted);
    }

    public function test_a_row_naming_another_format_is_skipped_and_counted(): void
    {
        $service = $this->service();
        $service->responses = [['content' => $this->wrapper([
            $this->stubRow(),
            $this->stubRow(['question_type' => 'true_false'], ['question_title' => 'Wrong form row here?']),
        ])]];
        $result = $service->generate($this->input());

        $this->assertTrue($result['status']);
        $this->assertSame(1, $result['data']['generated']);
        $this->assertSame(1, $result['data']['skipped_invalid']);
        $this->assertStringContainsString('answer.question_type must be "stub_form"', $result['data']['invalid_reasons'][0]);
    }

    public function test_a_row_at_a_disallowed_bloom_level_or_outside_the_marks_range_is_skipped(): void
    {
        $service = $this->service();
        $service->responses = [['content' => $this->wrapper([
            $this->stubRow(),
            $this->stubRow(['bloom_level' => 'Create'], ['question_title' => 'Create level row here?']),
            $this->stubRow([], ['points' => 9, 'question_title' => 'Too many marks row?']),
        ])]];
        $result = $service->generate($this->input(['total_questions' => 3]));

        $this->assertSame(1, $result['data']['generated']);
        $this->assertSame(2, $result['data']['skipped_invalid']);
    }

    public function test_a_format_rule_failure_skips_the_row(): void
    {
        $service = $this->service();
        $bad = $this->stubRow();
        unset($bad['answer']['model_answer']);
        $service->responses = [['content' => $this->wrapper([$this->stubRow(), $bad])]];
        $result = $service->generate($this->input());

        $this->assertSame(1, $result['data']['skipped_invalid']);
        $this->assertStringContainsString('model_answer is required', $result['data']['invalid_reasons'][0]);
    }

    public function test_missing_required_keys_skip_the_row(): void
    {
        $service = $this->service();
        $bad = $this->stubRow();
        unset($bad['learning_outcome']);
        $service->responses = [['content' => $this->wrapper([$this->stubRow(), $bad])]];
        $result = $service->generate($this->input());

        $this->assertSame(1, $result['data']['skipped_invalid']);
        $this->assertStringContainsString('missing column keys learning_outcome', $result['data']['invalid_reasons'][0]);
    }

    public function test_invalid_json_fails_the_batch(): void
    {
        $service = $this->service();
        $service->responses = [['content' => 'this is not json']];
        $result = $service->generate($this->input());

        $this->assertFalse($result['status']);
        $this->assertStringContainsString('Batch 1 failed', $result['message']);
        $this->assertSame([], $service->persisted);
    }

    public function test_a_response_without_rows_fails_the_batch(): void
    {
        $service = $this->service();
        $service->responses = [['content' => '{"question_type":"stub_form"}']];
        $result = $service->generate($this->input());

        $this->assertFalse($result['status']);
        $this->assertStringContainsString('missing "rows"', $result['message']);
    }

    public function test_a_batch_with_no_valid_rows_fails(): void
    {
        $service = $this->service();
        $bad = $this->stubRow(['bloom_level' => 'Create']);
        $service->responses = [['content' => $this->wrapper([$bad])]];
        $result = $service->generate($this->input());

        $this->assertFalse($result['status']);
        $this->assertStringContainsString('returned no valid rows', $result['message']);
    }

    public function test_a_provider_failure_fails_the_run_with_nothing_persisted(): void
    {
        $service = $this->service();
        $service->responses = [['error' => 'DeepSeek request failed: 402 Insufficient Balance']];
        $result = $service->generate($this->input());

        $this->assertFalse($result['status']);
        $this->assertStringContainsString('402', $result['message']);
        $this->assertSame([], $service->persisted);
    }

    public function test_a_truncated_response_is_reported_not_parsed(): void
    {
        $service = $this->service();
        $service->responses = [['content' => '{"rows":[', 'finish_reason' => 'length']];
        $result = $service->generate($this->input());

        $this->assertFalse($result['status']);
        $this->assertStringContainsString('truncated', $result['message']);
    }

    public function test_there_is_no_retry_one_failed_call_means_one_call(): void
    {
        $service = $this->service();
        $service->responses = [['error' => 'timeout'], ['content' => $this->wrapper([$this->stubRow()])]];
        $service->generate($this->input());

        $this->assertCount(1, $service->calls);
    }

    public function test_an_underfilled_run_says_so(): void
    {
        $service = $this->service();
        $service->responses = [['content' => $this->wrapper([$this->stubRow()])]];
        $result = $service->generate($this->input(['total_questions' => 2]));

        $this->assertTrue($result['status']);
        $this->assertSame(1, $result['data']['generated']);
        $this->assertSame(2, $result['data']['requested']);
        $this->assertTrue($result['data']['underfilled']);
        $this->assertStringContainsString('Generated 1 of 2', $result['message']);
    }

    public function test_valid_rows_are_prepared_by_the_format_before_persistence(): void
    {
        $service = $this->service();
        $service->responses = [['content' => $this->wrapper([$this->stubRow(), $this->stubRow()])]];
        $service->generate($this->input());

        $this->assertTrue($service->persisted[0]['resp']['rows'][0]['answer']['prepared']);
    }

    public function test_batches_follow_the_formats_batch_size(): void
    {
        $service = $this->service();
        // StubFormat batches 5 at a time: 7 questions => two calls.
        $service->responses = [
            ['content' => $this->wrapper(array_map(fn ($i) => $this->stubRow([], ['question_title' => "Stub question number {$i} here?"]), range(1, 5)))],
            ['content' => $this->wrapper(array_map(fn ($i) => $this->stubRow([], ['question_title' => "Stub question number {$i} here?"]), range(6, 7)))],
        ];
        $result = $service->generate($this->input(['total_questions' => 7]));

        $this->assertTrue($result['status'], $result['message'] ?? '');
        $this->assertCount(2, $service->calls);
        $this->assertSame(2, $result['data']['batches']);
        $this->assertSame(7, $result['data']['generated']);
    }

    // ---- the stored row ---------------------------------------------------

    private function ctx(array $override = []): array
    {
        return $override + [
            'concept_id' => 123, 'concept_name' => 'Multiplication', 'question_type_id' => 2,
            'sub_institute_id' => 7, 'created_by' => 55, 'standard_id' => 8, 'subject_id' => 3,
            'chapter_id' => 11, 'grade_id' => 8, 'semantic_concept_key' => 'K_0123',
            'format_code' => 'stub_form', 'scope_dedup' => true,
        ];
    }

    public function test_the_stored_row_dual_writes_the_format_and_the_generator_known_metadata(): void
    {
        [$insert, $answer] = $this->service()->masterRow($this->stubRow(), ['semantic_concept_key' => 'K'], $this->ctx(), ['model' => 'm']);

        $this->assertSame('stub_form', $insert['question_format_code']);
        $this->assertSame('stub_form', $answer['item_form']);
        $this->assertSame($insert['question_format_code'], $answer['item_form'], 'both copies must be the same code');
        $this->assertSame(2, $insert['question_type_id']);
        $this->assertSame('Understand', $insert['g_bloom']);
        $this->assertSame('Easy', $insert['g_difficulty']);
        $this->assertSame(1, $insert['g_dok']);
        $this->assertArrayNotHasKey('g_qtype_code', $insert, 'the tagger owns g_qtype_code');
        $this->assertArrayNotHasKey('g_content_hash', $insert, 'g_content_hash is left alone');
        $this->assertArrayNotHasKey('h5p_content_type', $insert, 'no native h5p link is written');
        $this->assertSame(7, $insert['sub_institute_id']);
        $this->assertSame(55, $insert['created_by']);
        $this->assertSame(123, $insert['concept_id']);
        $this->assertSame(11, $insert['chapter_id']);
        $this->assertSame(3, $insert['subject_id']);
        $this->assertSame(8, $insert['standard_id']);
    }

    public function test_the_legacy_narrative_row_records_no_format_code_but_still_gets_g_metadata(): void
    {
        [$insert, $answer] = $this->service()->masterRow($this->stubRow(), [], $this->ctx(['format_code' => null, 'scope_dedup' => false]), []);

        $this->assertArrayNotHasKey('question_format_code', $insert);
        $this->assertArrayNotHasKey('item_form', $answer);
        $this->assertSame('Understand', $insert['g_bloom']);
    }

    public function test_g_values_outside_their_columns_vocabulary_are_not_written(): void
    {
        $row = $this->stubRow(['bloom_level' => 'Understand', 'difficulty' => 'Extremely hard', 'dok_level' => 9]);
        [$insert] = $this->service()->masterRow($row, [], $this->ctx(), []);

        $this->assertArrayNotHasKey('g_difficulty', $insert, 'varchar(8) would reject it and fail the whole transaction');
        $this->assertArrayNotHasKey('g_dok', $insert);
        $this->assertSame('Understand', $insert['g_bloom']);
    }

    public function test_columns_that_do_not_exist_on_an_older_schema_are_not_written(): void
    {
        $service = $this->service();
        $service->hasColumns = false;
        [$insert] = $service->masterRow($this->stubRow(), [], $this->ctx(), []);

        foreach (['question_format_code', 'g_bloom', 'g_difficulty', 'g_dok'] as $column) {
            $this->assertArrayNotHasKey($column, $insert);
        }
    }

    // ---- format quota -----------------------------------------------------

    public function test_auto_quota_spreads_over_the_allowed_levels_only_and_sums_to_the_total(): void
    {
        $quota = $this->service()->formatQuota(new StubFormat(), 10, [], 2);

        $this->assertSame(10, array_sum(array_column($quota, 'count')));
        foreach ($quota as $row) {
            $this->assertContains($row['level'], ['Remember', 'Understand', 'Apply']);
            $this->assertSame(2, $row['points']);
        }
    }

    public function test_explicit_marks_are_clamped_into_the_formats_range(): void
    {
        $quota = $this->service()->formatQuota(new StubFormat(), 2, ['quota' => [
            ['level' => 'Remember', 'count' => 1, 'points' => 99],
            ['level' => 'Apply', 'count' => 1, 'points' => 0],
        ]]);

        $this->assertSame([3, 1], array_column($quota, 'points'));
    }

    public function test_generatable_formats_exclude_legacy_and_uncatalogued_ones(): void
    {
        $codes = array_column($this->service()->formats()->generatable(), 'code');

        $this->assertContains('mcq', $codes);
        $this->assertContains('stub_form', $codes);
        $this->assertNotContains('narrative', $codes);
    }

    public function test_the_real_registry_is_a_quota_registry_subclass_relationship(): void
    {
        $this->assertInstanceOf(QuestionFormatRegistry::class, new StubRegistry());
        $this->assertInstanceOf(StubFormat::class, (new StubRegistry())->get('stub_form'));
    }
}
