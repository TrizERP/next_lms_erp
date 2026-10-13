<?php

namespace App\Services\QuestionGeneration;

use App\Services\QuestionGeneration\Formats\AssertionReasonFormat;
use App\Services\QuestionGeneration\Formats\CaseStudyFormat;
use App\Services\QuestionGeneration\Formats\ConstructionFormat;
use App\Services\QuestionGeneration\Formats\DragDropFormat;
use App\Services\QuestionGeneration\Formats\DragTextFormat;
use App\Services\QuestionGeneration\Formats\FillBlankFormat;
use App\Services\QuestionGeneration\Formats\LongFormat;
use App\Services\QuestionGeneration\Formats\MatchFollowingFormat;
use App\Services\QuestionGeneration\Formats\MarkTheWordsFormat;
use App\Services\QuestionGeneration\Formats\McqFormat;
use App\Services\QuestionGeneration\Formats\NarrativeLegacyFormat;
use App\Services\QuestionGeneration\Formats\NumericalFormat;
use App\Services\QuestionGeneration\Formats\ProofFormat;
use App\Services\QuestionGeneration\Formats\ShortFormat;
use App\Services\QuestionGeneration\Formats\TrueFalseFormat;
use App\Services\QuestionGeneration\Formats\VeryShortFormat;

/**
 * The formats the generator can write, and the join between them and the
 * `question_type_catalog` that names them.
 *
 * TWO SOURCES, ONE ANSWER. The catalogue (database) decides which forms exist,
 * what they are called and which coarse type each belongs to. The registry
 * (code) decides which of them the generator knows how to WRITE -- a prompt
 * cannot live in a catalogue row. A format is offered to a teacher only when
 * both agree, which is what generatable() returns.
 *
 * `narrative` is registered for the legacy `question_type` alias but is not a
 * catalogue form: it is never listed and cannot be requested by code.
 */
class QuestionFormatRegistry
{
    /**
     * Implemented formats, in the order they are offered.
     *
     * @var list<class-string<QuestionFormat>>
     */
    public const FORMATS = [
        McqFormat::class,
        TrueFalseFormat::class,
        FillBlankFormat::class,
        DragTextFormat::class,
        MarkTheWordsFormat::class,
        MatchFollowingFormat::class,
        AssertionReasonFormat::class,
        NumericalFormat::class,
        VeryShortFormat::class,
        ShortFormat::class,
        LongFormat::class,
        CaseStudyFormat::class,
        ProofFormat::class,
        ConstructionFormat::class,
        DragDropFormat::class,
        // The legacy `question_type: narrative` alias. Last, and never listed.
        NarrativeLegacyFormat::class,
    ];

    /** The most questions one request may ask for; mirrors the modal's own limit. */
    public const MAX_QUESTIONS = 50;

    /** @var array<string, QuestionFormat>|null */
    private ?array $formats = null;

    public function __construct(private ?QuestionTypeCatalogue $catalogue = null)
    {
    }

    public function catalogue(): QuestionTypeCatalogue
    {
        return $this->catalogue ??= new QuestionTypeCatalogue();
    }

    /** @return array<string, QuestionFormat> every implemented format, legacy aliases included */
    public function all(): array
    {
        if ($this->formats === null) {
            $this->formats = [];
            foreach (static::FORMATS as $class) {
                $format = new $class();
                $this->formats[$format->code()] = $format;
            }
        }

        return $this->formats;
    }

    public function get(string $code): ?QuestionFormat
    {
        return $this->all()[$code] ?? null;
    }

    /**
     * The format a request asks for.
     *
     * `question_format_code` wins when present and must name a real, non-legacy
     * format that the catalogue also knows. Otherwise the legacy `question_type`
     * alias maps `mcq` and `narrative` onto their formats. Returns null when the
     * request names nothing the generator can write; $error then says why.
     */
    public function resolveRequest(array $input, ?string &$error = null): ?QuestionFormat
    {
        $code = strtolower(trim((string) ($input['question_format_code'] ?? '')));

        if ($code !== '') {
            $format = $this->get($code);

            if ($format === null || $format->isLegacy() && $format->persistedFormatCode() === null) {
                $error = "Question format \"{$code}\" is not supported for generation.";

                return null;
            }

            // Same rule generatable() applies: implemented AND catalogued. A code the
            // catalogue does not know has no lms_question_type_id to resolve, and
            // the client's own value is never trusted to fill that gap.
            if ($this->catalogue()->find($format->persistedFormatCode() ?? $code) === null) {
                $error = "Question format \"{$code}\" is not in the question type catalogue.";

                return null;
            }

            return $format;
        }

        $legacy = strtolower(trim((string) ($input['question_type'] ?? '')));
        $format = in_array($legacy, ['mcq', 'narrative'], true) ? $this->get($legacy) : null;

        if ($format === null) {
            $error = $legacy === ''
                ? 'Provide question_format_code (or the legacy question_type of "mcq" or "narrative").'
                : 'question_type must be "mcq" or "narrative".';
        }

        return $format;
    }

    /**
     * Resolve several requested format codes at once, each through the SAME rules a
     * single `question_format_code` goes through (implemented AND catalogued, and
     * never the legacy narrative alias).
     *
     * All-or-nothing: if any code is refused, no formats are returned and $errors
     * lists every refusal, so a bad selection is rejected before a single model call.
     * Duplicates collapse. The result is in REGISTRY order, not request order, so the
     * same set of formats always resolves -- and therefore distributes -- the same way
     * whatever order the teacher clicked them in.
     *
     * @param  list<mixed>  $codes
     * @param  list<string>|null  $errors
     * @return list<QuestionFormat>
     */
    public function resolveMany(array $codes, ?array &$errors = null): array
    {
        $errors = [];
        $wanted = [];

        foreach ($codes as $code) {
            $code = strtolower(trim((string) $code));
            if ($code === '') {
                $errors[] = 'A question format code is empty.';
                continue;
            }
            $wanted[$code] = true;
        }

        if ($wanted === [] && $errors === []) {
            $errors[] = 'Select at least one question format.';
        }

        $resolved = [];
        foreach (array_keys($wanted) as $code) {
            $error = null;
            $format = $this->resolveRequest(['question_format_code' => $code], $error);

            if ($format === null) {
                $errors[] = $error ?? "Question format \"{$code}\" is not supported for generation.";
                continue;
            }
            $resolved[$format->code()] = $format;
        }

        if ($errors !== []) {
            return [];
        }

        // Registry order, so the split does not depend on click order.
        return array_values(array_filter(
            $this->all(),
            fn (QuestionFormat $format) => isset($resolved[$format->code()])
        ));
    }

    /**
     * Split $total questions across the formats, as evenly as whole numbers allow.
     *
     * Every format gets floor(total / K); the remainder goes one apiece to the first
     * formats in the order given (registry order from resolveMany()). Pure and
     * deterministic: 15 over 3 is 5/5/5, 20 over 3 is 7/7/6.
     *
     * @param  list<QuestionFormat>  $formats
     * @return array<string, int> format code => question count
     */
    public function distribute(int $total, array $formats): array
    {
        $count = count($formats);
        if ($count === 0 || $total < 1) {
            return [];
        }

        $base = intdiv($total, $count);
        $extra = $total % $count;
        $split = [];

        foreach (array_values($formats) as $i => $format) {
            $split[$format->code()] = $base + ($i < $extra ? 1 : 0);
        }

        return $split;
    }

    /** The lms_question_type_id to store: the catalogue's, else the format's own fallback. */
    public function questionTypeIdFor(QuestionFormat $format): int
    {
        $code = $format->persistedFormatCode();
        $entry = $code !== null ? $this->catalogue()->find($code) : null;

        return (int) ($entry['lms_question_type_id'] ?? $format->fallbackQuestionTypeId());
    }

    /**
     * Marks for an item of this format when the request does not set them: the
     * catalogue's default_marks if it has one inside the format's range, else the
     * format's own fallback (Case Study's catalogue row is NULL, so it uses 4).
     */
    public function defaultMarksFor(QuestionFormat $format): int
    {
        $code = $format->persistedFormatCode();
        $catalogued = $code !== null ? ($this->catalogue()->find($code)['default_marks'] ?? null) : null;
        [$min, $max] = $format->marksRange();

        if ($catalogued !== null && $catalogued >= $min && $catalogued <= $max) {
            return (int) $catalogued;
        }

        return $format->defaultMarks();
    }

    /**
     * The formats a teacher can pick: implemented AND present in the catalogue.
     *
     * @return list<array<string, mixed>>
     */
    public function generatable(): array
    {
        $out = [];

        foreach ($this->all() as $format) {
            if ($format->persistedFormatCode() === null) {
                continue;
            }

            $entry = $this->catalogue()->find($format->persistedFormatCode());
            if ($entry === null) {
                continue;
            }

            [$minMarks, $maxMarks] = $format->marksRange();

            $out[] = [
                'code' => $format->persistedFormatCode(),
                'label' => $entry['label'] !== '' ? $entry['label'] : $format->label(),
                'lms_question_type_id' => $this->questionTypeIdFor($format),
                'default_marks' => $this->defaultMarksFor($format),
                'marks_editable' => $minMarks !== $maxMarks,
                'min_marks' => $minMarks,
                'max_marks' => $maxMarks,
                'allowed_bloom_levels' => $format->allowedBloomLevels(),
                'batch_size' => $format->batchSize(),
                'max_questions' => self::MAX_QUESTIONS,
                'prompt_version' => $format->promptVersion(),
                'is_standard' => (bool) $entry['is_standard'],
            ];
        }

        return $out;
    }
}
