<?php

namespace Tests\Feature\CoursePresentation;

use App\Jobs\GenerateCoursePresentationJob;
use App\Services\CoursePresentation\GenerationRun;
use App\Services\CoursePresentation\Templates\ImagePool;
use App\Services\CoursePresentation\Templates\SlideDeckAdapter;
use App\Services\StudyDeck\ConceptContextBuilder;
use App\Services\StudyDeck\StudyDeckImages;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Console\Output\BufferedOutput;
use Tests\Unit\StudyDeck\StudyDeckFixture;

/**
 * Data-driven templates: the chosen format opens from the chapter's STORED data, with no AI call and no record created.
 * The adapter is exercised on the study-deck fixture (a synthetic chapter, with real intelligence matching), the picture
 * pool on the real StudyDeckImages class over throwaway sqlite, and the preview endpoint end to end.
 */
class CoursePresentationTemplateTest extends CoursePresentationTestCase
{
    use StudyDeckFixture;

    private function context(array $over = []): array
    {
        $raw = $this->raw();
        foreach ($over as $path => $value) {
            [$k, $i, $f] = explode('.', $path);
            $raw[$k][(int) $i][$f] = $value;
        }

        return (new ConceptContextBuilder())->assemble($raw);
    }

    /** @return array{0:array<string,mixed>,1:array<int,array{what:string,where:string,why:string}>,2:array<string,mixed>} deck, missing, summary */
    private function build(string $method, array $context = null, array $outcomes = [], array $pictures = []): array
    {
        $r = (new SlideDeckAdapter())->build($context ?? $this->context(), $outcomes, $method, $pictures);

        return [$r['deck'], $r['missing'], $r['summary']];
    }

    private function titles(array $deck, ?string $section = null): array
    {
        return array_values(array_map(fn ($s) => $s['title'], array_filter($deck['slides'], fn ($s) => $section === null || $s['section'] === $section)));
    }

    // ------------------------------------------------------------------ the adapter

    // ------------------------------------------------------------------ the picture pool

    private function png(int $shade): string
    {
        $im = imagecreatetruecolor(16, 9);
        imagefill($im, 0, 0, imagecolorallocate($im, $shade, 40, 90));
        ob_start();
        imagepng($im);

        return (string) ob_get_clean();
    }

    private function earlier(array $images, array $planSlides): void
    {
        config(['course_presentation_pilot.earlier_bundle_base' => $this->runDir . '/earlier']);
        $dir = storage_path($this->runDir . '/earlier/chapter-1');   // the fixture's chapter id
        @mkdir($dir, 0775, true);
        file_put_contents($dir . '/images.json', json_encode($images));
        file_put_contents($dir . '/plan.json', json_encode(['slides' => $planSlides]));
    }

    public function test_a_picture_is_offered_only_when_it_was_made_for_the_concept_exists_and_is_grounded_in_the_text(): void
    {
        $store = new StudyDeckImages();
        $ok = $store->put($this->png(10), self::TENANT, 1);
        $weak = $store->put($this->png(20), self::TENANT, 1);
        $foreign = $store->put($this->png(30), self::OTHER_TENANT, 1);
        $photo = $store->put($this->png(40), self::TENANT, 1);
        $photoReviewed = $store->put($this->png(50), self::TENANT, 1);
        $second = $store->put($this->png(60), self::TENANT, 1);
        $diagram = fn ($ref, $texts) => ['type' => 'diagram', 'url' => $ref, 'texts' => $texts, 'alt' => 'x', 'caption' => null];
        $this->earlier([
            '2' => $diagram($ok['ref'], ['Models', 'Map', 'Details']),                                   // grounded: every label is in the textbook text
            '3' => $diagram($weak['ref'], ['Models', 'Quantum chromodynamics', 'Gluon plasma']),        // 1 of 3 labels in the text
            '4' => $diagram($foreign['ref'], ['Models']),                                               // another school's picture
            '5' => $diagram('study-deck-image:424242', ['Models']),                                     // not in the library
            '6' => ['type' => 'photo', 'url' => $photo['ref'], 'alt' => 'x', 'licence' => 'CC0'],      // no relevance review
            '7' => ['type' => 'photo', 'url' => $photoReviewed['ref'], 'alt' => 'x', 'licence' => 'CC0', 'review' => ['ok' => true]],
            '8' => $diagram($second['ref'], ['Models']),                                                // a second picture for a concept that has one
            '9' => $diagram($ok['ref'], ['Models']),                                                    // a slide that taught no chapter concept
        ], [
            ['n' => 2, 'taught_concept_ids' => [1]], ['n' => 3, 'taught_concept_ids' => [2]], ['n' => 4, 'taught_concept_ids' => [3]], ['n' => 5, 'taught_concept_ids' => [4]],
            ['n' => 6, 'taught_concept_ids' => [3]], ['n' => 7, 'taught_concept_ids' => [4]], ['n' => 8, 'taught_concept_ids' => [1]], ['n' => 9, 'taught_concept_ids' => [999]],
        ]);

        $ground = ['ground_by_concept' => [1 => 'Models, a map and details of a model', 2 => 'Models']];
        // By default only verified diagrams are placed: a photograph cannot be checked against the concept's data.
        $default = (new ImagePool())->forChapter($this->context() + $ground, self::TENANT);
        $this->assertSame([1], array_keys($default['by_concept']));
        $this->assertStringContainsString('only verified diagrams are placed', array_column($default['rejected'], 'reason', 'slide')[7]);

        config(['course_presentation_pilot.preview_photographs' => true]);
        $pool = (new ImagePool())->forChapter($this->context() + $ground, self::TENANT);

        $this->assertSame([1, 4], array_keys($pool['by_concept']), 'concept 1 gets the grounded diagram, concept 4 the reviewed, licensed photograph (only when switched on)');
        $this->assertSame($ok['ref'], $pool['by_concept'][1]['url'], 'the first picture for a concept wins');
        $this->assertSame('photo', $pool['by_concept'][4]['type']);
        $reasons = array_column($pool['rejected'], 'reason', 'slide');
        $this->assertStringContainsString('only 1 of 3 diagram labels are found in the concept\'s source data', $reasons[3]);
        $this->assertStringContainsString('not in the library or this school cannot read it', $reasons[4]);
        $this->assertStringContainsString('not in the library', $reasons[5]);
        $this->assertStringContainsString('needs a recorded relevance review', $reasons[6]);
        $this->assertStringContainsString('did not teach a concept of this chapter', $reasons[9]);
        $this->assertSame(8, $pool['considered']);
        $this->assertSame(6, DB::table('study_deck_images')->count(), 'the pool creates no record');
    }

    public function test_the_earlier_drafts_hotspots_are_carried_only_for_a_validated_draft_and_only_when_playable(): void
    {
        $store = new StudyDeckImages();
        $ref = $store->put($this->png(10), self::TENANT, 1)['ref'];
        $spot = fn (string $id) => ['id' => $id, 'label' => 'Part ' . $id, 'x' => 30, 'y' => 40, 'w' => 10, 'h' => 10, 'text' => 'What this part is.', 'image' => ['url' => 'study-deck-image:99']];
        $this->earlier(['2' => ['type' => 'diagram', 'url' => $ref, 'texts' => ['Models'], 'alt' => 'x', 'caption' => null]], [['n' => 2, 'taught_concept_ids' => [1]]]);
        $dir = storage_path($this->runDir . '/earlier/chapter-1');
        $ground = ['ground_by_concept' => [1 => 'Models']];
        $draft = fn (array $spots) => file_put_contents($dir . '/deck.json', json_encode(['slides' => [['n' => 2, 'interaction' => ['kind' => 'hotspots', 'reason' => 'r', 'intro' => 'i', 'spots' => $spots, 'wrapup' => 'w']]]]));

        // Draft passed its own validation, two playable parts: carried, without the parts' own pictures.
        $draft([$spot('a'), $spot('b')]);
        file_put_contents($dir . '/report.json', json_encode(['ok' => true]));
        $got = (new ImagePool())->forChapter($this->context() + $ground, self::TENANT)['by_concept'][1]['interaction'];
        $this->assertSame('hotspots', $got['kind']);
        $this->assertSame(['a', 'b'], array_column($got['spots'], 'id'));
        $this->assertArrayNotHasKey('image', $got['spots'][0]);

        // One part is not an interaction the viewer plays.
        $draft([$spot('a')]);
        $this->assertNull((new ImagePool())->forChapter($this->context() + $ground, self::TENANT)['by_concept'][1]['interaction']);

        // A repeated part id is refused.
        $draft([$spot('a'), $spot('a')]);
        $this->assertNull((new ImagePool())->forChapter($this->context() + $ground, self::TENANT)['by_concept'][1]['interaction']);

        // A draft that did not pass validation contributes the picture's place but never its hotspots.
        $draft([$spot('a'), $spot('b')]);
        file_put_contents($dir . '/report.json', json_encode(['ok' => false]));
        $this->assertNull((new ImagePool())->forChapter($this->context() + $ground, self::TENANT)['by_concept'][1]['interaction']);
    }

    public function test_without_an_earlier_draft_there_is_no_picture_and_nothing_is_attached(): void
    {
        config(['course_presentation_pilot.earlier_bundle_base' => $this->runDir . '/none']);
        $this->assertSame(['by_concept' => [], 'rejected' => [], 'considered' => 0], (new ImagePool())->forChapter($this->context(), self::TENANT));
    }

    // ------------------------------------------------------------------ the preview endpoint

    /** The pilot chapter, with the study-deck fixture as the chapter's stored context. */
    private function seedPreview(): void
    {
        $this->seedPilotChapter();
        // Never the real earlier bundle of the live chapter: pictures come from a scratch folder in these tests.
        config(['course_presentation_pilot.earlier_bundle_base' => $this->runDir . '/no-earlier-draft']);
        $rec = fn (string ...$n) => array_map(fn ($x) => ['strategy' => $x], $n);
        DB::table('semantic_intelligence')->where('id', 13)->update(['full_intelegance_json' => json_encode(['concepts' => array_map(fn ($n) => ['concept' => ['concept_name' => $n], 'pedagogy_recommendations' => $rec('Inquiry Based Teaching', 'Activity Based Teaching')], ['Atom', 'Ion', 'Mole', 'Bond'])])]);
        $raw = $this->raw();
        $raw['chapter'] = ['id' => self::CHAPTER, 'chapter_name' => 'Exploration: Entering the World of Secondary Science', 'standard_id' => self::STANDARD, 'subject_id' => self::SUBJECT, 'standard_name' => '9', 'subject_name' => 'Science', 'sub_institute_id' => self::TENANT];
        $raw['concepts'][0]['definition'] = 'A model is a simplified representation.';
        $this->app->instance(ConceptContextBuilder::class, new class($raw) extends ConceptContextBuilder {
            public function __construct(private array $fixture)
            {
            }

            public function load(int $chapterId, int $tenantId): array
            {
                return $this->fixture;
            }
        });
        // Anything that would start or store a generation is watched.
        $this->commands = [];
        $this->app->instance(GenerationRun::class, new GenerationRun(function (string $c, array $a, BufferedOutput $o): int {
            $this->commands[] = $c;

            return 0;
        }, null, fn (int $chapterId): ?array => null));
    }

    /** @var array<int,string> */
    private array $commands = [];

    private function previewUrl(array $q = []): string
    {
        return '/api/lms/course-presentation/preview?' . http_build_query($q + ['chapter_id' => self::CHAPTER, 'pedagogy' => 'inquiry_based', 'format' => 'interactive_slides']);
    }

    /** An earlier draft for the pilot chapter whose slide 2 taught concept 1 with one picture. */
    private function earlier2(string $ref): void
    {
        config(['course_presentation_pilot.earlier_bundle_base' => $this->runDir . '/earlier']);
        $dir = storage_path($this->runDir . '/earlier/chapter-' . self::CHAPTER);
        mkdir($dir, 0775, true);
        file_put_contents($dir . '/images.json', json_encode(['2' => ['type' => 'diagram', 'url' => $ref, 'texts' => ['Models', 'Map'], 'alt' => 'A model', 'caption' => null]]));
        file_put_contents($dir . '/plan.json', json_encode(['slides' => [['n' => 2, 'taught_concept_ids' => [1]]]]));
    }

    public function test_the_story_format_shows_its_registered_template_and_says_the_story_is_written_per_chapter(): void
    {
        $this->seedPreview();
        \Illuminate\Support\Facades\Schema::create('template_master', function ($t) {
            $t->increments('id');
            $t->integer('sub_institute_id')->nullable();
            $t->string('module_name')->nullable();
            $t->string('title')->nullable();
            $t->text('html_content')->nullable();
            $t->integer('status')->nullable();
            $t->integer('created_by')->nullable();
            $t->timestamp('created_on')->nullable();
        });
        // Built-in first (no row in Template Management), then the stored row.
        $fit = $this->asTeacher()->getJson('/api/lms/course-presentation/fit?' . http_build_query(['chapter_id' => self::CHAPTER, 'pedagogy' => 'experiential', 'format' => 'branching_scenario']))->assertOk()->json('fit');
        $this->assertSame('story_template', $fit['template']['kind']);
        $this->assertFalse($fit['template']['available'], 'a story is written per chapter, so nothing opens from stored data');
        $this->assertSame(['key' => 'story_branching_scenario', 'name' => 'Story-Based Branching Scenario', 'version' => \App\Services\StudyDeck\Story\StoryTemplate::VERSION, 'source' => 'built_in', 'template_id' => null, 'player' => 'study-deck-v3'], $fit['template']['registered']);
        $this->assertStringContainsString('model writes it', $fit['template']['requirements'][0]);

        $id = DB::table('template_master')->insertGetId(['sub_institute_id' => 1, 'module_name' => 'study_deck', 'title' => 'Story-Based Branching Scenario', 'html_content' => \App\Services\StudyDeck\Story\StoryTemplate::html(), 'status' => 1]);
        $fit = $this->asTeacher()->getJson('/api/lms/course-presentation/fit?' . http_build_query(['chapter_id' => self::CHAPTER, 'pedagogy' => 'experiential', 'format' => 'branching_scenario']))->assertOk()->json('fit');
        $this->assertSame(['source' => 'template_master', 'template_id' => $id], ['source' => $fit['template']['registered']['source'], 'template_id' => $fit['template']['registered']['template_id']]);
        // The other formats do not carry a registered template.
        $other = $this->asTeacher()->getJson('/api/lms/course-presentation/fit?' . http_build_query(['chapter_id' => self::CHAPTER, 'pedagogy' => 'inquiry_based', 'format' => 'interactive_slides']))->json('fit');
        $this->assertArrayNotHasKey('registered', $other['template']);
    }

    public function test_formats_without_a_template_are_refused_with_what_they_need_and_whether_create_is_possible(): void
    {
        $this->seedPreview();
        foreach (['scenario_lesson' => 'decision', 'video_lesson' => 'video', 'simulation_activity' => 'simulation', 'worksheet_guide' => 'document', 'socratic_dialogue' => 'dialogue'] as $format => $word) {
            $res = $this->asTeacher()->getJson($this->previewUrl(['format' => $format]))->assertStatus(422);
            $this->assertStringContainsString('no template', strtolower($res->json('message')), $format);
            $this->assertFalse($res->json('template.available'), $format);
            $this->assertStringContainsStringIgnoringCase($word, implode(' ', $res->json('template.requirements')), $format);
            $this->assertSame('Create', $res->json('create.label'));
            $this->assertFalse($res->json('create.available'), "$format cannot be created: no generator can be started for it");
        }
        // The scenario template is not faked from data that holds no decisions.
        $this->assertStringContainsString('without inventing content', implode(' ', $this->asTeacher()->getJson($this->previewUrl(['format' => 'scenario_lesson']))->json('template.requirements')));
    }

    public function test_the_slides_format_offers_create_as_well_when_a_model_is_available(): void
    {
        $this->seedPreview();
        $this->app->instance(\App\Services\CoursePresentation\ModelAvailability::class, new class extends \App\Services\CoursePresentation\ModelAvailability {
            public function available(): bool
            {
                return true;
            }
        });
        $fit = $this->asTeacher()->getJson('/api/lms/course-presentation/fit?' . http_build_query(['chapter_id' => self::CHAPTER, 'pedagogy' => 'inquiry_based', 'format' => 'interactive_slides']))->json('fit');

        $this->assertTrue($fit['template']['available'], 'it opens from data');
        $this->assertTrue($fit['create']['available'], 'and a new version can still be created');
    }

    public function test_the_template_is_unavailable_when_the_chapter_text_is_missing(): void
    {
        $this->seedPreview();
        DB::table('document_extractions')->where('id', 81)->delete();

        $res = $this->asTeacher()->getJson($this->previewUrl())->assertStatus(422);
        $this->assertFalse($res->json('template.available'));
        $this->assertStringContainsString('source material is missing', implode(' ', $res->json('template.blocked_by')));
    }

    public function test_an_unsuitable_combination_gets_no_preview(): void
    {
        $this->seedPreview();
        $res = $this->asTeacher()->getJson($this->previewUrl(['pedagogy' => 'project_based']))->assertStatus(422);
        $this->assertStringContainsString('cannot continue', $res->json('message'));
    }

    public function test_the_preview_is_isolated_to_the_pilot_chapter_and_school_and_ignores_prayogshala(): void
    {
        $this->seedPreview();
        DB::table('chapter_master')->insert(['id' => 8615, 'chapter_name' => 'Other', 'standard_id' => self::STANDARD, 'subject_id' => self::SUBJECT, 'sub_institute_id' => self::TENANT]);

        $this->asTeacher()->getJson($this->previewUrl(['chapter_id' => 8615]))->assertStatus(403);
        $this->asTeacher(self::OTHER_TENANT)->getJson($this->previewUrl())->assertStatus(404);
        $this->asTeacher(self::TENANT, 'Student')->getJson($this->previewUrl())->assertStatus(403);
        $this->asTeacher()->getJson($this->previewUrl(['pedagogy' => 'nope']))->assertStatus(422);
        $this->asTeacher()->getJson($this->previewUrl(['sources' => [1]]))->assertStatus(422);

        // The two stored Prayogshala activities are not part of the deck.
        $deck = $this->asTeacher()->getJson($this->previewUrl())->assertOk()->json('deck');
        $this->assertStringNotContainsStringIgnoringCase('prayogshala', json_encode($deck));
        $this->assertSame(2, DB::table('lms_prayogshala_activity')->where('status', 'review')->count(), 'their status is untouched');
    }
}
