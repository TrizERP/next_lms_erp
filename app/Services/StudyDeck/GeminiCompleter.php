<?php

namespace App\Services\StudyDeck;

use App\Domain\AI\Support\FallbackChainModelClient;
use App\Services\StudyDeck\Contracts\Completer;
use RuntimeException;

/**
 * The branching-scenario / study-deck Completer, answered by a configured provider client (Gemini) instead of the
 * Claude closure. It owns no provider code: key, model and retry behaviour stay in the central AI configuration and
 * the provider client; this only turns one (system, prompt) pair into one text answer.
 */
class GeminiCompleter implements Completer
{
    public function __construct(
        private readonly FallbackChainModelClient $client,
        private readonly int $timeoutSeconds = 300,
    ) {
    }

    public function complete(string $system, string $prompt, int $maxTokens = 16000): string
    {
        $text = $this->client->chat(
            [
                ['role' => 'system', 'content' => $system],
                ['role' => 'user', 'content' => $prompt],
            ],
            null,
            $maxTokens,
            null,
            false,
            $this->timeoutSeconds
        );

        if (! is_string($text) || trim($text) === '') {
            throw new RuntimeException('Gemini returned an empty answer.');
        }

        return $text;
    }
}
