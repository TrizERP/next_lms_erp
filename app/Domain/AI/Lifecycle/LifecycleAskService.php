<?php

namespace App\Domain\AI\Lifecycle;

use App\Domain\AI\Conversation\AnswerComposer;
use App\Domain\AI\Conversation\ConversationStore;
use App\Domain\AI\Conversation\FollowUpComposer;
use App\Domain\AI\Conversation\GeneralAnswerService;
use App\Domain\AI\Conversation\Intent;
use App\Domain\AI\Lifecycle\Modules\ModuleResolver;
use App\Domain\AI\Workspace\ModuleSuggestions;
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
        private readonly GeneralAnswerService $general,
        private readonly ModuleSuggestions $suggestions,
        private readonly FollowUpComposer $followUps,
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
        array $options = [],
        ?callable $onStage = null,
        ?callable $onToken = null
    ): array {
        $startedAt = microtime(true);

        $threadHint = $this->conversations->threadHint($conversationId, $scope, $question);

        if ($threadHint['module'] !== null && ! isset($options['conversation_module'])) {
            $options['conversation_module'] = $threadHint['module'];
        }

        // A question that points at the rows the thread just printed belongs to the
        // module that printed them, whatever domain nouns it happens to contain.
        $options['points_at_previous_answer'] = $threadHint['points_at_previous_answer'];

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
        // Named on the context so the trace can say the turn ran somewhere other than
        // the screen it was asked on. Silent re-routing would be worse than none.
        $context->set('module_stood_down', $resolution['stood_down'] ?? null);

        $trace = $this->pipeline->run($context, $onStage);

        $answer = $this->composeAnswer($context, $trace, $onToken);
        $intent = $context->intent ?? Intent::unknown();
        $durationMs = (int) round((microtime(true) - $startedAt) * 1000);

        $links = $context->links();

        if ($context->agentRun !== null) {
            $links['agent_run_id'] = $context->agentRun['run_id'] ?? null;
        }

        // Where this turn actually ran, carried forward so the next elliptical follow-up
        // inherits the conversation rather than whichever screen the panel is sitting on.
        // 'general' is deliberately not recorded: a turn that belonged to no module has
        // nothing to teach the next one, and writing it would erase a real module.
        if ($resolution['module']->key !== 'general') {
            $links['module'] = $resolution['module']->key;
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
            // stages | general | fallback — see composeAnswer(). The audit reads this to
            // tell an answer from a refusal, which stage counts alone cannot.
            'answer_source' => $context->get('answer_source', 'stages'),
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
    private function composeAnswer(StageContext $context, LifecycleTrace $trace, ?callable $onToken = null): array
    {
        $headline = $context->headline();
        $sections = $context->sections();

        // What to ask next, derived from what this turn actually produced, ahead of
        // whatever the stages asked for.
        //
        // The order is the point. A stage suggests a follow-up from where it sits in the
        // ladder — reasonable, and blind to the rows the answer ended up carrying. The
        // composer reads those rows, the record the turn opened and the task it is
        // part-way through, so its suggestions are the ones tied to what is on screen.
        // Stage suggestions follow rather than being replaced: "what evidence supports
        // this?" is still the right second question after an explanation.
        $followUps = $this->mergeFollowUps(
            $this->followUps->forTurn($context),
            $context->followUps()
        );

        // Where the answer came from, recorded rather than inferred. The audit trail has
        // to tell a refusal from a general answer, and both leave planning blocked —
        // guessing from stage counts marks "what is the capital of Australia" as refused
        // when it was answered perfectly well.
        $context->set('answer_source', 'stages');

        if ($headline === null) {
            // Before falling back to "nothing to report", see whether this was simply
            // not an ERP question. general() returns null for anything that was refused
            // or that the estate should have answered, so a real refusal keeps its own
            // message rather than being smoothed over by a model.
            $general = $this->general($context, $trace, $onToken);

            if ($general !== null) {
                $context->set('answer_source', 'general');

                return $this->compose->make(
                    $general['answer'],
                    array_merge($sections, array_filter([$this->generalProvenance()])),
                    $context->actions(),
                    $followUps === [] ? $general['follow_ups'] : $followUps
                );
            }

            $context->set('answer_source', 'fallback');

            [$headline, $fallbackSection] = $this->fallback($context, $trace);

            if ($fallbackSection !== null) {
                array_unshift($sections, $fallbackSection);
            }
        }

        if ($followUps === []) {
            // What to ask next comes from the module the turn was answered in, read from
            // `ai_suggestions`. This used to be two sentences about academic risk offered
            // after every turn in the estate, which on a fees screen invited someone
            // looking at unpaid invoices to go and read about struggling students.
            //
            // No suggestion is better than a wrong one: a module nobody has curated
            // returns nothing, and the answer simply carries no chips.
            $followUps = $this->suggestions->forModule(
                $context->module->key,
                $context->scope,
                array_map(static fn (array $action) => (string) ($action['utterance'] ?? ''), $context->actions())
            );
        }

        return $this->compose->make($headline, $sections, $context->actions(), $followUps);
    }

    /**
     * Merge two sources of follow-up, keeping the first occurrence of each.
     *
     * Capped, because a menu is not a suggestion: past half a dozen chips a reader stops
     * reading them and the answer above starts to look like the smaller half of the reply.
     *
     * @param  array<int, string>  $derived
     * @param  array<int, string>  $suggested
     * @return array<int, string>
     */
    private function mergeFollowUps(array $derived, array $suggested): array
    {
        $seen = [];
        $merged = [];

        foreach ([...$derived, ...$suggested] as $followUp) {
            $followUp = trim((string) $followUp);

            if ($followUp === '') {
                continue;
            }

            $key = mb_strtolower(preg_replace('/\s+/u', ' ', $followUp) ?? $followUp);

            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $merged[] = $followUp;

            if (count($merged) >= 6) {
                break;
            }
        }

        return $merged;
    }

    /**
     * A general answer, when and only when this was not an ERP question.
     *
     * The dangerous version of this feature answers "how many students are in 8B?" from a
     * language model. Three conditions keep that from happening, and all three must hold:
     *
     *   1. **The module binds no tools.** A question that reached `general` had no lookup
     *      available to it in the first place, so there is no ERP answer being displaced.
     *      A fees question routes to the fees module, which binds tools, and never gets
     *      here however badly it went.
     *   2. **Only planning blocked.** Planning refusing with "not scoped to a module and
     *      matched no registered intent" *is* the signature of a non-ERP question. A
     *      block anywhere else — no permission, tool not bound, provider down — has a
     *      reason the user is owed, and hiding it behind a plausible sentence would turn
     *      a permissions failure into a wrong answer.
     *   3. **No tool returned data.** A completed call means the estate answered; if that
     *      answer was "no rows", saying so is correct and a model must not improve on it.
     *   4. **No case, no records, no sections.** Anything the lifecycle actually built is
     *      a real answer, whether or not a stage set a headline.
     *
     * What survives all four is a turn that had nothing to look up and refused nothing —
     * which is what small talk, arithmetic and general knowledge look like from in here.
     * The service's own prompt is the second line of defence: asked a school question
     * that simply failed to classify, it says it cannot see the records rather than
     * inventing a number.
     *
     * @return array{answer:string, follow_ups:array<int, string>}|null
     */
    private function general(StageContext $context, LifecycleTrace $trace, ?callable $onToken = null): ?array
    {
        if (! $this->general->isAvailable() || $context->module->mcpTools !== []) {
            return null;
        }

        foreach (StageKey::inExecutionOrder() as $key) {
            if ($key === StageKey::Planning) {
                continue;
            }

            if ($trace->outcomeOf($key)->status === StageStatus::Blocked) {
                return null;
            }
        }

        foreach ($context->toolCalls() as $call) {
            if (($call['status'] ?? null) === 'completed' || ($call['count'] ?? 0) > 0) {
                return null;
            }
        }

        if ($context->cases !== [] || $context->sections() !== []) {
            return null;
        }

        return $this->general->answer($context->question, $this->historyFor($context), $onToken);
    }

    /**
     * Prior turns of this thread, so a general follow-up keeps its subject.
     *
     * @return array<int, array{role:string, content:string}>
     */
    private function historyFor(StageContext $context): array
    {
        $turns = $context->thread['recent_turns'] ?? null;

        if (! is_array($turns)) {
            return [];
        }

        $history = [];

        foreach ($turns as $turn) {
            if (is_array($turn) && isset($turn['question'])) {
                $history[] = ['role' => 'user', 'content' => (string) $turn['question']];
            }

            if (is_array($turn) && isset($turn['answer'])) {
                $history[] = ['role' => 'assistant', 'content' => (string) $turn['answer']];
            }
        }

        return $history;
    }

    /**
     * Say where a general answer came from.
     *
     * Without this the reply is indistinguishable from one built out of the institute's
     * records, and the whole point of the trace beside it is that a reader can tell those
     * apart. The frontend derives citations from the trace, which for this turn correctly
     * shows no data stage produced anything — this section is what explains why.
     *
     * @return array<string, mixed>|null
     */
    private function generalProvenance(): ?array
    {
        return $this->compose->text(
            'Source',
            'Answered from general knowledge, not from this institute\'s records. No student, '
            . 'staff, fee or attendance data was read for this question.'
        );
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
