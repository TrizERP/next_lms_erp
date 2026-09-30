<?php

namespace App\Domain\AI\Conversation;

use App\Domain\AI\Configuration\AiModelClientFactory;
use App\Domain\AI\Lifecycle\StageContext;
use App\Domain\AI\Support\ModelClient;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Says what the tools found, the way a person would.
 *
 * The lifecycle answers a data question with a card: a count-and-noun headline, a list of
 * records, a block of figures. That is correct and auditable, and it is not a
 * conversation - "66 students have an outstanding balance" does not tell a bursar which
 * class to look at first, or that the fifteen per cent collection rate is the real story.
 * This turns the same tool results into a reply that answers the question that was asked,
 * in the length it deserves.
 *
 * ## What keeps it honest
 *
 * The model is handed the question and the tool results and nothing else. It is told to
 * use only those, to say so when they do not contain the answer, and to say when a figure
 * covers only part of the school. Then the reply is checked: every number in it must occur
 * in the data it was given. A reply that mentions a figure the tools never returned - a
 * total it added up itself, a percentage it invented - is discarded, and the caller keeps
 * the deterministic headline, which is always available and always true.
 *
 * The cards stay underneath either way. The narration is the top line, not a replacement
 * for the evidence beside it.
 */
class ConversationalNarrator
{
    private const MODULE = 'conversational_ai';

    private const MAX_TOKENS = 700;

    /** More data than this is cut, not summarised: the cards below still carry every row. */
    private const MAX_DATA_CHARS = 14000;

    /** Numbers below this are ordinals and small counts of things the reply itself lists. */
    private const CHECK_FROM = 10;

    public function __construct(
        private readonly ModelClient $client,
        private readonly ?AiModelClientFactory $clients = null,
    ) {
    }

    /**
     * A conversational reply for a turn the tools answered, or null to keep the headline.
     *
     * @param  string  $draft  The deterministic headline, given to the model as the plain
     *                         statement of the result so its wording stays anchored to it.
     */
    public function narrate(StageContext $context, string $draft): ?string
    {
        $data = $this->dataFrom($context);

        if ($data === null) {
            return null;
        }

        $client = $this->clients?->for(self::MODULE, $context->scope->selectedInstituteId, $context->module->key)
            ?? $this->client;

        if (! $client->isConfigured()) {
            return null;
        }

        $encoded = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR);

        if (! is_string($encoded)) {
            return null;
        }

        $truncated = mb_strlen($encoded) > self::MAX_DATA_CHARS;
        $encoded = mb_substr($encoded, 0, self::MAX_DATA_CHARS);

        try {
            $reply = $client->chat(
                [
                    ['role' => 'system', 'content' => $this->systemPrompt()],
                    ['role' => 'user', 'content' => $this->userPrompt($context, $draft, $encoded, $truncated)],
                ],
                model: null,
                maxTokens: self::MAX_TOKENS,
                temperature: 0.2,
            );
        } catch (Throwable) {
            return null;
        }

        $reply = trim((string) $reply);

        if ($reply === '') {
            return null;
        }

        $stray = $this->numbersNotInData($reply, $encoded . ' ' . $draft . ' ' . $context->question);

        if ($stray !== []) {
            // Quietly keeping the headline is right for the user; recording why is right for
            // whoever wonders why replies are sometimes plainer than they were yesterday.
            try {
                Log::info('ai.narration.discarded', [
                    'reason' => 'figures not present in the tool data',
                    'figures' => $stray,
                    'module' => $context->module->key,
                ]);
            } catch (Throwable) {
                // Logging must never turn a discarded narration into a failed turn.
            }

            return null;
        }

        return $reply;
    }

    // ---------------------------------------------------------------- internals

    /**
     * What the tools returned this turn, or null when narrating would be inventing.
     *
     * Only a turn answered wholly by completed tool calls qualifies. A confirmation, a
     * drafted action, a case from an agent run or a refusal keeps its own wording - those
     * are governed sentences, and a model paraphrasing one is a model changing what a
     * person is about to approve.
     *
     * @return array<int, array<string, mixed>>|null
     */
    private function dataFrom(StageContext $context): ?array
    {
        if ($context->actions() !== [] || $context->cases !== [] || $context->pendingRecommendation !== null
            || $context->get('admissions_flow') !== null) {
            return null;
        }

        $results = $context->get('mcp_step_results', []);

        if (! is_array($results) || $results === []) {
            return null;
        }

        $data = [];

        foreach ($results as $step => $payload) {
            if (! is_array($payload) || ($payload['success'] ?? true) === false) {
                continue;
            }

            $body = is_array($payload['data'] ?? null) ? $payload['data'] : $payload;

            $data[] = ['step' => (string) $step, 'result' => $this->slim($body)];
        }

        return $data === [] ? null : $data;
    }

    /**
     * Drop presentation-only and repeated keys so the model reads facts, not scaffolding.
     *
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    private function slim(array $body): array
    {
        unset($body['headline'], $body['basis'], $body['ranked_by'], $body['money_fields']);

        return $body;
    }

    private function systemPrompt(): string
    {
        return <<<PROMPT
        You are the assistant inside a school's management system, talking with a member of staff.
        Write the reply to their question from the DATA provided, and only from it.

        How to answer:
        - Answer the question that was asked, first, in plain language. Do not describe the data or the system.
        - A simple question gets one or two sentences. A question that asks for analysis, priorities,
          risk or a summary gets a short structured answer: the main point, the evidence, and what to look at first.
        - Use only facts and numbers that appear in the DATA. Do not add, average, extrapolate or estimate.
          You may compare, rank and order what is there, and say which is largest or oldest.
        - If the DATA does not contain what was asked, say so plainly and say what it does contain.
          Never guess, and never fill a gap with typical or general figures.
        - If a result covers only part of the school (a sample, a limited list, "showing the N who owe the most"),
          say so.
        - Money is in rupees with Indian grouping, for example ₹2,92,299. Refer to students by name.
        - Do not mention tools, databases, JSON, ids, keys or field names. No greetings, no apologies, no emoji.
        - Where a next step is genuinely useful, offer one short suggestion at the end. Do not invent actions
          the system cannot do.
        PROMPT;
    }

    private function userPrompt(StageContext $context, string $draft, string $encoded, bool $truncated): string
    {
        $earlier = $context->thread['memory']['last_result_set']['question'] ?? null;

        return implode("\n\n", array_filter([
            'QUESTION: ' . $context->question,
            is_string($earlier) && $earlier !== '' && $earlier !== $context->question
                ? 'EARLIER IN THIS CONVERSATION THE USER ASKED: ' . $earlier
                : null,
            'PLAIN STATEMENT OF THE RESULT: ' . $draft,
            'DATA' . ($truncated ? ' (cut short - more rows exist than are shown)' : '') . ': ' . $encoded,
        ]));
    }

    /**
     * Figures in the reply that the source text never contained.
     *
     * Compared as digit strings with grouping removed, so ₹2,92,299 in the reply matches
     * 292299 in the data. Small numbers are exempt: "the first two" and "3 classes" are
     * counting what the reply itself lists, and a false positive here would discard good
     * answers far more often than it would catch a wrong one.
     *
     * @return array<int, string>
     */
    private function numbersNotInData(string $reply, string $source): array
    {
        $known = [];

        preg_match_all('/\d[\d,]*(?:\.\d+)?/', $source, $found);

        foreach ($found[0] as $number) {
            $known[$this->canonical($number)] = true;
        }

        preg_match_all('/\d[\d,]*(?:\.\d+)?/', $reply, $used);

        $stray = [];

        foreach ($used[0] as $number) {
            $canonical = $this->canonical($number);

            if ((float) $canonical < self::CHECK_FROM || isset($known[$canonical])) {
                continue;
            }

            $stray[] = $number;
        }

        return array_values(array_unique($stray));
    }

    private function canonical(string $number): string
    {
        $number = rtrim(str_replace(',', '', $number), '.');

        if (str_contains($number, '.')) {
            $number = rtrim(rtrim($number, '0'), '.');
        }

        return ltrim($number, '0') === '' ? '0' : ltrim($number, '0');
    }
}
