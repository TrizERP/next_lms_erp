<?php

namespace Tests\Unit\QuestionGeneration\H5p;

use App\Services\QuestionGeneration\H5p\H5pContentTypeRegistry;
use App\Services\QuestionGeneration\H5p\H5pMultipleChoice;
use App\Services\QuestionGeneration\H5p\H5pTrueFalse;
use App\Services\QuestionGeneration\QuestionFormatRegistry;
use Tests\TestCase;
use Tests\Unit\QuestionGeneration\Support\FakeCatalogue;

/**
 * Which formats are generated from an H5P content type, and what the picker is shown.
 * NO DATABASE.
 */
class H5pContentTypeRegistryTest extends TestCase
{
    private function on(array $types = ['mcq', 'true_false'], int $perType = 2): H5pContentTypeRegistry
    {
        config(['question_formats.h5p' => [
            'enabled' => true, 'types' => $types, 'questions_per_type' => $perType, 'max_attempts' => 3,
        ]]);

        return new H5pContentTypeRegistry();
    }

    public function test_phase_one_implements_exactly_multiple_choice_and_true_false(): void
    {
        $all = (new H5pContentTypeRegistry())->all();

        $this->assertSame(['mcq', 'true_false'], array_keys($all));
        $this->assertInstanceOf(H5pMultipleChoice::class, $all['mcq']);
        $this->assertInstanceOf(H5pTrueFalse::class, $all['true_false']);
        $this->assertSame(['H5P.MultiChoice', 'H5P.TrueFalse'], array_map(fn ($t) => $t->h5pType(), array_values($all)));
    }

    public function test_each_content_type_declares_the_catalogue_code_it_writes_and_a_type_value(): void
    {
        foreach ((new H5pContentTypeRegistry())->all() as $code => $type) {
            $this->assertSame($code, $type->formatCode());
            $this->assertSame($code, $type->questionType());
            $this->assertJson($type->responseSchema(), "{$code} schema is not valid JSON");
            $this->assertStringContainsString('"const": "' . $code . '"', $type->responseSchema());
            $this->assertStringNotContainsString('{{count}}', $type->prompt(2), 'the count placeholder was not filled');
            $this->assertStringContainsString('exactly 2 question object(s)', $type->prompt(2));
        }
    }

    public function test_only_configured_types_are_active(): void
    {
        $registry = $this->on(['mcq']);

        $this->assertSame(['mcq'], $registry->activeCodes());
        $this->assertNotNull($registry->activeFor('mcq'));
        $this->assertNull($registry->activeFor('true_false'));
        $this->assertNull($registry->activeFor('fill_blank'));
    }

    public function test_a_configured_code_with_no_h5p_definition_is_ignored(): void
    {
        $registry = $this->on(['mcq', 'fill_blank', 'matching', 'nonsense']);

        $this->assertSame(['mcq'], $registry->activeCodes());
        $this->assertNull($registry->activeFor('fill_blank'));
    }

    public function test_switching_the_layer_off_restores_every_format(): void
    {
        config(['question_formats.h5p.enabled' => false]);
        $registry = new H5pContentTypeRegistry();

        $this->assertNull($registry->activeCodes(), 'null means unrestricted');
        $this->assertNull($registry->activeFor('mcq'));
        $this->assertNull($registry->questionsPerType());

        $catalogue = (new QuestionFormatRegistry(FakeCatalogue::live()))->generatable();
        $this->assertSame($catalogue, $registry->decorate($catalogue), 'the list is passed through untouched');
    }

    public function test_the_picker_lists_every_format_and_tags_the_active_h5p_ones(): void
    {
        $registry = $this->on();
        $generatable = (new QuestionFormatRegistry(FakeCatalogue::live()))->generatable();
        $this->assertGreaterThan(2, count($generatable), 'the catalogue offers more than the two H5P types');

        $shown = $registry->decorate($generatable);

        $this->assertSame(array_column($generatable, 'code'), array_column($shown, 'code'), 'nothing is filtered out');
        $tagged = array_filter(array_column($shown, 'h5p_content_type', 'code'));
        $this->assertSame(
            ['mcq' => ['type' => 'H5P.MultiChoice', 'label' => 'Multiple Choice'], 'true_false' => ['type' => 'H5P.TrueFalse', 'label' => 'True/False']],
            $tagged
        );
        // Everything the modal already reads is still there.
        $this->assertArrayHasKey('label', $shown[0]);
        $this->assertArrayHasKey('lms_question_type_id', $shown[0]);
    }

    public function test_a_type_missing_from_the_catalogue_is_not_offered(): void
    {
        $entries = FakeCatalogue::live()->entries();
        unset($entries['true_false']);

        $shown = $this->on()->decorate((new QuestionFormatRegistry(new FakeCatalogue($entries)))->generatable());

        $this->assertContains('mcq', array_column($shown, 'code'));
        $this->assertNotContains('true_false', array_column($shown, 'code'));
    }

    public function test_the_per_type_count_is_configurable_and_never_below_one(): void
    {
        $this->assertSame(2, $this->on()->questionsPerType());
        $this->assertSame(3, $this->on(['mcq'], 3)->questionsPerType());
        $this->assertSame(1, $this->on(['mcq'], 0)->questionsPerType());
    }
}
