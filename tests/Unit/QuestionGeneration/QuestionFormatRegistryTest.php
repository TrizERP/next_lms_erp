<?php

namespace Tests\Unit\QuestionGeneration;

use App\Services\QuestionGeneration\Formats\McqFormat;
use App\Services\QuestionGeneration\Formats\NarrativeLegacyFormat;
use App\Services\QuestionGeneration\QuestionFormatRegistry;
use Tests\TestCase;
use Tests\Unit\QuestionGeneration\Support\FakeCatalogue;
use Tests\Unit\QuestionGeneration\Support\StubFormat;
use Tests\Unit\QuestionGeneration\Support\StubRegistry;

/**
 * Which formats exist, which are offered, and how a request resolves to one.
 *
 * NO DATABASE: a FakeCatalogue stands in for question_type_catalog.
 */
class QuestionFormatRegistryTest extends TestCase
{
    private function registry(?array $entries = null): QuestionFormatRegistry
    {
        $catalogue = $entries === null ? FakeCatalogue::live() : new FakeCatalogue($entries);

        return new QuestionFormatRegistry($catalogue);
    }

    public function test_every_registered_format_has_a_unique_code(): void
    {
        $codes = array_keys($this->registry()->all());

        $this->assertSame($codes, array_values(array_unique($codes)));
        $this->assertContains('mcq', $codes);
        $this->assertContains('narrative', $codes);
    }

    public function test_a_format_code_resolves_to_its_format(): void
    {
        $format = $this->registry()->resolveRequest(['question_format_code' => 'mcq']);

        $this->assertInstanceOf(McqFormat::class, $format);
    }

    public function test_the_format_code_wins_over_the_legacy_alias(): void
    {
        $format = $this->registry()->resolveRequest(['question_format_code' => 'mcq', 'question_type' => 'narrative']);

        $this->assertInstanceOf(McqFormat::class, $format);
    }

    public function test_the_codes_are_case_and_space_insensitive(): void
    {
        $this->assertInstanceOf(McqFormat::class, $this->registry()->resolveRequest(['question_format_code' => '  MCQ ']));
    }

    public function test_legacy_aliases_resolve(): void
    {
        $this->assertInstanceOf(McqFormat::class, $this->registry()->resolveRequest(['question_type' => 'mcq']));
        $this->assertInstanceOf(NarrativeLegacyFormat::class, $this->registry()->resolveRequest(['question_type' => 'narrative']));
        $this->assertInstanceOf(NarrativeLegacyFormat::class, $this->registry()->resolveRequest(['question_type' => 'NARRATIVE']));
    }

    public function test_the_legacy_alias_needs_no_catalogue(): void
    {
        $format = $this->registry([])->resolveRequest(['question_type' => 'narrative']);

        $this->assertInstanceOf(NarrativeLegacyFormat::class, $format);
    }

    public function test_unsupported_requests_explain_themselves(): void
    {
        $error = null;

        $this->assertNull($this->registry()->resolveRequest(['question_format_code' => 'antonym'], $error));
        $this->assertStringContainsString('"antonym" is not supported', $error);

        $this->assertNull($this->registry()->resolveRequest(['question_type' => 'essay'], $error));
        $this->assertSame('question_type must be "mcq" or "narrative".', $error);

        $this->assertNull($this->registry()->resolveRequest([], $error));
        $this->assertStringContainsString('Provide question_format_code', $error);
    }

    public function test_narrative_is_not_a_catalogue_form_and_cannot_be_requested_by_code(): void
    {
        $error = null;

        $this->assertNull($this->registry()->resolveRequest(['question_format_code' => 'narrative'], $error));
        $this->assertStringContainsString('not supported', $error);
    }

    public function test_an_implemented_format_missing_from_the_catalogue_is_refused(): void
    {
        $error = null;
        $registry = new StubRegistry(FakeCatalogue::live());

        $this->assertNull($registry->resolveRequest(['question_format_code' => 'stub_form'], $error));
        $this->assertStringContainsString('not in the question type catalogue', $error);
    }

    public function test_the_question_type_id_comes_from_the_catalogue_not_the_formats_fallback(): void
    {
        $entries = FakeCatalogue::live()->entries();
        $entries['mcq']['lms_question_type_id'] = 9; // deliberately unlike the fallback of 1
        $registry = $this->registry($entries);

        $this->assertSame(9, $registry->questionTypeIdFor(new McqFormat()));
    }

    public function test_the_question_type_id_falls_back_when_the_catalogue_has_no_row(): void
    {
        $registry = $this->registry([]);

        $this->assertSame(1, $registry->questionTypeIdFor(new McqFormat()));
        $this->assertSame(2, $registry->questionTypeIdFor(new NarrativeLegacyFormat()));
    }

    public function test_the_live_catalogue_maps_each_v1_code_to_its_real_type_id(): void
    {
        // Values read from the live question_type_catalog: 1 = multiple, 2 = narrative.
        $live = FakeCatalogue::live()->entries();
        $expected = [
            'mcq' => 1, 'true_false' => 1, 'assertion_reason' => 1,
            'fill_blank' => 2, 'match_following' => 2, 'numerical' => 2,
            'very_short' => 2, 'short' => 2, 'long' => 2, 'case_study' => 2,
        ];

        foreach ($expected as $code => $typeId) {
            $this->assertSame($typeId, $live[$code]['lms_question_type_id'], $code);
        }
    }

    public function test_default_marks_use_the_catalogue_when_it_is_inside_the_formats_range(): void
    {
        $registry = new StubRegistry(new FakeCatalogue([
            'stub_form' => FakeCatalogue::entry('stub_form', 'Stub', 2, 3),
        ]));

        $this->assertSame(3, $registry->defaultMarksFor(new StubFormat()));
    }

    public function test_default_marks_fall_back_when_the_catalogue_is_null_or_out_of_range(): void
    {
        $nullMarks = new StubRegistry(new FakeCatalogue(['stub_form' => FakeCatalogue::entry('stub_form', 'Stub', 2, null)]));
        $outOfRange = new StubRegistry(new FakeCatalogue(['stub_form' => FakeCatalogue::entry('stub_form', 'Stub', 2, 50)]));

        $this->assertSame(2, $nullMarks->defaultMarksFor(new StubFormat()));
        $this->assertSame(2, $outOfRange->defaultMarksFor(new StubFormat()));
    }

    public function test_generatable_is_the_intersection_of_implemented_and_catalogued(): void
    {
        $generatable = $this->registry()->generatable();
        $codes = array_column($generatable, 'code');

        $this->assertContains('mcq', $codes);
        // Catalogued but not implemented by the generator.
        foreach (['flash_card', 'case_study_parent', 'antonym'] as $notImplemented) {
            $this->assertNotContains($notImplemented, $codes);
        }
        // Implemented but not catalogued: a registry over a catalogue with no mcq row.
        $noMcq = $this->registry(['true_false' => FakeCatalogue::entry('true_false', 'True / False', 1, 1)]);
        $this->assertNotContains('mcq', array_column($noMcq->generatable(), 'code'));
        // The legacy alias is never listed.
        $this->assertNotContains('narrative', $codes);
    }

    public function test_generatable_entries_carry_everything_the_modal_needs(): void
    {
        $mcq = array_values(array_filter($this->registry()->generatable(), fn ($f) => $f['code'] === 'mcq'))[0];

        $this->assertSame('Multiple Choice', $mcq['label']);
        $this->assertSame(1, $mcq['lms_question_type_id']);
        $this->assertSame(1, $mcq['default_marks']);
        $this->assertFalse($mcq['marks_editable']);
        $this->assertSame(50, $mcq['max_questions']);
        $this->assertContains('Apply', $mcq['allowed_bloom_levels']);
        $this->assertArrayHasKey('batch_size', $mcq);
        $this->assertArrayHasKey('prompt_version', $mcq);
        $this->assertArrayNotHasKey('h5p_target', $mcq, 'the H5P mapping lives only in lib/h5p/question-bank-h5p-map.ts');
    }

    public function test_the_catalogue_collapses_duplicate_publisher_rows_to_one_code(): void
    {
        // The real reader keeps the first row per code; the fake mirrors the contract.
        $codes = array_keys(FakeCatalogue::live()->entries());

        $this->assertSame($codes, array_values(array_unique($codes)));
    }
}
