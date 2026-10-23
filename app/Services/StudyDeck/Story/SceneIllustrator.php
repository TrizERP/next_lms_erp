<?php

namespace App\Services\StudyDeck\Story;

use App\Domain\AI\Support\ImageGenerationResult;

/**
 * The optional "AI-illustrated scene" rung of the picture ladder (lms:generate-study-deck --ai-scene-art).
 *
 * It asks the estate's own Gemini client (GeminiClient::generateImage, the same key pool and error handling as every other call) for one picture
 * of the scene, written from the scene's own text. It only makes the picture: whether it is kept is decided by the picture check, and what it is
 * called is decided by ImagePlanner::generated(), which labels it an AI-generated illustration and never a photograph.
 *
 * It stops itself: after a spent quota, a rejected key or a billing limit it is unavailable for the rest of the run, and it never makes more than
 * `$max` pictures, so a large story cannot run up an unbounded bill.
 */
final class SceneIllustrator
{
    /** @var \Closure(string):ImageGenerationResult */
    private \Closure $generate;

    public int $made = 0;

    public int $failed = 0;

    /** Why it is switched off, once it is. */
    public ?string $unavailable = null;

    /** The last failure, for the report: its kind, status and the start of its message (never a key). */
    public ?string $lastError = null;

    private int $failedInARow = 0;

    /**
     * @param \Closure(string):ImageGenerationResult|null $generate how a picture is made; the Gemini client by default
     * @param int|string|null $tenant the school the run is for, so the image model uses the same key the rest of the run does
     */
    public function __construct(?\Closure $generate = null, private readonly int $max = 60, public readonly string $model = 'gemini image model', int $timeout = 90, int|string|null $tenant = null)
    {
        $this->generate = $generate ?? function (string $prompt) use ($timeout, $tenant): ImageGenerationResult {
            return app(\App\Domain\AI\Support\GeminiClient::class)->forInstitute($tenant)->generateImage($prompt, null, $timeout);
        };
    }

    public function available(): bool
    {
        return $this->unavailable === null && $this->made < $this->max;
    }

    /** The request, from the scene's own words. No writing is asked for: a model draws letters badly, and the caption says what to notice. */
    public static function prompt(string $title, string $body, array $concepts): string
    {
        $scene = trim((string) preg_replace('/\s+/u', ' ', $body));
        if (mb_strlen($scene) > 700) {
            $scene = mb_substr($scene, 0, 700);
        }

        return "Create one wide (16:9) educational illustration for a school science story for students aged 14 to 15, set in an Indian school and neighbourhood.\n"
            . "Scene title: {$title}\n"
            . "What happens in the scene: {$scene}\n"
            . ($concepts !== [] ? 'The idea this scene helps teach: ' . implode('; ', array_slice($concepts, 0, 3)) . "\n" : '')
            . "Style: a realistic, bright, natural-light scene like a high-quality textbook photograph, with a clear composition showing the students and the apparatus or setting the scene names, doing what the scene says.\n"
            . 'Strict rules: no written words, letters, numbers or logos anywhere in the picture; no watermark; nothing violent or unsuitable for school; show the real objects the scene names, not a diagram.';
    }

    /**
     * One picture for a scene, or null (and the reason in `$this->unavailable` when it is the kind of failure that will repeat).
     *
     * @return array{bytes:string,mime:string,caption:?string,model:string,prompt:string}|null
     */
    public function illustrate(string $prompt): ?array
    {
        if (!$this->available()) {
            return null;
        }
        try {
            $result = ($this->generate)($prompt);
        } catch (\Throwable $e) {
            $this->failed++;
            $message = $e->getMessage();
            $this->lastError = class_basename($e) . (isset($e->status) ? ' status ' . $e->status : '') . ': ' . mb_substr((string) preg_replace('/AIza[0-9A-Za-z_\-]{20,}/', '[key]', preg_replace('/\s+/', ' ', $message)), 0, 160);
            // A failure that will not clear by waiting switches the rung off for the run; a busy moment only costs this scene.
            if ($e instanceof \App\Domain\AI\Support\AiProviderException && (in_array($e->status, [401, 403], true) || ($e->status === 429 && $e->quotaMetric !== null && !str_contains($e->quotaMetric, 'PerMinute')))) {
                $this->unavailable = 'the image model refused the request (' . mb_substr($message, 0, 90) . ')';
            } elseif (str_contains($message, 'No usable AI API key')) {
                $this->unavailable = 'no AI API key is configured';
            } elseif (++$this->failedInARow >= 3) {
                $this->unavailable = 'the image model failed three times in a row (' . ($this->lastError ?? 'no detail') . ')';
            }

            return null;
        }
        $this->failedInARow = 0;
        $bytes = $result->toBinary();
        if ($bytes === '') {
            $this->failed++;

            return null;
        }
        $this->made++;

        return ['bytes' => $bytes, 'mime' => $result->mimeType, 'caption' => $result->caption, 'model' => $this->model, 'prompt' => $prompt];
    }
}
