<?php

namespace App\Services\StudyDeck\Story;

use App\Domain\AI\Support\GeminiClient;
use App\Services\StudyDeck\ExtractsJson;

/**
 * Looks at a candidate photograph and says whether it really shows what a story scene needs.
 *
 * The picture search ranks by title and tags, and the existing relevance judge reads the same text, so a wrong picture with a plausible
 * title gets through (a coat of arms for a vernier caliper, a speech bubble for a balloon). This reads the pixels with the estate's own
 * Gemini client (the same key and model configuration as the story itself; no other provider, no image generation) and answers
 * strictly: a picture is kept only when it plainly shows the subject in a way that helps a 14 to 15 year old understand the scene.
 *
 * Fails closed: a reply that cannot be read, or a call that fails, rejects the picture. A scene without a picture is acceptable; a wrong
 * picture is not.
 */
class StoryPixelJudge
{
    use ExtractsJson;

    /** Marks a purpose as "check an ILLUSTRATION made for this scene" (an image model's picture), not a found photograph. */
    public const AI_PREFIX = 'AI ILLUSTRATION: ';

    /** When set, a picture check never waits longer than the time left before this moment (a unix time). */
    public ?float $deadline = null;

    public function __construct(private readonly GeminiClient $client)
    {
    }

    /** Seconds the check waits and then asks once more when Gemini answers a per-minute rate limit (never past the deadline). */
    public int $rateLimitWait = 15;

    /** Why verification cannot run (a spent quota or billing limit), or null. Once set, no further call is made. */
    public ?string $unavailable = null;

    /** The longest one request may take. A busy model answers slowly, and a few such answers must not use up a search window. */
    public int $callSeconds = 15;

    /** Busy answers (5xx) in a row; at two the check is switched off for the rest of the pass instead of being asked again. */
    private int $busyStreak = 0;

    /** One tiny call to learn whether the picture check can run at all, before a search window is spent. @return array{available:bool,reason:string} */
    public function probe(): array
    {
        if ($this->unavailable !== null) {
            return ['available' => false, 'reason' => $this->unavailable];
        }
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==');
        try {
            $this->ask('Reply with the single word OK.', (string) $png, 'image/png');
        } catch (\Throwable $e) {
            $this->note($e);

            return ['available' => $this->unavailable === null, 'reason' => $this->unavailable ?? ('a busy moment, not a spent quota: ' . mb_substr($e->getMessage(), 0, 80))];
        }

        return ['available' => true, 'reason' => 'ok'];
    }

    /** A spent quota or billing limit (a 429 that is not the per-minute kind) switches the check off; it is never retried. */
    private function note(\Throwable $e): void
    {
        if ($e instanceof \App\Domain\AI\Support\AiProviderException && $e->status === 429 && $e->quotaMetric !== null && !str_contains($e->quotaMetric, 'PerMinute')) {
            $this->unavailable = 'the AI quota or billing limit is used up';
        }
    }

    private function ask(string $prompt, string $bytes, string $mime): ?string
    {
        $left = fn (): int => $this->deadline === null ? $this->callSeconds : (int) max(5, min($this->callSeconds, floor($this->deadline - microtime(true))));
        try {
            return $this->client->analyzeImage($prompt, $bytes, $mime, null, $left());
        } catch (\App\Domain\AI\Support\AiProviderException $e) {
            // A rate limit that clears by itself (not a per-day quota) is waited out once, if there is time for it.
            $perDay = $e->quotaMetric !== null && str_contains(strtolower($e->quotaMetric), 'perday');
            $wait = max(1, min($e->retryAfter ?? $this->rateLimitWait, $this->rateLimitWait));
            if ($e->status !== 429 || $perDay || ($this->deadline !== null && microtime(true) + $wait + 10 >= $this->deadline)) {
                throw $e;
            }
            sleep($wait);

            return $this->client->analyzeImage($prompt, $bytes, $mime, null, $left());
        }
    }

    /** @return array{ok:bool,shows:string,reason:string} */
    public function __invoke(string $bytes, string $mime, string $query, string $purpose, string $teaches): array
    {
        $mode = str_starts_with($purpose, self::AI_PREFIX) ? 'ai' : (str_starts_with($purpose, \App\Services\StudyDeck\ImagePlanner::RELATED_PREFIX) ? 'related' : 'exact');
        $purpose = preg_replace('/^(' . preg_quote(self::AI_PREFIX, '/') . '|' . preg_quote(\App\Services\StudyDeck\ImagePlanner::RELATED_PREFIX, '/') . ')/', '', $purpose);
        $rules = match ($mode) {
            'ai' => "Look at the picture itself and answer strictly. This is an ILLUSTRATION made by an image model for this scene (not a photograph). Reject it unless ALL of these are true:\n- it plainly depicts the situation described (the people, apparatus and setting the scene names, doing what the scene says);\n- it would help a student picture this scene (clear and uncluttered; no garbled or misspelt writing, no distorted hands or faces, no unrelated objects);\n- it contains nothing unsuitable for school.",
            'related' => "Look at the picture itself and answer strictly. No picture of the exact scene was found, so a RELATED picture is acceptable. Reject it unless ALL of these are true:\n- it plainly shows the same kind of object, instrument, activity, phenomenon or environment as the subject named above (it need not be this exact scene);\n- it would help a student understand the idea behind this scene (a clear, ordinary, realistic view; not a logo, coat of arms, cartoon, map, screenshot, text-only page, collage or decorative texture);\n- it contains no watermark across the subject and nothing unsuitable for school.",
            default => "Look at the picture itself and answer strictly. Reject it unless ALL of these are true:\n- it plainly shows the subject named above (the real thing, clearly visible and recognisable, not only something with a similar name);\n- it would help a student understand this scene (a clear, ordinary, realistic view; not a logo, coat of arms, cartoon, map, screenshot, text-only page, collage or decorative texture);\n- it contains no watermark across the subject and nothing unsuitable for school.",
        };
        $what = $mode === 'ai' ? 'an illustration' : 'a photograph';
        $prompt = <<<PROMPT
You are checking {$what} for a school science lesson (students aged 14 to 15).

The scene needs a picture of: "{$query}"
The scene is about: {$purpose}
It helps teach: {$teaches}

{$rules}

Also point out WHERE in the picture the thing that matters is: a rectangle around it as percentages of the picture's width and height (x and y are the top-left corner, w and h the size; all between 0 and 100), tight enough to be useful and not the whole picture unless nothing smaller makes sense. Name that part in at most 3 words (for example "Leaf veins"), and say in one short sentence what a student should notice there.

Reply with JSON and nothing else:
{"matches": true or false, "shows": "one plain sentence on what the picture actually shows (no more than 25 words)", "reason": "one sentence on why it fits or why it does not", "focus": {"x": 0, "y": 0, "w": 100, "h": 100}, "label": "name of that part, at most 3 words", "notice": "what a student should notice, at most 20 words"}
PROMPT;

        if ($this->unavailable !== null) {
            return ['ok' => false, 'unverified' => true, 'shows' => '', 'reason' => $this->unavailable . '; the picture was not verified and is not approved'];
        }
        try {
            $reply = $this->ask($prompt, $bytes, $mime);
            $this->busyStreak = 0;
            $data = is_string($reply) && trim($reply) !== '' ? $this->extractJson($reply) : [];
        } catch (\Throwable $e) {
            $this->note($e);
            if ($e instanceof \App\Domain\AI\Support\AiProviderException && $e->status >= 500 && ++$this->busyStreak >= 2) {
                $this->unavailable = 'the AI model is busy and did not answer twice in a row';
            }
            if ($this->unavailable !== null) {
                return ['ok' => false, 'unverified' => true, 'shows' => '', 'reason' => $this->unavailable . '; the picture was not verified and is not approved'];
            }

            return ['ok' => false, 'shows' => '', 'reason' => 'the picture could not be checked (' . mb_substr($e->getMessage(), 0, 80) . ')'];
        }
        $shows = trim((string) preg_replace('/\s+/u', ' ', (string) ($data['shows'] ?? '')));

        $notice = trim((string) preg_replace('/\s+/u', ' ', (string) ($data['notice'] ?? '')));
        $label = trim((string) preg_replace('/\s+/u', ' ', (string) ($data['label'] ?? '')));
        $focus = self::region($data['focus'] ?? null);

        return [
            'ok' => ($data['matches'] ?? false) === true && $shows !== '',
            'shows' => $shows,
            'reason' => trim((string) preg_replace('/\s+/u', ' ', (string) ($data['reason'] ?? ''))),
            ...($focus !== null ? ['focus' => $focus] : []),
            ...($notice !== '' && mb_strlen($notice) <= 200 ? ['notice' => $notice] : []),
            ...($focus !== null && $label !== '' && mb_strlen($label) <= 30 && !preg_match('/\d/', $label) ? ['label' => $label] : []),
        ];
    }

    /**
     * A region of the picture as the player draws it: percentages of its width and height. Anything that is not a sensible rectangle inside the
     * picture, or that is the whole picture (nothing to point at), is dropped.
     *
     * @return array{x:float,y:float,w:float,h:float}|null
     */
    public static function region(mixed $raw): ?array
    {
        if (!is_array($raw)) {
            return null;
        }
        foreach (['x', 'y', 'w', 'h'] as $k) {
            if (!isset($raw[$k]) || !is_numeric($raw[$k])) {
                return null;
            }
        }
        [$x, $y, $w, $h] = [(float) $raw['x'], (float) $raw['y'], (float) $raw['w'], (float) $raw['h']];
        if ($x < 0 || $y < 0 || $w < 8 || $h < 8 || $x + $w > 100.5 || $y + $h > 100.5 || ($w > 92 && $h > 92)) {
            return null;
        }

        return ['x' => round($x, 1), 'y' => round($y, 1), 'w' => round(min($w, 100 - $x), 1), 'h' => round(min($h, 100 - $y), 1)];
    }
}
