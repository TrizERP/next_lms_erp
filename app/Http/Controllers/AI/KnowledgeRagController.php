<?php

namespace App\Http\Controllers\AI;

use App\Services\PAL\ContentModel\ContentKnowledgeAnswerer;
use Illuminate\Http\Request;
use Throwable;

/**
 * The real, working half of the `knowledge-rag` AI capability — "ask a
 * question against ingested curriculum content" — for K-12's own
 * `content_master` corpus. Added 2026-10-12 so the capability console at
 * `/ai/knowledge-rag` has something real to call instead of only
 * describing what centralised knowledge-and-RAG would eventually be.
 *
 * This is NOT the shared cross-product ingestion contract the registry
 * entry still (honestly) says does not exist: it answers only from this
 * one institute's own curriculum documents, using the same embed-then-
 * cosine-similarity pattern `ContentEmbeddingService` already proved for
 * content-authoring auto-tagging.
 *
 * Same tenant scoping as every other route in this file's sibling
 * controllers — `scope($request)->selectedInstituteId` comes from the JWT
 * via `McpContextHydrator`, never from request input.
 */
class KnowledgeRagController extends AiController
{
    public function __construct(private readonly ContentKnowledgeAnswerer $answerer)
    {
    }

    public function ask(Request $request)
    {
        try {
            $validated = $request->validate([
                'question' => 'required|string|max:500',
            ]);

            $instituteId = (int) $this->scope($request)->selectedInstituteId;
            $limit = $this->limit($request, 6, 10);

            $result = $this->answerer->answer($validated['question'], $instituteId, $limit, (bool) $this->scope($request)->isStudent);

            return $this->success('Knowledge & RAG answer resolved.', $result);
        } catch (Throwable $exception) {
            return $this->handle($exception);
        }
    }
}
