<?php

namespace Tests\Unit\QuestionGeneration\H5p;

use App\Domain\AI\Configuration\AiConfigurationResolver;
use App\Domain\AI\Support\SchemaCache;
use App\Services\QuestionGeneration\H5p\ClaudeQuestionClient;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Question generation uses the key saved in AI Providers — and only that.
 *
 * In-memory SQLite, and Http is faked: no real database, no real key, nothing leaves the machine.
 * It drives the real client -> factory -> resolver -> provider-client path, so what is proven is
 * that a credential saved in `ai_api_keys` is what the request is sent with, and that with none saved
 * the answer is a configuration message — never a demand for an environment variable.
 */
class ClaudeQuestionClientTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.connections.qgen_test' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''],
            'database.default' => 'qgen_test',
            'cache.default' => 'array',
            'ai.provider.gemini.api_key' => 'ENV-KEY-MUST-NOT-BE-USED',
            'ai.allow_env_credentials' => false,
        ]);
        DB::purge('qgen_test');

        Schema::create('ai_api_keys', function ($t) {
            $t->bigIncrements('id');
            $t->string('account_email')->nullable();
            $t->string('api_type')->nullable();
            $t->string('ai_module')->nullable();
            $t->string('model')->nullable();
            $t->unsignedSmallInteger('priority')->nullable();
            $t->text('api_key');
            $t->string('api_limit')->nullable();
            $t->integer('status')->default(1);
            $t->unsignedBigInteger('sub_institute_id')->nullable();
            $t->timestamps();
        });
        Schema::create('ai_models', function ($t) {
            $t->bigIncrements('id');
            $t->string('provider');
            $t->string('model_id');
            $t->string('label');
            $t->unsignedInteger('max_output_tokens')->nullable();
            $t->decimal('input_cost_per_1k', 12, 6)->nullable();
            $t->decimal('output_cost_per_1k', 12, 6)->nullable();
            $t->unsignedInteger('sort_order')->default(0);
            $t->integer('status')->default(1);
            $t->unsignedBigInteger('sub_institute_id')->nullable();
            $t->timestamps();
        });
        Schema::create('ai_usage_events', function ($t) {
            $t->id();
            $t->string('product')->nullable();
            $t->string('ai_module')->nullable();
            $t->string('provider')->nullable();
            $t->string('model')->nullable();
            $t->string('source')->nullable();
            $t->integer('input_tokens')->nullable();
            $t->integer('output_tokens')->nullable();
            $t->integer('latency_ms')->nullable();
            $t->decimal('estimated_cost_usd', 10, 6)->nullable();
            $t->string('outcome')->nullable();
            $t->string('finish_reason')->nullable();
            $t->text('error')->nullable();
            $t->string('related_type')->nullable();
            $t->unsignedBigInteger('related_id')->nullable();
            $t->unsignedBigInteger('user_id')->nullable();
            $t->unsignedBigInteger('sub_institute_id')->nullable();
            $t->timestamps();
        });

        app(SchemaCache::class)->flush();
        app()->forgetInstance(AiConfigurationResolver::class);
    }

    public function test_with_no_key_saved_it_asks_for_ai_providers_not_an_environment_variable(): void
    {
        Http::fake();

        $result = (new ClaudeQuestionClient())->complete('SYS', 'USER', 7);

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('AI provider is not configured', $result['error']);
        $this->assertStringContainsString('AI Providers', $result['error']);
        $this->assertStringNotContainsString('ANTHROPIC', $result['error']);
        $this->assertStringNotContainsString('.env', $result['error']);
        Http::assertNothingSent();
    }

    public function test_a_key_saved_in_ai_providers_is_what_the_request_uses(): void
    {
        DB::table('ai_api_keys')->insert([
            'api_type' => 'gemini', 'api_key' => 'SAVED-IN-AI-PROVIDERS', 'status' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        app(SchemaCache::class)->flush();
        app()->forgetInstance(AiConfigurationResolver::class);

        Http::fake(['generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [['content' => ['parts' => [['text' => '{"questions":[]}']]], 'finishReason' => 'STOP']],
        ])]);

        $result = (new ClaudeQuestionClient())->complete('SYSTEM TEXT', 'USER TEXT', 7);

        $this->assertTrue($result['ok'], $result['error'] ?? '');
        $this->assertSame('{"questions":[]}', $result['content']);

        Http::assertSent(function (Request $request) {
            $this->assertSame('SAVED-IN-AI-PROVIDERS', $request->header('x-goog-api-key')[0]);
            $this->assertStringNotContainsString('ENV-KEY', json_encode($request->headers()));

            return true;
        });

        // The call was recorded against the module, with the provider that really answered.
        $row = DB::table('ai_usage_events')->first();
        $this->assertSame('assessment_ai', $row->ai_module);
        $this->assertSame('gemini', $row->provider);
        $this->assertSame('ok', $row->outcome);
    }

    public function test_another_schools_key_is_never_used(): void
    {
        DB::table('ai_api_keys')->insert([
            'api_type' => 'gemini', 'api_key' => 'SCHOOL-5-ONLY', 'ai_module' => 'assessment_ai', 'sub_institute_id' => 5,
            'status' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
        app(SchemaCache::class)->flush();
        app()->forgetInstance(AiConfigurationResolver::class);
        Http::fake();

        $result = (new ClaudeQuestionClient())->complete('S', 'U', 6);

        $this->assertFalse($result['ok']);
        Http::assertNothingSent();
    }
}
