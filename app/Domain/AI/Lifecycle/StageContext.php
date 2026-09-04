<?php

namespace App\Domain\AI\Lifecycle;

use App\Domain\AI\Conversation\Intent;
use App\Domain\AI\Lifecycle\Modules\ModuleCapability;
use App\Domain\AI\Lifecycle\Plan\Plan;
use App\Services\Mcp\McpRequestContext;

/**
 * Everything one turn knows, carried from stage to stage.
 *
 * This is the only mutable object in the pipeline, and it is mutable on purpose: twelve
 * stages threading a growing tuple through each other would be worse. What keeps it
 * honest is that the context holds *artifacts*, never trace entries — a stage records
 * what it found here and reports what it did through its StageOutcome, and those are
 * two separate acts. A stage cannot quietly edit another stage's report.
 *
 * The answer is assembled the same way the trace is: each stage contributes the section
 * it is qualified to write, and the pipeline composes them at the end. That is why there
 * is no 400-line method anywhere that knows how to render every possible reply — the
 * Evidence stage writes the evidence section because it is the stage holding evidence,
 * and no other stage needs to know that section exists.
 */
final class StageContext
{
    /** @var array<string, mixed> */
    private array $artifacts = [];

    /** @var array<int, array<string, mixed>> */
    private array $sections = [];

    /** @var array<int, array<string, mixed>> */
    private array $actions = [];

    /** @var array<int, string> */
    private array $followUps = [];

    /** @var array<int, array<string, mixed>> */
    private array $toolCalls = [];

    /** @var array<string, mixed> */
    private array $links = [];

    private ?string $headline = null;

    // ---- artifacts the stages fill in, in the order they become known -------

    /** Stage 1. The conversation thread this turn belongs to. */
    public ?array $thread = null;

    /** Stage 2. What the question was understood to mean. */
    public ?Intent $intent = null;

    /** Stage 3. How the turn intends to answer it. */
    public ?Plan $plan = null;

    /** Stage 4. Tools the turn committed to calling. @var array<int, string> */
    public array $selectedTools = [];

    /** Stage 6. The agent run, when the plan called for one. */
    public ?array $agentRun = null;

    /** Stage 6+. Cases the turn produced or read. @var array<int, array<string, mixed>> */
    public array $cases = [];

    /** The case the answer is principally about. */
    public ?array $focusCase = null;

    /** Evidence rows behind the focus case. @var array<int, array<string, mixed>> */
    public array $evidence = [];

    /** The drafted recommendation waiting on a person, when there is one. */
    public ?array $pendingRecommendation = null;

    /**
     * @param  array<string, mixed>  $options
     */
    public function __construct(
        public readonly string $question,
        public readonly McpRequestContext $scope,
        public readonly ModuleCapability $module,
        public readonly array $options = [],
        public readonly ?int $conversationId = null,
    ) {
    }

    // ------------------------------------------------------------- artifacts

    public function set(string $key, mixed $value): void
    {
        $this->artifacts[$key] = $value;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->artifacts[$key] ?? $default;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->artifacts);
    }

    /**
     * One entry from `options.payload`, restricted to the ids a console button is
     * allowed to pin. A button sends the same sentence a user could type plus the id it
     * was rendered against; it may not send anything else.
     */
    public function payload(string $key): ?int
    {
        $payload = $this->options['payload'] ?? [];

        if (! is_array($payload) || ! isset($payload[$key]) || ! is_numeric($payload[$key])) {
            return null;
        }

        return (int) $payload[$key];
    }

    // ------------------------------------------------------------ MCP calls

    /**
     * Log one Laravel MCP call, completed or refused.
     *
     * Stage 6 reports from this log rather than making the calls itself, because a tool
     * call can legitimately happen anywhere downstream — hydrating the student a
     * decision is recorded against is an MCP call made by the approval stage. Recording
     * centrally is what lets stage 6 stay truthful about calls it did not personally
     * make.
     *
     * @param  array<string, mixed>  $call
     */
    public function recordToolCall(array $call): void
    {
        $this->toolCalls[] = $call;
    }

    /** @return array<int, array<string, mixed>> */
    public function toolCalls(): array
    {
        return $this->toolCalls;
    }

    /**
     * Tools the turn genuinely invoked, refusals included.
     *
     * Distinct from `selectedTools`, which is what it committed to, and from the plan's
     * candidates, which is only what it considered. Three different facts; the trace
     * keeps them apart.
     *
     * @return array<int, string>
     */
    public function executedTools(): array
    {
        return array_values(array_unique(array_filter(array_map(
            static fn (array $call) => is_string($call['tool'] ?? null) ? $call['tool'] : null,
            $this->toolCalls
        ))));
    }

    // --------------------------------------------------------- the answer

    public function setHeadline(string $headline): void
    {
        $this->headline = $headline;
    }

    public function headline(): ?string
    {
        return $this->headline;
    }

    /**
     * Contribute one section of the reply.
     *
     * @param  array<string, mixed>|null  $section  Null is accepted and dropped, so a
     *                                              stage can write `$c->addSection($x ? ... : null)`
     *                                              without a guard around every call.
     */
    public function addSection(?array $section): void
    {
        if ($section !== null && $section !== []) {
            $this->sections[] = $section;
        }
    }

    /** @return array<int, array<string, mixed>> */
    public function sections(): array
    {
        return $this->sections;
    }

    /**
     * Offer a button. Only stages that own a real gate should add one.
     *
     * @param  array<string, mixed>  $action
     */
    public function addAction(array $action): void
    {
        $this->actions[] = $action;
    }

    /** @return array<int, array<string, mixed>> */
    public function actions(): array
    {
        return $this->actions;
    }

    public function suggestFollowUp(string ...$questions): void
    {
        foreach ($questions as $question) {
            $question = trim($question);

            if ($question !== '' && ! in_array($question, $this->followUps, true)) {
                $this->followUps[] = $question;
            }
        }
    }

    /** @return array<int, string> */
    public function followUps(): array
    {
        return $this->followUps;
    }

    /**
     * Referents this turn should hand to the next one — the student, the case, the
     * recommendation. This is what makes "why is she at risk?" resolvable.
     *
     * @param  array<string, mixed>  $links
     */
    public function link(array $links): void
    {
        $this->links = [...$this->links, ...array_filter($links, static fn ($v) => $v !== null)];
    }

    /** @return array<string, mixed> */
    public function links(): array
    {
        return $this->links;
    }
}
