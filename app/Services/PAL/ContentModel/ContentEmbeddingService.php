<?php

namespace App\Services\PAL\ContentModel;

use App\Domain\AI\Support\GeminiClient;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Collection;
use Throwable;

/**
 * Classic RAG for the Content Authoring row: auto-tag an uploaded curriculum
 * document (`content_master`) against the concept graph — chunk, embed,
 * cosine-similarity, no new infrastructure.
 *
 * Replicates hpbrain_backend's `Embeddings.php` / `KnowledgeIngestionService.
 * php` pattern (read in full before writing this, 2026-10-10): simple
 * paragraph chunking up to ~1000 chars, one Gemini `:embedContent` call per
 * chunk, one JSON column holding `[{index, hash, text, vector}, ...]`, plain
 * PHP cosine similarity at query time — no vector DB extension, same as
 * there. `GeminiClient::embed()` (added alongside this class) is the local
 * equivalent of hpbrain's `GeminiProvider::embed()`.
 *
 * `content_master` has no extracted document-BODY column — unlike hpbrain's
 * `hpbrain_knowledge_assets` — so the text embedded here is whatever
 * curriculum metadata the row actually carries (title/description/
 * meta_tags). Extracting text out of the uploaded FILE itself (PDF/DOCX
 * parsing) is a separate, materially larger piece of work and is
 * deliberately out of scope here; this is the same "say so plainly" case
 * config/neo4j.php's own docblock already applies to this table.
 *
 * CONTENT LAW C5 (see `ContentModelLlmClient`'s docblock): this class only
 * ever PROPOSES. `suggestConcepts()` returns a ranked list; nothing here
 * writes `content_master.concept_id` or any other "approved" field.
 */
class ContentEmbeddingService
{
    /** Same ceiling as hpbrain's KnowledgeIngestionService::chunk(). */
    private const MAX_CHUNK_CHARS = 1000;

    private const EMBEDDING_VERSION = 'v1';

    public function __construct(private readonly GeminiClient $gemini)
    {
    }

    /** Whether a usable embedding-capable key is configured right now. */
    public function enabled(): bool
    {
        return $this->gemini->isConfigured();
    }

    /**
     * Chunk, embed, and store the embedding for one content_master row.
     *
     * The whole `embedding` column is overwritten on every call, never
     * appended to — idempotent by construction, mirroring hpbrain's own
     * migration note.
     *
     * @return array{ok:bool, chunks?:int, error?:string}
     */
    public function embedAndStore(int $contentMasterId): array
    {
        $row = DB::table('content_master')->where('id', $contentMasterId)->first();

        if ($row === null) {
            return ['ok' => false, 'error' => "content_master#{$contentMasterId} not found"];
        }

        $text = $this->sourceText($row);

        if (trim($text) === '') {
            return ['ok' => false, 'error' => 'This content has no title, description or tags to embed.'];
        }

        $model = (string) config('ai.provider.gemini.embedding_model', 'text-embedding-004');
        $encoded = [];

        foreach ($this->chunk($text) as $index => $chunkText) {
            try {
                $vector = $this->gemini->embed($chunkText, $model);
            } catch (Throwable $e) {
                Log::warning('ContentEmbeddingService embed failed: ' . $e->getMessage(), [
                    'content_master_id' => $contentMasterId,
                ]);

                return ['ok' => false, 'error' => 'AI embedding call failed: ' . $e->getMessage()];
            }

            if ($vector === null) {
                continue;
            }

            $encoded[] = [
                'index'  => $index,
                'hash'   => hash('sha256', $chunkText),
                'text'   => $chunkText,
                'vector' => $vector,
            ];
        }

        if ($encoded === []) {
            return ['ok' => false, 'error' => 'The AI provider returned no usable embedding.'];
        }

        DB::table('content_master')->where('id', $contentMasterId)->update([
            'embedding'         => json_encode($encoded, JSON_UNESCAPED_UNICODE),
            'embedding_model'   => $model,
            'embedding_version' => self::EMBEDDING_VERSION,
        ]);

        return ['ok' => true, 'chunks' => count($encoded)];
    }

    /**
     * Rank candidate `lms_concept` rows against a content_master row's
     * embedding, scoped to the same chapter (falling back to standard+subject
     * when the row carries no chapter_id) — the same scope
     * `pal_question_metadata`/`pal_content_metadata` use elsewhere in this
     * estate, so a suggestion is never drawn from outside the curriculum area
     * the document is actually filed under.
     *
     * Concepts are embedded ON THE FLY, not stored: there are at most a few
     * hundred per chapter, this runs far less often than a student-facing
     * request, and caching a concept's vector would be premature
     * infrastructure for a feature this class's own docblock already scopes
     * down to "no new infrastructure".
     *
     * @return array{ok:bool, suggestions?:array<int, array{concept_id:int, name:string, score:float}>, error?:string}
     */
    public function suggestConcepts(int $contentMasterId, int $limit = 5): array
    {
        $row = DB::table('content_master')->where('id', $contentMasterId)->first();

        if ($row === null) {
            return ['ok' => false, 'error' => "content_master#{$contentMasterId} not found"];
        }

        if ($row->embedding === null) {
            $stored = $this->embedAndStore($contentMasterId);

            if (! $stored['ok']) {
                return $stored;
            }

            $row = DB::table('content_master')->where('id', $contentMasterId)->first();
        }

        $chunks = json_decode((string) $row->embedding, true);

        if (! is_array($chunks) || $chunks === []) {
            return ['ok' => false, 'error' => 'No embedding is stored for this content.'];
        }

        $concepts = $this->candidateConcepts($row);

        if ($concepts->isEmpty()) {
            return ['ok' => false, 'error' => 'No concepts exist in this chapter/subject to compare against.'];
        }

        $model = (string) ($row->embedding_model ?: config('ai.provider.gemini.embedding_model', 'text-embedding-004'));
        $scored = [];

        foreach ($concepts as $concept) {
            $label = trim((string) $concept->name);

            if ($label === '') {
                continue;
            }

            try {
                $vector = $this->gemini->embed($label, $model);
            } catch (Throwable $e) {
                Log::warning('ContentEmbeddingService concept embed failed: ' . $e->getMessage(), [
                    'concept_id' => $concept->id,
                ]);

                continue;
            }

            if ($vector === null) {
                continue;
            }

            $best = 0.0;

            foreach ($chunks as $chunk) {
                $chunkVector = $chunk['vector'] ?? null;

                if (is_array($chunkVector)) {
                    $best = max($best, $this->cosineSimilarity($vector, $chunkVector));
                }
            }

            $scored[] = ['concept_id' => (int) $concept->id, 'name' => $label, 'score' => round($best, 4)];
        }

        usort($scored, static fn ($a, $b) => $b['score'] <=> $a['score']);

        return ['ok' => true, 'suggestions' => array_slice($scored, 0, $limit)];
    }

    /**
     * Retrieval half of the "ask a question against ingested curriculum
     * content" capability (the `knowledge-rag` AI capability's real,
     * K-12-only implementation, added 2026-10-12) — embeds a free-text query
     * and ranks it against every chunk `embedAndStore()` has already saved,
     * scoped to one institute so a question never retrieves another
     * school's documents.
     *
     * Deliberately retrieval-only, same split `suggestConcepts()` already
     * keeps from generation: this never calls the chat model and never
     * writes anything. See `ContentKnowledgeAnswerer` for the generation
     * half, which is the only billed step.
     *
     * @return array{ok:bool, matches?:array<int, array{content_master_id:int, title:string, chunk_text:string, score:float}>, error?:string}
     */
    public function searchChunks(string $query, int $subInstituteId, int $limit = 6): array
    {
        if (trim($query) === '') {
            return ['ok' => false, 'error' => 'A question is required.'];
        }

        $model = (string) config('ai.provider.gemini.embedding_model', 'text-embedding-004');

        try {
            $queryVector = $this->gemini->embed($query, $model);
        } catch (Throwable $e) {
            Log::warning('ContentEmbeddingService searchChunks embed failed: ' . $e->getMessage());

            return ['ok' => false, 'error' => 'AI embedding call failed: ' . $e->getMessage()];
        }

        if ($queryVector === null) {
            return ['ok' => false, 'error' => 'The AI provider returned no usable embedding for this question.'];
        }

        // Same tenant filter every other content_master read in this class
        // applies — a question is answered only from this school's own
        // ingested documents.
        $rows = DB::table('content_master')
            ->where('sub_institute_id', $subInstituteId)
            ->whereNotNull('embedding')
            ->select(['id', 'title', 'embedding'])
            ->limit(500)
            ->get();

        $scored = [];

        foreach ($rows as $row) {
            $chunks = json_decode((string) $row->embedding, true);

            if (! is_array($chunks)) {
                continue;
            }

            foreach ($chunks as $chunk) {
                $chunkVector = $chunk['vector'] ?? null;
                $chunkText = trim((string) ($chunk['text'] ?? ''));

                if (! is_array($chunkVector) || $chunkText === '') {
                    continue;
                }

                $scored[] = [
                    'content_master_id' => (int) $row->id,
                    'title' => trim((string) ($row->title ?? '')),
                    'chunk_text' => $chunkText,
                    'score' => round($this->cosineSimilarity($queryVector, $chunkVector), 4),
                ];
            }
        }

        usort($scored, static fn ($a, $b) => $b['score'] <=> $a['score']);

        return ['ok' => true, 'matches' => array_slice($scored, 0, $limit)];
    }

    /**
     * The mirror of `suggestConcepts()`: given a concept, rank the
     * already-embedded `content_master` rows in its chapter (falling back
     * to subject+standard, same rule `candidateConcepts()` applies in the
     * other direction) by similarity to the concept's own name. Same
     * `embed()` + `cosineSimilarity()` primitives, same scope rule — only
     * the two loop sides are swapped.
     *
     * Added for the Recommendation Engine aggregator, which needs "what
     * content fits this weak concept" rather than `suggestConcepts()`'s
     * "what concepts fit this content". Still only ever proposes (CONTENT
     * LAW C5, see this class's own docblock): nothing here writes
     * `content_master.concept_id` or anything else.
     *
     * @return array{ok:bool, suggestions?:array<int, array{content_master_id:int, title:string, score:float}>, error?:string}
     */
    public function suggestContentForConcept(int $conceptId, int $subInstituteId, int $limit = 3): array
    {
        $concept = DB::table('lms_concept')
            ->where('id', $conceptId)
            ->where('sub_institute_id', $subInstituteId)
            ->first(['id', 'name', 'chapter_id', 'subject_id', 'standard_id']);

        if ($concept === null) {
            return ['ok' => false, 'error' => "concept#{$conceptId} not found for this institute"];
        }

        $name = trim((string) $concept->name);

        if ($name === '') {
            return ['ok' => false, 'error' => 'This concept has no name to compare against.'];
        }

        $query = DB::table('content_master')
            ->where('sub_institute_id', $subInstituteId)
            ->whereNotNull('embedding');

        if (! empty($concept->chapter_id)) {
            $query->where('chapter_id', (int) $concept->chapter_id);
        } elseif (! empty($concept->subject_id) && ! empty($concept->standard_id)) {
            $query->where('subject_id', (int) $concept->subject_id)->where('standard_id', (int) $concept->standard_id);
        } else {
            return ['ok' => false, 'error' => 'This concept has no chapter or subject/standard to scope content by.'];
        }

        $rows = $query->select(['id', 'title', 'embedding'])->limit(200)->get();

        if ($rows->isEmpty()) {
            return ['ok' => false, 'error' => "No embedded content exists in this concept's chapter yet."];
        }

        $model = (string) config('ai.provider.gemini.embedding_model', 'text-embedding-004');

        try {
            $conceptVector = $this->gemini->embed($name, $model);
        } catch (Throwable $e) {
            Log::warning('ContentEmbeddingService suggestContentForConcept embed failed: ' . $e->getMessage(), [
                'concept_id' => $conceptId,
            ]);

            return ['ok' => false, 'error' => 'AI embedding call failed: ' . $e->getMessage()];
        }

        if ($conceptVector === null) {
            return ['ok' => false, 'error' => 'The AI provider returned no usable embedding for this concept.'];
        }

        $scored = [];

        foreach ($rows as $row) {
            $chunks = json_decode((string) $row->embedding, true);

            if (! is_array($chunks) || $chunks === []) {
                continue;
            }

            $best = 0.0;

            foreach ($chunks as $chunk) {
                $chunkVector = $chunk['vector'] ?? null;

                if (is_array($chunkVector)) {
                    $best = max($best, $this->cosineSimilarity($conceptVector, $chunkVector));
                }
            }

            $scored[] = [
                'content_master_id' => (int) $row->id,
                'title' => trim((string) ($row->title ?? '')),
                'score' => round($best, 4),
            ];
        }

        usort($scored, static fn ($a, $b) => $b['score'] <=> $a['score']);

        return ['ok' => true, 'suggestions' => array_slice($scored, 0, $limit)];
    }

    // -----------------------------------------------------------------------

    private function sourceText(object $row): string
    {
        $parts = array_filter([
            trim((string) ($row->title ?? '')),
            trim((string) ($row->description ?? '')),
            trim((string) ($row->meta_tags ?? '')),
        ], static fn ($v) => $v !== '');

        return implode("\n\n", $parts);
    }

    /** @return Collection<int, object> */
    private function candidateConcepts(object $row): Collection
    {
        $query = DB::table('lms_concept');

        if (! empty($row->chapter_id)) {
            $query->where('chapter_id', (int) $row->chapter_id);
        } elseif (! empty($row->subject_id) && ! empty($row->standard_id)) {
            $query->where('subject_id', (int) $row->subject_id)->where('standard_id', (int) $row->standard_id);
        } else {
            return collect();
        }

        return $query->select(['id', 'name'])->limit(200)->get();
    }

    /**
     * Simple paragraph chunking up to MAX_CHUNK_CHARS — ported from
     * hpbrain_backend's `KnowledgeIngestionService::chunk()`: split on blank
     * lines, merge into buffers up to the limit, hard-split any paragraph
     * longer than the limit on its own. No overlap, no token counting —
     * "simple on purpose", same reasoning hpbrain's own docblock gives.
     *
     * @return string[]
     */
    private function chunk(string $text): array
    {
        $paragraphs = preg_split('/\n\s*\n/', trim($text)) ?: [trim($text)];

        $chunks = [];
        $buffer = '';

        foreach ($paragraphs as $paragraph) {
            $paragraph = trim($paragraph);

            if ($paragraph === '') {
                continue;
            }

            if (mb_strlen($paragraph) > self::MAX_CHUNK_CHARS) {
                if ($buffer !== '') {
                    $chunks[] = $buffer;
                    $buffer = '';
                }

                foreach (mb_str_split($paragraph, self::MAX_CHUNK_CHARS) as $piece) {
                    $chunks[] = $piece;
                }

                continue;
            }

            $candidate = $buffer === '' ? $paragraph : $buffer . "\n\n" . $paragraph;

            if (mb_strlen($candidate) > self::MAX_CHUNK_CHARS) {
                $chunks[] = $buffer;
                $buffer = $paragraph;
            } else {
                $buffer = $candidate;
            }
        }

        if ($buffer !== '') {
            $chunks[] = $buffer;
        }

        return $chunks === [] ? [trim($text)] : $chunks;
    }

    /**
     * Ported from hpbrain_backend's `Embeddings::cosineSimilarity()` — a
     * plain dot-product/magnitude loop. No DB vector extension (MariaDB here
     * has none either).
     */
    private function cosineSimilarity(array $a, array $b): float
    {
        $length = min(count($a), count($b));

        if ($length === 0) {
            return 0.0;
        }

        $dot = 0.0;
        $magA = 0.0;
        $magB = 0.0;

        for ($i = 0; $i < $length; $i++) {
            $dot += $a[$i] * $b[$i];
            $magA += $a[$i] ** 2;
            $magB += $b[$i] ** 2;
        }

        if ($magA <= 0.0 || $magB <= 0.0) {
            return 0.0;
        }

        return $dot / (sqrt($magA) * sqrt($magB));
    }
}
