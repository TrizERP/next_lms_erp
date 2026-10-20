<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Guards the call sites that were moved onto the centralised AI configuration.
 *
 * A call site that reads a provider key, model or base URL from the environment or from a
 * provider-specific config file is one the AI Providers screen cannot govern, and it fails
 * silently: it keeps working with the old key while the administrator believes they changed it.
 * This reads source only -- no container, no database, no network.
 */
class AiCentralisedConfigurationGuardTest extends TestCase
{
    private const FORBIDDEN = [
        '/config\(\s*[\'"](deepseek|openrouter)\.(api_key|headers|base_url|model)[\'"]/' => 'provider config read',
        '/config\(\s*[\'"]gemini\.(api_key|model|base_url)[\'"]/' => 'gemini config read',
        '/config\(\s*[\'"]idms\.gemini_api_key[\'"]/' => 'idms gemini key read',
        '/config\(\s*[\'"]ai\.provider\.gemini\.api_key[\'"]/' => 'gemini api key read',
        '/env\(\s*[\'"](GEMINI_API_KEY|OPENROUTER_API_KEY|DEEPSEEK_API_KEY)[\'"]/' => 'provider key env read',
        '/\bgetAIKey\s*\(/' => 'legacy ai_api_keys lookup',
    ];

    /** @return array<string, array{0:string}> */
    public static function migratedFiles(): array
    {
        $files = [
            'app/Services/Homework/GeminiClient.php',
            'app/Services/PAL/ContentModel/ContentModelLlmClient.php',
            'app/Services/PAL/ContentModel/ContentEmbeddingService.php',
            'app/Services/PAL/ContentModel/ContentKnowledgeAnswerer.php',
            'app/Services/PAL/AI/AIOrchestrationService.php',
            'app/Services/PAL/Intelligence/MisconceptionIntelligenceEngine.php',
            'app/Services/PAL/Runtime/SubsystemRuntime.php',
            'app/Services/PAL/Administration/ArchitectureHealthService.php',
            'app/Console/Commands/PAL/TagContentCommand.php',
            'app/Http/Controllers/api/PAL/AttainmentReportController.php',
            'app/Http/Controllers/api/PAL/CoherenceMapController.php',
            'app/CareerIntelligence/CareerAlignmentNarrator.php',
            'app/CareerIntelligence/CareerCounsellingNarrator.php',
            'app/Services/PAL/Gamification/CareerQuestNarrator.php',
            'app/Domain/AI/Lifecycle/Flows/AdmissionsFlow.php',
            'app/Services/Documents/Understanding/GeminiClassifier.php',
            'app/Services/Documents/Extraction/GeminiVisionOcrProvider.php',
        ];

        return array_combine($files, array_map(static fn (string $f) => [$f], $files));
    }

    #[DataProvider('migratedFiles')]
    public function test_the_call_site_reads_no_provider_credential_from_the_environment(string $relative): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/' . $relative);
        $this->assertIsString($source, $relative . ' is missing');

        foreach (self::FORBIDDEN as $pattern => $what) {
            $this->assertSame(
                0,
                preg_match($pattern, $source),
                sprintf('%s still has a %s; provider, model and key must come from AiModelClientFactory.', $relative, $what)
            );
        }
    }

    #[DataProvider('migratedFiles')]
    public function test_the_call_site_resolves_through_the_central_factory(string $relative): void
    {
        $source = (string) file_get_contents(dirname(__DIR__, 2) . '/' . $relative);

        // The two console/status files only REPORT the configuration; they still go through it.
        $this->assertStringContainsString('AiModelClientFactory', $source, $relative);
    }
}
