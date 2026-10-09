<?php

namespace App\Services\QuestionGeneration\Formats;

use App\Services\QuestionGeneration\AbstractQuestionFormat;
use App\Services\QuestionGeneration\DragDrop\Contracts\DiagramImageFinder;
use App\Services\QuestionGeneration\DragDrop\Contracts\DiagramImageStore;
use App\Services\QuestionGeneration\DragDrop\Contracts\DiagramVisionAnalyzer;
use App\Services\QuestionGeneration\DragDrop\DragDropGeometry;
use App\Services\QuestionGeneration\DragDrop\DragDropRowProducer;
use App\Services\QuestionGeneration\DragDrop\GeminiDiagramVisionAnalyzer;
use App\Services\QuestionGeneration\DragDrop\OpenverseDiagramImageFinder;
use App\Services\QuestionGeneration\DragDrop\SpacesDiagramImageStore;
use App\Services\QuestionGeneration\FormatSchema;
use App\Services\QuestionGeneration\SourcesOwnRows;

/**
 * Image-based Drag & Drop: a real picture, labelled parts to place on it, and a
 * scored answer key.
 *
 * Not written by the text model. A language model cannot say where the nucleus is in a
 * picture, so this format sources its rows itself (see SourcesOwnRows and
 * DragDropRowProducer): find an openly licensed picture, ask a vision model where its
 * parts are, convert and validate those boxes, store the picture. Everything after that
 * -- validation, hashing, de-duplication, persistence -- is the ordinary path.
 *
 * Stored at answer.drag_drop in the same percent-of-image geometry the manual editor
 * keeps in h5p_drag_drop_zones. No native h5p_drag_drop rows are written: the bank
 * projects the payload to the player at read time, like every other generated format.
 */
class DragDropFormat extends AbstractQuestionFormat implements SourcesOwnRows
{
    // One batch: the producer, not a prompt size, decides how many pictures a request
    // may use (question_formats.formats.drag_drop.max_images).
    protected const BATCH_SIZE = 50;
    protected const TEMPERATURE = 0.0;

    /** @var (callable(array<string, mixed>): DragDropRowProducer)|null */
    private $producerFactory = null;

    public function code(): string
    {
        return 'drag_drop';
    }

    public function label(): string
    {
        return 'Drag and Drop';
    }

    public function fallbackQuestionTypeId(): int
    {
        return 2;
    }

    public function defaultMarks(): int
    {
        return 4;
    }

    /** One mark per labelled part, so the range is the zone range. */
    public function marksRange(): array
    {
        $limits = $this->geometry()->limits();

        return [(int) $limits['min_zones'], (int) $limits['max_zones']];
    }

    public function allowedBloomLevels(): array
    {
        return ['Remember', 'Understand', 'Apply'];
    }

    public function taskLabel(): string
    {
        return 'image drag-and-drop';
    }

    public function subTypeFor(string $level): string
    {
        return 'Drag and Drop';
    }

    public function constructionRules(int $total): string
    {
        return 'Rows for this format are not written by the text model: a picture is found and a vision model locates its parts.';
    }

    public function responseSchema(): string
    {
        return FormatSchema::build(
            $this->code(),
            [
                'sub_type' => ['const' => 'Drag and Drop'],
                'drag_drop' => ['type' => 'object'],
                'model_answer' => ['type' => 'string', 'minLength' => 5],
            ],
            ['sub_type', 'drag_drop', 'model_answer'],
            ['type' => 'integer', 'minimum' => 3, 'maximum' => 8],
            10,
            400
        );
    }

    // -----------------------------------------------------------------
    // Sourcing
    // -----------------------------------------------------------------

    /** Lets a test supply the producer's collaborators. */
    public function withProducerFactory(callable $factory): static
    {
        $clone = clone $this;
        $clone->producerFactory = $factory;

        return $clone;
    }

    public function sourceRows(array $batchQuota, array $slice, array $context): array
    {
        $producer = $this->producerFactory !== null
            ? ($this->producerFactory)($context)
            : $this->defaultProducer($context);

        return $producer->produce($batchQuota, $slice, $context);
    }

    /** @param array<string, mixed> $context */
    private function defaultProducer(array $context): DragDropRowProducer
    {
        // A binding for an interface wins (that is how tests and alternative providers
        // plug in); otherwise the estate's own implementations are used.
        return new DragDropRowProducer(
            app()->bound(DiagramImageFinder::class) ? app(DiagramImageFinder::class) : app(OpenverseDiagramImageFinder::class),
            app()->bound(DiagramImageStore::class) ? app(DiagramImageStore::class) : app(SpacesDiagramImageStore::class),
            app()->bound(DiagramVisionAnalyzer::class)
                ? app(DiagramVisionAnalyzer::class)
                : new GeminiDiagramVisionAnalyzer(null, $context['sub_institute_id'] ?? null),
            $this->geometry(),
            fn (array $ctx): string => $this->visionPrompt($ctx),
            (int) $this->setting('max_images', 3),
        );
    }

    /**
     * What the vision model is asked. It reports what is in the picture; it is not asked
     * to invent anything, and is told to say so when the picture will not do.
     *
     * @param array<string, mixed> $context
     */
    public function visionPrompt(array $context): string
    {
        $concept = (string) ($context['concept_name'] ?? 'the topic');
        $min = $this->geometry()->limits()['min_zones'];
        $max = $this->geometry()->limits()['max_zones'];

        return <<<PROMPT
You are preparing a label-the-diagram exercise for school students. The topic is: {$concept}.

Look at the image. List the distinct parts of the diagram or object that a student could be asked to name.

For each part give:
- "label": its name, in at most 4 words, as a student would write it. Only name what you can actually see and are sure of.
- "box_2d": [ymin, xmin, ymax, xmax] - a tight box around that part, as integers from 0 to 1000 where 0,0 is the top-left of the image and 1000,1000 the bottom-right.

Rules:
- Give between {$min} and {$max} parts. If fewer than {$min} parts are clearly visible and nameable, give what you can and set "usable" to false.
- Boxes must not overlap each other much, and must each sit on a different part.
- Skip any part you cannot box confidently. Fewer, correct parts are better than more, doubtful ones.
- Set "has_printed_labels" to true if the image already shows the names of its parts as text, because then the exercise would give its own answers away.
- Set "usable" to false if the image is a photograph of people, a scene with no distinct labelled parts, a chart of data, or is not about the topic.
- "alt": a short description of the image, at most 12 words, that does not name the parts.

Reply with JSON only, in this shape:
{"usable": true, "has_printed_labels": false, "alt": "...", "parts": [{"label": "...", "box_2d": [0, 0, 0, 0]}]}
PROMPT;
    }

    private function geometry(): DragDropGeometry
    {
        $overrides = [];
        foreach (array_keys(DragDropGeometry::DEFAULTS) as $key) {
            $value = $this->setting($key, null);
            if ($value !== null) {
                $overrides[$key] = $value;
            }
        }

        return new DragDropGeometry($overrides);
    }

    // -----------------------------------------------------------------
    // Validation and preparation
    // -----------------------------------------------------------------

    public function validateRow(array $row): ?string
    {
        $ans = $this->answer($row);

        if (($ans['sub_type'] ?? null) !== 'Drag and Drop') {
            return 'sub_type must be "Drag and Drop"';
        }
        if (!is_array($ans['drag_drop'] ?? null)) {
            return 'answer.drag_drop is missing';
        }
        $problem = $this->geometry()->reason($ans['drag_drop']);
        if ($problem !== null) {
            return $problem;
        }
        if ($row['points'] !== count($ans['drag_drop']['zones'])) {
            return 'points must equal the number of zones';
        }
        if (mb_strlen($this->text($ans['model_answer'] ?? '')) < 5) {
            return 'model_answer is required';
        }
        if (mb_strlen($this->text($ans['explanation'] ?? '')) < 30) {
            return 'explanation is required (at least 30 characters)';
        }
        if (empty($ans['knowledge_refs']) || !is_array($ans['knowledge_refs'])) {
            return 'knowledge_refs required';
        }

        return null;
    }

    public function prepareRow(array $row): array
    {
        $ans = $this->answer($row);
        $ans['sub_type'] = 'Drag and Drop';
        $row['answer'] = $ans;

        return $row;
    }
}
