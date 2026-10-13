<?php

namespace Tests\Unit\QuestionGeneration;

use App\Services\QuestionGeneration\DragDrop\Contracts\DiagramImageFinder;
use App\Services\QuestionGeneration\DragDrop\Contracts\DiagramImageStore;
use App\Services\QuestionGeneration\DragDrop\Contracts\DiagramVisionAnalyzer;
use App\Services\QuestionGeneration\DragDrop\DragDropGeometry;
use App\Services\QuestionGeneration\DragDrop\DragDropRowProducer;
use App\Services\QuestionGeneration\Formats\DragDropFormat;
use App\Services\QuestionGeneration\QuestionEnvelope;
use App\Services\QuestionGeneration\QuestionFormatRegistry;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;
use Tests\Unit\QuestionGeneration\Support\FakeCatalogue;
use Tests\Unit\QuestionGeneration\Support\TestableGenerationService;

/**
 * Image-based Drag & Drop. NO network, NO database, NO model: the picture finder, the
 * store and the vision step are replaced by fakes that answer from a queue.
 */
class DragDropFormatTest extends TestCase
{
    private function image(string $url = 'https://img.test/cell.jpg', array $over = []): array
    {
        return $over + [
            'bytes' => 'bytes-of-' . $url, 'mime' => 'image/jpeg', 'width' => 1200, 'height' => 800,
            'image_url' => $url, 'source_url' => 'https://commons.test/cell', 'title' => 'Animal cell',
            'creator' => 'A. Person', 'licence' => 'CC BY-SA 4.0', 'attribution' => '"Animal cell" by A. Person',
            'provider' => 'wikimedia',
        ];
    }

    /** Four well-separated parts, boxes on the model's 0-1000 scale. */
    private function parts(): array
    {
        return [
            ['label' => 'Nucleus', 'box_2d' => [100, 200, 300, 400]],
            ['label' => 'Cell membrane', 'box_2d' => [100, 500, 300, 700]],
            ['label' => 'Mitochondrion', 'box_2d' => [500, 100, 700, 300]],
            ['label' => 'Cytoplasm', 'box_2d' => [500, 500, 700, 800]],
        ];
    }

    private function analysis(array $over = []): array
    {
        return $over + ['ok' => true, 'usable' => true, 'has_printed_labels' => false, 'alt' => 'An animal cell', 'parts' => $this->parts()];
    }

    private function finder(array $images): object
    {
        return new class($images) implements DiagramImageFinder {
            public array $asked = [];

            public function __construct(public array $images)
            {
            }

            public function find(array $context, array $excludeUrls = []): ?array
            {
                $this->asked[] = $excludeUrls;
                foreach ($this->images as $i => $image) {
                    if (!in_array($image['image_url'], $excludeUrls, true)) {
                        unset($this->images[$i]);

                        return $image;
                    }
                }

                return null;
            }
        };
    }

    private function analyzer(array $answers): object
    {
        return new class($answers) implements DiagramVisionAnalyzer {
            public array $prompts = [];

            public function __construct(public array $answers)
            {
            }

            public function analyze(string $prompt, string $imageBytes, string $mime): array
            {
                $this->prompts[] = $prompt;

                return array_shift($this->answers) ?? ['ok' => false, 'error' => 'none queued'];
            }
        };
    }

    private function store(): object
    {
        return new class implements DiagramImageStore {
            public array $stored = [];

            public function store(string $bytes, string $mime): string
            {
                $this->stored[] = $bytes;

                return 'https://cdn.test/dragdrop_gen_' . sha1($bytes) . '.jpg';
            }
        };
    }

    private function producer(object $finder, object $analyzer, object $store, int $max = 3): DragDropRowProducer
    {
        $format = new DragDropFormat();

        return new DragDropRowProducer($finder, $store, $analyzer, new DragDropGeometry(), fn (array $c) => $format->visionPrompt($c), $max);
    }

    private function quota(int $count = 1, string $level = 'Remember'): array
    {
        return [['level' => $level, 'count' => $count, 'dok' => 1, 'difficulty' => 'Easy', 'points' => 4, 'sub_type' => 'Drag and Drop']];
    }

    private function context(): array
    {
        return ['concept_id' => 123, 'concept_name' => 'Animal cell structure', 'sub_institute_id' => 7];
    }

    private function slice(): array
    {
        return [
            'knowledge_items' => ['Identify the parts of an animal cell'],
            'learning_outcomes' => [['outcome' => 'Names the organelles of an animal cell']],
            'misconceptions' => ['Cell membrane and cell wall are the same'],
        ];
    }

    // ---- the registered format ------------------------------------------------

    public function test_it_is_a_registered_format_that_sources_its_own_rows(): void
    {
        $format = new DragDropFormat();

        $this->assertSame('drag_drop', $format->code());
        $this->assertSame('drag_drop', $format->persistedFormatCode());
        $this->assertSame('Drag and Drop', $format->label());
        $this->assertSame([3, 8], $format->marksRange(), 'one mark per labelled part');
        $this->assertSame(['Remember', 'Understand', 'Apply'], $format->allowedBloomLevels());
        $this->assertInstanceOf(\App\Services\QuestionGeneration\SourcesOwnRows::class, $format);
        $this->assertContains('drag_drop', array_column((new QuestionFormatRegistry(FakeCatalogue::live()))->generatable(), 'code'));
    }

    // ---- geometry -------------------------------------------------------------

    private function payload(array $over = []): array
    {
        $zones = [
            ['id' => 'z1', 'label' => 'Nucleus', 'x' => 20.0, 'y' => 10.0, 'width' => 20.0, 'height' => 20.0],
            ['id' => 'z2', 'label' => 'Membrane', 'x' => 50.0, 'y' => 10.0, 'width' => 20.0, 'height' => 20.0],
            ['id' => 'z3', 'label' => 'Cytoplasm', 'x' => 10.0, 'y' => 50.0, 'width' => 20.0, 'height' => 20.0],
        ];
        $elements = array_map(fn ($z, $i) => ['id' => 'e' . ($i + 1), 'text' => $z['label'], 'zone_ids' => [$z['id']]], $zones, array_keys($zones));

        return $over + [
            'v' => 1,
            'image' => ['url' => 'https://cdn.test/a.jpg', 'width_px' => 1200, 'height_px' => 800, 'mime' => 'image/jpeg'],
            'image_fit' => 'contain', 'zones' => $zones, 'elements' => $elements,
        ];
    }

    public function test_a_clean_payload_passes(): void
    {
        $this->assertNull((new DragDropGeometry())->reason($this->payload()));
    }

    #[DataProvider('badPayloads')]
    public function test_a_broken_payload_is_refused(callable $break, string $expected): void
    {
        $p = $break($this->payload());
        $reason = (new DragDropGeometry())->reason($p);

        $this->assertNotNull($reason, 'should have been refused');
        $this->assertStringContainsString($expected, $reason);
    }

    public static function badPayloads(): array
    {
        return [
            'no image'            => [fn ($p) => array_diff_key($p, ['image' => 1]), 'image is missing'],
            'image not a url'     => [function ($p) { $p['image']['url'] = 'cell.jpg'; return $p; }, 'http(s) URL'],
            'image too small'     => [function ($p) { $p['image']['width_px'] = 300; return $p; }, 'at least 400x300'],
            'image type'          => [function ($p) { $p['image']['mime'] = 'image/gif'; return $p; }, 'not supported'],
            'too few zones'       => [function ($p) { array_pop($p['zones']); array_pop($p['elements']); return $p; }, '3 to 8 zones'],
            'zone off the image'  => [function ($p) { $p['zones'][0]['x'] = 90.0; return $p; }, 'outside the image'],
            'negative position'   => [function ($p) { $p['zones'][0]['y'] = -1.0; return $p; }, 'outside the image'],
            'zone too small'      => [function ($p) { $p['zones'][0]['width'] = 1.0; return $p; }, 'between 4% and 60%'],
            'zone too large'      => [function ($p) { $p['zones'][0]['height'] = 80.0; return $p; }, 'between 4% and 60%'],
            'non numeric'         => [function ($p) { $p['zones'][0]['width'] = '20'; return $p; }, 'numeric width'],
            'duplicate zone id'   => [function ($p) { $p['zones'][1]['id'] = 'z1'; return $p; }, 'used twice'],
            'duplicate label'     => [function ($p) { $p['zones'][1]['label'] = 'nucleus'; return $p; }, 'used twice'],
            'zones overlap'       => [function ($p) { $p['zones'][1]['x'] = 22.0; $p['zones'][1]['y'] = 12.0; return $p; }, 'overlap too much'],
            'duplicate element id'=> [function ($p) { $p['elements'][1]['id'] = 'e1'; return $p; }, 'used twice'],
            'element to nowhere'  => [function ($p) { $p['elements'][0]['zone_ids'] = []; return $p; }, 'maps to no zone'],
            'unknown zone'        => [function ($p) { $p['elements'][0]['zone_ids'] = ['z9']; return $p; }, 'unknown zone'],
            'zone with no answer' => [function ($p) { $p['elements'][2]['zone_ids'] = ['z1']; return $p; }, 'has no correct element'],
            'long label'          => [function ($p) { $p['elements'][0]['text'] = str_repeat('x', 41); return $p; }, 'characters'],
        ];
    }

    // ---- the producer ---------------------------------------------------------

    public function test_boxes_become_percentages_of_the_image_and_the_row_is_valid(): void
    {
        $store = $this->store();
        $result = $this->producer($this->finder([$this->image()]), $this->analyzer([$this->analysis()]), $store)
            ->produce($this->quota(), $this->slice(), $this->context());

        $this->assertTrue($result['ok']);
        $this->assertCount(1, $result['rows']);
        $row = $result['rows'][0];
        $dd = $row['answer']['drag_drop'];

        // [ymin, xmin, ymax, xmax] = [100, 200, 300, 400] on a 0-1000 scale.
        $this->assertSame(['id' => 'z1', 'label' => 'Nucleus', 'x' => 20.0, 'y' => 10.0, 'width' => 20.0, 'height' => 20.0], $dd['zones'][0]);
        $this->assertCount(4, $dd['zones']);
        $this->assertSame(['id' => 'e1', 'text' => 'Nucleus', 'zone_ids' => ['z1']], $dd['elements'][0]);
        $this->assertSame(4, $row['points'], 'one mark per zone');
        $this->assertSame('contain', $dd['image_fit']);

        $this->assertStringStartsWith('https://cdn.test/dragdrop_gen_', $dd['image']['url'], 'the stored copy, not the third party');
        $this->assertSame(1200, $dd['image']['width_px']);
        $this->assertSame('CC BY-SA 4.0', $dd['image']['licence']);
        $this->assertSame('https://commons.test/cell', $dd['image']['source_url']);

        $this->assertNull((new DragDropGeometry())->reason($dd));
        $format = new DragDropFormat();
        $this->assertNull($format->validateRow($row), 'the format accepts its own rows');
        $this->assertSame('Drag and Drop', $format->prepareRow($row)['answer']['sub_type']);
        $this->assertSame(['Identify the parts of an animal cell'], $row['answer']['knowledge_refs']);
        $this->assertSame(['Names the organelles of an animal cell'], $row['learning_outcome']);
    }

    public function test_parts_that_do_not_hold_up_are_dropped_one_by_one(): void
    {
        $parts = [
            ['label' => 'Nucleus', 'box_2d' => [100, 200, 300, 400]],
            ['label' => 'nucleus', 'box_2d' => [600, 600, 800, 800]],          // same label
            ['label' => 'Off the edge', 'box_2d' => [100, 900, 300, 1100]],    // beyond the image
            ['label' => 'Speck', 'box_2d' => [500, 500, 510, 510]],            // too small
            ['label' => 'Inverted', 'box_2d' => [300, 600, 200, 500]],         // max < min
            ['label' => 'On top', 'box_2d' => [110, 210, 290, 390]],           // covers the nucleus
            ['label' => 'Text box', 'box_2d' => 'nope'],
            ['label' => '  ', 'box_2d' => [0, 0, 100, 100]],
            ['label' => 'Membrane', 'box_2d' => [100, 500, 300, 700]],
            ['label' => 'Cytoplasm', 'box_2d' => [500, 100, 700, 300]],
        ];
        $result = $this->producer($this->finder([$this->image()]), $this->analyzer([$this->analysis(['parts' => $parts])]), $this->store())
            ->produce($this->quota(), $this->slice(), $this->context());

        $this->assertTrue($result['ok']);
        $this->assertSame(['Nucleus', 'Membrane', 'Cytoplasm'], array_column($result['rows'][0]['answer']['drag_drop']['zones'], 'label'));
    }

    public function test_at_most_the_maximum_number_of_zones_is_used(): void
    {
        $parts = [];
        for ($i = 0; $i < 10; $i++) {
            $col = $i % 5;
            $row = intdiv($i, 5);
            $parts[] = ['label' => "Part {$i}", 'box_2d' => [$row * 400 + 50, $col * 190 + 20, $row * 400 + 250, $col * 190 + 170]];
        }
        $result = $this->producer($this->finder([$this->image()]), $this->analyzer([$this->analysis(['parts' => $parts])]), $this->store())
            ->produce($this->quota(), $this->slice(), $this->context());

        $this->assertTrue($result['ok']);
        $this->assertCount(8, $result['rows'][0]['answer']['drag_drop']['zones']);
        $this->assertSame(8, $result['rows'][0]['points']);
    }

    #[DataProvider('unusablePictures')]
    public function test_a_picture_that_will_not_do_produces_nothing_and_says_why(array $image, array $analysis, string $expected): void
    {
        $store = $this->store();
        $result = $this->producer($this->finder([$image]), $this->analyzer([$analysis]), $store)
            ->produce($this->quota(), $this->slice(), $this->context());

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('No suitable diagram', $result['error']);
        $this->assertStringContainsString($expected, $result['error']);
        $this->assertSame([], $store->stored, 'nothing is stored for a question that was not made');
    }

    public static function unusablePictures(): array
    {
        $image = ['bytes' => 'b', 'mime' => 'image/jpeg', 'width' => 1200, 'height' => 800, 'image_url' => 'https://img.test/x.jpg'];
        $parts = [
            ['label' => 'A', 'box_2d' => [100, 100, 300, 300]],
            ['label' => 'B', 'box_2d' => [100, 500, 300, 700]],
            ['label' => 'C', 'box_2d' => [500, 100, 700, 300]],
        ];
        $good = ['ok' => true, 'usable' => true, 'has_printed_labels' => false, 'alt' => 'x', 'parts' => $parts];

        return [
            'too small'           => [array_replace($image, ['width' => 300, 'height' => 200]), $good, 'at least 400x300'],
            'not a supported type'=> [array_replace($image, ['mime' => 'image/gif']), $good, 'not supported'],
            'already labelled'    => [$image, array_replace($good, ['has_printed_labels' => true]), 'already shows its labels'],
            'model says unusable' => [$image, array_replace($good, ['usable' => false]), 'no distinct parts'],
            'too few parts'       => [$image, array_replace($good, ['parts' => array_slice($parts, 0, 2)]), 'fewer than 3 parts'],
            'vision failed'       => [$image, ['ok' => false, 'error' => 'quota'], 'could not be analysed'],
        ];
    }

    public function test_when_nothing_is_found_it_says_so_instead_of_inventing_a_question(): void
    {
        $analyzer = $this->analyzer([]);
        $result = $this->producer($this->finder([]), $analyzer, $this->store())->produce($this->quota(), $this->slice(), $this->context());

        $this->assertFalse($result['ok']);
        $this->assertSame([], $analyzer->prompts, 'the vision model is not asked about a picture that does not exist');
    }

    public function test_a_bad_picture_is_skipped_for_the_next_one_and_never_retried(): void
    {
        $finder = $this->finder([$this->image('https://img.test/one.jpg'), $this->image('https://img.test/two.jpg')]);
        $analyzer = $this->analyzer([$this->analysis(['usable' => false]), $this->analysis()]);
        $store = $this->store();

        $result = $this->producer($finder, $analyzer, $store)->produce($this->quota(), $this->slice(), $this->context());

        $this->assertTrue($result['ok']);
        $this->assertCount(1, $result['rows']);
        $this->assertSame([[], ['https://img.test/one.jpg']], $finder->asked, 'the rejected picture is excluded from the next search');
        $this->assertCount(1, $store->stored);
    }

    public function test_every_question_gets_its_own_picture(): void
    {
        $finder = $this->finder([$this->image('https://img.test/a.jpg'), $this->image('https://img.test/b.jpg')]);
        $result = $this->producer($finder, $this->analyzer([$this->analysis(), $this->analysis(['alt' => 'Another cell'])]), $this->store())
            ->produce($this->quota(2), $this->slice(), $this->context());

        $this->assertTrue($result['ok']);
        $this->assertCount(2, $result['rows']);
        $this->assertNotSame($result['rows'][0]['answer']['drag_drop']['image']['sha1'], $result['rows'][1]['answer']['drag_drop']['image']['sha1']);
        $this->assertNotSame($result['rows'][0]['question_title'], $result['rows'][1]['question_title'], 'titles differ, so de-duplication does not merge them');
        $this->assertFalse($result['underfilled']);
    }

    public function test_pictures_per_request_are_capped_and_the_shortfall_is_reported(): void
    {
        $images = array_map(fn ($i) => $this->image("https://img.test/{$i}.jpg"), range(1, 6));
        $analyses = array_map(fn ($i) => $this->analysis(['alt' => "Cell {$i}"]), range(1, 6));
        $result = $this->producer($this->finder($images), $this->analyzer($analyses), $this->store(), 2)
            ->produce($this->quota(5), $this->slice(), $this->context());

        $this->assertTrue($result['ok']);
        $this->assertCount(2, $result['rows']);
        $this->assertTrue($result['underfilled']);
        $this->assertStringContainsString('At most 2 image questions', $result['reason']);
    }

    public function test_the_vision_prompt_names_the_topic_and_asks_for_reported_not_invented_positions(): void
    {
        $analyzer = $this->analyzer([$this->analysis()]);
        $this->producer($this->finder([$this->image()]), $analyzer, $this->store())->produce($this->quota(), $this->slice(), $this->context());

        $prompt = $analyzer->prompts[0];
        $this->assertStringContainsString('Animal cell structure', $prompt);
        $this->assertStringContainsString('box_2d', $prompt);
        $this->assertStringContainsString('has_printed_labels', $prompt);
        $this->assertStringContainsString('Only name what you can actually see', $prompt);
    }

    // ---- format validation ------------------------------------------------------

    private function validRow(): array
    {
        $result = $this->producer($this->finder([$this->image()]), $this->analyzer([$this->analysis()]), $this->store())
            ->produce($this->quota(), $this->slice(), $this->context());

        return $result['rows'][0];
    }

    public function test_the_format_refuses_a_row_whose_marks_do_not_match_its_zones(): void
    {
        $row = $this->validRow();
        $row['points'] = 3;

        $this->assertSame('points must equal the number of zones', (new DragDropFormat())->validateRow($row));
    }

    public function test_the_format_refuses_a_row_without_a_payload_or_with_a_broken_one(): void
    {
        $format = new DragDropFormat();

        $row = $this->validRow();
        unset($row['answer']['drag_drop']);
        $this->assertSame('answer.drag_drop is missing', $format->validateRow($row));

        $row = $this->validRow();
        $row['answer']['drag_drop']['zones'][0]['x'] = 95.0;
        $this->assertStringContainsString('outside the image', $format->validateRow($row));
    }

    // ---- the envelope reader ----------------------------------------------------

    public function test_the_reader_passes_on_a_playable_payload_and_withholds_a_broken_one(): void
    {
        $row = $this->validRow();

        $this->assertSame($row['answer']['drag_drop'], QuestionEnvelope::dragDrop($row['answer']));
        $this->assertSame($row['answer']['drag_drop'], QuestionEnvelope::clientFields($row['answer'])['drag_drop']);

        $row['answer']['drag_drop']['zones'][0]['width'] = 99.0;
        $this->assertNull(QuestionEnvelope::dragDrop($row['answer']));
        $this->assertNull(QuestionEnvelope::dragDrop(['pairs' => []]));
        $this->assertNull(QuestionEnvelope::dragDrop(null));
        $this->assertNull(QuestionEnvelope::clientFields(['model_answer' => 'x'])['drag_drop']);
    }

    // ---- through the service ----------------------------------------------------

    public function test_generating_drag_drop_never_calls_the_text_model_and_stores_the_payload(): void
    {
        $this->app->instance(DiagramImageFinder::class, $this->finder([$this->image()]));
        $this->app->instance(DiagramImageStore::class, $this->store());
        $this->app->instance(DiagramVisionAnalyzer::class, $this->analyzer([$this->analysis()]));

        $service = new TestableGenerationService(new QuestionFormatRegistry(FakeCatalogue::live()));
        $service->forInstitute(7);
        $result = $service->generate([
            'concept_id' => 123, 'subject_id' => 3, 'standard_id' => 8, 'chapter_id' => 11,
            'question_format_code' => 'drag_drop', 'total_questions' => 1,
            'sub_institute_id' => 7, 'created_by' => 55,
        ]);

        $this->assertTrue($result['status'], $result['message'] ?? '');
        $this->assertSame([], $service->calls, 'the language model was not called');
        $this->assertSame('drag_drop', $result['data']['question_format_code']);
        $this->assertSame(1, $result['data']['generated']);

        $this->assertCount(1, $service->persisted);
        $this->assertSame('drag_drop', $service->persisted[0]['ctx']['format_code']);
        $stored = $service->persisted[0]['resp']['rows'][0];
        $this->assertSame('drag_drop', $stored['answer']['question_type']);
        $this->assertCount(4, $stored['answer']['drag_drop']['zones']);

        // And the row that would be inserted dual-writes the format and keeps the payload.
        [$insert, $answer] = $service->masterRow($stored, $service->persisted[0]['resp'], $service->persisted[0]['ctx'], $service->persisted[0]['meta']);
        $this->assertSame('drag_drop', $insert['question_format_code']);
        $this->assertSame('drag_drop', $answer['item_form']);
        $this->assertSame($stored['answer']['drag_drop'], $answer['drag_drop']);
        $this->assertSame(json_decode($insert['answer'], true)['drag_drop'], $answer['drag_drop']);
    }

    public function test_a_chapter_with_no_usable_picture_fails_cleanly_for_this_format_only(): void
    {
        $this->app->instance(DiagramImageFinder::class, $this->finder([]));
        $this->app->instance(DiagramImageStore::class, $this->store());
        $this->app->instance(DiagramVisionAnalyzer::class, $this->analyzer([]));

        $service = new TestableGenerationService(new QuestionFormatRegistry(FakeCatalogue::live()));
        $service->forInstitute(7);
        $result = $service->generate([
            'concept_id' => 123, 'subject_id' => 3, 'standard_id' => 8, 'chapter_id' => 11,
            'question_format_code' => 'drag_drop', 'total_questions' => 2,
            'sub_institute_id' => 7, 'created_by' => 55,
        ]);

        $this->assertFalse($result['status']);
        $this->assertStringContainsString('No suitable diagram', $result['message']);
        $this->assertSame([], $service->persisted, 'nothing is written');
    }
}
