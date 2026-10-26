<?php

namespace App\Domain\AI\Configuration;

/**
 * The AI modules an administrator can point at a provider.
 *
 * WHY THIS LIST IS WRITTEN DOWN RATHER THAN DISCOVERED
 *
 * "Which parts of this product call a model" is not something the code can be asked.
 * The callers are spread across a pipeline, half a dozen services and a handful of
 * controllers, and several of them reach a provider through a path that predates the
 * `ModelClient` interface. A registry is the honest way to name them: one row per
 * module, each saying what it is, what a provider must be able to do to serve it, and
 * whether the centralised configuration actually reaches it.
 *
 * `wired` IS THE HONEST HALF
 *
 * A module with `wired: true` resolves its provider, model and key through
 * `AiConfigurationResolver` at runtime — saving a configuration for it changes what the
 * next call does. A module with `wired: false` and `runtime: model` still reaches its
 * provider its own way. A module with `runtime: none` makes no model call at all, and
 * says so through `gap` — which is a different statement from "not wired yet".
 *
 * WHAT A MODULE NEEDS IS DECLARED; WHAT A PROVIDER CAN DO IS NOT
 *
 * `requires` and `min_output_tokens` are the module's own needs and belong here.
 * Whether a provider meets them is derived — from the client class that drives it and
 * from the provider's live model list — in `ProviderCapabilities` and
 * `ProviderCompatibility`. Nothing in this file names a provider.
 *
 * Keys are stable and lowercase snake_case: they are written into
 * `ai_api_keys.ai_module` and must survive a label being reworded.
 */
final class AiModuleRegistry
{
    /**
     * `requires`          — capabilities a provider must have: chat, json, vision, embedding,
     *                       image_generation.
     * `min_output_tokens` — the smallest output ceiling that still gives a usable answer.
     * `offered`           — the providers this module may use, best first. This is the module's own list:
     *                       the admin dropdown shows only these, in this order, and the runtime tries them
     *                       in this order. A listed provider that cannot do what the module needs (no
     *                       vision client, say) is shown as Not capable and cannot be selected; a provider
     *                       not listed is neither shown nor used for this module.
     * `inherits`          — a module with no configuration of its own resolves through this one.
     * `runtime`           — `model` when the module makes model calls, `none` when it makes none.
     * `gap`               — what is missing, in words an administrator can act on.
     *
     * @var array<int, array{key:string, label:string, description:string, wired:bool, consumer:string, requires:array<int,string>, min_output_tokens:int|null, offered:array<int,string>, inherits:string|null, runtime:string, gap:string|null}>
     */
    private const MODULES = [
        [
            'key' => 'conversational_ai',
            'label' => 'Conversational AI',
            'description' => 'The assistant panel and the twelve-stage ask pipeline behind it.',
            'wired' => true,
            'consumer' => 'App\Domain\AI\Conversation\AskPipeline',
            'requires' => ['chat', 'json'],
            'min_output_tokens' => 1024,
            'offered' => ['openai', 'gemini', 'deepseek'],
            'inherits' => null,
            'runtime' => 'model',
            'gap' => null,
        ],
        [
            'key' => 'generative_ai',
            'label' => 'Generative AI',
            'description' => 'Governed generation from a versioned prompt — field assist, record summaries, drafting.',
            'wired' => true,
            'consumer' => 'App\Domain\GenerativeAI\GenerationService',
            'requires' => ['chat', 'json'],
            'min_output_tokens' => 2048,
            'offered' => ['deepseek', 'openai', 'gemini'],
            'inherits' => null,
            'runtime' => 'model',
            'gap' => null,
        ],
        [
            'key' => 'analytics_ai',
            'label' => 'Report & Analytics AI',
            'description' => 'Analyse-this-screen actions over dashboards, reports, lists and records. Uses the Generative AI configuration unless it has its own.',
            'wired' => true,
            'consumer' => 'App\Domain\GenerativeAI\GenerationService',
            'requires' => ['chat'],
            'min_output_tokens' => 2048,
            'offered' => ['openai', 'gemini', 'deepseek'],
            'inherits' => 'generative_ai',
            'runtime' => 'model',
            'gap' => null,
        ],
        [
            'key' => 'recommendation_ai',
            'label' => 'Recommendation AI',
            'description' => 'Drafted recommendations on an open case. Rule-based today; no model call yet.',
            'wired' => false,
            'consumer' => 'App\Domain\AI\Recommendations\RecommendationDrafter',
            'requires' => ['chat', 'json'],
            'min_output_tokens' => 1024,
            'offered' => ['deepseek', 'openai', 'gemini'],
            'inherits' => null,
            'runtime' => 'none',
            'gap' => 'Recommendations are drafted by rules today and make no model call, so there is nothing for a provider to change. The setting is kept for when a model-drafted path exists.',
        ],
        [
            'key' => 'agent_reasoning',
            'label' => 'Agent Reasoning',
            'description' => 'Planning and tool selection for agent runs.',
            'wired' => true,
            'consumer' => 'App\Domain\AI\Lifecycle\Plan\LlmPlanner',
            'requires' => ['chat', 'json'],
            'min_output_tokens' => 900,
            'offered' => ['openai', 'gemini', 'openrouter'],
            'inherits' => null,
            'runtime' => 'model',
            'gap' => null,
        ],
        [
            'key' => 'assessment_ai',
            'label' => 'Assessment AI',
            'description' => 'Question and assessment generation.',
            'wired' => true,
            'consumer' => 'App\Services\QuestionGenerationService',
            'requires' => ['chat', 'json'],
            'min_output_tokens' => 4096,
            'offered' => ['gemini', 'openai', 'deepseek'],
            'inherits' => null,
            'runtime' => 'model',
            'gap' => null,
        ],
        [
            'key' => 'content_generation',
            'label' => 'Content Generation',
            'description' => 'Course and lesson content authoring.',
            'wired' => true,
            'consumer' => 'App\Services\lms\Content\ContentGenerationGateway',
            'requires' => ['chat', 'json'],
            'min_output_tokens' => 4096,
            'offered' => ['deepseek', 'gemini', 'openai'],
            'inherits' => null,
            'runtime' => 'model',
            'gap' => null,
        ],
        [
            'key' => 'evaluation_ai',
            'label' => 'Homework & Assignment Evaluation',
            'description' => 'Automated marking of submitted homework and assignments.',
            'wired' => true,
            'consumer' => 'App\Services\Homework\HomeworkEvaluationService',
            'requires' => ['chat', 'json', 'vision'],
            'min_output_tokens' => 4096,
            'offered' => ['gemini', 'openai', 'deepseek'],
            'inherits' => null,
            'runtime' => 'model',
            'gap' => null,
        ],
        [
            'key' => 'lesson_planning',
            'label' => 'Lesson Planning AI',
            'description' => 'Lesson plans, slides and micro-planning.',
            'wired' => true,
            'consumer' => 'App\Services\LessonIntelligence\MicroPlannerService',
            'requires' => ['chat', 'json'],
            'min_output_tokens' => 4096,
            'offered' => ['deepseek', 'openai', 'gemini'],
            'inherits' => null,
            'runtime' => 'model',
            'gap' => null,
        ],
        [
            'key' => 'recruitment_ai',
            'label' => 'Recruitment AI',
            'description' => 'Job description analysis and candidate screening.',
            'wired' => true,
            'consumer' => 'App\Http\Controllers\api\TalentManagement\Recruitment\AnalyzeJDController',
            'requires' => ['chat', 'json'],
            'min_output_tokens' => 2048,
            'offered' => ['openai', 'deepseek', 'gemini'],
            'inherits' => null,
            'runtime' => 'model',
            'gap' => null,
        ],
        [
            'key' => 'pal_content_model',
            'label' => 'PAL Content Model',
            'description' => 'The personalised adaptive learning content model.',
            'wired' => true,
            'consumer' => 'App\Services\PAL\ContentModel\ContentModelLlmClient',
            'requires' => ['chat', 'json'],
            'min_output_tokens' => 4096,
            'offered' => ['deepseek', 'gemini', 'openai'],
            'inherits' => null,
            'runtime' => 'model',
            'gap' => null,
        ],
        [
            'key' => 'knowledge_sop',
            'label' => 'Knowledge & SOP AI',
            'description' => 'SOP drafting and retrieval-grounded answers.',
            'wired' => true,
            'consumer' => 'App\Http\Controllers\api\AiSopGenerationController',
            'requires' => ['chat', 'json'],
            'min_output_tokens' => 4096,
            'offered' => ['gemini', 'openai', 'deepseek'],
            'inherits' => null,
            'runtime' => 'model',
            'gap' => null,
        ],
        [
            'key' => 'pal_intelligence',
            'label' => 'PAL Intelligence',
            'description' => 'Misconception analysis, attainment and coherence reports, and content tagging in the personalised adaptive learning engine.',
            'wired' => true,
            'consumer' => 'App\Services\PAL\Intelligence\MisconceptionIntelligenceEngine',
            'requires' => ['chat', 'json'],
            'min_output_tokens' => 2048,
            'offered' => ['openai', 'gemini', 'deepseek'],
            'inherits' => null,
            'runtime' => 'model',
            'gap' => null,
        ],
        [
            'key' => 'career_intelligence',
            'label' => 'Career Intelligence AI',
            'description' => 'Career alignment and counselling narratives.',
            'wired' => true,
            'consumer' => 'App\CareerIntelligence\CareerCounsellingNarrator',
            'requires' => ['chat'],
            'min_output_tokens' => 1024,
            'offered' => ['openai', 'deepseek', 'gemini'],
            'inherits' => null,
            'runtime' => 'model',
            'gap' => null,
        ],
        [
            'key' => 'document_intelligence',
            'label' => 'Document Intelligence',
            'description' => 'Reading and classifying uploaded documents (OCR and document understanding).',
            'wired' => true,
            'consumer' => 'App\Services\Documents\Extraction\GeminiVisionOcrProvider',
            'requires' => ['chat', 'vision'],
            'min_output_tokens' => 2048,
            'offered' => ['gemini', 'openai', 'mistral'],
            'inherits' => null,
            'runtime' => 'model',
            'gap' => null,
        ],
        [
            'key' => 'content_embeddings',
            'label' => 'Content Embeddings',
            'description' => 'Vector embeddings that power content search and retrieval.',
            'wired' => true,
            'consumer' => 'App\Services\PAL\ContentModel\ContentEmbeddingService',
            'requires' => ['embedding'],
            'min_output_tokens' => null,
            'offered' => ['openai', 'gemini', 'mistral'],
            'inherits' => null,
            'runtime' => 'model',
            'gap' => null,
        ],
        [
            'key' => 'translation_ai',
            'label' => 'Translation AI',
            'description' => 'Content translation. Declared for configuration; no caller yet.',
            'wired' => false,
            'consumer' => '',
            'requires' => ['chat'],
            'min_output_tokens' => 2048,
            'offered' => ['gemini', 'deepseek', 'openai'],
            'inherits' => null,
            'runtime' => 'none',
            'gap' => 'No translation feature calls a model yet — the Hindi/Gujarati layer belongs to Conversational AI. The setting is kept for when one does.',
        ],
        [
            'key' => 'image_generation',
            'label' => 'Image Generation',
            'description' => 'Image generation for image-output templates.',
            'wired' => true,
            'consumer' => 'App\Domain\GenerativeAI\GenerationService',
            'requires' => ['image_generation'],
            'min_output_tokens' => null,
            'offered' => ['gemini', 'openai'],
            'inherits' => null,
            'runtime' => 'model',
            'gap' => null,
        ],
    ];

    /** @return array<int, array<string, mixed>> */
    public function all(): array
    {
        return self::MODULES;
    }

    /** @return array<int, string> */
    public function keys(): array
    {
        return array_column(self::MODULES, 'key');
    }

    public function exists(string $key): bool
    {
        return in_array($key, $this->keys(), true);
    }

    /** @return array<string, mixed>|null */
    public function find(string $key): ?array
    {
        foreach (self::MODULES as $module) {
            if ($module['key'] === $key) {
                return $module;
            }
        }

        return null;
    }

    public function label(string $key): string
    {
        return $this->find($key)['label'] ?? $key;
    }

    /** @return array<int, string> Capabilities a provider must have to serve this module. */
    public function requires(string $key): array
    {
        return (array) ($this->find($key)['requires'] ?? ['chat']);
    }

    /** @return array<int, string> The providers this module may use, best first. */
    public function offered(string $key): array
    {
        $preferred = array_values((array) ($this->find($key)['offered'] ?? []));
        $all = ['gemini', 'openai', 'openrouter', 'deepseek', 'groq', 'mistral', 'anthropic'];

        return array_values(array_unique(array_merge($preferred, $all)));
    }

    public function isOffered(string $key, string $provider): bool
    {
        return in_array($provider, $this->offered($key), true);
    }

    public function minOutputTokens(string $key): ?int
    {
        $value = $this->find($key)['min_output_tokens'] ?? null;

        return is_numeric($value) ? (int) $value : null;
    }

    /** The module this one resolves through when it has no configuration of its own. */
    public function inheritsFrom(string $key): ?string
    {
        $value = $this->find($key)['inherits'] ?? null;

        return is_string($value) && $this->exists($value) ? $value : null;
    }

    /** `model` when the module makes model calls, `none` when it makes none. */
    public function runtime(string $key): string
    {
        return (string) ($this->find($key)['runtime'] ?? 'model');
    }

    public function isWired(string $key): bool
    {
        return (bool) ($this->find($key)['wired'] ?? false);
    }
}
