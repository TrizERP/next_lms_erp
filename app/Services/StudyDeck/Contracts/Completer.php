<?php

namespace App\Services\StudyDeck\Contracts;

/**
 * One model call, text in and text out.
 *
 * The study-deck pipeline makes several calls (plan, then slide content in
 * chunks) and must not care whether they go to the Anthropic API - the
 * production path, via ContentGenerationService - or to the dev-only Claude CLI
 * used when a tenant has no API key.
 */
interface Completer
{
    public function complete(string $system, string $prompt, int $maxTokens = 16000): string;
}
