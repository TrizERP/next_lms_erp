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

        return StageOutcome::ran(
            sprintf('Question accepted on thread %s (turn %d).', $reference, $turn),
            [
                'utterance' => $context->question,
                'conversation_id' => $thread['id'] ?? null,
                'conversation_reference' => $thread['reference'] ?? null,
                'turn' => $turn,
                'memory_before' => $thread['memory'] ?? [],
                'module' => $context->module->key,
                'module_resolved_by' => $context->get('module_source'),
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
    }

    private function prefix(): string
    {
        return '/' . trim((string) config('ai.route_prefix', 'api/ai'), '/');
    }
}
