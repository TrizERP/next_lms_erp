<?php

namespace Tests\Unit;

use App\Domain\AI\Configuration\ResolvedAiConfiguration;
use App\Domain\AI\Support\FallbackModelClient;
use App\Domain\AI\Support\ModelClient;
use PHPUnit\Framework\TestCase;

/**
 * A model that is out of quota must not stop a sentence being read: a sibling model is asked.
 */
class FallbackModelClientTest extends TestCase
{
    /** @param  array<string, string|null|\Throwable>  $replies  model name ('' = the configured one) => reply */
    private function primary(array $replies): object
    {
        return new class($replies) implements ModelClient {
            /** @var array<int, string> */
            public array $asked = [];

            public function __construct(private array $replies)
            {
            }

            public function isConfigured(): bool
            {
                return true;
            }

            public function forInstitute(int|string|null $subInstituteId): static
            {
                return $this;
            }

            public function withConfiguration(ResolvedAiConfiguration $configuration): static
            {
                return $this;
            }

            public function defaultModel(): string
            {
                return 'configured-model';
            }

            public function chat(array $messages, ?string $model = null, ?int $maxTokens = null, ?float $temperature = null, bool $expectJson = false, ?int $timeout = null): ?string
            {
                $this->asked[] = $model ?? '';
                $reply = $this->replies[$model ?? ''] ?? null;

                if ($reply instanceof \Throwable) {
                    throw $reply;
                }

                return $reply;
            }

            public function stream(array $messages, callable $onDelta, ?string $model = null, ?int $maxTokens = null, ?float $temperature = null): ?string
            {
                return null;
            }

            public function json(array $messages, ?string $model = null, int $maxTokens = 900, float $temperature = 0.0): ?array
            {
                return null;
            }
        };
    }

    public function test_a_working_model_is_the_only_one_asked(): void
    {
        $primary = $this->primary(['' => 'from the configured model']);

        $this->assertSame('from the configured model', (new FallbackModelClient($primary, ['b', 'c']))->chat([]));
        $this->assertSame([''], $primary->asked);
    }

    public function test_when_it_fails_or_throws_the_next_model_answers(): void
    {
        $primary = $this->primary(['' => new \RuntimeException('quota'), 'b' => null, 'c' => 'from c']);

        $this->assertSame('from c', (new FallbackModelClient($primary, ['b', 'c']))->chat([]));
        $this->assertSame(['', 'b', 'c'], $primary->asked);
    }

    public function test_when_every_model_fails_the_answer_is_null_not_an_exception(): void
    {
        $primary = $this->primary([]);

        $this->assertNull((new FallbackModelClient($primary, ['b', 'c']))->chat([]));
    }

    public function test_a_caller_that_names_its_own_model_gets_exactly_that_model(): void
    {
        $primary = $this->primary(['named' => null, 'b' => 'should not be used']);

        $this->assertNull((new FallbackModelClient($primary, ['b']))->chat([], 'named'));
        $this->assertSame(['named'], $primary->asked);
    }

    public function test_the_configured_model_is_not_asked_twice(): void
    {
        $primary = $this->primary(['configured-model' => 'x']);

        $this->assertNull((new FallbackModelClient($primary, ['configured-model']))->chat([]));
        $this->assertSame([''], $primary->asked);
    }
}
