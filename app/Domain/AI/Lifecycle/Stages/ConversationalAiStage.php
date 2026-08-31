<?php

namespace App\Domain\AI\Lifecycle\Stages;

use App\Domain\AI\Conversation\ConversationStore;
use App\Domain\AI\Lifecycle\LifecycleStage;
use App\Domain\AI\Lifecycle\StageContext;
use App\Domain\AI\Lifecycle\StageKey;
use App\Domain\AI\Lifecycle\StageOutcome;

/**
 * Stage 1 — the question arrives and joins a thread.
 *
 * This stage does one thing that matters far more than it looks: it establishes the
 * memory that makes "why is she at risk?" answerable. Without a thread, every follow-up
 * would need to restate its subject, and a conversation would be a series of unrelated
 * lookups wearing a chat interface.
 *
 * It cannot fail in any interesting way. An unopenable thread falls back to an in-memory
 * one so a storage problem costs continuity rather than the answer.
 */
class ConversationalAiStage implements LifecycleStage
{
    public function __construct(private readonly ConversationStore $conversations)
    {
    }

    public function key(): StageKey
    {
        return StageKey::Conversation;
    }

    public function run(StageContext $context): StageOutcome
    {
        $thread = $this->conversations->open(
            $context->conversationId,
            $context->scope,
            $context->module->key
        );

        $context->thread = $thread;

        $turn = ($thread['turn_count'] ?? 0) + 1;
        $reference = $thread['reference'] ?? 'in-memory';
        $memory = $thread['memory'] ?? [];

        $outcome = StageOutcome::ran(
            sprintf(
                'Question accepted on thread %s (turn %d), %s.',
                $reference,
                $turn,
                $thread['reused'] ?? false
                    ? sprintf('carrying %d referent%s from earlier turns', count($memory), count($memory) === 1 ? '' : 's')
                    : 'newly opened'
            ),
            [
                'utterance' => $context->question,
                'conversation_id' => $thread['id'] ?? null,
                'conversation_reference' => $thread['reference'] ?? null,
                'turn' => $turn,
                'memory_before' => $memory,
                // Three separate facts a reader needs to tell "the frontend sent no id"
                // apart from "the id it sent was refused". They used to be one silence.
                'requested_conversation_id' => $thread['requested_id'] ?? null,
                'thread_reused' => $thread['reused'] ?? false,
                'persisted' => ($thread['id'] ?? null) !== null,
                'module' => $context->module->key,
                'module_resolved_by' => $context->get('module_source'),
                'thread_module' => $thread['module_key'] ?? null,
                'asked_by' => [
                    'user_id' => $context->scope->userId,
                    'role' => $context->scope->role,
                ],
            ],
            ['table' => 'ai_conversations', 'ids' => array_filter([$thread['id'] ?? null])],
            [
                'api' => 'GET ' . $this->prefix() . '/conversations/' . ($thread['id'] ?? '{id}'),
                'sql' => 'select * from ai_conversation_turns where conversation_id = '
                    . ($thread['id'] ?? 0) . ' order by sequence',
            ]
        );

        // A new thread when the caller asked for an existing one is the single most
        // misleading thing this stage can do quietly, because every downstream symptom
        // — turn 1 on a follow-up, empty memory, a subject the answer cannot resolve —
        // points somewhere else. Say it here, where the decision was made.
        $note = $this->noteFor($thread, $turn);

        return $note === null ? $outcome : $outcome->withNote($note);
    }

    /**
     * @param  array<string, mixed>  $thread
     */
    private function noteFor(array $thread, int $turn): ?string
    {
        $declined = $thread['not_reused_reason'] ?? null;

        if (is_string($declined) && $declined !== '') {
            return $declined;
        }

        if ($turn > 1 && ($thread['memory'] ?? []) === []) {
            return 'This thread has answered before but remembers nothing, so a follow-up cannot '
                . 'resolve who or what it refers to. The previous turn recorded no referents — check '
                . 'the log for a failed memory merge, and ai_conversation_turns for that turn\'s row.';
        }

        if (($thread['id'] ?? null) === null) {
            return null;
        }

        if (! ($thread['reused'] ?? false)) {
            return 'A new thread was opened because the question arrived without a conversation id. '
                . 'That is correct for a first question and a bug for a follow-up — the frontend must '
                . 'send back the id this turn returns.';
        }

        return null;
    }

    private function prefix(): string
    {
        return '/' . trim((string) config('ai.route_prefix', 'api/ai'), '/');
    }
}
