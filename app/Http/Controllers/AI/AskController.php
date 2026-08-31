<?php

namespace App\Http\Controllers\AI;

use App\Domain\AI\Conversation\AskPipeline;
use App\Domain\AI\Conversation\AskService;
use App\Domain\AI\Conversation\ConversationStore;
use App\Domain\AI\Lifecycle\Modules\ModuleRegistry;
use Illuminate\Http\Request;
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
