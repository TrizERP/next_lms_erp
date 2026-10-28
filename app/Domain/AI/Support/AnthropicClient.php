<?php

namespace App\Domain\AI\Support;

use App\Domain\AI\Configuration\ProviderCatalog;
use App\Domain\AI\Configuration\ResolvedAiConfiguration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * Client for Anthropic Claude via its Messages API.
 *
 * Implements ModelClient and SupportsVisionAnalysis.
 * Separates system messages into the top-level 'system' parameter required by Anthropic,
 * and formats image attachments into base64 content blocks.
 */
class AnthropicClient implements ModelClient, SupportsVisionAnalysis
{
    private const DEFAULT_MODEL = 'claude-3-5-sonnet-20241022';

    private const DEFAULT_MAX_TOKENS = 4096;

    private const DEFAULT_TIMEOUT = 60;

    private const API_VERSION = '2023-06-01';

    private int|string|null $subInstituteId = null;

    private ?ResolvedAiConfiguration $configuration = null;

    public function __construct(private readonly ProviderCatalog $providers)
    {
    }

    public function forInstitute(int|string|null $subInstituteId): static
    {
        $clone = clone $this;
        $clone->subInstituteId = $subInstituteId;

        return $clone;
    }

    public function withConfiguration(ResolvedAiConfiguration $configuration): static
    {
        $clone = clone $this;
        $clone->configuration = $configuration;

        return $clone;
    }

    public function isConfigured(): bool
    {
        return $this->resolveApiKey() !== null;
    }

    public function defaultModel(): string
    {
        return $this->configuration?->model
            ?? $this->providers->defaultModel('anthropic')
            ?? self::DEFAULT_MODEL;
    }

    public function chat(
        array $messages,
        ?string $model = null,
        ?int $maxTokens = null,
        ?float $temperature = null,
        bool $expectJson = false,
        ?int $timeout = null,
    ): ?string {
        $key = $this->requireKey();
        $formatted = $this->formatMessages($messages, $expectJson);

        $body = [
            'model' => $model ?? $this->defaultModel(),
            'max_tokens' => $maxTokens ?? self::DEFAULT_MAX_TOKENS,
            'messages' => $formatted['messages'],
        ];

        if ($formatted['system'] !== null && $formatted['system'] !== '') {
            $body['system'] = $formatted['system'];
        }

        if ($temperature !== null) {
            $body['temperature'] = min(1.0, max(0.0, $temperature));
        }

        $response = Http::withHeaders($this->headers($key))
            ->timeout($timeout ?? self::DEFAULT_TIMEOUT)
            ->post($this->endpoint(), $body);

        if (! $response->successful()) {
            throw new RuntimeException(sprintf(
                'Anthropic API returned %d: %s',
                $response->status(),
                mb_substr($response->body(), 0, 300)
            ));
        }

        return $this->extractText($response->json());
    }

    public function stream(
        array $messages,
        callable $onDelta,
        ?string $model = null,
        ?int $maxTokens = null,
        ?float $temperature = null,
    ): ?string {
        // Fall back to non-streaming chat and call onDelta once with the complete answer
        $text = $this->chat($messages, $model, $maxTokens, $temperature);

        if ($text !== null && $text !== '') {
            $onDelta($text);
        }

        return $text;
    }

    public function json(
        array $messages,
        ?string $model = null,
        ?int $maxTokens = null,
        ?float $temperature = null,
        ?int $timeout = null,
    ): ?array {
        try {
            $text = $this->chat($messages, $model, $maxTokens, $temperature, expectJson: true, timeout: $timeout);

            if ($text === null || trim($text) === '') {
                return null;
            }

            // Extract JSON from possible markdown fences: ```json ... ```
            if (preg_match('/```(?:json)?\s*([\s\S]*?)\s*```/', $text, $matches)) {
                $text = $matches[1];
            }

            $decoded = json_decode(trim($text), true);

            return is_array($decoded) ? $decoded : null;
        } catch (Throwable) {
            return null;
        }
    }

    public function chatWithVision(
        array $messages,
        array $images,
        ?string $model = null,
        ?int $maxTokens = null,
        ?float $temperature = null,
        ?int $timeout = null,
    ): ?string {
        $key = $this->requireKey();
        $formatted = $this->formatMessages($messages, false);

        // Append images to the last user message
        $imageBlocks = [];
        foreach ($images as $img) {
            $mime = $img['mime_type'] ?? 'image/jpeg';
            $data = $img['data'] ?? '';
            if (is_string($data) && $data !== '') {
                // If data is base64 encoded already or raw bytes
                $base64 = base64_encode(base64_decode($data, true) !== false ? base64_decode($data) : $data);
                $imageBlocks[] = [
                    'type' => 'image',
                    'source' => [
                        'type' => 'base64',
                        'media_type' => $mime,
                        'data' => $base64,
                    ],
                ];
            }
        }

        $anthropicMessages = $formatted['messages'];
        if ($imageBlocks !== [] && $anthropicMessages !== []) {
            $lastIndex = count($anthropicMessages) - 1;
            $currentContent = $anthropicMessages[$lastIndex]['content'];

            $contentBlocks = is_string($currentContent)
                ? [['type' => 'text', 'text' => $currentContent]]
                : (array) $currentContent;

            $anthropicMessages[$lastIndex]['content'] = array_merge($imageBlocks, $contentBlocks);
        }

        $body = [
            'model' => $model ?? $this->defaultModel(),
            'max_tokens' => $maxTokens ?? self::DEFAULT_MAX_TOKENS,
            'messages' => $anthropicMessages,
        ];

        if ($formatted['system'] !== null && $formatted['system'] !== '') {
            $body['system'] = $formatted['system'];
        }

        $response = Http::withHeaders($this->headers($key))
            ->timeout($timeout ?? self::DEFAULT_TIMEOUT)
            ->post($this->endpoint(), $body);

        if (! $response->successful()) {
            throw new RuntimeException(sprintf(
                'Anthropic Vision API returned %d: %s',
                $response->status(),
                mb_substr($response->body(), 0, 300)
            ));
        }

        return $this->extractText($response->json());
    }

    public function analyzeImage(
        string $prompt,
        string $imageBytes,
        string $mimeType,
        ?string $model = null,
        ?int $timeout = null,
    ): ?string {
        $key = $this->requireKey();
        $base64 = base64_encode($imageBytes);

        $body = [
            'model' => $model ?? $this->defaultModel(),
            'max_tokens' => self::DEFAULT_MAX_TOKENS,
            'messages' => [
                [
                    'role' => 'user',
                    'content' => [
                        [
                            'type' => 'image',
                            'source' => [
                                'type' => 'base64',
                                'media_type' => $mimeType,
                                'data' => $base64,
                            ],
                        ],
                        [
                            'type' => 'text',
                            'text' => $prompt,
                        ],
                    ],
                ],
            ],
        ];

        $response = Http::withHeaders($this->headers($key))
            ->timeout($timeout ?? self::DEFAULT_TIMEOUT)
            ->post($this->endpoint(), $body);

        if (! $response->successful()) {
            throw new RuntimeException(sprintf(
                'Anthropic Vision API returned %d: %s',
                $response->status(),
                mb_substr($response->body(), 0, 300)
            ));
        }

        return $this->extractText($response->json());
    }

    // ---------------------------------------------------------------------

    private function endpoint(): string
    {
        $base = rtrim((string) ($this->providers->baseUrl('anthropic') ?? 'https://api.anthropic.com/v1'), '/');

        return $base . '/messages';
    }

    private function headers(string $apiKey): array
    {
        return [
            'x-api-key' => $apiKey,
            'anthropic-version' => self::API_VERSION,
            'content-type' => 'application/json',
            'accept' => 'application/json',
        ];
    }

    /**
     * @param  array<int, array{role:string, content:string}>  $messages
     * @return array{system:string|null, messages:array<int, array{role:string, content:string}>}
     */
    private function formatMessages(array $messages, bool $expectJson): array
    {
        $systemParts = [];
        $chatMessages = [];

        if ($expectJson) {
            $systemParts[] = 'You must respond strictly with valid JSON. Do not include markdown formatting or commentary outside the JSON object.';
        }

        foreach ($messages as $m) {
            $role = strtolower((string) ($m['role'] ?? 'user'));
            $content = (string) ($m['content'] ?? '');

            if ($role === 'system') {
                $systemParts[] = $content;
            } else {
                $anthropicRole = $role === 'assistant' ? 'assistant' : 'user';

                // Merge consecutive messages with the same role (Anthropic requires strict alternating roles)
                $count = count($chatMessages);
                if ($count > 0 && $chatMessages[$count - 1]['role'] === $anthropicRole) {
                    $chatMessages[$count - 1]['content'] .= "\n\n" . $content;
                } else {
                    $chatMessages[] = [
                        'role' => $anthropicRole,
                        'content' => $content,
                    ];
                }
            }
        }

        // Anthropic requires the first message to have role 'user'
        if ($chatMessages === []) {
            $chatMessages[] = ['role' => 'user', 'content' => 'Hello'];
        } elseif ($chatMessages[0]['role'] !== 'user') {
            array_unshift($chatMessages, ['role' => 'user', 'content' => 'Continue']);
        }

        return [
            'system' => $systemParts !== [] ? implode("\n\n", $systemParts) : null,
            'messages' => $chatMessages,
        ];
    }

    private function extractText(?array $data): ?string
    {
        if ($data === null) {
            return null;
        }

        $text = '';
        foreach ((array) ($data['content'] ?? []) as $block) {
            if (($block['type'] ?? '') === 'text') {
                $text .= (string) ($block['text'] ?? '');
            }
        }

        return $text !== '' ? $text : null;
    }

    private function requireKey(): string
    {
        $key = $this->resolveApiKey();

        if ($key === null || $key === '') {
            throw new RuntimeException('No API key configured for Anthropic Claude.');
        }

        return $key;
    }

    private function resolveApiKey(): ?string
    {
        if ($this->configuration !== null && $this->configuration->hasKey()) {
            return $this->configuration->apiKey;
        }

        // Database lookup for an active Anthropic key
        $query = DB::table('ai_api_keys')
            ->whereIn('api_type', ['anthropic', 'ANTHROPIC_API_KEY'])
            ->where('status', 1)
            ->where(function ($q) {
                $q->whereNull('sub_institute_id');
                if ($this->subInstituteId !== null) {
                    $q->orWhere('sub_institute_id', $this->subInstituteId);
                }
            })
            ->orderByRaw('sub_institute_id IS NULL ASC')
            ->orderByDesc('id');

        $key = \App\Support\KeyVault::reveal($query->value('api_key'));

        if (is_string($key) && trim($key) !== '' && trim($key) !== '-') {
            return trim($key);
        }

        return $this->providers->envKey('anthropic');
    }
}
