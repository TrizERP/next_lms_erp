<?php

namespace App\Services\StudyDeck;

use App\Services\PAL\Integration\ConceptImageSearchService;
use App\Services\QuestionGeneration\DragDrop\Contracts\DiagramImageStore;
use App\Services\StudyDeck\Contracts\Completer;
use App\Services\StudyDeck\Story\StoryLessonGenerator;
use App\Services\StudyDeck\Story\StoryTemplate;

/**
 * Orchestrates the chapter -> classroom study deck pipeline:
 *
 *   load chapter, topics, concepts, intelligence, baseline, questions   (read-only)
 *   -> pick self-contained questions
 *   -> learning map                    (deterministic)
 *   -> stage 1: slide plan             (Claude)
 *   -> stage 2: slide content          (Claude, chunked)
 *   -> images: Openverse, licensed, downloaded, or drawn diagrams
 *   -> stage 3: interactions (hotspots, scenarios, reveals) where a concept earns one   (Claude)
 *   -> render to the .cover / .slide design-system markup
 *   -> validate
 *
 * It stores NOTHING. The caller decides what to do with a validated result
 * (the artisan command writes a review bundle; ContentGenerationService
 * persists to content_master). Intermediate artefacts are returned so a run can
 * be inspected.
 */
class StudyDeckService
{
    public function __construct(
        private readonly ConceptContextBuilder $contextBuilder,
        private readonly QuestionSelector $questionSelector,
        private readonly LearningPlanBuilder $learningPlan,
        private readonly SlidePlanner $planner,
        private readonly SlideContentGenerator $contentGenerator,
        private readonly ImagePlanner $imagePlanner,
        private readonly SlideHtmlRenderer $renderer,
        private readonly DeckValidator $validator,
        private readonly ActivityPlanner $activityPlanner = new ActivityPlanner(),
        private readonly ?InteractionPlanner $interactionPlanner = null,
        private readonly ?StoryLessonGenerator $story = null,
    ) {
    }

    public static function make(Completer $completer, DiagramImageStore $images, ?ConceptImageSearchService $search = null): self
    {
        $patterns = new H5pPatternSelector();
        // Generation waits for the picture search (the anonymous API takes 9-17 s); the Learn page keeps its short timeout.
        $search = ($search ?? app(ConceptImageSearchService::class))->forBatch();
        $imagePlanner = new ImagePlanner($search, $images, null, 2.0, new ImageRelevanceJudge($completer));

        return new self(
            new ConceptContextBuilder(),
            new QuestionSelector(),
            new LearningPlanBuilder($patterns),
            new SlidePlanner($completer, $patterns),
            new SlideContentGenerator($completer),
            $imagePlanner,
            new SlideHtmlRenderer(),
            new DeckValidator(images: $imagePlanner),
            new ActivityPlanner($patterns),
            new InteractionPlanner($completer),
            // A story's scene pictures are also looked at (by the estate's Gemini client) before they are kept; ordinary decks are not.
            new StoryLessonGenerator($completer, (new ImagePlanner($search, $images, null, 1.0, new ImageRelevanceJudge($completer)))
                ->withPixelJudge(new Story\StoryPixelJudge(app(\App\Domain\AI\Support\GeminiClient::class)))
                ->withProviders([
                    'nasa' => fn (string $q, int $n): array => app(\App\Services\PAL\Integration\NasaImageSearch::class)->search($q, $n),
                    'pexels' => fn (string $q, int $n): array => app(\App\Services\PAL\Integration\PexelsImageSearch::class)->search($q, $n),
                ])),
        );
    }

    /**
     * @param callable(string $stage, string $message):void|null $progress
     * @param array<string,mixed> $options min_slides, max_slides, per_concept, allowed_forms, limits (max_practice, questions_per_slide, max_visuals: see SlidePlanner::limits)
     * @return array<string,mixed>
     */
    public function generate(int $chapterId, int $tenantId, array $options = [], ?callable $progress = null): array
    {
        $say = $progress ?? fn () => null;

        $say('load', 'Loading chapter data (read-only)');
        $raw = $this->contextBuilder->load($chapterId, $tenantId);
        if (trim($raw['ground_truth']) === '') {
            throw new \RuntimeException('Chapter ' . $chapterId . ' has no ground-truth text (document_extractions.md_content); refusing to generate.');
        }

        return $this->fromRaw($raw, $options, $say);
    }

    /**
     * replay() for a chapter: read its rows (read-only), then rebuild the deck from a bundle's saved model output.
     *
     * @param array<string,mixed> $saved see replay()
     * @param array<string,mixed> $options as generate()
     * @return array<string,mixed> as fromRaw()
     */
    public function replayChapter(int $chapterId, int $tenantId, array $saved, array $options = [], ?callable $progress = null): array
    {
        $say = $progress ?? fn () => null;

        $say('load', 'Loading chapter data (read-only)');
        $raw = $this->contextBuilder->load($chapterId, $tenantId);

        return $this->replay($raw, $saved, $options, $say);
    }

    /**
     * The same pipeline from already-loaded rows - used by tests and replays.
     *
     * @param array<string,mixed> $raw
     * @return array<string,mixed>
     */
    public function fromRaw(array $raw, array $options = [], ?callable $progress = null): array
    {
        $say = $progress ?? fn () => null;

        // The story template (Study Deck V3): the same pipeline stages, with the story planner and writer in place of the slide planner.
        if (($options['template'] ?? null) === StoryTemplate::KEY) {
            return $this->fromRawStory($raw, $options, $say);
        }

        [$context, $selection, $eligible, $map] = $this->prepare($raw, $options, $say);

        $say('plan', 'Stage 1: planning slides with the model');
        try {
            $plan = $this->planner->plan($context, $map, $eligible);
        } catch (\RuntimeException $e) {
            $dir = (string) ($options['rejected_replies_dir'] ?? storage_path('app/study-deck/chapter-' . (int) $raw['chapter']['id']));
            @mkdir($dir, 0775, true);
            file_put_contents($dir . '/rejected-plan-replies.json', json_encode($this->planner->replies, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            throw new \RuntimeException($e->getMessage() . "\n(raw replies saved to $dir/rejected-plan-replies.json)", 0, $e);
        }
        // The method the deck was planned for travels with the plan, so the later stages and the saved bundle know it.
        if (!empty($map['teaching_method'])) {
            $plan['teaching_method'] = $map['teaching_method'];
        }
        $say('plan', count($plan['slides']) . ' slides planned');

        $say('content', 'Stage 2: writing slide content with the model');
        $content = $this->contentGenerator->generate($context, $map, $plan, fn ($done, $total) => $say('content', "$done/$total slides written"));

        $say('images', 'Finding openly licensed images');
        $images = $this->imagePlanner->plan($plan['slides'], $content, function ($n, $r) use ($say) {
            $say('images', isset($r['missing']) ? "slide $n: none ({$r['missing']})" : "slide $n: " . (($r['type'] ?? 'photo') === 'diagram' ? 'drawn diagram' : $r['licence']));
        });

        $say('interact', 'Stage 3: deciding which slides earn an interaction');
        $interactions = $this->interactionPlanner?->plan($context, $map, $plan, $content, $images, fn ($found, $total) => $say('interact', "$found interaction(s) so far")) ?? [];

        return $this->finish($context, $selection, $eligible, $map, $plan, $content, $images, $interactions, $say);
    }

    /**
     * A story lesson: a story bible, then for every act a CHECKED skeleton and its written, illustrated story (see Story\StoryLessonGenerator),
     * assembled into the plan, slide content and interactions the existing renderer takes, and judged by the existing validator in its story
     * mode. Nothing is stored: the caller (the artisan command, then the store workflow) decides that after review.
     *
     * @param array<string,mixed> $raw ConceptContextBuilder::load()
     * @param array<string,mixed> $options as generate(), plus story_config (StoryTemplate::resolve()['config'], default: the school's)
     * @return array<string,mixed> as fromRaw(), plus `story` (the bible, the notes and the checked skeletons)
     */
    private function fromRawStory(array $raw, array $options, callable $say): array
    {
        if ($this->story === null) {
            throw new \RuntimeException('This service was built without a story generator.');
        }
        // A story is not practice: no bank question is offered, and the teaching method shapes the story rather than a slide sequence.
        $options['per_concept'] = 0;
        [$context, $selection, $eligible, $map] = $this->prepare($raw, $options, $say);
        $method = $map['teaching_method'] ?? null;
        unset($map['teaching_method']);
        $config = $options['story_config'] ?? StoryTemplate::resolve((int) ($raw['chapter']['sub_institute_id'] ?? $options['tenant'] ?? 1))['config'];

        // Picture coverage (a run that asks for it): every scene and ending ends with a picture of its own, by the ladder in StoryPictureCover.
        $cover = null;
        if (!empty($options['image_coverage'])) {
            $config['image_coverage'] = true;
            $config['images'] = [0, max(60, (int) ($config['images'][1] ?? 10))];   // a main picture per scene and ending, and room for supporting ones
            $cover = $this->story->enableCoverage(
                new Story\StoryImageLibrary($options['image_library'] ?? null),
                !empty($options['ai_scene_art']) ? new Story\SceneIllustrator(null, (int) ($options['ai_scene_max'] ?? 60), 'gemini image model', 90, $options['tenant'] ?? null) : null,
                !empty($options['allow_placeholder'])
            );
            // The real pictures the last rung draws from are found NOW, so a run with no way to find one stops here and not after hours of writing.
            if ($cover !== null) {
                $chapterRow = (array) ($context['chapter'] ?? []);
                $generic = $cover->prepareGenericPool(['chapter' => (string) ($chapterRow['chapter_name'] ?? $chapterRow['name'] ?? ''), 'subject' => (string) ($chapterRow['subject_name'] ?? '')]);
                $say('pictures', 'Generic educational pictures ready: ' . $generic);
                if ($generic === 0 && empty($options['allow_placeholder'])) {
                    throw new \RuntimeException('Picture coverage needs a real picture for every scene, and no generic educational picture could be found (is image search reachable and the picture check available?). Nothing was generated. Pass --allow-placeholder to accept labelled placeholders instead.');
                }
            }
            $say('pictures', 'Picture coverage on: exact, related, topic, reused' . ($cover?->illustrator() !== null ? ', AI-generated' : '') . ' and a labelled placeholder, in that order');
        }
        $methodKey = is_string($method) ? $method : (isset($method['key']) ? (string) $method['key'] : null);
        if (isset($options['story_prebuilt'])) {
            // A finished story handed in (a bundle from an earlier run): its text is not written again.
            $story = StoryLessonGenerator::prebuilt($map, $config, $options['story_prebuilt']);
            $say('story', 'Using the finished story of an earlier run (' . count($story['acts']) . ' acts); no text is rewritten');
        } else {
            $story = $this->story->generate($context, $map, $config, $methodKey, $say, $options['story_checkpoint_dir'] ?? null);
        }
        $pictureReport = null;
        if ((float) ($options['picture_seconds'] ?? 0) > 0) {
            // The picture pass: the best relevant pictures for the scenes that have none, within a time budget, without touching any text.
            $pass = $this->story->repicture($context, $map, $config, $methodKey, $story, (float) $options['picture_seconds'], $say, ['plan' => $options['picture_plan'] ?? [], 'misses' => $options['picture_misses'] ?? [], 'curated' => $options['picture_curated'] ?? [], 'curated_only' => (bool) ($options['picture_curated_only'] ?? false)]);
            $story = $pass['story'];
            $story['notes'] = array_merge($story['notes'] ?? [], array_map(fn ($n) => 'Pictures: ' . $n, $pass['notes']));
            $pictureReport = ['report' => $pass['report'], 'plan' => $pass['plan'], 'misses' => $pass['misses']];
        }
        if ($cover !== null) {
            // Whichever way the acts were built (written now, kept from an earlier run, handed in), none leaves here with a bare scene.
            $this->story->writer->useMap($map);
            foreach ($story['acts'] as $ai => $a) {
                $coverNotes = [];
                $story['acts'][$ai]['interaction'] = $this->story->writer->coverInteraction($a['interaction'], $context, $a['act'], $config, $coverNotes);
                $story['notes'] = array_merge($story['notes'] ?? [], array_map(fn ($n) => 'Pictures: ' . $n, $coverNotes));
            }
            $cover->library()->save();
        }
        ['plan' => $plan, 'content' => $content, 'images' => $images, 'interactions' => $interactions] = StoryLessonGenerator::assemble($context, $map, $story);
        $say('plan', count($plan['slides']) . ' slides assembled (' . count($story['acts']) . ' story acts)');

        $map['story_mode'] = true;
        $map['story_config'] = $config;
        $map['target_slides'] = ['min' => 3, 'max' => count($plan['slides']) + 4];
        $result = $this->finish($context, $selection, [], $map, $plan, $content, $images, $interactions, $say);
        if ($method !== null) {
            $key = is_string($method) ? $method : (string) ($method['key'] ?? '');
            $result['report']['teaching_method'] = ['key' => $key, 'followed' => true, 'errors' => [], 'applied_to' => 'the story'];
        }
        if ($pictureReport !== null) {
            $result['pictures'] = $pictureReport;
        }
        if ($cover !== null) {
            $ai = $cover->illustrator();
            $result['picture_coverage'] = [
                'given_by_the_ladder' => $cover->given, 'searches_made' => $cover->searches, 'search_failures' => $cover->searchFailures, 'problems' => $cover->problems, 'log' => $cover->log,
                'ai_scene_art' => $ai === null ? ['enabled' => false] : ['enabled' => true, 'made' => $ai->made, 'failed' => $ai->failed, 'unavailable' => $ai->unavailable, 'last_error' => $ai->lastError],
                'library_pictures' => $cover->library()->count(),
                'summary' => Story\StoryPictureCover::summarise(array_map(fn ($a) => $a['interaction'], $story['acts'])),
            ];
        }
        $result['story'] = ['template' => StoryTemplate::KEY, 'version' => (int) $config['version'], 'bible' => $story['bible'], 'notes' => $story['notes'],
            'acts' => array_map(fn ($a) => ['key' => $a['act']['key'], 'title' => $a['act']['title'], 'kind' => $a['act']['kind'], 'required_concept_ids' => $a['act']['required_ids'], 'skeleton' => $a['skeleton']], $story['acts'])];

        return $result;
    }

    /**
     * Render and validate AGAIN what an earlier run wrote, with no model call and no image search.
     *
     * For when the code after the model changed (a validator rule, the renderer) and the saved plan, slide text,
     * pictures and interactions of a review bundle are still good: the deck is rebuilt from those files and judged by
     * the current rules, so a fix does not cost another hour of generation. The chapter rows are read again, so the
     * deck is held to what the school data says now.
     *
     * @param array<string,mixed> $raw ConceptContextBuilder::load()
     * @param array{plan:array<string,mixed>,content:array<int,array<string,mixed>>,images:array<int,array<string,mixed>>,interactions:array<int,array<string,mixed>>} $saved the bundle plan.json, slides.json, images.json and interactions.json
     * @param array<string,mixed> $options as generate(), plus recheck_images: hold the photographs to the picture rules again; image_overrides: {spec, base} the chosen pictures to swap in (see ImageOverrides)
     * @return array<string,mixed> as fromRaw()
     */
    public function replay(array $raw, array $saved, array $options = [], ?callable $progress = null): array
    {
        $say = $progress ?? fn () => null;

        [$context, $selection, $eligible, $map] = $this->prepare($raw, $options, $say);

        $images = $saved['images'];
        if (!empty($options['recheck_images'])) {
            $say('images', 'Holding the chosen photographs to the picture rules again');
            $images = $this->imagePlanner->recheck($saved['plan']['slides'], $images, fn ($n, $r) => $say('images', "slide $n: photograph replaced (" . ($r['photo_missing'] ?? $r['missing'] ?? '') . ')'));
        }

        if (!empty($options['redraw_diagrams'])) {
            $say('images', 'Drawing the diagrams again in the current look');
            $images = $this->imagePlanner->redraw($saved['plan']['slides'], $images);
        }

        // Pictures a person chose (see ImageOverrides): swapped in after the saved ones, with the hotspots moved to match.
        $plan = $saved['plan'];
        $interactions = $saved['interactions'];
        if (!empty($options['image_overrides']['spec'])) {
            $say('images', 'Swapping in the chosen pictures');
            [$plan, $images, $interactions] = (new ImageOverrides($this->imagePlanner))->apply(
                (array) $options['image_overrides']['spec'], $plan, $images, $interactions, $say, (string) ($options['image_overrides']['base'] ?? '')
            );
        }

        return $this->finish($context, $selection, $eligible, $map, $plan, $saved['content'], $images, $interactions, $say);
    }

    /**
     * The part of a run that needs no model: context, the questions the deck may use, the learning map.
     *
     * @return array{0:array<string,mixed>,1:array<string,mixed>,2:array<int,array<int,array<string,mixed>>>,3:array<string,mixed>}
     */
    private function prepare(array $raw, array $options, callable $say): array
    {
        $context = $this->contextBuilder->assemble($raw);
        // The chapter's own textbook pictures, a fallback AFTER the web search found nothing suitable (none configured: nothing changes).
        $textbook = TextbookImages::fromConfig();
        $this->imagePlanner->withTextbookImages($textbook, (int) ($raw['chapter']['id'] ?? 0));
        $this->story?->withTextbookImages($textbook, (int) ($raw['chapter']['id'] ?? 0));
        $say('context', sprintf('%d topics, %d concepts, %d without intelligence', count($context['topics']), count($context['concepts']), count($context['unmatched_concepts'])));

        $selection = $this->questionSelector->select(
            (array) $raw['questions'],
            (int) ($options['per_concept'] ?? 4),
            $options['allowed_forms'] ?? ['mcq', 'true_false', 'very_short_answer', 'short_answer']
        );
        $eligible = $selection['eligible'];
        $say('questions', sprintf('%d questions considered, %d excluded as not self-contained or unusable', count((array) $raw['questions']), count($selection['excluded'])));

        $map = $this->learningPlan->build($context, $eligible, (int) ($options['min_slides'] ?? 30), (int) ($options['max_slides'] ?? 35), (array) ($options['limits'] ?? []), $options['teaching_method'] ?? null);
        $say('map', 'Learning map built' . ($map['warnings'] ? ' (' . count($map['warnings']) . ' warning(s))' : ''));

        return [$context, $selection, $eligible, $map];
    }

    /**
     * The part of a run after the model: place the practice, render the design-system markup, validate.
     *
     * @return array<string,mixed> as fromRaw()
     */
    private function finish(array $context, array $selection, array $eligible, array $map, array $plan, array $content, array $images, array $interactions, callable $say): array
    {
        $activities = $this->activities($context, $map, $plan, $content, $eligible);
        $plan = $this->dropUnjustifiedPatterns($plan, $activities, $map, $eligible);
        $say('activities', array_sum(array_map('count', $activities)) . ' practice question(s) placed, '
            . count(array_filter($interactions, fn ($d) => $d['interaction'] !== null)) . ' interaction(s)');

        $rendered = $this->renderer->render($context, $map, $plan, $content, $images, $eligible, $activities, $interactions);
        $report = $this->validator->validate($context, $map, $eligible, $rendered, $selection['flagged'] ?? []);
        // A deck made for a teaching method must still follow it after every later stage.
        if (!empty($map['teaching_method'])) {
            $methodErrors = TeachingMethodProfile::planErrors($map['teaching_method'], $plan['slides']);
            // ...and what was actually WRITTEN must show the method's sequence (predictions and evidence; materials, steps and recording), not only the slide types.
            $texts = [];
            foreach ($plan['slides'] as $i => $s) {
                $n = $s['n'] ?? ($i + 1);
                $texts[] = (string) json_encode([$content[$n] ?? ($content[$i] ?? null), $interactions[$n] ?? ($interactions[$i] ?? null)], JSON_UNESCAPED_UNICODE);
            }
            $methodErrors = array_merge($methodErrors, TeachingMethodProfile::contentErrors($map['teaching_method'], $texts));
            $report['teaching_method'] = ['key' => $map['teaching_method'], 'followed' => $methodErrors === [], 'errors' => $methodErrors];
            if ($methodErrors) {
                $report['ok'] = false;
                $report['errors'] = array_merge($report['errors'], $methodErrors);
            }
        }
        $say('validate', $report['ok'] ? 'Validation passed' : count($report['errors']) . ' validation error(s)');

        return [
            'context' => $context,
            'selection' => $selection,
            'map' => $map,
            'plan' => $plan,
            'content' => $content,
            'images' => $images,
            'activities' => $activities,
            'interactions' => $interactions,
            'html' => $rendered['html'],
            'deck' => $rendered['deck'],
            'report' => $report,
        ];
    }

    /**
     * A pattern is recorded on a slide only if an activity on it really takes that shape.
     *
     * Branching is for a real decision, never to make a slide look more interactive; matching,
     * drag-and-drop, true/false and the rest need a question of that form, and the bank may have
     * none for the concept. When the planner chose a pattern nothing realises, it is DROPPED and
     * the reason kept (`pattern_dropped`), so the deck neither claims a pattern the learner never
     * meets nor fails over a label. `course_presentation` is the slide-by-slide progression the
     * player itself provides, so it needs no activity.
     *
     * @return array<string,mixed> the plan with unrealised patterns removed
     */
    private function dropUnjustifiedPatterns(array $plan, array $activities, array $map, array $eligible): array
    {
        $byId = [];
        foreach ($eligible as $list) {
            foreach ($list as $q) {
                $byId[$q['id']] = $q;
            }
        }

        foreach ($plan['slides'] as $i => $slide) {
            $type = $slide['h5p_pattern']['type'] ?? null;
            if ($type === null || $type === 'course_presentation') {
                continue;
            }
            if (array_filter($activities[$slide['n']] ?? [], fn ($a) => ($a['pattern'] ?? null) === $type)) {
                continue;
            }

            $reason = $type === 'branching'
                ? $this->activityPlanner->branchingEligible($slide, array_map(fn ($id) => $byId[$id], $slide['question_ids']), $map['concepts'])['reason']
                : 'no question on this slide can be asked this way (the bank has no question of the form it needs for these concepts)';
            $plan['slides'][$i]['pattern_dropped'] = ['type' => $type, 'reason' => $reason];
            $plan['slides'][$i]['h5p_pattern'] = null;
        }

        return $plan;
    }

    /**
     * How each placed practice question is played, per slide. Specs only: nothing is converted or
     * stored. A slide the bank gave nothing to gets a discussion prompt (SlideHtmlRenderer), not a quiz.
     *
     * @return array<int,array<int,array<string,mixed>>> slide number => activity specs
     */
    private function activities(array $context, array $map, array $plan, array $content, array $eligible): array
    {
        $byId = [];
        foreach ($eligible as $list) {
            foreach ($list as $q) {
                $byId[$q['id']] = $q;
            }
        }
        $level = preg_match('/\d+/', (string) $context['chapter']['standard_name'], $m) ? (int) $m[0] : null;

        $out = [];
        foreach ($plan['slides'] as $slide) {
            $n = $slide['n'];
            $questions = array_map(fn ($id) => $byId[$id], $slide['question_ids']);
            $acts = $this->activityPlanner->plan($slide, $questions, $map['concepts'], $level);

            $out[$n] = $acts;
        }

        return $out;
    }
}
