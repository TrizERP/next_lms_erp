<?php

namespace App\Domain\AI\Conversation;

use App\Domain\AI\Lifecycle\LifecycleAskService;
use App\Services\Mcp\McpRequestContext;

/**
 * Which pipeline answers a question — decided once, for every caller.
 *
 * There are two entry points into the AI while the cutover is in progress: the HTTP
 * endpoint and `php artisan ai:journey`. Both must reach the same pipeline, or the
 * command stops being a way to verify the system and becomes a way to be misled by it —
 * a stage shown as `ran` in the terminal would say nothing about what the API does.
 *
 * The obvious way to get that wrong is to copy `config('ai.lifecycle.enabled')` into
 * both call sites. That is the shape of duplication this whole refactor exists to
 * remove: two copies of one decision, drifting apart the first time somebody adds a
 * condition to one of them. So the decision lives here, and both callers ask.
 *
 * This class disappears when the flag does. It is scaffolding for a migration, and it is
 * written to be deleted — at which point `LifecycleAskService` is injected directly.
 */
class AskPipeline
{
    public const LIFECYCLE = 'lifecycle_v2';

    public const LEGACY = 'ask_service_v1';

    public function __construct(
        private readonly LifecycleAskService $lifecycle,
        private readonly AskService $legacy,
    ) {
    }

    /** Which pipeline is live right now. */
    public function name(): string
    {
        return $this->usesLifecycle() ? self::LIFECYCLE : self::LEGACY;
    }

    public function usesLifecycle(): bool
    {
        return (bool) config('ai.lifecycle.enabled', false);
    }

    /**
     * Ask a question of whichever pipeline is live.
     *
     * Both return the same wire shape and write turns to the same tables, so a caller
     * does not need to know which answered — but `pipeline` is stamped on the result so
     * a reader comparing two turns can tell.
     *
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function ask(
        string $question,
        McpRequestContext $scope,
        ?int $conversationId = null,
        array $options = []
    ): array {
        $result = $this->usesLifecycle()
            ? $this->lifecycle->ask($question, $scope, $conversationId, $options)
            : $this->legacy->ask($question, $scope, $conversationId, $options);

        $result['pipeline'] = $this->name();

        return $result;
    }
}
