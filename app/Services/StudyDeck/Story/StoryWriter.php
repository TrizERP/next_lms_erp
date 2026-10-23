<?php

namespace App\Services\StudyDeck\Story;

use App\Services\StudyDeck\Contracts\Completer;
use App\Services\StudyDeck\DiagramRenderer;
use App\Services\StudyDeck\ExtractsJson;
use App\Services\StudyDeck\ImagePlanner;

/**
 * Stage S2 of a story lesson: the words of one act, node by node, written onto a skeleton that has already been proved sound.
 *
 * The writer is handed the node ids and cannot add, remove or rewire a node: its reply is matched to the skeleton by id and by
 * choice order, and anything that does not fit is an error, not a repair. The assembled act is then judged by the full story rules
 * (StoryGraph::check()) and by checks that need prose: the story's recurring characters really appear, no sentence is a question-and-answer
 * item, and no figure is invented. A reply that fails is sent back with the exact reasons, three times at most.
 *
 * A scene is written the way a good explainer scene reads: the situation, what a character notices or tries, what is still uncertain, then
 * a plain-language explanation of the concept it teaches (`explain`). A scene where branches meet also carries a short `variants` lead-in for
 * each branch that reaches it, so what happened on the learner's own path shows in the story that follows.
 *
 * Pictures come last. For each scene a real photograph is looked for through the existing image search (licence-checked, and the picture
 * itself is looked at by the pixel judge before it is kept); a drawn diagram built from the chapter's own words supports it or stands in
 * when no photograph fits. A scene with no good picture is left without one and the reason is noted: a wrong picture is worse than none.
 */
class StoryWriter
{
    use ExtractsJson;

    public array $replies = [];

    /** Source addresses of the photographs already used in this lesson, so no picture appears twice (kept across acts). */
    public array $usedImageUrls = [];

    /** The picture ladder for a run that asks for picture coverage (see StoryPictureCover); null leaves the normal step as it was. */
    public ?StoryPictureCover $cover = null;

    /** @var array<int,string> concept id => name, for what a picture is meant to teach */
    private array $conceptNames = [];

    public function __construct(private readonly Completer $completer, private readonly ?ImagePlanner $images = null)
    {
    }

    /**
     * @param array<string,mixed> $act StoryActs::plan() entry
     * @param array<string,mixed> $skeleton StoryPlanner::skeleton()
     * @param array<string,mixed> $bible StoryPlanner::bible()
     * @param array<string,mixed> $config StoryTemplate::resolve()['config']
     * @param array<string,mixed>|null $previous what the previous act left behind (see StoryLessonGenerator)
     * @return array{interaction:array<string,mixed>,notes:array<int,string>}
     */
    public function write(array $context, array $map, array $act, array $skeleton, array $bible, array $config, ?string $method, ?array $previous = null): array
    {
        $this->conceptNames = array_map(fn ($c) => (string) $c['name'], (array) ($map['concepts'] ?? []));
        $prompt = $this->prompt($context, $map, $act, $skeleton, $bible, $config, $method, $previous);
        $noPictures = $config;
        $noPictures['images'] = [0, 99];   // pictures are added after the words, and judged then
        $noPictures['image_coverage'] = false;

        $errors = [];
        $interaction = [];
        $asked = $prompt;
        for ($round = 0; $round <= 3; $round++) {
            $reply = $this->replies[] = $this->completer->complete(StoryPlanner::SYSTEM, $asked, 24000);
            try {
                $prose = $this->extractJson($reply);
            } catch (\RuntimeException) {
                $errors = ['Your reply was not valid JSON (' . mb_strlen($reply) . ' characters, ending "' . mb_substr(trim($reply), -60) . '"). Return ONE complete JSON object and nothing else; if it was cut off, shorten the texts.'];
                $asked = $prompt . "\n\nYour previous reply was rejected for these reasons:\n- " . implode("\n- ", $errors) . "\n\nReturn the COMPLETE corrected JSON.";
                continue;
            }
            [$interaction, $errors] = $this->assemble($skeleton, $prose);
            if ($errors === []) {
                $errors = StoryGraph::check($interaction, array_map('intval', $act['required_ids']), $noPictures)['errors'];
            }
            if ($errors === []) {
                $errors = $this->prose($interaction, $bible, $context, $map);
            }
            if ($errors === []) {
                break;
            }
            $asked = $prompt . "\n\nYOUR PREVIOUS REPLY (edit this one):\n" . $reply . "\n\nIt was rejected for these reasons:\n- " . implode("\n- ", array_slice($errors, 0, 25))
                . "\n\nReturn the COMPLETE corrected JSON: take your previous reply, change ONLY what the reasons name, and keep every other node, explanation, variant and choice exactly as it is. Do not drop anything that was already right.";
        }
        if ($errors !== []) {
            throw new \RuntimeException('The story for act "' . $act['title'] . "\" is invalid after three repair attempts:\n- " . implode("\n- ", array_slice($errors, 0, 12)));
        }

        $notes = [];
        $interaction = $this->pictures($interaction, $skeleton, $context, $config, $notes);
        $interaction = $this->coverInteraction($interaction, $context, $act, $config, $notes);
        foreach ($interaction['nodes'] as $k => $node) {
            unset($interaction['nodes'][$k]['picture_caption']);   // it lives on as the picture's caption, when the node has a picture
        }
        $final = StoryGraph::check($interaction, array_map('intval', $act['required_ids']), $config);
        if ($final['errors'] !== []) {
            throw new \RuntimeException('The story for act "' . $act['title'] . "\" does not pass the story rules:\n- " . implode("\n- ", array_slice($final['errors'], 0, 12)));
        }

        return ['interaction' => $interaction, 'notes' => $notes];
    }

    /**
     * The nodes that lead to each node of a skeleton.
     *
     * @param array<string,mixed> $skeleton
     * @return array<string,array<int,string>>
     */
    public static function predecessors(array $skeleton): array
    {
        $from = [];
        foreach ($skeleton['nodes'] as $n) {
            $targets = $n['kind'] === 'scene' ? [$n['next'] ?? null] : array_column((array) ($n['choices'] ?? []), 'next');
            foreach ($targets as $t) {
                if ($t !== null && $t !== '') {
                    $from[(string) $t][(string) $n['id']] = (string) $n['id'];
                }
            }
        }

        return array_map('array_values', $from);
    }

    /**
     * The prose laid onto the skeleton. The structure comes from the skeleton alone.
     *
     * @param array<string,mixed> $skeleton
     * @param mixed $prose the decoded reply
     * @return array{0:array<string,mixed>,1:array<int,string>}
     */
    public function assemble(array $skeleton, mixed $prose): array
    {
        $errors = [];
        $by = is_array($prose) ? (array) ($prose['nodes'] ?? []) : [];
        $preds = self::predecessors($skeleton);
        $nodes = [];
        foreach ($skeleton['nodes'] as $n) {
            $p = (array) ($by[$n['id']] ?? []);
            if ($p === []) {
                $errors[] = 'There is no text for node "' . $n['id'] . '".';
            }
            $node = [
                'id' => $n['id'], 'kind' => $n['kind'], 'title' => $this->s($p['title'] ?? ''), 'body' => $this->s($p['body'] ?? ''),
                'prompt' => $n['kind'] === 'decision' ? $this->s($p['prompt'] ?? '') : '', 'concept_ids' => $n['concept_ids'], 'choices' => [],
            ];
            $caption = $this->s($p['picture_caption'] ?? '');
            if ($caption !== '') {
                $node['picture_caption'] = $caption;
            }
            if ($n['kind'] !== 'decision') {
                $explain = (array) ($p['explain'] ?? []);
                if ($this->s($explain['body'] ?? '') !== '') {
                    $node['explain'] = ['title' => $this->s($explain['title'] ?? ''), 'body' => $this->s($explain['body'] ?? '')];
                }
                $from = $preds[$n['id']] ?? [];
                if (count($from) >= 2) {
                    $given = (array) ($p['variants'] ?? []);
                    $variants = [];
                    foreach ($from as $pred) {
                        $text = $this->s($given[$pred] ?? '');
                        if ($text === '') {
                            foreach ($given as $g) {
                                if (is_array($g) && ($g['after'] ?? null) === $pred) {
                                    $text = $this->s($g['body'] ?? '');
                                }
                            }
                        }
                        if ($text !== '') {
                            $variants[] = ['after' => $pred, 'body' => $text];
                        }
                    }
                    if ($variants !== []) {
                        $node['variants'] = $variants;
                    }
                }
            }
            if ($n['kind'] === 'scene') {
                $node['choices'] = [['id' => $n['id'] . '-1', 'text' => 'Continue', 'outcome' => '', 'why' => '', 'sound' => true, 'next' => $n['next']]];
            } elseif ($n['kind'] === 'decision') {
                $texts = array_values((array) ($p['choices'] ?? []));
                if (count($texts) !== count($n['choices'])) {
                    $errors[] = 'Decision "' . $n['id'] . '" must have exactly ' . count($n['choices']) . ' choices written, in the order of the skeleton; you wrote ' . count($texts) . '.';
                }
                foreach ($n['choices'] as $k => $c) {
                    $t = (array) ($texts[$k] ?? []);
                    $node['choices'][] = [
                        'id' => $n['id'] . '-' . ($k + 1), 'text' => $this->s($t['text'] ?? ''), 'outcome' => $this->s($t['outcome'] ?? ''), 'why' => $this->s($t['why'] ?? ''),
                        'sound' => $c['quality'] === 'best', 'next' => $c['next'],
                    ];
                }
            }
            $nodes[] = $node;
        }
        $mission = (array) ($prose['mission'] ?? []);
        $interaction = [
            'kind' => 'scenario', 'template' => StoryTemplate::KEY,
            'reason' => 'A story act: the learner reads scenes, makes decisions and reaches an ending, with a different event and explanation on each path.',
            'mission' => ['title' => $this->s($mission['title'] ?? ''), 'body' => $this->s($mission['body'] ?? '')],
            'situation' => $skeleton['premise'], 'start' => $skeleton['nodes'][0]['id'] ?? '', 'nodes' => $nodes,
            'conclusion' => $this->s($prose['conclusion'] ?? ''),
        ];

        return [$interaction, $errors];
    }

    /**
     * The checks that need prose.
     *
     * @return array<int,string>
     */
    private function prose(array $interaction, array $bible, array $context, array $map): array
    {
        $errors = $this->unsupportedNumbers($interaction, $context, $map);
        $everything = fn ($n) => [$n['title'], $n['body'], $n['prompt'], $n['explain']['body'] ?? '', implode(' ', array_column((array) ($n['variants'] ?? []), 'body'))];
        $text = mb_strtolower(implode(' ', array_merge(...array_map($everything, $interaction['nodes']))));
        $names = array_map(fn ($c) => mb_strtolower($c['name']), $bible['characters']);
        $hit = array_filter($names, fn ($n) => preg_match('/\b' . preg_quote($n, '/') . '\b/u', $text));
        if ($names !== [] && $hit === []) {
            $errors[] = 'None of the story\'s characters (' . implode(', ', array_column($bible['characters'], 'name')) . ') appears in this act; the same characters must carry the story through every act.';
        }
        foreach ($interaction['nodes'] as $n) {
            $all = implode(' ', $everything($n));
            if (preg_match('/\b(?:which of the following|true or false|choose the correct|select the correct)\b/i', $all)) {
                $errors[] = 'Node "' . $n['id'] . '" reads like a quiz question; a decision is something a character does in the story.';
            }
            foreach ($n['choices'] as $c) {
                if (preg_match('/^\(?[A-Da-d][).:]\s/', (string) $c['text'])) {
                    $errors[] = 'A choice in "' . $n['id'] . '" is lettered like a multiple-choice option; write it as what the character does.';
                }
            }
            // The wording of a choice must not give the answer away: no "absolute" or "guess" words on one side only.
            if ($n['kind'] === 'decision' && count($n['choices']) >= 2) {
                $tell = fn (string $t): bool => StorySkeleton::givesAway($t);
                $flagged = array_values(array_filter($n['choices'], fn ($c) => $tell((string) $c['text'])));
                if ($flagged !== [] && count($flagged) < count($n['choices'])) {
                    $errors[] = 'The choice "' . mb_substr((string) $flagged[0]['text'], 0, 50) . '" in "' . $n['id'] . '" gives itself away with a word like never, always, guess or blindly; write every choice as an approach a sensible student could take, in the same tone and similar length.';
                }
            }
        }
        if (preg_match('/[\x{1F300}-\x{1FAFF}\x{2600}-\x{27BF}]/u', json_encode($interaction, JSON_UNESCAPED_UNICODE) ?: '')) {
            $errors[] = 'The act contains emoji.';
        }

        return $errors;
    }

    /**
     * Numbers the story invents. The deck validator refuses any figure in slide wording that the chapter does not contain, but it runs on
     * the finished lesson; the same rule is applied here, per act, so the model is told while the act is still being written.
     *
     * @param array<string,mixed> $interaction
     * @return array<int,string>
     */
    private function unsupportedNumbers(array $interaction, array $context, array $map): array
    {
        $allowed = StoryFigures::allowed($context, $map);
        $errors = [];
        $check = function (string $where, string $text) use ($allowed, &$errors): void {
            $errors = array_merge($errors, StoryFigures::errors($where, $text, $allowed));
        };
        $check('The mission', (string) ($interaction['mission']['title'] ?? '') . ' ' . (string) ($interaction['mission']['body'] ?? ''));
        $check('The conclusion', (string) ($interaction['conclusion'] ?? ''));
        foreach ($interaction['nodes'] as $n) {
            $check('Node "' . $n['id'] . '"', $n['title'] . ' ' . $n['body'] . ' ' . $n['prompt'] . ' ' . ($n['explain']['title'] ?? '') . ' ' . ($n['explain']['body'] ?? '') . ' ' . ($n['picture_caption'] ?? ''));
            foreach ((array) ($n['variants'] ?? []) as $v) {
                $check('A variant of "' . $n['id'] . '"', (string) $v['body']);
            }
            foreach ($n['choices'] as $c) {
                $check('A choice in "' . $n['id'] . '"', $c['text'] . ' ' . $c['outcome'] . ' ' . $c['why']);
            }
        }

        return $errors;
    }

    /**
     * Pictures for the scenes: a photograph found through the existing image search (kept only when its licence is accepted and the
     * picture itself shows the subject), with a diagram drawn from the chapter's own words as a supporting picture or a stand-in.
     *
     * @param array<string,mixed> $interaction
     * @param array<string,mixed> $skeleton
     * @param array<int,string> $notes what was left out, and why
     * @return array<string,mixed>
     */
    private function pictures(array $interaction, array $skeleton, array $context, array $config, array &$notes): array
    {
        $plan = [];
        foreach ($skeleton['nodes'] as $n) {
            if (!empty($n['image_queries']) || isset($n['visual']) || isset($n['process_step'])) {
                $plan[$n['id']] = $n;
            }
        }
        if ($plan === []) {
            return $interaction;
        }
        if ($this->images === null) {
            $notes[] = count($plan) . ' planned picture(s) were not made: no picture store is available to this run.';

            return $interaction;
        }
        [, $cap] = $config['images'];
        $used = 0;
        $truth = $this->norm((string) ($context['ground_truth'] ?? ''));
        foreach ($interaction['nodes'] as $k => $node) {
            $sk = $plan[$node['id']] ?? null;
            if ($sk === null || $used >= $cap) {
                continue;
            }
            $images = $this->imagesForNode($node, $sk, $truth, $notes, $cap - $used);
            if ($images === []) {
                continue;
            }
            $interaction['nodes'][$k]['image'] = $images[0];
            if (count($images) > 1) {
                $interaction['nodes'][$k]['extra_images'] = array_slice($images, 1);
            }
            $used += count($images);
        }

        return $interaction;
    }

    /**
     * The pictures for one node: a photograph (found through the image search, licence-checked and looked at by the pixel judge) and/or a drawn
     * diagram (a planned visual, or the process sequence). The first picture carries the caption: the writer's, else what the picture check saw.
     * A query that already found nothing in this run is not searched again.
     *
     * @param array<string,mixed> $node the written node
     * @param array<string,mixed> $sk its picture plan (image_queries, visual, process_step)
     * @param array<int,string> $notes
     * @return array<int,array<string,mixed>>
     */
    private function imagesForNode(array $node, array $sk, string $truth, array &$notes, int $room): array
    {
        $body = preg_split('/\s+/u', trim((string) ($node['body'] ?? '')), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $show = trim((string) ($sk['show'] ?? ''));
        // What the picture must SHOW is what the strict check is given when the plan says it; otherwise the scene's own words.
        $purpose = $show !== '' ? 'A photograph that shows: ' . $show . ' (for the scene "' . $node['title'] . '")'
            : trim($node['title'] . '. ' . (string) ($sk['covers'] ?? implode(' ', array_slice($body, 0, 30))));
        $teaches = implode('; ', array_slice(array_map(fn ($id) => (string) ($this->conceptNames[$id] ?? ''), (array) $node['concept_ids']), 0, 3));

        $photo = null;
        $this->images->useProviders((array) ($sk['sources'] ?? []));
        $this->images->skipSources((array) ($sk['skip'] ?? []));
        foreach ((array) ($sk['image_queries'] ?? []) as $q) {
            $key = $this->norm((string) $q) . ($show !== '' ? '|' . $this->norm($show) : '');
            if (isset($this->queryMisses[$key])) {
                $this->pictureStats['cached_misses']++;
                $notes[] = 'Scene "' . $node['id'] . '": no photograph for "' . $q . '" (searched before in this run: ' . $this->queryMisses[$key] . ').';
                continue;
            }
            $found = $this->images->find((string) $q, $this->usedImageUrls, $purpose, $teaches);
            if (($found['missing'] ?? null) === ImagePlanner::TIME_UP) {
                $this->pictureStats['stopped_by_deadline']++;
                $notes[] = 'Scene "' . $node['id'] . '": the search for "' . $q . '" was stopped by the time limit.';
                break;   // not a verdict on the query: it is not remembered as a miss
            }
            if (isset($found['missing'])) {
                // A search that could not finish (a picture that could not be checked or downloaded) says nothing about the query: it is tried again next time.
                if (!preg_match('/could not be (checked|downloaded)/i', (string) $found['missing'])) {
                    $this->queryMisses[$key] = (string) $found['missing'];
                }
                $notes[] = 'Scene "' . $node['id'] . '": no photograph for "' . $q . '" (' . $found['missing'] . ').';
                continue;
            }
            $this->usedImageUrls[] = (string) $found['source_image_url'];
            $photo = self::photoImage($found, (string) $q);
            $this->cover?->learn($photo, array_values(array_filter(array_map(fn ($id) => (string) ($this->conceptNames[$id] ?? ''), (array) $node['concept_ids']))));
            break;
        }

        $diagram = null;
        if (isset($sk['keep_diagram'])) {
            // The scene already has a diagram that teaches: it stays, beside a photograph if one is found, untouched if not.
            if ($photo === null) {
                return [];
            }
            $diagram = $sk['keep_diagram'];
        } elseif (isset($sk['visual'])) {
            $diagram = $this->diagramImage($node['id'], $sk['visual'], $truth, $notes);
        } elseif (isset($sk['process_step'])) {
            $diagram = $this->diagramImage($node['id'], StoryProcess::spec($sk['process_step']), $truth, $notes);
            if ($diagram !== null) {
                $diagram['caption'] = StoryProcess::caption($sk['process_step']);
                $diagram['step'] = $sk['process_step'];
            }
        }

        $images = array_values(array_filter([$photo, $diagram]));
        if ($images === []) {
            return [];
        }
        $images = array_slice($images, 0, max(1, min(3, $room)));
        // What the student should notice: the writer's own caption for this scene's picture, else what the picture check saw.
        $notice = (string) ($node['picture_caption'] ?? $sk['picture_caption'] ?? '');
        if ($notice === '' && !empty($images[0]['notice'])) {
            $notice = (string) $images[0]['notice'];
        }
        // When a photograph was asked for, the plan's caption belongs to it, and a process diagram that stands in keeps its own (which step it shows).
        if ($notice !== '' && !($images[0]['type'] === 'diagram' && isset($images[0]['step']) && !empty($sk['image_queries']))) {
            $images[0]['caption'] = $notice;
        }
        unset($images[0]['notice']);
        foreach ($images as $img) {
            if (!isset($sk['keep_diagram']) || $img !== $sk['keep_diagram']) {
                $this->pictureStats[($img['type'] ?? '') === 'photo' ? 'photos' : 'diagrams']++;
            }
        }

        return $images;
    }

    /**
     * Give every scene and ending of an act that has no picture one, down the ladder (StoryPictureCover). A no-op unless the run asked for picture
     * coverage. Needs the picture planner (it stores the pictures); without one the scenes stay bare and the coverage rule says so.
     *
     * @param array<string,mixed> $interaction
     * @param array<string,mixed> $act StoryActs entry (key, title)
     * @param array<int,string> $notes
     * @return array<string,mixed>
     */
    public function coverInteraction(array $interaction, array $context, array $act, array $config, array &$notes, ?float $deadline = null): array
    {
        if (empty($config['image_coverage'])) {
            return $interaction;
        }
        if ($this->cover === null && $this->images !== null) {
            $this->cover = new StoryPictureCover($this->images, new StoryImageLibrary(null));
        }
        if ($this->cover === null) {
            $notes[] = 'Picture coverage was asked for but no picture store is available to this run.';

            return $interaction;
        }
        $chapter = (array) ($context['chapter'] ?? []);
        $ctx = [
            'key' => (string) ($act['key'] ?? ''),
            'concepts' => $this->conceptNames,
            // Most story nodes carry no concept of their own; the act's concepts say what the whole scene sequence is about.
            'act_concepts' => array_values(array_filter(array_map(fn ($id) => (string) ($this->conceptNames[$id] ?? ''), (array) ($act['required_ids'] ?? [])))),
            'chapter' => (string) ($chapter['chapter_name'] ?? $chapter['name'] ?? ''),
            'subject' => (string) ($chapter['subject_name'] ?? ''),
            'topic' => (string) (($act['kind'] ?? '') === 'topic' ? ($act['title'] ?? '') : ($chapter['chapter_name'] ?? $chapter['name'] ?? '')),
        ];

        return $this->cover->cover($interaction, $ctx, $this->usedImageUrls, $notes, $deadline);
    }

    /** Counts of what the picture pass did (scenes searched, photographs and diagrams kept, scenes left without one). */
    public array $pictureStats = ['scenes_searched' => 0, 'photos' => 0, 'diagrams' => 0, 'no_picture' => 0, 'pending' => 0, 'cached_misses' => 0, 'stopped_by_deadline' => 0];

    /** @var array<string,string> normalised query => why it found nothing, so the same search is not made twice */
    public array $queryMisses = [];

    /** @return array{available:bool,reason:string}|null */
    public function verificationStatus(): ?array
    {
        return $this->images?->verificationStatus();
    }

    /** What was decided about every candidate, in order. @return array<int,array<string,mixed>> */
    public function imageEvents(): array
    {
        return $this->images?->events ?? [];
    }

    /** What the image search did so far (searches, candidates, and why each rejected one was refused). @return array<string,mixed> */
    public function imageStats(): array
    {
        return ($this->images?->stats ?? []) + ['by_source' => $this->images?->bySource ?? []];
    }

    /** The chapter's concept names, for the picture steps that run before any act is written (an act kept from an earlier run). @param array<string,mixed> $map */
    public function useMap(array $map): void
    {
        $this->conceptNames = array_map(fn ($c) => (string) $c['name'], (array) ($map['concepts'] ?? []));
    }

    /** The hard limit of a picture pass: no search, download or picture check starts after it. */
    public function setDeadline(?float $unixTime): void
    {
        $this->images?->withDeadline($unixTime);
    }

    /**
     * What to show in each scene that has no picture yet, planned in ONE model call for the whole lesson (so the text of no act is rewritten):
     * for each scene, one or two concrete things that can be photographed (instruments, experiments, real-world examples, natural phenomena
     * that the chapter concept is about), the process step when the scene genuinely is one, a "what to notice" caption, and (rarely) a
     * concept diagram built from the chapter's own words. Entries that break the rules (a query with digits, a caption with an invented
     * figure, an unknown step) lose that part; nothing is repaired or invented.
     *
     * @param array<int,array{ref:string,title:string,body:string,explain:string,concepts:array<int,string>}> $scenes
     * @param array<string,mixed> $context
     * @return array<string,array<string,mixed>> ref => {image_queries?, process_step?, picture_caption?, visual?}
     */
    public function planPictures(array $scenes, array $context, array $map, ?string $method): array
    {
        if ($scenes === []) {
            return [];
        }
        $list = array_map(fn ($s) => ['ref' => $s['ref'], 'title' => $s['title'], 'scene' => $s['body'], 'explains' => $s['explain'], 'concepts' => $s['concepts']], $scenes);
        $input = json_encode($list, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $prompt = <<<PROMPT
You are choosing pictures for the scenes of a school science story (students aged 14 to 15). The story text is finished and must not change: you only say what picture, if any, each scene should have.

For EACH scene below (identified by "ref") give:
- "image_queries": one or two search phrases of 1 to 4 words for a real photograph on Wikimedia Commons that would genuinely help a student understand what the scene is teaching: a clear photograph of an instrument, an experiment, a natural phenomenon or a real-world example that the scene's CONCEPT is about (for example "vernier caliper", "stopwatch", "thermometer", "magnifying glass", "cumulus cloud", "weather station", "pendulum", "periodic table", "microscope"). Name the thing itself. No people, no verbs, no digits, and NOT a generic prop of the story setting ("school gate", "notebook and pen", "laboratory", "classroom", "bus stop" are useless). Give queries for EVERY scene that has a concrete thing to show (an instrument, a material, an experiment, a natural phenomenon, a real-world object or event that the scene is about): expect about half of the scenes to have one. Use [] only for a scene that is purely conversation, reflection or an abstract idea with nothing concrete to photograph.
- "process_step": "observation", "investigation", "evidence" or "conclusion" ONLY when the scene truly is that step of how science finds something out (a character observes, investigates, collects evidence, or reaches a conclusion); otherwise null. It is rare: most scenes are null.
- "picture_caption": one plain sentence of at most 25 words telling the student what to NOTICE in the picture, using only what the scene says. No numbers. Empty when there is no picture.
- "visual": null, unless a diagram of the chapter's own ideas would teach better than a photograph: then {"layout":"flow|compare|hub", "title":"...", "nodes":[...] or "center":"..." or "left":{"heading","items"},"right":{"heading","items"}}, every label a word or phrase from the scene or its concept. Use it sparingly.

A picture is only kept if a strict check of the picture itself says it helps this scene, so name the thing that the scene's concept is really about.

Reply with this JSON and nothing else:
{"plan": {"<ref>": {"image_queries": ["..."], "process_step": null, "picture_caption": "...", "visual": null}}}

SCENES
{$input}
PROMPT;
        $reply = $this->replies[] = $this->completer->complete(StoryPlanner::SYSTEM, $prompt, 16000);
        try {
            $raw = (array) ($this->extractJson($reply)['plan'] ?? []);
        } catch (\RuntimeException) {
            return [];
        }
        $allowed = StoryFigures::allowed($context, $map);
        $out = [];
        foreach ($scenes as $s) {
            $e = (array) ($raw[$s['ref']] ?? []);
            $entry = [];
            $queries = [];
            foreach (array_slice((array) ($e['image_queries'] ?? []), 0, 2) as $q) {
                $q = $this->s($q);
                $wc = count(preg_split('/\s+/u', $q, -1, PREG_SPLIT_NO_EMPTY) ?: []);
                if ($q !== '' && $wc >= 1 && $wc <= 4 && !preg_match('/\d/', $q)) {
                    $queries[] = $q;
                }
            }
            if ($queries !== []) {
                $entry['image_queries'] = $queries;
            }
            $step = strtolower($this->s($e['process_step'] ?? ''));
            if ($step !== '' && StoryProcess::valid($step)) {
                $entry['process_step'] = $step;
            }
            $caption = $this->s($e['picture_caption'] ?? '');
            if ($caption !== '' && str_word_count($caption) <= 30 && StoryFigures::errors('c', $caption, $allowed) === []) {
                $entry['picture_caption'] = $caption;
            }
            if (!isset($entry['process_step']) && is_array($e['visual'] ?? null) && DiagramRenderer::problems($e['visual']) === []) {
                $entry['visual'] = $e['visual'];
            }
            if ($entry !== []) {
                $out[$s['ref']] = $entry;
            }
        }

        return $out;
    }

    /**
     * A picture plan written by a person who read the scenes (what to photograph, what the photograph must show, what to notice, a corrected
     * process step). It faces the same rules as a planned one: queries of one to five words with no digits (at most four), a caption of at
     * most 30 words with no figure the chapter lacks, a known process step. An entry that breaks a rule loses that part.
     *
     * @param array<string,mixed> $raw ref => {image_queries, show, picture_caption, process_step, priority}
     * @return array<string,array<string,mixed>>
     */
    public function curatedPlan(array $raw, array $context, array $map): array
    {
        $allowed = StoryFigures::allowed($context, $map);
        $out = [];
        foreach ($raw as $ref => $e) {
            $e = (array) $e;
            $entry = ['curated' => true];
            $queries = [];
            foreach (array_slice((array) ($e['image_queries'] ?? []), 0, 4) as $q) {
                $q = $this->s($q);
                $wc = count(preg_split('/\s+/u', $q, -1, PREG_SPLIT_NO_EMPTY) ?: []);
                if ($q !== '' && $wc <= 5 && !preg_match('/\d/', $q)) {
                    $queries[] = $q;
                }
            }
            if ($queries !== []) {
                $entry['image_queries'] = $queries;
            }
            $show = $this->s($e['show'] ?? '');
            if ($show !== '' && mb_strlen($show) <= 220) {
                $entry['show'] = $show;
            }
            $caption = $this->s($e['picture_caption'] ?? '');
            if ($caption !== '' && str_word_count($caption) <= 30 && StoryFigures::errors('c', $caption, $allowed) === []) {
                $entry['picture_caption'] = $caption;
            }
            $step = strtolower($this->s($e['process_step'] ?? ''));
            if ($step !== '' && StoryProcess::valid($step)) {
                $entry['process_step'] = $step;
            }
            if (isset($e['priority']) && is_numeric($e['priority'])) {
                $entry['priority'] = (int) $e['priority'];
            }
            $sources = array_values(array_intersect(array_map('strtolower', array_map('strval', (array) ($e['sources'] ?? []))), ['nasa', 'pexels']));
            if ($sources !== []) {
                $entry['sources'] = $sources;
            }
            $skip = array_values(array_intersect(array_map('strtolower', array_map('strval', (array) ($e['skip_sources'] ?? []))), ['wikimedia', 'flickr', 'nasa', 'pexels']));
            if ($skip !== []) {
                $entry['skip'] = $skip;
            }
            $out[(string) $ref] = $entry;
        }

        return $out;
    }

    /**
     * Give a finished act's scenes pictures without touching its text: for each scene in $order (already ranked by importance), look for the
     * picture its plan names, and stop when the deadline passes. Scenes not reached are counted as pending. Returns the act with whatever
     * pictures were kept.
     *
     * @param array<string,mixed> $interaction the finished act
     * @param array<string,array<string,mixed>> $plans node id => picture plan
     * @param array<int,string> $order node ids, most important first
     * @param array<int,string> $notes
     * @return array<string,mixed>
     */
    /** @var array<string,float> seconds spent on each scene searched (key: scene id) */
    public array $sceneSeconds = [];

    public function repicture(array $interaction, array $plans, array $order, array $context, array $map, array $config, float $deadline, array &$notes): array
    {
        if ($this->images === null) {
            return $interaction;
        }
        $this->conceptNames = array_map(fn ($c) => (string) $c['name'], (array) ($map['concepts'] ?? []));
        $truth = $this->norm((string) ($context['ground_truth'] ?? ''));
        [, $cap] = $config['images'];
        $have = array_sum(array_map(fn ($n) => (empty($n['image']) ? 0 : 1) + count((array) ($n['extra_images'] ?? [])), $interaction['nodes']));
        $index = array_flip(array_column($interaction['nodes'], 'id'));
        foreach ($order as $id) {
            if (!isset($index[$id], $plans[$id])) {
                continue;
            }
            $k = $index[$id];
            $node = $interaction['nodes'][$k];
            $existing = $node['image'] ?? null;
            $entry = $plans[$id];
            $isDiagram = is_array($existing) && ($existing['type'] ?? '') === 'diagram';
            if ($node['kind'] === 'decision' || ($existing && !$isDiagram)) {
                continue;   // a photograph is already there
            }
            if ($existing && empty($entry['image_queries']) && !isset($entry['process_step'])) {
                continue;   // a diagram is there and the plan has nothing to add or correct
            }
            if (microtime(true) >= $deadline || (!$existing && $have >= $cap)) {
                $this->pictureStats['pending']++;
                continue;
            }
            $this->pictureStats['scenes_searched']++;
            // A diagram that stays (no new step in the plan) is kept beside a photograph; a new step replaces the old diagram.
            if ($existing && !isset($entry['process_step'])) {
                $entry['keep_diagram'] = $existing;
            }
            $t0 = microtime(true);
            if ($this->images !== null) {
                $this->images->scene = (string) ($interaction['key'] ?? '') . '/' . $id;
            }
            $images = $this->imagesForNode($node, $entry, $truth, $notes, $cap - $have + ($existing ? 1 : 0));
            $this->sceneSeconds[$id] = round(microtime(true) - $t0, 1);
            if ($images === []) {
                $this->pictureStats['no_picture']++;
                continue;
            }
            $interaction['nodes'][$k]['image'] = $images[0];
            unset($interaction['nodes'][$k]['extra_images']);
            if (count($images) > 1) {
                $interaction['nodes'][$k]['extra_images'] = array_slice($images, 1);
            }
            $have += count($images) - ($existing ? 1 : 0);
        }

        return $interaction;
    }

    /** @param array<string,mixed> $found ImagePlanner::find() result @return array<string,mixed> */
    public static function photoImage(array $found, string $query): array
    {
        return [
            'type' => 'photo', 'role' => 'scene', 'match_level' => $found['match_level'] ?? 'exact', 'fetched_from' => $found['fetched_from'] ?? null, 'url' => $found['url'], 'alt' => $found['alt'], 'caption' => $found['caption'] ?? null,
            'width' => $found['width'], 'height' => $found['height'], 'title' => $found['title'] ?? null, 'query' => $query,
            'licence' => $found['licence'] ?? null, 'source_url' => $found['source_url'] ?? null, 'creator' => $found['creator'] ?? null,
            'attribution' => $found['attribution'] ?? '', 'attribution_required' => (bool) ($found['attribution_required'] ?? false),
            'review' => $found['review'] ?? null, 'source_image_url' => $found['source_image_url'] ?? null,
            ...(isset($found['focus']) ? ['focus' => $found['focus']] : []), ...(isset($found['notice']) ? ['notice' => $found['notice']] : []), ...(isset($found['focus_label']) ? ['focus_label' => $found['focus_label']] : []), 'provider' => $found['provider'] ?? null,
            // Parts of the picture a learner can open, found on the picture by the picture check. Only an exact or a related picture keeps them: a
            // part's words are about what the picture shows for THIS scene's idea, which a topic or reused picture does not.
            ...(in_array($found['match_level'] ?? 'exact', ['exact', 'related'], true) && ($parts = StoryHotspots::spots((array) ($found['parts'] ?? []))) !== [] ? ['hotspots' => $parts] : []),
        ];
    }

    /** A diagram drawn from the planned spec, only when the chapter text supports its labels. @return array<string,mixed>|null */
    private function diagramImage(string $id, array $spec, string $truth, array &$notes): ?array
    {
        $labels = array_values(array_filter(array_map('strval', DiagramRenderer::texts($spec))));
        $found = 0;
        foreach ($labels as $label) {
            $words = array_filter(explode(' ', $this->norm($label)), fn ($w) => strlen($w) >= 3 && !in_array($w, StoryProcess::words(), true));
            $ok = array_filter($words, fn ($w) => !str_contains($truth, $w) && !(strlen($w) > 3 && str_ends_with($w, 's') && str_contains($truth, substr($w, 0, -1))));
            if ($words === [] || $ok === []) {
                $found++;
            }
        }
        if ($labels === [] || $found / count($labels) < 0.8) {
            $notes[] = 'The diagram for "' . $id . '" was not drawn: only ' . $found . ' of ' . count($labels) . ' labels are in the chapter text.';

            return null;
        }
        try {
            $drawn = $this->images->diagram($spec);
        } catch (\Throwable $e) {
            $notes[] = 'The diagram for "' . $id . '" could not be drawn: ' . $e->getMessage();

            return null;
        }

        return [
            'type' => 'diagram', 'role' => 'diagram', 'url' => $drawn['url'], 'alt' => $drawn['alt'], 'caption' => $drawn['caption'], 'width' => $drawn['width'], 'height' => $drawn['height'],
            'texts' => $drawn['texts'], 'licence' => null, 'source_url' => null, 'creator' => null, 'attribution' => '', 'attribution_required' => false,
        ];
    }

    private function norm(string $s): string
    {
        return trim((string) preg_replace('/[^a-z0-9]+/', ' ', mb_strtolower($s)));
    }

    private function s(mixed $v): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', is_scalar($v) ? (string) $v : ''));
    }

    private function prompt(array $context, array $map, array $act, array $skeleton, array $bible, array $config, ?string $method, ?array $previous): string
    {
        [$sLo, $sHi] = $config['scene_words'];
        [$xLo, $xHi] = $config['explain_words'];
        [$cLo, $cHi] = $config['choice_words'];
        [$eLo, $eHi] = $config['event_words'];
        [$vLo, $vHi] = $config['variant_words'];
        $preds = self::predecessors($skeleton);
        $nodes = array_map(function ($n) use ($preds) {
            $o = ['id' => $n['id'], 'kind' => $n['kind'], 'teaches_concept_ids' => $n['concept_ids']];
            if ($n['kind'] === 'decision') {
                $o['asks'] = $n['asks'];
                $o['choices'] = array_map(fn ($c) => ['intent' => $c['intent'], 'quality' => $c['quality'], 'leads_to' => $c['next']], $n['choices']);
            } else {
                $o['covers'] = $n['covers'];
                if (!empty($n['image_queries']) || isset($n['visual']) || isset($n['process_step'])) {
                    $o['will_have_a_picture'] = true;
                }
                if ($n['kind'] === 'scene') {
                    $o['leads_to'] = $n['next'];
                } else {
                    $o['outcome'] = $n['outcome'];
                }
                if (count($preds[$n['id']] ?? []) >= 2) {
                    $o['branches_meet_here_from'] = $preds[$n['id']];   // a merge: write one "variants" lead-in for each of these
                }
            }

            return $o;
        }, $skeleton['nodes']);
        $concepts = [];
        $ids = $act['kind'] === StoryActs::MISSION ? $act['required_ids'] : $act['concept_ids'];
        foreach ($ids as $id) {
            $c = $map['concepts'][$id] ?? null;
            if ($c) {
                $concepts[] = ['id' => (int) $id, 'name' => $c['name'], 'definition' => $c['definition'], 'misconceptions' => array_column((array) ($c['misconceptions'] ?? []), 'wrong_idea')];
            }
        }
        $input = json_encode([
            'story' => ['title' => $bible['title'], 'setting' => $bible['setting'], 'premise' => $bible['premise'], 'investigation' => $bible['investigation'] ?? '', 'characters' => $bible['characters'], 'this_act_hook' => $bible['act_hooks'][$act['key']] ?? ''],
            'previous' => $previous,
            'act' => ['title' => $skeleton['title'], 'premise' => $skeleton['premise']],
            'concepts' => $concepts,
            'skeleton' => $nodes,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $methodLine = $method !== null && ($m = StoryTemplate::methodRule($method)) !== null ? "\n- TEACHING METHOD: $m" : '';

        return <<<PROMPT
Write the words of ONE act of an interactive story lesson. The structure is fixed (the skeleton below): you may not add, remove, rename or rewire a node. You write only the text of each node.

The learner is a student ("you") in a small team investigating the story's central question. It is one continuous investigation: this act carries on from "previous" (what the team found and where it left off), and what happens here follows from the choices made on the way. Characters have goals, notice things, disagree, make mistakes and change their minds. The scenes are vivid and concrete, like a good science documentary told as a story. Not a list of facts.

Rules
- A SCENE ("body": AT LEAST 60 words and at most {$sHi}; aim for 80 to 130. A shorter body is rejected) tells what is happening: the situation, what a character notices, tries or says, why it matters, and what is still uncertain. Past or present tense, the same throughout. A scene that teaches a concept (it lists teaches_concept_ids) also has "explain": {"title": a short heading such as "Why a model leaves things out", "body": {$xLo} to {$xHi} words}, a plain-language explanation of that concept for a 14 to 15 year old, taken from the concept definitions and tied to what has just happened in the scene. Say WHY it matters, not only what it is.
- A CONSEQUENCE or REMEDIAL scene shows what happened because of the learner's choice: what turned up, what the choice missed or got right, a surprise, a reason to rethink. It names the character's reaction. It never just says "wrong".
- A scene where branches meet (its skeleton entry has "branches_meet_here_from") also has a "variants" object in YOUR reply, with one entry for EACH id listed in branches_meet_here_from, each {$vLo} to {$vHi} words: a short lead-in that says what the team carries into this scene from THAT path (the evidence found, or the mistake made and what it showed). The scene's "body" then continues and must make sense after any of the variants.
- A DECISION has a "prompt": what the learner must decide, as something a character does in the story, at most 30 words. Optional "body": one or two sentences of set-up. Write each choice in the skeleton's order: "text" ({$cLo} to {$cHi} words, what the character does, no letters or numbers in front), "outcome" ({$eLo} to {$eHi} words: the STORY EVENT that follows) and "why" ({$eLo} to {$eHi} words: what the science says about this path and how it changes what the team does next). All choices read as approaches a sensible student could take: similar length and tone, no giveaway words (never, always, just, guess, blindly). Every choice has a different outcome and a different why.
- An ENDING has a "title" and a "body" ({$sLo} to {$sHi} words): what happened on this path, what the team now knows about the investigation, and what the learner now understands. Different endings differ in what they say; they are earned by the path.
- PICTURE CAPTION: for each node whose skeleton entry says it will have a picture (it has image_queries, a visual or a process_step), also write "picture_caption": one short sentence (at most 25 words) telling the student what to NOTICE in that picture, in plain words, using only what the scene and the chapter say. It is not a title.
- Every fact, term, formula and number comes from the CHAPTER TEXT or the concept definitions. Invent no science, no measurements and no experimental results. Do not name real people or places the chapter does not mention.
- No emoji. Titles are short, sentence case and say what the scene is about (never "Scene 1" or "Part 2").
- Facts come ONLY from the chapter text. Write no number, count, measurement, year or quantity that the chapter text does not contain: do not compute or estimate figures in the story. Say it in words ("many", "a few", "about the same") instead.
- Also write "mission": {"title", "body"}: the mission of this act in one or two sentences ("Your mission is to ..."), and "conclusion": one sentence naming what the team now knows after this act.{$methodLine}

Reply with this JSON and nothing else:
{
  "mission": {"title": "...", "body": "..."},
  "conclusion": "...",
  "nodes": {
    "s1": {"title": "...", "body": "...", "explain": {"title": "...", "body": "..."}, "picture_caption": "what to notice in the picture"},
    "d1": {"title": "...", "body": "optional set-up", "prompt": "...", "choices": [{"text": "...", "outcome": "...", "why": "..."}]},
    "s3": {"title": "...", "body": "...", "explain": {"title": "...", "body": "..."}, "variants": {"s2a": "...", "s2b": "..."}},
    "e1": {"title": "...", "body": "..."}
  }
}

ACT DATA
{$input}

CHAPTER TEXT (the only source of facts)
{$context['ground_truth']}
PROMPT;
    }
}
