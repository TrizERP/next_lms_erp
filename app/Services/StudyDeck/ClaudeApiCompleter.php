<?php

namespace App\Services\StudyDeck;

use App\Services\StudyDeck\Contracts\Completer;
use Closure;

/**
 * Production completer. It owns no Anthropic code of its own: the closure it is
 * given is ContentGenerationService::callClaude, so key resolution, model,
 * effort, streaming and refusal handling stay in exactly one place.
 */
class ClaudeApiCompleter implements Completer
{
    /** @param Closure(string $system, string $prompt, int $maxTokens): array{text:string} $call */
    public function __construct(private readonly Closure $call)
    {
    }

    public function complete(string $system, string $prompt, int $maxTokens = 16000): string
    {
        return (string) (($this->call)($system, $prompt, $maxTokens)['text'] ?? '');
    }
}
