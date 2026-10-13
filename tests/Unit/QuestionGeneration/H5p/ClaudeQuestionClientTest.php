<?php

namespace Tests\Unit\QuestionGeneration\H5p;

use App\Services\QuestionGeneration\H5p\ClaudeQuestionClient;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The Claude Messages API call. NO DATABASE and no network: Http is faked and the key
 * lookup is replaced, so no credential is read and nothing leaves the machine.
 */
class ClaudeQuestionClientTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['claude.question_generation' => [
            'model' => 'claude-test-model',
            'base_url' => 'https://claude.example',
            'api_version' => '2023-06-01',
            'max_output_tokens' => 1234,
            'timeout_seconds' => 30,
        ]]);
    }

    private function client(?string $key = 'sk-test', ?array &$asked = null): ClaudeQuestionClient
    {
        return new ClaudeQuestionClient(function ($institute) use ($key, &$asked) {
            $asked = [$institute];

            return $key;
        });
    }

    private function reply(array $body, int $status = 200): void
    {
        Http::fake(['claude.example/*' => Http::response($body, $status)]);
    }

    public function test_it_sends_the_documented_request_and_no_sampling_parameters(): void
    {
        $this->reply(['content' => [['type' => 'text', 'text' => '{"questions":[]}']], 'stop_reason' => 'end_turn', 'model' => 'claude-test-model']);

        $this->client()->complete('SYSTEM TEXT', 'USER TEXT', 7);

        Http::assertSent(function (Request $request) {
            $this->assertSame('https://claude.example/v1/messages', $request->url());
            $this->assertSame('sk-test', $request->header('x-api-key')[0]);
            $this->assertSame('2023-06-01', $request->header('anthropic-version')[0]);

            $body = $request->data();
            $this->assertSame('claude-test-model', $body['model']);
            $this->assertSame(1234, $body['max_tokens']);
            $this->assertSame('SYSTEM TEXT', $body['system']);
            $this->assertSame([['role' => 'user', 'content' => 'USER TEXT']], $body['messages']);
            // Claude Opus 5 answers these with HTTP 400.
            foreach (['temperature', 'top_p', 'top_k', 'seed'] as $rejected) {
                $this->assertArrayNotHasKey($rejected, $body);
            }

            return true;
        });
    }

    public function test_the_key_is_resolved_for_the_callers_school(): void
    {
        $this->reply(['content' => [['type' => 'text', 'text' => '{}']], 'stop_reason' => 'end_turn']);
        $asked = null;

        $this->client('sk-test', $asked)->complete('s', 'u', 42);

        $this->assertSame([42], $asked);
    }

    public function test_it_returns_the_text_the_model_wrote_in_the_shape_the_service_reads(): void
    {
        $this->reply([
            'model' => 'claude-actual',
            'stop_reason' => 'end_turn',
            // A thinking block ahead of the text must not be mistaken for the answer.
            'content' => [['type' => 'thinking', 'thinking' => 'hmm'], ['type' => 'text', 'text' => '{"questions":'], ['type' => 'text', 'text' => '[]}']],
            'usage' => ['input_tokens' => 120, 'output_tokens' => 45],
        ]);

        $result = $this->client()->complete('s', 'u');

        $this->assertTrue($result['ok']);
        $this->assertSame('{"questions":[]}', $result['content']);
        $this->assertSame('claude-actual', $result['model']);
        $this->assertSame('end_turn', $result['finish_reason']);
        $this->assertSame(['prompt_tokens' => 120, 'completion_tokens' => 45], $result['usage']);
    }

    public function test_a_truncated_response_is_reported_as_length(): void
    {
        $this->reply(['content' => [['type' => 'text', 'text' => '{"questions":[{']], 'stop_reason' => 'max_tokens']);

        $this->assertSame('length', $this->client()->complete('s', 'u')['finish_reason']);
    }

    public function test_a_refusal_is_an_error_not_content(): void
    {
        $this->reply(['content' => [['type' => 'text', 'text' => 'I cannot help with that.']], 'stop_reason' => 'refusal']);

        $result = $this->client()->complete('s', 'u');

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('declined', $result['error']);
    }

    public function test_an_empty_message_is_an_error(): void
    {
        $this->reply(['content' => [['type' => 'thinking', 'thinking' => 'only thoughts']], 'stop_reason' => 'end_turn']);

        $result = $this->client()->complete('s', 'u');

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('empty', $result['error']);
    }

    public function test_a_missing_key_fails_without_any_request(): void
    {
        Http::fake();

        $result = $this->client(null)->complete('s', 'u');

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('ANTHROPIC_API_KEY', $result['error']);
        Http::assertNothingSent();
    }

    public function test_http_failures_are_explained_in_plain_words(): void
    {
        $cases = [
            401 => 'key was rejected',
            403 => 'not permitted to use claude-test-model',
            404 => 'claude-test-model" was not found',
            429 => 'rate limiting',
            503 => 'temporarily unavailable',
        ];

        foreach ($cases as $status => $expected) {
            $this->reply(['error' => ['message' => 'upstream detail']], $status);
            $result = $this->client()->complete('s', 'u');

            $this->assertFalse($result['ok'], (string) $status);
            $this->assertStringContainsString($expected, $result['error'], (string) $status);
            // The key itself never appears in an error that reaches a user.
            $this->assertStringNotContainsString('sk-test', $result['error']);
        }
    }

    public function test_a_connection_failure_is_an_error_not_an_exception(): void
    {
        Http::fake(['claude.example/*' => fn () => throw new \Illuminate\Http\Client\ConnectionException('timed out')]);

        $result = $this->client()->complete('s', 'u');

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('timed out', $result['error']);
    }
}
