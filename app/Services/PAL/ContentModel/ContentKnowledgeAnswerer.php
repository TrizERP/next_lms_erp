<?php

namespace App\Services\PAL\ContentModel;

use App\Domain\AI\Support\ModelClient;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Generation half of the "ask a question against ingested curriculum
 * content" capability — the real, K-12-only implementation of the
 * `knowledge-rag` AI capability (`packages/ai-intelligence-core`'s
 * registry.ts), added 2026-10-12 alongside `ContentEmbeddingService
 * ::searchChunks()`.
 *
 * Retrieval-then-generation, the same split `CoherenceMapController
 * ::explain()` and `CareerQuestNarrator` already use elsewhere in this
 * estate: `ContentEmbeddingService::searchChunks()` does the one retrieval
 * (zero LLM calls, unchanged by this class), and this does the one LLM
 * call, grounded in nothing but the chunks that call actually returned.
 *
 * Three statuses only, the same vocabulary every narrative this estate
 * ships uses — `ok` / `insufficient_evidence` / `model_unavailable` — never
 * a fourth state and never a fabricated answer when retrieval comes back
 * thin. `insufficient_evidence` covers both "nothing is embedded yet" and
 * "something is embedded but none of it is actually related to this
 * question" — both are honest "no" answers, not a provider fault.
 *
 * This class does NOT call `GeminiClient::forInstitute()` for the retrieval
 * step — `ContentEmbeddingService`'s own embed() calls already resolve
 * platform keys only (see its constructor), and this class does not change
 * that pre-existing choice. The generation step below DOES scope to the
 * caller's institute, matching `CareerQuestNarrator::narrate()` exactly,
 * because a chat call (unlike embedding) is squarely the kind of per-school
 * billed call the rest of this estate always scopes.
 */
class ContentKnowledgeAnswerer
{
    /** Below this cosine score, a chunk is unrelated noise, not weak context worth citing. */
    private const MIN_RELEVANCE = 0.45;

    public function __construct(
        private readonly ContentEmbeddingService $retrieval,
        private readonly ModelClient $model,
    ) {
    }

    /**
     * @return array{status:string, answer:?string, message:?string, sources:array<int, array{content_master_id:int, title:string, score:float}>}
     */
    public function answer(string $question, int $subInstituteId, int $limit = 6): array
    {
        $question = trim($question);

        if ($question === '') {
            return $this->result('insufficient_evidence', null, 'A question is required.', []);
        }

        if (! $this->retrieval->enabled()) {
            return $this->result('model_unavailable', null, 'No AI model is configured for this institute.', []);
        }

        $retrieved = $this->retrieval->searchChunks($question, $subInstituteId, $limit);

        if (! ($retrieved['ok'] ?? false)) {
            // searchChunks() failed before it ever found anything to rank —
            // a bad key or a provider outage, not "nothing relevant exists".
            return $this->result(
                'model_unavailable',
                null,
                (string) ($retrieved['error'] ?? 'The AI provider could not be reached.'),
                []
            );
        }

        $matches = $retrieved['matches'] ?? [];
        $relevant = array_values(array_filter(
            $matches,
            static fn (array $match) => (float) ($match['score'] ?? 0.0) >= self::MIN_RELEVANCE
        ));

        if ($relevant === []) {
            return $this->result(
                'insufficient_evidence',
                null,
                'No ingested curriculum content was found that is related enough to answer this grounded.',
                []
            );
        }

        $client = $this->model->forInstitute($subInstituteId);

        if (! $client->isConfigured()) {
            return $this->result('model_unavailable', null, 'No AI model is configured for this institute.', []);
        }

        try {
            $answer = $client->chat(
                [
                    ['role' => 'system', 'content' => $this->systemPrompt()],
                    ['role' => 'user', 'content' => $this->userPrompt($question, $relevant)],
                ],
                model: null,
                maxTokens: 1024,
                temperature: 0.2,
            );
        } catch (Throwable $exception) {
            Log::warning('ContentKnowledgeAnswerer chat failed: ' . $exception->getMessage());

            return $this->result('model_unavailable', null, 'The answer could not be generated.', []);
        }

        $answer = trim((string) $answer);

        if ($answer === '') {
            return $this->result('model_unavailable', null, 'The answer could not be generated.', []);
        }

        $sources = array_map(static fn (array $match) => [
            'content_master_id' => $match['content_master_id'],
            'title' => $match['title'],
            'score' => $match['score'],
        ], $relevant);

        return $this->result('ok', $answer, null, $sources);
    }

    /**
     * @param  array<int, array{content_master_id:int, title:string, score:float}>  $sources
     * @return array{status:string, answer:?string, message:?string, sources:array<int, array{content_master_id:int, title:string, score:float}>}
     */
    private function result(string $status, ?string $answer, ?string $message, array $sources): array
    {
        return ['status' => $status, 'answer' => $answer, 'message' => $message, 'sources' => $sources];
    }

    private function systemPrompt(): string
    {
        return 'You are answering a question about this school\'s own curriculum content. Use only the '
            . 'numbered passages given in the message — never invent a fact, a document, a title, or any '
            . 'detail that is not present in them. If the passages do not actually answer the question, say '
            . 'so plainly rather than guessing. Cite which passage number(s) support each part of your '
            . 'answer. Keep the answer short, factual, and addressed directly to the person asking.';
    }

    /**
     * @param  array<int, array{content_master_id:int, title:string, chunk_text:string, score:float}>  $chunks
     */
    private function userPrompt(string $question, array $chunks): string
    {
        $lines = ["Question: {$question}", '', 'Passages:'];

        foreach (array_values($chunks) as $index => $chunk) {
            $title = $chunk['title'] !== '' ? $chunk['title'] : 'Untitled';
            $lines[] = sprintf('[%d] (%s) %s', $index + 1, $title, $chunk['chunk_text']);
        }

        $lines[] = '';
        $lines[] = 'Answer the question using only the passages above, citing passage numbers.';

        return implode("\n", $lines);
    }
}
