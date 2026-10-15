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
                'module_stood_down' => $context->get('module_stood_down'),
                'asked_by' => [
                    'user_id' => $context->scope->userId,
                    'role' => $context->scope->role,
                    // Report-only, and deliberately so. The scope is derived from the JWT
                    // by McpContextHydrator and is never read from request input, so this
                    // cannot be used to choose an institute — it is here so a reader can
                    // confirm which one answered without inferring it from the rows.
                    'sub_institute_id' => $context->scope->selectedInstituteId,
                    'allowed_institute_ids' => $context->scope->allowedInstituteIds,
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

        // The same argument applies to routing. A turn that ran in a different module
        // from the screen it was typed on is doing the right thing, but a reader
        // comparing the answer against the page they are looking at needs to be told,
        // or correct routing is indistinguishable from a bug.
        $stoodDown = $context->get('module_stood_down');

        if (is_string($stoodDown) && $stoodDown !== '') {
            $routing = sprintf(
                'Asked on the %s screen, but the question has no words in common with that module '
                . 'and names the %s domain outright, so it ran there instead — which is where the '
                . 'tools and the agent that can answer it are bound.',
                $stoodDown,
                $context->module->key
            );

            $note = $note === null ? $routing : $routing . ' ' . $note;
        }

        $outside = $context->get('out_of_page');

        if (is_array($outside)) {
            return $this->redirect($context, $outcome->withNote($note), $outside);
        }

        return $note === null ? $outcome : $outcome->withNote($note);
    }

    /**
     * Answer a question that belongs to another page by saying so, and read nothing.
     *
     * Halting here is the point: no tool of the other module runs, so no figure from it can end
     * up on this page, and every later stage is recorded as not reached for this reason.
     *
     * @param  array{page: array{key:string,label:string}, asked: array{key:string,label:string}}  $outside
     */
    private function redirect(StageContext $context, StageOutcome $outcome, array $outside): StageOutcome
    {
        $page = $outside['page']['label'];
        $asked = $outside['asked']['label'];

        $article = preg_match('/^[aeiou]/i', $asked) ? 'an' : 'a';

        $context->setHeadline(sprintf('That is %s %s question, and this is the %s page.', $article, $asked, $page));
        $context->addSection([
            'type' => 'text',
            'title' => 'Where to ask it',
            'body' => sprintf(
                'Open the %s page and ask it there, where it is answered from the %s records. '
                .'Each page here answers from its own data only, so the figures you see on this page are always %s ones.',
                $asked,
                $asked,
                $page
            ),
        ]);
        $context->link(['out_of_page' => $outside]);

        return $outcome
            ->withNote(sprintf('The question names the %s domain, but it was asked on the %s page, which answers %s questions only.', $asked, $page, $page))
            ->halting(sprintf('The question belongs to the %s page, so nothing was read for it here.', $asked));
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
