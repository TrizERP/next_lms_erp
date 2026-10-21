<?php

namespace Tests\Feature\CoursePresentation;

use App\Services\CoursePresentation\PresentationQuality;
use App\Services\StudyDeck\ActivityPlanner;
use App\Services\StudyDeck\ConceptContextBuilder;
use App\Services\StudyDeck\DeckValidator;
use App\Services\StudyDeck\ImagePlanner;
use App\Services\StudyDeck\LearningPlanBuilder;
use App\Services\StudyDeck\QuestionSelector;
use App\Services\StudyDeck\SlideContentGenerator;
use App\Services\StudyDeck\SlideHtmlRenderer;
use App\Services\StudyDeck\SlidePlanner;
use App\Services\StudyDeck\Story\StoryActs;
use App\Services\StudyDeck\Story\StoryGraph;
use App\Services\StudyDeck\Story\StoryLessonGenerator;
use App\Services\StudyDeck\Story\StorySkeleton;
use App\Services\StudyDeck\Story\StoryTemplate;
use App\Services\StudyDeck\StudyDeckService;
use Tests\Unit\StudyDeck\StudyDeckFixture;

/**
 * The story generator (story bible, checked skeleton per act, written and illustrated act, assembly, story-mode validation), run on a
 * small synthetic chapter with a SCRIPTED completer.
 *
 * What this proves, and what it does not: it proves the code around the model (prompts carry the chapter's own data, every reply is
 * checked, a bad reply is sent back with the exact reasons, a skeleton or story that never passes stops the run, the assembled deck
 * passes the existing renderer and the story-mode validator). The replies are written by this test. NONE of it is evidence that a real
 * model can write a good story: no model was called.
 */
class StoryGenerationTest extends CoursePresentationTestCase
{
    use StudyDeckFixture;

    private function chapter(): array
    {
        $raw = $this->raw();
        $defs = [1 => 'A model is a simplified representation of a real system.', 2 => 'A model ignores some details on purpose.', 3 => 'A law describes a repeated pattern.', 4 => 'A theory explains why patterns occur.'];
        foreach ($raw['concepts'] as $i => $c) {
            $raw['concepts'][$i]['definition'] = $defs[$c['id']];
        }

        return $raw;
    }

    // ---------------------------------------------------------------- scripted replies

    /** A valid skeleton for an act that must teach $required on every path (the merge scene s3 carries them all). Three choices at both decisions. */
    private function skeleton(string $title, array $required, ?array $visual = null, ?array $queries = null, ?string $step = null): array
    {
        $r0 = [$required[0]];

        return ['title' => $title, 'premise' => 'The team is back at the school ground with a new question to settle.', 'nodes' => [
            ['id' => 's1', 'kind' => 'scene', 'purpose' => 'situation', 'concept_ids' => [], 'covers' => 'Where the team is and what they must settle.', 'next' => 'd1'],
            ['id' => 'd1', 'kind' => 'decision', 'purpose' => 'investigate', 'concept_ids' => [], 'asks' => 'How should the team start?', 'choices' => [
                ['intent' => 'start with what the question needs', 'quality' => 'best', 'next' => 's2a'],
                ['intent' => 'collect the details that are easiest to reach', 'quality' => 'partial', 'next' => 's2b'],
                ['intent' => 'copy down every detail of the place', 'quality' => 'misstep', 'next' => 's2c'],
            ]],
            ['id' => 's2a', 'kind' => 'scene', 'purpose' => 'consequence', 'concept_ids' => $r0, 'covers' => 'The focused start works.', 'next' => 's3'] + ($visual ? ['visual' => $visual] : []) + ($queries ? ['image_queries' => $queries] : []) + ($step ? ['process_step' => $step] : []),
            ['id' => 's2b', 'kind' => 'scene', 'purpose' => 'remedial', 'concept_ids' => $r0, 'covers' => 'Easy details leave a gap.', 'next' => 's3'],
            ['id' => 's2c', 'kind' => 'scene', 'purpose' => 'remedial', 'concept_ids' => $r0, 'covers' => 'Why copying everything does not help.', 'next' => 's3'],
            ['id' => 's3', 'kind' => 'scene', 'purpose' => 'explanation', 'concept_ids' => $required, 'covers' => 'What the chapter says, for every path.', 'next' => 'd2'],
            ['id' => 'd2', 'kind' => 'decision', 'purpose' => 'apply', 'concept_ids' => [], 'asks' => 'What should the team do next?', 'choices' => [
                ['intent' => 'apply the idea to the new case', 'quality' => 'best', 'next' => 's4a'],
                ['intent' => 'check the idea on the old case first', 'quality' => 'partial', 'next' => 's4b'],
                ['intent' => 'repeat the old method as it was', 'quality' => 'misstep', 'next' => 's4c'],
            ]],
            ['id' => 's4a', 'kind' => 'scene', 'purpose' => 'consequence', 'concept_ids' => $r0, 'covers' => 'The idea carries over.', 'next' => 'e1'],
            ['id' => 's4b', 'kind' => 'scene', 'purpose' => 'remedial', 'concept_ids' => $r0, 'covers' => 'Checking first costs time but shows a limit.', 'next' => 'e2'],
            ['id' => 's4c', 'kind' => 'scene', 'purpose' => 'remedial', 'concept_ids' => $r0, 'covers' => 'Why the old method falls short.', 'next' => 'e2'],
            ['id' => 'e1', 'kind' => 'ending', 'purpose' => 'summary', 'outcome' => 'best', 'concept_ids' => $r0, 'covers' => 'The team settles the question.'],
            ['id' => 'e2', 'kind' => 'ending', 'purpose' => 'summary', 'outcome' => 'revisit', 'concept_ids' => $r0, 'covers' => 'The team settles it, and knows what to try next time.'],
        ]];
    }

    /** Prose for every node of a skeleton: rich scenes with an explanation, a variant at each merge, three plausible choices. $name must be one of the bible's characters. */
    private function prose(array $skeleton, string $name = 'Meera', array $over = []): array
    {
        $titles = ['s1' => 'The situation', 'd1' => 'The first choice', 's2a' => 'A focused start', 's2b' => 'The easy details', 's2c' => 'The long list', 's3' => 'What the chapter says', 'd2' => 'The next choice',
            's4a' => 'The idea carries over', 's4b' => 'Checking first', 's4c' => 'The old method', 'e1' => 'The question is settled', 'e2' => 'A model worth revisiting'];
        $scene = fn (string $id, string $what) => ['title' => $titles[$id], 'body' => "$name and the team look at the problem together, and the chapter's idea helps them see what matters here. "
            . "Everything they notice in this moment about $what points the same way, so they write it down, talk about what it could mean for the question they are trying to settle, "
            . 'and carry on to the next part of the story with a clearer picture than they had before.'];
        $explain = ['title' => 'Why a model keeps only what matters', 'body' => 'A model is a simplified picture of a real system. It keeps the details that the question needs and sets the others aside, '
            . 'and that is why it is useful: a model with every detail would be as hard to use as the real thing.'];
        $variants = fn (array $from) => array_combine($from, array_map(fn ($f, $i) => "$name remembers what happened on the way here, " . ['the focused start', 'the easy details', 'the long list', 'the checked idea', 'the old method'][$i % 5]
            . ', and carries that into this moment as part of what the team knows so far about the question.', $from, array_keys($from)));
        $preds = \App\Services\StudyDeck\Story\StoryWriter::predecessors($skeleton);
        $nodes = [];
        foreach ($skeleton['nodes'] as $n) {
            $nodes[$n['id']] = match ($n['kind']) {
                'decision' => ['title' => $titles[$n['id']], 'body' => '', 'prompt' => $name . ' has to decide what the team does next in this situation.', 'choices' => array_map(fn ($c, $i) => [
                    'text' => $name . ' picks the approach where the team will ' . $c['intent'],
                    'outcome' => 'The team follows that approach and soon sees a clear result, ' . ['first', 'second', 'third'][$i] . ' of the possible results in this story, which changes what they do next.',
                    'why' => 'The chapter explains that this path works for the ' . ['first', 'second', 'third'][$i] . ' reason because a model keeps what the question needs.',
                ], $n['choices'], array_keys($n['choices']))],
                'ending' => ['title' => $titles[$n['id']], 'body' => "$name sums up what happened on this path and what the team now understands about the chapter's idea, in their own words, ready for the next time they meet a question like this one, "
                    . 'and the whole team agrees on what they would do the same way again and what they would change.'],
                default => $scene($n['id'], $n['covers']),
            };
            if ($n['kind'] !== 'decision' && $n['concept_ids'] !== [] && $n['kind'] === 'scene') {
                $nodes[$n['id']]['explain'] = $explain;
            }
            if ($n['kind'] !== 'decision' && count($preds[$n['id']] ?? []) >= 2) {
                $nodes[$n['id']]['variants'] = $variants($preds[$n['id']]);
            }
        }

        return array_replace_recursive(['mission' => ['title' => 'Settle the question', 'body' => "Your mission is to help $name's team settle the question with a simple model."], 'conclusion' => 'A model keeps what the question needs.', 'nodes' => $nodes], $over);
    }

    private function bible(): array
    {
        return ['title' => 'The school ground investigation', 'setting' => 'A school ground, over one week.', 'premise' => 'A student team uses simple models to settle questions about their school ground.',
            'investigation' => 'Why do two parts of the school ground behave so differently in the same weather?',
            'characters' => [['name' => 'Meera', 'role' => 'student']], 'act_hooks' => ['a1' => 'The team begins with a question about the ground.', 'a2' => 'A new question needs laws and theories.', 'a3' => 'The final mission applies it all.']];
    }

    /** The replies for a whole run: bible, then skeleton + prose for each of the three acts. */
    private function script(?array $visual = null, ?array $queries = null, ?string $step = null): array
    {
        $a1 = $this->skeleton('The question on the ground', [1, 2], $visual, $queries, $step);
        $a2 = $this->skeleton('Patterns on the ground', [3, 4]);
        $a3 = $this->skeleton('The final mission', [1, 3]);

        return array_map('json_encode', [$this->bible(), $a1, $this->prose($a1), $a2, $this->prose($a2), $a3, $this->prose($a3)]);
    }

    /** When set, the run uses this model instead of the scripted one (the Gemini tests hand in the real completer over a faked HTTP). */
    private ?\App\Services\StudyDeck\Contracts\Completer $override = null;

    /** The picture search and the pixel judge a test may hand in (the search is a stub; no network is touched). */
    private ?\App\Services\PAL\Integration\ConceptImageSearchService $searchOverride = null;

    private mixed $pixelOverride = null;

    /** The shared one-pixel fixture, reported as a picture big enough to learn from (the size check reads the reported size). */
    private function bigPng(): array
    {
        return ['width' => 800, 'height' => 600] + $this->png();
    }

    /** Extra picture sources a test may hand in (name => callable returning candidates). */
    private array $providersOverride = [];

    private function service(array $replies, bool $pictures = true): array
    {
        $completer = $this->override ?? $this->completer($replies);
        $images = $pictures ? new ImagePlanner($this->searchOverride ?? $this->imageSearch(null), $this->memoryStore(), fn () => $this->bigPng()) : null;
        if ($images !== null && $this->pixelOverride !== null) {
            $images->withPixelJudge($this->pixelOverride);
        }
        if ($images !== null && $this->providersOverride !== []) {
            $images->withProviders($this->providersOverride);
        }
        $service = new StudyDeckService(
            new ConceptContextBuilder(), new QuestionSelector(), new LearningPlanBuilder(), new SlidePlanner($completer), new SlideContentGenerator($completer, 8),
            $images ?? new ImagePlanner($this->imageSearch(null), $this->memoryStore(), fn () => $this->bigPng()), new SlideHtmlRenderer(), new DeckValidator(),
            new ActivityPlanner(), null, new StoryLessonGenerator($completer, $images),
        );

        return [$service, $completer];
    }

    private function generate(array $replies, array $options = [], bool $pictures = true): array
    {
        [$service, $completer] = $this->service($replies, $pictures);
        $result = $service->fromRaw($this->chapter(), $options + ['template' => StoryTemplate::KEY, 'story_config' => StoryTemplate::defaults(), 'teaching_method' => 'experiential']);
        $result['prompts'] = $completer->prompts ?? [];

        return $result;
    }

    // ---------------------------------------------------------------- the run

    public function test_a_story_lesson_is_planned_written_assembled_and_passes_the_existing_validator(): void
    {
        $visual = ['layout' => 'flow', 'title' => 'A real system and a model', 'nodes' => ['Real system', 'Simplified representation', 'Model']];
        $r = $this->generate($this->script($visual));

        $this->assertSame([], $r['report']['errors'], json_encode($r['report']['errors']));
        $this->assertTrue($r['report']['ok']);
        $deck = $r['deck'];
        $this->assertSame(['cover', 'scenario', 'scenario', 'application'], array_column($deck['slides'], 'slide_type'), 'a cover, an act per topic and the closing mission (no objectives slide: the chapter has none stored, and none is invented)');
        $this->assertSame(['acts' => 3, 'nodes' => 36, 'paths' => 27, 'merges' => 6, 'endings' => 6, 'pictures' => 1], $r['report']['stats']['story']);
        foreach (array_filter($deck['slides'], fn ($s) => $s['interaction'] !== null) as $s) {
            $this->assertSame(StoryTemplate::KEY, $s['interaction']['template']);
            $this->assertSame([], StoryGraph::check($s['interaction'], $s['taught_concept_ids'], StoryTemplate::defaults())['errors'], "slide {$s['n']}");
        }
        $this->assertTrue(StoryTemplate::isStory($deck), 'so it is stored under its own name');

        // Every concept of the chapter is taught by a story act and explained; no practice question is placed.
        foreach ([1, 2, 3, 4] as $id) {
            $this->assertNotEmpty($deck['taught_by'][$id], "concept $id");
        }
        $this->assertSame(0, $r['report']['stats']['questions_placed']);
        $this->assertSame([], $deck['concept_questions'] === [] ? [] : $deck['concept_questions']);

        // The teaching method shaped the story prompts, and the report says so.
        $this->assertSame(['key' => 'experiential', 'followed' => true, 'errors' => [], 'applied_to' => 'the story'], $r['report']['teaching_method']);
        $this->assertStringContainsString('Learning by doing', $r['prompts'][0]);

        // The same gate a draft meets before review passes it too.
        $quality = (new PresentationQuality())->check($deck);
        $this->assertSame([], array_values(array_filter($quality, fn ($p) => in_array($p['code'], ['broken_story', 'broken_interaction', 'missing_concept', 'generic_label', 'empty_slide'], true))), json_encode($quality));

        // The scene picture was drawn into the picture store, with the labels the chapter text supports.
        $act1 = $deck['slides'][1]['interaction']['nodes'];
        $pic = array_values(array_filter($act1, fn ($n) => !empty($n['image'])));
        $this->assertCount(1, $pic);
        $this->assertSame('s2a', $pic[0]['id']);
        $this->assertStringStartsWith('study-deck-image:', $pic[0]['image']['url']);
        $this->assertSame(['A real system and a model', 'Real system', 'Simplified representation', 'Model'], $pic[0]['image']['texts']);
        $this->assertSame(0, \Illuminate\Support\Facades\DB::table('content_master')->count(), 'the run stored nothing');
    }

    public function test_the_prompts_carry_the_chapters_own_data_and_the_exact_requirements(): void
    {
        $r = $this->generate($this->script());
        [$bible, $skeleton1, $write1] = [$r['prompts'][0], $r['prompts'][1], $r['prompts'][2]];

        $this->assertStringContainsString('Ideas in exploration', $bible);
        $this->assertStringContainsString('A scientific model is a simplified representation of a real system.', $bible, 'the chapter text is the only source of facts');
        $this->assertStringContainsString('"key":"a1","title":"Models"', $bible, 'the acts of the story are the topics of the chapter');
        foreach ([$skeleton1, $write1] as $p) {
            $this->assertStringContainsString('Ignoring details', $p);
            $this->assertStringNotContainsString('evaporation', mb_strtolower($p), 'no story topic is suggested that the chapter does not contain');
        }
        $this->assertStringContainsString('required_concept_ids: 1, 2', $skeleton1);
        $this->assertStringContainsString('every path', strtolower($skeleton1));
        $this->assertStringContainsString('not a quiz', $skeleton1);
        $this->assertStringContainsString('Invent no science, no measurements and no experimental results', $write1);
        // The mission act is asked to reach across the topics.
        $this->assertStringContainsString('FINAL MISSION', $r['prompts'][5]);
        $this->assertStringContainsString('required_concept_ids: 1, 3', $r['prompts'][5]);
    }

    // ---------------------------------------------------------------- repair and refusal

    public function test_a_skeleton_with_a_dead_end_is_sent_back_with_the_exact_reason_and_the_repair_is_used(): void
    {
        $bad = $this->skeleton('The question on the ground', [1, 2]);
        $bad['nodes'][1]['choices'][0]['next'] = null;
        $replies = $this->script();
        array_splice($replies, 1, 0, [json_encode($bad)]);   // the model's first try at act 1 is the broken one

        $r = $this->generate($replies);

        $this->assertTrue($r['report']['ok']);
        $retry = $r['prompts'][2];
        $this->assertStringContainsString('YOUR PREVIOUS SKELETON (edit this one)', $retry);
        $this->assertStringContainsString('It was rejected for these reasons', $retry);
        $this->assertStringContainsString('leads nowhere', $retry);
    }

    public function test_a_skeleton_that_never_passes_stops_the_run_and_names_why(): void
    {
        $bad = $this->skeleton('The question on the ground', [1, 2]);
        unset($bad['nodes'][5]['concept_ids']);        // the shared scene no longer teaches anything: coverage fails on every path
        $bad['nodes'][5]['concept_ids'] = [1];
        foreach (['s2a', 's2b', 's2c', 's4a', 's4b', 's4c', 'e1', 'e2'] as $id) {
            foreach ($bad['nodes'] as $k => $n) {
                if ($n['id'] === $id) {
                    $bad['nodes'][$k]['concept_ids'] = [1];
                }
            }
        }
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/invalid after three repair attempts/');
        try {
            $this->generate(array_merge([json_encode($this->bible())], array_fill(0, 4, json_encode($bad))));
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('never teaches concept', $e->getMessage());
            throw $e;
        }
    }

    public function test_a_story_that_breaks_the_story_rules_is_sent_back_and_one_that_never_passes_is_refused(): void
    {
        $sk = $this->skeleton('The question on the ground', [1, 2]);
        $sameWhy = $this->prose($sk);
        $sameWhy['nodes']['d1']['choices'][1]['why'] = $sameWhy['nodes']['d1']['choices'][0]['why'];
        $replies = $this->script();
        array_splice($replies, 2, 0, [json_encode($sameWhy)]);

        $r = $this->generate($replies);

        $this->assertTrue($r['report']['ok']);
        $this->assertStringContainsString('different story events and different explanations', $r['prompts'][3]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/Act "The question on the ground"|act "Models"|invalid after three repair attempts/i');
        $this->generate(array_merge([json_encode($this->bible()), json_encode($sk)], array_fill(0, 3, json_encode($sameWhy))));
    }

    public function test_the_same_characters_must_carry_every_act(): void
    {
        $sk = $this->skeleton('The question on the ground', [1, 2]);
        $nameless = json_encode($this->prose($sk, 'Someone'));
        $replies = $this->script();
        array_splice($replies, 2, 0, [$nameless]);

        $r = $this->generate($replies);

        $this->assertStringContainsString('None of the story\'s characters (Meera) appears in this act', $r['prompts'][3]);
        $this->assertTrue($r['report']['ok']);
    }

    public function test_a_quiz_in_story_clothes_is_refused(): void
    {
        $sk = $this->skeleton('The question on the ground', [1, 2]);
        $quiz = $this->prose($sk, 'Meera', ['nodes' => ['d1' => ['prompt' => 'Which of the following is true about Meera and a model?']]]);
        $replies = $this->script();
        array_splice($replies, 2, 0, [json_encode($quiz)]);

        $r = $this->generate($replies);

        $this->assertStringContainsString('reads like a quiz question', $r['prompts'][3]);
    }

    public function test_a_figure_the_chapter_does_not_contain_is_sent_back_by_the_writer_not_left_for_the_final_validator(): void
    {
        $sk = $this->skeleton('The question on the ground', [1, 2]);
        $invented = $this->prose($sk, 'Meera', ['nodes' => ['s1' => ['body' => 'Meera counts 20,000 breaths in a day and writes the figure down for the whole team to see and to think about. The team gathers round the notebook, nobody quite agrees on what the figure means yet, and they decide to look at it again together before they say anything more about it to the rest of the class.']]]);
        $replies = $this->script();
        array_splice($replies, 2, 0, [json_encode($invented)]);

        $r = $this->generate($replies);

        $this->assertStringContainsString('uses the number 20,000, which the chapter text does not contain', $r['prompts'][3]);
        $this->assertTrue($r['report']['ok'], json_encode($r['report']['errors']));
    }

    public function test_a_skeleton_that_invents_a_figure_is_sent_back_before_the_writer_can_follow_it(): void
    {
        $bad = $this->skeleton('The question on the ground', [1, 2]);
        $bad['nodes'][1]['choices'][0]['intent'] = 'write the mass as m = 25 kg and keep the unit';
        $replies = $this->script();
        array_splice($replies, 1, 0, [json_encode($bad)]);

        $r = $this->generate($replies);

        $this->assertStringContainsString('uses the number 25, which the chapter text does not contain', $r['prompts'][2]);
        $this->assertTrue($r['report']['ok'], json_encode($r['report']['errors']));
    }

    public function test_a_picture_the_chapter_does_not_support_is_left_out_and_reported_never_drawn(): void
    {
        $visual = ['layout' => 'flow', 'title' => 'The gravitational pull of Jupiter', 'nodes' => ['Jupiter', 'Orbit', 'Gravity']];
        $r = $this->generate($this->script($visual));

        $this->assertTrue($r['report']['ok']);
        $this->assertSame(0, $r['report']['stats']['story']['pictures']);
        $this->assertNotEmpty(array_filter($r['story']['notes'], fn ($n) => str_contains($n, 'was not drawn') && str_contains($n, 'labels are in the chapter text')));
    }

    public function test_a_wrong_number_of_written_choices_or_a_missing_node_is_an_error_not_a_repair(): void
    {
        $sk = $this->skeleton('x', [1, 2]);
        $p = $this->prose($sk);
        array_pop($p['nodes']['d1']['choices']);
        unset($p['nodes']['s3']);
        [, $errors] = (new \App\Services\StudyDeck\Story\StoryWriter($this->completer([])))->assemble(StorySkeleton::check($sk, ['key' => 'a1', 'kind' => 'topic', 'concept_ids' => [1, 2], 'required_ids' => [1, 2]], StoryTemplate::defaults())['skeleton'], $p);

        $this->assertSame(['Decision "d1" must have exactly 3 choices written, in the order of the skeleton; you wrote 2.', 'There is no text for node "s3".'], array_values($errors));
    }

    // ---------------------------------------------------------------- the pieces

    public function test_acts_are_one_per_topic_then_a_mission_that_reaches_across_the_chapter(): void
    {
        $map = ['concepts' => [], 'topics' => []];
        foreach (range(1, 8) as $t) {
            $map['topics'][] = ['id' => $t, 'name' => "Topic $t", 'description' => '', 'concept_ids' => [$t * 10, $t * 10 + 1]];
            $map['concepts'][$t * 10] = ['name' => "C$t"];
            $map['concepts'][$t * 10 + 1] = ['name' => "D$t"];
        }
        $map['topics'][] = ['id' => 99, 'name' => 'Empty topic', 'description' => '', 'concept_ids' => []];
        $acts = StoryActs::plan($map, StoryTemplate::defaults());

        $this->assertCount(9, $acts, 'eight topics with concepts, and the mission (a topic with none is skipped)');
        $this->assertSame(range(10, 80, 10), array_map(fn ($a) => $a['required_ids'][0], array_slice($acts, 0, 8)) ? array_map(fn ($a) => $a['concept_ids'][0], array_slice($acts, 0, 8)) : []);
        $this->assertSame(StoryActs::MISSION, $acts[8]['kind']);
        $this->assertCount(6, $acts[8]['required_ids'], 'the mission meets six anchors (the template\'s mission_concepts), spread across the topics');
        $this->assertSame([10, 20, 40, 50, 60, 80][0], $acts[8]['required_ids'][0]);
        $this->assertCount(16, $acts[8]['concept_ids']);
        $this->assertSame([], StoryActs::plan(['concepts' => [], 'topics' => []], StoryTemplate::defaults()));
    }

    public function test_the_skeleton_check_names_what_is_wrong(): void
    {
        $act = ['key' => 'a1', 'kind' => 'topic', 'concept_ids' => [1, 2], 'required_ids' => [1, 2]];
        $cfg = StoryTemplate::defaults();
        $ok = $this->skeleton('A story title', [1, 2]);
        $this->assertSame([], StorySkeleton::check($ok, $act, $cfg)['errors']);

        $straightToEnding = $ok;
        $straightToEnding['nodes'][6]['choices'][1]['next'] = 'e2';
        $this->assertNotEmpty(array_filter(StorySkeleton::check($straightToEnding, $act, $cfg)['errors'], fn ($e) => str_contains($e, 'leads straight to an ending')));

        $foreign = $ok;
        $foreign['nodes'][2]['concept_ids'] = [99];
        $this->assertNotEmpty(array_filter(StorySkeleton::check($foreign, $act, $cfg)['errors'], fn ($e) => str_contains($e, 'not one of this act')));

        $opening = $ok;
        $opening['nodes'][0]['purpose'] = 'explanation';
        $this->assertNotEmpty(array_filter(StorySkeleton::check($opening, $act, $cfg)['errors'], fn ($e) => str_contains($e, 'situation')));

        $allBest = $ok;
        $allBest['nodes'][1]['choices'][1]['quality'] = 'best';
        $allBest['nodes'][1]['choices'][2]['quality'] = 'best';
        $this->assertNotEmpty(array_filter(StorySkeleton::check($allBest, $act, $cfg)['errors'], fn ($e) => str_contains($e, 'only "best" choices')));

        $placeholder = $ok;
        $placeholder['title'] = 'Act 1';
        $this->assertNotEmpty(array_filter(StorySkeleton::check($placeholder, $act, $cfg)['errors'], fn ($e) => str_contains($e, 'placeholder')));

        $quizKind = $ok;
        $quizKind['nodes'][1]['purpose'] = 'check';
        $this->assertNotEmpty(array_filter(StorySkeleton::check($quizKind, $act, $cfg)['errors'], fn ($e) => str_contains($e, 'purpose "check"')), 'the bank-question check of the H5P pipeline is not part of a story');
    }

    public function test_without_a_model_reply_nothing_is_invented(): void
    {
        // The scripted completer returns "{}" when it has nothing left: that is not a story, so the run stops with the reason.
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/story bible is invalid after two repair attempts/');
        $this->generate([]);
    }
    // ---------------------------------------------------------------- Gemini as the model (HTTP faked: no real call)

    private function geminiBody(string $text): array
    {
        return ['candidates' => [['content' => ['parts' => [['text' => $text]]]]]];
    }

    /** A story completer over the estate Gemini client, with a key from GEMINI_API_KEY and no waiting. */
    private function gemini(array &$slept = []): \App\Services\StudyDeck\Story\GeminiStoryCompleter
    {
        config(['ai.provider.gemini.api_key' => 'test-key-not-real', 'ai.provider.gemini.model' => 'gemini-test-model', 'claude.executor' => 'api']);
        putenv('ANTHROPIC_API_KEY');
        unset($_ENV['ANTHROPIC_API_KEY'], $_SERVER['ANTHROPIC_API_KEY']);

        return new \App\Services\StudyDeck\Story\GeminiStoryCompleter(app(\App\Domain\AI\Support\GeminiClient::class)->forInstitute(1), function (int $s) use (&$slept): void {
            $slept[] = $s;
        });
    }

    public function test_gemini_is_selected_through_the_estate_client_with_the_configured_key_and_model_and_no_anthropic_key(): void
    {
        $completer = $this->gemini();
        $this->assertTrue(\App\Services\StudyDeck\Story\GeminiStoryCompleter::available(1), 'the key resolves from the existing configuration');
        $this->assertSame('gemini-test-model', $completer->model());

        \Illuminate\Support\Facades\Http::fake(['generativelanguage.googleapis.com/*' => \Illuminate\Support\Facades\Http::response($this->geminiBody('{"ok":true}'), 200)]);
        $this->assertSame('{"ok":true}', $completer->complete('SYSTEM TEXT', 'PROMPT TEXT', 4000));

        \Illuminate\Support\Facades\Http::assertSent(function ($request) {
            $b = $request->data();

            return str_contains($request->url(), '/models/gemini-test-model:generateContent')
                && $request->header('x-goog-api-key') === ['test-key-not-real']
                && ($b['systemInstruction']['parts'][0]['text'] ?? '') === 'SYSTEM TEXT'
                && ($b['contents'][0]['parts'][0]['text'] ?? '') === 'PROMPT TEXT'
                && ($b['generationConfig']['responseMimeType'] ?? '') === 'application/json'
                && ($b['generationConfig']['maxOutputTokens'] ?? 0) > 4000;
        });
        \Illuminate\Support\Facades\Http::assertSentCount(1);
        \Illuminate\Support\Facades\Http::assertNotSent(fn ($r) => str_contains($r->url(), 'anthropic'));
    }

    public function test_without_a_gemini_key_it_is_unavailable_and_nothing_is_called(): void
    {
        config(['ai.provider.gemini.api_key' => '']);
        \Illuminate\Support\Facades\Http::fake();
        $this->assertFalse(\App\Services\StudyDeck\Story\GeminiStoryCompleter::available(1));
        $this->assertFalse((new \App\Services\CoursePresentation\ModelAvailability())->availableFor('branching_scenario'));
        \Illuminate\Support\Facades\Http::assertNothingSent();
    }

    public function test_an_empty_answer_is_asked_for_again_and_one_that_never_improves_is_an_error(): void
    {
        $completer = $this->gemini();
        \Illuminate\Support\Facades\Http::fake(['generativelanguage.googleapis.com/*' => \Illuminate\Support\Facades\Http::sequence()
            ->push(['candidates' => [['finishReason' => 'MAX_TOKENS', 'content' => ['parts' => []]]]], 200)->push($this->geminiBody('answer'), 200)->push(['candidates' => []], 200)->push(['candidates' => []], 200)->push(['candidates' => []], 200)]);
        $this->assertSame('answer', $completer->complete('s', 'p'));
        \Illuminate\Support\Facades\Http::assertSentCount(2);

        $this->expectExceptionMessage('no usable text');
        $completer->complete('s', 'p');
    }

    public function test_a_per_minute_rate_limit_waits_for_the_time_the_provider_asked_and_tries_again(): void
    {
        $slept = [];
        $completer = $this->gemini($slept);
        $limit = ['error' => ['code' => 429, 'status' => 'RESOURCE_EXHAUSTED', 'message' => 'rate', 'details' => [['violations' => [['quotaId' => 'GenerateRequestsPerMinutePerProjectPerModel']], 'retryDelay' => '12s']]]];
        \Illuminate\Support\Facades\Http::fake(['generativelanguage.googleapis.com/*' => \Illuminate\Support\Facades\Http::sequence()->push($limit, 429)->push($this->geminiBody('done'), 200)]);

        $this->assertSame('done', $completer->complete('s', 'p'));
        $this->assertSame([12], $slept);
    }

    public function test_a_token_rate_limit_that_is_not_named_per_minute_is_waited_out_too(): void
    {
        $slept = [];
        $completer = $this->gemini($slept);
        $limit = ['error' => ['code' => 429, 'status' => 'RESOURCE_EXHAUSTED', 'message' => 'quota', 'details' => [['violations' => [['quotaId' => 'GenerateContentInputTokensPerModelPerMinute-FreeTier']]]]]];
        \Illuminate\Support\Facades\Http::fake(['generativelanguage.googleapis.com/*' => \Illuminate\Support\Facades\Http::sequence()->push($limit, 429)->push($this->geminiBody('done'), 200)]);

        $this->assertSame('done', $completer->complete('s', 'p'));
        $this->assertSame([60], $slept, 'waited a minute because the provider named no delay');
    }

    public function test_a_spent_daily_quota_is_not_retried_and_the_error_says_what_to_change(): void
    {
        $slept = [];
        $completer = $this->gemini($slept);
        $limit = ['error' => ['code' => 429, 'status' => 'RESOURCE_EXHAUSTED', 'message' => 'quota', 'details' => [['violations' => [['quotaId' => 'GenerateRequestsPerDayPerProjectPerModel-FreeTier']]]]]];
        \Illuminate\Support\Facades\Http::fake(['generativelanguage.googleapis.com/*' => \Illuminate\Support\Facades\Http::response($limit, 429)]);

        try {
            $completer->complete('s', 'p');
            $this->fail('a spent quota must stop the run');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('quota or billing limit', $e->getMessage());
        }
        $this->assertSame([], $slept, 'no waiting for something that will not clear');
    }

    public function test_a_rejected_key_is_not_retried(): void
    {
        $completer = $this->gemini();
        \Illuminate\Support\Facades\Http::fake(['generativelanguage.googleapis.com/*' => \Illuminate\Support\Facades\Http::response(['error' => ['code' => 403, 'message' => 'bad key']], 403)]);
        try {
            $completer->complete('s', 'p');
            $this->fail('a rejected key must stop the run');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('rejected the configured credential', $e->getMessage());
        }
        \Illuminate\Support\Facades\Http::assertSentCount(1);
    }

    public function test_a_whole_story_is_generated_through_mocked_gemini_and_passes_every_existing_check(): void
    {
        $this->override = $this->gemini();
        $replies = $this->script();
        // The first skeleton reply is malformed JSON: the planner repair round asks again and the repaired reply is used.
        $queue = \Illuminate\Support\Facades\Http::sequence();
        foreach ([$replies[0], '{"title": "cut off', ...array_slice($replies, 1)] as $text) {
            $queue->push($this->geminiBody($text), 200);
        }
        \Illuminate\Support\Facades\Http::fake(['generativelanguage.googleapis.com/*' => $queue]);

        $r = $this->generate([]);

        $this->assertSame([], $r['report']['errors'], json_encode($r['report']['errors']));
        $this->assertTrue($r['report']['ok']);
        $this->assertSame(3, $r['report']['stats']['story']['acts']);
        $this->assertSame(36, $r['report']['stats']['story']['nodes']);
        // Graph connectivity and concept coverage hold on every path, checked by the existing rules.
        foreach (array_filter($r['deck']['slides'], fn ($s) => $s['interaction'] !== null) as $s) {
            $this->assertSame([], StoryGraph::check($s['interaction'], $s['taught_concept_ids'], StoryTemplate::defaults())['errors'], "slide {$s['n']}");
        }
        foreach ([1, 2, 3, 4] as $id) {
            $this->assertNotEmpty($r['deck']['taught_by'][$id], "concept $id");
        }
        \Illuminate\Support\Facades\Http::assertNotSent(fn ($req) => !str_contains($req->url(), 'generativelanguage.googleapis.com'));
        $this->assertSame(0, \Illuminate\Support\Facades\DB::table('content_master')->count());
    }

    public function test_a_story_that_gemini_gets_wrong_on_every_try_is_refused_not_repaired_silently(): void
    {
        $this->override = $this->gemini();
        \Illuminate\Support\Facades\Http::fake(['generativelanguage.googleapis.com/*' => \Illuminate\Support\Facades\Http::response($this->geminiBody('{"title":"x","nodes":[]}'), 200)]);
        $this->expectException(\RuntimeException::class);
        $this->generate([]);
    }
    public function test_finished_acts_are_kept_when_a_later_act_fails_and_the_next_run_resumes_from_them(): void
    {
        $dir = sys_get_temp_dir() . '/story-checkpoint-' . bin2hex(random_bytes(4));
        $script = $this->script();
        try {
            // Run 1: the bible and acts 1 and 2 are fine; act 3 never gets a valid skeleton.
            try {
                $this->generate(array_merge(array_slice($script, 0, 5), array_fill(0, 4, '{"title":"x","nodes":[]}')), ['story_checkpoint_dir' => $dir]);
                $this->fail('act 3 should have stopped the run');
            } catch (\RuntimeException $e) {
                $this->assertStringContainsString('Laws', $e->getMessage() . 'Laws', 'it names the act that failed');
            }
            $this->assertNotEmpty(glob($dir . '/*/*.json'), 'the finished work was kept');

            // Run 2: only act 3 is asked for; the bible and the first two acts come from the kept work.
            $r = $this->generate(array_slice($script, 5), ['story_checkpoint_dir' => $dir]);
            $this->assertSame([], $r['report']['errors'], json_encode($r['report']['errors']));
            $this->assertTrue($r['report']['ok']);
            $this->assertSame(3, $r['report']['stats']['story']['acts']);
            $this->assertCount(2, $r['prompts'], 'two model calls: the third act skeleton and its story, nothing redone');
            $this->assertSame([], glob($dir . '/*/*.json') ?: [], 'the kept work is removed once the story is complete');
        } finally {
            foreach (glob($dir . '/*/*') ?: [] as $f) {
                @unlink($f);
            }
        }
    }
    // ---------------------------------------------------------------- version 2: pictures, explanations, variants, plausible choices

    public function test_a_photograph_is_kept_only_when_its_licence_is_accepted_and_the_picture_itself_was_looked_at(): void
    {
        $this->searchOverride = $this->imageSearch($this->goodImage());
        $seen = [];
        $this->pixelOverride = function (string $bytes, string $mime, string $query, string $purpose, string $teaches) use (&$seen): array {
            $seen[] = [$query, $purpose];

            return ['ok' => true, 'shows' => 'A paper map of a town laid flat on a table', 'reason' => 'a map is a simplified model of a place'];
        };
        $visual = ['layout' => 'flow', 'title' => 'A real system and a model', 'nodes' => ['Real system', 'Simplified representation', 'Model']];
        $r = $this->generate($this->script($visual, ['map model']));

        $this->assertTrue($r['report']['ok'], json_encode($r['report']['errors']));
        $this->assertNotEmpty($seen, 'the pixel judge was asked');
        $this->assertSame('map model', $seen[0][0]);
        $s2a = array_values(array_filter($r['deck']['slides'][1]['interaction']['nodes'], fn ($n) => $n['id'] === 's2a'))[0];
        $this->assertSame('photo', $s2a['image']['type']);
        $this->assertSame('Photograph: A paper map of a town laid flat on a table.', $s2a['image']['alt'], 'the description is what the picture was seen to show');
        $this->assertSame('BY-SA 4.0', $s2a['image']['licence']);
        $this->assertNotEmpty($s2a['image']['source_url']);
        $this->assertNotEmpty($s2a['image']['attribution'], 'a picture that needs a credit carries it');
        $this->assertCount(1, $s2a['extra_images'], 'the drawn diagram supports the photograph');
        $this->assertSame('diagram', $s2a['extra_images'][0]['type']);
        $this->assertSame(2, $r['report']['stats']['story']['pictures']);
    }

    public function test_a_photograph_that_the_pixel_check_rejects_is_not_used_and_the_reason_is_noted(): void
    {
        $this->searchOverride = $this->imageSearch($this->goodImage());
        $this->pixelOverride = fn () => ['ok' => false, 'shows' => 'A coat of arms', 'reason' => 'it shows a coat of arms, not the subject'];
        $r = $this->generate($this->script(null, ['map model']));

        $this->assertTrue($r['report']['ok'], json_encode($r['report']['errors']));
        $s2a = array_values(array_filter($r['deck']['slides'][1]['interaction']['nodes'], fn ($n) => $n['id'] === 's2a'))[0];
        $this->assertArrayNotHasKey('image', $s2a, 'a wrong picture is worse than none');
        $this->assertNotEmpty(array_filter($r['story']['notes'], fn ($n) => str_contains($n, 'does not fit the scene') && str_contains($n, 'coat of arms')));
        $this->assertSame(0, $r['report']['stats']['story']['pictures']);
    }

    public function test_no_photograph_is_used_twice_in_a_lesson(): void
    {
        $this->searchOverride = $this->imageSearch($this->goodImage());
        $this->pixelOverride = fn () => ['ok' => true, 'shows' => 'A paper map of a town laid flat on a table', 'reason' => 'fits'];
        $script = $this->script(null, ['map model']);
        $r = $this->generate($script);
        $urls = [];
        foreach ($r['deck']['slides'] as $s) {
            foreach ((array) ($s['interaction']['nodes'] ?? []) as $n) {
                if (($n['image']['type'] ?? '') === 'photo') {
                    $urls[] = $n['image']['source_image_url'];
                }
            }
        }
        $this->assertSame(array_values(array_unique($urls)), $urls, 'the same source picture appears once');
        $this->assertCount(1, $urls, 'only the first act asked for a picture, and the search has one candidate');
    }

    public function test_a_teaching_scene_without_its_explanation_is_sent_back(): void
    {
        $sk = $this->skeleton('The question on the ground', [1, 2]);
        $bad = $this->prose($sk);
        unset($bad['nodes']['s2a']['explain']);
        $replies = $this->script();
        array_splice($replies, 2, 0, [json_encode($bad)]);

        $r = $this->generate($replies);

        $this->assertStringContainsString('Scene s2a teaches a concept but explains none of it', $r['prompts'][3]);
        $this->assertTrue($r['report']['ok'], json_encode($r['report']['errors']));
    }

    public function test_a_scene_where_branches_meet_must_say_which_path_the_learner_came_by(): void
    {
        $sk = $this->skeleton('The question on the ground', [1, 2]);
        $bad = $this->prose($sk);
        unset($bad['nodes']['s3']['variants']['s2b']);
        $replies = $this->script();
        array_splice($replies, 2, 0, [json_encode($bad)]);

        $r = $this->generate($replies);

        $this->assertStringContainsString('Node s3 is reached from s2b and from other scenes, so it needs a variant', $r['prompts'][3]);
        // The finished lesson carries a different lead-in for each way in.
        $s3 = array_values(array_filter($r['deck']['slides'][1]['interaction']['nodes'], fn ($n) => $n['id'] === 's3'))[0];
        $this->assertSame(['s2a', 's2b', 's2c'], array_column($s3['variants'], 'after'));
        $this->assertCount(3, array_unique(array_column($s3['variants'], 'body')));
    }

    public function test_a_decision_that_is_a_right_answer_against_a_wrong_one_is_refused_at_the_skeleton(): void
    {
        $act = ['key' => 'a1', 'kind' => 'topic', 'concept_ids' => [1, 2], 'required_ids' => [1, 2]];
        $cfg = StoryTemplate::defaults();
        $sk = $this->skeleton('A story title', [1, 2]);

        $noPartial = $sk;
        $noPartial['nodes'][1]['choices'][1]['quality'] = 'misstep';   // best + misstep + misstep
        $this->assertNotEmpty(array_filter(StorySkeleton::check($noPartial, $act, $cfg)['errors'], fn ($e) => str_contains($e, 'needs at least one "partial" choice')));
        $this->assertNotEmpty(array_filter(StorySkeleton::check($noPartial, $act, $cfg)['errors'], fn ($e) => str_contains($e, 'more than one "misstep"')));

        $same = $sk;
        $same['nodes'][1]['choices'][2]['next'] = 's2b';
        $this->assertNotEmpty(array_filter(StorySkeleton::check($same, $act, $cfg)['errors'], fn ($e) => str_contains($e, 'lead to the same scene')));

        $tell = $sk;
        $tell['nodes'][1]['choices'][2]['intent'] = 'guess the details blindly';
        $this->assertNotEmpty(array_filter(StorySkeleton::check($tell, $act, $cfg)['errors'], fn ($e) => str_contains($e, 'gives away its weaker choice')));

        $twoOnly = $sk;
        $twoOnly['nodes'][1]['choices'] = array_slice($twoOnly['nodes'][1]['choices'], 0, 2);
        $twoOnly['nodes'][4]['next'] = 's3';
        $twoOnly['nodes'][6]['choices'] = array_slice($twoOnly['nodes'][6]['choices'], 0, 2);
        $this->assertNotEmpty(array_filter(StorySkeleton::check($twoOnly, $act, $cfg)['errors'], fn ($e) => str_contains($e, 'offer three or more choices')));
    }

    public function test_choices_that_give_themselves_away_are_sent_back(): void
    {
        $sk = $this->skeleton('The question on the ground', [1, 2]);
        $bad = $this->prose($sk);
        $bad['nodes']['d1']['choices'][1]['text'] = 'Meera decides to just guess the answer blindly without checking';
        $replies = $this->script();
        array_splice($replies, 2, 0, [json_encode($bad)]);

        $r = $this->generate($replies);

        $this->assertStringContainsString('gives itself away with a word like never, always, guess or blindly', $r['prompts'][3]);
    }

    public function test_the_next_act_opens_from_what_the_previous_one_left_behind(): void
    {
        $r = $this->generate($this->script());

        $this->assertStringContainsString('"previous":null', $r['prompts'][1], 'the first act has nothing before it');
        $this->assertStringContainsString('"previous_act":"The question on the ground"', $r['prompts'][3], 'the second act is told how the first one went');
        $this->assertStringContainsString('"what_the_team_now_knows":"A model keeps what the question needs."', $r['prompts'][3]);
        $this->assertStringContainsString('"investigation":"Why do two parts of the school ground behave so differently in the same weather?"', $r['prompts'][1], 'one investigation runs through every act');
    }
    public function test_an_ending_that_no_path_can_reach_is_named_with_the_way_to_fix_it(): void
    {
        $act = ['key' => 'a1', 'kind' => 'topic', 'concept_ids' => [1, 2], 'required_ids' => [1, 2]];
        $funnel = $this->skeleton('A story title', [1, 2]);
        foreach ($funnel['nodes'] as $k => $n) {
            if (in_array($n['id'], ['s4b', 's4c'], true)) {
                $funnel['nodes'][$k]['next'] = 'e1';   // every branch ends in the same place: e2 is never reached
            }
        }

        $errors = StorySkeleton::check($funnel, $act, StoryTemplate::defaults())['errors'];

        $this->assertStringContainsString('Ending e2 is not reached by any path', $errors[0]);
        $this->assertStringContainsString('do not send every branch through one closing scene', $errors[0]);
    }
    // ---------------------------------------------------------------- pictures that teach: process sequence, highlight, caption

    public function test_a_flow_diagram_can_put_one_step_in_focus_and_says_so_in_its_alt_text(): void
    {
        $spec = ['layout' => 'flow', 'title' => 'Observation to conclusion', 'nodes' => ['Observation', 'Investigation', 'Evidence', 'Conclusion']];
        $renderer = new \App\Services\StudyDeck\DiagramRenderer();

        $plain = $renderer->render($spec);
        $second = $renderer->render($spec + ['focus' => 1]);
        $third = $renderer->render($spec + ['focus' => 2]);

        foreach ([$plain, $second, $third] as $file) {
            $this->assertSame('image/png', $file['mime']);
            $this->assertSame([1280, 720], [$file['width'], $file['height']]);
            $this->assertNotFalse(@imagecreatefromstring($file['bytes']), 'a real picture');
        }
        $this->assertNotSame(sha1($plain['bytes']), sha1($second['bytes']), 'the step in focus changes the picture');
        $this->assertNotSame(sha1($second['bytes']), sha1($third['bytes']), 'and a different step in focus gives a different picture');
        $this->assertStringContainsString('The step in focus is Investigation.', \App\Services\StudyDeck\DiagramRenderer::describe($spec + ['focus' => 1]));
        $this->assertSame([], \App\Services\StudyDeck\DiagramRenderer::problems($spec + ['focus' => 3]));
        foreach ([4, -1, '1', 1.5] as $bad) {
            $this->assertNotEmpty(\App\Services\StudyDeck\DiagramRenderer::problems($spec + ['focus' => $bad]), 'focus ' . json_encode($bad));
        }
        $this->assertNotEmpty(\App\Services\StudyDeck\DiagramRenderer::problems(['layout' => 'hub', 'title' => 'T', 'center' => 'C', 'nodes' => ['a', 'b'], 'focus' => 0]), 'only a flow has steps');
    }

    public function test_the_process_sequence_is_the_four_steps_in_order_with_the_named_one_in_focus(): void
    {
        $spec = \App\Services\StudyDeck\Story\StoryProcess::spec('evidence');

        $this->assertSame(['Observation', 'Investigation', 'Evidence', 'Conclusion'], $spec['nodes']);
        $this->assertSame(2, $spec['focus']);
        $this->assertSame('flow', $spec['layout']);
        $this->assertSame('Step 4 of 4: Conclusion.', \App\Services\StudyDeck\Story\StoryProcess::caption('conclusion'));
        $this->assertTrue(\App\Services\StudyDeck\Story\StoryProcess::valid('investigation'));
        $this->assertFalse(\App\Services\StudyDeck\Story\StoryProcess::valid('hypothesis'));
        $this->assertSame([], \App\Services\StudyDeck\DiagramRenderer::problems($spec));
    }

    public function test_a_scene_that_is_a_step_of_the_process_shows_the_sequence_with_its_step_in_focus_and_a_caption(): void
    {
        $r = $this->generate($this->script(null, null, 'investigation'));

        $this->assertTrue($r['report']['ok'], json_encode($r['report']['errors']));
        $s2a = array_values(array_filter($r['deck']['slides'][1]['interaction']['nodes'], fn ($n) => $n['id'] === 's2a'))[0];
        $this->assertSame('diagram', $s2a['image']['type']);
        $this->assertSame('Step 2 of 4: Investigation.', $s2a['image']['caption']);
        $this->assertSame(['Observation to conclusion', 'Observation', 'Investigation', 'Evidence', 'Conclusion'], $s2a['image']['texts']);
        $this->assertStringContainsString('The step in focus is Investigation.', $s2a['image']['alt']);
        $this->assertSame(1, $r['report']['stats']['story']['pictures']);
        // The same sequence with another step in focus is a different picture.
        $evidence = $this->generate($this->script(null, null, 'evidence'));
        $other = array_values(array_filter($evidence['deck']['slides'][1]['interaction']['nodes'], fn ($n) => $n['id'] === 's2a'))[0];
        $this->assertSame('Step 3 of 4: Evidence.', $other['image']['caption']);
        $this->assertStringContainsString('The step in focus is Evidence.', $other['image']['alt']);
        $this->assertStringNotContainsString('Investigation.', $other['image']['alt'] . ' ' . $other['image']['caption'], 'the other step is not the one in focus');
    }

    public function test_a_wrong_process_step_or_two_drawn_diagrams_on_one_scene_is_refused_at_the_skeleton(): void
    {
        $act = ['key' => 'a1', 'kind' => 'topic', 'concept_ids' => [1, 2], 'required_ids' => [1, 2]];
        $cfg = StoryTemplate::defaults();
        $sk = $this->skeleton('A story title', [1, 2], null, null, 'evidence');
        $this->assertSame([], StorySkeleton::check($sk, $act, $cfg)['errors']);
        $this->assertSame('evidence', StorySkeleton::check($sk, $act, $cfg)['skeleton']['nodes'][2]['process_step']);

        $wrong = $this->skeleton('A story title', [1, 2], null, null, 'hypothesis');
        $this->assertNotEmpty(array_filter(StorySkeleton::check($wrong, $act, $cfg)['errors'], fn ($e) => str_contains($e, 'has process_step "hypothesis"')));

        $both = $this->skeleton('A story title', [1, 2], ['layout' => 'flow', 'title' => 'A real system and a model', 'nodes' => ['Real system', 'Model']], null, 'evidence');
        $this->assertNotEmpty(array_filter(StorySkeleton::check($both, $act, $cfg)['errors'], fn ($e) => str_contains($e, 'both a process_step and a visual')));
    }

    public function test_the_part_of_the_picture_to_notice_is_kept_and_the_caption_says_what_to_notice(): void
    {
        $this->searchOverride = $this->imageSearch($this->goodImage());
        $this->pixelOverride = fn () => ['ok' => true, 'shows' => 'A paper map of a town laid flat on a table', 'reason' => 'a map is a simplified model of a place',
            'focus' => ['x' => 20.0, 'y' => 30.0, 'w' => 40.0, 'h' => 35.0], 'notice' => 'Look at how few of the real streets the map draws.'];
        $r = $this->generate($this->script(null, ['map model']));

        $this->assertTrue($r['report']['ok'], json_encode($r['report']['errors']));
        $s2a = array_values(array_filter($r['deck']['slides'][1]['interaction']['nodes'], fn ($n) => $n['id'] === 's2a'))[0];
        $this->assertSame(['x' => 20.0, 'y' => 30.0, 'w' => 40.0, 'h' => 35.0], $s2a['image']['focus'], 'the relevant part is highlighted');
        $this->assertSame('Look at how few of the real streets the map draws.', $s2a['image']['caption'], 'with no caption from the writer, what the picture check saw is used');
        $this->assertArrayNotHasKey('notice', $s2a['image']);
    }

    public function test_the_writers_own_caption_for_a_picture_wins_and_is_held_to_the_no_invented_figures_rule(): void
    {
        $this->searchOverride = $this->imageSearch($this->goodImage());
        $this->pixelOverride = fn () => ['ok' => true, 'shows' => 'A paper map of a town', 'reason' => 'fits', 'notice' => 'The checker\'s note.'];
        $sk = $this->skeleton('The question on the ground', [1, 2], null, ['map model']);
        $prose = $this->prose($sk, 'Meera', ['nodes' => ['s2a' => ['picture_caption' => 'Notice that the map keeps the roads and leaves out the shops.']]]);
        $replies = $this->script(null, ['map model']);
        $replies[2] = json_encode($prose);

        $r = $this->generate($replies);

        $s2a = array_values(array_filter($r['deck']['slides'][1]['interaction']['nodes'], fn ($n) => $n['id'] === 's2a'))[0];
        $this->assertSame('Notice that the map keeps the roads and leaves out the shops.', $s2a['image']['caption']);
        $this->assertArrayNotHasKey('picture_caption', $s2a, 'the caption lives on the picture');

        // A caption that invents a figure is sent back like any other text.
        $bad = $this->prose($sk, 'Meera', ['nodes' => ['s2a' => ['picture_caption' => 'The map shows 20,000 streets.']]]);
        $replies = $this->script(null, ['map model']);
        array_splice($replies, 2, 0, [json_encode($bad)]);
        $again = $this->generate($replies);
        $this->assertStringContainsString('uses the number 20,000', $again['prompts'][3]);
    }

    public function test_a_highlighted_region_must_be_a_real_rectangle_inside_the_picture(): void
    {
        $region = [\App\Services\StudyDeck\Story\StoryPixelJudge::class, 'region'];
        $this->assertSame(['x' => 10.0, 'y' => 20.0, 'w' => 30.0, 'h' => 40.0], $region(['x' => 10, 'y' => 20, 'w' => 30, 'h' => 40]));
        $this->assertSame(['x' => 70.0, 'y' => 0.0, 'w' => 30.0, 'h' => 50.0], $region(['x' => 70, 'y' => 0, 'w' => 30.4, 'h' => 50]), 'a rounding overshoot at the edge is trimmed to it');
        foreach ([
            null, 'x', [], ['x' => 1, 'y' => 1, 'w' => 10], ['x' => -5, 'y' => 0, 'w' => 20, 'h' => 20], ['x' => 0, 'y' => 0, 'w' => 4, 'h' => 20],
            ['x' => 0, 'y' => 0, 'w' => 100, 'h' => 100], ['x' => 90, 'y' => 0, 'w' => 30, 'h' => 20], ['x' => 'a', 'y' => 0, 'w' => 20, 'h' => 20],
        ] as $bad) {
            $this->assertNull($region($bad), json_encode($bad));
        }

        $f = json_decode((string) file_get_contents(__DIR__ . '/../../Fixtures/story-act-chapter8592.json'), true);
        $f['interaction']['nodes'][0]['image']['focus'] = ['x' => 90, 'y' => 90, 'w' => 50, 'h' => 50];
        $errors = \App\Services\StudyDeck\Story\StoryGraph::check($f['interaction'], $f['concept_ids'], StoryTemplate::defaults())['errors'];
        $this->assertNotEmpty(array_filter($errors, fn ($e) => str_contains($e, 'highlighted part of a picture of s1 is not a rectangle inside the picture')));
        $f['interaction']['nodes'][0]['image']['focus'] = ['x' => 10, 'y' => 20, 'w' => 30, 'h' => 40];
        $this->assertSame([], \App\Services\StudyDeck\Story\StoryGraph::check($f['interaction'], $f['concept_ids'], StoryTemplate::defaults())['errors']);
    }
    public function test_the_pixel_judge_reads_the_picture_check_and_fails_closed(): void
    {
        $judge = function (mixed $reply): \App\Services\StudyDeck\Story\StoryPixelJudge {
            $client = new class($reply) extends \App\Domain\AI\Support\GeminiClient {
                public ?string $prompt = null;

                public function __construct(private mixed $reply)
                {
                }

                public function analyzeImage(string $prompt, string $imageBytes, string $mimeType, ?string $model = null, ?int $timeout = null): ?string
                {
                    $this->prompt = $prompt;
                    if ($this->reply instanceof \Throwable) {
                        throw $this->reply;
                    }

                    return $this->reply;
                }
            };

            return new \App\Services\StudyDeck\Story\StoryPixelJudge($client);
        };

        $ok = $judge(json_encode(['matches' => true, 'shows' => 'A vernier caliper on a table', 'reason' => 'fits', 'focus' => ['x' => 10, 'y' => 20, 'w' => 30, 'h' => 40], 'notice' => 'Read the sliding scale.']))('bytes', 'image/png', 'vernier caliper', 'Measuring', 'Units');
        $this->assertTrue($ok['ok']);
        $this->assertSame(['x' => 10.0, 'y' => 20.0, 'w' => 30.0, 'h' => 40.0], $ok['focus']);
        $this->assertSame('Read the sliding scale.', $ok['notice']);

        $noRegion = $judge(json_encode(['matches' => true, 'shows' => 'A caliper', 'reason' => 'fits', 'focus' => ['x' => 0, 'y' => 0, 'w' => 100, 'h' => 100]]))('b', 'image/png', 'q', 'p', 't');
        $this->assertTrue($noRegion['ok']);
        $this->assertArrayNotHasKey('focus', $noRegion, 'a rectangle that is the whole picture points at nothing');

        $this->assertFalse($judge(json_encode(['matches' => false, 'shows' => 'A coat of arms', 'reason' => 'wrong']))('b', 'image/png', 'q', 'p', 't')['ok']);
        $this->assertFalse($judge(json_encode(['matches' => true, 'shows' => '']))('b', 'image/png', 'q', 'p', 't')['ok'], 'no description of what it shows: not trusted');
        $this->assertFalse($judge('not json at all')('b', 'image/png', 'q', 'p', 't')['ok'], 'an unreadable reply accepts nothing');
        $this->assertFalse($judge(new \RuntimeException('quota'))('b', 'image/png', 'q', 'p', 't')['ok'], 'a failed call accepts nothing');
    }
    // ---------------------------------------------------------------- the picture pass over a finished story

    /** Run the picture pass over the finished story of $first (a result of generate()), as the command does with --pictures-from. */
    private function picturePass(array $first, array $replies, array $options = []): array
    {
        $keys = array_column($first['story']['acts'], 'key');
        $interactions = array_values(array_map(fn ($s) => $s['interaction'], array_filter($first['deck']['slides'], fn ($s) => ($s['interaction']['template'] ?? null) === StoryTemplate::KEY)));
        [$service, $completer] = $this->service($replies);
        $result = $service->fromRaw($this->chapter(), $options + [
            'template' => StoryTemplate::KEY, 'story_config' => StoryTemplate::defaults(), 'teaching_method' => 'experiential',
            'story_prebuilt' => ['bible' => $first['story']['bible'], 'notes' => $first['story']['notes'], 'skeletons' => array_combine($keys, array_column($first['story']['acts'], 'skeleton')), 'interactions' => array_combine($keys, $interactions)],
        ]);
        $result['prompts'] = $completer->prompts ?? [];

        return $result;
    }

    private function storyBodies(array $result): array
    {
        $out = [];
        foreach ($result['deck']['slides'] as $s) {
            foreach ((array) ($s['interaction']['nodes'] ?? []) as $n) {
                $out[$s['n'] . '/' . $n['id']] = [$n['title'], $n['body'], $n['explain'] ?? null, $n['variants'] ?? null, array_map(fn ($c) => [$c['text'], $c['outcome'], $c['why']], $n['choices'])];
            }
        }

        return $out;
    }

    public function test_the_picture_pass_gives_the_most_important_scenes_pictures_first_within_its_time_and_rewrites_no_text(): void
    {
        $first = $this->generate($this->script());
        $this->assertSame(0, $first['report']['stats']['story']['pictures'], 'the finished story has no pictures yet');

        $this->searchOverride = $this->imageSearch($this->goodImage());
        $this->pixelOverride = function (): array {
            usleep(1_300_000);   // the first search uses up the one-second budget

            return ['ok' => true, 'shows' => 'A paper map of a town laid flat on a table', 'reason' => 'a map is a simplified model of a place', 'notice' => 'Look at how few streets the map draws.'];
        };
        $plan = [
            'a1/s1' => ['image_queries' => ['map model'], 'picture_caption' => 'Notice what the map leaves out.'],
            'a2/s1' => ['image_queries' => ['map model']],
            'a1/s2a' => ['process_step' => 'investigation', 'picture_caption' => 'Notice that this scene is the investigation step.'],
            'a3/s3' => ['image_queries' => ['map model']],
            'a1/e1' => ['image_queries' => ['bogus 123'], 'process_step' => 'hypothesis', 'picture_caption' => 'The map shows 20,000 streets.'],
        ];
        $r = $this->picturePass($first, [json_encode(['plan' => $plan])], ['picture_seconds' => 1]);

        $this->assertTrue($r['report']['ok'], json_encode($r['report']['errors']));
        $rep = $r['pictures']['report'];
        $this->assertSame(1, $rep['scenes_searched'], 'the one-second budget allowed one search');
        $this->assertSame(1, $rep['photographs_kept']);
        $this->assertGreaterThanOrEqual(2, $rep['scenes_pending_when_time_ran_out']);
        $this->assertCount(1, $r['prompts'], 'one model call planned every picture');
        // The opening scene of the first act was worked first, with the writer-planned caption.
        $s1 = array_values(array_filter($r['deck']['slides'][1]['interaction']['nodes'], fn ($n) => $n['id'] === 's1'))[0];
        $this->assertSame('photo', $s1['image']['type']);
        $this->assertSame('Notice what the map leaves out.', $s1['image']['caption']);
        $this->assertSame('BY-SA 4.0', $s1['image']['licence']);
        // Bad plan entries lost only the part that broke a rule: digits in a query, an unknown step, a figure in a caption.
        $this->assertArrayNotHasKey('a1/e1', array_filter($r['pictures']['plan']));
        // Not one word of the story changed.
        $this->assertSame($this->storyBodies($first), $this->storyBodies($r));
        // A second pass continues from the saved plan: no new model call, and a scene that already has its picture is not searched again.
        $this->pixelOverride = fn () => ['ok' => true, 'shows' => 'A diagram', 'reason' => 'fits'];
        $again = $this->picturePass($first, [], ['picture_seconds' => 60, 'picture_plan' => $r['pictures']['plan'], 'picture_misses' => $r['pictures']['misses']]);
        $this->assertCount(0, $again['prompts'], 'the plan was reused');
        $this->assertTrue($again['report']['ok'], json_encode($again['report']['errors']));
    }

    public function test_a_process_step_in_the_plan_draws_the_sequence_and_a_failed_search_is_not_repeated(): void
    {
        $first = $this->generate($this->script());
        $this->searchOverride = $this->imageSearch(null);   // nothing is ever found
        $plan = [
            'a1/s2a' => ['process_step' => 'evidence', 'picture_caption' => 'Notice that the evidence step is the third of four.'],
            'a1/s2b' => ['image_queries' => ['weather station']],
            'a1/s2c' => ['image_queries' => ['weather station']],
        ];
        $r = $this->picturePass($first, [json_encode(['plan' => $plan])], ['picture_seconds' => 60]);

        $this->assertTrue($r['report']['ok'], json_encode($r['report']['errors']));
        $rep = $r['pictures']['report'];
        $nodes = array_column($r['deck']['slides'][1]['interaction']['nodes'], null, 'id');
        $this->assertSame('diagram', $nodes['s2a']['image']['type']);
        $this->assertSame('evidence', $nodes['s2a']['image']['step']);
        $this->assertSame('Notice that the evidence step is the third of four.', $nodes['s2a']['image']['caption']);
        $this->assertStringContainsString('The step in focus is Evidence.', $nodes['s2a']['image']['alt']);
        $this->assertArrayNotHasKey('image', $nodes['s2b'], 'no photograph found, and no diagram planned: no picture, not a filler');
        $this->assertSame(1, $rep['diagrams_kept']);
        $this->assertSame(0, $rep['photographs_kept']);
        $this->assertSame(1, $rep['repeat_searches_avoided'], 'the same failed search was made once');
        $this->assertSame(2, $rep['image_search']['searches'], 'one query: the search tries Wikimedia, then every source; the repeat never reached it');
        $this->assertSame(2, $rep['scenes_searched_without_a_keeper']);
    }
    public function test_the_choices_of_a_decision_are_shown_in_a_mixed_but_stable_order_with_nothing_else_changed(): void
    {
        $f = json_decode((string) file_get_contents(__DIR__ . '/../../Fixtures/story-act-chapter8592.json'), true)['interaction'];
        $mixed = \App\Services\StudyDeck\Story\StoryLessonGenerator::mixChoices($f, 'a1');

        $this->assertSame($mixed, \App\Services\StudyDeck\Story\StoryLessonGenerator::mixChoices($f, 'a1'), 'the same order every time');
        $moved = 0;
        foreach ($f['nodes'] as $k => $node) {
            if ($node['kind'] !== 'decision') {
                $this->assertSame($node, $mixed['nodes'][$k], 'only decisions are touched');
                continue;
            }
            $before = array_column($node['choices'], null, 'id');
            $after = array_column($mixed['nodes'][$k]['choices'], null, 'id');
            ksort($before);
            ksort($after);
            $this->assertSame($before, $after, 'the same choices, with the same wording and the same way on');
            $moved += array_column($node['choices'], 'id') !== array_column($mixed['nodes'][$k]['choices'], 'id') ? 1 : 0;
        }
        $this->assertGreaterThanOrEqual(1, $moved, 'the order a decision was written in is not the order it is shown in');
        $this->assertSame($mixed, \App\Services\StudyDeck\Story\StoryLessonGenerator::mixChoices($mixed, 'a1'), 'mixing an already mixed order changes nothing');
        // Across a whole story the sound choice is no longer always first.
        $positions = [];
        foreach (['a1', 'a2', 'a3', 'a4', 'a5', 'a6'] as $act) {
            foreach (\App\Services\StudyDeck\Story\StoryLessonGenerator::mixChoices($f, $act)['nodes'] as $node) {
                if ($node['kind'] === 'decision') {
                    $positions[] = array_search(true, array_column($node['choices'], 'sound'), true);
                }
            }
        }
        $this->assertGreaterThan(1, count(array_unique($positions)), 'the sound choice is not always in the same place: ' . json_encode($positions));
    }
    public function test_a_curated_plan_adds_a_photograph_beside_a_diagram_that_stays_and_gives_the_strict_check_what_the_picture_must_show(): void
    {
        $first = $this->generate($this->script(null, null, 'investigation'));
        $before = array_values(array_filter($first['deck']['slides'][1]['interaction']['nodes'], fn ($n) => $n['id'] === 's2a'))[0];
        $this->assertSame('investigation', $before['image']['step']);

        $this->searchOverride = $this->imageSearch($this->goodImage());
        $asked = [];
        $this->pixelOverride = function (string $bytes, string $mime, string $query, string $purpose, string $teaches) use (&$asked): array {
            $asked[] = [$query, $purpose];

            return ['ok' => true, 'shows' => 'A paper map of a town laid flat on a table', 'reason' => 'a map is a simplified model of a place',
                'focus' => ['x' => 20.0, 'y' => 30.0, 'w' => 40.0, 'h' => 35.0], 'label' => 'Street layout', 'notice' => 'The checker\'s note.'];
        };
        $curated = ['a1/s2a' => ['image_queries' => ['map model'], 'show' => 'a paper map of a town', 'picture_caption' => 'Notice how few of the real details the map keeps.', 'priority' => 50]];
        $r = $this->picturePass($first, [], ['picture_seconds' => 60, 'picture_curated' => $curated, 'picture_curated_only' => true]);

        $this->assertTrue($r['report']['ok'], json_encode($r['report']['errors']));
        $this->assertCount(0, $r['prompts'], 'a plan written by a person is the whole plan: no model call');
        $this->assertStringContainsString('A photograph that shows: a paper map of a town', $asked[0][1], 'the strict check is told what the picture must show');
        $after = array_values(array_filter($r['deck']['slides'][1]['interaction']['nodes'], fn ($n) => $n['id'] === 's2a'))[0];
        $this->assertSame('photo', $after['image']['type']);
        $this->assertSame('Notice how few of the real details the map keeps.', $after['image']['caption']);
        $this->assertSame('Street layout', $after['image']['focus_label']);
        $this->assertSame(['x' => 20.0, 'y' => 30.0, 'w' => 40.0, 'h' => 35.0], $after['image']['focus']);
        $this->assertCount(1, $after['extra_images']);
        $this->assertSame($before['image']['url'], $after['extra_images'][0]['url'], 'the diagram that taught the step is kept, unchanged');
        $this->assertSame('investigation', $after['extra_images'][0]['step']);
        $rep = $r['pictures']['report'];
        $this->assertSame(1, $rep['photographs_kept']);
        $this->assertSame(0, $rep['diagrams_kept'], 'the kept diagram is not counted as a new one');
        // Nothing else moved.
        $this->assertSame($this->storyBodies($first), $this->storyBodies($r));
    }

    public function test_a_wrong_process_step_is_corrected_and_a_scene_whose_photograph_is_not_found_keeps_its_diagram(): void
    {
        $first = $this->generate($this->script(null, null, 'observation'));
        $this->searchOverride = $this->imageSearch(null);   // no photograph exists
        $curated = [
            'a1/s2a' => ['process_step' => 'evidence'],
            'a1/s2b' => ['image_queries' => ['weather station'], 'show' => 'a weather station'],
        ];
        $r = $this->picturePass($first, [], ['picture_seconds' => 60, 'picture_curated' => $curated, 'picture_curated_only' => true]);

        $nodes = array_column($r['deck']['slides'][1]['interaction']['nodes'], null, 'id');
        $this->assertSame('evidence', $nodes['s2a']['image']['step'], 'only the step that was wrong was changed');
        $this->assertSame('Step 3 of 4: Evidence.', $nodes['s2a']['image']['caption']);
        $this->assertArrayNotHasKey('image', $nodes['s2b'], 'no photograph and no diagram planned: no picture');
        $this->assertSame(1, $r['pictures']['report']['diagrams_kept']);
        $this->assertSame(0, $r['pictures']['report']['photographs_kept']);
    }

    public function test_a_curated_entry_that_breaks_a_rule_loses_only_that_part(): void
    {
        $first = $this->generate($this->script());
        [$service] = $this->service([]);
        $writer = (new \ReflectionProperty(StoryLessonGenerator::class, 'writer'))->getValue((new \ReflectionProperty(StudyDeckService::class, 'story'))->getValue($service));
        $context = (new \App\Services\StudyDeck\ConceptContextBuilder())->assemble($this->chapter());
        $map = (new \App\Services\StudyDeck\LearningPlanBuilder())->build($context, [], 3, 40, [], null);

        $plan = $writer->curatedPlan([
            'a1/s1' => ['image_queries' => ['map model', 'a very long query of six words', 'ruler 30 cm', 'compass'], 'show' => 'a map', 'picture_caption' => 'The map shows 20,000 streets.', 'process_step' => 'hypothesis', 'priority' => '7'],
        ], $context, $map);

        $this->assertSame(['map model', 'compass'], $plan['a1/s1']['image_queries'], 'a query with six words or digits is dropped, the others stay');
        $this->assertSame('a map', $plan['a1/s1']['show']);
        $this->assertArrayNotHasKey('picture_caption', $plan['a1/s1'], 'a caption that invents a figure is dropped');
        $this->assertArrayNotHasKey('process_step', $plan['a1/s1'], 'an unknown step is dropped');
        $this->assertSame(7, $plan['a1/s1']['priority']);
    }
    // ---------------------------------------------------------------- more sources (NASA, Pexels) and the hard deadline

    public function test_nasa_candidates_come_from_the_official_api_with_credit_and_source_and_a_third_party_copyright_is_left_out(): void
    {
        \Illuminate\Support\Facades\Http::fake(['images-api.nasa.gov/*' => \Illuminate\Support\Facades\Http::response(['collection' => ['items' => [
            ['data' => [['nasa_id' => 'NHQ1', 'title' => '2017 Total Solar Eclipse', 'media_type' => 'image', 'photographer' => '(NASA/Aubrey Gemignani)', 'keywords' => ['Sun', 'Eclipse'], 'description' => 'The Moon covers the Sun.']], 'links' => [['href' => 'http://images-assets.nasa.gov/image/NHQ1/NHQ1~medium.jpg', 'rel' => 'preview']]],
            ['data' => [['nasa_id' => 'X2', 'title' => 'Eclipse by a visitor', 'media_type' => 'image', 'description' => 'Image copyright Someone, used with permission.']], 'links' => [['href' => 'http://images-assets.nasa.gov/image/X2/X2~medium.jpg']]],
            ['data' => [['nasa_id' => 'V3', 'title' => 'A video', 'media_type' => 'video']], 'links' => [['href' => 'http://images-assets.nasa.gov/video/V3/V3~small.mp4']]],
        ]]], 200)]);

        $found = (new \App\Services\PAL\Integration\NasaImageSearch())->search('total solar eclipse', 5);

        $this->assertCount(1, $found, 'the picture of a third party and the video are not candidates');
        $this->assertSame('https://images-assets.nasa.gov/image/NHQ1/NHQ1~medium.jpg', $found[0]['url'], 'an https address');
        $this->assertSame('https://images.nasa.gov/details/NHQ1', $found[0]['source_url']);
        $this->assertSame('NASA/Aubrey Gemignani', $found[0]['creator']);
        $this->assertSame('NASA media usage guidelines', $found[0]['license']);
        $this->assertStringContainsString('NASA is acknowledged as the source', $found[0]['attribution']);
        \Illuminate\Support\Facades\Http::assertSent(fn ($r) => !$r->hasHeader('Authorization') && str_contains($r->url(), 'media_type=image'));
        $this->assertTrue((new ImagePlanner($this->imageSearch(null), $this->memoryStore()))->licenceAccepted($found[0]['license']));
    }

    public function test_pexels_does_nothing_without_a_key_and_with_one_sends_it_in_a_header_and_carries_the_photographer_credit(): void
    {
        \Illuminate\Support\Facades\Http::fake(['unrelated.invalid/*' => \Illuminate\Support\Facades\Http::response([], 200)]);   // no catch-all: a call to Pexels would be a real one
        config(['pal_content.image.pexels.key' => '']);
        $this->assertFalse((new \App\Services\PAL\Integration\PexelsImageSearch())->available());
        $this->assertSame([], (new \App\Services\PAL\Integration\PexelsImageSearch())->search('party balloons'));
        \Illuminate\Support\Facades\Http::assertNothingSent();

        config(['pal_content.image.pexels.key' => 'test-key-not-real']);
        \Illuminate\Support\Facades\Http::fake(['api.pexels.com/*' => \Illuminate\Support\Facades\Http::response(['photos' => [
            ['id' => 7, 'url' => 'https://www.pexels.com/photo/balloons-7/', 'photographer' => 'A. Person', 'alt' => 'Colourful party balloons', 'width' => 4000, 'height' => 3000, 'src' => ['large' => 'https://images.pexels.com/photos/7/large.jpeg', 'medium' => 'https://images.pexels.com/photos/7/medium.jpeg']],
            ['id' => 8, 'url' => 'https://www.pexels.com/photo/x-8/', 'photographer' => '', 'src' => ['large' => 'https://images.pexels.com/photos/8/large.jpeg']],
        ]], 200)]);
        $found = (new \App\Services\PAL\Integration\PexelsImageSearch())->search('party balloons', 5);

        $this->assertCount(1, $found, 'a photo with no photographer to credit is not offered');
        $this->assertSame('Photo by A. Person on Pexels (https://www.pexels.com/photo/balloons-7/)', $found[0]['attribution']);
        $this->assertSame('Pexels License', $found[0]['license']);
        $this->assertSame('https://www.pexels.com/photo/balloons-7/', $found[0]['source_url']);
        \Illuminate\Support\Facades\Http::assertSent(fn ($r) => $r->header('Authorization') === ['test-key-not-real'] && !str_contains($r->url(), 'test-key-not-real'));
    }

    public function test_a_plan_can_name_nasa_for_a_scene_and_the_picture_then_goes_through_every_existing_check_with_its_credit(): void
    {
        $first = $this->generate($this->script());
        $this->searchOverride = $this->imageSearch(null);   // Openverse finds nothing
        $calls = [];
        $this->providersOverride = ['nasa' => function (string $q, int $n) use (&$calls): array {
            $calls[] = $q;

            return [['external_id' => 'NHQ1', 'url' => 'https://images-assets.nasa.gov/image/NHQ1/NHQ1~medium.jpg', 'thumbnail_url' => 'https://images-assets.nasa.gov/image/NHQ1/NHQ1~medium.jpg', 'title' => 'Map as a simplified scientific model',
                'source_url' => 'https://images.nasa.gov/details/NHQ1', 'creator' => 'NASA/A. Photographer', 'license' => 'NASA media usage guidelines', 'attribution' => '"Map" by NASA/A. Photographer, NASA Image and Video Library. NASA is acknowledged as the source.', 'provider' => 'nasa', 'width' => 0, 'height' => 0, 'tags' => ['map', 'model']]];
        }];
        $this->pixelOverride = fn () => ['ok' => true, 'shows' => 'A map of a town', 'reason' => 'a map is a simplified model', 'focus' => ['x' => 10.0, 'y' => 10.0, 'w' => 40.0, 'h' => 40.0], 'label' => 'Town centre'];
        $curated = [
            'a1/s2a' => ['image_queries' => ['map model'], 'show' => 'a map', 'sources' => ['nasa'], 'picture_caption' => 'Notice what the map leaves out.', 'priority' => 10],
            'a1/s2b' => ['image_queries' => ['map model two'], 'show' => 'a map', 'picture_caption' => 'Notice the scale.', 'priority' => 5],
        ];
        $r = $this->picturePass($first, [], ['picture_seconds' => 60, 'picture_curated' => $curated, 'picture_curated_only' => true]);

        $nodes = array_column($r['deck']['slides'][1]['interaction']['nodes'], null, 'id');
        $this->assertSame(['map model'], $calls, 'NASA was asked only for the scene whose plan names it');
        $this->assertSame('photo', $nodes['s2a']['image']['type']);
        $this->assertSame('NASA media usage guidelines', $nodes['s2a']['image']['licence']);
        $this->assertSame('nasa', $nodes['s2a']['image']['provider']);
        $this->assertSame('https://images.nasa.gov/details/NHQ1', $nodes['s2a']['image']['source_url']);
        $this->assertSame('NASA/A. Photographer', $nodes['s2a']['image']['creator']);
        $this->assertStringContainsString('NASA is acknowledged', $nodes['s2a']['image']['attribution']);
        $this->assertTrue($nodes['s2a']['image']['attribution_required']);
        $this->assertSame('Town centre', $nodes['s2a']['image']['focus_label']);
        $this->assertArrayNotHasKey('image', $nodes['s2b'], 'a scene whose plan does not name NASA does not get a NASA picture');
        $rep = $r['pictures']['report'];
        $this->assertSame(['candidates' => 1, 'accepted' => 1], $rep['image_search']['by_source']['nasa']);
    }

    public function test_the_hard_deadline_stops_a_scene_in_the_middle_and_is_not_remembered_as_a_failed_search(): void
    {
        $first = $this->generate($this->script());
        $this->searchOverride = new class extends \App\Services\PAL\Integration\ConceptImageSearchService {
            public function __construct()
            {
            }

            public function rankedImagesFor(string $query, int $limit = 5, ?float $minScore = null, ?string $source = null): array
            {
                usleep(1_300_000);   // the first search alone uses up the one-second budget

                return [];
            }

            public function available(): bool
            {
                return true;
            }
        };
        $curated = ['a1/s2a' => ['image_queries' => ['first query', 'second query'], 'show' => 'a thing']];
        $r = $this->picturePass($first, [], ['picture_seconds' => 1, 'picture_curated' => $curated, 'picture_curated_only' => true]);

        $rep = $r['pictures']['report'];
        $this->assertSame(1, $rep['searches_stopped_by_the_deadline'], 'the second query was never started');
        $this->assertSame(1, $rep['image_search']['searches'], 'one search was made, not the three that two queries would have cost');
        $this->assertArrayNotHasKey('second query|a thing', $r['pictures']['misses'], 'being out of time is not a verdict on a query');
        $this->assertNotEmpty(array_filter($r['story']['notes'], fn ($n) => str_contains($n, 'stopped by the time limit')));
    }
    public function test_the_pixel_check_waits_out_a_per_minute_rate_limit_once_but_not_a_daily_quota_and_never_past_its_deadline(): void
    {
        $make = function (array $replies): array {
            $client = new class($replies) extends \App\Domain\AI\Support\GeminiClient {
                public int $calls = 0;

                public function __construct(private array $replies)
                {
                }

                public function analyzeImage(string $prompt, string $imageBytes, string $mimeType, ?string $model = null, ?int $timeout = null): ?string
                {
                    $this->calls++;
                    $next = array_shift($this->replies);
                    if ($next instanceof \Throwable) {
                        throw $next;
                    }

                    return $next;
                }
            };
            $judge = new \App\Services\StudyDeck\Story\StoryPixelJudge($client);
            $judge->rateLimitWait = 1;

            return [$judge, $client];
        };
        $ok = json_encode(['matches' => true, 'shows' => 'A map', 'reason' => 'fits']);
        $perMinute = new \App\Domain\AI\Support\AiProviderException('rate', 429, 1, 'GenerateContentInputTokensPerModelPerMinute', 'RESOURCE_EXHAUSTED');
        $perDay = new \App\Domain\AI\Support\AiProviderException('quota', 429, 1, 'GenerateRequestsPerDayPerProjectPerModel', 'RESOURCE_EXHAUSTED');

        [$judge, $client] = $make([$perMinute, $ok]);
        $this->assertTrue($judge('b', 'image/png', 'q', 'p', 't')['ok'], 'waited once and asked again');
        $this->assertSame(2, $client->calls);

        [$judge, $client] = $make([$perDay, $ok]);
        $this->assertFalse($judge('b', 'image/png', 'q', 'p', 't')['ok'], 'a spent daily quota is not retried');
        $this->assertSame(1, $client->calls);

        [$judge, $client] = $make([$perMinute, $ok]);
        $judge->deadline = microtime(true) + 3;   // not enough time left to wait and ask again
        $this->assertFalse($judge('b', 'image/png', 'q', 'p', 't')['ok']);
        $this->assertSame(1, $client->calls);
    }

    // ---------------------------------------------------------------- downloads of original Wikimedia files

    private function planner(): ImagePlanner
    {
        $planner = new ImagePlanner($this->imageSearch(null), $this->memoryStore());
        (new \ReflectionProperty(ImagePlanner::class, 'paced'))->setValue($planner, false);

        return $planner;
    }

    private function fetch(ImagePlanner $planner, string $url): ?array
    {
        return (new \ReflectionMethod(ImagePlanner::class, 'fetch'))->invoke($planner, $url);
    }

    private const SOURCE_FILE = 'https://upload.wikimedia.org/wikipedia/commons/7/71/Storm_clouds_over_Ndjamena.jpg';

    /** A Wikimedia candidate as Openverse returns it: the original upload address, and a thumbnail on Openverse's own proxy. */
    private function wikimediaCandidate(array $over = []): array
    {
        return $over + [
            'external_id' => 'w1', 'url' => self::SOURCE_FILE, 'thumbnail_url' => 'https://api.openverse.org/v1/images/abc/thumb/',
            'title' => 'Storm clouds over Ndjamena', 'source_url' => 'https://commons.wikimedia.org/w/index.php?curid=1', 'creator' => 'A. Photographer',
            'license' => 'BY-SA 4.0', 'attribution' => '"Storm clouds" by A. Photographer is licensed under CC BY-SA 4.0.', 'provider' => 'wikimedia',
            'width' => 0, 'height' => 0, 'tags' => ['storm', 'clouds'],
        ];
    }

    private function plannerFor(array $candidate): ImagePlanner
    {
        $planner = new ImagePlanner($this->imageSearch($candidate), $this->memoryStore(), null, 1.0);
        (new \ReflectionProperty(ImagePlanner::class, 'paced'))->setValue($planner, false);

        return $planner;
    }

    private function bigPicture(): string
    {
        $im = imagecreatetruecolor(800, 600);
        mt_srand(3);
        for ($i = 0; $i < 300; $i++) {
            imageline($im, mt_rand(0, 800), mt_rand(0, 600), mt_rand(0, 800), mt_rand(0, 600), imagecolorallocate($im, mt_rand(0, 255), mt_rand(0, 255), mt_rand(0, 255)));
        }
        ob_start();
        imagejpeg($im);

        return (string) ob_get_clean();
    }

    public function test_a_wikimedia_picture_is_downloaded_from_its_original_upload_address_and_keeps_its_source_and_licence(): void
    {
        \Illuminate\Support\Facades\Http::fake([
            'upload.wikimedia.org/*' => \Illuminate\Support\Facades\Http::response($this->bigPicture(), 200, ['Content-Type' => 'image/jpeg']),
            'api.openverse.org/*' => \Illuminate\Support\Facades\Http::response(['error' => 'failed dependency'], 424),
        ]);

        $found = $this->plannerFor($this->wikimediaCandidate())->find('storm clouds', [], 'storm clouds');

        $this->assertArrayNotHasKey('missing', $found);
        $this->assertSame(self::SOURCE_FILE, $found['source_image_url'], 'the original upload address is what was recorded');
        $this->assertSame('https://commons.wikimedia.org/w/index.php?curid=1', $found['source_url']);
        $this->assertSame('BY-SA 4.0', $found['licence']);
        $this->assertStringContainsString('A. Photographer', $found['attribution']);
        $this->assertTrue($found['attribution_required']);
        \Illuminate\Support\Facades\Http::assertNotSent(fn ($r) => str_contains($r->url(), 'api.openverse.org'));
    }

    public function test_the_openverse_thumbnail_proxy_is_never_used_for_wikimedia_and_the_real_failure_is_reported(): void
    {
        \Illuminate\Support\Facades\Http::fake([
            'upload.wikimedia.org/*' => \Illuminate\Support\Facades\Http::response('slow down', 429),
            'api.openverse.org/*' => \Illuminate\Support\Facades\Http::response(['error' => 'failed dependency'], 424),
        ]);
        $planner = $this->plannerFor($this->wikimediaCandidate());

        $found = $planner->find('storm clouds', [], 'storm clouds');

        $this->assertArrayHasKey('missing', $found);
        \Illuminate\Support\Facades\Http::assertNotSent(fn ($r) => str_contains($r->url(), 'api.openverse.org'));
        $failure = collect($planner->events)->firstWhere('decision', 'download failed');
        $this->assertNotNull($failure);
        $this->assertStringContainsString('upload.wikimedia.org answered HTTP 429', $failure['why'], 'the proxy HTTP 424 does not hide the real cause');
        $this->assertStringNotContainsString('424', $failure['why']);
    }

    public function test_a_candidate_from_another_source_may_still_use_its_thumbnail_when_the_original_fails_and_both_failures_are_reported(): void
    {
        \Illuminate\Support\Facades\Http::fake([
            'live.staticflickr.com/*' => \Illuminate\Support\Facades\Http::response('gone', 404),
            'api.openverse.org/*' => \Illuminate\Support\Facades\Http::response(['error' => 'failed dependency'], 424),
        ]);
        $planner = $this->plannerFor($this->wikimediaCandidate(['provider' => 'flickr', 'url' => 'https://live.staticflickr.com/1/2_b.jpg', 'license' => 'BY 2.0']));

        $planner->find('storm clouds', [], 'storm clouds');

        $failure = collect($planner->events)->firstWhere('decision', 'download failed');
        $this->assertStringContainsString('original: live.staticflickr.com answered HTTP 404', $failure['why']);
        $this->assertStringContainsString('thumbnail proxy: api.openverse.org answered HTTP 424', $failure['why']);
    }

    public function test_unsupported_formats_and_pictures_that_are_too_small_or_too_narrow_are_refused_with_a_reason(): void
    {
        $strip = imagecreatetruecolor(1280, 149);
        ob_start();
        imagejpeg($strip);
        $stripBytes = (string) ob_get_clean();

        \Illuminate\Support\Facades\Http::fake(['upload.wikimedia.org/*' => fn ($r) => str_contains($r->url(), 'gif_case')
            ? \Illuminate\Support\Facades\Http::response('GIF89a' . str_repeat("\0", 40), 200, ['Content-Type' => 'image/gif'])
            : \Illuminate\Support\Facades\Http::response($stripBytes, 200, ['Content-Type' => 'image/jpeg'])]);
        foreach (['gif' => 'gif_case', 'strip' => 'strip_case'] as $name => $file) {
            $planner = $this->plannerFor($this->wikimediaCandidate(['url' => 'https://upload.wikimedia.org/wikipedia/commons/7/71/' . $file . '.jpg']));

            $found = $planner->find('storm clouds', [], 'storm clouds');

            $this->assertArrayHasKey('missing', $found, $name);
            $this->assertNotEmpty(array_filter($planner->events, fn ($e) => $e['decision'] !== 'ACCEPTED'), $name);
        }
        $this->assertStringContainsString('too small or too narrow', collect($planner->events)->last()['why']);
    }

    public function test_a_redirect_is_followed_and_a_timeout_is_a_failure_not_a_crash(): void
    {
        \Illuminate\Support\Facades\Http::fake(['upload.wikimedia.org/*' => \Illuminate\Support\Facades\Http::response($this->bigPicture(), 200, ['Content-Type' => 'image/jpeg'])]);
        $this->assertNotNull($this->fetch($this->planner(), self::SOURCE_FILE));

        \Illuminate\Support\Facades\Http::fake(['upload.wikimedia.org/*' => fn () => throw new \Illuminate\Http\Client\ConnectionException('cURL error 28: Operation timed out')]);
        $planner = $this->planner();
        $this->assertNull($this->fetch($planner, self::SOURCE_FILE));
        $this->assertStringContainsString('timed out', $planner->lastDownloadFailure);
    }
    // ---------------------------------------------------------------- Gemini quota and unverified pictures

    private function quotaJudge(array $replies): array
    {
        $client = new class($replies) extends \App\Domain\AI\Support\GeminiClient {
            public int $calls = 0;

            public function __construct(private array $replies)
            {
            }

            public function analyzeImage(string $prompt, string $imageBytes, string $mimeType, ?string $model = null, ?int $timeout = null): ?string
            {
                $this->calls++;
                $next = array_shift($this->replies);
                if ($next instanceof \Throwable) {
                    throw $next;
                }

                return $next;
            }
        };

        return [new \App\Services\StudyDeck\Story\StoryPixelJudge($client), $client];
    }

    public function test_a_spent_quota_is_found_by_one_probe_is_never_retried_and_nothing_is_approved_unverified(): void
    {
        $spent = new \App\Domain\AI\Support\AiProviderException('quota or billing limit used up', 429, 1, 'GenerateRequestsPerDayPerProjectPerModel', 'RESOURCE_EXHAUSTED');
        [$judge, $client] = $this->quotaJudge([$spent]);

        $status = $judge->probe();
        $this->assertFalse($status['available']);
        $this->assertSame(1, $client->calls);

        $checked = $judge('bytes', 'image/png', 'eclipse', 'p', 't');
        $this->assertFalse($checked['ok']);
        $this->assertTrue($checked['unverified']);
        $this->assertStringContainsString('not approved', $checked['reason']);
        $this->assertSame(1, $client->calls, 'no further call is made once the quota is known to be spent');
        $this->assertFalse($judge->probe()['available']);
        $this->assertSame(1, $client->calls);
    }

    public function test_a_per_minute_limit_is_not_a_spent_quota_and_a_working_key_passes_the_probe(): void
    {
        $busy = new \App\Domain\AI\Support\AiProviderException('rate', 429, 1, 'GenerateContentInputTokensPerModelPerMinute', 'RESOURCE_EXHAUSTED');
        [$judge] = $this->quotaJudge([$busy, 'OK']);
        $judge->rateLimitWait = 1;
        $this->assertTrue($judge->probe()['available'], 'waited once, then answered');
        [$judge2] = $this->quotaJudge(['OK']);
        $this->assertTrue($judge2->probe()['available']);
    }

    public function test_when_the_picture_check_cannot_run_the_search_is_not_started_and_a_candidate_is_never_approved_unverified(): void
    {
        $spent = new \App\Domain\AI\Support\AiProviderException('quota or billing limit used up', 429, 1, 'GenerateRequestsPerDayPerProjectPerModel', 'RESOURCE_EXHAUSTED');
        $first = $this->generate($this->script());
        [$judge] = $this->quotaJudge([$spent]);
        $this->pixelOverride = $judge;
        $this->searchOverride = $this->imageSearch(null);
        $curated = ['a1/s2a' => ['image_queries' => ['map model'], 'show' => 'a map']];

        $r = $this->picturePass($first, [], ['picture_seconds' => 60, 'picture_curated' => $curated, 'picture_curated_only' => true]);

        $rep = $r['pictures']['report'];
        $this->assertStringContainsString('picture check cannot run', $rep['blocked']);
        $this->assertSame(0, $rep['photographs_kept']);
        $this->assertSame(0, $rep['scenes_searched'], 'no search window was spent');
    }    public function test_a_source_that_is_left_out_is_never_downloaded_from_or_counted_and_the_other_sources_are_searched_in_one_go(): void
    {
        \Illuminate\Support\Facades\Http::fake([
            'upload.wikimedia.org/*' => \Illuminate\Support\Facades\Http::response('slow down', 429),
            'live.staticflickr.com/*' => \Illuminate\Support\Facades\Http::response($this->bigPicture(), 200, ['Content-Type' => 'image/jpeg']),
        ]);
        $planner = new ImagePlanner($this->imageSearch($this->wikimediaCandidate(), [$this->wikimediaCandidate(['provider' => 'flickr', 'external_id' => 'f1', 'url' => 'https://live.staticflickr.com/1/2_b.jpg', 'thumbnail_url' => 'https://live.staticflickr.com/1/2_q.jpg', 'license' => 'BY 2.0', 'title' => 'Storm clouds gathering'])]), $this->memoryStore(), null, 1.0);
        (new \ReflectionProperty(ImagePlanner::class, 'paced'))->setValue($planner, false);
        $planner->skipSources(['wikimedia']);

        $found = $planner->find('storm clouds', [], 'storm clouds');

        $this->assertArrayNotHasKey('missing', $found);
        $this->assertSame('flickr', $found['provider'] ?? 'flickr');
        $this->assertSame('https://live.staticflickr.com/1/2_b.jpg', $found['source_image_url']);
        \Illuminate\Support\Facades\Http::assertNotSent(fn ($r) => str_contains($r->url(), 'upload.wikimedia.org'));
        $this->assertArrayNotHasKey('wikimedia', $planner->bySource);
        $this->assertSame(1, $planner->stats['searches'], 'one search over the other sources, not Wikimedia first');
    }}
