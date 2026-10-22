<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Contract test: the content_generation, lesson_planning, recruitment_ai, knowledge_sop and
 * legacy OpenAIService call sites go through AiModelClientFactory and read no provider
 * credential, model or base URL from config/env.
 *
 * Source-level on purpose (same reason as AssessmentAiCentralisedConfigTest): the factory and
 * chain are final and record a usage row per attempt, so a behavioural test would hit the DB.
 */
class ContentLessonRecruitmentCentralisedConfigTest extends TestCase
{
    /** file => module keys it must use */
    private const FILES = [
        'app/Services/lms/Content/ContentGenerationGateway.php' => ['content_generation'],
        'app/Services/ContentGenerationService.php' => ['content_generation'],
        'app/Http/Controllers/lms/contentController.php' => ['content_generation'],
        'app/Services/LessonIntelligence/MicroPlannerService.php' => ['lesson_planning'],
        'app/Http/Controllers/lms/lessonplan/lms_lessonplanController.php' => ['lesson_planning'],
        'app/Http/Controllers/front_desk/syllabus/syllabusController.php' => ['content_generation'],
        'app/Http/Controllers/api/TalentManagement/Recruitment/AnalyzeJDController.php' => ['recruitment_ai'],
        'app/Http/Controllers/api/AiSopGenerationController.php' => ['knowledge_sop'],
        'app/Http/Controllers/AJAXController.php' => ['content_generation', 'assessment_ai'],
        'app/Http/Controllers/lms/h5p/H5PIndexController.php' => ['content_generation'],
        'app/Http/Controllers/lms/h5p/H5PScenarioController.php' => ['content_generation'],
        'app/Services/OpenAIService.php' => ['content_generation', 'lesson_planning', 'conversational_ai', 'assessment_ai'],
    ];

    public function test_call_sites_use_the_factory_with_their_module_keys(): void
    {
        foreach (self::FILES as $path => $modules) {
            $src = $this->source($path);
            $this->assertStringContainsString('AiModelClientFactory', $src, $path);
            foreach ($modules as $module) {
                $this->assertStringContainsString("'{$module}'", $src, "{$path} should use {$module}");
            }
        }
    }

    public function test_no_provider_credential_model_or_base_url_is_read_directly(): void
    {
        $forbidden = [
            "config('deepseek.api_key'", "config('deepseek.model'", "config('deepseek.base_url'",
            "config('deepseek.api_type'", "config('gemini.api_key'", "config('gemini.model'", "config('gemini.base_url'",
            "config('openrouter.", 'getAIKey(', "env('DEEPSEEK", "env('GEMINI", "env('OPENROUTER",
            "env('OPENAI", 'getenv(', 'ProviderKeyResolver', '/chat/completions', 'generativelanguage.googleapis.com',
            'Anthropic\\Client', 'apiKey_deepseek',
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
        foreach ([
            'app/Services/lms/Content/ContentGenerationGateway.php',
            'app/Services/ContentGenerationService.php',
            'app/Http/Controllers/lms/contentController.php',
            'app/Http/Controllers/lms/lessonplan/lms_lessonplanController.php',
            'app/Http/Controllers/front_desk/syllabus/syllabusController.php',
            'app/Http/Controllers/api/TalentManagement/Recruitment/AnalyzeJDController.php',
            'app/Http/Controllers/api/AiSopGenerationController.php',
            'app/Http/Controllers/AJAXController.php',
            'app/Services/OpenAIService.php',
        ] as $path) {
            $this->assertStringContainsString('AiConfigurationException', $this->source($path), $path);
        }
    }

    private function source(string $relative): string
    {
        $path = dirname(__DIR__, 2) . '/' . $relative;
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
