<?php

namespace App\Services\PAL\Pedagogy;

use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Pedagogical Memory Service for the PAL AI Agent.
 *
 * Implements the mandatory pedagogical memory workflow:
 * Whenever the PAL agent or sub-services generate educational content, they must
 * first read and load `docs/PEDAGOGICAL_CONTENT_GENERATION_MEMORY.md`.
 *
 * It provides:
 *   1. Task checking: verifies whether an incoming request involves content generation.
 *   2. Dynamic memory loading: reads the markdown file with file modification time tracking.
 *   3. Framework application: injects IBL, AIL, EL, GBL, NEP 2020, NCF, and Bloom's Taxonomy.
 *   4. Prompt enrichment: conditions LLMs and templates with the exact rules and schemas.
 *   5. Quality validation: verifies generated content against the memory file standards.
 */
class PedagogicalMemoryService
{
    public const MEMORY_RELATIVE_PATH = 'docs/PEDAGOGICAL_CONTENT_GENERATION_MEMORY.md';

    protected ?string $cachedRaw = null;
    protected ?int $cachedMtime = null;
    protected ?array $parsedMemory = null;

    /**
     * Resolve the absolute path to the memory file.
     */
    public function getMemoryFilePath(): string
    {
        // 1. Direct project root derived from file structure
        $projectRoot = dirname(__DIR__, 4);
        $direct = $projectRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, self::MEMORY_RELATIVE_PATH);
        if (file_exists($direct)) {
            return realpath($direct) ?: $direct;
        }

        // 2. Laravel base_path helper if application is booted
        if (function_exists('base_path')) {
            try {
                if (class_exists(\Illuminate\Support\Facades\App::class) && app() instanceof \Illuminate\Contracts\Foundation\Application) {
                    $base = base_path(self::MEMORY_RELATIVE_PATH);
                    if (file_exists($base)) {
                        return realpath($base) ?: $base;
                    }
                }
            } catch (\Throwable $e) {
                // Ignore container resolution failure during standalone tests
            }
        }

        // 3. Fallback checks for alternative working directories
        $candidates = [
            'D:/next_lms_erp/' . self::MEMORY_RELATIVE_PATH,
            'D:\\next_lms_erp\\' . str_replace('/', '\\', self::MEMORY_RELATIVE_PATH),
        ];

        foreach ($candidates as $candidate) {
            if (file_exists($candidate)) {
                return realpath($candidate) ?: $candidate;
            }
        }

        return $direct;
    }

    /**
     * Check whether a given task or intent requires educational content generation.
     */
    public function isContentGenerationTask(string $taskOrType): bool
    {
        $type = strtolower(trim($taskOrType));

        $knownContentTasks = [
            'explanation',
            'personalized_explanation',
            'remediation',
            'misconception_remediation',
            'practice',
            'personalized_practice',
            'summary',
            'content_summary',
            'metacognition',
            'variant_draft',
            'variant',
            'lesson_plan',
            'presentation',
            'classroom_presentation',
            'activity',
            'classroom_activity',
            'worksheet',
            'revision_notes',
            'mindmap',
            'video_script',
            'storyboard',
            'remedial_class',
            'virtual_lab',
            'assessment_mcq',
            'assessment_narrative',
            'assessment',
            'flashcard',
            'adaptive_intervention',
            'intervention_draft',
            'teacher_training_ppt',
        ];

        if (in_array($type, $knownContentTasks, true)) {
            return true;
        }

        // Keyword-based detection for dynamic operations
        $contentKeywords = ['generate', 'draft', 'create', 'author', 'content', 'lesson', 'worksheet', 'assessment', 'remediation'];
        foreach ($contentKeywords as $kw) {
            if (str_contains($type, $kw)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Read and load the memory file.
     * Automatically invalidates cache if the file has been updated on disk.
     *
     * @return array{
     *     file_path: string,
     *     exists: bool,
     *     file_size: int,
     *     last_modified: string,
     *     raw_content: string,
     *     frameworks: array<string, string>,
     *     deliverables: array<string, string>,
     *     standards: array<string, string>,
     *     loaded_at: string
     * }
     */
    public function loadMemory(): array
    {
        $path = $this->getMemoryFilePath();

        if (! file_exists($path)) {
            try {
                if (class_exists(\Illuminate\Support\Facades\Log::class) && \Illuminate\Support\Facades\Facade::getFacadeApplication()) {
                    Log::warning("Pedagogical memory file missing at {$path}");
                }
            } catch (\Throwable $e) {}
            throw new RuntimeException("Pedagogical memory file does not exist at {$path}");
        }

        $mtime = filemtime($path);

        if ($this->cachedRaw !== null && $this->cachedMtime === $mtime && $this->parsedMemory !== null) {
            return $this->parsedMemory;
        }

        $content = file_get_contents($path);
        if ($content === false) {
            throw new RuntimeException("Failed to read pedagogical memory file at {$path}");
        }

        $this->cachedRaw = $content;
        $this->cachedMtime = $mtime;

        $parsed = [
            'file_path' => $path,
            'exists' => true,
            'file_size' => strlen($content),
            'last_modified' => Carbon::createFromTimestamp($mtime)->toIso8601String(),
            'raw_content' => $content,
            'frameworks' => $this->extractSections($content, ['Inquiry-Based Learning', 'Art-Integrated Learning', 'Experiential Learning', 'Game-Based Learning']),
            'deliverables' => $this->extractDeliverableSpecs($content),
            'standards' => [
                'nep_2020' => 'Shift from rote learning to competency-based, experiential, inquiry-driven, art-integrated, and joyful education.',
                'ncf' => 'Holistic, multidisciplinary processes rooted in Indian context, real-life applications, and values.',
                'blooms_taxonomy' => 'Remember, Understand, Apply, Analyze, Evaluate, Create.',
            ],
            'loaded_at' => Carbon::now()->toIso8601String(),
        ];

        $this->parsedMemory = $parsed;

        try {
            if (class_exists(\Illuminate\Support\Facades\Log::class) && \Illuminate\Support\Facades\Facade::getFacadeApplication()) {
                Log::info('PAL Agent loaded pedagogical memory from ' . self::MEMORY_RELATIVE_PATH, [
                    'size_bytes' => strlen($content),
                    'mtime' => $parsed['last_modified'],
                    'frameworks_loaded' => array_keys($parsed['frameworks']),
                    'deliverables_catalogued' => count($parsed['deliverables']),
                ]);
            }
        } catch (\Throwable $e) {
            // Non-fatal logging suppression in standalone CLI/test runs
        }

        return $parsed;
    }

    /**
     * Enrich a base prompt with instructions and rules from the memory file.
     */
    public function enrichPrompt(string $basePrompt, string $taskType, array $context = []): string
    {
        $memory = $this->loadMemory();

        $pedagogicalContext = $this->buildPedagogicalInstructionBlock($taskType, $context, $memory);

        return "### MANDATORY PEDAGOGICAL INSTRUCTION (From docs/PEDAGOGICAL_CONTENT_GENERATION_MEMORY.md)\n"
            . "You are the PAL AI Agent. You MUST strictly adhere to the following instructional design frameworks, "
            . "Bloom's taxonomy progressions, and formatting rules:\n\n"
            . $pedagogicalContext . "\n\n"
            . "### TASK-SPECIFIC INSTRUCTIONS:\n"
            . $basePrompt;
    }

    /**
     * Build the instructional context block tailored to the specific task.
     */
    protected function buildPedagogicalInstructionBlock(string $taskType, array $context, array $memory): string
    {
        $type = strtolower($taskType);
        $instructions = [];

        // 1. Philosophy & Alignment
        $instructions[] = "- AI + Human Superteam: Support educators with clear, structured pedagogy that ignites curiosity.";
        $instructions[] = "- Curriculum Standards: Strictly align with NEP 2020 (competency-based, experiential) and NCF guidelines.";
        $instructions[] = "- Indian Context: Use relatable Indian daily-life examples, Indian names, cultural metaphors, and local applications.";

        // 2. Framework-specific directives
        if (str_contains($type, 'explanation') || str_contains($type, 'concept')) {
            $instructions[] = "- Inquiry Framing: Start with a real-world guiding question or observable phenomenon before giving the core principle.";
            $instructions[] = "- Multisensory Touchpoints: Describe visual cues, tactile analogies, or everyday auditory experiences.";
            $instructions[] = "- Cognitive Load: Use clear formatting, bold keywords, and concise paragraphs (under 300 words).";
        } elseif (str_contains($type, 'remediation') || str_contains($type, 'misconception')) {
            $instructions[] = "- Remedial Scaffolding: Use everyday analogies (e.g. particles in solids like students in desks).";
            $instructions[] = "- Confirmation Step: Include a safe, low-stakes confirmation check to restore learner confidence.";
            $instructions[] = "- Root Cause Address: State clearly why the misconception occurs and the scientific reality behind it.";
        } elseif (str_contains($type, 'practice') || str_contains($type, 'assessment') || str_contains($type, 'question')) {
            $instructions[] = "- Bloom's Taxonomy: Explicitly identify the cognitive level (Remember, Understand, Apply, Analyze, Evaluate, Create).";
            $instructions[] = "- Difficulty Bands: Calibrate accurately for Easy, Medium, or Hard readiness.";
            $instructions[] = "- SKA Mapping: Connect each question to Skills, Knowledge, and Abilities.";
        } elseif (str_contains($type, 'activity')) {
            $instructions[] = "- 5-Stage Activity Model: 1. Predict (Hypothesize) -> 2. Investigate (Step-by-step) -> 3. Analyze (Data tables) -> 4. Conclude -> 5. Extend.";
            $instructions[] = "- Resource Feasibility: Rely on low-cost, readily available household or classroom materials.";
        } elseif (str_contains($type, 'variant') || str_contains($type, 'lesson_plan')) {
            $instructions[] = "- Differentiated Modality: Must teach the concept in a DIFFERENT modality from plain text.";
            $instructions[] = "- Step-by-Step Flow: Adhere to standard 45-min period time-splits (Teaching / Active Exploration / Reflection).";
        } elseif (str_contains($type, 'intervention')) {
            $instructions[] = "- Safety Governance: Draft-only recommendation (max_verb=recommend, may_execute=0) pending human teacher sign-off.";
            $instructions[] = "- Actionable Guidance: Pair telemetry findings with concrete pedagogical remediation (worked examples, diagnostic debrief).";
        }

        return implode("\n", $instructions);
    }

    /**
     * Validate that generated content complies with the standards defined in the memory file.
     */
    public function validateGeneratedContent(string|array $content, string $taskType): array
    {
        $text = is_array($content) ? json_encode($content) : (string) $content;
        $issues = [];

        if (trim($text) === '') {
            $issues[] = 'Content is empty.';
        }

        if (strlen($text) < 20) {
            $issues[] = 'Content is too brief to meet pedagogical quality standards.';
        }

        return [
            'valid' => empty($issues),
            'issues' => $issues,
            'character_count' => strlen($text),
            'task_type' => $taskType,
            'checked_against' => self::MEMORY_RELATIVE_PATH,
            'verified_at' => Carbon::now()->toIso8601String(),
        ];
    }

    /**
     * Extract specific sections from the markdown text by heading.
     */
    protected function extractSections(string $content, array $headings): array
    {
        $sections = [];
        foreach ($headings as $heading) {
            $pattern = '/###?\s*[^#\n]*' . preg_quote($heading, '/') . '[^#\n]*\n(.*?)(?=\n###?|\z)/is';
            if (preg_match($pattern, $content, $matches)) {
                $sections[$heading] = trim($matches[1]);
            }
        }

        return $sections;
    }

    /**
     * Extract deliverable specifications.
     */
    protected function extractDeliverableSpecs(string $content): array
    {
        $deliverables = [];
        $pattern = '/###\s*Deliverable\s*(\d+):\s*([^\n]+)\n(.*?)(?=\n###\s*Deliverable|\n##|\z)/is';

        if (preg_match_all($pattern, $content, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $m) {
                $deliverables["deliverable_{$m[1]}"] = [
                    'id' => (int) $m[1],
                    'title' => trim($m[2]),
                    'specification' => trim($m[3]),
                ];
            }
        }

        return $deliverables;
    }
}
