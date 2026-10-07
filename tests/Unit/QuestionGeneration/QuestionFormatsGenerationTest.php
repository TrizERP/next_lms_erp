<?php

namespace Tests\Unit\QuestionGeneration;

use App\Services\QuestionGeneration\QuestionFormatRegistry;
use Tests\TestCase;
use Tests\Unit\QuestionGeneration\Support\FakeCatalogue;
use Tests\Unit\QuestionGeneration\Support\TestableGenerationService;

/**
 * Each real format through QuestionGenerationService::generate(), end to end:
 * request -> prompt -> (fake) model -> parse -> validate -> prepare -> stored row.
 *
 * NO DATABASE, no network: TestableGenerationService replaces the I/O edges and
 * the catalogue is FakeCatalogue::live() -- the real lms_question_type_id and
 * default_marks the live question_type_catalog holds.
 */
class QuestionFormatsGenerationTest extends TestCase
{
    private const MARKER = "EXAMPLE ROW (structure only; do not reuse its content)\n";

    /** format code => [expected lms_question_type_id, expected answer_master rows] */
    private const EXPECTED = [
        'true_false'       => [1, 2],
        'fill_blank'       => [2, 0],
        'drag_text'        => [1, 0],
        'mark_the_words'   => [1, 0],
        'match_following'  => [2, 0],
        'assertion_reason' => [1, 4],
        'numerical'        => [2, 0],
        'very_short'       => [2, 0],
        'short'            => [2, 0],
        'long'             => [2, 0],
        'case_study'       => [2, 0],
        'proof'            => [2, 0],
        'construction'     => [2, 0],
    ];

    public static function formatCodes(): array
    {
        return array_map(fn ($code) => [$code], array_keys(self::EXPECTED));
    }

    private function service(): TestableGenerationService
    {
        $service = new TestableGenerationService(new QuestionFormatRegistry(FakeCatalogue::live()));
        $service->forInstitute(7);

        return $service;
    }

    private function input(string $code, array $override = []): array
    {
        return $override + [
            'concept_id' => 123, 'subject_id' => 3, 'standard_id' => 8, 'chapter_id' => 11,
            'question_format_code' => $code, 'total_questions' => 2,
            'sub_institute_id' => 7, 'created_by' => 55,
        ];
    }

    private function example(string $code): array
    {
        $rules = $this->service()->formats()->get($code)->constructionRules(5);
        $row = json_decode(trim(substr($rules, strpos($rules, self::MARKER) + strlen(self::MARKER))), true);
        $this->assertIsArray($row, json_last_error_msg());

        return $row;
    }

    /** Two distinct valid rows for a format. */
    private function twoRows(string $code): array
    {
        $first = $this->example($code);
        $second = $this->example($code);
        $second['question_title'] = $second['question_title'] . ' (second item)';
        $second['subconcept'] = 'A second knowledge string';

        // Formats whose stem is rebuilt from structured parts must differ there instead.
        if ($code === 'assertion_reason') {
            $second['answer']['assertion'] = 'The product of 6 and -2 is negative.';
        }
        if ($code === 'match_following') {
            $second['answer']['pairs'][0]['left'] = 'Positive multiplied by positive';
        }
        if ($code === 'case_study') {
            $second['answer']['stimulus'] .= ' The coach also records the time.';
        }
        if ($code === 'mark_the_words') {
            $second['answer']['passage'] .= ' A second reading follows here.';
        }

        return [$first, $second];
    }

    private function wrapper(string $code, array $rows): string
    {
        return json_encode([
            'semantic_concept_key' => 'K_0123', 'question_type' => $code,
            'underfilled' => false, 'reason' => null, 'rows' => $rows,
        ]);
    }

    /**
     * @dataProvider formatCodes
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('formatCodes')]
    public function test_a_valid_response_is_stored_under_the_selected_format(string $code): void
    {
        [$typeId, $optionRows] = self::EXPECTED[$code];
        $service = $this->service();
        $service->responses = [['content' => $this->wrapper($code, $this->twoRows($code))]];

        $result = $service->generate($this->input($code));

        $this->assertTrue($result['status'], $result['message'] ?? '');
        $this->assertSame(2, $result['data']['generated']);
        $this->assertSame($code, $result['data']['question_format_code']);
        $this->assertSame($typeId, $result['data']['question_type_id'], 'type id comes from the catalogue');

        $ctx = $service->persisted[0]['ctx'];
        $this->assertSame($code, $ctx['format_code']);
        $this->assertSame($typeId, $ctx['question_type_id']);
        $this->assertTrue($ctx['scope_dedup']);

        $row = $service->persisted[0]['resp']['rows'][0];
        [$insert, $answer] = $service->masterRow($row, ['semantic_concept_key' => 'K_0123'], $ctx, $service->persisted[0]['meta']);

        // The dual write: both copies, same code.
        $this->assertSame($code, $insert['question_format_code']);
        $this->assertSame($code, $answer['item_form']);
        $this->assertSame($typeId, $insert['question_type_id']);
        $this->assertSame(7, $insert['sub_institute_id']);
        $this->assertSame(55, $insert['created_by']);
        $this->assertSame(11, $insert['chapter_id']);
        $this->assertSame(123, $insert['concept_id']);
        $this->assertNotNull($insert['g_bloom']);
        $this->assertNotNull($insert['g_difficulty']);
        $this->assertNotNull($insert['g_dok']);
        foreach (['g_qtype_code', 'g_content_hash', 'h5p_content_type', 'h5p_content_id'] as $untouched) {
            $this->assertArrayNotHasKey($untouched, $insert);
        }

        $this->assertCount($optionRows, $service->answerRows(9001, $answer, $ctx), 'answer_master rows');
        $this->assertNotSame('', $service->flowCategory($answer, $row));
        $this->assertSame($code, $service->persisted[0]['meta']['format_code']);
    }

    /**
     * @dataProvider formatCodes
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('formatCodes')]
    public function test_the_prompt_names_only_the_selected_format(string $code): void
    {
        $service = $this->service();
        $service->responses = [['content' => $this->wrapper($code, $this->twoRows($code))]];
        $service->generate($this->input($code));

        $user = $service->calls[0]['user'];
        $this->assertStringContainsString("Every row you write is a `{$code}` item and nothing else", $user);
        $this->assertStringContainsString('"const": "' . $code . '"', $user, 'schema pins the format');
        $this->assertStringContainsString('## CONSTRUCTION RULES', $user);
        $this->assertStringContainsString('## RESPONSE SCHEMA', $user);
        $this->assertStringContainsString('EXAMPLE ROW', $user);
        $this->assertStringNotContainsString('## CONSTRUCTION RULES (MCQ)', $user);
        $this->assertStringNotContainsString('## CONSTRUCTION RULES (NARRATIVE)', $user);

        // Every format shares the one system prompt; only the user prompt differs.
        $this->assertStringContainsString('assessment item writer for a K-12 CBSE curriculum question bank', $service->calls[0]['system']);
    }

    /**
     * @dataProvider formatCodes
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('formatCodes')]
    public function test_a_response_for_another_format_is_refused(string $code): void
    {
        $service = $this->service();
        $other = $code === 'true_false' ? 'fill_blank' : 'true_false';
        $service->responses = [['content' => $this->wrapper($other, $this->twoRows($code))]];

        $result = $service->generate($this->input($code));

        $this->assertFalse($result['status']);
        $this->assertStringContainsString("\"{$other}\" but \"{$code}\" was requested", $result['message']);
        $this->assertSame([], $service->persisted);
    }

    /**
     * @dataProvider formatCodes
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('formatCodes')]
    public function test_one_bad_row_is_skipped_and_the_rest_are_kept(string $code): void
    {
        $service = $this->service();
        [$good, $bad] = $this->twoRows($code);
        $bad['answer']['question_type'] = 'mcq'; // claims to be another form
        $service->responses = [['content' => $this->wrapper($code, [$good, $bad])]];

        $result = $service->generate($this->input($code));

        $this->assertTrue($result['status'], $result['message'] ?? '');
        $this->assertSame(1, $result['data']['generated']);
        $this->assertSame(1, $result['data']['skipped_invalid']);
        $this->assertTrue($result['data']['underfilled']);
        $this->assertStringContainsString('Generated 1 of 2', $result['message']);
    }

    /**
     * @dataProvider formatCodes
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('formatCodes')]
    public function test_invalid_json_and_provider_failure_persist_nothing(string $code): void
    {
        $service = $this->service();
        $service->responses = [['content' => '{"rows": [ {']];
        $this->assertFalse($service->generate($this->input($code))['status']);

        $service = $this->service();
        $service->responses = [['error' => 'DeepSeek request failed: 500']];
        $result = $service->generate($this->input($code));

        $this->assertFalse($result['status']);
        $this->assertStringContainsString('500', $result['message']);
        $this->assertSame([], $service->persisted);
    }

    /**
     * @dataProvider formatCodes
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('formatCodes')]
    public function test_a_missing_required_field_skips_the_row(string $code): void
    {
        $service = $this->service();
        [$good, $bad] = $this->twoRows($code);
        unset($bad['answer']['explanation']);
        // A key the shared checks require, removed from a copy.
        $alsoBad = $this->twoRows($code)[1];
        unset($alsoBad['learning_outcome']);
        $service->responses = [['content' => $this->wrapper($code, [$good, $alsoBad])]];

        $result = $service->generate($this->input($code));

        $this->assertSame(1, $result['data']['generated']);
        $this->assertStringContainsString('missing column keys learning_outcome', $result['data']['invalid_reasons'][0]);
    }

    /**
     * @dataProvider formatCodes
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('formatCodes')]
    public function test_dedup_is_scoped_to_the_format(string $code): void
    {
        $service = $this->service();
        $service->responses = [['content' => $this->wrapper($code, $this->twoRows($code))]];
        $service->generate($this->input($code));

        $this->assertSame($code, $service->dedupCalls[0]['formatCode']);

        $ctx = $service->persisted[0]['ctx'];
        $scoped = $service->hashQuery($ctx, 123, $ctx['question_type_id'], 'abc')->toSql();
        $this->assertStringContainsString('`question_format_code` = ?', $scoped);
        $this->assertStringContainsString('`question_type_id` = ?', $scoped);
        $this->assertStringContainsString('`concept_id` = ?', $scoped);
    }

    public function test_legacy_dedup_keeps_the_original_concept_type_hash_check(): void
    {
        $service = $this->service();
        $legacy = $service->hashQuery(['format_code' => null, 'scope_dedup' => false], 123, 2, 'abc')->toSql();
        $mcq = $service->hashQuery(['format_code' => 'mcq', 'scope_dedup' => false], 123, 1, 'abc')->toSql();

        $this->assertStringNotContainsString('question_format_code', $legacy);
        $this->assertStringNotContainsString('question_format_code', $mcq);
        $this->assertStringContainsString('`concept_id` = ? and `question_type_id` = ?', $legacy);
    }

    public function test_the_same_words_in_two_formats_are_not_one_question(): void
    {
        $service = $this->service();
        $trueFalse = $service->hashQuery(['format_code' => 'true_false', 'scope_dedup' => true], 1, 1, 'h')->getBindings();
        $fillBlank = $service->hashQuery(['format_code' => 'fill_blank', 'scope_dedup' => true], 1, 2, 'h')->getBindings();

        $this->assertContains('true_false', $trueFalse);
        $this->assertContains('fill_blank', $fillBlank);
        $this->assertNotContains('fill_blank', $trueFalse);
    }

    public function test_the_mcq_format_code_uses_the_legacy_mcq_engine_and_records_the_form(): void
    {
        $service = $this->service();
        $service->responses = [['content' => $this->wrapper('mcq', [])]];
        $service->generate($this->input('mcq'));

        $this->assertStringContainsString('## CONSTRUCTION RULES (MCQ)', $service->calls[0]['user']);
        $this->assertStringNotContainsString('## FORMAT CONTRACT', $service->calls[0]['user']);
        $this->assertNull($service->dedupCalls[0]['formatCode'], 'MCQ dedup is unchanged');
    }

    public function test_every_format_batches_by_its_own_size(): void
    {
        $service = $this->service();
        $sizes = [];
        foreach (array_keys(self::EXPECTED) as $code) {
            $sizes[$code] = $service->formats()->get($code)->batchSize();
        }

        $this->assertSame(
            ['true_false' => 10, 'fill_blank' => 10, 'drag_text' => 8, 'mark_the_words' => 8, 'match_following' => 3,
             'assertion_reason' => 5, 'numerical' => 5, 'very_short' => 5, 'short' => 3, 'long' => 3, 'case_study' => 2,
             'proof' => 3, 'construction' => 3],
            $sizes
        );
    }

    public function test_a_long_run_splits_into_the_formats_batches(): void
    {
        $service = $this->service();
        $rows = [];
        for ($i = 1; $i <= 12; $i++) {
            $row = $this->example('true_false');
            $row['question_title'] = "Statement number {$i} about the sign of a product holds.";
            $rows[] = $row;
        }
        // 12 true/false at 10 per call => two calls.
        $service->responses = [
            ['content' => $this->wrapper('true_false', array_slice($rows, 0, 10))],
            ['content' => $this->wrapper('true_false', array_slice($rows, 10, 2))],
        ];

        $result = $service->generate($this->input('true_false', ['total_questions' => 12]));

        $this->assertTrue($result['status'], $result['message'] ?? '');
        $this->assertCount(2, $service->calls);
        $this->assertSame(12, $result['data']['generated']);
        $this->assertSame(2, $result['data']['batches']);
    }
}
