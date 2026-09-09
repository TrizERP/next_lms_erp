<?php

namespace Tests\Unit;

use App\Domain\AI\Lifecycle\LifecyclePipeline;
use App\Domain\AI\Lifecycle\LifecycleStage;
use App\Domain\AI\Lifecycle\Modules\ModuleCapability;
use App\Domain\AI\Lifecycle\StageContext;
use App\Domain\AI\Lifecycle\StageKey;
use App\Domain\AI\Lifecycle\StageOutcome;
use App\Services\Mcp\McpRequestContext;
use RuntimeException;
use Tests\TestCase;

/**
 * Streaming must not become a second contract.
 *
 * A lifecycle turn runs twelve stages and takes seconds; the stages were already the
 * most informative thing the platform knew, and were being withheld until the end purely
 * because the result came back as one JSON body. The `onStage` hook fixes that.
 *
 * The risk is drift. If a streamed stage is *nearly* the same shape as a stored one,
 * LifecycleTrace.tsx renders one of them subtly wrong and nobody notices until a field
 * is missing in production. So the assertion that matters here is not "an event fired"
 * but "the event is the same object the JSON route would have returned".
 *
 * No database, no provider, no HTTP.
 */
class LifecycleStreamTest extends TestCase
{
    public function test_every_stage_is_announced_as_it_settles(): void
    {
        $pipeline = new LifecyclePipeline([
            $this->stage(StageKey::Conversation, StageOutcome::ran('One.')),
            $this->stage(StageKey::GenerativeAi, StageOutcome::skipped('Two.')),
            $this->stage(StageKey::Planning, StageOutcome::ran('Three.')),
        ]);

        $seen = [];

        $trace = $pipeline->run($this->context(), function (StageKey $key, StageOutcome $outcome) use (&$seen): void {
            $seen[] = $key->value;
        });

        $this->assertSame(
            ['conversation', 'generative_ai', 'planning'],
            $seen,
            'Stages must be announced in execution order, as they settle.'
        );
        $this->assertSame('One.', $trace->outcomeOf(StageKey::Conversation)->summary);
    }

    public function test_a_streamed_stage_is_identical_to_the_stored_one(): void
    {
        // The contract LifecycleTrace.tsx renders. Anything less than field-for-field
        // parity means the streamed ladder and the stored one drift.
        $pipeline = new LifecyclePipeline([
            $this->stage(StageKey::Conversation, StageOutcome::ran('Thread opened.', ['turn' => 1])),
            $this->stage(StageKey::Planning, StageOutcome::blocked('Nothing to plan.')),
        ]);

        $streamed = [];

        $trace = $pipeline->run($this->context(), function (StageKey $key, StageOutcome $outcome) use (&$streamed): void {
            $streamed[$key->value] = $outcome->toArray($key);
        });

        // Compared over the stages this pipeline registers. A LifecycleTrace always
        // carries all twelve keys — unregistered ones stay not_reached — and only the
        // registered ones are ever announced. The live pipeline registers all twelve,
        // which the end-to-end run confirms with twelve stage events.
        $this->assertNotSame([], $streamed);

        foreach ($trace->toArray() as $stored) {
            if (! array_key_exists($stored['key'], $streamed)) {
                continue;
            }

            $this->assertSame(
                $stored,
                $streamed[$stored['key']],
                "The streamed {$stored['key']} stage differs from the stored one."
            );
        }

        $this->assertSame(['conversation', 'planning'], array_keys($streamed));
    }

    public function test_the_streamed_stage_carries_every_field_the_frontend_reads(): void
    {
        // Named explicitly rather than left to the parity test: these are the fields of
        // the TraceStage interface in lib/intelligence/types.ts, and a stage missing one
        // renders as a blank row rather than an error.
        $pipeline = new LifecyclePipeline([
            $this->stage(StageKey::Conversation, StageOutcome::ran('Thread opened.')),
        ]);

        $captured = null;

        $pipeline->run($this->context(), function (StageKey $key, StageOutcome $outcome) use (&$captured): void {
            $captured = $outcome->toArray($key);
        });

        foreach ([
            'key', 'order', 'layer', 'status', 'summary',
            'component', 'surface', 'data', 'records', 'verify',
            'duration_ms', 'note',
        ] as $field) {
            $this->assertArrayHasKey($field, $captured, "A streamed stage is missing {$field}.");
        }
    }

    public function test_stages_after_a_halt_are_announced_as_not_reached(): void
    {
        // A client draws twelve rows. If the ladder simply stopped emitting, the rest
        // would sit as spinners for ever rather than showing why they were skipped.
        $pipeline = new LifecyclePipeline([
            $this->stage(StageKey::Conversation, StageOutcome::ran('One.')),
            $this->stage(StageKey::Planning, StageOutcome::blocked('Stop.')->halting('Nothing downstream can run.')),
            $this->stage(StageKey::RealData, StageOutcome::ran('Never runs.')),
        ]);

        $seen = [];

        $pipeline->run($this->context(), function (StageKey $key, StageOutcome $outcome) use (&$seen): void {
            $seen[$key->value] = $outcome->status->value;
        });

        $this->assertSame('ran', $seen['conversation']);
        $this->assertSame('blocked', $seen['planning']);
        $this->assertSame('not_reached', $seen['real_data'], 'A halted stage must still be announced.');
    }

    public function test_a_broken_client_does_not_break_the_turn(): void
    {
        // The ordinary way this fails is a user closing the tab mid-answer. The work is
        // still worth finishing and recording — the trace is the record.
        $pipeline = new LifecyclePipeline([
            $this->stage(StageKey::Conversation, StageOutcome::ran('One.')),
            $this->stage(StageKey::Planning, StageOutcome::ran('Two.')),
        ]);

        $trace = $pipeline->run($this->context(), function (): void {
            throw new RuntimeException('client hung up');
        });

        $this->assertSame('ran', $trace->outcomeOf(StageKey::Conversation)->status->value);
        $this->assertSame('Two.', $trace->outcomeOf(StageKey::Planning)->summary);
    }

    public function test_the_pipeline_still_runs_without_an_observer(): void
    {
        // The JSON route passes nothing, and must be unaffected.
        $pipeline = new LifecyclePipeline([
            $this->stage(StageKey::Conversation, StageOutcome::ran('One.')),
        ]);

        $trace = $pipeline->run($this->context());

        $this->assertSame('One.', $trace->outcomeOf(StageKey::Conversation)->summary);
    }

    // ------------------------------------------------------------------- helpers

    private function stage(StageKey $key, StageOutcome $outcome): LifecycleStage
    {
        return new class($key, $outcome) implements LifecycleStage
        {
            public function __construct(
                private readonly StageKey $key,
                private readonly StageOutcome $outcome,
            ) {
            }

            public function key(): StageKey
            {
                return $this->key;
            }

            public function run(StageContext $context): StageOutcome
            {
                return $this->outcome;
            }
        };
    }

    private function context(): StageContext
    {
        return new StageContext(
            question: 'Anything.',
            scope: new McpRequestContext(
                userId: 1,
                role: 'admin',
                selectedInstituteId: 1,
                allowedInstituteIds: [1],
                userProfileId: null,
                clientId: null,
                academicYear: 2026,
                termId: null,
                isAdmin: true,
                isStudent: false,
            ),
            module: new ModuleCapability(key: 'general', label: 'General'),
        );
    }
}
