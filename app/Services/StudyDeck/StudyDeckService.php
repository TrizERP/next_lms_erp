<?php

namespace App\Services\StudyDeck;

use App\Services\PAL\Integration\ConceptImageSearchService;
use App\Services\QuestionGeneration\DragDrop\Contracts\DiagramImageStore;
use App\Services\StudyDeck\Contracts\Completer;

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
    ) {
    }

    public static function make(Completer $completer, DiagramImageStore $images, ?ConceptImageSearchService $search = null): self
    {
        $patterns = new H5pPatternSelector();
        $imagePlanner = new ImagePlanner($search ?? app(ConceptImageSearchService::class), $images, null, 2.0, new ImageRelevanceJudge($completer));

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
        );
    }

    /**
     * @param callable(string $stage, string $message):void|null $progress
     * @param array<string,mixed> $options min_slides, max_slides, per_concept, allowed_forms
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
     * The same pipeline from already-loaded rows - used by tests and replays.
     *
     * @param array<string,mixed> $raw
     * @return array<string,mixed>
     */
    public function fromRaw(array $raw, array $options = [], ?callable $progress = null): array
    {
        $say = $progress ?? fn () => null;

        $context = $this->contextBuilder->assemble($raw);
        $say('context', sprintf('%d topics, %d concepts, %d without intelligence', count($context['topics']), count($context['concepts']), count($context['unmatched_concepts'])));

        $selection = $this->questionSelector->select(
            (array) $raw['questions'],
            (int) ($options['per_concept'] ?? 4),
            $options['allowed_forms'] ?? ['mcq', 'true_false', 'very_short_answer', 'short_answer']
        );
        $eligible = $selection['eligible'];
        $say('questions', sprintf('%d questions considered, %d excluded as not self-contained or unusable', count((array) $raw['questions']), count($selection['excluded'])));

        $map = $this->learningPlan->build($context, $eligible, (int) ($options['min_slides'] ?? 30), (int) ($options['max_slides'] ?? 35));
        $say('map', 'Learning map built' . ($map['warnings'] ? ' (' . count($map['warnings']) . ' warning(s))' : ''));

        $say('plan', 'Stage 1: planning slides with Claude');
        try {
            $plan = $this->planner->plan($context, $map, $eligible);
        } catch (\RuntimeException $e) {
            $dir = storage_path('app/study-deck/chapter-' . (int) $raw['chapter']['id']);
            @mkdir($dir, 0775, true);
            file_put_contents($dir . '/rejected-plan-replies.json', json_encode($this->planner->replies, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            throw new \RuntimeException($e->getMessage() . "\n(raw replies saved to $dir/rejected-plan-replies.json)", 0, $e);
        }
        $say('plan', count($plan['slides']) . ' slides planned');

        $say('content', 'Stage 2: writing slide content with Claude');
        $content = $this->contentGenerator->generate($context, $map, $plan, fn ($done, $total) => $say('content', "$done/$total slides written"));

        $say('images', 'Finding openly licensed images');
        $images = $this->imagePlanner->plan($plan['slides'], $content, function ($n, $r) use ($say) {
            $say('images', isset($r['missing']) ? "slide $n: none ({$r['missing']})" : "slide $n: " . (($r['type'] ?? 'photo') === 'diagram' ? 'drawn diagram' : $r['licence']));
        });

        $say('interact', 'Stage 3: deciding which slides earn an interaction');
        $interactions = $this->interactionPlanner?->plan($context, $map, $plan, $content, $images, fn ($found, $total) => $say('interact', "$found interaction(s) so far")) ?? [];

        $activities = $this->activities($context, $map, $plan, $content, $eligible);
        $plan = $this->dropUnjustifiedPatterns($plan, $activities, $map, $eligible);
        $say('activities', array_sum(array_map('count', $activities)) . ' practice question(s) placed, '
            . count(array_filter($interactions, fn ($d) => $d['interaction'] !== null)) . ' interaction(s)');

        $rendered = $this->renderer->render($context, $map, $plan, $content, $images, $eligible, $activities, $interactions);
        $report = $this->validator->validate($context, $map, $eligible, $rendered, $selection['flagged'] ?? []);
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
