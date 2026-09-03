<?php

namespace Tests\Unit;

use App\Domain\AI\Lifecycle\Flows\AdmissionsFlow;
use App\Domain\AI\Lifecycle\Modules\ModuleCapability;
use App\Domain\AI\Lifecycle\StageContext;
use App\Domain\AI\Lifecycle\Support\McpToolCaller;
use App\Domain\AI\Support\OpenRouterClient;
use App\Services\Mcp\McpRequestContext;
use PHPUnit\Framework\TestCase;

/**
 * The admission confirmation flow.
 *
 * The first test here is the one that matters. The flow reads readiness out of a
 * ToolResult, whose findings live under `data` — and reading them from the top level
 * instead found nothing, which is indistinguishable from "nothing is missing". The flow
 * announced that an admission with four empty required columns was ready to confirm, and
 * the only thing between that and a fabricated student record was the person who had to
 * say yes.
 *
 * So: a validation that does not clearly say what is missing must never resolve to
 * "ready". Every other assertion here is secondary to that one.
 */
class AdmissionsFlowTest extends TestCase
{
    /**
     * @param  array<string, mixed>|null  $validationPayload
     */
    private function flow(?array $validationPayload, array $extracted = []): AdmissionsFlow
    {
        $caller = new class($validationPayload) extends McpToolCaller
        {
            /** @var array<int, string> */
            public array $calls = [];

            public function __construct(private readonly ?array $payload)
            {
            }

            public function call(
                StageContext $context,
                string $tool,
                array $arguments,
                string $why,
                ?string $confirmationToken = null
            ): ?array {
                $this->calls[] = $tool;

                return $tool === 'admissions.validateConfirmation' ? $this->payload : ['success' => true];
            }
        };

        $llm = new class($extracted) extends OpenRouterClient
        {
            public function __construct(private readonly array $extracted)
            {
            }

            public function isConfigured(): bool
            {
                return true;
            }

            public function json(array $messages, string $model, int $maxTokens = 900, float $temperature = 0.0): ?array
            {
                return ['fields' => $this->extracted];
            }
        };

        return new AdmissionsFlow($caller, $llm);
    }

    private function context(string $question = 'Confirm the admission for enquiry 21'): StageContext
    {
        return new StageContext(
            question: $question,
            scope: new McpRequestContext(1, 'admin', 1, [1], null, null, 2022, null, true, false),
            module: new ModuleCapability(
                key: 'admissions',
                label: 'Admissions',
                capabilities: ['conversational' => true],
                mcpTools: [
                    'admissions.validateConfirmation',
                    'admissions.updateEnquiry',
                    'admissions.confirm',
                ],
            ),
        );
    }

    public function test_a_validation_that_does_not_say_what_is_missing_is_never_ready(): void
    {
        // The exact shape that caused the bug: a successful ToolResult whose findings
        // are under `data`, read from the top level and therefore absent.
        $flow = $this->flow(['success' => true, 'data' => ['ready' => false]]);

        $result = $flow->start($this->context(), 21);

        $this->assertSame('blocked', $result['state']);
        $this->assertNotSame('ready', $result['state']);
    }

    public function test_a_failed_validation_is_blocked_rather_than_ready(): void
    {
        $flow = $this->flow(['success' => false, 'error' => ['code' => 'RECORD_NOT_FOUND'], 'data' => null]);

        $this->assertSame('blocked', $flow->start($this->context(), 999)['state']);
    }

    public function test_missing_fields_put_the_flow_into_collecting(): void
    {
        $flow = $this->flow(['success' => true, 'data' => [
            'ready' => false,
            'already_confirmed' => false,
            'missing_fields' => [
                ['field' => 'enrollment_no', 'label' => 'Enrollment number'],
                ['field' => 'student_quota', 'label' => 'Quota'],
            ],
        ]]);

        $result = $flow->start($this->context(), 21);

        $this->assertSame('collecting', $result['state']);
        $this->assertCount(2, $result['missing']);
        $this->assertSame(
            ['enrollment_no', 'student_quota'],
            $result['pending']['missing']
        );
        $this->assertSame(AdmissionsFlow::KIND, $result['pending']['kind']);
    }

    public function test_an_empty_missing_list_is_ready(): void
    {
        $flow = $this->flow(['success' => true, 'data' => [
            'ready' => true,
            'already_confirmed' => false,
            'missing_fields' => [],
        ]]);

        $result = $flow->start($this->context(), 21);

        $this->assertSame('ready', $result['state']);
        $this->assertSame('ready', $result['pending']['state']);
    }

    public function test_an_already_confirmed_admission_ends_the_task(): void
    {
        $flow = $this->flow(['success' => true, 'data' => [
            'ready' => false,
            'already_confirmed' => true,
            'missing_fields' => [],
        ]]);

        $result = $flow->start($this->context(), 21);

        $this->assertSame('already_confirmed', $result['state']);
        $this->assertNull($result['pending'], 'A finished task must not linger on the thread.');
    }

    public function test_extraction_only_accepts_fields_that_are_actually_missing(): void
    {
        // A loose reply must not be able to rewrite a field nobody asked about — a name
        // that was already correct should not be overwritten because the model returned
        // one.
        $flow = $this->flow(
            ['success' => true, 'data' => [
                'ready' => false,
                'already_confirmed' => false,
                'missing_fields' => [['field' => 'enrollment_no', 'label' => 'Enrollment number']],
            ]],
            ['enrollment_no' => '2026-0481', 'first_name' => 'Someone Else']
        );

        $result = $flow->advance($this->context('enrollment number 2026-0481'), [
            'kind' => AdmissionsFlow::KIND,
            'state' => 'collecting',
            'enquiry_id' => 21,
            'missing' => ['enrollment_no'],
        ]);

        $this->assertSame(['enrollment_no' => '2026-0481'], $result['supplied']);
        $this->assertArrayNotHasKey('first_name', $result['supplied']);
    }

    public function test_a_pending_task_is_only_recognised_when_it_is_this_flow(): void
    {
        $flow = $this->flow(null);

        $this->assertNull($flow->pending([]));
        $this->assertNull($flow->pending(['pending_action' => ['kind' => 'something_else']]));
        $this->assertNotNull($flow->pending(['pending_action' => ['kind' => AdmissionsFlow::KIND]]));
    }

    /**
     * @dataProvider cancellations
     */
    public function test_cancellation_wording_is_recognised(string $said, bool $expected): void
    {
        $this->assertSame($expected, $this->flow(null)->looksLikeCancel($said));
    }

    /**
     * @return array<int, array{0:string, 1:bool}>
     */
    public static function cancellations(): array
    {
        return [
            ['cancel', true],
            ['never mind', true],
            ['forget it', true],
            ['stop', true],
            ['enrollment number 2026-0481', false],
            ['yes go ahead', false],
        ];
    }

    public function test_an_approval_only_fires_from_the_ready_state(): void
    {
        // "Yes" while fields are still missing is agreement with the request for them,
        // not authorisation to create a student record.
        $flow = $this->flow(['success' => true, 'data' => [
            'ready' => false,
            'already_confirmed' => false,
            'missing_fields' => [['field' => 'enrollment_no', 'label' => 'Enrollment number']],
        ]]);

        $result = $flow->advance($this->context('yes'), [
            'kind' => AdmissionsFlow::KIND,
            'state' => 'collecting',
            'enquiry_id' => 21,
            'missing' => ['enrollment_no'],
        ]);

        $this->assertSame('collecting', $result['state']);
        $this->assertNotSame('confirmed', $result['state']);
    }
}
