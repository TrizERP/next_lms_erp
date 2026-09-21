<?php

namespace App\Services\lms\H5P;

use App\Models\lms\h5p\H5pTextActivity;
use RuntimeException;

/**
 * Translates between this ERP's text-activity rows and the params of the three
 * official text-passage libraries: H5P.DragText, H5P.Blanks, H5P.MarkTheWords.
 *
 * WHY THIS EXISTS AT ALL
 *
 * Same reason as H5PDragQuestionBuilder: the rows are the source of truth and
 * this product's own player reads them directly. What needs params is the
 * boundary -- export to a .h5p package, import of one authored elsewhere, and
 * any future swap to the official H5P player, which this class makes a change
 * of renderer rather than a change of data model.
 *
 * THE MARKUP IS THE FORMAT
 *
 * All three libraries store the whole task as ONE STRING. An answer slot is
 * delimited by asterisks and carries up to three things:
 *
 *     *solution/alternative1/alternative2:tip*
 *
 * A literal asterisk in the passage is escaped `\*`. That single grammar is
 * what parsePassage() reads and what buildPassage() writes, and it is why the
 * three types can share storage at all.
 *
 * WHAT EACH LIBRARY ACTUALLY HONOURS -- read this before "fixing" a flatten.
 *
 *   H5P.Blanks        the full grammar: alternatives AND tips.
 *   H5P.DragText      solution + `:tip`. It has NO `/` alternative syntax --
 *                     in DragText, two draggables with identical text are
 *                     interchangeable, which is how that library expresses
 *                     "either word fits here".
 *   H5P.MarkTheWords  the word only. No alternatives, no tips: the learner
 *                     clicks words that are already on the page, so there is
 *                     nothing to be alternative TO.
 *
 * This ERP's own player honours alternatives on all three, because a teacher
 * who typed them means them. But an EXPORT has to produce something the real
 * library will load, so build() flattens what the target cannot express and
 * reports each flatten in `notes()` -- the export surfaces them as warnings
 * rather than shipping a package that quietly marks answers wrong.
 */
class H5PTextActivityBuilder
{
    /** Registry code -> config/h5p_libraries.php key. They are the same here. */
    private const LIBRARY_KEYS = [
        'drag_text' => 'drag_text',
        'fill_in_the_blanks' => 'fill_in_the_blanks',
        'mark_the_words' => 'mark_the_words',
    ];

    /** Which part of the grammar each library can actually render. */
    private const SUPPORTS_ALTERNATIVES = [
        'drag_text' => false,
        'fill_in_the_blanks' => true,
        'mark_the_words' => false,
    ];

    private const SUPPORTS_TIPS = [
        'drag_text' => true,
        'fill_in_the_blanks' => true,
        'mark_the_words' => false,
    ];

    /** Semantic flattens recorded by the last build(), for the exporter. */
    private array $notes = [];

    // -----------------------------------------------------------------------
    // Markup
    // -----------------------------------------------------------------------

    /**
     * Read `*solution/alt:tip*` slots out of a passage, in reading order.
     *
     * Returns one payload per slot, shaped for an h5p_text_activity_blanks
     * row. `raw` and `offset` are carried so a caller can rewrite the passage
     * in place without re-scanning it.
     *
     * The regex deliberately does NOT use a lazy `.*?` across the whole
     * string: an unclosed asterisk would then swallow to the next one and
     * silently turn ordinary prose into an answer. `[^*]*` stops at the first
     * asterisk, so an unbalanced marker yields nothing rather than nonsense.
     *
     * @return list<array{blank_index:int, solution:string, alternatives:list<string>, tip:string|null, raw:string, offset:int, length:int}>
     */
    public function parsePassage(?string $passage): array
    {
        $passage = (string) $passage;
        if ($passage === '') {
            return [];
        }

        // Hide escaped asterisks behind a placeholder that cannot occur in
        // authored text, so `\*` is never mistaken for a delimiter. It is put
        // back verbatim in each captured segment below.
        $sentinel = "\x00ESCAPED_ASTERISK\x00";
        $masked = str_replace('\\*', $sentinel, $passage);

        if (! preg_match_all('/\*([^*]*)\*/u', $masked, $matches, PREG_OFFSET_CAPTURE)) {
            return [];
        }

        $slots = [];
        foreach ($matches[1] as $index => $capture) {
            $body = str_replace($sentinel, '*', (string) $capture[0]);
            $parsed = $this->parseSlotBody($body);

            if ($parsed['solution'] === '') {
                // `**` -- an empty slot. An author mid-edit, not an answer.
                continue;
            }

            $slots[] = $parsed + [
                'blank_index' => count($slots),
                'is_distractor' => false,
                'raw' => str_replace($sentinel, '\\*', (string) $matches[0][$index][0]),
                'offset' => (int) $matches[0][$index][1],
                'length' => strlen((string) $matches[0][$index][0]),
            ];
        }

        return $slots;
    }

    /**
     * Distractor words for Drag the Words, written in the same `*word*`
     * markup so an author uses one grammar rather than two.
     *
     * A bare list with no asterisks is accepted too -- "apple, pear" is what
     * most authors type first -- because rejecting it would be pedantry about
     * punctuation rather than a real ambiguity.
     *
     * @return list<array<string,mixed>>
     */
    public function parseDistractors(?string $distractors, int $startIndex): array
    {
        $distractors = trim((string) $distractors);
        if ($distractors === '') {
            return [];
        }

        $slots = str_contains($distractors, '*')
            ? $this->parsePassage($distractors)
            : array_map(
                fn (string $word) => $this->parseSlotBody($word),
                array_filter(array_map('trim', preg_split('/[,\n]+/u', $distractors) ?: []), fn ($w) => $w !== '')
            );

        $out = [];
        foreach (array_values($slots) as $offset => $slot) {
            if (($slot['solution'] ?? '') === '') {
                continue;
            }
            $out[] = [
                'blank_index' => $startIndex + $offset,
                'solution' => $slot['solution'],
                // A distractor matches nothing, so alternatives and tips on it
                // have nowhere to show. Dropped rather than stored as dead data.
                'alternatives' => [],
                'tip' => null,
                'is_distractor' => true,
            ];
        }

        return $out;
    }

    /**
     * Every answer slot for one activity: passage slots first, then the
     * Drag-the-Words distractors, numbered continuously.
     *
     * @return list<array<string,mixed>>
     */
    public function parseAnswerKey(string $contentType, ?string $passage, ?string $distractors): array
    {
        $slots = array_map(
            fn (array $slot) => [
                'blank_index' => $slot['blank_index'],
                'solution' => $slot['solution'],
                'alternatives' => $slot['alternatives'],
                'tip' => $slot['tip'],
                'is_distractor' => false,
            ],
            $this->parsePassage($passage)
        );

        // Only Drag the Words has anything to drag, so only it has spare words.
        if ($contentType === 'drag_text') {
            $slots = array_merge($slots, $this->parseDistractors($distractors, count($slots)));
        }

        return $slots;
    }

    /** `solution/alt1/alt2:tip` -> its three parts. */
    private function parseSlotBody(string $body): array
    {
        $tip = null;

        // The tip is everything after the LAST colon, so a solution may itself
        // contain one ("ratio 3:4:5" keeps "3:4" and tips on "5" -- which is
        // why the editor shows the parsed result back to the author).
        $colon = strrpos($body, ':');
        if ($colon !== false) {
            $tip = trim(substr($body, $colon + 1));
            $body = substr($body, 0, $colon);
            if ($tip === '') {
                $tip = null;
            }
        }

        $terms = array_values(array_filter(
            array_map('trim', explode('/', $body)),
            fn (string $term) => $term !== ''
        ));

        return [
            'solution' => $terms[0] ?? '',
            'alternatives' => array_slice($terms, 1),
            'tip' => $tip,
        ];
    }

    /**
     * Write a passage's slots back into markup a given library will accept,
     * flattening what it cannot express and recording each flatten.
     */
    private function passageFor(H5pTextActivity $activity): string
    {
        $type = (string) $activity->content_type;
        $passage = (string) $activity->passage;

        if ((self::SUPPORTS_ALTERNATIVES[$type] ?? false) && (self::SUPPORTS_TIPS[$type] ?? false)) {
            return $passage;   // H5P.Blanks -- the markup is already exact.
        }

        $slots = $this->parsePassage($passage);
        if ($slots === []) {
            return $passage;
        }

        $keepAlternatives = self::SUPPORTS_ALTERNATIVES[$type] ?? false;
        $keepTips = self::SUPPORTS_TIPS[$type] ?? false;

        // Rewrite right-to-left so each replacement leaves earlier offsets
        // valid -- the alternative is recomputing every offset per edit.
        foreach (array_reverse($slots) as $slot) {
            $terms = [$slot['solution']];
            if ($keepAlternatives) {
                $terms = array_merge($terms, $slot['alternatives']);
            } elseif ($slot['alternatives'] !== []) {
                $this->notes[] = sprintf(
                    'The alternatives for "%s" (%s) were dropped: %s does not accept more than one answer per slot.',
                    $slot['solution'],
                    implode(', ', $slot['alternatives']),
                    $this->machineName($type)
                );
            }

            $body = implode('/', $terms);
            if ($keepTips && $slot['tip'] !== null && $slot['tip'] !== '') {
                $body .= ':' . $slot['tip'];
            } elseif (! $keepTips && $slot['tip'] !== null && $slot['tip'] !== '') {
                $this->notes[] = sprintf(
                    'The tip on "%s" was dropped: %s does not show tips.',
                    $slot['solution'],
                    $this->machineName($type)
                );
            }

            $passage = substr_replace($passage, '*' . $body . '*', $slot['offset'], $slot['length']);
        }

        return $passage;
    }

    // -----------------------------------------------------------------------
    // Build
    // -----------------------------------------------------------------------

    /**
     * Params for one activity, in its own library's shape.
     *
     * The three libraries agree on `behaviour` and `overallFeedback` and
     * disagree on almost every other key name -- Blanks puts the task text in
     * `text` and the passage in `questions[]`, the other two use
     * `taskDescription` and `textField`. That divergence is the whole reason
     * this method branches rather than templating one object.
     *
     * @return array<string,mixed>
     */
    public function build(H5pTextActivity $activity): array
    {
        $this->notes = [];

        $type = (string) $activity->content_type;
        if (! isset(self::LIBRARY_KEYS[$type])) {
            throw new RuntimeException('Unknown text activity type: ' . $type);
        }

        $passage = $this->passageFor($activity);
        $feedback = $this->feedbackBands($activity);
        $media = $this->mediaParams($activity);

        $params = match ($type) {
            'fill_in_the_blanks' => [
                'text' => $this->html($activity->task_description),
                // Blanks takes a LIST of question strings. This product
                // authors one passage, so the list has one entry -- but it
                // stays a list, because that is the shape the library reads
                // and a package with a bare string there will not load.
                'questions' => [$this->html($passage)],
                'overallFeedback' => $feedback,
                'showSolutions' => 'Show solution',
                'tryAgain' => 'Retry',
                'checkAnswer' => 'Check',
                'submitAnswer' => 'Submit',
                'notFilledOut' => 'Please fill in all blanks to view the solution.',
                'behaviour' => [
                    'enableRetry' => (bool) $activity->enable_retry,
                    'enableSolutionsButton' => (bool) $activity->enable_show_solution,
                    'enableCheckButton' => (bool) $activity->enable_check,
                    'autoCheck' => (bool) $activity->instant_feedback,
                    'caseSensitive' => (bool) $activity->case_sensitive,
                    'showSolutionsRequiresInput' => (bool) $activity->solution_requires_input,
                    'separateLines' => (bool) $activity->separate_lines,
                    'acceptSpellingErrors' => (bool) $activity->accept_spelling_errors,
                    'confirmCheckDialog' => false,
                    'confirmRetryDialog' => false,
                ],
            ],
            'drag_text' => [
                'taskDescription' => $this->html($activity->task_description),
                'textField' => $passage,
                'distractors' => $this->distractorMarkup($activity),
                'overallFeedback' => $feedback,
                'checkAnswer' => 'Check',
                'tryAgain' => 'Retry',
                'showSolution' => 'Show solution',
                'submitAnswer' => 'Submit',
                'behaviour' => [
                    'enableRetry' => (bool) $activity->enable_retry,
                    'enableSolutionsButton' => (bool) $activity->enable_show_solution,
                    'enableCheckButton' => (bool) $activity->enable_check,
                    'instantFeedback' => (bool) $activity->instant_feedback,
                    'showScorePoints' => (bool) $activity->show_score_points,
                ],
            ],
            'mark_the_words' => [
                'taskDescription' => $this->html($activity->task_description),
                'textField' => $passage,
                'overallFeedback' => $feedback,
                'checkAnswerButton' => 'Check',
                'tryAgainButton' => 'Retry',
                'showSolutionButton' => 'Show solution',
                'submitAnswerButton' => 'Submit',
                'behaviour' => [
                    'enableRetry' => (bool) $activity->enable_retry,
                    'enableSolutionsButton' => (bool) $activity->enable_show_solution,
                    'enableCheckButton' => (bool) $activity->enable_check,
                    'showScorePoints' => (bool) $activity->show_score_points,
                ],
            ],
        };

        if ($media !== null) {
            $params['media'] = $media;
        }

        return $this->mergePreservedKeys($params, $activity->content_json);
    }

    /** Semantic flattens recorded by the last build(). @return list<string> */
    public function notes(): array
    {
        return array_values(array_unique($this->notes));
    }

    /**
     * Distractors as DragText writes them: one `*word*` per spare draggable,
     * space separated. Built from the child rows rather than echoed from the
     * author's `distractors` column so the export matches the answer key the
     * player and the scoring actually use.
     */
    private function distractorMarkup(H5pTextActivity $activity): string
    {
        if ($activity->content_type !== 'drag_text') {
            return '';
        }

        $blanks = $activity->relationLoaded('blanks') ? $activity->blanks : $activity->blanks()->get();
        $words = [];
        foreach ($blanks as $blank) {
            if ($blank->is_distractor && trim((string) $blank->solution) !== '') {
                $words[] = '*' . trim((string) $blank->solution) . '*';
            }
        }

        return implode(' ', $words);
    }

    /**
     * `overallFeedback` bands.
     *
     * H5P requires the bands to cover 0-100 with no gap, so an activity that
     * has none authored gets the single band the H5P editor itself defaults
     * to rather than an empty list, which some versions render as a blank
     * feedback line.
     *
     * @return list<array<string,mixed>>
     */
    private function feedbackBands(H5pTextActivity $activity): array
    {
        $bands = (array) ($activity->feedback_bands ?? []);
        $out = [];

        foreach ($bands as $band) {
            if (! is_array($band)) {
                continue;
            }
            $from = max(0, min(100, (int) ($band['from'] ?? 0)));
            $to = max(0, min(100, (int) ($band['to'] ?? 100)));
            if ($to < $from) {
                [$from, $to] = [$to, $from];
            }
            $out[] = array_filter([
                'from' => $from,
                'to' => $to,
                'feedback' => trim((string) ($band['feedback'] ?? '')),
            ], fn ($v) => $v !== '');
        }

        return $out !== [] ? $out : [['from' => 0, 'to' => 100]];
    }

    /**
     * The optional illustration, as the H5P `media` sub-content object.
     *
     * @return array<string,mixed>|null
     */
    private function mediaParams(H5pTextActivity $activity): ?array
    {
        $path = trim((string) $activity->media_image);
        if ($path === '') {
            return null;
        }

        return [
            'type' => [
                'library' => 'H5P.Image 1.1',
                'params' => [
                    'contentName' => 'Image',
                    'alt' => (string) ($activity->media_alt ?? ''),
                    'file' => [
                        'path' => $path,
                        'mime' => $this->mimeFor($path),
                        'copyright' => ['license' => 'U'],
                    ],
                ],
                'subContentId' => $this->subContentId('media', (int) $activity->id),
            ],
            'disableImageZooming' => false,
        ];
    }

    // -----------------------------------------------------------------------
    // Parse (import)
    // -----------------------------------------------------------------------

    /**
     * Read a params object back into row payloads for one activity.
     *
     * Nothing is written here -- the controller owns that, so an import stays
     * inside one transaction with its audit entries. Same contract as
     * H5PDragQuestionBuilder::parse().
     *
     * @param  array<string,mixed>  $params
     * @return array{content_type:string, activity: array<string,mixed>, blanks: list<array<string,mixed>>}
     */
    public function parse(array $params, string $contentType): array
    {
        if (! isset(self::LIBRARY_KEYS[$contentType])) {
            throw new RuntimeException('Unknown text activity type: ' . $contentType);
        }

        $behaviour = (array) ($params['behaviour'] ?? []);

        if ($contentType === 'fill_in_the_blanks') {
            $taskDescription = (string) ($params['text'] ?? '');
            // A package authored elsewhere may legitimately hold several
            // question strings. This product models one passage, so they are
            // joined rather than dropped -- the teacher then sees everything
            // that was in the package and can split it if they want to.
            $questions = array_values(array_filter(
                array_map('strval', (array) ($params['questions'] ?? [])),
                fn (string $q) => trim($q) !== ''
            ));
            $passage = implode("\n", $questions);
            $distractors = '';
        } else {
            $taskDescription = (string) ($params['taskDescription'] ?? '');
            $passage = (string) ($params['textField'] ?? '');
            $distractors = $contentType === 'drag_text' ? (string) ($params['distractors'] ?? '') : '';
        }

        $activity = [
            'content_type' => $contentType,
            'task_description' => $taskDescription,
            'passage' => $passage,
            'distractors' => $distractors,
            'media_image' => $this->mediaPathFrom($params),
            'media_alt' => (string) ($params['media']['type']['params']['alt'] ?? ''),
            'enable_retry' => (bool) ($behaviour['enableRetry'] ?? true),
            'enable_show_solution' => (bool) ($behaviour['enableSolutionsButton'] ?? true),
            'enable_check' => (bool) ($behaviour['enableCheckButton'] ?? true),
            'case_sensitive' => (bool) ($behaviour['caseSensitive'] ?? false),
            'accept_spelling_errors' => (bool) ($behaviour['acceptSpellingErrors'] ?? false),
            // DragText spells it instantFeedback, Blanks spells it autoCheck;
            // one column holds whichever the package used.
            'instant_feedback' => (bool) ($behaviour['instantFeedback'] ?? $behaviour['autoCheck'] ?? false),
            'show_score_points' => (bool) ($behaviour['showScorePoints'] ?? true),
            'separate_lines' => (bool) ($behaviour['separateLines'] ?? false),
            'solution_requires_input' => (bool) ($behaviour['showSolutionsRequiresInput'] ?? true),
            'feedback_bands' => $this->parseFeedbackBands((array) ($params['overallFeedback'] ?? [])),
            'pass_percentage' => $this->passFromFeedback((array) ($params['overallFeedback'] ?? [])),
        ];

        return [
            'content_type' => $contentType,
            'activity' => $activity,
            'blanks' => $this->parseAnswerKey($contentType, $passage, $distractors),
        ];
    }

    /** @return list<array<string,mixed>> */
    private function parseFeedbackBands(array $bands): array
    {
        $out = [];
        foreach ($bands as $band) {
            if (! is_array($band)) {
                continue;
            }
            $out[] = [
                'from' => max(0, min(100, (int) ($band['from'] ?? 0))),
                'to' => max(0, min(100, (int) ($band['to'] ?? 100))),
                'feedback' => (string) ($band['feedback'] ?? ''),
            ];
        }

        return $out;
    }

    /**
     * Infer a pass mark from the feedback bands.
     *
     * H5P has no explicit pass percentage on these three types -- the author
     * expresses it as the band where the message turns positive. The lowest
     * band that starts above 0 is the closest honest reading of that; a
     * package with one 0-100 band means "no pass mark", which is 100 here.
     */
    private function passFromFeedback(array $bands): int
    {
        $starts = [];
        foreach ($bands as $band) {
            $from = (int) ($band['from'] ?? 0);
            if ($from > 0) {
                $starts[] = $from;
            }
        }

        return $starts === [] ? 100 : max(0, min(100, min($starts)));
    }

    private function mediaPathFrom(array $params): ?string
    {
        $path = $params['media']['type']['params']['file']['path'] ?? null;

        return is_string($path) && $path !== '' ? $path : null;
    }

    // -----------------------------------------------------------------------
    // Shared helpers
    // -----------------------------------------------------------------------

    /** The official machine name for a content type. */
    public function machineName(string $contentType): string
    {
        return (string) config(
            'h5p_libraries.libraries.' . (self::LIBRARY_KEYS[$contentType] ?? '') . '.machine_name',
            'H5P.Blanks'
        );
    }

    /** config/h5p_libraries.php key for a content type. */
    public function libraryKey(string $contentType): string
    {
        if (! isset(self::LIBRARY_KEYS[$contentType])) {
            throw new RuntimeException('Unknown text activity type: ' . $contentType);
        }

        return self::LIBRARY_KEYS[$contentType];
    }

    /** "H5P.Blanks 1.14" -- what the `library` column records. */
    public function libraryVersionString(string $contentType): string
    {
        $library = (array) config('h5p_libraries.libraries.' . $this->libraryKey($contentType), []);

        return sprintf(
            '%s %d.%d',
            $library['machine_name'] ?? 'H5P.Blanks',
            $library['major_version'] ?? 1,
            $library['minor_version'] ?? 1
        );
    }

    /**
     * Wrap bare text in a paragraph.
     *
     * These fields are rich text in the H5P editor, so a host renders them as
     * HTML. Text this product stored as plain would otherwise lose its line
     * breaks on the way out.
     */
    private function html(?string $value): string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }

        return preg_match('/<[a-z][\s\S]*>/i', $value) ? $value : '<p>' . e($value) . '</p>';
    }

    /**
     * Keys an imported package carried that this schema does not model.
     *
     * Same contract as H5PDragQuestionBuilder: a round trip must not be lossy
     * just because a field was added to the library after this code was
     * written. Built keys always win.
     *
     * @param  array<string,mixed>  $params
     * @return array<string,mixed>
     */
    private function mergePreservedKeys(array $params, ?string $cachedJson): array
    {
        if (! is_string($cachedJson) || trim($cachedJson) === '') {
            return $params;
        }

        $cached = json_decode($cachedJson, true);
        if (! is_array($cached)) {
            return $params;
        }

        foreach ($cached as $key => $value) {
            if (! array_key_exists($key, $params)) {
                $params[$key] = $value;
            }
        }

        return $params;
    }

    /**
     * A stable sub-content id.
     *
     * H5P expects a UUID here. It must be stable across exports of the same
     * row, or every export looks like a different piece of content to a host
     * that tracks sub-content -- so it is derived from the row, not random.
     */
    private function subContentId(string $kind, int $id): string
    {
        $hash = md5('eduerp:h5p_text_activity:' . $kind . ':' . $id);

        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($hash, 0, 8),
            substr($hash, 8, 4),
            substr($hash, 12, 4),
            substr($hash, 16, 4),
            substr($hash, 20, 12)
        );
    }

    private function mimeFor(string $path): string
    {
        return match (strtolower((string) pathinfo(parse_url($path, PHP_URL_PATH) ?: $path, PATHINFO_EXTENSION))) {
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'svg' => 'image/svg+xml',
            default => 'image/jpeg',
        };
    }
}
