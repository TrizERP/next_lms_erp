<?php

namespace Tests\Feature\CoursePresentation;

use App\Services\StudyDeck\DeckValidator;
use App\Services\StudyDeck\Story\StoryGraph;
use App\Services\StudyDeck\Story\StoryTemplate;
use App\Services\StudyDeck\StudyDeckImages;
use App\Services\StudyDeck\StudyDeckImageUrls;
use App\Services\StudyDeck\StudyDeckPublisher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The Story-Based Branching Scenario template in Template Management (`template_master`), and a story scene's own picture going
 * through the existing picture store, signed addresses and publisher. Throwaway sqlite only; the shared database is never touched.
 * The story is a HAND-WRITTEN fixture (tests/Fixtures/story-act-chapter8592.json), not generated content.
 */
class StoryTemplateTest extends CoursePresentationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::create('template_master', function ($t) {
            $t->increments('id');
            $t->integer('sub_institute_id')->nullable();
            $t->string('module_name')->nullable();
            $t->string('title')->nullable();
            $t->text('html_content')->nullable();
            $t->integer('status')->nullable();
            $t->integer('created_by')->nullable();
            $t->timestamp('created_on')->nullable();
        });
    }

    private function fixture(): array
    {
        return json_decode((string) file_get_contents(__DIR__ . '/../../Fixtures/story-act-chapter8592.json'), true);
    }

    private function row(array $over = []): int
    {
        return DB::table('template_master')->insertGetId($over + ['sub_institute_id' => 1, 'module_name' => StoryTemplate::MODULE, 'title' => StoryTemplate::NAME, 'html_content' => StoryTemplate::html(), 'status' => 1]);
    }

    private function png(int $shade): string
    {
        $im = imagecreatetruecolor(8, 4);
        imagefill($im, 0, 0, imagecolorallocate($im, $shade, 40, 90));
        ob_start();
        imagepng($im);

        return (string) ob_get_clean();
    }

    // ------------------------------------------------------------------ the template in Template Management

    public function test_without_a_stored_row_the_built_in_template_is_used(): void
    {
        $r = StoryTemplate::resolve(1);

        $this->assertSame(StoryTemplate::BUILT_IN, $r['source']);
        $this->assertNull($r['template_id']);
        $this->assertSame(StoryTemplate::defaults(), $r['config']);
    }

    public function test_the_stored_design_document_round_trips_and_is_found_by_title_and_module(): void
    {
        $id = $this->row();
        $r = StoryTemplate::resolve(1);

        $this->assertSame(StoryTemplate::STORED, $r['source']);
        $this->assertSame($id, $r['template_id']);
        $this->assertSame(StoryTemplate::defaults(), $r['config']);
        // The document is real, printable HTML that names the player it is bound to.
        $html = (string) DB::table('template_master')->where('id', $id)->value('html_content');
        $this->assertStringContainsString('data-player="study-deck-v3"', $html);
        $this->assertStringContainsString('data-part="ending"', $html);
    }

    public function test_a_school_uses_its_own_row_else_the_platform_row_and_ignores_unpublished_or_other_modules(): void
    {
        $platform = StoryTemplate::defaults();
        $this->row(['sub_institute_id' => 1]);
        $own = $platform;
        $own['nodes'] = [8, 12];
        $ownId = $this->row(['sub_institute_id' => 7, 'html_content' => StoryTemplate::html($own)]);
        $this->row(['sub_institute_id' => 7, 'status' => 0, 'html_content' => StoryTemplate::html(array_replace($platform, ['nodes' => [1, 2]]))]);
        $this->row(['sub_institute_id' => 7, 'module_name' => 'fees', 'html_content' => StoryTemplate::html(array_replace($platform, ['nodes' => [3, 4]]))]);

        $this->assertSame([8, 12], StoryTemplate::resolve(7)['config']['nodes']);
        $this->assertSame($ownId, StoryTemplate::resolve(7)['template_id']);
        $this->assertSame($platform['nodes'], StoryTemplate::resolve(2)['config']['nodes'], 'a school with no row of its own gets the platform copy');
    }

    public function test_only_the_documented_settings_can_be_changed_and_only_when_well_formed(): void
    {
        $bad = StoryTemplate::defaults();
        $bad['nodes'] = [20, 5];
        $bad['decision_share_max'] = 7;
        $bad['player'] = 'something-else';
        $bad['parts'] = ['quiz'];
        $this->row(['html_content' => StoryTemplate::html($bad)]);
        $r = StoryTemplate::resolve(1)['config'];

        $this->assertSame(StoryTemplate::defaults()['nodes'], $r['nodes'], 'a malformed budget is ignored');
        $this->assertSame(StoryTemplate::defaults()['decision_share_max'], $r['decision_share_max']);
        $this->assertSame('study-deck-v3', $r['player'], 'the binding to the player cannot be edited away');
        $this->assertSame(StoryTemplate::defaults()['parts'], $r['parts']);
    }

    public function test_a_document_that_is_not_this_template_is_not_used(): void
    {
        $this->row(['html_content' => '<p>An ordinary letter template</p>']);

        $this->assertSame(StoryTemplate::BUILT_IN, StoryTemplate::resolve(1)['source']);
        $this->assertNull(StoryTemplate::parse('<div data-template-key="other" data-config="{}"></div>'));
    }

    public function test_an_edited_budget_in_template_management_changes_what_the_validator_accepts(): void
    {
        $f = $this->fixture();
        $this->assertSame([], StoryGraph::check($f['interaction'], $f['concept_ids'], StoryTemplate::resolve(1)['config'])['errors']);

        $tight = StoryTemplate::defaults();
        $tight['nodes'] = [6, 8];
        $this->row(['html_content' => StoryTemplate::html($tight)]);
        $errors = StoryGraph::check($f['interaction'], $f['concept_ids'], StoryTemplate::resolve(1)['config'])['errors'];

        $this->assertNotEmpty(array_filter($errors, fn ($e) => str_contains($e, '12 nodes')));
    }

    public function test_the_command_registers_the_template_once_and_never_overwrites_an_edited_row(): void
    {
        $this->artisan('lms:seed-story-template', ['--dry-run' => true])->expectsOutputToContain('Would register')->assertSuccessful();
        $this->assertSame(0, DB::table('template_master')->count(), 'a dry run writes nothing');

        $this->artisan('lms:seed-story-template')->expectsOutputToContain('Registered: template_master id')->assertSuccessful();
        $row = DB::table('template_master')->first();
        $this->assertSame([StoryTemplate::MODULE, StoryTemplate::NAME, 1, 1], [$row->module_name, $row->title, $row->status, $row->sub_institute_id]);
        $this->assertSame(StoryTemplate::defaults(), StoryTemplate::parse($row->html_content));

        // Run again: the existing row is left alone, even if a school edited it in Template Management.
        DB::table('template_master')->where('id', $row->id)->update(['html_content' => str_replace('data-version="2"', 'data-version="2" data-edited="yes"', $row->html_content)]);
        $this->artisan('lms:seed-story-template')->expectsOutputToContain('Already registered')->assertSuccessful();
        $this->assertSame(1, DB::table('template_master')->count());
        $this->assertStringContainsString('data-edited="yes"', (string) DB::table('template_master')->value('html_content'));

        // --refresh replaces only a row that still holds this template's own document.
        $this->artisan('lms:seed-story-template', ['--refresh' => true])->assertSuccessful();
        $this->assertStringNotContainsString('data-edited', (string) DB::table('template_master')->value('html_content'));
        DB::table('template_master')->update(['html_content' => '<p>someone else\'s document</p>']);
        $this->artisan('lms:seed-story-template', ['--refresh' => true])->assertFailed();
        $this->assertSame('<p>someone else\'s document</p>', DB::table('template_master')->value('html_content'));
    }

    // ------------------------------------------------------------------ the deck validator

    public function test_the_deck_validator_applies_the_story_rules_to_a_story_slide_and_not_the_scenario_cap(): void
    {
        $f = $this->fixture();
        $slides = [];
        for ($n = 1; $n <= 5; $n++) {   // five story acts: far more than the "few scenarios" cap of an ordinary deck
            $slides[] = ['n' => $n, 'slide_type' => 'scenario', 'title' => "Act $n", 'taught_concept_ids' => $f['concept_ids'], 'interaction' => $f['interaction']];
        }
        $run = function (array $slides): array {
            $errors = [];
            $warnings = [];
            $m = new \ReflectionMethod(DeckValidator::class, 'interactionRules');
            $m->invokeArgs(new DeckValidator(), [['slides' => $slides], &$errors, &$warnings]);

            return $errors;
        };

        $this->assertSame([], $run($slides));

        $slides[2]['interaction']['nodes'][1]['choices'][0]['next'] = null;
        $errors = $run($slides);
        $this->assertCount(1, array_filter($errors, fn ($e) => str_starts_with($e, 'Slide 3:') && str_contains($e, 'leads nowhere')));
    }

    // ------------------------------------------------------------------ a scene's own picture

    private function storyDeckWithPictures(array $refs): array
    {
        $f = $this->fixture();
        $i = $f['interaction'];
        foreach (['s1', 'd1', 's4a'] as $k => $id) {
            foreach ($i['nodes'] as $x => $node) {
                if ($node['id'] === $id) {
                    $i['nodes'][$x]['image']['url'] = $refs[$k];
                }
            }
        }

        return ['version' => 3, 'slides' => [['n' => 1, 'slide_type' => 'cover', 'title' => 'Chapter', 'image' => null, 'interaction' => null],
            ['n' => 2, 'slide_type' => 'scenario', 'title' => 'Act', 'image' => null, 'interaction' => $i]]];
    }

    public function test_a_scene_picture_gets_a_signed_address_only_when_the_school_may_see_it(): void
    {
        $store = new StudyDeckImages();
        $mine = $store->put($this->png(10), self::TENANT, self::CHAPTER);
        $theirs = $store->put($this->png(20), self::OTHER_TENANT, self::CHAPTER);
        $deck = $this->storyDeckWithPictures([$mine['ref'], $theirs['ref'], $mine['ref']]);

        $out = (new StudyDeckImageUrls())->hydrate($deck, self::TENANT);
        $nodes = array_column($out['slides'][1]['interaction']['nodes'], null, 'id');

        $this->assertStringContainsString('/api/study-deck/images/' . $mine['id'], $nodes['s1']['image']['url']);
        $this->assertStringContainsString('/api/study-deck/images/' . $mine['id'], $nodes['s4a']['image']['url']);
        $this->assertSame($theirs['ref'], $nodes['d1']['image']['url'], 'another school\'s picture stays a reference that loads nothing');
    }

    public function test_publishing_stores_maps_and_links_every_scene_picture_and_refuses_a_local_path(): void
    {
        $store = new StudyDeckImages();
        $a = $store->put($this->png(11), self::TENANT, self::CHAPTER);
        $b = $store->put($this->png(22), self::TENANT, self::CHAPTER);
        $deck = $this->storyDeckWithPictures([$a['ref'], $b['ref'], $a['ref']]);

        $prepared = (new StudyDeckPublisher($store, self::TENANT))->prepare($deck, '', null, false);

        $this->assertCount(2, $prepared['assets'], 'one asset per distinct picture');
        $this->assertEqualsCanonicalizing([$a['id'], $b['id']], StudyDeckPublisher::imageIds($prepared), 'so both are linked to the stored deck and not pruned as orphans');
        foreach (['s1', 'd1', 's4a'] as $id) {
            $node = array_column($prepared['deck']['slides'][1]['interaction']['nodes'], null, 'id')[$id];
            $this->assertNotEmpty($node['image']['asset_id'], "$id carries its asset id");
        }

        $bad = $this->storyDeckWithPictures([$a['ref'], 'C:\\work\\scene.png', $a['ref']]);
        $this->expectException(\RuntimeException::class);
        (new StudyDeckPublisher($store, self::TENANT))->prepare($bad, '', null, false);
    }
    public function test_a_template_row_written_for_an_older_version_is_not_applied(): void
    {
        $old = StoryTemplate::defaults();
        $old['version'] = 1;
        $old['scene_words'] = [1, 5];
        $this->row(['html_content' => StoryTemplate::html($old)]);

        $resolved = StoryTemplate::resolve(1);

        $this->assertSame(StoryTemplate::BUILT_IN, $resolved['source'], 'the version 1 row no longer fits the design');
        $this->assertSame([45, 170], $resolved['config']['scene_words']);
    }
}
