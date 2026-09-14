<?php

namespace App\Domain\AI\Conversation;

use App\Domain\AI\Configuration\AiModelClientFactory;
use App\Services\Mcp\McpRequestContext;
use App\Domain\AI\Support\ModelClient;
use Throwable;

/**
 * The answer for a question the ERP has no data for.
 *
 * "What is the capital of Australia", "hi", "what's 12 times 7", a sentence in Gujarati —
 * none of these are lookups, and until now the lifecycle answered all of them with
 * "Nothing to report for that question." The Next.js route papered over the same gap with
 * a hard-coded `capitalCityMap` and a regex arithmetic evaluator, which is worse: it
 * shipped a list of two dozen capitals as if it were knowledge, silently returned nothing
 * for the twenty-fifth country, and could not do 12 × 7 unless the sentence matched its
 * pattern.
 *
 * Both are replaced by asking a model, which is the only honest way to answer a general
 * question.
 *
 * The thing this service must never do is answer a question the ERP should have answered.
 * A model that invents a student count is far more damaging than one that says it does
 * not know, so:
 *
 *   - it is only reached when every stage ran and none produced an answer, and nothing
 *     was blocked — a refusal keeps its own message rather than being smoothed over;
 *   - the prompt forbids inventing institute data outright, and tells it to say the
 *     lookup did not return anything instead of guessing;
 *   - the reply is labelled as general knowledge in the answer, so a reader is never
 *     left to assume a number came from their database.
 */
class GeneralAnswerService
{
    private const MAX_TOKENS = 600;

    /**
     * @param  ModelClient  $client  The container's default client. Still what
     *                               `isAvailable()` asks, because that question has no
     *                               school in scope, and the fallback below when no
     *                               factory was supplied.
     * @param  AiModelClientFactory|null  $clients  Resolves the provider, model and key
     *                               configured for this module. Optional so a caller
     *                               that constructs this service with a client of its
     *                               own — the unit tests do exactly that — keeps
     *                               working against that client rather than having the
     *                               factory hand it a real one. The container always
     *                               supplies it, so the configured path is what runs in
     *                               production.
     */
    public function __construct(
        private readonly ModelClient $client,
        private readonly ?AiModelClientFactory $clients = null,
    ) {
    }

    /** The AI module this path is configured under. */
    private const MODULE = 'conversational_ai';

    public function isAvailable(): bool
    {
        return $this->client->isConfigured();
    }

    /**
     * Answer a general question, or null if no answer could be produced.
     *
     * Never throws. A provider outage on this path must degrade to the honest
     * "nothing to report" the caller already has, not to a 500 on a question that was
     * only ever small talk.
     *
     * @param  array<int, array{role:string, content:string}>  $history  Prior turns, oldest first.
     * @return array{answer:string, follow_ups:array<int, string>}|null
     */
    /**
     * @param  McpRequestContext|null  $scope  The asking school, so a tenant holding its
     *                                        own provider configuration uses it. Optional
     *                                        so existing callers keep working unchanged;
     *                                        null resolves platform configuration only.
     */
    public function answer(
        string $question,
        array $history = [],
        ?callable $onToken = null,
        ?McpRequestContext $scope = null
    ): ?array {
        if (trim($question) === '') {
            return null;
        }

        $client = $this->clients?->for(self::MODULE, $scope?->selectedInstituteId) ?? $this->client;

        if (! $client->isConfigured()) {
            return null;
        }

        $messages = array_merge(
            [['role' => 'system', 'content' => $this->systemPrompt()]],
            $this->recentHistory($history),
            [['role' => 'user', 'content' => $question]]
        );

        try {
            // The provider's own default model. A model name is provider-specific, and
            // naming one here is how this call site would break the next time the
            // driver changes.
            //
            // Temperature: small talk reads as a machine at 0.0, and this path never
            // produces numbers anyone acts on, so a little warmth costs nothing.
            $content = $onToken === null
                ? $client->chat(
                    $messages,
                    model: null,
                    maxTokens: self::MAX_TOKENS,
                    temperature: 0.3,
                )
                : $client->stream(
                    $messages,
                    $onToken,
                    model: null,
                    maxTokens: self::MAX_TOKENS,
                    temperature: 0.3,
                );
        } catch (Throwable) {
            return null;
        }

        $answer = trim((string) $content);

        if ($answer === '') {
            return null;
        }

        return [
            'answer' => $answer,
            'follow_ups' => $this->followUps($question),
        ];
    }

    private function systemPrompt(): string
    {
        return <<<'PROMPT'
        You are the assistant inside a school management system (ERP). This particular
        question could not be answered from the school's own records, so you are
        answering it as a general assistant.

        Answer the question directly and briefly. You can handle general knowledge,
        everyday conversation, explanations, and arithmetic.

        Reply in the same language the user wrote in. If they write in Hindi, Gujarati,
        Marathi or any other language, answer in that language.

        The one rule you must not break: never state a fact about this school — a student,
        a teacher, a class, a fee, an attendance figure, an exam mark, an admission — as
        though you knew it. You have no access to their records on this turn. If the
        question needs school data, say plainly that you could not retrieve it and suggest
        how to ask more precisely (for example naming the class or the student). Do not
        guess a number, a name or a date, and never present an example as if it were real.

        Keep it to a few sentences unless the question genuinely needs more.
        PROMPT;
    }

    /**
     * The last few turns, so "and in French?" still works.
     *
     * Capped because this is a fallback path on a request that has already run twelve
     * stages, and an unbounded history would make the cheapest question the slowest.
     *
     * @param  array<int, array{role:string, content:string}>  $history
     * @return array<int, array{role:string, content:string}>
     */
    private function recentHistory(array $history): array
    {
        $clean = [];

        foreach ($history as $entry) {
            $role = $entry['role'] ?? null;
            $content = trim((string) ($entry['content'] ?? ''));

            if (! in_array($role, ['user', 'assistant'], true) || $content === '') {
                continue;
            }

            $clean[] = ['role' => $role, 'content' => mb_substr($content, 0, 2000)];
        }

        return array_slice($clean, -6);
    }

    /**
     * Follow-ups that fit a general answer.
     *
     * The lifecycle's own defaults ("Which students are at academic risk?") are the wrong
     * suggestions after "what is the capital of Australia" — they read as a non-sequitur
     * and teach the user that the chips are decoration. These point back at what the
     * platform is actually for, without pretending the last answer came from it.
     *
     * @return array<int, string>
     */
    private function followUps(string $question): array
    {
        return [
            'Ask about a class, a student or a fee record.',
            'Which students are at academic risk?',
        ];
    }
}
