<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Contract test: the assessment_ai / G2G call sites go through AiModelClientFactory and read
 * no provider credential, model or base URL from config/env.
 *
 * Source-level on purpose. AiModelClientFactory and FallbackChainModelClient are final and the
 * chain records a usage row per attempt, so a behavioural test would write to the shared DB.
 */
class AssessmentAiCentralisedConfigTest extends TestCase
{
    private const FILES = [
        'app/Services/QuestionGenerationService.php' => 'assessment_ai',
        'app/Services/G2gLms/DeepSeekAssessmentService.php' => 'assessment_ai',
        'app/Http/Controllers/G2gLms/AiCourseController.php' => 'content_generation',
    ];

    public function test_call_sites_use_the_factory_with_their_module_key(): void
    {
        foreach (self::FILES as $path => $module) {
            $src = $this->source($path);
            $this->assertStringContainsString('AiModelClientFactory', $src, $path);
            $this->assertStringContainsString("'{$module}'", $src, $path);
        }
    }

    public function test_no_provider_credential_model_or_base_url_is_read_directly(): void
    {
        $forbidden = [
            "config('deepseek.api_key'", "config('deepseek.model'", "config('deepseek.base_url'",
            "config('deepseek.api_type'", "config('gemini.", "config('openrouter.",
            "config('ai.provider.", 'getAIKey(', "env('DEEPSEEK", "env('GEMINI", "env('OPENROUTER",
            "env('OPENAI", 'getenv(', 'ProviderKeyResolver', '/chat/completions', 'generativelanguage.googleapis.com',
        ];

        foreach (array_keys(self::FILES) as $path) {
            $src = $this->source($path);
            foreach ($forbidden as $needle) {
                $this->assertStringNotContainsString($needle, $src, "{$path} still contains {$needle}");
            }
        }
    }

    public function test_unconfigured_module_message_is_surfaced_not_swallowed(): void
    {
        $this->assertStringContainsString('AiConfigurationException', $this->source('app/Services/QuestionGenerationService.php'));
        $this->assertStringContainsString('AiConfigurationException', $this->source('app/Http/Controllers/G2gLms/AiCourseController.php'));
    }

    private function source(string $relative): string
    {
        $path = dirname(__DIR__, 2) . '/' . $relative;
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
