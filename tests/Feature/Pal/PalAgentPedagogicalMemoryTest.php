<?php

namespace Tests\Feature\Pal;

use App\Services\Mcp\McpRequestContext;
use App\Services\Mcp\NewPalReportService;
use App\Services\PAL\AI\AIOrchestrationService;
use App\Services\PAL\Pedagogy\PedagogicalMemoryService;
use Tests\TestCase;

/**
 * Validates the PAL AI Agent's mandatory pedagogical memory workflow.
 *
 * Workflow Rules:
 *   1. The PAL agent starts running.
 *   2. It checks whether the task requires content generation.
 *   3. Before generating any content, it reads and loads docs/PEDAGOGICAL_CONTENT_GENERATION_MEMORY.md.
 *   4. It applies all relevant instructions and frameworks from the file.
 *   5. Only then does it generate the requested content.
 *   6. The generated content follows the guidelines and quality standards defined in the Markdown file.
 */
class PalAgentPedagogicalMemoryTest extends TestCase
{
    private function scope(int $instituteId = 1): McpRequestContext
    {
        return new McpRequestContext(
            userId: 1,
            role: 'Admin',
            selectedInstituteId: $instituteId,
            allowedInstituteIds: [$instituteId],
            userProfileId: 1,
            clientId: 1,
            academicYear: 2026,
            termId: 1,
            isAdmin: true,
            isStudent: false,
        );
    }

    /**
     * Test 1: Task checking identifies content-generation tasks.
     */
    public function test_task_checking_identifies_content_generation(): void
    {
        $service = app(PedagogicalMemoryService::class);

        $this->assertTrue($service->isContentGenerationTask('explanation'));
        $this->assertTrue($service->isContentGenerationTask('remediation'));
        $this->assertTrue($service->isContentGenerationTask('practice'));
        $this->assertTrue($service->isContentGenerationTask('summary'));
        $this->assertTrue($service->isContentGenerationTask('variant_draft'));
        $this->assertTrue($service->isContentGenerationTask('lesson_plan'));
        $this->assertTrue($service->isContentGenerationTask('classroom_activity'));
        $this->assertTrue($service->isContentGenerationTask('worksheet'));
        $this->assertTrue($service->isContentGenerationTask('assessment_mcq'));
        $this->assertTrue($service->isContentGenerationTask('adaptive_intervention'));

        // Non-content tasks must return false
        $this->assertFalse($service->isContentGenerationTask('view_profile'));
        $this->assertFalse($service->isContentGenerationTask('attendance_check'));
    }

    /**
     * Test 2: Memory file loading reads docs/PEDAGOGICAL_CONTENT_GENERATION_MEMORY.md.
     */
    public function test_memory_file_loading_reads_memory_document(): void
    {
        $service = app(PedagogicalMemoryService::class);
        $memory = $service->loadMemory();

        $this->assertTrue($memory['exists'], 'Memory file must exist on disk.');
        $this->assertGreaterThan(10000, $memory['file_size'], 'Memory file must contain comprehensive guidelines.');
        $this->assertStringContainsString('PEDAGOGICAL_CONTENT_GENERATION_MEMORY.md', $memory['file_path']);

        // Check required frameworks are extracted
        $this->assertArrayHasKey('Inquiry-Based Learning', $memory['frameworks']);
        $this->assertArrayHasKey('Art-Integrated Learning', $memory['frameworks']);
        $this->assertArrayHasKey('Experiential Learning', $memory['frameworks']);
        $this->assertArrayHasKey('Game-Based Learning', $memory['frameworks']);

        // Check standards
        $this->assertArrayHasKey('nep_2020', $memory['standards']);
        $this->assertArrayHasKey('ncf', $memory['standards']);
        $this->assertArrayHasKey('blooms_taxonomy', $memory['standards']);
    }

    /**
     * Test 3: Prompt enrichment conditions generation with memory frameworks.
     */
    public function test_prompt_enrichment_applies_pedagogical_instructions(): void
    {
        $service = app(PedagogicalMemoryService::class);

        $basePrompt = 'Explain photosynthesis to grade 6.';
        $enriched = $service->enrichPrompt($basePrompt, 'explanation', ['grade' => 6]);

        $this->assertStringContainsString('MANDATORY PEDAGOGICAL INSTRUCTION', $enriched);
        $this->assertStringContainsString('docs/PEDAGOGICAL_CONTENT_GENERATION_MEMORY.md', $enriched);
        $this->assertStringContainsString('NEP 2020', $enriched);
        $this->assertStringContainsString('Inquiry Framing', $enriched);
        $this->assertStringContainsString($basePrompt, $enriched);
    }

    /**
     * Test 4: Quality validation checks generated content against memory standards.
     */
    public function test_quality_validation_verifies_content(): void
    {
        $service = app(PedagogicalMemoryService::class);

        $valid = $service->validateGeneratedContent(
            'In inquiry-based learning, we begin by observing green leaves under sunlight...',
            'explanation'
        );
        $this->assertTrue($valid['valid']);
        $this->assertSame('docs/PEDAGOGICAL_CONTENT_GENERATION_MEMORY.md', $valid['checked_against']);

        $invalid = $service->validateGeneratedContent('', 'explanation');
        $this->assertFalse($invalid['valid']);
        $this->assertNotEmpty($invalid['issues']);
    }

    /**
     * Test 5: NewPalReportService loads memory before drafting adaptive interventions.
     */
    public function test_report_service_loads_memory_for_interventions(): void
    {
        $reportService = app(NewPalReportService::class);
        $scope = $this->scope(1);

        $args = [
            'flagged_learners' => [
                [
                    'student_id' => 101,
                    'student_name' => 'Aarav Sharma',
                    'stream_share' => 0.70,
                    'practised_concepts' => 6,
                    'stuck_concepts' => ['Cell Structure', 'Plant Tissues'],
                ],
            ],
        ];

        $result = $reportService->draftAdaptiveIntervention($scope, $args);

        $this->assertArrayHasKey('pedagogical_memory', $result);
        $this->assertTrue($result['pedagogical_memory']['loaded']);
        $this->assertSame('docs/PEDAGOGICAL_CONTENT_GENERATION_MEMORY.md', $result['pedagogical_memory']['file']);
        $this->assertContains('Inquiry-Based Learning', $result['pedagogical_memory']['frameworks_applied']);
        $this->assertContains('Experiential Learning', $result['pedagogical_memory']['frameworks_applied']);
        $this->assertNotEmpty($result['draft_notes']);
    }

    /**
     * Test 6: HTTP endpoint GET /api/ai/pal/agent/memory exposes active memory state.
     */
    public function test_memory_endpoint_returns_pedagogical_memory_status(): void
    {
        $response = $this->withoutMiddleware([
            \App\Http\Middleware\McpAuth::class,
            \App\Http\Middleware\McpRateLimit::class,
            \App\Http\Middleware\McpContextHydrator::class,
        ])->getJson('/api/ai/pal/agent/memory');

        $response->assertStatus(200);
        $data = $response->json('data');

        $this->assertNotNull($data);
        $this->assertStringContainsString('PEDAGOGICAL_CONTENT_GENERATION_MEMORY.md', $data['file_path']);
        $this->assertGreaterThan(10000, $data['file_size']);
        $this->assertContains('Inquiry-Based Learning', $data['frameworks']);
        $this->assertContains('Art-Integrated Learning', $data['frameworks']);
        $this->assertContains('Experiential Learning', $data['frameworks']);
        $this->assertContains('Game-Based Learning', $data['frameworks']);
    }
}
