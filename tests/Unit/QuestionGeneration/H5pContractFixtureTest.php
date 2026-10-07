<?php

namespace Tests\Unit\QuestionGeneration;

use App\Services\QuestionGeneration\QuestionEnvelope;
use App\Services\QuestionGeneration\QuestionFormatRegistry;
use Tests\TestCase;
use Tests\Unit\QuestionGeneration\Support\FakeCatalogue;
use Tests\Unit\QuestionGeneration\Support\TestableGenerationService;

/**
 * The bank-API shape of one generated question per format -- the contract the
 * frontend H5P projection (lib/h5p/question-bank-h5p-map.ts) is tested against.
 *
 * WHAT THIS IS. For each format, the example row is prepared exactly as persistence
 * would store it, then projected the way ApiLmsCourseController::getQuestionBank
 * returns a stored row: `question` is the stem, `options` come from the
 * answer_master rows the service would write, `model_answer` / `assertion` /
 * `reason` / `sub_part_labels` / `pairs` come out of the envelope, and
 * `question_type_code` is the format code. The result is pinned to
 * tests/Fixtures/question_generation/h5p_contract.json.
 *
 * THE FRONTEND COPY. lms_k12/lib/h5p/fixtures/generated-questions.json is a byte
 * copy of that file; lib/h5p/question-bank-generated-contract.test.ts feeds it
 * through the real builders (toTrueFalsePayload, blanksPassage, matchPairs, ...).
 * When a format's stored shape changes, this test fails first; regenerate with
 *
 *     UPDATE_GOLDEN=1 vendor/bin/phpunit --filter H5pContractFixture
 *
 * and copy the file across.
 *
 * NO DATABASE. The projection mirrors the reader rather than calling it (the
 * reader needs rows in a database); the live reader is exercised read-only by
 * the smoke checks recorded in the change notes.
 */
class H5pContractFixtureTest extends TestCase
{
    private const PATH = __DIR__ . '/../../Fixtures/question_generation/h5p_contract.json';
    private const MARKER = "EXAMPLE ROW (structure only; do not reuse its content)\n";

    private function service(): TestableGenerationService
    {
        $service = new TestableGenerationService(new QuestionFormatRegistry(FakeCatalogue::live()));
        $service->forInstitute(7);

        return $service;
    }

    /**
     * A drag_drop row, produced the way the generator produces one: a picture, a vision
     * answer, and the producer's conversion. The picture and the vision answer are fixed,
     * so the stored shape is reproducible.
     */
    private function dragDropRow(): array
    {
        $finder = new class implements \App\Services\QuestionGeneration\DragDrop\Contracts\DiagramImageFinder {
            public function find(array $context, array $excludeUrls = []): ?array
            {
                return [
                    'bytes' => 'fixture-image-bytes', 'mime' => 'image/jpeg', 'width' => 1200, 'height' => 800,
                    'image_url' => 'https://upload.test/animal-cell.jpg', 'source_url' => 'https://commons.test/animal-cell',
                    'title' => 'Animal cell', 'creator' => 'A. Person', 'licence' => 'CC BY-SA 4.0',
                    'attribution' => '"Animal cell" by A. Person, CC BY-SA 4.0', 'provider' => 'wikimedia',
                ];
            }
        };
        $store = new class implements \App\Services\QuestionGeneration\DragDrop\Contracts\DiagramImageStore {
            public function store(string $bytes, string $mime): string
            {
                return 'https://cdn.test/dragdrop_gen_fixture.jpg';
            }
        };
        $analyzer = new class implements \App\Services\QuestionGeneration\DragDrop\Contracts\DiagramVisionAnalyzer {
            public function analyze(string $prompt, string $imageBytes, string $mime): array
            {
                return [
                    'ok' => true, 'usable' => true, 'has_printed_labels' => false, 'alt' => 'An animal cell',
                    'parts' => [
                        ['label' => 'Nucleus', 'box_2d' => [100, 200, 300, 400]],
                        ['label' => 'Cell membrane', 'box_2d' => [100, 500, 300, 700]],
                        ['label' => 'Mitochondrion', 'box_2d' => [500, 100, 700, 300]],
                        ['label' => 'Cytoplasm', 'box_2d' => [500, 500, 700, 800]],
                    ],
                ];
            }
        };
        $format = new \App\Services\QuestionGeneration\Formats\DragDropFormat();
        $producer = new \App\Services\QuestionGeneration\DragDrop\DragDropRowProducer(
            $finder, $store, $analyzer, new \App\Services\QuestionGeneration\DragDrop\DragDropGeometry(),
            fn (array $c) => $format->visionPrompt($c), 3
        );
        $result = $producer->produce(
            [['level' => 'Remember', 'count' => 1, 'dok' => 1, 'difficulty' => 'Easy', 'points' => 4, 'sub_type' => 'Drag and Drop']],
            ['knowledge_items' => ['Identify the parts of an animal cell'], 'learning_outcomes' => ['Names the organelles of an animal cell'], 'misconceptions' => []],
            ['concept_id' => 123, 'concept_name' => 'Animal cell structure']
        );

        return $result['rows'][0];
    }

    private function exampleRow(string $code): array
    {
        $rules = $this->service()->formats()->get($code)->constructionRules(5);

        return json_decode(trim(substr($rules, strpos($rules, self::MARKER) + strlen(self::MARKER))), true);
    }

    /** One stored question, shaped as the bank API returns it. */
    private function project(string $code): array
    {
        $service = $this->service();
        $registry = $service->formats();
        $format = $registry->get($code);

        $row = $code === 'drag_drop'
            ? $format->prepareRow($this->dragDropRow())
            : ($format->isLegacy() ? $this->exampleRow($code) : $format->prepareRow($this->exampleRow($code)));
        $typeId = $registry->questionTypeIdFor($format);
        $ctx = [
            'concept_id' => 123, 'concept_name' => 'Multiplication', 'question_type_id' => $typeId,
            'sub_institute_id' => 7, 'created_by' => 55, 'standard_id' => 8, 'subject_id' => 3,
            'chapter_id' => 11, 'grade_id' => 8, 'semantic_concept_key' => 'K',
            'format_code' => $format->persistedFormatCode(), 'scope_dedup' => true,
        ];
        [$insert, $answer] = $service->masterRow($row, ['semantic_concept_key' => 'K'], $ctx, []);

        // Options as getQuestionBank labels them: by position, A, B, C...
        $options = [];
        foreach ($service->answerRows(1, $answer, $ctx) as $i => $option) {
            $options[] = [
                'label' => chr(65 + $i),
                'text' => $option['answer'],
                'is_correct' => (bool) $option['correct_answer'],
            ];
        }

        return [
            'id' => 9000 + array_search($code, $this->codes(), true),
            'question' => $insert['question_title'],
            'question_type' => $typeId === 1 ? 'MCQ' : 'Narrative',
            'question_type_code' => $answer['item_form'],
            'question_type_raw' => FakeCatalogue::live()->entries()[$code]['label'],
            'options' => $options,
            'model_answer' => $answer['model_answer'] ?? json_encode($answer),
            'marks' => $insert['points'],
            'assertion' => $answer['assertion'] ?? null,
            'reason' => $answer['reason'] ?? null,
            'sub_part_labels' => $answer['sub_part_labels'] ?? [],
            'pairs' => QuestionEnvelope::pairs($answer),
            'distractors' => QuestionEnvelope::distractors($answer),
            'drag_drop' => QuestionEnvelope::dragDrop($answer),
        ];
    }

    /** @return list<string> */
    private function codes(): array
    {
        return [
            'true_false', 'fill_blank', 'drag_text', 'mark_the_words', 'match_following', 'assertion_reason',
            'numerical', 'very_short', 'short', 'long', 'case_study', 'proof', 'construction', 'drag_drop',
        ];
    }

    private function build(): array
    {
        $fixture = [];
        foreach ($this->codes() as $code) {
            $fixture[$code] = $this->project($code);
        }

        return $fixture;
    }

    public function test_the_contract_fixture_matches_what_the_formats_store(): void
    {
        $actual = json_encode($this->build(), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";

        if (getenv('UPDATE_GOLDEN') === '1') {
            file_put_contents(self::PATH, $actual);
        }

        $this->assertFileExists(self::PATH);
        $this->assertSame(
            str_replace("\r\n", "\n", file_get_contents(self::PATH)),
            $actual,
            'A format changed what it stores. Regenerate (UPDATE_GOLDEN=1) and copy the file to lms_k12/lib/h5p/fixtures/generated-questions.json.'
        );
    }

    public function test_each_projected_row_has_the_fields_the_h5p_readers_need(): void
    {
        $fixture = $this->build();

        // True / false: a flagged True/False option AND the bare word.
        $this->assertSame('True', $fixture['true_false']['options'][0]['text']);
        $this->assertContains($fixture['true_false']['model_answer'], ['True', 'False']);
        $this->assertCount(1, array_filter($fixture['true_false']['options'], fn ($o) => $o['is_correct']));

        // Assertion & reason: four options, one correct, both halves kept apart AND in the stem.
        $this->assertCount(4, $fixture['assertion_reason']['options']);
        $this->assertCount(1, array_filter($fixture['assertion_reason']['options'], fn ($o) => $o['is_correct']));
        $this->assertStringContainsString($fixture['assertion_reason']['assertion'], $fixture['assertion_reason']['question']);
        $this->assertStringContainsString($fixture['assertion_reason']['reason'], $fixture['assertion_reason']['question']);
        $this->assertStringStartsWith('A) ', $fixture['assertion_reason']['model_answer']);

        // Typed-answer forms carry no options and a bare model answer.
        foreach (['fill_blank', 'drag_text', 'mark_the_words', 'numerical', 'match_following', 'very_short', 'short', 'long', 'case_study', 'proof', 'construction'] as $code) {
            $this->assertSame([], $fixture[$code]['options'], $code);
            $this->assertNotSame('', $fixture[$code]['model_answer'], $code);
        }
        $this->assertSame('negative', $fixture['fill_blank']['model_answer']);
        $this->assertSame('-12', $fixture['numerical']['model_answer']);

        // Match: structured pairs, not just text.
        $this->assertGreaterThanOrEqual(4, count($fixture['match_following']['pairs']));

        // Case study: one row carrying its sub-part labels.
        $this->assertSame(['a', 'b', 'c'], $fixture['case_study']['sub_part_labels']);

        // Only the match row has pairs, and only drag-the-words has a word bank.
        foreach ($fixture as $code => $row) {
            if ($code !== 'match_following') {
                $this->assertNull($row['pairs'], $code);
            }
            if ($code !== 'drag_text') {
                $this->assertNull($row['distractors'], $code);
            }
        }
        $this->assertSame(['respiration', 'oxygen', 'nitrogen'], $fixture['drag_text']['distractors']);
        $this->assertSame('photosynthesis; carbon dioxide', $fixture['drag_text']['model_answer']);

        // Mark the words: the stem carries the instruction AND the passage, and the answers sit in the passage.
        $this->assertStringStartsWith('Mark the word that names the gas the leaf releases.', $fixture['mark_the_words']['question']);
        $this->assertSame('oxygen', $fixture['mark_the_words']['model_answer']);
        $this->assertStringContainsString('oxygen', $fixture['mark_the_words']['question']);
    }

    public function test_question_type_is_the_catalogues_grading_type(): void
    {
        $fixture = $this->build();

        foreach (['true_false', 'assertion_reason'] as $code) {
            $this->assertSame('MCQ', $fixture[$code]['question_type'], $code);
        }
        foreach (['fill_blank', 'numerical', 'match_following', 'very_short', 'short', 'long', 'case_study', 'proof', 'construction'] as $code) {
            $this->assertSame('Narrative', $fixture[$code]['question_type'], $code);
        }
        // The catalogue types these two as MCQ, though they carry no answer_master options.
        foreach (['drag_text', 'mark_the_words'] as $code) {
            $this->assertSame('MCQ', $fixture[$code]['question_type'], $code);
        }
    }
}
