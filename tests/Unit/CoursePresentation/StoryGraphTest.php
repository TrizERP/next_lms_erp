<?php

namespace Tests\Unit\CoursePresentation;

use App\Services\CoursePresentation\PresentationQuality;
use App\Services\StudyDeck\Story\StoryGraph;
use App\Services\StudyDeck\Story\StoryTemplate;
use PHPUnit\Framework\TestCase;

/**
 * NO DATABASE and no framework. The fixture is a HAND-WRITTEN story act for chapter 8592 ("Scientific Models and
 * Simplification", four real concept ids). It is test data: nothing here is evidence that a model generated a story.
 */
class StoryGraphTest extends TestCase
{
    /** @return array{concept_ids:array<int,int>,interaction:array<string,mixed>} */
    private function fixture(): array
    {
        return json_decode((string) file_get_contents(__DIR__ . '/../../Fixtures/story-act-chapter8592.json'), true);
    }

    /** @param callable(array<string,mixed>&):void $mutate */
    private function errorsAfter(callable $mutate, ?array $config = null): array
    {
        $f = $this->fixture();
        $mutate($f['interaction']);

        return StoryGraph::check($f['interaction'], $f['concept_ids'], $config ?? StoryTemplate::defaults())['errors'];
    }

    private function nodeIndex(array $interaction, string $id): int
    {
        foreach ($interaction['nodes'] as $i => $n) {
            if ($n['id'] === $id) {
                return $i;
            }
        }
        $this->fail("no node $id");
    }

    public function test_the_fixture_story_is_valid_and_every_path_teaches_every_concept(): void
    {
        $f = $this->fixture();
        $r = StoryGraph::check($f['interaction'], $f['concept_ids'], StoryTemplate::defaults());

        $this->assertSame([], $r['errors']);
        $this->assertSame(['nodes' => 12, 'scenes' => 8, 'decisions' => 2, 'endings' => 2, 'paths' => 9, 'merges' => 2], $r['stats']);
    }

    public function test_a_choice_that_leads_nowhere_is_a_dead_end(): void
    {
        $errors = $this->errorsAfter(function (array &$i) {
            $i['nodes'][$this->nodeIndex($i, 'd1')]['choices'][0]['next'] = null;
        });

        $this->assertNotEmpty(array_filter($errors, fn ($e) => str_contains($e, 'leads nowhere')));
    }

    public function test_a_choice_to_a_scene_that_does_not_exist_is_refused(): void
    {
        $errors = $this->errorsAfter(function (array &$i) {
            $i['nodes'][$this->nodeIndex($i, 'd2')]['choices'][1]['next'] = 'ghost';
        });

        $this->assertNotEmpty(array_filter($errors, fn ($e) => str_contains($e, 'does not exist')));
    }

    public function test_a_way_back_is_refused_because_the_story_only_moves_forward(): void
    {
        $errors = $this->errorsAfter(function (array &$i) {
            $i['nodes'][$this->nodeIndex($i, 's3')]['choices'][0]['next'] = 'd1';
        });

        $this->assertNotEmpty(array_filter($errors, fn ($e) => str_contains($e, 'only moves forward')));
    }

    public function test_a_scene_nobody_can_reach_is_reported_by_the_shared_graph_rules(): void
    {
        $errors = $this->errorsAfter(function (array &$i) {
            // d1's second choice now skips scene s2b, so s2b is written but unreachable.
            $i['nodes'][$this->nodeIndex($i, 'd1')]['choices'][1]['next'] = 's2a';
        });

        $this->assertNotEmpty(array_filter($errors, fn ($e) => stripos($e, 'reach') !== false || stripos($e, 's2b') !== false), json_encode($errors));
    }

    public function test_a_story_with_no_ending_is_refused(): void
    {
        $errors = $this->errorsAfter(function (array &$i) {
            $i['nodes'] = array_values(array_filter($i['nodes'], fn ($n) => $n['kind'] !== 'ending'));
            $i['nodes'][$this->nodeIndex($i, 's4a')]['choices'][0]['next'] = null;
            $i['nodes'][$this->nodeIndex($i, 's4b')]['choices'][0]['next'] = null;
        });

        $this->assertNotEmpty($errors);
    }

    public function test_a_path_that_skips_a_required_concept_is_named(): void
    {
        $errors = $this->errorsAfter(function (array &$i) {
            // The "bat brand" path no longer teaches concept 7190 anywhere (not even at the decision that opens it).
            foreach (['d2', 's4b', 'e2'] as $id) {
                $i['nodes'][$this->nodeIndex($i, $id)]['concept_ids'] = [7187];
            }
        });

        $this->assertCount(1, $errors);
        $this->assertStringContainsString('never teaches concept 7190', $errors[0]);
        $this->assertStringContainsString('e2', $errors[0], 'the path is named');
    }

    public function test_branches_must_come_back_together(): void
    {
        $errors = $this->errorsAfter(function (array &$i) {
            // Make the story a pure tree: every branch gets its own ending, and no scene is reached from two choices.
            $ending = $i['nodes'][$this->nodeIndex($i, 'e2')];
            unset($ending['variants']);
            foreach (['e3', 'e4', 'e5'] as $id) {
                $i['nodes'][] = ['id' => $id] + $ending;
            }
            unset($i['nodes'][$this->nodeIndex($i, 'e2')]['variants'], $i['nodes'][$this->nodeIndex($i, 's3')]['variants']);
            $i['nodes'][$this->nodeIndex($i, 's2b')]['choices'][0]['next'] = 'e2';
            $i['nodes'][$this->nodeIndex($i, 's2c')]['choices'][0]['next'] = 'e3';
            $i['nodes'][$this->nodeIndex($i, 's4b')]['choices'][0]['next'] = 'e4';
            $i['nodes'][$this->nodeIndex($i, 's4c')]['choices'][0]['next'] = 'e5';
        });

        $this->assertNotEmpty(array_filter($errors, fn ($e) => str_contains($e, 'never come back together')));
    }

    public function test_the_two_choices_of_a_decision_must_give_different_events_and_explanations(): void
    {
        $errors = $this->errorsAfter(function (array &$i) {
            $d = $this->nodeIndex($i, 'd2');
            $i['nodes'][$d]['choices'][1]['outcome'] = $i['nodes'][$d]['choices'][0]['outcome'];
        });

        $this->assertNotEmpty(array_filter($errors, fn ($e) => str_contains($e, 'different story events')));
    }

    public function test_a_story_is_not_a_quiz(): void
    {
        $errors = $this->errorsAfter(function (array &$i) {
            // Turn most scenes into decisions.
            foreach (['s2a', 's2b', 's2c', 's3'] as $id) {
                $k = $this->nodeIndex($i, $id);
                $next = $i['nodes'][$k]['choices'][0]['next'];
                $i['nodes'][$k]['kind'] = 'decision';
                $i['nodes'][$k]['prompt'] = 'What now?';
                $i['nodes'][$k]['choices'] = [
                    ['id' => "$id-x", 'text' => 'One way', 'outcome' => "event $id x", 'why' => "why $id x", 'sound' => true, 'next' => $next],
                    ['id' => "$id-y", 'text' => 'Another way', 'outcome' => "event $id y", 'why' => "why $id y", 'sound' => false, 'next' => $next],
                ];
            }
        });

        $this->assertNotEmpty(array_filter($errors, fn ($e) => str_contains($e, 'not a quiz')));
    }

    public function test_each_kind_needs_the_parts_it_is_made_of(): void
    {
        $scene = $this->errorsAfter(function (array &$i) {
            $k = $this->nodeIndex($i, 's2a');
            $i['nodes'][$k]['choices'][] = $i['nodes'][$k]['choices'][0];
        });
        $this->assertNotEmpty(array_filter($scene, fn ($e) => str_contains($e, 'exactly one way on')));

        $ending = $this->errorsAfter(function (array &$i) {
            $i['nodes'][$this->nodeIndex($i, 'e1')]['choices'] = [['id' => 'x', 'text' => 'More', 'outcome' => 'a', 'why' => 'b', 'sound' => true, 'next' => null]];
        });
        $this->assertNotEmpty(array_filter($ending, fn ($e) => str_contains($e, 'must have no choices')));

        $noMission = $this->errorsAfter(function (array &$i) {
            unset($i['mission']);
        });
        $this->assertNotEmpty(array_filter($noMission, fn ($e) => str_contains($e, 'needs a mission')));

        $opening = $this->errorsAfter(function (array &$i) {
            $i['start'] = 'd1';
        });
        $this->assertNotEmpty(array_filter($opening, fn ($e) => str_contains($e, 'start at its first scene')));
    }

    public function test_narration_must_be_a_real_scene_and_a_picture_must_be_drawable(): void
    {
        $short = $this->errorsAfter(function (array &$i) {
            $i['nodes'][$this->nodeIndex($i, 's2a')]['body'] = 'Too short.';
        });
        $this->assertNotEmpty(array_filter($short, fn ($e) => str_contains($e, 'narrates only 2 words')));

        $picture = $this->errorsAfter(function (array &$i) {
            $i['nodes'][$this->nodeIndex($i, 's1')]['image']['alt'] = '';
        });
        $this->assertNotEmpty(array_filter($picture, fn ($e) => str_contains($e, 'alternative text')));
    }

    public function test_a_story_made_with_another_template_is_not_judged_as_one(): void
    {
        $errors = $this->errorsAfter(function (array &$i) {
            $i['template'] = 'something_else';
        });

        $this->assertStringContainsString('not made with the', $errors[0]);
    }

    public function test_the_draft_quality_gate_reports_a_broken_story_and_passes_a_good_one(): void
    {
        $f = $this->fixture();
        $slide = ['n' => 2, 'slide_type' => 'scenario', 'title' => 'Predict the shot', 'concept_ids' => $f['concept_ids'], 'taught_concept_ids' => $f['concept_ids'],
            'content' => ['body' => 'A story.', 'explanations' => [], 'bullets' => []], 'interaction' => $f['interaction'], 'image' => null];
        $deck = fn (array $s) => ['version' => 3, 'slides' => [$s], 'concepts' => array_combine(array_map('strval', $f['concept_ids']), array_map(fn ($c) => ['name' => "C$c"], $f['concept_ids']))];

        $good = (new PresentationQuality())->check($deck($slide));
        $this->assertSame([], array_values(array_filter($good, fn ($p) => in_array($p['code'], ['broken_story', 'broken_interaction', 'generic_label'], true))), json_encode($good));

        $slide['interaction']['nodes'][3]['choices'][0]['next'] = null;
        $bad = (new PresentationQuality())->check($deck($slide));
        $this->assertContains('broken_story', array_column($bad, 'code'));
    }

    public function test_numbers_in_scene_titles_are_fine_but_numbered_placeholders_are_not(): void
    {
        $slide = ['n' => 2, 'slide_type' => 'scenario', 'title' => 'Predict the shot', 'concept_ids' => [], 'taught_concept_ids' => [],
            'content' => ['body' => 'A story.', 'explanations' => [], 'bullets' => []], 'image' => null];
        $f = $this->fixture();
        $slide['interaction'] = $f['interaction'];
        $slide['interaction']['nodes'][0]['title'] = 'Scene 1';
        $slide['interaction']['nodes'][1]['title'] = 'Newton\'s 2nd law in the nets';

        $codes = array_column((new PresentationQuality())->check(['version' => 3, 'slides' => [$slide], 'concepts' => []]), 'message', 'code');

        $this->assertArrayNotHasKey('generic_label', $codes, '"Scene 1" is a descriptive title in a story, not one of the numbered placeholders');
    }
}
