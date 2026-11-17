<?php

namespace App\Services\StudyDeck\Branching\Pro;

use App\Services\StudyDeck\DiagramRenderer;
use App\Services\StudyDeck\Story\Checkpoint;
use App\Services\StudyDeck\Story\StoryActs;

/**
 * Builds a chapter's pro Branching Scenario: for every act (a topic of the chapter, then a closing mission) the slides
 *
 *     explore (hotspots)  ->  decide (decision)  ->  one outcome per answer  ->  watch (flow)
 *
 * wired so that every answer opens the outcome that belongs to it, a weaker answer offers "Choose again" and "See the intelligent
 * path", the sound outcome carries on to the watch slide, and the watch slide carries on to the next act. A cover, an
 * objectives slide and a closing summary frame the lesson.
 *
 * The model writes the words (ProScenarioWriter); THIS class owns every slide number and every link, so a branch that leads
 * nowhere cannot come out of it, and ProScenarioValidator proves that again on the finished deck.
 *
 * Pictures are fetched through two seams, so the build can be tested with no network and no database:
 *   $pictures(array $nodes, array $act, array &$notes): array   gives each scene node an `image` (the story picture ladder)
 *   $diagram(array $spec): array                                  draws and stores a diagram (the hotspot picture of last resort)
 */
final class ProScenarioBuilder
{
    /** Bump when the shape of what is built changes: kept acts of an older shape are then not reused. */
    private const VERSION = 1;

    /** @var array<int,string> */
    public array $notes = [];

    /** @var array<int,string> concept id => name */
    private array $conceptNames = [];

    /**
     * @param callable(array,array,array&):array|null $pictures
     * @param callable(array):array|null $diagram
     */
    public function __construct(
        private readonly ProScenarioWriter $writer,
        private $pictures = null,
        private $diagram = null,
        /** The most slides the lesson may have: its topics are grouped into as many parts as fit (ProScenarioBudget); null: one part per topic. */
        private readonly ?int $maxSlides = null,
    ) {
    }

    /**
     * @param array<string,mixed> $context ConceptContextBuilder::assemble()
     * @param array<string,mixed> $map LearningPlanBuilder::build()
     * @param array<string,mixed> $config StoryTemplate::resolve()['config']
     * @param callable(string,string):void|null $say
     * @return array{plan:array<string,mixed>,content:array<int,array<string,mixed>>,images:array<int,array<string,mixed>>,interactions:array<int,array<string,mixed>>,acts:array<int,array<string,mixed>>,notes:array<int,string>}
     */
    public function build(array $context, array $map, array $config, ?string $method, ?callable $say = null, ?string $checkpointDir = null): array
    {
        $say ??= fn () => null;
        $acts = ProScenarioBudget::group(StoryActs::plan($map, $config), $this->maxSlides);
        if ($acts === []) {
            throw new \RuntimeException('The chapter has no topic with a concept, so there is nothing to build a scenario from.');
        }
        $keep = $checkpointDir === null ? null : new Checkpoint($checkpointDir, ['pro', self::VERSION, $method, $acts, hash('sha1', (string) $context['ground_truth'])]);

        $written = [];
        foreach ($acts as $i => $act) {
            $label = 'Part ' . ($i + 1) . '/' . count($acts);
            $kept = $keep?->load('written-' . $act['key']);
            if ($kept !== null && ProScenarioWriter::check($kept) === [] && ProScenarioWriter::grounding($kept, $context, $map) === []) {
                $say('write', "$label \"{$act['title']}\": kept from an earlier run");
                $written[$act['key']] = $kept;
                continue;
            }
            $say('write', "$label \"{$act['title']}\": writing");
            $result = $this->writer->write($context, $map, $act, $method);
            $this->notes = array_merge($this->notes, $result['notes']);
            $written[$act['key']] = $result['written'];
            $keep?->save('written-' . $act['key'], $result['written']);
        }

        $out = $this->assemble($context, $map, $acts, $written, $say);

        return $out + ['acts' => array_map(fn ($a) => ['key' => $a['key'], 'title' => $a['title'], 'kind' => $a['kind'], 'required_concept_ids' => $a['required_ids']], $acts), 'notes' => $this->notes];
    }

    /**
     * The slides, content, pictures and interactions for already written acts. Public so a replay or a test can run it with no model.
     *
     * @param array<string,mixed> $context
     * @param array<string,mixed> $map
     * @param array<int,array<string,mixed>> $acts StoryActs::plan()
     * @param array<string,array<string,mixed>> $written act key => ProScenarioWriter::normalise()
     * @param callable(string,string):void|null $say
     * @return array{plan:array<string,mixed>,content:array<int,array<string,mixed>>,images:array<int,array<string,mixed>>,interactions:array<int,array<string,mixed>>}
     */
    public function assemble(array $context, array $map, array $acts, array $written, ?callable $say = null): array
    {
        $say ??= fn () => null;
        $this->conceptNames = array_map(fn ($c) => (string) $c['name'], (array) ($map['concepts'] ?? []));
        $slides = [];
        $content = [];
        $images = [];
        $interactions = [];
        $blank = fn (string $title, string $body = '') => [
            'title' => $title, 'body' => $body, 'explanations' => [], 'bullets' => [], 'example' => null, 'misconception' => null, 'relationship_note' => null,
            'check' => null, 'key_idea' => null, 'bloom' => 'understand', 'dok' => 2, 'minutes' => 2,
        ];
        $add = function (array $slide, array $c, ?array $interaction = null, ?array $image = null) use (&$slides, &$content, &$interactions, &$images): int {
            $n = count($slides) + 1;
            $slides[] = $slide + ['n' => $n, 'teaches' => '', 'relationship' => null, 'visual' => null, 'diagram' => null, 'question_ids' => [], 'h5p_pattern' => null, 'uses_baseline' => 'new'];
            $content[$n] = $c;
            if ($interaction !== null) {
                $interactions[$n] = ['interaction' => $interaction, 'reason' => (string) $interaction['reason']];
            }
            if ($image !== null) {
                $images[$n] = $image;
            }

            return $n;
        };
        $chapterName = (string) $context['chapter']['chapter_name'];

        $add(['section' => 'Introduction', 'slide_type' => 'cover', 'title' => $chapterName, 'concept_ids' => [], 'taught_concept_ids' => []],
            $blank($chapterName, 'Explore each situation, make a decision, and see what your choice leads to.'));

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
            $add(['section' => 'Introduction', 'slide_type' => 'objectives', 'title' => 'What you will be able to do', 'concept_ids' => [], 'taught_concept_ids' => []],
                ['bullets' => array_slice(array_values(array_unique($bullets)), 0, 7)] + $blank('What you will be able to do', 'You will meet each of these ideas in a situation where you have to decide.'));
        }

        $conclusions = [];
        $usedTitles = [];
        $unique = function (string $title, string $part) use (&$usedTitles): string {
            $key = mb_strtolower(trim($title));
            if (isset($usedTitles[$key])) {
                $title = rtrim($title, '. ') . ' (' . $part . ')';
                $key = mb_strtolower(trim($title));
            }
            $usedTitles[$key] = true;

            return $title;
        };
        $firstOfAct = [];
        $flowOfAct = [];
        foreach ($acts as $ai => $act) {
            $w = $written[$act['key']] ?? null;
            if ($w === null) {
                throw new \RuntimeException('Act "' . $act['title'] . '" has no written text.');
            }
            $say('pictures', 'Part ' . ($ai + 1) . '/' . count($acts) . ' "' . $act['title'] . '": slides and pictures');
            $mission = $act['kind'] === StoryActs::MISSION;
            $ids = $mission ? $act['required_ids'] : $act['concept_ids'];
            $section = $mission ? 'Final mission' : $act['title'];
            $explanations = [];
            foreach ($ids as $id) {
                $def = trim((string) ($map['concepts'][$id]['definition'] ?? ''));
                if ($def !== '') {
                    $explanations[] = ['concept_id' => (int) $id, 'text' => $def];
                }
            }
            $options = self::arrange($w['decision']['options'], $ai);
            $w['scene']['title'] = $unique($w['scene']['title'], $act['title']);
            $w['decision']['title'] = $unique($w['decision']['title'], $act['title']);
            $w['flow']['title'] = $unique($w['flow']['title'], $act['title']);
            foreach ($options as $k => $o) {
                $options[$k]['title'] = $unique($o['title'], $act['title']);
            }

            // Slide numbers for this act, fixed before anything is written so every link is known.
            $base = count($slides) + 1;
            $nExplore = $base;
            $nDecide = $base + 1;
            $nOutcome = [];
            foreach (array_keys($options) as $k) {
                $nOutcome[$k] = $base + 2 + $k;
            }
            $nFlow = $base + 2 + count($options);
            $soundIndex = (int) array_key_first(array_filter($options, fn ($o) => $o['sound']));

            // One picture for each slide that is drawn over one. Each is searched from what the slide is about.
            $nodes = [
                ['id' => 'explore', 'kind' => 'scene', 'title' => $w['scene']['title'], 'body' => $w['scene']['situation'], 'concept_ids' => array_map('intval', $ids)],
                ['id' => 'decide', 'kind' => 'scene', 'title' => $w['decision']['title'], 'body' => $w['decision']['question'], 'concept_ids' => array_map('intval', $ids)],
            ];
            foreach ($options as $k => $o) {
                $nodes[] = ['id' => 'o' . ($k + 1), 'kind' => 'ending', 'title' => $o['title'], 'body' => $o['what'], 'concept_ids' => array_map('intval', $ids)];
            }
            $notes = [];
            $nodes = $this->pictures === null ? $nodes : ($this->pictures)($nodes, $act, $notes);
            $this->notes = array_merge($this->notes, $notes);
            $pic = [];
            foreach ($nodes as $node) {
                $pic[$node['id']] = is_array($node['image'] ?? null) && !empty($node['image']['url']) ? $node['image'] : null;
            }
            $pic = self::withoutGeneric($pic);
            $pic = $this->diagramInsteadOfGeneric($pic, $act, $ids, $w);

            // EXPLORE
            [$exploreImage, $spots] = $this->exploreSurface($pic['explore'] ?? null, $w, $act, $context, $ids);
            $add(
                ['section' => $section, 'slide_type' => 'teach', 'title' => $w['scene']['title'], 'concept_ids' => array_map('intval', $ids), 'taught_concept_ids' => array_map('intval', $ids)],
                ['explanations' => $explanations, 'minutes' => 3] + $blank($w['scene']['title'], $w['scene']['situation']),
                $spots === null ? self::missingSpots() : [
                    'kind' => 'hotspots', 'reason' => 'Select a part of the picture.', 'intro' => $w['scene']['intro'], 'spots' => $spots, 'wrapup' => $w['flow']['wrapup'],
                ],
                $exploreImage
            );

            // DECIDE
            $decidePicture = $pic['decide'] ?? $pic['explore'] ?? null;
            $add(
                ['section' => $section, 'slide_type' => 'teach', 'title' => $w['decision']['title'], 'concept_ids' => [], 'taught_concept_ids' => [], 'next' => ProScenarioFormat::WAIT],
                $blank($w['decision']['title']),
                [
                    'kind' => 'decision', 'reason' => 'A choice that changes what happens next.', 'kicker' => $w['decision']['kicker'], 'question' => $w['decision']['question'],
                    'options' => array_map(fn ($o, $k) => ['id' => chr(97 + $k), 'text' => $o['text'], 'goto' => $nOutcome[$k]], $options, array_keys($options)),
                ],
                self::slideImage($decidePicture)
            );

            // OUTCOMES, one per answer
            foreach ($options as $k => $o) {
                $sound = $k === $soundIndex;
                $actions = $sound
                    ? [['label' => 'Watch how it works', 'goto' => $nFlow, 'primary' => true], ['label' => 'Choose again', 'goto' => $nDecide]]
                    : [['label' => 'Choose again', 'goto' => $nDecide, 'primary' => true], ['label' => 'See the intelligent path', 'goto' => $nOutcome[$soundIndex]]];
                $picture = $pic['o' . ($k + 1)] ?? $decidePicture;
                $add(
                    ['section' => $section, 'slide_type' => 'teach', 'title' => $o['title'], 'concept_ids' => [], 'taught_concept_ids' => [], 'next' => $sound ? $nFlow : $nOutcome[$soundIndex]],
                    $blank($o['title']),
                    [
                        'kind' => 'outcome', 'reason' => 'What the choice leads to.', 'tone' => $sound ? ProScenarioFormat::TONE_SOUND : ProScenarioFormat::TONE_CAUTION,
                        'choice' => ['id' => chr(97 + $k), 'text' => $o['text']], 'what' => $o['what'], 'why' => $o['why'], 'actions' => $actions,
                    ],
                    self::slideImage($picture)
                );
            }

            // WATCH: no `next`, so Continue goes on to the following act (or the summary).
            $flowSlide = ['section' => $section, 'slide_type' => 'teach', 'title' => $w['flow']['title'], 'concept_ids' => [], 'taught_concept_ids' => []];
            $firstOfAct[$ai] = $nExplore;
            $flowOfAct[$ai] = $add($flowSlide, $blank($w['flow']['title']), [
                'kind' => 'flow', 'reason' => 'A guided tour of the sound path.', 'intro' => $w['flow']['intro'],
                'steps' => array_map(fn ($label, $k) => ['id' => 's' . ($k + 1), 'label' => $label], $w['flow']['steps'], array_keys($w['flow']['steps'])),
                'wrapup' => $w['flow']['wrapup'],
            ], self::slideImage($decidePicture));
            if ($flowOfAct[$ai] !== $nFlow) {
                throw new \LogicException('The watch slide of "' . $act['title'] . '" was numbered ' . $flowOfAct[$ai] . ', not ' . $nFlow . '.');
            }
            $conclusions[] = $w['conclusion'] !== '' ? $w['conclusion'] : $w['flow']['wrapup'];
        }

        $add(['section' => 'Review', 'slide_type' => 'summary', 'title' => 'What you have learned', 'concept_ids' => [], 'taught_concept_ids' => []],
            ['bullets' => array_slice($conclusions, 0, 8)] + $blank('What you have learned', 'Each choice you made showed how the ideas of this chapter work together.'));

        $plan = [
            'teaching_strategy' => 'A branching scenario: for each topic the student explores a real situation, makes a decision, and sees a consequence and explanation for the choice made. Weaker choices lead back to the decision and to the sound path.',
            'baseline_review' => null, 'slides' => $slides,
        ];

        return ['plan' => $plan, 'content' => $content, 'images' => $images, 'interactions' => $interactions];
    }

    /**
     * A generic picture (a stock photograph of the subject, found for NO scene in particular) is not a picture of this part: a lab coat over a
     * lesson on ploughing. A slide that would get one takes the part's own best picture instead (a photograph found for its scene, or its topic
     * diagram); a picture is generic only where the part has nothing better, and then it stays so that the validator and the report can say so.
     *
     * @param array<string,array<string,mixed>|null> $pic node id => picture
     * @return array<string,array<string,mixed>|null>
     */
    public static function withoutGeneric(array $pic): array
    {
        $own = array_values(array_filter($pic, fn ($p) => $p !== null && ($p['match_level'] ?? '') !== 'generic'));
        if ($own === []) {
            return $pic;
        }
        // The scene's own photograph first; a drawn topic diagram only after the photographs.
        usort($own, fn ($a, $b) => (int) (($a['type'] ?? '') === 'diagram') <=> (int) (($b['type'] ?? '') === 'diagram'));
        foreach ($pic as $id => $p) {
            if ($p !== null && ($p['match_level'] ?? '') === 'generic') {
                $pic[$id] = $own[0];
            }
        }

        return $pic;
    }

    /**
     * A slide whose only picture would still be a generic stock photograph gets the part's topic diagram instead (the chapter's own concept names
     * around the topic), never an unrelated photograph. Where no diagram can be drawn either, the generic picture stays so it is reported.
     *
     * @param array<string,array<string,mixed>|null> $pic
     * @param array<int,int|string> $ids
     * @return array<string,array<string,mixed>|null>
     */
    private function diagramInsteadOfGeneric(array $pic, array $act, array $ids, array $w): array
    {
        $generic = array_keys(array_filter($pic, fn ($p) => $p !== null && ($p['match_level'] ?? '') === 'generic'));
        if ($generic === [] || $this->diagram === null) {
            return $pic;
        }
        $names = array_values(array_filter(array_map(fn ($id) => (string) ($this->conceptNames[$id] ?? ''), $ids), fn ($n) => $n !== '' && mb_strlen($n) <= 60));
        $names = array_slice($names, 0, 5);
        $spec = ['layout' => 'hub', 'title' => mb_substr((string) $w['scene']['title'], 0, 60), 'center' => mb_substr((string) $act['title'], 0, 60), 'nodes' => $names];
        if (count($names) < 2 || DiagramRenderer::problems($spec) !== []) {
            return $pic;
        }
        try {
            $drawn = ($this->diagram)($spec);
        } catch (\Throwable $e) {
            $this->notes[] = 'Part "' . $act['title'] . '": the topic diagram could not be drawn (' . $e->getMessage() . ').';

            return $pic;
        }
        $image = [
            'type' => 'diagram', 'role' => 'diagram', 'url' => $drawn['url'], 'alt' => $drawn['alt'], 'caption' => $drawn['caption'] ?? null, 'width' => $drawn['width'],
            'height' => $drawn['height'], 'texts' => $drawn['texts'], 'licence' => null, 'source_url' => null, 'creator' => null, 'attribution' => '',
            'attribution_required' => false, 'match_level' => 'topic',
        ];
        foreach ($generic as $id) {
            $pic[$id] = $image;
        }

        return $pic;
    }

    /**
     * Where the sound answer goes, as a letter, is decided by code: the model's habit of putting it first would let a learner pass by always
     * choosing A. The order is a fixed function of the act's position, so a replay gives the same deck.
     *
     * @param array<int,array<string,mixed>> $options
     * @return array<int,array<string,mixed>>
     */
    public static function arrange(array $options, int $actIndex): array
    {
        $sound = array_values(array_filter($options, fn ($o) => $o['sound']));
        $weak = array_values(array_filter($options, fn ($o) => !$o['sound']));
        $position = [1, 2, 0][$actIndex % 3];
        $out = $weak;
        array_splice($out, min($position, count($out)), 0, $sound);

        return array_values($out);
    }

    /**
     * The picture of the explore slide and the parts on it. A photograph whose parts the picture check found keeps exactly those (they were
     * found ON the picture); otherwise a diagram is drawn from the parts the model wrote, and its parts sit where the drawing put them.
     *
     * @param array<string,mixed>|null $picture
     * @return array{0:?array<string,mixed>,1:?array<int,array<string,mixed>>} the slide's picture and its spots (null: none could be made)
     */
    private function exploreSurface(?array $picture, array $w, array $act, array $context, array $ids = []): array
    {
        if ($picture !== null && !empty($picture['hotspots']) && is_array($picture['hotspots'])) {
            $image = self::slideImage($picture);
            $image['annotated'] = true;

            return [$image, array_values($picture['hotspots'])];
        }

        $parts = $w['scene']['parts'];
        $nodes = array_values(array_map(fn ($p) => $p['label'], $parts));
        // A label the chapter does not use is never drawn: the chapter's own concept names stand in for the model's labels (the texts keep their order).
        $fromNames = false;
        if (!self::labelsInChapter($nodes, (string) $context['ground_truth'])) {
            $names = array_values(array_filter(array_map(fn ($id) => (string) ($this->conceptNames[$id] ?? ''), $ids), fn ($n) => $n !== '' && mb_strlen($n) <= 60));
            $k = min(count($names), count($parts), 5);
            if ($k >= 2) {
                $nodes = array_slice($names, 0, $k);
                $w['scene']['parts'] = array_slice($parts, 0, $k);
                $fromNames = true;
            }
        }
        $spec = ['layout' => 'hub', 'title' => $w['scene']['title'], 'center' => mb_substr((string) $act['title'], 0, 60), 'nodes' => $nodes];
        if ($this->diagram === null || DiagramRenderer::problems($spec) !== [] || (!$fromNames && !self::labelsInChapter($nodes, (string) $context['ground_truth']))) {
            $this->notes[] = 'Part "' . $act['title'] . '": no picture with parts could be made for the explore slide.';

            return [self::slideImage($picture), null];
        }
        try {
            $drawn = ($this->diagram)($spec);
        } catch (\Throwable $e) {
            $this->notes[] = 'Part "' . $act['title'] . '": the diagram could not be drawn (' . $e->getMessage() . ').';

            return [self::slideImage($picture), null];
        }
        $image = [
            'type' => 'diagram', 'role' => 'diagram', 'url' => $drawn['url'], 'alt' => $drawn['alt'], 'caption' => $drawn['caption'] ?? null,
            'width' => $drawn['width'], 'height' => $drawn['height'], 'texts' => $drawn['texts'], 'licence' => null, 'source_url' => null,
            'creator' => null, 'attribution' => '', 'attribution_required' => false,
        ];
        $spots = [];
        foreach (DiagramRenderer::anchors($spec) as $k => $a) {
            $spots[] = $a + ['id' => 'h' . ($k + 1), 'text' => $w['scene']['parts'][$k]['text']];
        }

        return [$image, array_map(fn ($s) => ['id' => $s['id'], 'label' => $s['label'], 'x' => $s['x'], 'y' => $s['y'], 'w' => $s['w'], 'h' => $s['h'], 'text' => $s['text']], $spots)];
    }

    /** A hotspots interaction with no parts: it is kept (so the slide exists) but the validator refuses the deck, naming the slide. */
    private static function missingSpots(): array
    {
        return ['kind' => 'hotspots', 'reason' => 'Select a part of the picture.', 'intro' => '', 'spots' => [], 'wrapup' => ''];
    }

    /**
     * A story picture as the deck's slide picture: what the player and the stored deck need, without the picture ladder's bookkeeping.
     *
     * @param array<string,mixed>|null $image
     * @return array<string,mixed>|null
     */
    public static function slideImage(?array $image): ?array
    {
        if ($image === null || trim((string) ($image['url'] ?? '')) === '') {
            return null;
        }
        unset($image['hotspots'], $image['match_level'], $image['match_note'], $image['extra_images']);
        $image['role'] = $image['role'] ?? 'scene';

        return $image;
    }

    /** At least four in five labels are made of words the chapter itself uses: a diagram never names a thing the chapter does not. */
    private static function labelsInChapter(array $labels, string $truth): bool
    {
        $truth = mb_strtolower($truth);
        $ok = 0;
        foreach ($labels as $label) {
            $words = array_filter(preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower((string) $label), -1, PREG_SPLIT_NO_EMPTY) ?: [], fn ($w) => mb_strlen($w) >= 4);
            $missing = array_filter($words, fn ($w) => !str_contains($truth, $w) && !(str_ends_with($w, 's') && str_contains($truth, mb_substr($w, 0, -1))));
            if ($words === [] || count($missing) === 0) {
                $ok++;
            }
        }

        return $labels !== [] && $ok / count($labels) >= 0.8;
    }

    private static function shorten(string $text, int $words): string
    {
        $w = preg_split('/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return count($w) <= $words ? $text : rtrim(implode(' ', array_slice($w, 0, $words)), ',;:.') . '.';
    }
}
