<?php

namespace App\Http\Controllers\AI;

use App\Domain\AI\Conversation\AskPipeline;
use App\Services\AI\AiPolicyResolver;
use App\Domain\AI\Conversation\AskService;
use App\Domain\AI\Conversation\ConversationStore;
use App\Domain\AI\Lifecycle\Modules\ModuleRegistry;
use App\Domain\AI\Lifecycle\StageKey;
use App\Domain\AI\Lifecycle\StageOutcome;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * The conversational front door.
 *
 * One endpoint answers a question and returns, alongside the answer, the twelve-stage
 * lifecycle (Conversational AI -> Action) that the console renders. That view is the
 * point: the layers underneath already worked, but a caller had no way to see them, so
 * from the outside the platform looked like a chatbot with an opinion.
 *
 * Two pipelines can answer, chosen by `ai.lifecycle.enabled`:
 *
 *   - the standardised twelve-stage pipeline, where each stage is one class, and
 *   - the previous AskService, kept until every module has been migrated.
 *
 * Both return the same wire shape and write turns to the same tables, so the flag can be
 * flipped either way without stranding history. `pipeline` in the response names which
 * one answered, because a reader comparing two turns needs to know.
 *
 * Same middleware stack as the rest of routes/ai.php — the scope comes from
 * McpContextHydrator, never from request input, so a question cannot be asked about
 * another school by naming one.
 */
class AskController extends AiController
{
    public function __construct(
        private readonly AskPipeline $pipeline,
        // Still injected for `interpret` and `intents`, which are classification-only
        // and identical under both pipelines.
        private readonly AskService $ask,
        private readonly ModuleRegistry $modules,
        private readonly ConversationStore $conversations,
        private readonly AiPolicyResolver $policyResolver,
    ) {
    }

    /**
     * Ask a question.
     *
     * Returns {answer, trace, lifecycle_trace, intent, module, links}. Everything the
     * console renders comes from this one response — including the approve and reject
     * buttons, which are not special-cased actions but the next question with its
     * subject pinned. A button and a typed sentence therefore go down one path and
     * produce one trace shape, which is what makes the trace usable as evidence.
     */
    public function ask(Request $request)
    {
        $this->allowTimeForACohortSweep();

        try {
            $scope = $this->scope($request);

            $validated = $request->validate([
                'question' => 'required|string|max:1000',
                'conversation_id' => 'nullable|integer|min:1',
                // Sent by a button so the decision lands on the record the user was
                // looking at rather than on whatever was most recently mentioned.
                'payload' => 'nullable|array',
                'payload.case_id' => 'nullable|integer|min:1',
                'payload.student_id' => 'nullable|integer|min:1',
                'payload.recommendation_id' => 'nullable|integer|min:1',
                'payload.workflow_approval_id' => 'nullable|integer|min:1',
                'limit' => 'nullable|integer|min:1|max:200',
                // The screen the question was asked from, and the module it belongs to.
                // Both are hints: the resolver treats a declared module as authoritative
                // and a route as strong evidence, because the panel knows what it opened
                // on and the words alone often do not.
                'module' => 'nullable|string|max:64',
                'route' => 'nullable|string|max:512',
            ]);

            $options = [
                'payload' => $validated['payload'] ?? [],
                'limit' => $validated['limit'] ?? null,
                'module' => $validated['module'] ?? null,
                'route' => $validated['route'] ?? null,
            ];

            $policy = $this->policyResolver->resolve($scope->selectedInstituteId, $this->policyContext($validated, $options));

            if (! $policy['allowed']) {
                return $this->failure(
                    $policy['message'] ?? 'AI request blocked by policy.',
                    403,
                    ['policy' => $policy]
                );
            }

            $result = $this->pipeline->ask(
                $validated['question'],
                $scope,
                $validated['conversation_id'] ?? null,
                $options
            );

            return $this->success($result['answer']['headline'], $result);
        } catch (Throwable $exception) {
            return $this->handle($exception);
        }
    }

    /**
     * The same question, streamed.
     *
     * A lifecycle turn runs twelve stages and takes seconds. Non-streaming, the user
     * watches a spinner with no idea whether anything is happening; the stages are
     * already the most informative thing the platform knows, and they were being
     * withheld until the end purely as an artefact of returning one JSON body.
     *
     * Four event types, in this order:
     *
     *   - `stage`   — one per stage, as it settles. The payload is exactly one element
     *                 of the `trace` array the JSON route returns, so LifecycleTrace.tsx
     *                 renders a streamed stage and a stored one with the same code.
     *   - `token`   — a text fragment, where the answer is written by a model. ERP
     *                 answers are composed from rows rather than generated, so those
     *                 turns emit no tokens and their text arrives with `done`.
     *   - `done`    — the complete result, byte-identical to the JSON route's `data`.
     *                 A client can ignore every earlier event and still be correct.
     *   - `error`   — a failure, in the same envelope shape as the JSON route.
     *
     * `done` carrying the whole payload is deliberate. It means streaming is an
     * enhancement rather than a second contract: a client that cannot parse SSE
     * incrementally, or that drops events, can wait for `done` and be exactly as
     * correct as a caller of the JSON route.
     */
    public function stream(Request $request)
    {
        $this->allowTimeForACohortSweep();

        try {
            $scope = $this->scope($request);

            $validated = $request->validate([
                'question' => 'required|string|max:1000',
                'conversation_id' => 'nullable|integer|min:1',
                'payload' => 'nullable|array',
                'payload.case_id' => 'nullable|integer|min:1',
                'payload.student_id' => 'nullable|integer|min:1',
                'payload.recommendation_id' => 'nullable|integer|min:1',
                'payload.workflow_approval_id' => 'nullable|integer|min:1',
                'limit' => 'nullable|integer|min:1|max:200',
                'module' => 'nullable|string|max:64',
                'route' => 'nullable|string|max:512',
            ]);
        } catch (Throwable $exception) {
            // Validation and scope failures happen before a byte is streamed, so they
            // answer as ordinary JSON with a real status code rather than as a 200 with
            // an error event a client would have to dig out of the stream.
            return $this->handle($exception);
        }

        $options = [
            'payload' => $validated['payload'] ?? [],
            'limit' => $validated['limit'] ?? null,
            'module' => $validated['module'] ?? null,
            'route' => $validated['route'] ?? null,
        ];

        $response = new StreamedResponse(function () use ($validated, $scope, $options): void {
            $emit = $this->emitter();

            try {
                $policy = $this->policyResolver->resolve($scope->selectedInstituteId, $this->policyContext($validated, $options));

                if (! $policy['allowed']) {
                    $emit('error', [
                        'message' => $policy['message'] ?? 'AI request blocked by policy.',
                        'code' => 'policy_denied',
                        'policy' => $policy,
                    ]);

                    return;
                }

                $result = $this->pipeline->ask(
                    $validated['question'],
                    $scope,
                    $validated['conversation_id'] ?? null,
                    $options,
                    onStage: function (StageKey $key, StageOutcome $outcome) use ($emit): void {
                        $emit('stage', $outcome->toArray($key));
                    },
                    onToken: function (string $delta) use ($emit): void {
                        $emit('token', ['delta' => $delta]);
                    },
                );

                $emit('done', $result);
            } catch (Throwable $exception) {
                report($exception);

                $emit('error', [
                    'message' => 'The question could not be answered.',
                    // The trace is the diagnostic surface; the message stays generic so
                    // a provider or query detail never reaches a browser.
                    'code' => 'ask_failed',
                ]);
            }
        });

        $response->headers->set('Content-Type', 'text/event-stream');
        $response->headers->set('Cache-Control', 'no-cache, no-transform');
        // Nginx buffers proxied responses by default, which turns a stream back into one
        // delivery at the end — the exact failure this endpoint exists to avoid.
        $response->headers->set('X-Accel-Buffering', 'no');
        $response->headers->set('Connection', 'keep-alive');

        return $response;
    }

    /**
     * Give a turn long enough to finish the work it was asked to do.
     *
     * A cohort risk scan reads every student in the tenant through three detectors —
     * measured at 57–61s against a 140-student school. The web SAPI's default 60s
     * `max_execution_time` killed it mid-sweep, and because this endpoint streams, PHP's
     * fatal error was written *into* the SSE frames: the client received a half-drawn
     * stage ladder followed by an HTML error page, which reads as an agent that quietly
     * gave up rather than a request that ran out of time.
     *
     * Raised here rather than in php.ini so the limit travels with the code that needs
     * it, matching what the LMS generation controller already does. It is a ceiling, not
     * a target — nothing here is expected to take three minutes, and a turn that does is
     * a performance bug this does not excuse.
     */
    private function allowTimeForACohortSweep(): void
    {
        if (function_exists('set_time_limit')) {
            @set_time_limit(180);
        }
    }

    private function policyContext(array $validated, array $options): array
    {
        $payload = $options['payload'] ?? [];

        return [
            'operation' => 'ai_request',
            'scope_type' => $validated['scope_type'] ?? null,
            'scope_id' => $validated['scope_id'] ?? null,
            'assignment_id' => $payload['assignment_id'] ?? $payload['assignment'] ?? null,
            'assessment_id' => $payload['assessment_id'] ?? null,
            'activity_id' => $payload['activity_id'] ?? null,
            'class_id' => $payload['class_id'] ?? null,
            'course_id' => $payload['course_id'] ?? null,
            'grade_id' => $payload['grade_id'] ?? null,
            'academic_year' => $payload['academic_year'] ?? null,
            'module' => $options['module'] ?? $validated['module'] ?? null,
            'route' => $options['route'] ?? $validated['route'] ?? null,
        ];
    }

    /**
     * Write one SSE event and push it out.
     *
     * The flush pair is the whole trick: without it PHP holds output in its own buffer
     * and every event lands at once, which looks exactly like a working stream in tests
     * and like a broken one to a user.
     *
     * @return callable(string, array<string, mixed>): void
     */
    private function emitter(): callable
    {
        return static function (string $event, array $data): void {
            echo 'event: ' . $event . "\n";
            echo 'data: ' . json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n\n";

            if (ob_get_level() > 0) {
                @ob_flush();
            }

            flush();
        };
    }

    /**
     * Which modules the lifecycle serves, and how deep each one goes.
     *
     * The honest answer to "what can this thing actually do?". Every module reports all
     * twelve stages; this says which of them can reach stage 10 and beyond, and names
     * what is missing for the ones that cannot.
     */
    public function modules(Request $request)
    {
        try {
            $scope = $this->scope($request);

            $modules = array_values(array_map(
                static fn ($module) => $module->toArray() + ['depth_reason' => $module->whyNoDepth()],
                $this->modules->all($scope->selectedInstituteId)
            ));

            return $this->success('Lifecycle modules.', [
                'pipeline' => $this->pipeline->name(),
                'stages' => array_map(static fn ($stage) => [
                    'key' => $stage->value,
                    'order' => $stage->displayOrder(),
                    'layer' => $stage->layer(),
                    'component' => $stage->component(),
                    'surface' => $stage->surface(),
                ], \App\Domain\AI\Lifecycle\StageKey::inDisplayOrder()),
                'modules' => $modules,
            ]);
        } catch (Throwable $exception) {
            return $this->handle($exception);
        }
    }

    /**
     * Classification only — nothing runs, nothing is written.
     *
     * This is the endpoint to use when checking that a rephrasing still lands on the
     * intent you expect, without starting an analysis to find out.
     */
    public function interpret(Request $request)
    {
        try {
            $this->scope($request);

            $validated = $request->validate([
                'question' => 'required|string|max:1000',
                'memory' => 'nullable|array',
            ]);

            return $this->success('Question interpreted.', [
                'intent' => $this->ask->interpret($validated['question'], $validated['memory'] ?? []),
                'note' => 'Read-only. No agent ran, no case was opened, nothing was recorded.',
            ]);
        } catch (Throwable $exception) {
            return $this->handle($exception);
        }
    }

    /**
     * Everything this module can be asked, and what each intent needs to answer.
     */
    public function intents(Request $request)
    {
        try {
            $this->scope($request);

            return $this->success('Intents available for Student Profiles.', [
                'module' => AskService::MODULE,
                'intents' => $this->ask->catalogue(),
            ]);
        } catch (Throwable $exception) {
            return $this->handle($exception);
        }
    }

    /**
     * Replay a thread: every question, answer and trace, in order.
     */
    public function conversation(Request $request, int $conversation)
    {
        try {
            $scope = $this->scope($request);

            $transcript = $this->conversations->transcript($conversation, $scope, $this->limit($request));

            if ($transcript['conversation'] === null) {
                return $this->failure('No such conversation in your scope.', 404);
            }

            return $this->success('Conversation loaded.', $transcript);
        } catch (Throwable $exception) {
            return $this->handle($exception);
        }
    }
}
