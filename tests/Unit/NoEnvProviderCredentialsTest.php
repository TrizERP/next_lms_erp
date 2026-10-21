<?php

namespace Tests\Unit;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\TestCase;

/**
 * No application code reads an AI provider credential from the environment.
 *
 * Provider keys live in `ai_api_keys` and are resolved by AI Providers. This scans the whole `app/`
 * tree so a new direct `env('..._API_KEY')` / `getAIKey()` / provider-key `config()` read cannot be
 * added without this failing. A source scan, not a behaviour test — it proves the reads are gone,
 * not that every provider call works.
 *
 * Allowed, and why:
 *  - `OpenRouterClient` and `GeminiClient` hand an env value to `ProviderKeyResolver`, which ignores
 *    it unless `ai.allow_env_credentials` is on (off by default and tested elsewhere).
 *  - `ClaudeQuestionClient` does the same for Anthropic, which has no AI Providers driver yet.
 *  - `ProviderCatalog` exposes the env value only to that same resolver.
 *  - The import command exists to read env files in order to move their keys into the database.
 */
class NoEnvProviderCredentialsTest extends TestCase
{
    private const ALLOWED = [
        'Domain/AI/Support/OpenRouterClient.php',
        'Domain/AI/Support/GeminiClient.php',
        'Domain/AI/Support/OpenAiCompatibleClient.php',
        'Domain/AI/Configuration/ProviderCatalog.php',
        'Services/QuestionGeneration/H5p/ClaudeQuestionClient.php',
        'Console/Commands/AiImportEnvKeysCommand.php',
        'Helpers/Helper.php',
    ];

    public function test_no_provider_key_is_read_from_the_environment_in_app_code(): void
    {
        $offenders = [];
        $root = base_path('app');
        $pattern = '/(env|getenv)\(\s*[\'"][A-Z_]*(GEMINI|OPENROUTER|OPENAI|DEEPSEEK|ANTHROPIC|GROQ|MISTRAL)[A-Z_]*(KEY|TOKEN)[A-Z_]*[\'"]'
            . '|config\(\s*[\'"](gemini|openrouter|deepseek|claude|idms)\.[a-z_.]*api_key[\'"]'
            . '|config\(\s*[\'"]ai\.provider\.[a-z]+\.api_key[\'"]/';

        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS));

        foreach ($files as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));

            if (in_array($relative, self::ALLOWED, true)) {
                continue;
            }

            foreach (file($file->getPathname()) as $n => $line) {
                $trimmed = ltrim($line);

                // A comment explaining what was removed is fine.
                if (str_starts_with($trimmed, '*') || str_starts_with($trimmed, '//') || str_starts_with($trimmed, '/*')) {
                    continue;
                }

                if (preg_match($pattern, $line)) {
                    $offenders[] = $relative . ':' . ($n + 1);
                }
            }
        }

        $this->assertSame([], $offenders, "Provider credentials must come from AI Providers, not the environment:\n" . implode("\n", $offenders));
    }

    public function test_the_environment_credential_switch_is_off_by_default(): void
    {
        $this->assertFalse((bool) config('ai.allow_env_credentials'));
    }
}
