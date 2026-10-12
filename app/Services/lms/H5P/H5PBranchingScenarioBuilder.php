<?php

namespace App\Services\lms\H5P;

use App\Models\lms\h5p\H5pBranchingNode;
use App\Models\lms\h5p\H5pBranchingScenario;
use RuntimeException;

/**
 * Translates between h5p_branching_* rows and H5P.BranchingScenario params.
 *
 * THE SHAPE OF THE TARGET (read from h5p/h5p-branching-scenario and
 * h5p/h5p-branching-question semantics.json, v1.11.1 / 1.0.20):
 *
 *   {branchingScenario: {
 *       title, startScreen{startScreenTitle, startScreenSubtitle,
 *                          startScreenImage, startScreenAltText},
 *       endScreens[{endScreenTitle, endScreenSubtitle, endScreenImage,
 *                   endScreenScore, contentId}],
 *       content[{type{library, params, subContentId, metadata},
 *                showContentTitle, proceedButtonText, forceContentFinished,
 *                nextContentId, feedback{...}, contentBehaviour}],
 *       scoringOptionGroup{scoringOption, includeInteractionsScores},
 *       behaviour{enableBackwardsNavigation, forceContentFinished,
 *                 randomizeBranchingQuestions},
 *       l10n{...}}}
 *
 * Three translations are worth knowing before reading the code:
 *
 *   ENDINGS. In H5P an ending is not a node: it is an entry of `endScreens`,
 *   and a branch reaches it with a NEGATIVE `nextContentId` (-1 is the first
 *   end screen, -2 the second). Here an ending is a row like any other, so the
 *   k-th ending becomes end screen k with contentId -(k+1), and every edge
 *   into it is rewritten.
 *
 *   A SCREEN WITH A PICTURE. H5P has no node that is both text and image, so a
 *   node carrying both becomes two entries -- H5P.Image, then H5P.AdvancedText
 *   -- chained one to the next. The first entry is tagged `mergeWithNext` so
 *   a re-import through THIS product folds them back into one screen; a stock
 *   host shows them as two consecutive steps, which reads the same.
 *
 *   WHAT H5P DOES NOT MODEL. A choice's `quality`, a node's `purpose`, the
 *   concept and bank question it was written from, and the learning objectives
 *   ride in `eduerp*` extension keys. A foreign host ignores them; this
 *   importer reads them back, so a round trip through this product loses
 *   nothing. The objectives are ALSO appended to the start screen's subtitle as
 *   a list, so a stock host shows them to the learner.
 *
 * Only the three libraries this product's nodes use are written. Anything else
 * a foreign package nests (Course Presentation, Interactive Video, Video,
 * Image Hotspots) is imported as a placeholder screen AND reported, never
 * dropped silently: dropping it would leave a branch that leads nowhere.
 */
class H5PBranchingScenarioBuilder
{
    use ConvertsToH5PParams;

    private const REGISTRY_CODE = 'branching_scenario';

    private const TEXT_LIBRARY = 'H5P.AdvancedText 1.1';

    private const IMAGE_LIBRARY = 'H5P.Image 1.1';

    private const QUESTION_LIBRARY = 'H5P.BranchingQuestion 1.0';

    // -----------------------------------------------------------------------
    // Build
    // -----------------------------------------------------------------------

    /**
     * Build the H5P.BranchingScenario `params` object for one scenario.
     *
     * @return array<string,mixed>
     */
    public function build(H5pBranchingScenario $scenario): array
    {
        $nodes = $scenario->relationLoaded('nodes') ? $scenario->nodes : $scenario->nodes()->get();
        $nodes = $nodes->sortBy('node_index')->values();

        $endings = [];
        $content = [];
        foreach ($nodes as $node) {
            if ($node->node_type === H5pBranchingNode::ENDING) {
                $endings[] = $node;
            } else {
                $content[] = $node;
            }
        }

        // The k-th ending is end screen k, reached by contentId -(k+1).
        $endingId = [];
        foreach ($endings as $k => $ending) {
            $endingId[(int) $ending->id] = -($k + 1);
        }

        // Where each content node's FIRST entry sits in `content`.
        $entryIndex = [];
        $cursor = 0;
        foreach ($content as $node) {
            $entryIndex[(int) $node->id] = $cursor;
            $cursor += $this->slots($node);
        }

        // An edge to nothing -- which publishing refuses -- becomes the first
        // end screen, as H5P itself treats -1, rather than an index that does
        // not exist.
        $resolve = function (mixed $id) use ($endingId, $entryIndex): int {
            $id = $id === null ? null : (int) $id;

            return $id !== null ? ($endingId[$id] ?? $entryIndex[$id] ?? -1) : -1;
        };

        $entries = [];
        foreach ($content as $node) {
            foreach ($this->entriesFor($node, count($entries), $resolve) as $entry) {
                $entries[] = $entry;
            }
        }

        $endScreens = [];
        foreach ($endings as $k => $ending) {
            $endScreens[] = $this->endScreen($ending, -($k + 1));
        }
        if ($endScreens === []) {
            // H5P needs somewhere for a path to stop.
            $endScreens[] = [
                'endScreenTitle' => '<p>The end</p>',
                'endScreenSubtitle' => '',
                'endScreenScore' => 0,
                'contentId' => -1,
            ];
        }

        $params = [
            'branchingScenario' => [
                'title' => (string) $scenario->title,
                'startScreen' => $this->startScreen($scenario),
                'endScreens' => $endScreens,
                'content' => $entries,
                'scoringOptionGroup' => [
                    'scoringOption' => in_array($scenario->scoring_option, H5pBranchingScenario::SCORING_OPTIONS, true)
                        ? $scenario->scoring_option
                        : 'static-end-score',
                    'includeInteractionsScores' => true,
                ],
                'behaviour' => [
                    'enableBackwardsNavigation' => (bool) $scenario->enable_backwards_navigation,
                    'forceContentFinished' => false,
                    'randomizeBranchingQuestions' => (bool) $scenario->randomize_alternatives,
                ],
                'l10n' => [
                    'startScreenButtonText' => 'Start',
                    'endScreenButtonText' => 'Try again',
                    'backButtonText' => 'Back',
                    'disableProceedButtonText' => 'Require to complete the current module',
                    'replayButtonText' => 'Replay the video',
                    'scoreText' => 'Your score:',
                    'fullscreenAria' => 'Fullscreen',
                ],
            ],
            // Not part of H5P.BranchingScenario: what this product keeps that
            // H5P does not model. Read back by parse().
            'eduerpScenario' => [
                'learningObjectives' => array_values((array) ($scenario->learning_objectives ?? [])),
                'startSubtitle' => (string) ($scenario->start_subtitle ?? ''),
                'passPercentage' => max(0, min(100, (int) $scenario->pass_percentage)),
            ],
        ];

        return $params;
    }

    /** @return array<string,mixed> */
    private function startScreen(H5pBranchingScenario $scenario): array
    {
        $title = trim((string) $scenario->start_title) !== '' ? (string) $scenario->start_title : (string) $scenario->title;

        $subtitle = trim((string) ($scenario->start_subtitle ?? ''));
        $objectives = array_values(array_filter(array_map(
            fn ($o) => trim((string) $o),
            (array) ($scenario->learning_objectives ?? [])
        ), fn ($o) => $o !== ''));

        $subtitleHtml = $subtitle !== '' ? $this->html($subtitle) : '';
        if ($objectives !== []) {
            // So a stock host shows them: its start screen has nowhere else.
            $subtitleHtml .= '<p><strong>In this scenario you will:</strong></p><ul>'
                . implode('', array_map(fn ($o) => '<li>' . htmlspecialchars($o, ENT_QUOTES, 'UTF-8') . '</li>', $objectives))
                . '</ul>';
        }

        $screen = [
            'startScreenTitle' => $this->html($title),
            'startScreenSubtitle' => $subtitleHtml,
            'startScreenAltText' => (string) ($scenario->start_image_alt ?? ''),
        ];

        $image = trim((string) ($scenario->start_image ?? ''));
        if ($image !== '') {
            $screen['startScreenImage'] = $this->file($image);
        }

        return $screen;
    }

    /** @return array<string,mixed> */
    private function endScreen(H5pBranchingNode $ending, int $contentId): array
    {
        $screen = [
            'endScreenTitle' => $this->html((string) $ending->title),
            'endScreenSubtitle' => $this->html((string) ($ending->body ?? '')),
            'endScreenScore' => max(0, (int) $ending->score),
            'contentId' => $contentId,
            'eduerp' => [
                'nodeId' => (int) $ending->id,
                'purpose' => $ending->purpose,
                'conceptId' => $ending->concept_id,
                'imageAlt' => (string) ($ending->media_alt ?? ''),
            ],
        ];

        $image = trim((string) ($ending->media_path ?? ''));
        if ($image !== '') {
            $screen['endScreenImage'] = $this->file($image);
        }

        return $screen;
    }

    /** How many `content` entries one node takes. */
    private function slots(H5pBranchingNode $node): int
    {
        $hasMedia = trim((string) ($node->media_path ?? '')) !== '';
        $hasBody = BranchingPayload::plain($node->body ?? '') !== '';

        // A picture on its own is one entry; a picture with words, or in front
        // of a decision, is two.
        return $hasMedia && ($node->isDecision() || $hasBody) ? 2 : 1;
    }

    /**
     * @param  callable(mixed):int  $resolve
     * @return list<array<string,mixed>>
     */
    private function entriesFor(H5pBranchingNode $node, int $firstIndex, callable $resolve): array
    {
        $hasMedia = trim((string) ($node->media_path ?? '')) !== '';
        $title = trim((string) $node->title);
        $showTitle = (bool) $node->show_title && $title !== '';
        $proceed = trim((string) ($node->proceed_button_text ?? '')) !== '' ? (string) $node->proceed_button_text : 'Proceed';

        $common = fn (string $library, array $params, string $contentType, string $part) => [
            'type' => [
                'library' => $library,
                'params' => $params,
                'subContentId' => $this->subContentId('branching:' . $part, (int) $node->id),
                'metadata' => [
                    'title' => $title !== '' ? $title : 'Untitled',
                    'license' => 'U',
                    'contentType' => $contentType,
                ],
            ],
            'showContentTitle' => $showTitle,
            'proceedButtonText' => $proceed,
            'forceContentFinished' => 'useBehavioural',
            'contentBehaviour' => 'useBehavioural',
            'feedback' => ['title' => '', 'subtitle' => ''],
        ];

        $extension = fn (array $more = []) => ['eduerp' => [
            'nodeId' => (int) $node->id,
            'purpose' => $node->purpose,
            'conceptId' => $node->concept_id,
            'sourceQuestionId' => $node->source_question_id,
            'showTitle' => (bool) $node->show_title,
        ] + $more];

        $entries = [];
        $mediaEntry = null;

        if ($hasMedia) {
            $mediaEntry = $common(
                self::IMAGE_LIBRARY,
                [
                    'file' => $this->file((string) $node->media_path),
                    'alt' => (string) ($node->media_alt ?? ''),
                    'decorative' => trim((string) ($node->media_alt ?? '')) === '',
                ],
                'Image',
                'image'
            );
            // A picture shown alone has no title to show.
            $mediaEntry['showContentTitle'] = false;
        }

        if ($node->isDecision()) {
            $main = $common(
                self::QUESTION_LIBRARY,
                ['branchingQuestion' => [
                    'question' => $this->html((string) ($node->body ?? '')),
                    'alternatives' => $this->alternatives($node, $resolve),
                ]],
                'Branching Question',
                'question'
            );
            // The alternatives carry the destinations; -1 is what H5P's own
            // editor writes on a question's own `nextContentId`.
            $main['nextContentId'] = -1;
            $main += $extension();
            $entries = $mediaEntry !== null ? [$mediaEntry, $main] : [$main];
        } elseif ($hasMedia && BranchingPayload::plain($node->body ?? '') === '') {
            // A picture and nothing else: the Image entry IS the node.
            $mediaEntry['nextContentId'] = $resolve($node->next_node_id);
            $mediaEntry['showContentTitle'] = $showTitle;
            $mediaEntry += $extension();
            $entries = [$mediaEntry];
        } else {
            $main = $common(self::TEXT_LIBRARY, ['text' => $this->html((string) ($node->body ?? ''))], 'Text', 'text');
            $main['nextContentId'] = $resolve($node->next_node_id);
            $main += $extension();
            $entries = $mediaEntry !== null ? [$mediaEntry, $main] : [$main];
        }

        if ($mediaEntry !== null && count($entries) === 2) {
            $entries[0]['nextContentId'] = $firstIndex + 1;
            $entries[0] += $extension(['mergeWithNext' => true]);
        }

        return $entries;
    }

    /**
     * @param  callable(mixed):int  $resolve
     * @return list<array<string,mixed>>
     */
    private function alternatives(H5pBranchingNode $node, callable $resolve): array
    {
        $out = [];
        foreach (array_values((array) ($node->options ?? [])) as $option) {
            if (! is_array($option)) {
                continue;
            }

            $feedback = [
                'title' => $this->html((string) ($option['feedback_title'] ?? '')),
                'subtitle' => $this->html((string) ($option['feedback_text'] ?? '')),
            ];
            $image = trim((string) ($option['feedback_image'] ?? ''));
            if ($image !== '') {
                $feedback['image'] = $this->file($image);
            }

            $out[] = [
                // `text` is a plain-text field in the semantics.
                'text' => trim(html_entity_decode(strip_tags((string) ($option['text'] ?? '')), ENT_QUOTES | ENT_HTML5, 'UTF-8')),
                'nextContentId' => $resolve($option['next_node_id'] ?? null),
                'feedback' => $feedback,
                'eduerp' => [
                    'quality' => $option['quality'] ?? null,
                    'feedbackImageAlt' => (string) ($option['feedback_image_alt'] ?? ''),
                ],
            ];
        }

        return $out;
    }

    /** @return array{path:string,mime:string,copyright:array<string,string>} */
    private function file(string $path): array
    {
        return [
            'path' => $path,
            'mime' => $this->mimeFor($path, 'image'),
            'copyright' => ['license' => 'U'],
        ];
    }

    /** H5P's rich-text fields are HTML; a plain string becomes a paragraph. */
    private function html(string $text): string
    {
        $text = trim($text);
        if ($text === '') {
            return '';
        }
        if (preg_match('/<\/?[a-z][^>]*>/i', $text) === 1) {
            return $text;
        }

        return '<p>' . htmlspecialchars($text, ENT_QUOTES, 'UTF-8') . '</p>';
    }

    // -----------------------------------------------------------------------
    // Parse
    // -----------------------------------------------------------------------

    /**
     * Read H5P.BranchingScenario params into the editor's payload shape.
     *
     * Nodes come back addressed by REF (`c<index>` for content, `e<k>` for end
     * screens), because a package has no ids -- the same shape a save from the
     * editor takes, so an import and a save share one write path.
     *
     * @param  array<string,mixed>  $content
     * @return array{scenario:array<string,mixed>,nodes:list<array<string,mixed>>,warnings:list<string>}
     *
     * @throws RuntimeException
     */
    public function parse(array $content): array
    {
        $bs = $content['branchingScenario'] ?? null;
        if (! is_array($bs) || ! is_array($bs['content'] ?? null)) {
            throw new RuntimeException('That package has no Branching Scenario content.');
        }

        $warnings = [];
        $items = array_values($bs['content']);
        $screens = array_values(array_filter((array) ($bs['endScreens'] ?? []), 'is_array'));

        // An entry tagged mergeWithNext is the picture half of a screen this
        // product exported as two. Its node is the NEXT entry's.
        $merged = [];
        foreach ($items as $i => $item) {
            $merged[$i] = ! empty($item['eduerp']['mergeWithNext']) && isset($items[$i + 1]);
        }
        $refOfEntry = [];
        foreach ($items as $i => $_) {
            $refOfEntry[$i] = 'c' . ($merged[$i] ? $i + 1 : $i);
        }

        $endRef = [];
        foreach ($screens as $k => $screen) {
            $endRef[(int) ($screen['contentId'] ?? -($k + 1))] = 'e' . $k;
        }
        // A negative id with no matching end screen is H5P's default ending,
        // which is the first one (and is added below if the package has none).
        $defaultEnding = 'e0';

        $resolve = function (mixed $id) use ($refOfEntry, $endRef, $defaultEnding): ?string {
            if ($id === null || $id === '') {
                return null;
            }
            $id = (int) $id;
            if ($id >= 0) {
                return $refOfEntry[$id] ?? null;
            }

            return $endRef[$id] ?? $defaultEnding;
        };

        $nodes = [];
        $pendingMedia = [];
        foreach ($items as $i => $item) {
            if (! is_array($item)) {
                continue;
            }

            $type = (array) ($item['type'] ?? []);
            $library = explode(' ', (string) ($type['library'] ?? ''))[0];
            $params = (array) ($type['params'] ?? []);
            $extension = (array) ($item['eduerp'] ?? []);
            $title = trim((string) ($type['metadata']['title'] ?? ''));
            $title = $title === 'Untitled' ? '' : $title;

            $base = [
                'ref' => $refOfEntry[$i],
                'purpose' => $extension['purpose'] ?? null,
                'title' => $title,
                'show_title' => array_key_exists('showTitle', $extension)
                    ? (bool) $extension['showTitle']
                    : (bool) ($item['showContentTitle'] ?? false),
                'body' => '',
                'media_path' => '',
                'media_alt' => '',
                'proceed_button_text' => (string) ($item['proceedButtonText'] ?? ''),
                'next_ref' => $resolve($item['nextContentId'] ?? null),
                'score' => 0,
                'concept_id' => $extension['conceptId'] ?? null,
                'source_question_id' => $extension['sourceQuestionId'] ?? null,
                'options' => [],
            ];

            if ($library === 'H5P.Image') {
                $media = [
                    'media_path' => (string) ($params['file']['path'] ?? ''),
                    'media_alt' => (string) ($params['alt'] ?? ''),
                ];
                if ($merged[$i]) {
                    $pendingMedia[$i + 1] = $media;

                    continue;
                }
                $nodes[] = ['node_type' => 'text'] + $media + $base;
            } elseif ($library === 'H5P.AdvancedText') {
                $nodes[] = ['node_type' => 'text', 'body' => (string) ($params['text'] ?? '')] + ($pendingMedia[$i] ?? []) + $base;
            } elseif ($library === 'H5P.BranchingQuestion') {
                $bq = (array) ($params['branchingQuestion'] ?? []);
                $options = [];
                foreach (array_values((array) ($bq['alternatives'] ?? [])) as $alt) {
                    if (! is_array($alt)) {
                        continue;
                    }
                    $options[] = [
                        'text' => (string) ($alt['text'] ?? ''),
                        'next_ref' => $resolve($alt['nextContentId'] ?? null),
                        'quality' => $alt['eduerp']['quality'] ?? null,
                        'feedback_title' => (string) ($alt['feedback']['title'] ?? ''),
                        'feedback_text' => (string) ($alt['feedback']['subtitle'] ?? ''),
                        'feedback_image' => (string) ($alt['feedback']['image']['path'] ?? ''),
                        'feedback_image_alt' => (string) ($alt['eduerp']['feedbackImageAlt'] ?? ''),
                    ];
                }
                $nodes[] = [
                    'node_type' => 'branching_question',
                    'body' => (string) ($bq['question'] ?? ''),
                    'options' => $options,
                    'next_ref' => null,
                ] + ($pendingMedia[$i] ?? []) + $base;
            } else {
                // A library this ERP has no screen for. Kept as a placeholder
                // so the branches into and out of it still connect, and said
                // out loud so the author knows what to replace.
                $name = $library !== '' ? $library : 'an unknown library';
                $warnings[] = sprintf('Step %d is %s, which this ERP cannot play yet. It was imported as a placeholder screen.', $i + 1, $name);
                $nodes[] = [
                    'node_type' => 'text',
                    'title' => $title !== '' ? $title : 'Unsupported content',
                    'body' => '<p>This step was ' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . ' in the imported package. Replace it with a screen of your own.</p>',
                ] + $base;
            }
        }

        foreach ($screens as $k => $screen) {
            $extension = (array) ($screen['eduerp'] ?? []);
            $nodes[] = [
                'ref' => 'e' . $k,
                'node_type' => 'ending',
                'purpose' => $extension['purpose'] ?? null,
                'title' => trim(html_entity_decode(strip_tags((string) ($screen['endScreenTitle'] ?? '')), ENT_QUOTES | ENT_HTML5, 'UTF-8')),
                'show_title' => true,
                'body' => (string) ($screen['endScreenSubtitle'] ?? ''),
                'media_path' => (string) ($screen['endScreenImage']['path'] ?? ''),
                'media_alt' => (string) ($extension['imageAlt'] ?? ''),
                'proceed_button_text' => '',
                'next_ref' => null,
                'score' => max(0, (int) ($screen['endScreenScore'] ?? 0)),
                'concept_id' => $extension['conceptId'] ?? null,
                'source_question_id' => null,
                'options' => [],
            ];
        }

        if ($screens === [] && $nodes !== []) {
            $nodes[] = [
                'ref' => 'e0', 'node_type' => 'ending', 'purpose' => 'summary', 'title' => 'The end', 'show_title' => true,
                'body' => '', 'media_path' => '', 'media_alt' => '', 'proceed_button_text' => '', 'next_ref' => null,
                'score' => 0, 'concept_id' => null, 'source_question_id' => null, 'options' => [],
            ];
        }

        $start = (array) ($bs['startScreen'] ?? []);
        $own = (array) ($content['eduerpScenario'] ?? []);
        $scoring = (string) ($bs['scoringOptionGroup']['scoringOption'] ?? 'no-score');
        if ($scoring === 'dynamic-score') {
            $warnings[] = 'The package scores by points collected along the path. This ERP scores by the ending reached, so it was imported that way.';
            $scoring = 'static-end-score';
        }
        if (! in_array($scoring, H5pBranchingScenario::SCORING_OPTIONS, true)) {
            $scoring = 'no-score';
        }

        $scenario = [
            'title' => trim((string) ($bs['title'] ?? '')),
            'start_title' => trim(html_entity_decode(strip_tags((string) ($start['startScreenTitle'] ?? '')), ENT_QUOTES | ENT_HTML5, 'UTF-8')),
            // Prefer the verbatim subtitle this product exported: the one in
            // startScreen has the objectives list appended for a stock host.
            'start_subtitle' => array_key_exists('startSubtitle', $own)
                ? (string) $own['startSubtitle']
                : (string) ($start['startScreenSubtitle'] ?? ''),
            'start_image' => (string) ($start['startScreenImage']['path'] ?? ''),
            'start_image_alt' => (string) ($start['startScreenAltText'] ?? ''),
            'learning_objectives' => array_values((array) ($own['learningObjectives'] ?? [])),
            'scoring_option' => $scoring,
            'pass_percentage' => isset($own['passPercentage']) ? (int) $own['passPercentage'] : 60,
            'enable_backwards_navigation' => (bool) ($bs['behaviour']['enableBackwardsNavigation'] ?? false),
            'randomize_alternatives' => (bool) ($bs['behaviour']['randomizeBranchingQuestions'] ?? false),
        ];

        return ['scenario' => $scenario, 'nodes' => $nodes, 'warnings' => $warnings];
    }
}
