<?php

namespace Tests\Feature\ExamEvaluation;

use App\Jobs\IdentifyAnswerSheetJob;
use App\Domain\AI\Configuration\ResolvedAiConfiguration;
use App\Services\Evaluation\AnswerSheetReaderService;
use App\Services\Homework\Exceptions\DocumentExtractionException;
use App\Services\Homework\Exceptions\ModelQuotaExhaustedException;
use App\Services\Homework\Exceptions\ProviderBusyException;
use App\Services\Homework\Exceptions\ProviderConfigurationException;
use App\Services\Homework\Exceptions\RateLimitedException;
use App\Services\Homework\GeminiClient;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\FakeGeminiClient;

/**
 * "The AI provider rejected the configured credential" -- found on a real machine where the
 * key in use was a Vertex AI key (53 characters, "AQ.") sent to the Gemini Developer API, which
 * only accepts Google AI Studio keys ("AIza", 39 characters). Google answers that with a bare
 * 401 that does not say which half is wrong.
 *
 * Three things were wrong, and are pinned here: the message gave an administrator nothing to go
 * on; the first stage (reading the student) recorded it as "this scan was unreadable", hiding the
 * cause until marking failed; and nobody was told before uploading a class set to a service
 * that could not read any of it. No test here reaches Google or uses a real key.
 */
class GeminiCredentialDiagnosisTest extends ExamEvaluationTestCase
{
    use IdentityTestHelpers;

    private const MAIN = 'https://generativelanguage.googleapis.com/v1beta/models/main-model:generateContent';

    // Shaped like the real thing, and obviously not real.
    private const VERTEX_KEY = 'AQ.Ab8RN6FAKEFAKEFAKEFAKEFAKEFAKEFAKEFAKEFAKEFAKE';   // 53 chars
    private const STUDIO_KEY = 'AIzaSyFAKEFAKEFAKEFAKEFAKEFAKEFAKEFAKE12';           // 39 chars
    private const TOKEN_KEY = 'ya29.a0AfH6SMFAKEFAKEFAKEFAKEFAKEFAKE';

    private int $batchId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPaper();
        $this->setUpScan();
        $this->batchId = $this->makeBatch(['status' => 'Processing']);
        config(['ai.provider.gemini.fallback_model' => '']);
        // What Homework & Assignment Evaluation resolves to: the exam summary asks the container for its client.
        $this->app->instance(GeminiClient::class, $this->clientWith(self::STUDIO_KEY));
    }

    protected function tearDown(): void
    {
        $this->tearDownScan();
        parent::tearDown();
    }

    private function clientWith(string $key): GeminiClient
    {
        return (new GeminiClient())->forConfiguration(new ResolvedAiConfiguration('gemini', 'main-model', $key, 'test'));
    }

    private function rejectWith(int $status, string $message = 'Request had invalid authentication credentials. Expected OAuth 2 access token, login cookie or other valid authentication credential.'): void
    {
        Http::fake([self::MAIN => Http::response(['error' => ['code' => $status, 'message' => $message, 'status' => 'UNAUTHENTICATED']], $status)]);
    }

    // -- what the format alone says --------------------------------------------------------

    public function test_a_vertex_key_is_recognised_and_explained(): void
    {
        $problem = $this->clientWith(self::VERTEX_KEY)->credentialProblem();

        $this->assertStringContainsString('Vertex AI key', $problem);
        $this->assertStringContainsString('Google AI Studio', $problem);
        $this->assertStringContainsString('AIza', $problem);
    }

    public function test_an_oauth_token_is_recognised_and_explained(): void
    {
        $this->assertStringContainsString('OAuth access token', $this->clientWith(self::TOKEN_KEY)->credentialProblem());
    }

    public function test_an_empty_key_says_the_service_is_not_set_up(): void
    {
        foreach (['', '   '] as $blank) {
            $this->assertStringContainsString('no AI provider configured', (string) $this->clientWith($blank)->credentialProblem());
        }
    }

    public function test_a_proper_ai_studio_key_and_an_unfamiliar_format_raise_no_alarm(): void
    {
        $this->assertNull($this->clientWith(self::STUDIO_KEY)->credentialProblem());
        // A new format Google may introduce is not called wrong on a guess; a real rejection still is, by the 401.
        $this->assertNull($this->clientWith('gk_live_somethingNew1234567890')->credentialProblem());
        $this->assertNull($this->clientWith('  ' . self::STUDIO_KEY . "\n")->credentialProblem(), 'surrounding whitespace does not matter');
    }

    public function test_the_diagnosis_never_repeats_the_key(): void
    {
        foreach ([self::VERTEX_KEY, self::TOKEN_KEY] as $key) {
            $this->assertStringNotContainsString($key, (string) $this->clientWith($key)->credentialProblem());
            $this->assertStringNotContainsString(substr($key, 0, 12), (string) $this->clientWith($key)->credentialProblem(), 'not even its start');
        }
    }

    // -- a rejected key ---------------------------------------------------------------------

    public function test_a_rejected_vertex_key_is_reported_as_a_configuration_fault_with_the_reason(): void
    {
        $this->rejectWith(401);

        try {
            $this->clientWith(self::VERTEX_KEY)->generateContent([['text' => 'hi']]);
            $this->fail('should have raised');
        } catch (\RuntimeException $exception) {
            $this->assertInstanceOf(ProviderConfigurationException::class, $exception);
            $message = $exception->getMessage();

            $this->assertStringContainsString('saved Gemini key', $message);
            $this->assertStringContainsString('looks like a Vertex AI key', $message);
            $this->assertStringContainsString('Request had invalid authentication credentials', $message, 'the provider\'s own words are kept');
            $this->assertStringNotContainsString(self::VERTEX_KEY, $message);
            // `failure_reason` keeps 250 characters: the fix ("AIza" key from AI Studio) must survive that cut.
            $this->assertStringContainsString('starts with “AIza”', mb_substr($message, 0, 250));
        }

        Http::assertSentCount(1);   // a refused credential is not retried
    }

    public function test_a_rejected_ai_studio_key_gets_the_plain_message_without_a_wrong_hint(): void
    {
        $this->rejectWith(403, 'API key not valid. Please pass a valid API key.');

        try {
            $this->clientWith(self::STUDIO_KEY)->generateContent([['text' => 'hi']]);
            $this->fail('should have raised');
        } catch (ProviderConfigurationException $exception) {
            $this->assertStringContainsString('saved Gemini key is missing, disabled or no longer valid', $exception->getMessage());
            $this->assertStringNotContainsString('Vertex', $exception->getMessage());
        }
    }

    public function test_a_missing_key_is_a_configuration_fault_raised_before_any_request(): void
    {
        Http::fake();

        try {
            $this->clientWith('')->generateContent([['text' => 'hi']]);
            $this->fail('should have raised');
        } catch (ProviderConfigurationException $exception) {
            $this->assertStringContainsString('AI Providers', $exception->getMessage());
        }

        Http::assertNothingSent();
    }

    public function test_it_is_still_a_runtime_exception_so_every_existing_caller_behaves_as_before(): void
    {
        $this->assertInstanceOf(\RuntimeException::class, new ProviderConfigurationException('x'));
    }

    /** @return array<string,array{0:int,1:class-string}> */
    public static function otherFailures(): array
    {
        return [
            'busy' => [503, ProviderBusyException::class],
            'rate limit' => [429, RateLimitedException::class],
        ];
    }

    #[DataProvider('otherFailures')]
    public function test_other_failures_keep_their_own_types(int $status, string $type): void
    {
        $this->rejectWith($status, 'something else');

        try {
            $this->clientWith(self::VERTEX_KEY)->generateContent([['text' => 'hi']]);
            $this->fail('should have raised');
        } catch (\RuntimeException $exception) {
            $this->assertInstanceOf($type, $exception);
            $this->assertNotInstanceOf(ProviderConfigurationException::class, $exception);
            $this->assertStringNotContainsString('Vertex', $exception->getMessage(), 'a busy or rate-limited provider is not the key\'s fault');
        }
    }

    public function test_a_malformed_request_or_unknown_model_is_not_blamed_on_the_key(): void
    {
        foreach ([400, 404] as $status) {
            $this->rejectWith($status, 'nope');

            try {
                $this->clientWith(self::VERTEX_KEY)->generateContent([['text' => 'hi']]);
                $this->fail('should have raised');
            } catch (\RuntimeException $exception) {
                $this->assertNotInstanceOf(ProviderConfigurationException::class, $exception, (string) $status);
            }
        }
    }

    public function test_the_key_is_never_written_to_the_logs_when_it_is_rejected(): void
    {
        $logged = [];
        Event::listen(MessageLogged::class, function (MessageLogged $event) use (&$logged) {
            $logged[] = $event->message . ' ' . json_encode($event->context);
        });
        $this->rejectWith(401);

        try {
            $this->clientWith(self::VERTEX_KEY)->generateContent([['text' => 'hi']]);
        } catch (\RuntimeException) {
            // expected
        }

        $this->assertNotEmpty($logged, 'the failure is logged');

        foreach ($logged as $line) {
            $this->assertStringNotContainsString(self::VERTEX_KEY, $line);
            $this->assertStringNotContainsString('AQ.Ab8', $line);
        }
    }

    // -- the reader keeps the cause -----------------------------------------------------------

    public function test_the_reader_keeps_the_providers_exception_so_a_job_can_tell_the_cause(): void
    {
        $reader = new AnswerSheetReaderService(new FakeGeminiClient([new ProviderConfigurationException('The AI provider rejected the configured credential.')]));

        try {
            $reader->readIdentity($this->scanPath, 'application/pdf');
            $this->fail('should have raised');
        } catch (DocumentExtractionException $exception) {
            $this->assertInstanceOf(ProviderConfigurationException::class, $exception->getPrevious());
            $this->assertStringContainsString('rejected the configured credential', $exception->getMessage());
        }
    }

    // -- the first stage fails visibly, and recovers ---------------------------------------------

    public function test_a_rejected_key_fails_the_sheet_with_the_real_reason_not_as_an_unreadable_scan(): void
    {
        $sheetId = $this->identifyingSheet($this->batchId);

        $this->identify($sheetId, new FakeGeminiClient([new ProviderConfigurationException('The AI provider rejected the configured credential. The saved Gemini key is missing, disabled or no longer valid - an administrator can fix it in AI Providers.')]));

        $sheet = $this->sheetRow($sheetId);
        $this->assertSame('Failed', $sheet->status);
        $this->assertStringContainsString('saved Gemini key', $sheet->failure_reason);
        $this->assertSame([], $this->identityBlock($sheetId), 'no "unreadable" verdict was recorded about the scan');
        $this->assertNull($sheet->student_id);
        $this->assertSame('Review', DB::table('exam_evaluation_batch')->find($this->batchId)->status, 'the batch is settled, not "Processing" forever');
    }

    public function test_a_spent_allowance_fails_the_sheet_at_the_identity_stage_too(): void
    {
        $sheetId = $this->identifyingSheet($this->batchId);

        $this->identify($sheetId, new FakeGeminiClient([new ModelQuotaExhaustedException('The daily allowance for the model is spent.')]));

        $sheet = $this->sheetRow($sheetId);
        $this->assertSame('Failed', $sheet->status);
        $this->assertStringContainsString('daily allowance', $sheet->failure_reason);
    }

    public function test_a_scan_that_really_is_unreadable_is_still_left_for_a_teacher(): void
    {
        $sheetId = $this->identifyingSheet($this->batchId);

        $this->identify($sheetId, new FakeGeminiClient(['not json', 'still not json']));

        $this->assertSame('Awaiting identity', $this->sheetRow($sheetId)->status);
        $this->assertSame('unreadable', $this->identityBlock($sheetId)['issue']);
    }

    public function test_after_the_key_is_fixed_the_failed_sheet_is_read_again_and_waits_for_a_teacher(): void
    {
        Queue::fake();
        $sheetId = $this->identifyingSheet($this->batchId);
        $this->identify($sheetId, new FakeGeminiClient([new ProviderConfigurationException('The AI provider rejected the configured credential.')]));
        $this->assertSame('Failed', $this->sheetRow($sheetId)->status);

        // An administrator replaces the key; a teacher presses "read again".
        $this->postJson("/api/exam-evaluation/sheets/{$sheetId}/reprocess", $this->body())->assertOk();
        Queue::assertPushed(IdentifyAnswerSheetJob::class, 1);
        $this->assertSame('Identifying', $this->sheetRow($sheetId)->status);

        $this->identify($sheetId, new FakeGeminiClient([$this->identityReply()]));

        $sheet = $this->sheetRow($sheetId);
        $this->assertSame('Awaiting identity', $sheet->status);
        $this->assertNull($sheet->failure_reason);
        $this->assertSame(101, (int) $sheet->student_id);
    }

    public function test_marking_still_fails_a_sheet_once_with_the_reason_as_before(): void
    {
        $sheetId = $this->bareSheet($this->batchId, [
            'status' => 'Pending', 'student_id' => 101, 'identity_source' => 'manual', 'identity_confirmed_at' => now(),
        ]);

        $this->evaluate($sheetId, new FakeGeminiClient([new ProviderConfigurationException('The AI provider rejected the configured credential.')]));

        $sheet = $this->sheetRow($sheetId);
        $this->assertSame('Failed', $sheet->status);
        $this->assertStringContainsString('rejected the configured credential', $sheet->failure_reason);
        $this->assertSame(101, (int) $sheet->student_id, 'the confirmed student is untouched by a failed attempt');
    }

    // -- told before anything is uploaded ----------------------------------------------------------------

    public function test_the_exam_summary_warns_about_a_vertex_key_before_any_upload(): void
    {
        $this->app->instance(GeminiClient::class, $this->clientWith(self::VERTEX_KEY));

        $response = $this->getJson('/api/exam-evaluation/answer-key/1?sub_institute_id=' . self::TENANT)->assertOk();

        $this->assertStringContainsString('Vertex AI key', (string) $response->json('data.ai_warning'));
        $this->assertStringNotContainsString(self::VERTEX_KEY, $response->getContent(), 'the key is never echoed');
    }

    public function test_the_exam_summary_has_no_warning_for_a_proper_key(): void
    {
        $this->getJson('/api/exam-evaluation/answer-key/1?sub_institute_id=' . self::TENANT)
            ->assertOk()->assertJsonPath('data.ai_warning', null);
    }

    public function test_the_exam_summary_says_when_there_is_no_key_at_all(): void
    {
        $this->app->instance(GeminiClient::class, $this->clientWith(''));

        $this->getJson('/api/exam-evaluation/answer-key/1?sub_institute_id=' . self::TENANT)
            ->assertOk()
            ->assertJsonPath('data.ai_warning', fn ($warning) => str_contains((string) $warning, 'no AI provider configured'));
    }

    public function test_the_check_makes_no_request_to_any_provider(): void
    {
        Http::fake();
        $this->app->instance(GeminiClient::class, $this->clientWith(self::VERTEX_KEY));

        $this->getJson('/api/exam-evaluation/answer-key/1?sub_institute_id=' . self::TENANT)->assertOk();

        Http::assertNothingSent();
    }
}
