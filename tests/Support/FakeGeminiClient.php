<?php

namespace Tests\Support;

use App\Services\Homework\GeminiClient;
use Throwable;

/**
 * A GeminiClient that never leaves the process.
 *
 * Hand it the replies in the order they will be asked for. Each item is one of:
 *   - a Throwable      -> thrown, to exercise the failure paths;
 *   - a string         -> returned as the model's text;
 *   - an array         -> json_encoded and returned as the text;
 *   - ['__text' => ..., 'finish_reason' => 'MAX_TOKENS'] -> that text with that finish reason.
 *
 * The parent constructor is skipped on purpose: it reads the API key from
 * config, and a test double has no business holding one.
 */
class FakeGeminiClient extends GeminiClient
{
    /** @var array<int,array<int,array<string,mixed>>> Every `parts` array received, in order. */
    public array $calls = [];

    /** @var array<int,array<string,mixed>> Every generation config received, in order. */
    public array $configs = [];

    /** @param array<int,mixed> $replies */
    public function __construct(private array $replies = [])
    {
    }

    public function isConfigured(): bool
    {
        return true;
    }

    /** The double is not tenant-scoped; keep one instance so recorded calls stay visible. */
    public function forInstitute(int|string|null $subInstituteId): static
    {
        return $this;
    }

    public function generateContent(array $parts, array $generationConfig = []): array
    {
        $this->calls[] = $parts;
        $this->configs[] = $generationConfig;

        if ($this->replies === []) {
            throw new \RuntimeException('FakeGeminiClient was asked for more replies than it was given.');
        }

        $next = array_shift($this->replies);

        if ($next instanceof Throwable) {
            throw $next;
        }

        $finish = '';

        if (is_array($next) && array_key_exists('__text', $next)) {
            $finish = (string) ($next['finish_reason'] ?? '');
            $next = $next['__text'];
        }

        return [
            'text' => is_array($next) ? json_encode($next) : (string) $next,
            'raw' => [],
            'model' => 'fake',
            'finish_reason' => $finish,
        ];
    }

    /** The text of the first prompt part of call number $index. */
    public function promptText(int $index = 0): string
    {
        return (string) ($this->calls[$index][0]['text'] ?? '');
    }
}
