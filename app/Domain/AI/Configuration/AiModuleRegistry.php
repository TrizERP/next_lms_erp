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
 * module, each saying what it is and whether the centralised configuration actually
 * reaches it yet.
 *
 * `wired` IS THE HONEST HALF
 *
 * A module with `wired: true` resolves its provider, model and key through
 * `AiConfigurationResolver` at runtime — saving a configuration for it changes what
 * the next call does. A module with `wired: false` is a real part of the product that
 * still reaches its provider its own way; a configuration saved against it is stored
 * and shown, but nothing reads it yet. The admin screen says so rather than implying
 * a binding that does not exist, because a settings screen that silently does nothing
 * is worse than one that admits what it does not control.
 *
 * Keys are stable and lowercase snake_case: they are written into
 * `ai_api_keys.ai_module` and must survive a label being reworded.
 */
final class AiModuleRegistry
{
    /**
     * @var array<int, array{key:string, label:string, description:string, wired:bool, consumer:string}>
     */
    private const MODULES = [
        [
            'key' => 'conversational_ai',
            'label' => 'Conversational AI',
            'description' => 'The assistant panel and the twelve-stage ask pipeline behind it.',
            'wired' => true,
            'consumer' => 'App\Domain\AI\Conversation\AskPipeline',
        ],
        [
            'key' => 'generative_ai',
            'label' => 'Generative AI',
            'description' => 'Governed generation from a versioned prompt — field assist, record summaries, drafting.',
            'wired' => true,
            'consumer' => 'App\Domain\GenerativeAI\GenerationService',
        ],
        [
            'key' => 'analytics_ai',
            'label' => 'Report & Analytics AI',
            // Runs through GenerationService, which resolves under `generative_ai` —
            // one service, one set of credentials. Flagged unwired rather than wired
            // because a configuration saved here does not change what it calls, and a
            // settings row that silently does nothing is worse than one that says so.
            'description' => 'Analyse-this-screen actions over dashboards, reports, lists and records. Uses the Generative AI configuration.',
            'wired' => false,
            'consumer' => 'App\Domain\GenerativeAI\GenerationService',
        ],
        [
            'key' => 'recommendation_ai',
            'label' => 'Recommendation AI',
            // Drafting is rule-based today — the drafter reaches no model at all — so a
            // configuration here is stored against the module it will belong to rather
            // than one that reads it now.
            'description' => 'Drafted recommendations on an open case. Rule-based today; no model call yet.',
            'wired' => false,
            'consumer' => 'App\Domain\AI\Recommendations\RecommendationDrafter',
        ],
        [
            'key' => 'agent_reasoning',
            'label' => 'Agent Reasoning',
            'description' => 'Planning and tool selection for agent runs.',
            'wired' => true,
            'consumer' => 'App\Domain\AI\Lifecycle\Plan\LlmPlanner',
        ],
        [
            'key' => 'assessment_ai',
            'label' => 'Assessment AI',
            'description' => 'Question and assessment generation.',
            'wired' => false,
            'consumer' => 'App\Services\QuestionGenerationService',
        ],
        [
            'key' => 'content_generation',
            'label' => 'Content Generation',
            'description' => 'Course and lesson content authoring.',
            'wired' => false,
            'consumer' => 'App\Services\lms\Content\ContentGenerationGateway',
        ],
        [
            'key' => 'evaluation_ai',
            'label' => 'Homework & Assignment Evaluation',
            'description' => 'Automated marking of submitted homework and assignments.',
            'wired' => false,
            'consumer' => 'App\Services\Homework\HomeworkEvaluationService',
        ],
        [
            'key' => 'lesson_planning',
            'label' => 'Lesson Planning AI',
            'description' => 'Lesson plans, slides and micro-planning.',
            'wired' => false,
            'consumer' => 'App\Services\LessonIntelligence\MicroPlannerService',
        ],
        [
            'key' => 'recruitment_ai',
            'label' => 'Recruitment AI',
            'description' => 'Job description analysis and candidate screening.',
            'wired' => false,
            'consumer' => 'App\Http\Controllers\api\TalentManagement\Recruitment\AnalyzeJDController',
        ],
        [
            'key' => 'pal_content_model',
            'label' => 'PAL Content Model',
            'description' => 'The personalised adaptive learning content model.',
            'wired' => false,
            'consumer' => 'App\Services\PAL\ContentModel\ContentModelLlmClient',
        ],
        [
            'key' => 'knowledge_sop',
            'label' => 'Knowledge & SOP AI',
            'description' => 'SOP drafting and retrieval-grounded answers.',
            'wired' => false,
            'consumer' => 'App\Http\Controllers\api\AiSopGenerationController',
        ],
        [
            'key' => 'translation_ai',
            'label' => 'Translation AI',
            'description' => 'Content translation. Declared for configuration; no caller yet.',
            'wired' => false,
            'consumer' => '',
        ],
        [
            'key' => 'image_generation',
            'label' => 'Image Generation',
            'description' => 'Image generation. Declared for configuration; no caller yet.',
            'wired' => false,
            'consumer' => '',
        ],
    ];

    /** @return array<int, array{key:string, label:string, description:string, wired:bool, consumer:string}> */
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

    /** @return array{key:string, label:string, description:string, wired:bool, consumer:string}|null */
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

    public function isWired(string $key): bool
    {
        return (bool) ($this->find($key)['wired'] ?? false);
    }
}
