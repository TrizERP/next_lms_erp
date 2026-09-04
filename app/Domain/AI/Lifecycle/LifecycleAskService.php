<?php

namespace App\Domain\AI\Lifecycle;

use App\Domain\AI\Conversation\AnswerComposer;
use App\Domain\AI\Conversation\ConversationStore;
use App\Domain\AI\Conversation\Intent;
use App\Domain\AI\Lifecycle\Modules\ModuleResolver;
use App\Services\Mcp\McpRequestContext;

/**
 * One question in, one answer and one twelve-stage lifecycle out.
 *
 * This is what the old AskService became once every stage owned itself. It does four
 * things and delegates the rest:
 *
 *   1. Works out which module the question belongs to, because that decides which tools
 *      may be selected and how deep the ladder can go.
 *   2. Builds the context the stages share.
 *   3. Runs the pipeline.
 *   4. Composes the answer the stages contributed, and records the turn.
 *
 * There is no routing table here, no intent handler, and no `foreach` over stage names.
 * Adding a module means adding config and, if it has depth, an agent — not editing this
 * file. Adding a stage means adding a class. That property is the point of the rewrite:
 * the previous version grew to 2,879 lines because every new capability had to be
 * threaded through it by hand, and five separate copies of the same downstream-marking
 * loop had already drifted apart from each other.
 */
class LifecycleAskService
{
    public function __construct(
        private readonly ModuleResolver $modules,
        private readonly LifecyclePipeline $pipeline,
        private readonly ConversationStore $conversations,
        private readonly AnswerComposer $compose,
    ) {
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array{
     *   conversation:array, question:string, intent:array, answer:array,
     *   trace:array, ladder:array, stage_counts:array, lifecycle_trace:array,
     *   lifecycle_stage_counts:array, module:array, links:array, duration_ms:int
     * }
     */
    public function ask(
        string $question,
        McpRequestContext $scope,
        ?int $conversationId = null,
        array $options = []
    ): array {
        $startedAt = microtime(true);

        $resolution = $this->modules->resolve($question, $options, $scope->selectedInstituteId);

        $context = new StageContext(
            question: $question,
            scope: $scope,
            module: $resolution['module'],
            options: $options,
            conversationId: $conversationId,
        );

        $context->set('module_source', $resolution['source']);
        $context->set('modules_considered', $resolution['considered']);

        $trace = $this->pipeline->run($context);

        $answer = $this->composeAnswer($context, $trace);
        $intent = $context->intent ?? Intent::unknown();
        $durationMs = (int) round((microtime(true) - $startedAt) * 1000);

        $links = $context->links();

        if ($context->agentRun !== null) {
            $links['agent_run_id'] = $context->agentRun['run_id'] ?? null;
        }

        $turnId = $this->conversations->recordTurn(
            $context->thread['id'] ?? null,
            $scope,
            $question,
            $intent,
            $answer,
            $trace,
            $links,
            $durationMs,
            $this->errorFrom($trace)
        );

        $this->carryPendingTask($context, $scope);

        $stages = $trace->toArray();
        $counts = $trace->summaryCounts();

        return [
            'conversation' => [
                'id' => $context->thread['id'] ?? null,
                'reference' => $context->thread['reference'] ?? null,
                'turn_id' => $turnId,
                'turn' => ($context->thread['turn_count'] ?? 0) + 1,
            ],
            'question' => $question,
            'intent' => $intent->toArray(),
            'answer' => $answer,
            // `trace` and `lifecycle_trace` are the same twelve stages. Both keys are
            // returned because the console and stored turns from the previous pipeline
            // read different ones, and a consumer should not have to know which
            // pipeline answered it.
            'trace' => $stages,
            'ladder' => $trace->toLadder(),
            'stage_counts' => $counts,
            'lifecycle_trace' => $stages,
            'lifecycle_stage_counts' => $counts,
            'module' => $resolution['module']->toArray() + ['resolved_by' => $resolution['source']],
            'depth_reached' => $trace->depthReached(),
            'links' => $links,
            'duration_ms' => $durationMs,
        ];
    }

    // ---------------------------------------------------------------- internals

    /**
     * Save or clear the task the thread is part-way through.
     *
     * Runs after `recordTurn()`, which merges referents into memory and would otherwise
     * overwrite this. Clearing is explicit because `rememberOn()` deliberately ignores
     * nulls — a finished or abandoned admission has to be actively forgotten, or it
     * would follow the user for the rest of the conversation and quietly capture every
     * later sentence as an answer to a question they had stopped being asked.
     */
    private function carryPendingTask(StageContext $context, McpRequestContext $scope): void
    {
        $conversationId = $context->thread['id'] ?? null;

        if ($conversationId === null || ! $context->has('pending_action_next')) {
            return;
        }

        $next = $context->get('pending_action_next');

        if (is_array($next) && $next !== []) {
            $this->conversations->rememberOn(
                (int) $conversationId,
                $scope,
                ['pending_action' => $next]
            );

            return;
        }

        $this->conversations->forgetOn((int) $conversationId, $scope, ['pending_action']);
    }

    /**
     * Assemble what the stages contributed.
     *
     * A turn where no stage set a headline is a turn where nothing had anything to say,
     * and that has to produce an honest reply rather than an empty card — so the fallback
     * reads the trace for the first stage that refused and quotes its reason.
     *
     * @return array<string, mixed>
     */
    private function composeAnswer(StageContext $context, LifecycleTrace $trace): array
    {
        $headline = $context->headline();
        $sections = $context->sections();

        if ($headline === null) {
            [$headline, $fallbackSection] = $this->fallback($context, $trace);

            if ($fallbackSection !== null) {
                array_unshift($sections, $fallbackSection);
            }
        }

        $followUps = $context->followUps();

        if ($followUps === []) {
            $followUps = ['Which students are at academic risk?', 'What has the system learned?'];
        }

        return $this->compose->make($headline, $sections, $context->actions(), $followUps);
    }

    /**
     * @return array{0:string, 1:array<string, mixed>|null}
     */
    private function fallback(StageContext $context, LifecycleTrace $trace): array
    {
        foreach (StageKey::inExecutionOrder() as $key) {
            $outcome = $trace->outcomeOf($key);

            if ($outcome->status === StageStatus::Blocked) {
                return [
                    'I could not answer that.',
                    $this->compose->text(
                        'Where it stopped',
                        sprintf('%s — %s', $key->layer(), $outcome->summary)
                    ),
                ];
            }
        }

        if ($context->cases !== []) {
            return [
                sprintf(
                    '%d student%s currently showing risk signals.',
                    count($context->cases),
                    count($context->cases) === 1 ? ' is' : 's are'
                ),
                null,
            ];
        }

        return [
            'Nothing to report for that question.',
            $this->compose->text(
                'Why',
                'Every stage of the lifecycle was reached and none of them found anything to act on. '
                . 'The trace beside this answer shows which stage stopped and why.'
            ),
        ];
    }

    /**
     * The first refusal, recorded against the turn so a failed question is queryable
     * rather than only visible in a trace nobody thought to open.
     */
    private function errorFrom(LifecycleTrace $trace): ?string
    {
        foreach (StageKey::inExecutionOrder() as $key) {
            $outcome = $trace->outcomeOf($key);

            if ($outcome->status === StageStatus::Blocked) {
                return sprintf('[%s] %s', $key->value, $outcome->summary);
            }
        }

        return null;
    }
}
