<?php

namespace App\Domain\AI\Configuration;

/**
 * Curated, reliable registry of supported AI models with rich descriptions and technical specifications.
 *
 * Each model entry defines:
 *   - label: Human-readable model name
 *   - description: Comprehensive overview of what it does
 *   - best_for: Main recommended use cases and task types
 *   - strengths: Core strengths and technical capabilities
 *   - limitations: Context window constraints, output token ceilings, reasoning latency
 *   - context_tokens: Max input context tokens
 *   - max_output_tokens: Max output generation tokens
 *   - chat / json / vision / embedding / image_output: Modalities and capabilities
 */
final class ModelRegistry
{
    /**
     * @var array<string, array<string, array<string, mixed>>>
     */
    private const REGISTRY = [
        'gemini' => [
            'gemini-3.8-flash' => [
                'label' => 'Gemini 3.8 Flash',
                'description' => 'Google’s state-of-the-art fast multimodal model. Delivers ultra-low latency, outstanding accuracy across coding and analytical tasks, and high-fidelity structured outputs.',
                'best_for' => 'Conversational AI, live chat, real-time analytics, report generation, and automated record drafting.',
                'strengths' => '1,048,576 token context window, 65,536 output tokens, rapid token throughput, multimodal image/document understanding, and native structured JSON.',
                'limitations' => 'Optimised for throughput and balanced intelligence; use Gemini 3.1 Pro for extreme multi-hour academic analysis.',
                'context_tokens' => 1048576,
                'max_output_tokens' => 65536,
                'chat' => true,
                'json' => true,
                'vision' => true,
                'embedding' => false,
                'image_output' => false,
            ],
            'gemini-2.5-flash' => [
                'label' => 'Gemini 2.5 Flash',
                'description' => 'Google’s next-generation high-speed multimodal model. Built for high-frequency tasks, real-time responses, and high-volume data processing with exceptionally large context support.',
                'best_for' => 'Conversational AI, live chat, real-time analytics, report generation, and automated record drafting.',
                'strengths' => '1,048,576 token context window, 65,536 output tokens, rapid token throughput, multimodal image/document understanding, and native structured JSON.',
                'limitations' => 'Optimised for throughput and latency; slightly less deep chain-of-thought mathematical reasoning than Gemini 2.5 Pro.',
                'context_tokens' => 1048576,
                'max_output_tokens' => 65536,
                'chat' => true,
                'json' => true,
                'vision' => true,
                'embedding' => false,
                'image_output' => false,
            ],
            'gemini-2.5-pro' => [
                'label' => 'Gemini 2.5 Pro',
                'description' => 'Google’s flagship reasoning and analytical model. Exceptional performance on complex logic, multi-step problem solving, in-depth academic evaluations, and rich document comprehension.',
                'best_for' => 'Report & Analytics AI, PAL intelligence, curriculum planning, homework evaluation, and complex agent reasoning.',
                'strengths' => 'Up to 2,097,152 token context window, 65,536 output tokens, deep analytical reasoning, comprehensive multi-page document inspection, and high reliability.',
                'limitations' => 'Higher latency than Flash models; best reserved for analytical and evaluative workloads rather than high-frequency simple chat.',
                'context_tokens' => 2097152,
                'max_output_tokens' => 65536,
                'chat' => true,
                'json' => true,
                'vision' => true,
                'embedding' => false,
                'image_output' => false,
            ],
            'gemini-2.5-flash-lite' => [
                'label' => 'Gemini 2.5 Flash-Lite',
                'description' => 'Google’s most lightweight, cost-effective generation model. Designed for high-volume summarisation, simple classifications, and rapid query handling.',
                'best_for' => 'High-throughput text classification, simple query routing, brief field drafts, and fast keyword tagging.',
                'strengths' => 'Ultra-low cost, sub-second responses, 1M context window, high concurrency.',
                'limitations' => 'Smaller parameter capacity; not recommended for multi-step reasoning, complex curriculum analysis, or deep report synthesis.',
                'context_tokens' => 1048576,
                'max_output_tokens' => 65536,
                'chat' => true,
                'json' => true,
                'vision' => true,
                'embedding' => false,
                'image_output' => false,
            ],
            'gemini-flash-latest' => [
                'label' => 'Gemini Flash (Latest)',
                'description' => 'Production alias pointing to Google’s latest stable Gemini Flash build. Balances high throughput with strong multimodal capabilities.',
                'best_for' => 'General generative tasks, interactive dashboards, and conversational modules.',
                'strengths' => 'Always stays on the current production Flash release, high reliability, large context.',
                'limitations' => 'Model weights update automatically with Google production releases.',
                'context_tokens' => 1048576,
                'max_output_tokens' => 65536,
                'chat' => true,
                'json' => true,
                'vision' => true,
                'embedding' => false,
                'image_output' => false,
            ],
            'gemini-pro-latest' => [
                'label' => 'Gemini Pro (Latest)',
                'description' => 'Production alias pointing to Google’s latest stable Gemini Pro build for deep reasoning and comprehensive synthesis.',
                'best_for' => 'In-depth analytics, complex grading, and multi-factor decision analysis.',
                'strengths' => 'Top-tier reasoning, deep synthesis, reliable structured JSON output.',
                'limitations' => 'Higher latency than Flash.',
                'context_tokens' => 2097152,
                'max_output_tokens' => 65536,
                'chat' => true,
                'json' => true,
                'vision' => true,
                'embedding' => false,
                'image_output' => false,
            ],
        ],
        'openai' => [
            'gpt-4o' => [
                'label' => 'GPT-4o',
                'description' => 'OpenAI’s flagship omni model. Integrates advanced text, vision, and reasoning intelligence into an omni-channel system with exceptional structured output accuracy.',
                'best_for' => 'Report & Analytics AI, complex question generation, homework evaluation, and agent reasoning.',
                'strengths' => '128,000 token context window, 16,384 output tokens, high instruction-following adherence, complex JSON schema formatting, vision document inspection.',
                'limitations' => 'Higher token cost than gpt-4o-mini; moderate response latency on very large prompts.',
                'context_tokens' => 128000,
                'max_output_tokens' => 16384,
                'chat' => true,
                'json' => true,
                'vision' => true,
                'embedding' => false,
                'image_output' => false,
            ],
            'gpt-4o-mini' => [
                'label' => 'GPT-4o Mini',
                'description' => 'OpenAI’s compact, highly efficient workhorse model. Delivers remarkable speed and intelligence for routine tasks at a fraction of the cost.',
                'best_for' => 'Conversational AI, quick notifications, candidate screening, summary drafting, and student engagement prompts.',
                'strengths' => '128,000 token context window, 16,384 max output tokens, high concurrency, fast latency, cost-effective.',
                'limitations' => 'Less nuance on deeply nested multi-variable reasoning and intricate mathematical problem-solving compared to GPT-4o.',
                'context_tokens' => 128000,
                'max_output_tokens' => 16384,
                'chat' => true,
                'json' => true,
                'vision' => true,
                'embedding' => false,
                'image_output' => false,
            ],
            'o1' => [
                'label' => 'o1 (Reasoning)',
                'description' => 'OpenAI’s premier deliberate-reasoning model. Uses internal chain-of-thought thinking before generating answers, excelling at math, science, and complex multi-step planning.',
                'best_for' => 'Advanced STEM assessment generation, misconception analysis, algorithm generation, and strategic agent planning.',
                'strengths' => '200,000 token context window, 100,000 max completion tokens, peerless reasoning depth and precision on hard logic.',
                'limitations' => 'Extended thinking latency (10-30s); higher token usage due to internal reasoning tokens; does not support streaming in all modes.',
                'context_tokens' => 200000,
                'max_output_tokens' => 100000,
                'chat' => true,
                'json' => true,
                'vision' => true,
                'embedding' => false,
                'image_output' => false,
            ],
            'o3-mini' => [
                'label' => 'o3-mini (Fast Reasoning)',
                'description' => 'OpenAI’s high-speed reasoning model with configurable thinking capacity. Tailored for math, coding, and structured analytical tasks with lower latency than o1.',
                'best_for' => 'STEM question authoring, code evaluation, logic verification, and complex data validation.',
                'strengths' => '200,000 context, 100,000 output tokens, configurable reasoning effort, fast throughput.',
                'limitations' => 'Pure text modality (no image/document vision input).',
                'context_tokens' => 200000,
                'max_output_tokens' => 100000,
                'chat' => true,
                'json' => true,
                'vision' => false,
                'embedding' => false,
                'image_output' => false,
            ],
        ],
        'deepseek' => [
            'deepseek-chat' => [
                'label' => 'DeepSeek Chat (V3)',
                'description' => 'DeepSeek’s flagship general-purpose model (DeepSeek-V3). Offers exceptional coding, multilingual comprehension, and structured drafting at remarkable cost efficiency.',
                'best_for' => 'Generative AI, content generation, SOP drafting, recruitment job description analysis, and curriculum text.',
                'strengths' => '64,000 context window, 8,192 max output tokens, strong JSON adherence, excellent multilingual performance, economical.',
                'limitations' => 'Text-only modality (no direct vision/image inputs); output ceiling capped at 8,192 tokens.',
                'context_tokens' => 64000,
                'max_output_tokens' => 8192,
                'chat' => true,
                'json' => true,
                'vision' => false,
                'embedding' => false,
                'image_output' => false,
            ],
            'deepseek-reasoner' => [
                'label' => 'DeepSeek Reasoner (R1)',
                'description' => 'DeepSeek’s state-of-the-art reasoning model (DeepSeek-R1). Generates a visible reasoning process before delivering structured answers, excelling in logic and math.',
                'best_for' => 'Assessment generation, mathematical validation, rubric scoring, and logic-intensive curriculum analysis.',
                'strengths' => '64,000 context window, 8,192 max output tokens, transparent step-by-step thinking, high accuracy on challenging technical prompts.',
                'limitations' => 'Higher latency due to chain-of-thought generation; text-only (no direct vision/image inputs).',
                'context_tokens' => 64000,
                'max_output_tokens' => 8192,
                'chat' => true,
                'json' => true,
                'vision' => false,
                'embedding' => false,
                'image_output' => false,
            ],
        ],
        'groq' => [
            'llama-3.3-70b-versatile' => [
                'label' => 'Llama 3.3 70B (Groq)',
                'description' => 'Meta’s open flagship 70B parameter model served at blazing inference speeds on Groq’s LPU hardware architecture. Delivers GPT-4 class capabilities at near-instantaneous speed.',
                'best_for' => 'Real-time conversational AI, interactive analytics exploration, instant drafting, and dynamic lesson planning.',
                'strengths' => '128,000 token context window, 4,096 max output tokens, industry-leading token generation speed (up to 300+ tokens/second), low latency.',
                'limitations' => 'Output token limit capped at 4,096 tokens; text-only (no image vision input).',
                'context_tokens' => 128000,
                'max_output_tokens' => 4096,
                'chat' => true,
                'json' => true,
                'vision' => false,
                'embedding' => false,
                'image_output' => false,
            ],
            'llama-3.1-8b-instant' => [
                'label' => 'Llama 3.1 8B Instant (Groq)',
                'description' => 'Lightweight 8B parameter model running on Groq LPUs. Designed for sub-second responses, classification, short summaries, and lightweight query routing.',
                'best_for' => 'Ultra-fast conversational turns, simple query assistance, notification generation, and tag categorization.',
                'strengths' => '128,000 context window, instant sub-second response times, extremely cost-effective.',
                'limitations' => 'Compact parameter size; not suitable for complex multi-page report analysis or intricate question generation.',
                'context_tokens' => 128000,
                'max_output_tokens' => 4096,
                'chat' => true,
                'json' => true,
                'vision' => false,
                'embedding' => false,
                'image_output' => false,
            ],
            'mixtral-8x7b-32768' => [
                'label' => 'Mixtral 8x7B (Groq)',
                'description' => 'High-speed sparse Mixture-of-Experts model from Mistral AI served on Groq LPUs. Balances speed with multilingual breadth.',
                'best_for' => 'Multilingual drafting, code assistance, and high-speed content processing.',
                'strengths' => '32,768 context window, fast execution, strong European language support.',
                'limitations' => 'Smaller context window (32k) than Llama 3.3; 4,096 output ceiling.',
                'context_tokens' => 32768,
                'max_output_tokens' => 4096,
                'chat' => true,
                'json' => true,
                'vision' => false,
                'embedding' => false,
                'image_output' => false,
            ],
        ],
        'mistral' => [
            'mistral-large-latest' => [
                'label' => 'Mistral Large (Latest)',
                'description' => 'Mistral AI’s premier flagship model. Renowned for top-tier multilingual performance, nuanced reasoning, coding capabilities, and strict JSON schema adherence.',
                'best_for' => 'Multilingual report analytics, curriculum alignment, complex drafting, and structured evaluation.',
                'strengths' => '128,000 context window, 4,096 max output tokens, excellent fluency across English, French, Spanish, German, and Hindi.',
                'limitations' => 'Output tokens capped at 4,096; higher cost than Mistral Small.',
                'context_tokens' => 128000,
                'max_output_tokens' => 4096,
                'chat' => true,
                'json' => true,
                'vision' => false,
                'embedding' => false,
                'image_output' => false,
            ],
            'mistral-small-latest' => [
                'label' => 'Mistral Small (Latest)',
                'description' => 'Compact and highly responsive language model from Mistral AI. Ideal for high-speed conversation and low-latency summarisation.',
                'best_for' => 'Conversational AI, everyday summaries, notification drafts, and fast categorization.',
                'strengths' => '32,768 context window, very fast latency, cost-effective.',
                'limitations' => '32k context window ceiling; less capable of deep diagnostic reasoning.',
                'context_tokens' => 32768,
                'max_output_tokens' => 4096,
                'chat' => true,
                'json' => true,
                'vision' => false,
                'embedding' => false,
                'image_output' => false,
            ],
            'codestral-latest' => [
                'label' => 'Codestral (Latest)',
                'description' => 'Mistral AI’s state-of-the-art code and technical reasoning model trained specifically on 80+ programming languages and technical specifications.',
                'best_for' => 'Coding questions, programming assessments, technical data modeling, and algorithmic evaluation.',
                'strengths' => '256,000 context window, exceptional technical syntax comprehension, high accuracy.',
                'limitations' => 'Specialized for code and logic; less tailored for creative literary drafting.',
                'context_tokens' => 256000,
                'max_output_tokens' => 4096,
                'chat' => true,
                'json' => true,
                'vision' => false,
                'embedding' => false,
                'image_output' => false,
            ],
        ],
        'anthropic' => [
            'claude-3-5-sonnet-20241022' => [
                'label' => 'Claude 3.5 Sonnet',
                'description' => 'Anthropic’s flagship intelligence model. Recognized as an industry benchmark in deep reasoning, nuanced academic writing, computer vision, code generation, and complex synthesis.',
                'best_for' => 'Report & Analytics AI, comprehensive homework evaluation, student assessment authoring, and complex document intelligence.',
                'strengths' => '200,000 token context window, 8,192 max output tokens, industry-leading instruction following, superior document/chart vision comprehension, elegant human-grade prose.',
                'limitations' => 'Higher cost than Haiku; requires Anthropic API key with tier permissions.',
                'context_tokens' => 200000,
                'max_output_tokens' => 8192,
                'chat' => true,
                'json' => true,
                'vision' => true,
                'embedding' => false,
                'image_output' => false,
            ],
            'claude-3-5-haiku-20241022' => [
                'label' => 'Claude 3.5 Haiku',
                'description' => 'Anthropic’s fastest, most responsive model. Delivers intelligence comparable to prior flagship models at ultra-low latency and minimal cost.',
                'best_for' => 'Fast conversational AI, immediate interactive feedback, candidate screening, and high-volume text drafting.',
                'strengths' => '200,000 context window, 8,192 output tokens, exceptionally fast generation, economical.',
                'limitations' => 'Text-only (no vision input in initial 3.5 Haiku release).',
                'context_tokens' => 200000,
                'max_output_tokens' => 8192,
                'chat' => true,
                'json' => true,
                'vision' => false,
                'embedding' => false,
                'image_output' => false,
            ],
            'claude-3-opus-20240229' => [
                'label' => 'Claude 3 Opus',
                'description' => 'Anthropic’s most deeply reflective model for massive research synthesis, complex philosophical evaluation, and nuanced academic reasoning.',
                'best_for' => 'Comprehensive research reports, deep student portfolio evaluation, and advanced curriculum synthesis.',
                'strengths' => '200,000 context window, 4,096 output tokens, deep analytical contemplation, vision support.',
                'limitations' => 'Higher latency and token cost than Claude 3.5 Sonnet.',
                'context_tokens' => 200000,
                'max_output_tokens' => 4096,
                'chat' => true,
                'json' => true,
                'vision' => true,
                'embedding' => false,
                'image_output' => false,
            ],
        ],
        'openrouter' => [
            'anthropic/claude-3.5-sonnet' => [
                'label' => 'Claude 3.5 Sonnet (OpenRouter)',
                'description' => 'Anthropic’s premier intelligence model accessed through the OpenRouter unified API gateway. Top-tier reasoning, vision, and nuanced writing.',
                'best_for' => 'Report & Analytics AI, Assessment AI, Generative AI, Homework Evaluation.',
                'strengths' => '200,000 context window, 8,192 output tokens, vision input, reliable uptime via OpenRouter routing.',
                'limitations' => 'Depends on OpenRouter rate limits and upstream provider availability.',
                'context_tokens' => 200000,
                'max_output_tokens' => 8192,
                'chat' => true,
                'json' => true,
                'vision' => true,
                'embedding' => false,
                'image_output' => false,
            ],
            'openai/gpt-4o' => [
                'label' => 'GPT-4o (OpenRouter)',
                'description' => 'OpenAI’s flagship multimodal intelligence model routed through OpenRouter.',
                'best_for' => 'Analytics AI, Assessment generation, complex evaluations.',
                'strengths' => '128,000 context window, 16,384 output tokens, vision input, JSON schema adherence.',
                'limitations' => 'OpenRouter gateway overhead.',
                'context_tokens' => 128000,
                'max_output_tokens' => 16384,
                'chat' => true,
                'json' => true,
                'vision' => true,
                'embedding' => false,
                'image_output' => false,
            ],
            'openai/gpt-4o-mini' => [
                'label' => 'GPT-4o Mini (OpenRouter)',
                'description' => 'Fast, economical OpenAI model served through OpenRouter.',
                'best_for' => 'Conversational AI, quick summaries, candidate screening.',
                'strengths' => '128,000 context window, 16k output tokens, fast throughput, cost-effective.',
                'limitations' => 'Lower reasoning depth than flagship GPT-4o.',
                'context_tokens' => 128000,
                'max_output_tokens' => 16384,
                'chat' => true,
                'json' => true,
                'vision' => true,
                'embedding' => false,
                'image_output' => false,
            ],
            'deepseek/deepseek-chat' => [
                'label' => 'DeepSeek Chat (OpenRouter)',
                'description' => 'DeepSeek-V3 general-purpose model accessed via OpenRouter.',
                'best_for' => 'Content generation, drafting, translation, lesson planning.',
                'strengths' => '64,000 context, 8,192 output tokens, cost-effective, strong coding and JSON.',
                'limitations' => 'Text-only (no vision input).',
                'context_tokens' => 64000,
                'max_output_tokens' => 8192,
                'chat' => true,
                'json' => true,
                'vision' => false,
                'embedding' => false,
                'image_output' => false,
            ],
            'deepseek/deepseek-r1' => [
                'label' => 'DeepSeek R1 (OpenRouter)',
                'description' => 'DeepSeek’s reasoning model with chain-of-thought processing via OpenRouter.',
                'best_for' => 'Logic questions, STEM grading, deep reasoning.',
                'strengths' => '64,000 context, 8,192 output, step-by-step thinking.',
                'limitations' => 'Higher latency due to reasoning tokens.',
                'context_tokens' => 64000,
                'max_output_tokens' => 8192,
                'chat' => true,
                'json' => true,
                'vision' => false,
                'embedding' => false,
                'image_output' => false,
            ],
            'meta-llama/llama-3.3-70b-instruct' => [
                'label' => 'Llama 3.3 70B Instruct (OpenRouter)',
                'description' => 'Meta’s open flagship instruction-tuned model via OpenRouter.',
                'best_for' => 'Conversational AI, generative tasks, lesson planning.',
                'strengths' => '128,000 context, strong reasoning, tool use.',
                'limitations' => 'Text-only (no vision input).',
                'context_tokens' => 128000,
                'max_output_tokens' => 4096,
                'chat' => true,
                'json' => true,
                'vision' => false,
                'embedding' => false,
                'image_output' => false,
            ],
            'google/gemini-2.5-flash' => [
                'label' => 'Gemini 2.5 Flash (OpenRouter)',
                'description' => 'Google’s Gemini 2.5 Flash routed through OpenRouter.',
                'best_for' => 'High-speed multimodal tasks and large-context processing.',
                'strengths' => '1,048,576 context window, 65k output tokens, fast throughput.',
                'limitations' => 'OpenRouter routing latency.',
                'context_tokens' => 1048576,
                'max_output_tokens' => 65536,
                'chat' => true,
                'json' => true,
                'vision' => true,
                'embedding' => false,
                'image_output' => false,
            ],
            'google/gemini-2.5-pro' => [
                'label' => 'Gemini 2.5 Pro (OpenRouter)',
                'description' => 'Google’s Gemini 2.5 Pro reasoning flagship routed through OpenRouter.',
                'best_for' => 'In-depth analytics, complex grading, academic synthesis.',
                'strengths' => '2M context, deep reasoning, vision support.',
                'limitations' => 'Higher latency than Flash.',
                'context_tokens' => 2097152,
                'max_output_tokens' => 65536,
                'chat' => true,
                'json' => true,
                'vision' => true,
                'embedding' => false,
                'image_output' => false,
            ],
        ],
    ];

    /**
     * Get all registered models for a provider.
     *
     * @return array<string, array<string, mixed>>
     */
    public function forProvider(string $provider): array
    {
        return self::REGISTRY[$provider] ?? [];
    }

    /**
     * Get details for a specific model under a provider.
     *
     * @return array<string, mixed>|null
     */
    public function find(string $provider, string $modelId): ?array
    {
        return self::REGISTRY[$provider][$modelId] ?? null;
    }

    /**
     * Enrich a raw model definition with registry metadata (descriptions, strengths, limitations).
     *
     * @param  array<string, mixed>  $model
     * @return array<string, mixed>
     */
    public function enrich(string $provider, array $model): array
    {
        $id = (string) ($model['model_id'] ?? $model['id'] ?? '');
        $registered = $this->find($provider, $id);

        if ($registered !== null) {
            return array_merge($model, [
                'label' => $model['label'] ?? $registered['label'],
                'description' => $registered['description'],
                'best_for' => $registered['best_for'],
                'strengths' => $registered['strengths'],
                'limitations' => $registered['limitations'],
                'context_tokens' => $model['context_tokens'] ?? $registered['context_tokens'],
                'max_output_tokens' => $model['max_output_tokens'] ?? $registered['max_output_tokens'],
                'chat' => $model['chat'] ?? $registered['chat'] ?? true,
                'json' => $model['json'] ?? $registered['json'] ?? true,
                'vision' => $model['vision'] ?? $registered['vision'] ?? false,
            ]);
        }

        // Automatic generated profile for dynamically discovered models not yet in the curated catalogue:
        $label = $model['label'] ?? $this->generateLabel($id);
        $context = $model['context_tokens'] ?? null;
        $maxOut = $model['max_output_tokens'] ?? null;

        $desc = sprintf(
            '%s model from %s.',
            $label,
            ucfirst($provider)
        );

        $limitations = [];
        if ($context) {
            $limitations[] = sprintf('%s-token context window', number_format($context));
        }
        if ($maxOut) {
            $limitations[] = sprintf('up to %s output tokens', number_format($maxOut));
        }

        return array_merge($model, [
            'label' => $label,
            'description' => $desc,
            'best_for' => 'General AI tasks matching this provider’s capabilities.',
            'strengths' => sprintf('Verified API model for %s.', ucfirst($provider)),
            'limitations' => $limitations !== [] ? implode(', ', $limitations) : 'Published by provider API.',
        ]);
    }

    private function generateLabel(string $modelId): string
    {
        // Strip provider prefix if present (e.g. "openai/gpt-4o" -> "GPT-4o")
        $parts = explode('/', $modelId);
        $base = end($parts);

        // Convert dashes/underscores to clean title case
        $words = preg_split('/[-_]/', $base) ?: [$base];

        return ucwords(implode(' ', array_map(function ($word) {
            if (in_array(strtolower($word), ['gpt', 'ai', 'r1', 'v3', 'sop', 'pal', 'tts', 'llm', '70b', '8b'], true)) {
                return strtoupper($word);
            }
            return ucfirst($word);
        }, $words)));
    }
}
