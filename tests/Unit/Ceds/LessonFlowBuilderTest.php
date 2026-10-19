<?php

namespace Tests\Unit\Ceds;

use App\Services\Ceds\CedsFitService;
use App\Services\Ceds\LessonFlowBuilder;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Tests\Support\CedsFlowFixtures;

/** No application boot and no database: plain PHPUnit over the config file and the stored-sidecar fixtures. */
class LessonFlowBuilderTest extends TestCase
{
    private array $config;
    private LessonFlowBuilder $flow;

    /** The provisional timings for a 40-minute period, as proposed in the lesson-flow review. */
    private const APPROVED_40 = [
        'P1' => [4, 10, 8, 8, 6, 4],
        'P2' => [4, 12, 10, 8, 6],
        'P3' => [4, 4, 12, 8, 6, 6],
        'P4' => [0, 10, 20, 10],
        'P5' => [6, 16, 6, 6, 6],
        'P6' => [4, 10, 8, 8, 6, 4],
        'P7' => [4, 8, 16, 8, 4],
        'P8' => [6, 10, 12, 6, 6],
    ];

    protected function setUp(): void
    {
        $this->config = require __DIR__ . '/../../../config/ceds_fit_matrix.php';
        $this->flow = new LessonFlowBuilder(new CedsFitService($this->config), $this->config);
    }

    private function build(string $method, int $topic = 23810, array $material = null, int $period = 40, ?array $minutes = null): array
    {
        return $this->flow->build($method, $topic, $period, $material ?? CedsFlowFixtures::material(), $minutes);
    }

    private function phase(array $flow, string $name): array
    {
        return collect($flow['phases'])->firstWhere('name', $name);
    }

    // ------------------------------------------------------------------ the eight methods and timings

    public function test_all_eight_methods_have_the_approved_timings_for_forty_minutes(): void
    {
        foreach (self::APPROVED_40 as $method => $minutes) {
            $this->assertSame($minutes, $this->flow->defaultMinutes($method, 40), $method);
            $this->assertSame(40, array_sum($minutes), $method);
        }
    }

    public function test_every_method_sums_to_any_period_the_teacher_picks(): void
    {
        foreach (array_keys(self::APPROVED_40) as $method) {
            foreach ([10, 25, 30, 35, 45, 50, 55, 60, 90, 120] as $period) {
                $this->assertSame($period, array_sum($this->flow->defaultMinutes($method, $period)), "$method @ $period");
            }
        }
    }

    public function test_phases_are_in_the_ceds_order_with_one_role_entry_each(): void
    {
        $fit = new CedsFitService($this->config);
        foreach (array_keys(self::APPROVED_40) as $method) {
            $phases = $fit->phases($method);
            $this->assertCount(count($phases), $this->config['phase_roles'][$method], "$method roles line up with phases");
            $flow = $this->build($method);
            $this->assertSame(array_column($phases, 0), array_column($flow['phases'], 'name'), $method);
            $this->assertSame(range(1, count($phases)), array_column($flow['phases'], 'index'));
            $this->assertSame($this->flow->defaultMinutes($method, 40), array_column($flow['phases'], 'minutes'));
            $this->assertSame([], $flow['warnings'], "$method has no flow warning by default");
        }
    }

    public function test_flipped_pre_class_input_is_outside_the_period(): void
    {
        $p = $this->phase($this->build('P4'), 'Pre-class input with check');
        $this->assertTrue($p['outside_period']);
        $this->assertSame(0, $p['minutes']);
        $this->assertSame([], $p['warnings'], 'no overrun against a phase that is outside the period');
    }

    // ------------------------------------------------------------------------- mapping, P2 sample

    public function test_p2_maps_the_sample_topic_to_its_verified_resources(): void
    {
        $flow = $this->build('P2');

        $engage = $this->phase($flow, 'Engage');
        $this->assertSame([2], array_column($engage['resources'], 'slide'), 'the deck hook slide');
        $this->assertSame('chapter', $engage['resources'][0]['scope'], 'the hook is the chapter\'s, and says so');

        $explore = $this->phase($flow, 'Explore');
        $this->assertSame(['activity', 'lab'], array_column($explore['resources'], 'type'));
        $this->assertSame('Model the cricket six', $explore['resources'][0]['title']);
        $this->assertSame(61404, $explore['resources'][0]['content_id']);

        $explain = $this->phase($flow, 'Explain');
        $this->assertSame([7, 8, 10], array_column($explain['resources'], 'slide'));

        $elaborate = $this->phase($flow, 'Elaborate');
        $this->assertSame([9], array_column($elaborate['resources'], 'slide'));

        $evaluate = $this->phase($flow, 'Evaluate');
        $this->assertSame(['questions', 'notes', 'notes', 'notes', 'notes'], array_column($evaluate['resources'], 'type'));
        $this->assertSame([701403, 701405], $evaluate['resources'][0]['question_ids']);
        $this->assertSame([7, 9], $evaluate['resources'][0]['on_slides']);
        $this->assertSame([4, 5, 6, 7], array_column(array_slice($evaluate['resources'], 1), 'section'));
    }

    public function test_slides_and_sections_of_other_topics_are_never_attached(): void
    {
        $flow = $this->build('P2');
        $all = collect($flow['phases'])->pluck('resources')->flatten(1);
        $slides = $all->where('type', 'slide')->pluck('slide')->all();
        foreach ([4, 5, 6, 16, 17, 18, 19] as $other) {
            $this->assertNotContains($other, $slides, "slide $other belongs to another topic");
        }
        $this->assertNotContains('Magnifying glass and compass', $all->pluck('title')->all(), 'activity of topic 23809');
        $this->assertNotContains(5, $all->where('type', 'lab')->pluck('id')->all(), 'lab of topic 23809');
    }

    public function test_the_stored_activity_longer_than_its_phase_warns_and_is_not_shortened(): void
    {
        $explore = $this->phase($this->build('P2'), 'Explore');

        $this->assertCount(1, $explore['warnings']);
        $this->assertSame('overrun', $explore['warnings'][0]['code']);
        $this->assertSame(25, $explore['warnings'][0]['stored_minutes']);
        $this->assertSame(12, $explore['warnings'][0]['phase_minutes']);
        $this->assertSame(25, $explore['resources'][0]['stored_minutes'], 'the resource is reported as stored, never re-timed');
    }

    public function test_stored_slides_add_up_against_their_phase_and_follow_the_teachers_minutes(): void
    {
        $this->assertSame([], $this->phase($this->build('P2'), 'Explain')['warnings'], '9 stored minutes fit 10');

        $short = $this->build('P2', 23810, null, 40, [4, 12, 6, 8, 6]);
        $explain = $this->phase($short, 'Explain');
        $this->assertSame(6, $explain['minutes']);
        $this->assertSame(10, $explain['suggested_minutes'], 'the suggestion is kept beside the teacher\'s value');
        $this->assertSame(9, $explain['warnings'][0]['stored_minutes']);
        $this->assertSame([7, 8, 10], array_column($explain['resources'], 'slide'), 'the slides are not trimmed or reordered');
    }

    public function test_a_period_the_phases_do_not_add_up_to_is_flagged(): void
    {
        $flow = $this->build('P2', 23810, null, 40, [4, 12, 10, 8, 10]);
        $this->assertSame(44, $flow['planned_minutes']);
        $this->assertSame('period_mismatch', $flow['warnings'][0]['code']);
    }

    public function test_minutes_must_match_the_phases(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->build('P2', 23810, null, 40, [10, 10]);
    }

    // ------------------------------------------------------------------ labs stay unapproved

    public function test_a_lab_in_review_is_pending_review_and_never_approved(): void
    {
        $lab = $this->phase($this->build('P2'), 'Explore')['resources'][1];
        $this->assertSame('lab', $lab['type']);
        $this->assertSame('pending_review', $lab['status']);
        $this->assertSame('Pending Review', $lab['review_label']);
    }

    // -------------------------------------------------------------- P5, P6, P7 and teacher-led

    public function test_p7_shows_every_phase_with_no_forced_mapping(): void
    {
        $flow = $this->build('P7');
        $this->assertCount(5, $flow['phases']);
        foreach ($flow['phases'] as $p) {
            $this->assertTrue($p['teacher_led'], $p['name']);
            $this->assertSame([], $p['resources']);
            $this->assertSame('no_stored_equivalent', $p['teacher_led_reason']);
        }
    }

    public function test_p6_maps_only_the_misconception_phase_and_only_with_a_verified_slide(): void
    {
        $with = $this->build('P6', 23812);
        $this->assertSame([19], array_column($this->phase($with, 'Surface misconception')['resources'], 'slide'));
        foreach (['Opening question', 'Probe', 'Challenge', 'Refine', 'Student summary'] as $name) {
            $this->assertTrue($this->phase($with, $name)['teacher_led'], $name);
        }

        $without = $this->build('P6', 23810);
        $p = $this->phase($without, 'Surface misconception');
        $this->assertTrue($p['teacher_led']);
        $this->assertSame('none_found', $p['teacher_led_reason'], 'a role exists, but this topic has no such slide');
    }

    public function test_p5_maps_only_the_group_task_and_the_individual_check(): void
    {
        $flow = $this->build('P5');
        $this->assertSame(['Model the cricket six', 'Same Ball, Different Questions'], array_column($this->phase($flow, 'Group task with roles')['resources'], 'title'), 'the topic activity and its lab (pending review), nothing else');
        $this->assertSame('questions', $this->phase($flow, 'Individual check')['resources'][0]['type']);
        foreach (['Individual think', 'Share', 'Synthesise'] as $name) {
            $this->assertTrue($this->phase($flow, $name)['teacher_led'], $name);
        }
    }

    public function test_every_resource_has_the_common_fields_the_page_reads(): void
    {
        foreach (array_keys(self::APPROVED_40) as $method) {
            foreach ([23809, 23810, 23812] as $topic) {
                foreach ($this->build($method, $topic)['phases'] as $p) {
                    foreach ($p['resources'] as $r) {
                        foreach (['type', 'title', 'status', 'review_label', 'stored_minutes'] as $key) {
                            $this->assertArrayHasKey($key, $r, "$method/$topic {$p['name']} {$r['type']} lacks $key");
                        }
                    }
                }
            }
        }
    }

    public function test_every_mapped_resource_carries_a_checkable_id(): void
    {
        foreach (array_keys(self::APPROVED_40) as $method) {
            foreach ([23809, 23810, 23812] as $topic) {
                foreach ($this->build($method, $topic)['phases'] as $p) {
                    foreach ($p['resources'] as $r) {
                        $this->assertNotEmpty($r['content_id'] ?? $r['id'] ?? null, "$method/$topic {$p['name']} {$r['type']}");
                        if ($r['type'] === 'slide') {
                            $this->assertSame(61401, $r['content_id']);
                            $this->assertGreaterThan(0, $r['slide']);
                        }
                    }
                }
            }
        }
    }

    // ------------------------------------------------------------- missing, partial, unreadable

    public function test_a_topic_with_nothing_stored_is_teacher_led_throughout(): void
    {
        $flow = $this->build('P1', 99999);
        foreach ($flow['phases'] as $p) {
            if ($p['name'] === 'Hook') {
                $this->assertSame([2], array_column($p['resources'], 'slide'), 'the chapter hook is still the chapter\'s');
                continue;
            }
            $this->assertTrue($p['teacher_led'], $p['name']);
        }
    }

    public function test_partial_resources_map_what_exists_and_leave_the_rest_teacher_led(): void
    {
        $flow = $this->build('P2', 23810, CedsFlowFixtures::material(['activities' => null, 'activities_id' => null, 'labs' => []]));
        $explore = $this->phase($flow, 'Explore');
        $this->assertTrue($explore['teacher_led']);
        $this->assertSame('none_found', $explore['teacher_led_reason']);
        $this->assertSame([7, 8, 10], array_column($this->phase($flow, 'Explain')['resources'], 'slide'), 'the deck part still maps');
    }

    public function test_without_a_deck_no_slide_is_claimed(): void
    {
        $flow = $this->build('P2', 23810, CedsFlowFixtures::material(['deck' => null, 'deck_id' => null]));
        $this->assertTrue($this->phase($flow, 'Engage')['teacher_led']);
        $this->assertTrue($this->phase($flow, 'Explain')['teacher_led']);
        $this->assertSame('Model the cricket six', $this->phase($flow, 'Explore')['resources'][0]['title'], 'activities still map by topic id');
    }

    public function test_an_unreadable_sidecar_is_reported_not_hidden(): void
    {
        $flow = $this->build('P2', 23810, CedsFlowFixtures::material(['deck' => null, 'deck_id' => null, 'unreadable' => ['presentation']]));
        $this->assertSame('unreadable', $flow['warnings'][0]['code']);
        $this->assertStringContainsString('presentation', $flow['warnings'][0]['message']);
        $this->assertSame('resource_unreadable', $this->phase($flow, 'Explain')['teacher_led_reason']);
    }

    // ----------------------------------------------------------------------------------- topics

    public function test_topics_list_what_is_stored_for_each(): void
    {
        $topics = collect($this->flow->topics(CedsFlowFixtures::material()))->keyBy('id');
        $this->assertSame([23809, 23810, 23812], $topics->keys()->all());
        $this->assertSame(['slides' => 4, 'activities' => 1, 'notes' => 4, 'labs' => 1], collect($topics[23810])->only(['slides', 'activities', 'notes', 'labs'])->all());
        $this->assertSame([7187, 7188, 7189, 7190], $topics[23810]['concept_ids']);
    }

    public function test_building_does_not_change_its_input(): void
    {
        $material = CedsFlowFixtures::material();
        $before = serialize($material);
        $this->build('P2', 23810, $material);
        $this->build('P8', 23812, $material);
        $this->assertSame($before, serialize($material));
    }
}
