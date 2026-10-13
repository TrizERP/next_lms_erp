<?php

namespace App\Services\StudyDeck;

use App\Services\StudyDeck\Contracts\Completer;
use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * DEV-ONLY completer: shells out to the `claude` CLI in print mode.
 *
 * Exists because tenant 1 has no Anthropic API key (ai_api_keys holds only
 * gemini and openrouter rows). It runs with every tool disabled, so the model
 * can only return text - it cannot read the repository or touch the database.
 * Never selected unless claude.executor=cli or --executor=cli.
 */
class ClaudeCliCompleter implements Completer
{
    public function complete(string $system, string $prompt, int $maxTokens = 16000): string
    {
        $binary = (string) config('claude.cli.binary', 'claude');
        $command = [$binary, '-p', '--output-format', 'text', '--tools', '', '--system-prompt', $system];

        if ($model = (string) config('claude.cli.model', '')) {
            array_push($command, '--model', $model);
        }

        // A neutral working directory, so the CLI does not load this repository's
        // CLAUDE.md or settings into what is meant to be a plain text completion.
        $process = new Process($command, sys_get_temp_dir(), null, $prompt, (float) config('claude.cli.timeout_seconds', 900));
        $process->run();

        if (!$process->isSuccessful()) {
            throw new RuntimeException('Claude CLI failed: ' . trim($process->getErrorOutput() ?: $process->getOutput()));
        }

        return trim($process->getOutput());
    }
}
