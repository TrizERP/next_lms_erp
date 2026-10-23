<?php

namespace App\Services\StudyDeck\Story;

use App\Services\StudyDeck\Contracts\Completer;
use App\Services\StudyDeck\ImagePlanner;

/**
 * The story lesson's generation, as StudyDeckService runs it for the story template: acts, then a story bible, then for every act a
 * checked skeleton and its written, illustrated, validated story. It makes no database call and stores nothing; the caller assembles
 * the deck from the result (assemble()) and the existing renderer, validator and publisher take it from there.
 */
class StoryLessonGenerator
{
    public StoryPlanner $planner;

    public StoryWriter $writer;

    public function __construct(Completer $completer, private readonly ?ImagePlanner $images = null)
    {
        $this->planner = new StoryPlanner($completer);
        $this->writer = new StoryWriter($completer, $images);
    }

    /**
     * Give this run the picture ladder: every scene and ending ends with a picture of its own (see StoryPictureCover). Without a picture planner there
     * is nowhere to store pictures, so nothing is enabled and the coverage rule will name the scenes that stayed bare.
     */
    public function enableCoverage(StoryImageLibrary $library, ?SceneIllustrator $illustrator = null, bool $allowPlaceholder = false): ?StoryPictureCover
    {
        return $this->images === null ? null : ($this->writer->cover = new StoryPictureCover($this->images, $library, $illustrator, $allowPlaceholder));
    }

    /**
     * @param array<string,mixed> $context ConceptContextBuilder::assemble()
     * @param array<string,mixed> $map LearningPlanBuilder::build()
     * @param array<string,mixed> $config StoryTemplate::resolve()['config']
     * @param callable(string,string):void|null $say
     * @param string|null $checkpointDir where finished work is kept so a later failure does not throw it away: the bible, then every act's skeleton and written story (see Checkpoint)
     * @return array{bible:array<string,mixed>,acts:array<int,array{act:array<string,mixed>,skeleton:array<string,mixed>,interaction:array<string,mixed>}>,notes:array<int,string>}
     */
    public function generate(array $context, array $map, array $config, ?string $method, ?callable $say = null, ?string $checkpointDir = null): array
    {
        $say ??= fn () => null;
        $acts = StoryActs::plan($map, $config);
        if ($acts === []) {
            throw new \RuntimeException('The chapter has no topic with a concept, so there is nothing to build a story from.');
        }

        $this->writer->useMap($map);
        $keep = $checkpointDir === null ? null : new Checkpoint($checkpointDir, [$method, $config, $acts, hash('sha1', (string) $context['ground_truth'])]);

        $bible = $keep?->load('bible');
        if ($bible === null) {
            $say('story', 'Stage S1: the story bible (' . count($acts) . ' acts)');
            $bible = $this->planner->bible($context, $map, $acts, $method);
            $keep?->save('bible', $bible);
        } else {
            $say('story', 'Stage S1: the story bible kept from an earlier run');
        }

        $out = [];
        $notes = [];
        $previous = null;
        foreach ($acts as $i => $act) {
            $label = 'Act ' . ($i + 1) . '/' . count($acts);
            $done = $keep?->load('written-' . $act['key']);
            // Picture coverage is not asked of the saved words: an act kept from an earlier run keeps its text and is given its pictures now.
            if ($done !== null && StoryGraph::check($done['interaction'], array_map('intval', $act['required_ids']), array_merge($config, ['image_coverage' => false]))['errors'] === []) {
                $say('story', "$label \"{$act['title']}\": kept from an earlier run");
                $coverNotes = [];
                $done['interaction'] = $this->writer->coverInteraction($done['interaction'], $context, $act, $config, $coverNotes);
                $done['notes'] = array_merge((array) $done['notes'], $coverNotes);
                foreach ((array) $done['notes'] as $n) {
                    $notes[] = 'Act ' . ($i + 1) . ': ' . $n;
                }
                $out[] = ['act' => $act, 'skeleton' => $done['skeleton'], 'interaction' => $done['interaction']];
                $previous = self::carry($done['skeleton'], $done['interaction']);
                foreach ($done['interaction']['nodes'] as $kept) {
                    foreach (array_merge([$kept['image'] ?? null], (array) ($kept['extra_images'] ?? [])) as $img) {
                        if (is_array($img) && !empty($img['source_url']) && ($img['type'] ?? '') === 'photo') {
                            $this->writer->usedImageUrls[] = (string) ($img['source_image_url'] ?? $img['source_url']);
                        }
                    }
                }

                continue;
            }
            $skeleton = $keep?->load('skeleton-' . $act['key']);
            if ($skeleton === null) {
                $say('story', "$label \"{$act['title']}\": skeleton");
                $skeleton = $this->planner->skeleton($context, $map, $act, $bible, $config, $method, $previous);
                $keep?->save('skeleton-' . $act['key'], $skeleton);
            }
            $say('story', "$label: writing " . count($skeleton['nodes']) . ' nodes');
            $written = $this->writer->write($context, $map, $act, $skeleton, $bible, $config, $method, $previous);
            $keep?->save('written-' . $act['key'], ['skeleton' => $skeleton, 'interaction' => $written['interaction'], 'notes' => $written['notes']]);
            foreach ($written['notes'] as $n) {
                $notes[] = 'Act ' . ($i + 1) . ': ' . $n;
            }
            $out[] = ['act' => $act, 'skeleton' => $skeleton, 'interaction' => $written['interaction']];
            $previous = self::carry($skeleton, $written['interaction']);
        }

        $keep?->clear();

        return ['bible' => $bible, 'acts' => $out, 'notes' => $notes];
    }


    /**
     * The order the choices of each decision are shown in. The model writes the sound choice first, and a learner who notices that is playing a
     * quiz, so the order is mixed, the same way every time (from the act and the choice's own id: mixing an already mixed order changes nothing) and
     * with no change to any text or to where a choice leads. Applies to a story generated now and to one rebuilt from a bundle.
     *
     * @param array<string,mixed> $interaction
     * @return array<string,mixed>
     */
    public static function mixChoices(array $interaction, string $actKey): array
    {
        foreach ($interaction['nodes'] as $k => $node) {
            if (($node['kind'] ?? '') !== 'decision' || count($node['choices']) < 2) {
                continue;
            }
            // The sound choice goes to a place chosen from the act and the node (so, across a story, it is spread evenly over the places); the
            // others keep a mixed order of their own around it.
            $sound = array_values(array_filter($node['choices'], fn ($c) => !empty($c['sound'])));
            $others = array_values(array_filter($node['choices'], fn ($c) => empty($c['sound'])));
            $rank = fn (array $c): int => crc32($actKey . '|' . $node['id'] . '|' . $c['id']);
            usort($others, fn ($x, $y) => $rank($x) <=> $rank($y));
            usort($sound, fn ($x, $y) => $rank($x) <=> $rank($y));
            $place = crc32($actKey . '|' . $node['id']) % count($node['choices']);
            array_splice($others, min($place, count($others)), 0, $sound);
            $interaction['nodes'][$k]['choices'] = $others;
        }

        return $interaction;
    }

    /**
     * A finished story rebuilt from a saved review bundle: the acts are planned again from the chapter's own data (the same ones, in the
     * same order), and each is paired with the skeleton and the written act the bundle holds.
     *
     * @param array{bible:array<string,mixed>,notes?:array<int,string>,skeletons:array<string,mixed>,interactions:array<string,mixed>} $saved
     * @return array{bible:array<string,mixed>,acts:array<int,array<string,mixed>>,notes:array<int,string>}
     */
    public static function prebuilt(array $map, array $config, array $saved): array
    {
        $out = [];
        foreach (StoryActs::plan($map, $config) as $act) {
            $skeleton = $saved['skeletons'][$act['key']] ?? null;
            $interaction = $saved['interactions'][$act['key']] ?? null;
            if (!is_array($skeleton) || !is_array($interaction)) {
                throw new \RuntimeException('The saved story has no act "' . $act['key'] . '" (' . $act['title'] . '); it does not match this chapter.');
            }
            $out[] = ['act' => $act, 'skeleton' => $skeleton, 'interaction' => $interaction];
        }

        return ['bible' => $saved['bible'], 'acts' => $out, 'notes' => (array) ($saved['notes'] ?? [])];
    }

    /**
     * The picture pass: give a finished story's scenes the best relevant pictures WITHOUT rewriting any text, within a time budget.
     *
     * One model call plans the pictures for every scene that has none (what to photograph, the process step when a scene genuinely is one, what
     * to notice). The scenes are then worked in order of importance across the whole lesson (the opening scene of each act first, then the scenes
     * that teach the most), each through the same image search, licence checks and picture check the writer uses, until the budget is spent.
     * Scenes not reached are pending, and a later pass picks them up (the plan and the searches already made are handed back to be saved).
     *
     * @param array{bible:array<string,mixed>,acts:array<int,array<string,mixed>>,notes:array<int,string>} $story
     * @param array<string,mixed> $options plan (a saved plan), misses (saved failed searches)
     * @return array{story:array<string,mixed>,report:array<string,mixed>,plan:array<string,mixed>,misses:array<string,string>}
     */
    public function repicture(array $context, array $map, array $config, ?string $method, array $story, float $seconds, ?callable $say = null, array $options = []): array
    {
        $say ??= fn () => null;
        $started = microtime(true);
        $deadline = $started + $seconds;
        // The picture check must be able to run before a search window is spent: a photograph nobody could verify is never approved.
        $status = $this->writer->verificationStatus();
        if ($status !== null && !$status['available']) {
            $say('pictures', 'Picture search not started: ' . $status['reason']);

            return ['story' => $story, 'plan' => (array) ($options['plan'] ?? []), 'misses' => (array) ($options['misses'] ?? []), 'notes' => [],
                'report' => ['blocked' => 'the picture check cannot run: ' . $status['reason'], 'seconds_budget' => $seconds, 'seconds_used' => round(microtime(true) - $started, 1), 'photographs_kept' => 0, 'diagrams_kept' => 0, 'scenes_searched' => 0, 'image_search' => [], 'scene_seconds' => [], 'candidate_log' => []]];
        }
        // A search that failed for a lasting reason is not made again; one that merely could not download is tried again.
        $this->writer->setDeadline($deadline);
        $this->writer->queryMisses = array_filter((array) ($options['misses'] ?? []), fn ($why) => !str_contains((string) $why, 'could not be downloaded'));

        // 1. the scenes that have no picture yet
        $scenes = [];
        foreach ($story['acts'] as $a) {
            $key = $a['act']['key'];
            foreach ($a['interaction']['nodes'] as $n) {
                if ($n['kind'] === 'decision' || !empty($n['image'])) {
                    continue;
                }
                $explain = $n['explain'] ?? null;
                $scenes[] = [
                    'ref' => $key . '/' . $n['id'], 'title' => (string) $n['title'], 'body' => self::shorten((string) $n['body'], 70),
                    'explain' => $explain ? self::shorten(trim(($explain['title'] ?? '') . '. ' . ($explain['body'] ?? '')), 40) : '',
                    'concepts' => array_slice(array_values(array_filter(array_map(fn ($id) => (string) ($map['concepts'][$id]['name'] ?? ''), (array) $n['concept_ids']))), 0, 3),
                ];
            }
        }

        // 2. what each of them should show (one call, or the plan a earlier pass saved)
        $plan = (array) ($options['plan'] ?? []);
        // A plan written by a person who read the scenes replaces the planned one for the scenes it names, and when it is the whole plan no model call is made.
        $curated = $this->writer->curatedPlan((array) ($options['curated'] ?? []), $context, $map);
        foreach ($curated as $ref => $entry) {
            $plan[$ref] = $entry;
        }
        if ($options['curated_only'] ?? false) {
            $plan = array_intersect_key($plan, $curated);   // only the scenes a person named
        }
        $missing = ($options['curated_only'] ?? false) ? [] : array_values(array_filter($scenes, fn ($s) => !array_key_exists($s['ref'], $plan)));
        if ($missing !== []) {
            $say('pictures', 'Planning pictures for ' . count($missing) . ' scenes (one model call)');
            $fresh = $this->writer->planPictures($missing, $context, $map, $method);
            foreach ($missing as $s) {
                $plan[$s['ref']] = $fresh[$s['ref']] ?? [];   // an empty entry: asked, and nothing worth showing
            }
        }

        // 3. most important first, across the whole lesson
        $ranked = [];
        foreach ($story['acts'] as $ai => $a) {
            $key = $a['act']['key'];
            foreach ($a['interaction']['nodes'] as $n) {
                $entry = $plan[$key . '/' . $n['id']] ?? null;
                $diagramOnly = !empty($n['image']) && ($n['image']['type'] ?? '') === 'diagram' && (!empty($entry['image_queries']) || isset($entry['process_step']));
                if ($n['kind'] === 'decision' || (!empty($n['image']) && !$diagramOnly) || empty($entry)) {
                    continue;
                }
                $score = (int) ($entry['priority'] ?? 0) + ($n['id'] === $a['interaction']['start'] ? 100 : 0) + 10 * count((array) $n['concept_ids']) + (isset($n['explain']) ? 20 : 0)
                    + (isset($entry['process_step']) ? 30 : 0) + (!empty($entry['image_queries']) ? 25 : 0) + ($n['kind'] === 'ending' ? 5 : 0);
                $ranked[] = [$score, $ai, $n['id']];
            }
        }
        usort($ranked, fn ($x, $y) => [$y[0], $x[1]] <=> [$x[0], $y[1]]);
        $say('pictures', count($ranked) . ' scenes have a picture plan; working them by importance for ' . (int) max(0, $deadline - microtime(true)) . ' more seconds');

        // 4. search, check, keep
        $notes = [];
        $done = 0;
        foreach ($ranked as [$score, $ai, $id]) {
            $act = $story['acts'][$ai];
            $key = $act['act']['key'];
            $act['interaction']['key'] = $key;
            $story['acts'][$ai]['interaction'] = $this->writer->repicture($act['interaction'], [$id => $plan[$key . '/' . $id]], [$id], $context, $map, $config, $deadline, $notes);
            unset($story['acts'][$ai]['interaction']['key']);
            if ((++$done % 5) === 0) {
                $say('pictures', "$done of " . count($ranked) . ' scenes worked; ' . $this->writer->pictureStats['photos'] . ' photographs and ' . $this->writer->pictureStats['diagrams'] . ' diagrams kept');
            }
        }

        // Picture coverage: every scene and ending the search left bare goes down the ladder, within no time limit (a placeholder takes no search).
        if (!empty($config['image_coverage'])) {
            foreach ($story['acts'] as $ai => $a) {
                $coverNotes = [];
                $story['acts'][$ai]['interaction'] = $this->writer->coverInteraction($a['interaction'], $context, $a['act'], $config, $coverNotes);
                $notes = array_merge($notes, $coverNotes);
            }
        }

        $pictures = ['photo' => 0, 'diagram' => 0];
        $licences = [];
        $without = [];
        $perAct = [];
        foreach ($story['acts'] as $a) {
            $key = $a['act']['key'];
            $withPicture = 0;
            foreach ($a['interaction']['nodes'] as $n) {
                if ($n['kind'] === 'decision') {
                    continue;
                }
                foreach (array_merge([$n['image'] ?? null], (array) ($n['extra_images'] ?? [])) as $img) {
                    if (!is_array($img)) {
                        continue;
                    }
                    $pictures[$img['type'] === 'photo' ? 'photo' : 'diagram']++;
                    if ($img['type'] === 'photo') {
                        $licences[(string) $img['licence']] = ($licences[(string) $img['licence']] ?? 0) + 1;
                    }
                }
                if (empty($n['image'])) {
                    $without[] = $key . '/' . $n['id'];
                } else {
                    $withPicture++;
                }
            }
            $perAct[$key] = ['title' => $a['act']['title'], 'scenes_with_picture' => $withPicture];
        }
        $this->writer->setDeadline(null);
        $image = $this->writer->imageStats();
        $report = [
            'seconds_budget' => $seconds, 'seconds_used' => round(microtime(true) - $started, 1),
            'scenes_without_picture_at_start' => count($scenes), 'scenes_with_a_plan' => count($ranked),
            'scenes_searched' => $this->writer->pictureStats['scenes_searched'], 'scenes_pending_when_time_ran_out' => $this->writer->pictureStats['pending'],
            'scenes_searched_without_a_keeper' => $this->writer->pictureStats['no_picture'], 'searches_stopped_by_the_deadline' => $this->writer->pictureStats['stopped_by_deadline'], 'repeat_searches_avoided' => $this->writer->pictureStats['cached_misses'],
            'photographs_kept' => $this->writer->pictureStats['photos'], 'diagrams_kept' => $this->writer->pictureStats['diagrams'],
            'pictures_in_lesson' => $pictures, 'photograph_licences' => $licences,
            'image_search' => $image, 'scene_seconds' => $this->writer->sceneSeconds, 'candidate_log' => $this->writer->imageEvents(), 'scenes_still_without_picture' => count($without), 'per_act' => $perAct,
        ];

        return ['story' => $story, 'report' => $report, 'plan' => $plan, 'misses' => $this->writer->queryMisses, 'notes' => $notes];
    }

    /**
     * What one act hands to the next so the lesson reads as one investigation: how the act was left, what the team now knows and the
     * way it ended (the best ending, shortened).
     *
     * @param array<string,mixed> $skeleton
     * @param array<string,mixed> $interaction
     * @return array{previous_act:string,what_the_team_now_knows:string,how_it_ended:string}
     */
    public static function carry(array $skeleton, array $interaction): array
    {
        $best = null;
        $byId = array_column($skeleton['nodes'], null, 'id');
        foreach ($interaction['nodes'] as $n) {
            if ($n['kind'] === 'ending' && ($best === null || ($byId[$n['id']]['outcome'] ?? '') === 'best')) {
                $best = $n;
            }
        }
        $words = preg_split('/\s+/u', trim((string) ($best['body'] ?? '')), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return [
            'previous_act' => (string) $skeleton['title'],
            'what_the_team_now_knows' => (string) ($interaction['conclusion'] ?? ''),
            'how_it_ended' => implode(' ', array_slice($words, 0, 60)),
        ];
    }

    /**
     * The deck inputs the existing renderer takes (plan, slide content, slide pictures, interactions) from a generated story.
     * One slide per act; the cover and (where the data has them) the learning objectives frame it.
     *
     * @param array<string,mixed> $context
     * @param array<string,mixed> $map
     * @param array{bible:array<string,mixed>,acts:array<int,array<string,mixed>>} $story generate()
     * @return array{plan:array<string,mixed>,content:array<int,array<string,mixed>>,images:array<int,array<string,mixed>>,interactions:array<int,array<string,mixed>>}
     */
    public static function assemble(array $context, array $map, array $story): array
    {
        $slides = [];
        $content = [];
        $interactions = [];
        $blank = fn (string $title, string $body = '') => [
            'title' => $title, 'body' => $body, 'explanations' => [], 'bullets' => [], 'example' => null, 'misconception' => null, 'relationship_note' => null,
            'check' => null, 'key_idea' => null, 'bloom' => 'understand', 'dok' => 2, 'minutes' => 2,
        ];
        $add = function (array $slide, array $c) use (&$slides, &$content): int {
            $n = count($slides) + 1;
            $slides[] = $slide + ['n' => $n, 'teaches' => '', 'relationship' => null, 'visual' => null, 'diagram' => null, 'question_ids' => [], 'h5p_pattern' => null, 'uses_baseline' => 'new'];
            $content[$n] = $c;

            return $n;
        };

        $bible = $story['bible'];
        $add(['section' => 'Introduction', 'slide_type' => 'cover', 'title' => $context['chapter']['chapter_name'], 'concept_ids' => [], 'taught_concept_ids' => []], $blank($bible['title'], $bible['premise']));

        // The chapter's own learning objectives, one line per topic (the first objective of its first concept), as the framing slide.
        $bullets = [];
        foreach ((array) $map['topics'] as $t) {
            foreach ((array) ($t['concept_ids'] ?? []) as $cid) {
                $o = $map['concepts'][$cid]['objectives'][0] ?? null;
                if (is_string($o) && trim($o) !== '') {
                    $bullets[] = self::shorten(trim($o), 22);
                    break;
                }
            }
        }
        if ($bullets !== []) {
            $bullets = array_slice(array_values(array_unique($bullets)), 0, 7);
            $add(['section' => 'Introduction', 'slide_type' => 'objectives', 'title' => 'What you will be able to do', 'concept_ids' => [], 'taught_concept_ids' => []], ['bullets' => $bullets] + $blank('What you will be able to do', 'You will meet each of these ideas inside the story.'));
        }

        foreach ($story['acts'] as $a) {
            $act = $a['act'];
            $mission = $act['kind'] === StoryActs::MISSION;
            $ids = $mission ? $act['required_ids'] : $act['concept_ids'];
            $explanations = [];
            if (!$mission) {
                foreach ($ids as $id) {
                    $def = trim((string) ($map['concepts'][$id]['definition'] ?? ''));
                    if ($def !== '') {
                        $explanations[] = ['concept_id' => (int) $id, 'text' => $def];
                    }
                }
            }
            $n = $add(
                ['section' => $mission ? 'Review' : $act['title'], 'slide_type' => $mission ? 'application' : 'scenario', 'title' => $a['skeleton']['title'], 'concept_ids' => $ids, 'taught_concept_ids' => $ids],   // the mission "teaches" its anchors: every path of it must meet them (checked by StoryGraph)
                ['explanations' => $explanations, 'bloom' => $mission ? 'apply' : 'understand', 'minutes' => max(3, count($a['interaction']['nodes'])), 'dok' => 2] + $blank($a['skeleton']['title'], (string) ($a['interaction']['mission']['body'] ?? ''))
            );
            $interactions[$n] = ['interaction' => self::mixChoices($a['interaction'], (string) $act['key']), 'reason' => (string) $a['interaction']['reason']];
        }

        $plan = [
            'teaching_strategy' => 'A story lesson (' . StoryTemplate::NAME . '): one continuous story with an act for each topic and a closing mission. In each act the learner reads scenes and makes decisions; different choices lead to different events and explanations, and the paths come back together. Every path of an act teaches every concept of its topic.',
            'baseline_review' => null, 'slides' => $slides,
        ];

        return ['plan' => $plan, 'content' => $content, 'images' => [], 'interactions' => $interactions];
    }

    private static function shorten(string $text, int $words): string
    {
        $w = preg_split('/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return count($w) <= $words ? $text : rtrim(implode(' ', array_slice($w, 0, $words)), ',;:.') . '.';
    }
}
