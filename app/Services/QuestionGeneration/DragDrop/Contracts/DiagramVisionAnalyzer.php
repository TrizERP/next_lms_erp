<?php

namespace App\Services\QuestionGeneration\DragDrop\Contracts;

/** Looks at a picture and says where its parts are. */
interface DiagramVisionAnalyzer
{
    /**
     * @return array{
     *     ok: bool,
     *     error?: string,
     *     usable?: bool,
     *     has_printed_labels?: bool,
     *     alt?: string|null,
     *     parts?: array<int, array{label: mixed, box_2d: mixed}>
     * } box_2d is [ymin, xmin, ymax, xmax] on a 0-1000 scale of the image.
     */
    public function analyze(string $prompt, string $imageBytes, string $mime): array;
}
