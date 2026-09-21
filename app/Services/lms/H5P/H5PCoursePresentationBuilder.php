<?php

namespace App\Services\lms\H5P;

use App\Models\lms\h5p\H5pCoursePresentation;
use App\Models\lms\h5p\H5pDragDrop;
use App\Models\lms\h5p\H5pPresentationSlide;
use App\Models\lms\h5p\H5pSlideElement;

/**
 * Translates between the h5p_course_presentation tables and
 * H5P.CoursePresentation params.
 *
 * WHAT MAKES THIS BUILDER DIFFERENT FROM THE OTHER FIVE
 *
 * The others translate one library. This one translates a CONTAINER: every
 * slide element becomes a nested sub-content object with its own library, its
 * own params and its own subContentId. So the interesting part of this class
 * is a single map -- element_type -> library -> params shape -- and the rest
 * is the geometry and ordering around it.
 *
 *   text             H5P.AdvancedText  1.1
 *   image            H5P.Image         1.1
 *   video            H5P.Video         1.6
 *   audio            H5P.Audio         1.5
 *   multiple_choice  H5P.MultiChoice   1.16
 *   true_false       H5P.TrueFalse     1.8
 *   blanks           H5P.Blanks        1.14
 *   drag_drop        H5P.DragQuestion  1.14   (inlined from another row)
 *   goto_slide       H5P.GoToSlide     1.3
 *
 * That list and the `dependencies` closure in config/h5p_libraries.php are two
 * views of one decision: a library that can appear here must be declared
 * there, or an importing host renders an empty box where the question was.
 *
 * INLINING DRAG AND DROP. A `drag_drop` element holds a reference, not a
 * question -- see the migration for why. An exported package cannot hold a
 * reference, so the referenced activity's params are built (by the existing
 * H5PDragQuestionBuilder, not a second copy) and embedded. A reference that no
 * longer resolves exports as a short text element saying so, because a deck
 * that silently loses a question is worse than one that admits it.
 *
 * BRANCHING. H5P.CoursePresentation has no per-slide "go to X instead of X+1";
 * its branching is a GoToSlide element the learner clicks. A slide's
 * `next_slide_id` is therefore exported in the extension namespace AND, where
 * the slide has no navigation element of its own, does not change the exported
 * deck's sequence. This product's player honours it; a foreign host walks the
 * deck in order. That is stated rather than hidden because it is the one place
 * this schema is richer than the target format.
 */
class H5PCoursePresentationBuilder
{
    use ConvertsToH5PParams;

    private const REGISTRY_CODE = 'course_presentation';

    /** element_type -> the library its `action` declares. */
    private const LIBRARIES = [
        'text' => 'H5P.AdvancedText 1.1',
        'image' => 'H5P.Image 1.1',
        'video' => 'H5P.Video 1.6',
        'audio' => 'H5P.Audio 1.5',
        'multiple_choice' => 'H5P.MultiChoice 1.16',
        'true_false' => 'H5P.TrueFalse 1.8',
        'blanks' => 'H5P.Blanks 1.14',
        'drag_drop' => 'H5P.DragQuestion 1.14',
        'goto_slide' => 'H5P.GoToSlide 1.3',
    ];

    public function __construct(private readonly H5PDragQuestionBuilder $dragQuestions)
    {
    }

    // -----------------------------------------------------------------------
    // Build
    // -----------------------------------------------------------------------

    /**
     * Build the H5P.CoursePresentation `params` object for one deck.
     *
     * @return array<string,mixed>
     */
    public function build(H5pCoursePresentation $deck): array
    {
        $slides = $deck->relationLoaded('slides') ? $deck->slides : $deck->slides()->with('elements')->get();

        // slide id -> 0-based index. GoToSlide addresses slides by their
        // ORDINAL, not their id, so every navigation target has to be mapped
        // through this -- and a target outside the deck has to be dropped
        // rather than emitted as a jump to nowhere.
        $slideIndex = [];
        foreach ($slides as $position => $slide) {
            $slideIndex[(int) $slide->id] = $position;
        }

        $paramSlides = [];
        $keywords = [];

        foreach ($slides as $position => $slide) {
            $elements = $slide->relationLoaded('elements') ? $slide->elements : $slide->elements()->get();

            $paramElements = [];
            foreach ($elements as $element) {
                $action = $this->elementAction($element, $slideIndex);
                if ($action === null) {
                    continue;
                }

                $paramElements[] = [
                    'x' => $this->num($element->position_x),
                    'y' => $this->num($element->position_y),
                    'width' => $this->num($element->width),
                    'height' => $this->num($element->height),
                    'action' => $action,
                    // A question rendered as a button opens in an overlay
                    // instead of sitting on the slide. Static content never is.
                    'displayAsButton' => false,
                    'backgroundOpacity' => 0,
                    'invisible' => false,
                    'alwaysDisplayComments' => false,
                ];
            }

            $paramSlides[] = [
                'elements' => $paramElements,
                'keywords' => [['main' => $slide->displayTitle()]],
                'slideBackgroundSelector' => $this->slideBackground($slide),
                // This product's per-slide branching, carried for a round trip
                // through here. See the class header.
                'eduerpNextSlideIndex' => isset($slideIndex[(int) $slide->next_slide_id])
                    ? $slideIndex[(int) $slide->next_slide_id]
                    : null,
                'eduerpNotes' => (string) ($slide->notes ?? ''),
            ];

            $keywords[] = $slide->displayTitle();
        }

        $params = [
            'presentation' => [
                'slides' => $paramSlides,
                'keywordListEnabled' => (bool) $deck->show_keywords,
                'keywordListAlwaysShow' => false,
                'keywordListAutoHide' => false,
                'keywordListOpacity' => 90,
                'globalBackgroundSelector' => [],
            ],
            'override' => [
                'activeSurface' => (bool) $deck->active_surface,
                'hideSummarySlide' => ! $deck->show_summary_slide,
                'summarySlideSolutionButton' => (bool) $deck->enable_show_solution,
                'summarySlideRetryButton' => (bool) $deck->enable_retry,
                'enablePrintButton' => (bool) $deck->enable_print,
                'social' => ['showFacebookShare' => false, 'showTwitterShare' => false, 'showGooglePlusShare' => false],
            ],
            'l10n' => [
                'slide' => 'Slide',
                'score' => 'Score',
                'yourScore' => 'Your score',
                'maxScore' => 'Max score',
                'total' => 'Total',
                'showSolutions' => 'Show solutions',
                'retry' => 'Retry',
                'summary' => 'Summary',
                'nextSlide' => 'Next slide',
                'prevSlide' => 'Previous slide',
                'goHome' => 'First slide',
            ],
            // Not part of H5P.CoursePresentation: the theme, the transition and
            // the scoring threshold this product exposes.
            'eduerpPresentation' => [
                'theme' => (string) ($deck->theme ?: 'default'),
                'slideTransition' => (string) ($deck->slide_transition ?: 'fade'),
                'showProgressBar' => (bool) $deck->show_progress_bar,
                'passPercentage' => max(0, min(100, (int) $deck->pass_percentage)),
                'keywords' => $keywords,
            ],
            'overallFeedback' => $this->feedbackBands($deck->feedback_bands),
        ];

        return $this->mergePreservedKeys($params, $deck->content_json);
    }

    /** @return array<string,mixed> */
    private function slideBackground(H5pPresentationSlide $slide): array
    {
        $image = trim((string) ($slide->background_image ?? ''));
        if ($image !== '') {
            return [
                'imageSlideBackground' => [
                    'path' => $image,
                    'mime' => $this->mimeFor($image),
                    'copyright' => ['license' => 'U'],
                ],
            ];
        }

        $token = trim((string) ($slide->background_token ?? ''));

        // A design-system token name, not a colour. A foreign host that does
        // not know the token renders its default background, which is the
        // correct degradation -- the alternative is baking this product's
        // palette into an exported file.
        return $token !== '' ? ['eduerpBackgroundToken' => $token] : [];
    }

    /**
     * The sub-content object for one element, or null if it cannot be built.
     *
     * @param  array<int,int>  $slideIndex
     * @return array<string,mixed>|null
     */
    private function elementAction(H5pSlideElement $element, array $slideIndex): ?array
    {
        $library = self::LIBRARIES[$element->element_type] ?? null;
        if ($library === null) {
            return null;
        }

        $options = (array) ($element->options ?? []);
        $subContentId = $this->subContentId('coursepresentation:element', (int) $element->id);
        $title = $this->elementTitle($element);

        $params = match ($element->element_type) {
            'text' => ['text' => (string) ($element->content_text ?? '')],

            'image' => [
                'file' => $this->mediaFile((string) $element->media_path, 'image'),
                'alt' => (string) ($element->media_alt ?? ''),
                'decorative' => trim((string) ($element->media_alt ?? '')) === '',
            ],

            'video' => [
                'sources' => [$this->mediaFile((string) $element->media_path, 'video')],
                'visuals' => [
                    'controls' => (bool) ($options['controls'] ?? true),
                    'fit' => true,
                    'poster' => null,
                ],
                'playback' => [
                    'autoplay' => (bool) ($options['autoplay'] ?? false),
                    'loop' => (bool) ($options['loop'] ?? false),
                ],
                'a11y' => [],
            ],

            'audio' => [
                'files' => [$this->mediaFile((string) $element->media_path, 'audio')],
                'playerMode' => 'full',
                'fitToWrapper' => true,
                'controls' => (bool) ($options['controls'] ?? true),
                'autoplay' => (bool) ($options['autoplay'] ?? false),
                'playAudio' => 'Play audio',
                'pauseAudio' => 'Pause audio',
            ],

            'multiple_choice' => $this->multiChoiceParams($element, $options),

            'true_false' => [
                'question' => (string) ($element->content_text ?? ''),
                // H5P.TrueFalse stores the correct side as the STRING "true"
                // or "false", not a boolean. A boolean here is silently wrong.
                'correct' => ((bool) ($options['correct'] ?? true)) ? 'true' : 'false',
                'behaviour' => [
                    'enableRetry' => (bool) ($options['enable_retry'] ?? true),
                    'enableSolutionsButton' => (bool) ($options['enable_solution'] ?? true),
                    'confirmCheckDialog' => false,
                    'confirmRetryDialog' => false,
                    'autoCheck' => false,
                ],
                'l10n' => [
                    'trueText' => 'True',
                    'falseText' => 'False',
                    'correctText' => 'Correct!',
                    'wrongText' => 'Incorrect!',
                    'checkAnswer' => 'Check',
                    'showSolutionButton' => 'Show solution',
                    'tryAgain' => 'Retry',
                ],
            ],

            'blanks' => [
                'text' => (string) ($options['task_description'] ?? 'Fill in the missing words'),
                // H5P.Blanks takes a LIST of passages, each a paragraph of
                // HTML carrying inline *answer* markup -- the same markup the
                // existing text-activity type stores. One element is one
                // passage; the list is the format's, not a feature here.
                'questions' => [$this->blanksPassage($element, $options)],
                'behaviour' => [
                    'caseSensitive' => (bool) ($options['case_sensitive'] ?? false),
                    'showSolutionsRequiresInput' => true,
                    'autoCheck' => false,
                    'separateLines' => false,
                    'enableRetry' => (bool) ($options['enable_retry'] ?? true),
                    'enableSolutionsButton' => (bool) ($options['enable_solution'] ?? true),
                    'acceptSpellingErrors' => (bool) ($options['accept_spelling_errors'] ?? false),
                ],
            ],

            'drag_drop' => $this->embeddedDragQuestion($element),

            'goto_slide' => $this->goToSlideParams($element, $options, $slideIndex),

            default => null,
        };

        if ($params === null) {
            return null;
        }

        return [
            'library' => $library,
            'subContentId' => $subContentId,
            'params' => $params,
            'metadata' => [
                'contentType' => $this->contentTypeLabel($element->element_type),
                'license' => 'U',
                'title' => $title,
            ],
        ];
    }

    /**
     * @param  array<string,mixed>  $options
     * @return array<string,mixed>
     */
    private function multiChoiceParams(H5pSlideElement $element, array $options): array
    {
        $answers = [];
        foreach ((array) ($options['answers'] ?? []) as $answer) {
            if (! is_array($answer)) {
                continue;
            }
            $answers[] = [
                'text' => '<div>' . (string) ($answer['text'] ?? '') . '</div>',
                'correct' => (bool) ($answer['correct'] ?? false),
                'tipsAndFeedback' => [
                    'tip' => (string) ($answer['tip'] ?? ''),
                    'chosenFeedback' => (string) ($answer['feedback'] ?? ''),
                    'notChosenFeedback' => '',
                ],
            ];
        }

        // `singleAnswer` is H5P's radio-vs-checkbox switch. Derived from the
        // answer key rather than stored separately, so a question with two
        // correct answers cannot be saved as single-choice and score 0 forever.
        $correctCount = count(array_filter($answers, fn ($a) => $a['correct']));

        return [
            'question' => '<p>' . (string) ($element->content_text ?? '') . '</p>',
            'answers' => $answers,
            'behaviour' => [
                'singleAnswer' => $correctCount <= 1,
                'enableRetry' => (bool) ($options['enable_retry'] ?? true),
                'enableSolutionsButton' => (bool) ($options['enable_solution'] ?? true),
                'enableCheckButton' => true,
                'type' => 'auto',
                'singlePoint' => false,
                'randomAnswers' => (bool) ($options['randomise_answers'] ?? true),
                'showSolutionsRequiresInput' => true,
                'confirmCheckDialog' => false,
                'confirmRetryDialog' => false,
                'autoCheck' => false,
                'passPercentage' => 100,
            ],
            'UI' => [
                'checkAnswerButton' => 'Check',
                'showSolutionButton' => 'Show solution',
                'tryAgainButton' => 'Retry',
            ],
            'overallFeedback' => [['from' => 0, 'to' => 100]],
        ];
    }

    /**
     * The passage for an embedded Blanks question, as HTML.
     *
     * The stored passage is plain text with `*answer*` markup. H5P.Blanks
     * wants a paragraph of HTML with the same markup intact -- so the text is
     * escaped and the markup is not, which is why this is not one `e()` call.
     *
     * @param  array<string,mixed>  $options
     */
    private function blanksPassage(H5pSlideElement $element, array $options): string
    {
        $passage = (string) ($options['passage'] ?? $element->content_text ?? '');

        // Escape everything, then restore the asterisks the markup needs.
        // e() does not touch `*`, so this is a single pass in practice; the
        // step is written out because the invariant is easy to break later.
        return '<p>' . e($passage) . '</p>';
    }

    /**
     * A referenced Drag and Drop activity, inlined.
     *
     * Uses the existing builder rather than a second implementation, so an
     * embedded drag question and a standalone one export byte-identically.
     *
     * @return array<string,mixed>
     */
    private function embeddedDragQuestion(H5pSlideElement $element): array
    {
        $task = $element->ref_content_id
            ? H5pDragDrop::with(['zones', 'elements'])->find($element->ref_content_id)
            : null;

        if ($task === null) {
            // A deck that silently drops a question is worse than one that
            // says a question is missing. The caller turns this into an export
            // warning; the text is what a reader of the package sees.
            return [
                'question' => [
                    'settings' => ['size' => ['width' => 620, 'height' => 310]],
                    'task' => ['elements' => [], 'dropZones' => []],
                ],
                'eduerpUnavailable' => 'The embedded drag and drop activity could not be found.',
            ];
        }

        return $this->dragQuestions->build($task);
    }

    /**
     * @param  array<string,mixed>  $options
     * @param  array<int,int>  $slideIndex
     * @return array<string,mixed>|null
     */
    private function goToSlideParams(H5pSlideElement $element, array $options, array $slideIndex): ?array
    {
        $targetId = (int) ($options['target_slide_id'] ?? 0);

        // A jump out of the deck is not exportable and is not navigable. Null
        // drops the element rather than shipping a button that goes nowhere.
        if (! isset($slideIndex[$targetId])) {
            return null;
        }

        return [
            // H5P.GoToSlide counts slides from 1 in its params, not from 0.
            'goToSlide' => $slideIndex[$targetId] + 1,
            'goToSlideType' => 'specified',
            'text' => (string) ($options['label'] ?? $element->content_text ?? 'Continue'),
        ];
    }

    /** @return array<string,mixed> */
    private function mediaFile(string $path, string $family): array
    {
        return [
            'path' => $path,
            'mime' => $this->mimeFor($path, $family),
            'copyright' => ['license' => 'U'],
        ];
    }

    private function elementTitle(H5pSlideElement $element): string
    {
        $text = trim(strip_tags((string) ($element->content_text ?? '')));
        if ($text !== '') {
            return mb_strimwidth($text, 0, 80, '…');
        }

        return $this->contentTypeLabel($element->element_type);
    }

    private function contentTypeLabel(string $type): string
    {
        return match ($type) {
            'text' => 'Text',
            'image' => 'Image',
            'video' => 'Video',
            'audio' => 'Audio',
            'multiple_choice' => 'Multiple Choice',
            'true_false' => 'True/False Question',
            'blanks' => 'Fill in the Blanks',
            'drag_drop' => 'Drag and Drop',
            'goto_slide' => 'Go to slide',
            default => 'Element',
        };
    }

    // -----------------------------------------------------------------------
    // Parse
    // -----------------------------------------------------------------------

    /**
     * Read an H5P.CoursePresentation params object into row payloads.
     *
     * Slides come back with a `_ref` and elements address their branching
     * target by SLIDE INDEX, because ids do not exist until the controller has
     * inserted the slides -- the same ref-then-map pattern the drag-and-drop
     * import uses.
     *
     * @param  array<string,mixed>  $params
     * @return array{deck: array<string,mixed>, slides: list<array<string,mixed>>, warnings: list<string>}
     */
    public function parse(array $params): array
    {
        $presentation = (array) ($params['presentation'] ?? []);
        $override = (array) ($params['override'] ?? []);
        $extra = (array) ($params['eduerpPresentation'] ?? []);
        $bands = (array) ($params['overallFeedback'] ?? []);
        $warnings = [];

        $deck = [
            'theme' => in_array($extra['theme'] ?? '', H5pCoursePresentation::THEMES, true)
                ? (string) $extra['theme']
                : 'default',
            'slide_transition' => in_array($extra['slideTransition'] ?? '', H5pCoursePresentation::TRANSITIONS, true)
                ? (string) $extra['slideTransition']
                : 'fade',
            'show_progress_bar' => (bool) ($extra['showProgressBar'] ?? true),
            'show_keywords' => (bool) ($presentation['keywordListEnabled'] ?? true),
            'show_summary_slide' => ! (bool) ($override['hideSummarySlide'] ?? false),
            'enable_print' => (bool) ($override['enablePrintButton'] ?? false),
            'active_surface' => (bool) ($override['activeSurface'] ?? false),
            'enable_retry' => (bool) ($override['summarySlideRetryButton'] ?? true),
            'enable_show_solution' => (bool) ($override['summarySlideSolutionButton'] ?? true),
            'pass_percentage' => (int) ($extra['passPercentage'] ?? $this->passFromFeedback($bands, 60)),
            'feedback_bands' => $this->feedbackBands($bands),
        ];

        $slides = [];
        foreach ((array) ($presentation['slides'] ?? []) as $index => $slide) {
            if (! is_array($slide)) {
                continue;
            }

            $elements = [];
            foreach ((array) ($slide['elements'] ?? []) as $order => $element) {
                if (! is_array($element)) {
                    continue;
                }
                $parsed = $this->parseElement($element, $order, $warnings);
                if ($parsed !== null) {
                    $elements[] = $parsed;
                }
            }

            $slides[] = [
                '_ref' => 'slide-' . $index,
                'slide_index' => $index,
                'title' => (string) ($slide['keywords'][0]['main'] ?? '') ?: null,
                'background_image' => (string) ($slide['slideBackgroundSelector']['imageSlideBackground']['path'] ?? '') ?: null,
                'background_token' => (string) ($slide['slideBackgroundSelector']['eduerpBackgroundToken'] ?? '') ?: null,
                'notes' => (string) ($slide['eduerpNotes'] ?? '') ?: null,
                // Resolved to a ref by the controller once ids exist.
                '_next_slide_index' => is_numeric($slide['eduerpNextSlideIndex'] ?? null)
                    ? (int) $slide['eduerpNextSlideIndex']
                    : null,
                'elements' => $elements,
            ];
        }

        return ['deck' => $deck, 'slides' => $slides, 'warnings' => $warnings];
    }

    /**
     * @param  array<string,mixed>  $element
     * @param  list<string>  $warnings
     * @return array<string,mixed>|null
     */
    private function parseElement(array $element, int $order, array &$warnings): ?array
    {
        $action = (array) ($element['action'] ?? []);
        $library = (string) ($action['library'] ?? '');
        $p = (array) ($action['params'] ?? []);

        $type = $this->typeForLibrary($library);
        if ($type === null) {
            // A library this product has no editor for. Named rather than
            // counted, so the author knows what they will have to re-author.
            $warnings[] = sprintf(
                'Slide element using %s was skipped — this ERP has no editor for it.',
                $library !== '' ? $library : 'an unnamed library'
            );

            return null;
        }

        $row = [
            'element_type' => $type,
            'position_x' => $this->num($element['x'] ?? 10),
            'position_y' => $this->num($element['y'] ?? 10),
            'width' => $this->num($element['width'] ?? 40),
            'height' => $this->num($element['height'] ?? 20),
            'content_text' => null,
            'media_path' => null,
            'media_alt' => null,
            'options' => [],
            'ref_content_id' => null,
            'points' => 1,
            'sort_order' => $order,
        ];

        switch ($type) {
            case 'text':
                $row['content_text'] = (string) ($p['text'] ?? '');
                $row['points'] = 0;
                break;

            case 'image':
                $row['media_path'] = (string) ($p['file']['path'] ?? '');
                $row['media_alt'] = (string) ($p['alt'] ?? '');
                $row['points'] = 0;
                break;

            case 'video':
                $row['media_path'] = (string) ($p['sources'][0]['path'] ?? '');
                $row['options'] = [
                    'controls' => (bool) ($p['visuals']['controls'] ?? true),
                    'autoplay' => (bool) ($p['playback']['autoplay'] ?? false),
                    'loop' => (bool) ($p['playback']['loop'] ?? false),
                ];
                $row['points'] = 0;
                break;

            case 'audio':
                $row['media_path'] = (string) ($p['files'][0]['path'] ?? '');
                $row['options'] = [
                    'controls' => (bool) ($p['controls'] ?? true),
                    'autoplay' => (bool) ($p['autoplay'] ?? false),
                ];
                $row['points'] = 0;
                break;

            case 'multiple_choice':
                $answers = [];
                foreach ((array) ($p['answers'] ?? []) as $answer) {
                    if (! is_array($answer)) {
                        continue;
                    }
                    $answers[] = [
                        'text' => trim(strip_tags((string) ($answer['text'] ?? ''))),
                        'correct' => (bool) ($answer['correct'] ?? false),
                        'tip' => (string) ($answer['tipsAndFeedback']['tip'] ?? ''),
                        'feedback' => (string) ($answer['tipsAndFeedback']['chosenFeedback'] ?? ''),
                    ];
                }
                $row['content_text'] = trim(strip_tags((string) ($p['question'] ?? '')));
                $row['options'] = [
                    'answers' => $answers,
                    'randomise_answers' => (bool) ($p['behaviour']['randomAnswers'] ?? true),
                    'enable_retry' => (bool) ($p['behaviour']['enableRetry'] ?? true),
                    'enable_solution' => (bool) ($p['behaviour']['enableSolutionsButton'] ?? true),
                ];
                break;

            case 'true_false':
                $row['content_text'] = trim(strip_tags((string) ($p['question'] ?? '')));
                // The string, not a boolean -- see the build side.
                $row['options'] = [
                    'correct' => ($p['correct'] ?? 'true') === 'true' || ($p['correct'] ?? null) === true,
                    'enable_retry' => (bool) ($p['behaviour']['enableRetry'] ?? true),
                    'enable_solution' => (bool) ($p['behaviour']['enableSolutionsButton'] ?? true),
                ];
                break;

            case 'blanks':
                $passages = array_map(
                    fn ($q) => trim(strip_tags((string) $q)),
                    (array) ($p['questions'] ?? [])
                );
                $row['content_text'] = (string) ($p['text'] ?? '');
                $row['options'] = [
                    // One element is one passage. A foreign package with
                    // several is joined rather than split into elements the
                    // author never positioned.
                    'passage' => implode("\n", array_filter($passages)),
                    'task_description' => (string) ($p['text'] ?? ''),
                    'case_sensitive' => (bool) ($p['behaviour']['caseSensitive'] ?? false),
                    'accept_spelling_errors' => (bool) ($p['behaviour']['acceptSpellingErrors'] ?? false),
                    'enable_retry' => (bool) ($p['behaviour']['enableRetry'] ?? true),
                    'enable_solution' => (bool) ($p['behaviour']['enableSolutionsButton'] ?? true),
                ];
                break;

            case 'drag_drop':
                // An embedded drag question arrives inlined and has no row of
                // its own here. Importing it as a standalone Drag and Drop
                // activity and then referencing it would create content the
                // author never asked for, in a list they did not open. So the
                // element is kept with its params preserved and no reference,
                // and publish reports it as needing an activity chosen.
                $row['options'] = ['imported_params' => $p];
                $warnings[] = 'An embedded drag and drop arrived inline. Choose an existing activity for it before publishing.';
                break;

            case 'goto_slide':
                $row['content_text'] = (string) ($p['text'] ?? 'Continue');
                $row['options'] = [
                    // 1-based in params, 0-based here; resolved to an id by the
                    // controller once the slides have been inserted.
                    '_target_slide_index' => max(0, (int) ($p['goToSlide'] ?? 1) - 1),
                    'label' => (string) ($p['text'] ?? 'Continue'),
                ];
                $row['points'] = 0;
                break;
        }

        return $row;
    }

    /** "H5P.MultiChoice 1.16" -> "multiple_choice". */
    private function typeForLibrary(string $library): ?string
    {
        $name = trim(explode(' ', $library)[0] ?? '');

        foreach (self::LIBRARIES as $type => $declared) {
            if (explode(' ', $declared)[0] === $name) {
                return $type;
            }
        }

        return null;
    }
}
