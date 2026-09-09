<?php

namespace App\Services;

use Anthropic\Client as AnthropicClient;
use Anthropic\Core\Exceptions\APIStatusException;
use Anthropic\Lib\Streaming\MessageAccumulator;
use App\Models\lms\contentModel;
use App\Services\Content\RendersGeneratedContent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Writes chapter content into content_master with Claude, replacing the
 * Gamma (presentation) and Gemini (document) providers for the chapters
 * listed in config('claude.chapter_ids').
 *
 * Modelled on App\Services\QuestionGenerationService, which does the same job
 * for lms_question_master: config file -> service -> provider call -> rows.
 *
 * The prompt is NOT built here. Every content-generation prompt lives in the
 * frontend drawer (lms_k12 .../chapters/sideDrawer.tsx) and arrives already
 * assembled, so the templates have exactly one home and cannot drift between
 * the two repos.
 */
class ContentGenerationService
{
    use RendersGeneratedContent;

    /**
     * Output-format instruction only - no pedagogy, no subject matter.
     *
     * The four document prompts already carry these same PDF formatting rules
     * (PDF_FORMATTING_INSTRUCTIONS in sideDrawer.tsx). The two presentation
     * prompts do not: they ask for slide-by-slide prose, because Gamma used to
     * do the rendering. Repeating the rules in the system prompt makes the
     * presentation output land as HTML too, without editing any prompt.
     */
    private const OUTPUT_FORMAT_SYSTEM = "Return the finished document as clean HTML suitable for direct PDF conversion.\n"
        . "- Use semantic HTML tags such as <h2>, <h3>, <p>, <strong>, <ul>, <ol>, <li>, and <table> where appropriate.\n"
        . "- Do not use Markdown syntax such as #, ##, **, *, backticks, or code fences.\n"
        . "- Use clear section headings, bold emphasis, readable lists, adequate spacing, and a professional document layout.\n"
        . "- Return only the document body content. Do not wrap it in markdown fences, and do not add commentary before or after it.";

    /**
     * Is this chapter served by Claude?
     *
     * The only gate. A chapter that is not listed falls straight through to the
     * existing Gamma/Gemini branches, so nothing else in the estate changes.
     */
    public function handles($chapterId): bool
    {
        $chapterId = (int) $chapterId;
        if ($chapterId <= 0) {
            return false;
        }

        $configured = trim((string) config('claude.chapter_ids', ''));
        if ($configured === '') {
            return false;
        }

        if ($configured === '*') {
            return true;
        }

        $allowed = array_filter(array_map(
            static fn ($id) => (int) trim($id),
            explode(',', $configured)
        ));

        return in_array($chapterId, $allowed, true);
    }

    /**
     * Key precedence matches every other provider in this app (and the promise
     * in the .env comment): the rotating ai_api_keys table first, .env second.
     */
    protected function resolveApiKey(): string
    {
        $apiType = config('claude.api_type', 'ANTHROPIC_API_KEY');

        // getAIKey() is declared inside the App\Helpers namespace, so an
        // unqualified call from here does not resolve to it - the same reason
        // QuestionGenerationService guards the call and queries the table
        // directly when the guard fails. Both names are checked so this keeps
        // working if the helper is ever moved to the global namespace.
        $row = null;
        if (function_exists('getAIKey')) {
            $row = getAIKey($apiType, 1);
        } elseif (function_exists('App\Helpers\getAIKey')) {
            $row = \App\Helpers\getAIKey($apiType, 1);
        } else {
            $row = DB::table('ai_api_keys')
                ->where('api_type', $apiType)
                ->where('status', 1)
                ->first();
        }

        // getAIKey() returns the string '-' rather than null when it misses.
        if (is_object($row) && !empty($row->api_key) && $row->api_key !== '-') {
            return trim((string) $row->api_key);
        }

        return trim((string) config('claude.api_key', ''));
    }

    /**
     * Generate one content item and store it.
     *
     * @param array{
     *   prompt:string, content_type:string, is_presentation:bool,
     *   chapter:object, grade_id:int, chapter_name:string,
     *   concept_id:?int, sub_institute_id:?int, syear:?int,
     *   created_by:?int, user_profile_name:?string
     * } $input
     *
     * @return array{http:int, body:array<string,mixed>}
     */
    public function generate(array $input): array
    {
        // The controller sets 600s for the Gamma/Gemini paths; a long-form
        // deck at effort=high can outlast that.
        @set_time_limit((int) config('claude.timeout_seconds', 600) + 120);

        $apiKey = $this->resolveApiKey();
        if ($apiKey === '') {
            return $this->fail(
                'Claude API key is not configured. Set ANTHROPIC_API_KEY in the backend .env file, '
                . 'or add an ai_api_keys row with api_type=' . config('claude.api_type', 'ANTHROPIC_API_KEY') . ' and status=1.',
                500
            );
        }

        $prompt = trim((string) ($input['prompt'] ?? ''));
        if ($prompt === '') {
            return $this->fail('The generation prompt was empty.', 422);
        }

        $contentType = (string) $input['content_type'];
        $model = (string) config('claude.model', 'claude-opus-5');

        try {
            $generated = $this->callClaude($apiKey, $model, $prompt);
        } catch (APIStatusException $e) {
            Log::error('Claude content generation failed', [
                'chapter_id' => $input['chapter']->id ?? null,
                'content_type' => $contentType,
                'status' => $e->status,
                'error' => $e->getMessage(),
            ]);

            return $this->fail($this->readableApiError($e), 502);
        } catch (Throwable $e) {
            Log::error('Claude content generation failed', [
                'chapter_id' => $input['chapter']->id ?? null,
                'content_type' => $contentType,
                'error' => $e->getMessage(),
            ]);

            return $this->fail('Claude content generation failed: ' . $e->getMessage(), 500);
        }

        if (trim($generated['text']) === '') {
            $suffix = $generated['stop_reason'] ? ' (stop reason: ' . $generated['stop_reason'] . ')' : '';

            return $this->fail('Claude returned an empty document' . $suffix . '.', 502);
        }

        return $this->persist($input, $generated, $model);
    }

    /**
     * Store content that was authored elsewhere, skipping the provider call.
     *
     * Same rendering, sanitising, upload and insert as generate() - only the
     * step that asks a model for the text is bypassed. Used when the document
     * has already been written (an authoring session, an import, a human
     * editor) and only needs to land in content_master.
     *
     * @return array{http:int, body:array<string,mixed>}
     */
    public function storeAuthoredContent(array $input, string $html, string $authoredBy = 'authored'): array
    {
        @set_time_limit((int) config('claude.timeout_seconds', 600) + 120);

        if (trim($html) === '') {
            return $this->fail('The supplied document was empty.', 422);
        }

        return $this->persist($input, [
            'text' => $html,
            'stop_reason' => 'end_turn',
            'input_tokens' => 0,
            'output_tokens' => 0,
        ], $authoredBy);
    }

    /**
     * One streamed Messages API call.
     *
     * Streamed rather than a plain create() because max_output_tokens is well
     * above the range a non-streaming HTTP request comfortably survives, and a
     * full chapter deck routinely uses it.
     *
     * Deliberately absent: temperature, top_p, top_k, budget_tokens and
     * assistant prefill. Claude Opus 5 rejects every one of them with a 400.
     * QuestionGenerationService sends temperature to DeepSeek - do not copy
     * that pattern here.
     *
     * @return array{text:string, stop_reason:?string, input_tokens:int, output_tokens:int}
     */
    protected function callClaude(string $apiKey, string $model, string $prompt): array
    {
        $client = new AnthropicClient(apiKey: $apiKey);

        $stream = $client->messages->createStream(
            maxTokens: (int) config('claude.max_output_tokens', 32000),
            messages: [['role' => 'user', 'content' => $prompt]],
            model: $model,
            outputConfig: ['effort' => config('claude.effort', 'high')],
            system: self::OUTPUT_FORMAT_SYSTEM,
            thinking: ['type' => 'adaptive'],
            requestOptions: ['timeout' => (float) config('claude.timeout_seconds', 600)],
        );

        $accumulator = MessageAccumulator::forMessages();
        foreach ($stream as $event) {
            $accumulator->accumulate($event);
        }
        $message = $accumulator->message();

        // A policy decline arrives as HTTP 200 with stop_reason 'refusal', so it
        // has to be checked before the content is read.
        if ($message->stopReason === 'refusal') {
            $reason = $message->stopDetails->explanation ?? 'no explanation given';

            throw new \RuntimeException('Claude declined to generate this content (' . $reason . ').');
        }

        // With adaptive thinking on, content[0] is a ThinkingBlock, not text.
        // Concatenate every text block instead of indexing.
        $text = '';
        foreach ($message->content as $block) {
            if (($block->type ?? null) === 'text') {
                $text .= $block->text;
            }
        }

        return [
            'text' => $text,
            'stop_reason' => $message->stopReason,
            'input_tokens' => (int) ($message->usage->inputTokens ?? 0),
            'output_tokens' => (int) ($message->usage->outputTokens ?? 0),
        ];
    }

    /**
     * Render, upload and insert.
     *
     * @param array{text:string, stop_reason:?string, input_tokens:int, output_tokens:int} $generated
     *
     * @return array{http:int, body:array<string,mixed>}
     */
    protected function persist(array $input, array $generated, string $model): array
    {
        $chapter = $input['chapter'];
        $contentType = (string) $input['content_type'];
        $chapterName = (string) $input['chapter_name'];

        // Sanitised server-side down to a tag allowlist, so what lands in
        // content_master.description is already safe to render.
        $html = $this->formatGeneratedPdfBody($generated['text']);

        $renderer = $this->resolvePresentationRenderer((bool) ($input['is_presentation'] ?? false));
        $binary = $renderer($generated['text'], $chapterName, $contentType);

        $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '_', $contentType . '_' . $chapterName));
        $fileName = trim($slug, '_') . '_' . time() . '.pdf';
        $spacesPath = 'public/lms_content_file/' . $fileName;

        Storage::disk('digitalocean')->put($spacesPath, $binary, 'public');
        $fileUrl = Storage::disk('digitalocean')->url($spacesPath);

        $content = [
            'grade_id' => $input['grade_id'],
            'standard_id' => $chapter->standard_id,
            'subject_id' => $chapter->subject_id,
            'chapter_id' => $chapter->id,
            'topic_id' => null,
            'concept_id' => $input['concept_id'] ?? null,
            // varchar(250) / varchar(10) respectively on the live table.
            // The Gemini branch writes both raw; truncate so a long chapter
            // name or profile label cannot fail the insert under strict mode.
            'title' => mb_substr($chapterName . ' ' . $contentType, 0, 250),
            // The generated document itself. The Gamma/Gemini branches store the
            // prompt here instead; this is the column that makes the content
            // readable straight out of the database.
            'description' => $html,
            'file_folder' => '/lms_content_file',
            'filename' => $fileName,
            'url' => $fileUrl,
            'file_type' => 'pdf',
            'file_size' => strlen($binary) ?: null,
            'show_hide' => '1',
            'sort_order' => null,
            'meta_tags' => null,
            'content_category' => $contentType,
            'source' => config('claude.source_label', 'Claude AI'),
            'created_by' => $input['created_by'] ?? null,
            'sub_institute_id' => $input['sub_institute_id'] ?? null,
            'restrict_date' => null,
            'pre_grade_topic' => null,
            'post_grade_topic' => null,
            'cross_curriculum_grade_topic' => null,
            'basic_advance' => '1',
            'user_profile_name' => mb_substr((string) ($input['user_profile_name'] ?? ''), 0, 10) ?: null,
            'syear' => $input['syear'] ?? null,
        ];

        // NOTE: content_type and presentation_type are intentionally absent.
        // contentModel::$fillable lists both and migrations exist for them, but
        // they have never been applied to this database - content_master has
        // neither column, so including them here fails with "Unknown column".

        contentModel::insert($content);
        $lastId = DB::getPDO()->lastInsertId();

        return [
            'http' => 201,
            'body' => [
                'success' => true,
                'status_code' => 1,
                'message' => $contentType . ' generated with Claude and stored successfully',
                'data' => [
                    'id' => $lastId,
                    'file_url' => $fileUrl,
                    'storage_path' => $spacesPath,
                    'filename' => $fileName,
                    'file_type' => 'pdf',
                    'content_category' => $contentType,
                    'source' => config('claude.source_label', 'Claude AI'),
                    'model' => $model,
                    'input_tokens' => $generated['input_tokens'],
                    'output_tokens' => $generated['output_tokens'],
                ],
            ],
        ];
    }

    /**
     * Seam for a real .pptx renderer.
     *
     * Both presentation types currently render through the same HTML -> PDF
     * pipeline as the documents. To emit an actual deck instead, return a
     * different callable here for $isPresentation - a Claude Agent Skills call
     * (code_execution + python-pptx, artifact retrieved through the Files API)
     * is the intended replacement. Callers do not change; only file_type and
     * filename in persist() would need to follow.
     *
     * @return callable(string,string,string):string
     */
    protected function resolvePresentationRenderer(bool $isPresentation): callable
    {
        return fn (string $body, string $chapterName, string $contentType): string
            => $this->renderGeneratedContentPdf($body, $chapterName, $contentType);
    }

    protected function readableApiError(APIStatusException $e): string
    {
        $status = (int) ($e->status ?? 0);

        return match (true) {
            $status === 401 => 'The Claude API key was rejected. Check ANTHROPIC_API_KEY.',
            $status === 403 => 'The Claude API key is not permitted to use ' . config('claude.model') . '.',
            $status === 404 => 'Claude model "' . config('claude.model') . '" was not found. Check ANTHROPIC_MODEL.',
            $status === 429 => 'Claude is rate limiting this account. Try again shortly.',
            $status >= 500 => 'Claude is temporarily unavailable (HTTP ' . $status . '). Try again shortly.',
            default => 'Claude rejected the request (HTTP ' . $status . '): ' . $e->getMessage(),
        };
    }

    /**
     * @return array{http:int, body:array<string,mixed>}
     */
    protected function fail(string $message, int $http): array
    {
        return [
            'http' => $http,
            'body' => ['success' => false, 'status_code' => 0, 'message' => $message],
        ];
    }
}
