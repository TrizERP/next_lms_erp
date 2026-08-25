<?php

namespace App\Http\Controllers\AI;

use App\Domain\AI\Conversation\AskService;
use App\Domain\AI\Conversation\ConversationStore;
use Illuminate\Http\Request;
use Throwable;

/**
 * The conversational front door.
 *
 * One endpoint answers a question and returns, alongside the answer, two views of what
 * ran: `trace`, the fifteen-stage backend ladder used for diagnostics, and
 * `lifecycle_trace`, the twelve-stage product lifecycle (Conversational AI -> Action)
 * that the console renders. That second half is the point: the layers underneath
 * already worked, but a caller had no way to see them, so from the outside the platform
 * looked like a chatbot with an opinion.
 *
 * Same middleware stack as the rest of routes/ai.php — the scope comes from
 * McpContextHydrator, never from request input, so a question cannot be asked about
 * another school by naming one.
 */
class AskController extends AiController
{
    public function __construct(
        private readonly AskService $ask,
        private readonly ConversationStore $conversations,
    ) {
    }

    /**
     * Ask a question of the Student Profiles module.
     *
     * Returns {answer, trace, ladder, intent, links}. Everything the console renders
     * comes from this one response — including the approve/reject buttons, which are
     * just the next question with its subject pinned.
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
            ]);

            $result = $this->ask->ask(
                $validated['question'],
                $scope,
                $validated['conversation_id'] ?? null,
                [
                    'payload' => $validated['payload'] ?? [],
                    'limit' => $validated['limit'] ?? null,
                ]
            );

            return $this->success($result['answer']['headline'], $result);
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
