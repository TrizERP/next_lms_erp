<?php

namespace App\Services\QuestionGeneration\DragDrop;

use App\Domain\AI\Support\GeminiClient;
use App\Domain\AI\Support\SupportsVisionAnalysis;
use App\Services\QuestionGeneration\DragDrop\Contracts\DiagramVisionAnalyzer;

/**
 * The vision step, through the estate's Gemini client.
 *
 * Any client implementing SupportsVisionAnalysis would do; Gemini is the one that does.
 * When the configured client cannot read images this reports that plainly instead of
 * guessing, and the format produces nothing.
 */
class GeminiDiagramVisionAnalyzer implements DiagramVisionAnalyzer
{
    public function __construct(private readonly ?SupportsVisionAnalysis $client = null, private readonly int|string|null $subInstituteId = null)
    {
    }

    public function analyze(string $prompt, string $imageBytes, string $mime): array
    {
        $client = $this->client ?? app(GeminiClient::class)->forInstitute($this->subInstituteId);
        if (!$client instanceof SupportsVisionAnalysis) {
            return ['ok' => false, 'error' => 'the configured AI provider cannot read images'];
        }

        try {
            $text = $client->analyzeImage(
                $prompt,
                $imageBytes,
                $mime,
                config('question_formats.formats.drag_drop.vision_model'),
                (int) config('question_formats.formats.drag_drop.vision_timeout', 60),
            );
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }

        $data = is_string($text) ? json_decode(trim($text), true) : null;
        if (!is_array($data)) {
            return ['ok' => false, 'error' => 'the vision model did not return JSON'];
        }

        return [
            'ok'                 => true,
            'usable'             => (bool) ($data['usable'] ?? false),
            'has_printed_labels' => (bool) ($data['has_printed_labels'] ?? false),
            'alt'                => is_string($data['alt'] ?? null) ? $data['alt'] : null,
            'parts'              => is_array($data['parts'] ?? null) ? $data['parts'] : [],
        ];
    }
}
