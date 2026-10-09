<?php

namespace App\Services;

use Anthropic\Client as AnthropicClient;
use Anthropic\Core\Exceptions\APIStatusException;
use Anthropic\Lib\Streaming\MessageAccumulator;
use App\Models\lms\contentModel;
use App\Services\Content\RendersContentPresentation;
use App\Services\Content\RendersGeneratedContent;
use App\Services\StudyDeck\ClaudeApiCompleter;
use App\Services\StudyDeck\ClaudeCliCompleter;
use App\Services\StudyDeck\Contracts\Completer;
use App\Services\StudyDeck\Documents\DocumentKind;
use App\Services\StudyDeck\Documents\StudyDocumentPdfRenderer;
use App\Services\StudyDeck\Documents\StudyDocumentService;
use App\Services\StudyDeck\StudyDeckImages;
use App\Services\StudyDeck\StudyDeckPdfRenderer;
use App\Services\StudyDeck\StudyDeckPublisher;
use App\Services\StudyDeck\StudyDeckQuestions;
use App\Services\StudyDeck\StudyDeckService;
use App\Services\StudyDeck\StudyImageStores;
use Closure;
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
    use RendersContentPresentation;
    use RendersGeneratedContent;

    /** The school whose stored pictures the study-deck render in progress may read (see withStudyDeckImages). */
    protected ?int $deckImageTenant = null;

    /**
     * Output-format instruction only - no pedagogy, no subject matter.
     *
     * The three document prompts already carry these same formatting rules
     * (PDF_FORMATTING_INSTRUCTIONS in sideDrawer.tsx, interpolated at exactly
     * three sites - remedial class, classroom activity, and the generic
     * revision-notes branch). The presentation prompts do not, and must not:
     * they ask for slide-by-slide prose because Gamma builds its own slides
     * from that prose, and handing Gamma HTML would break it.
     *
     * Repeating the rules here is therefore what makes a presentation land as
     * structured HTML when Claude - rather than Gamma - does the rendering,
     * without editing a prompt Gamma still depends on. It is also why the
     * slide markup guidance below appears here and nowhere else.
     */
    private const OUTPUT_FORMAT_SYSTEM = "Return the finished document as clean HTML suitable for direct PDF conversion.\n"
        . "- Use semantic HTML tags such as <h2>, <h3>, <p>, <strong>, <ul>, <ol>, <li>, and <table> where appropriate.\n"
        . "- Do not use Markdown syntax such as #, ##, **, *, backticks, or code fences.\n"
        . "- Return only the document body content. Do not wrap it in markdown fences, and do not add commentary before or after it.\n"
        . "- Do not use inline styles, colours or emoji. Appearance is handled entirely by the stylesheet.\n"
        . "\n"
        . "Use these design-system classes:\n"
        . "- <section class=\"cover\"> with <p class=\"eyebrow\">, <h2> and <p class=\"lede\"> for the opening panel.\n"
        . "- <section class=\"callout callout-key\"> for a key idea, callout-warn for a misconception, "
        . "callout-example for a worked example, callout-try for something the learner must do or answer.\n"
        . "- Every callout opens with <span class=\"callout-label\">Short label</span>, so meaning is never carried by colour alone.\n"
        . "- <table class=\"tiles\"> with <span class=\"tile-num\"> and <span class=\"tile-label\"> for headline figures.\n"
        . "- For a presentation, wrap each slide in <section class=\"slide\"> opening with "
        . "<div class=\"slide-head\"><span class=\"slide-num\">Slide N</span><h3>Title</h3></div>, "
        . "and give every slide at least one callout-try the learner must answer.\n"
        . "\n"
        . "Tag every section with what it teaches:\n"
        . "- data-block: intro, explain, visual, example, real-world, misconception, check, activity, summary or assess\n"
        . "- data-concept: the concept name exactly as given in the prompt\n"
        . "- data-bloom: remember, understand, apply, analyze, evaluate or create (lowercase)\n"
        . "- data-dok: 1, 2, 3 or 4\n"
        . "- data-minutes: an integer\n"
        . "Example: <section class=\"callout callout-warn\" data-block=\"misconception\" data-concept=\"Osmosis\" "
        . "data-bloom=\"understand\" data-dok=\"2\" data-minutes=\"4\"><span class=\"callout-label\">Common misconception</span><p>...</p></section>";

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
     * Generate a classroom study deck for a chapter from its Chapter -> Topic ->
     * Concept -> Concept Intelligence data, existing AI content and question
     * bank, and store it as an ordinary content_master presentation.
     *
     * Same architecture as generate(): the key, model and streaming call are
     * this class's own (callClaude); StudyDeckService only supplies the prompts
     * and the pipeline around them. Nothing is stored unless validation passes.
     *
     * $input takes the same chapter/tenant fields as generate(), minus `prompt`.
     * `claude.executor=cli` swaps in the dev-only CLI completer when no API key
     * exists; it is never chosen implicitly.
     *
     * @return array{http:int, body:array<string,mixed>}
     */
    public function generateStudyDeck(array $input, ?Completer $completer = null): array
    {
        @set_time_limit((int) config('claude.timeout_seconds', 600) * 6 + 120);

        if ($completer === null) {
            if (config('claude.executor', 'api') === 'cli') {
                $completer = new ClaudeCliCompleter();
            } else {
                $apiKey = $this->resolveApiKey();
                if ($apiKey === '') {
                    return $this->fail('Claude API key is not configured. Set ANTHROPIC_API_KEY or an ai_api_keys row.', 500);
                }
                $model = (string) config('claude.model', 'claude-opus-5');
                $completer = new ClaudeApiCompleter(
                    fn (string $system, string $prompt, int $max): array => $this->callClaude($apiKey, $model, $prompt, $system, $max)
                );
            }
        }

        try {
            // Pictures are stored in the database as they are found; the deck refers to them by reference.
            $tenant = (int) ($input['sub_institute_id'] ?? 1);
            $result = StudyDeckService::make($completer, StudyImageStores::database($tenant, (int) $input['chapter']->id))
                ->generate((int) $input['chapter']->id, $tenant);
        } catch (APIStatusException $e) {
            return $this->fail($this->readableApiError($e), 502);
        } catch (Throwable $e) {
            Log::error('Study deck generation failed', ['chapter_id' => $input['chapter']->id ?? null, 'error' => $e->getMessage()]);

            return $this->fail('Study deck generation failed: ' . $e->getMessage(), 500);
        }

        if (!$result['report']['ok']) {
            return [
                'http' => 422,
                'body' => [
                    'success' => false,
                    'status_code' => 0,
                    'message' => 'The study deck failed validation and was not stored.',
                    'errors' => $result['report']['errors'],
                    'warnings' => $result['report']['warnings'],
                ],
            ];
        }

        // The pictures were stored while the deck was generated; publishing checks they are really there.
        return $this->publishStudyDeck($input, $result['deck'], $result['html'], null, false, (string) config('claude.model', 'claude-opus-5'));
    }

    /** Is this content_master file name a study deck? (Its name is its identity: no extra column.) */
    public static function isStudyDeckFilename(?string $filename): bool
    {
        return str_starts_with((string) $filename, 'study_deck_') && str_ends_with((string) $filename, '.pptx');
    }

    /**
     * Where a study deck's classroom PDF lives: beside the presentation, same name, `.pdf`. content_master holds one
     * file per row, so (like the deck file) the second file is found from the row's own file name - no extra column,
     * no second row. Null for anything that is not a study deck.
     */
    public static function studyDeckPdfPath(string $filename): string
    {
        return 'public/lms_content_file/' . preg_replace('/\.pptx$/i', '.pdf', $filename);
    }

    /**
     * The PDF's canonical URL for a content_master row, only when the row is a study deck and the file is really
     * stored (a row without one simply offers no download). Never built from anything the caller sent.
     *
     * @param array<string,mixed> $row a content_master row
     */
    public static function studyDeckPdfUrl(array $row): ?string
    {
        if (!self::isStudyDeckFilename($row['filename'] ?? null)) {
            return null;
        }
        try {
            $disk = Storage::disk('digitalocean');
            $path = self::studyDeckPdfPath((string) $row['filename']);

            return $disk->exists($path) ? $disk->url($path) : null;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Where a study deck row opens: the interactive player, keyed by the content item so it is exactly that deck.
     * Null for every other row. The Classroom Resource list uses it the way it uses an H5P item's `deep_link`.
     *
     * @param array<string,mixed> $row a content_master row
     */
    public static function studyDeckDeepLink(array $row): ?string
    {
        if (!self::isStudyDeckFilename($row['filename'] ?? null) || empty($row['id']) || empty($row['chapter_id'])) {
            return null;
        }

        return '/student/study-deck/' . (int) $row['chapter_id'] . '?content=' . (int) $row['id'];
    }

    /**
     * Where a study deck's player data lives: beside its presentation file, named after it.
     *
     * Deriving the path from the content_master row's own filename means no extra column,
     * no `meta_tags` marker and no lookup table: a row that has a deck has this file.
     */
    public static function studyDeckSidecarPath(string $filename): string
    {
        return 'public/lms_content_file/' . $filename . '.deck.json';
    }

    /**
     * Publish a reviewed study deck: pictures in the shared object store, one content_master row, one deck file.
     *
     * Everything that can fail is done BEFORE a row exists, in this order: pictures stored and read back, the
     * presentation rendered and stored, the deck file stored and read back, and only then the row inserted. A
     * failure at any step removes what this run stored, so no row points at a missing file and no file is left
     * without a row.
     *
     * Safe to repeat. A deck is identified by the hash of its content (it is part of the file name), so storing
     * the same deck again finds its row and changes nothing; a changed deck is a new row, and the study-deck
     * rows it replaces for the chapter are hidden in the same transaction (show_hide = 0, the convention
     * `lms:import-deck --replace` already uses).
     *
     * @param array<string,mixed> $input chapter, chapter_name, grade_id, sub_institute_id, syear, created_by, user_profile_name
     * @param array<string,mixed> $deck SlideHtmlRenderer deck
     * @param string|null $imageDir bundle folder holding `images/<name>` files, or null when the pictures are already stored
     * @return array{http:int, body:array<string,mixed>}
     */
    public function publishStudyDeck(array $input, array $deck, string $html, ?string $imageDir, bool $dryRun = false, string $authoredBy = 'study-deck'): array
    {
        $tenant = (int) ($input['sub_institute_id'] ?? 1);

        // The presentation and the PDF are drawn from pictures read out of the database for this school.
        return $this->withStudyDeckImages($tenant, fn () => $this->publishStudyDeckNow($input, $deck, $html, $imageDir, $dryRun, $authoredBy));
    }

    /**
     * Run a step that draws a deck's pictures (the presentation, the PDF) with those pictures readable: by reference,
     * from the database, and only the ones this school may see. Nothing is written to disk for them.
     *
     * @template T
     * @param callable():T $step
     * @return T
     */
    protected function withStudyDeckImages(int $tenant, callable $step): mixed
    {
        $previous = [$this->deckImageResolver, $this->deckImageTenant];
        $this->deckImageResolver = Closure::fromCallable($this->studyImageBytes($tenant));
        $this->deckImageTenant = $tenant;

        try {
            return $step();
        } finally {
            [$this->deckImageResolver, $this->deckImageTenant] = $previous;
        }
    }

    /**
     * Reads a stored picture by its `study-deck-image:<id>` reference: bytes and type, ready to put in a PDF or a
     * presentation (WebP comes out as PNG), or null when it is not there or not visible to this school.
     *
     * @return callable(string):?array{bytes:string,mime:string}
     */
    protected function studyImageBytes(int $tenant): callable
    {
        $images = new StudyDeckImages();

        return fn (string $ref): ?array => ($id = StudyDeckImages::idFromRef($ref)) !== null ? $images->forDocument($id, $tenant) : null;
    }

    private function publishStudyDeckNow(array $input, array $deck, string $html, ?string $imageDir, bool $dryRun, string $authoredBy): array
    {
        $tenant = (int) ($input['sub_institute_id'] ?? 1);
        $publisher = $this->studyDeckPublisher($tenant);

        try {
            $prepared = $publisher->prepare($deck, $html, $imageDir, !$dryRun);
        } catch (Throwable $e) {
            $publisher->cleanup();

            return $this->fail('The study deck was not stored: ' . $e->getMessage(), 422);
        }
        if ($prepared['missing'] !== []) {
            $publisher->cleanup();

            return $this->fail('The study deck refers to pictures that are not stored for this school: ' . implode(', ', $prepared['missing']), 422);
        }

        $chapter = $input['chapter'];
        $filename = StudyDeckPublisher::filenameFor((string) $input['chapter_name'], $prepared['deck']);
        $sidecar = self::studyDeckSidecarPath($filename);
        $disk = Storage::disk('digitalocean');
        $existing = $this->findStudyDeckRow((int) $chapter->id, $tenant, $filename);

        $summary = [
            'filename' => $filename,
            'deck_metadata_path' => $sidecar,
            'pdf_path' => self::studyDeckPdfPath($filename),
            'slide_count' => $prepared['deck']['slide_count'] ?? null,
            'images' => array_values($prepared['assets']),
            'images_uploaded' => $prepared['uploaded'],
            'images_reused' => $prepared['reused'],
        ];

        if ($dryRun) {
            return ['http' => 200, 'body' => ['success' => true, 'status_code' => 1, 'status' => $existing ? 'unchanged' : 'would_create', 'dry_run' => true, 'content_id' => $existing->id ?? null] + $summary];
        }

        // Same deck, already stored: make sure its files are all there and change nothing else.
        if ($existing) {
            $repaired = false;
            try {
                if (!$disk->exists($sidecar)) {
                    $disk->put($sidecar, $this->studyDeckJson($prepared['deck']), 'public');
                    $repaired = $disk->exists($sidecar);
                }
                // Its pictures are recorded as in use by this deck (a deck stored before the links existed gets them now).
                if ($this->linkStudyDeckImages((int) $existing->id, $prepared['deck'], (int) $chapter->id) > 0) {
                    $repaired = true;
                }
                // A deck stored before the PDF existed (or whose PDF went missing) gets it now, under the same row.
                $pdfPath = self::studyDeckPdfPath($filename);
                // `refresh_pdf` replaces a PDF made by an older layout. The new one is rendered completely BEFORE the
                // stored one is touched, and the object is replaced in a single put, so a failure leaves the old PDF.
                if (!$disk->exists($pdfPath) || !empty($input['refresh_pdf'])) {
                    $pdf = $this->renderStudyDeckPdf($prepared['deck'], ['content_id' => (int) $existing->id, 'tenant' => $tenant]);
                    if ($pdf === '' || !str_starts_with($pdf, '%PDF-')) {
                        throw new \RuntimeException('the PDF came out empty');
                    }
                    $disk->put($pdfPath, $pdf, 'public');
                    if (!$disk->exists($pdfPath) || (int) $disk->size($pdfPath) !== strlen($pdf)) {
                        throw new \RuntimeException('the PDF was not stored correctly');
                    }
                    $repaired = true;
                }
                $restored = (int) $existing->show_hide === 0 ? $this->restoreStudyDeckRow((int) $existing->id, (int) $chapter->id, $tenant) : false;
            } catch (Throwable $e) {
                $publisher->cleanup();

                return $this->fail('The study deck exists but could not be verified: ' . $e->getMessage(), 500);
            }

            return ['http' => 200, 'body' => ['success' => true, 'status_code' => 1, 'status' => $repaired || $restored ? 'repaired' : 'unchanged', 'content_id' => (int) $existing->id, 'file_url' => $existing->url] + $summary];
        }

        $input['filename'] = $filename;
        $input['title'] = mb_substr((string) $input['chapter_name'] . ' Study Deck', 0, 250);
        $result = $this->storeStudyDeck($input, $prepared['html'], $prepared['deck'], $authoredBy);
        if (($result['http'] ?? 0) !== 201) {
            $publisher->cleanup();

            return $result;
        }

        $result['body']['data'] += $summary + ['status' => 'created'];
        $result['body']['status'] = 'created';
        $result['body']['content_id'] = (int) $result['body']['data']['id'];

        return $result;
    }

    /**
     * Persist an already validated, already published (see publishStudyDeck) study deck.
     *
     * The presentation is an ordinary content_master row. The structured deck the native student player reads
     * has no content_master column, so it is a JSON file next to the presentation, named after it. `meta_tags`
     * is deliberately left empty: the concept tagger and the video relevance scorer read that column as text.
     *
     * The deck file is stored BEFORE the row, so a row never exists without its deck, and the previous study
     * decks of the chapter are hidden in the same transaction as the insert.
     *
     * @param array<string,mixed> $deck SlideHtmlRenderer deck
     * @return array{http:int, body:array<string,mixed>}
     */
    public function storeStudyDeck(array $input, string $html, array $deck, string $authoredBy = 'study-deck'): array
    {
        $input['is_presentation'] = true;
        $input['content_type'] = $input['content_type'] ?? 'Classroom Presentation';
        $input['meta_tags'] = null;
        // The classroom PDF is a second file of the same content item (like the deck file), not a second row.
        // It is made before anything is stored, so a PDF that cannot be rendered stops the whole publish.
        try {
            $pdf = $this->renderStudyDeckPdf($deck);
        } catch (Throwable $e) {
            return $this->fail('The study deck was not stored: its PDF could not be made: ' . $e->getMessage(), 500);
        }
        if ($pdf === '') {
            return $this->fail('The study deck was not stored: its PDF came out empty.', 500);
        }
        $input['extra_files'] = [
            self::studyDeckSidecarPath((string) $input['filename']) => $this->studyDeckJson($deck),
            self::studyDeckPdfPath((string) $input['filename']) => $pdf,
        ];
        $chapterId = (int) $input['chapter']->id;
        $tenant = (int) ($input['sub_institute_id'] ?? 1);
        $input['tenant'] = $tenant;
        // Inside the transaction that inserts the row: the deck and the record of which pictures it uses land together or not at all.
        $input['in_transaction'] = function (int $id) use ($chapterId, $tenant, $deck): void {
            $this->hidePreviousStudyDecks($chapterId, $tenant, $id);
            $this->linkStudyDeckImages($id, $deck, $chapterId);
        };

        try {
            $result = $this->withStudyDeckImages($tenant, fn () => $this->storeAuthoredContent($input, $html, $authoredBy));
        } catch (Throwable $e) {
            Log::error('Study deck storage failed', ['chapter_id' => $chapterId, 'error' => $e->getMessage()]);

            return $this->fail('The study deck was not stored: ' . $e->getMessage(), 500);
        }

        if (($result['http'] ?? 0) === 201) {
            $result['body']['data']['deck_metadata_url'] = Storage::disk('digitalocean')->url(self::studyDeckSidecarPath((string) $input['filename']));
            $result['body']['data']['pdf_path'] = self::studyDeckPdfPath((string) $input['filename']);
            $result['body']['data']['pdf_url'] = Storage::disk('digitalocean')->url(self::studyDeckPdfPath((string) $input['filename']));
            $result['body']['data']['slide_count'] = $deck['slide_count'] ?? null;
        }

        return $result;
    }

    /**
     * The classroom PDF, drawn from the stored deck so that what the interactive player reveals (hotspot and card
     * explanations, steps, scenarios, definitions) is written out in full. Same Dompdf pipeline and print stylesheet
     * every other generated document uses.
     *
     * @param array<string,mixed> $deck
     */
    protected function renderStudyDeckPdf(array $deck, array $options = []): string
    {
        // `tenant` is the school that owns the deck: the pictures it may draw are that school's (and the platform library's).
        $tenant = (int) ($options['tenant'] ?? $this->deckImageTenant ?? 1);

        return $this->withStudyDeckImages($tenant, fn () => $this->drawStudyDeckPdf($deck, $options));
    }

    private function drawStudyDeckPdf(array $deck, array $options): string
    {
        // Stored pictures are read from the database and embedded in the page (never fetched over the network).
        $renderer = new StudyDeckPdfRenderer($this->deckImageResolver, base_path('vendor/dompdf/dompdf/lib/fonts/DejaVuSans-Bold.ttf'));
        $html = $renderer->html($deck, $this->generatedContentCss(), [
            'variant' => $options['variant'] ?? StudyDeckPdfRenderer::REVISION,
            'questions' => $this->loadStudyDeckQuestions(StudyDeckQuestions::idsIn($deck)),
            'link_base' => $this->studyDeckLinkBase($deck),
            'content_id' => $options['content_id'] ?? null,
        ]);

        $title = StudyDeckPdfRenderer::runningTitle($deck);
        $subject = StudyDeckPdfRenderer::runningSubject($deck);

        return $this->renderHtmlToPdf($html, $this->studyPageFurniture($title, $subject, 'Study Deck'));
    }

    /**
     * The running header and footer of a study deck's PDF (and of a study document's): the title and the class and
     * subject on top, the kind of document and "Page n of N" below, on every page but the cover.
     *
     * @return callable(\Dompdf\Dompdf):void
     */
    protected function studyPageFurniture(string $title, string $subject, string $footerLabel): callable
    {
        return function ($dompdf) use ($title, $subject, $footerLabel): void {
            // A running header and footer with the page number, on every page but the cover.
            $dompdf->getCanvas()->page_script(function ($pageNumber, $pageCount, $canvas, $fontMetrics) use ($title, $subject, $footerLabel): void {
                if ($pageNumber === 1) {
                    return;
                }
                $font = $fontMetrics->getFont('DejaVu Sans', 'normal');
                $bold = $fontMetrics->getFont('DejaVu Sans', 'bold');
                $w = $canvas->get_width();
                $h = $canvas->get_height();
                $ink = [0.26, 0.22, 0.79];
                $grey = [0.39, 0.45, 0.55];
                $fit = function (string $text, $f, float $size, float $max) use ($fontMetrics): string {
                    while (mb_strlen($text) > 4 && $fontMetrics->getTextWidth($text, $f, $size) > $max) {
                        $text = rtrim(mb_substr($text, 0, -2)) . '…';
                    }

                    return $text;
                };
                $canvas->line(31.5, 38, $w - 31.5, 38, [0.78, 0.82, 0.99], 0.8);
                $canvas->text(31.5, 26, $fit($title, $bold, 8, $w - 63 - 140), $bold, 8, $ink);
                $canvas->text($w - 31.5 - $fontMetrics->getTextWidth($subject, $font, 8), 26, $subject, $font, 8, $grey);
                $canvas->line(31.5, $h - 38, $w - 31.5, $h - 38, [0.89, 0.91, 0.94], 0.8);
                $canvas->text(31.5, $h - 28, $footerLabel, $font, 8, $grey);
                $label = 'Page ' . $pageNumber . ' of ' . $pageCount;
                $canvas->text($w - 31.5 - $fontMetrics->getTextWidth($label, $font, 8), $h - 28, $label, $font, 8, $grey);
            });
        };
    }

    /**
     * The student app's address for this deck, or null when it is not configured (the PDF then has no links).
     *
     * @param array<string,mixed> $deck
     */
    protected function studyDeckLinkBase(array $deck): ?string
    {
        $base = rtrim((string) config('claude.study_deck_frontend_url', ''), '/');
        $chapter = (int) ($deck['chapter']['id'] ?? 0);

        return $base !== '' && $chapter > 0 ? $base . '/student/study-deck/' . $chapter : null;
    }

    /**
     * The bank questions a deck points at, normalised. A SELECT only.
     *
     * @param array<int,int> $ids
     * @return array<int,array<string,mixed>>
     */
    protected function loadStudyDeckQuestions(array $ids): array
    {
        try {
            return (new StudyDeckQuestions())->load($ids);
        } catch (Throwable $e) {
            // The questions are an addition to the lesson; the PDF is still made without them.
            Log::warning('Study deck PDF: practice questions could not be read', ['error' => $e->getMessage()]);

            return [];
        }
    }

    /**
     * The PDF bytes for a stored deck, drawn now: the revision copy (answers shown) or the practice copy (answers left
     * out). The default copy is stored; the practice copy is only ever made on request and is not kept.
     *
     * @param array<string,mixed> $deck
     * @param array{variant?:string, content_id?:?int, tenant?:int} $options `tenant` is the school that owns the deck
     */
    public function studyDeckPdfBytes(array $deck, array $options = []): string
    {
        return $this->renderStudyDeckPdf($deck, $options);
    }

    protected function studyDeckPublisher(int $tenant): StudyDeckPublisher
    {
        return StudyDeckPublisher::make($tenant);
    }

    /**
     * Record that a stored deck uses the pictures its `assets` map lists, so they are not removed as leftovers.
     *
     * @param array<string,mixed> $deck a prepared deck (see StudyDeckPublisher::prepare)
     * @return int how many links were new
     */
    protected function linkStudyDeckImages(int $contentId, array $deck, ?int $chapterId): int
    {
        $ids = StudyDeckPublisher::imageIds(['assets' => (array) ($deck['assets'] ?? [])]);

        return $ids === [] ? 0 : (new StudyDeckImages())->link($contentId, $ids, $chapterId);
    }

    protected function studyDeckJson(array $deck): string
    {
        return (string) json_encode($deck, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /** The row for this exact deck on this chapter and school, visible or hidden. */
    protected function findStudyDeckRow(int $chapterId, int $tenant, string $filename): ?object
    {
        return DB::table('content_master')
            ->where('chapter_id', $chapterId)
            ->where('sub_institute_id', $tenant)
            ->where('filename', $filename)
            ->first(['id', 'url', 'show_hide']);
    }

    /** Hide the other study decks of this chapter and school: the new one replaces them. */
    protected function hidePreviousStudyDecks(int $chapterId, int $tenant, int $keepId): int
    {
        return DB::table('content_master')
            ->where('chapter_id', $chapterId)
            ->where('sub_institute_id', $tenant)
            ->where('file_type', 'pptx')
            ->where('filename', 'like', 'study\\_deck\\_%')
            ->where('id', '<>', $keepId)
            ->where(fn ($q) => $q->whereNull('show_hide')->orWhere('show_hide', '<>', 0))
            ->update(['show_hide' => 0]);
    }

    /** Make an older, hidden study deck the current one again (and hide the rest). */
    protected function restoreStudyDeckRow(int $id, int $chapterId, int $tenant): bool
    {
        return (bool) DB::transaction(function () use ($id, $chapterId, $tenant) {
            DB::table('content_master')->where('id', $id)->update(['show_hide' => 1]);
            $this->hidePreviousStudyDecks($chapterId, $tenant, $id);

            return true;
        });
    }

    // =============================================================================================================
    // Study documents: revision notes, a remedial class, classroom activities
    //
    // The same architecture as a study deck with the kind-specific middle swapped (see StudyDeck\Documents): the same
    // Completer, the same pictures in the database, the same Dompdf pipeline and print stylesheet, the same content row
    // convention (ONE content_master row, its structured source and its PDF beside it), and the same checks before
    // anything is stored. The category a row is filed under is the one the content library already uses for it
    // ("Revision Notes", "Remedial Class", "Classroom Activity"); the row's PDF is its primary file.

    /**
     * Which kind of study document a requested content type should be written as for this chapter, or null when it
     * should be written the way it always was (a chapter Claude does not serve, a type that is not one of the three,
     * or `claude.study_documents` switched off, which restores the earlier single-prompt documents at once).
     */
    public function studyDocumentKindFor(string $contentType, $chapterId): ?DocumentKind
    {
        if (!(bool) config('claude.study_documents', true) || !$this->handles($chapterId)) {
            return null;
        }

        return DocumentKind::fromCategory($contentType);
    }

    /** Which study document, if any, is this content_master file name? (Its name is its identity: no extra column.) */
    public static function studyDocumentKind(?string $filename): ?DocumentKind
    {
        return DocumentKind::fromFilename($filename);
    }

    public static function isStudyDocumentFilename(?string $filename): bool
    {
        return DocumentKind::fromFilename($filename) !== null;
    }

    /** A study document's PDF is its primary file: the row's own file name. */
    public static function studyDocumentPdfPath(string $filename): string
    {
        return 'public/lms_content_file/' . $filename;
    }

    /** Where a study document's structured source lives: beside its PDF, named after it. */
    public static function studyDocumentSidecarPath(string $filename): string
    {
        return 'public/lms_content_file/' . $filename . '.doc.json';
    }

    /**
     * The PDF's canonical URL for a content_master row, only when the row is a study document of the category its
     * name says and the file is really stored. Never built from anything the caller sent.
     *
     * @param array<string,mixed> $row a content_master row
     */
    public static function studyDocumentPdfUrl(array $row): ?string
    {
        $kind = DocumentKind::fromFilename($row['filename'] ?? null);
        if ($kind === null || DocumentKind::fromCategory($row['content_category'] ?? null) !== $kind) {
            return null;
        }
        try {
            $disk = Storage::disk('digitalocean');
            $path = self::studyDocumentPdfPath((string) $row['filename']);

            return $disk->exists($path) ? $disk->url($path) : null;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Write a study document for a chapter from its Chapter -> Topic -> Concept -> Concept Intelligence data, its
     * prerequisite graph and its question bank, and store it as an ordinary content_master row.
     *
     * $input takes the same chapter/tenant fields as generateStudyDeck(), plus optional `concept_ids` to limit the
     * document to some of the chapter's concepts. Nothing is stored unless validation passes.
     *
     * @return array{http:int, body:array<string,mixed>}
     */
    public function generateStudyDocument(DocumentKind $kind, array $input, ?Completer $completer = null): array
    {
        @set_time_limit((int) config('claude.timeout_seconds', 600) * 4 + 120);
        // A long chapter takes many model calls, longer than a browser or a proxy will wait. If the person who asked gives up
        // waiting, the document is still written and stored (it then shows up in the list), instead of the work being lost.
        ignore_user_abort(true);

        $completer ??= $this->studyCompleter();
        if ($completer === null) {
            return $this->fail('Claude API key is not configured. Set ANTHROPIC_API_KEY or an ai_api_keys row.', 500);
        }

        $tenant = (int) ($input['sub_institute_id'] ?? 1);
        $chapterId = (int) $input['chapter']->id;
        $name = strtolower($kind->label());

        try {
            $result = $this->studyDocumentService($completer, $tenant, $chapterId)
                ->generate($kind, $chapterId, $tenant, ['concept_ids' => (array) ($input['concept_ids'] ?? [])]);
        } catch (APIStatusException $e) {
            return $this->fail($this->readableApiError($e), 502);
        } catch (\InvalidArgumentException $e) {
            return $this->fail($e->getMessage(), 422);
        } catch (Throwable $e) {
            Log::error('Study document generation failed', ['kind' => $kind->value, 'chapter_id' => $chapterId, 'error' => $e->getMessage()]);

            return $this->fail('The ' . $name . ' could not be written: ' . $e->getMessage(), 500);
        }

        if (!$result['report']['ok']) {
            return [
                'http' => 422,
                'body' => [
                    'success' => false,
                    'status_code' => 0,
                    'message' => 'The ' . $name . ' failed validation and was not stored.',
                    'errors' => $result['report']['errors'],
                    'warnings' => $result['report']['warnings'],
                ],
            ];
        }

        return $this->publishStudyDocument($kind, $input, $result['document'], $result['html'], false, (string) config('claude.model', 'claude-opus-5'));
    }

    /** The pipeline, built for one school and chapter: its diagrams are stored in the database as they are drawn. */
    protected function studyDocumentService(Completer $completer, int $tenant, int $chapterId): StudyDocumentService
    {
        return StudyDocumentService::make($completer, StudyImageStores::database($tenant, $chapterId));
    }

    /**
     * The model to ask: the dev-only CLI when `claude.executor=cli`, otherwise the Anthropic API with the key this
     * class resolves. Null when there is no key (the CLI is never chosen implicitly).
     */
    protected function studyCompleter(): ?Completer
    {
        if (config('claude.executor', 'api') === 'cli') {
            return new ClaudeCliCompleter();
        }
        $apiKey = $this->resolveApiKey();
        if ($apiKey === '') {
            return null;
        }
        $model = (string) config('claude.model', 'claude-opus-5');

        return new ClaudeApiCompleter(fn (string $system, string $prompt, int $max): array => $this->callClaude($apiKey, $model, $prompt, $system, $max));
    }

    /**
     * Publish a validated study document: its diagrams confirmed in the database, one content_master row, one PDF,
     * one structured-source file.
     *
     * Everything that can fail is done BEFORE a row exists (the PDF drawn and checked, the source file stored and
     * read back), and a failure removes what this run stored, so no row points at a missing file and no file is left
     * without a row. Safe to repeat: a document is identified by the hash of its content (part of the file name), so
     * storing the same document again finds its row and changes nothing; a changed one is a new row, and the
     * documents of the same kind and scope that it replaces are hidden in the same transaction.
     *
     * @param array<string,mixed> $input chapter, chapter_name, grade_id, sub_institute_id, syear, created_by, user_profile_name
     * @param array<string,mixed> $document the assembled study document
     * @return array{http:int, body:array<string,mixed>}
     */
    public function publishStudyDocument(DocumentKind $kind, array $input, array $document, string $html, bool $dryRun = false, string $authoredBy = 'study-document'): array
    {
        $tenant = (int) ($input['sub_institute_id'] ?? 1);

        return $this->withStudyDeckImages($tenant, fn () => $this->publishStudyDocumentNow($kind, $input, $document, $html, $dryRun, $authoredBy));
    }

    private function publishStudyDocumentNow(DocumentKind $kind, array $input, array $document, string $html, bool $dryRun, string $authoredBy): array
    {
        $tenant = (int) ($input['sub_institute_id'] ?? 1);
        $name = strtolower($kind->label());
        $publisher = $this->studyDeckPublisher($tenant);

        try {
            $prepared = $publisher->prepare($document, $html, null, !$dryRun);
        } catch (Throwable $e) {
            $publisher->cleanup();

            return $this->fail('The ' . $name . ' was not stored: ' . $e->getMessage(), 422);
        }
        if ($prepared['missing'] !== []) {
            $publisher->cleanup();

            return $this->fail('The ' . $name . ' refers to pictures that are not stored for this school: ' . implode(', ', $prepared['missing']), 422);
        }

        $chapter = $input['chapter'];
        $doc = $prepared['deck'];
        $filename = $kind->filenameFor((string) $input['chapter_name'], $doc);
        $sidecar = self::studyDocumentSidecarPath($filename);
        $pdfPath = self::studyDocumentPdfPath($filename);
        $disk = Storage::disk('digitalocean');
        $existing = $this->findStudyDocumentRow((int) $chapter->id, $tenant, $filename);

        $summary = [
            'kind' => $kind->value,
            'filename' => $filename,
            'document_metadata_path' => $sidecar,
            'pdf_path' => $pdfPath,
            'section_count' => count($doc['sections'] ?? []),
            'images' => array_values($prepared['assets']),
            'images_uploaded' => $prepared['uploaded'],
            'images_reused' => $prepared['reused'],
        ];

        if ($dryRun) {
            return ['http' => 200, 'body' => ['success' => true, 'status_code' => 1, 'status' => $existing ? 'unchanged' : 'would_create', 'dry_run' => true, 'content_id' => $existing->id ?? null] + $summary];
        }

        // The same document, already stored: make sure its files are all there and change nothing else.
        if ($existing) {
            $repaired = false;
            try {
                if (!$disk->exists($sidecar)) {
                    $disk->put($sidecar, $this->studyDeckJson($doc), 'public');
                    $repaired = $disk->exists($sidecar);
                }
                if ($this->linkStudyDeckImages((int) $existing->id, $doc, (int) $chapter->id) > 0) {
                    $repaired = true;
                }
                // `refresh_pdf` replaces a PDF made by an older layout. The new one is drawn completely BEFORE the
                // stored one is touched, and the object is replaced in a single put, so a failure leaves the old PDF.
                if (!$disk->exists($pdfPath) || !empty($input['refresh_pdf'])) {
                    $pdf = $this->renderStudyDocumentPdf($doc, ['tenant' => $tenant]);
                    if ($pdf === '' || !str_starts_with($pdf, '%PDF-')) {
                        throw new \RuntimeException('the PDF came out empty');
                    }
                    $disk->put($pdfPath, $pdf, 'public');
                    if (!$disk->exists($pdfPath) || (int) $disk->size($pdfPath) !== strlen($pdf)) {
                        throw new \RuntimeException('the PDF was not stored correctly');
                    }
                    $repaired = true;
                }
                $restored = (int) $existing->show_hide === 0
                    ? $this->restoreStudyDocumentRow($kind, (int) $existing->id, (int) $chapter->id, $tenant, (string) $input['chapter_name'], DocumentKind::scopeToken($doc))
                    : false;
            } catch (Throwable $e) {
                $publisher->cleanup();

                return $this->fail('The ' . $name . ' exists but could not be verified: ' . $e->getMessage(), 500);
            }

            return ['http' => 200, 'body' => ['success' => true, 'status_code' => 1, 'status' => $repaired || $restored ? 'repaired' : 'unchanged', 'content_id' => (int) $existing->id, 'file_url' => $existing->url] + $summary];
        }

        $input['filename'] = $filename;
        $result = $this->storeStudyDocument($kind, $input, $prepared['html'], $doc, $authoredBy);
        if (($result['http'] ?? 0) !== 201) {
            $publisher->cleanup();

            return $result;
        }

        $result['body']['data'] += $summary + ['status' => 'created'];
        $result['body']['status'] = 'created';
        $result['body']['content_id'] = (int) $result['body']['data']['id'];

        return $result;
    }

    /**
     * Persist an already validated, already published (see publishStudyDocument) study document.
     *
     * The PDF is the row's primary file. The structured source the online practice and the PDF are drawn from has no
     * content_master column, so it is a JSON file next to the PDF, named after it. `meta_tags` is deliberately left
     * empty (the concept tagger and the video relevance scorer read that column as text), and `description` holds the
     * design-system markup, so the document is readable straight out of the database.
     *
     * @param array<string,mixed> $input
     * @param array<string,mixed> $document a prepared document (pictures are stored references)
     * @return array{http:int, body:array<string,mixed>}
     */
    public function storeStudyDocument(DocumentKind $kind, array $input, string $html, array $document, string $authoredBy = 'study-document'): array
    {
        $chapterId = (int) $input['chapter']->id;
        $tenant = (int) ($input['sub_institute_id'] ?? 1);
        $name = strtolower($kind->label());
        $chapterName = (string) $input['chapter_name'];

        $input['is_presentation'] = false;
        $input['content_type'] = $kind->category();
        $input['meta_tags'] = null;
        $input['title'] = $input['title'] ?? $kind->title($chapterName);
        $input['tenant'] = $tenant;
        // A document for exactly one concept is filed under it; a document for the chapter, or for several, under none.
        $only = array_map('intval', (array) ($document['scope']['concept_ids'] ?? []));
        $input['concept_id'] = !empty($document['scope']['all']) || count($only) !== 1 ? null : $only[0];

        // The PDF is made before anything is stored, so one that cannot be drawn stops the whole publish.
        try {
            $pdf = $this->renderStudyDocumentPdf($document, ['tenant' => $tenant]);
        } catch (Throwable $e) {
            return $this->fail('The ' . $name . ' was not stored: its PDF could not be made: ' . $e->getMessage(), 500);
        }
        if ($pdf === '') {
            return $this->fail('The ' . $name . ' was not stored: its PDF came out empty.', 500);
        }
        $input['binary'] = $pdf;
        $input['extra_files'] = [self::studyDocumentSidecarPath((string) $input['filename']) => $this->studyDeckJson($document)];

        $scope = DocumentKind::scopeToken($document);
        // Inside the transaction that inserts the row: the document, the record of which pictures it uses and the
        // hiding of what it replaces land together or not at all.
        $input['in_transaction'] = function (int $id) use ($kind, $chapterId, $tenant, $chapterName, $scope, $document): void {
            $this->hidePreviousStudyDocuments($kind, $chapterId, $tenant, $chapterName, $scope, $id);
            $this->linkStudyDeckImages($id, $document, $chapterId);
        };

        try {
            $result = $this->withStudyDeckImages($tenant, fn () => $this->storeAuthoredContent($input, $html, $authoredBy));
        } catch (Throwable $e) {
            Log::error('Study document storage failed', ['kind' => $kind->value, 'chapter_id' => $chapterId, 'error' => $e->getMessage()]);

            return $this->fail('The ' . $name . ' was not stored: ' . $e->getMessage(), 500);
        }

        if (($result['http'] ?? 0) === 201) {
            $disk = Storage::disk('digitalocean');
            $result['body']['data']['document_metadata_url'] = $disk->url(self::studyDocumentSidecarPath((string) $input['filename']));
            $result['body']['data']['pdf_path'] = self::studyDocumentPdfPath((string) $input['filename']);
            $result['body']['data']['pdf_url'] = $disk->url(self::studyDocumentPdfPath((string) $input['filename']));
            $result['body']['data']['section_count'] = count($document['sections'] ?? []);
        }

        return $result;
    }

    /**
     * A study document's PDF, drawn from its stored source by the study deck's own Dompdf pipeline and print stylesheet.
     * The default (teacher / answers shown) copy is stored; the other copy is only ever drawn on request.
     *
     * @param array<string,mixed> $document
     * @param array{variant?:string, tenant?:int} $options `tenant` is the school that owns the document
     */
    protected function renderStudyDocumentPdf(array $document, array $options = []): string
    {
        $tenant = (int) ($options['tenant'] ?? $this->deckImageTenant ?? 1);

        return $this->withStudyDeckImages($tenant, fn () => $this->drawStudyDocumentPdf($document, $options));
    }

    private function drawStudyDocumentPdf(array $document, array $options): string
    {
        $renderer = new StudyDocumentPdfRenderer($this->deckImageResolver, base_path('vendor/dompdf/dompdf/lib/fonts/DejaVuSans-Bold.ttf'));
        $html = $renderer->html($document, $this->generatedContentCss(), [
            'variant' => $options['variant'] ?? StudyDocumentPdfRenderer::TEACHER,
            'questions' => $this->loadStudyDeckQuestions(StudyDeckQuestions::idsIn($document)),
        ]);

        return $this->renderHtmlToPdf($html, $this->studyPageFurniture(
            StudyDocumentPdfRenderer::runningTitle($document),
            StudyDocumentPdfRenderer::runningSubject($document),
            StudyDocumentPdfRenderer::footerLabel($document)
        ));
    }

    /**
     * The PDF bytes for a stored study document, drawn now: the stored copy (teacher edition / answers shown) or the
     * other copy (student handout / answers hidden), which is not kept anywhere.
     *
     * @param array<string,mixed> $document
     * @param array{variant?:string, tenant?:int} $options
     */
    public function studyDocumentPdfBytes(array $document, array $options = []): string
    {
        return $this->renderStudyDocumentPdf($document, $options);
    }

    /** The row for this exact document on this chapter and school, visible or hidden. */
    protected function findStudyDocumentRow(int $chapterId, int $tenant, string $filename): ?object
    {
        return $this->findStudyDeckRow($chapterId, $tenant, $filename);
    }

    /** Hide the other documents of this kind and scope for this chapter and school: the new one replaces them. */
    protected function hidePreviousStudyDocuments(DocumentKind $kind, int $chapterId, int $tenant, string $chapterName, string $scope, int $keepId): int
    {
        return DB::table('content_master')
            ->where('chapter_id', $chapterId)
            ->where('sub_institute_id', $tenant)
            ->where('file_type', 'pdf')
            ->where('content_category', $kind->category())
            ->where('filename', 'like', $kind->replaceablePattern($chapterName, $scope))
            ->where('id', '<>', $keepId)
            ->where(fn ($q) => $q->whereNull('show_hide')->orWhere('show_hide', '<>', 0))
            ->update(['show_hide' => 0]);
    }

    /** Make an older, hidden study document the current one again (and hide the rest of its kind and scope). */
    protected function restoreStudyDocumentRow(DocumentKind $kind, int $id, int $chapterId, int $tenant, string $chapterName, string $scope): bool
    {
        return (bool) DB::transaction(function () use ($kind, $id, $chapterId, $tenant, $chapterName, $scope) {
            DB::table('content_master')->where('id', $id)->update(['show_hide' => 1]);
            $this->hidePreviousStudyDocuments($kind, $chapterId, $tenant, $chapterName, $scope, $id);

            return true;
        });
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
    protected function callClaude(string $apiKey, string $model, string $prompt, ?string $system = null, ?int $maxTokens = null): array
    {
        $client = new AnthropicClient(apiKey: $apiKey);

        $stream = $client->messages->createStream(
            maxTokens: $maxTokens ?? (int) config('claude.max_output_tokens', 32000),
            messages: [['role' => 'user', 'content' => $prompt]],
            model: $model,
            outputConfig: ['effort' => config('claude.effort', 'high')],
            system: $system ?? self::OUTPUT_FORMAT_SYSTEM,
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

        $isPresentation = (bool) ($input['is_presentation'] ?? false);
        // A caller that has already drawn the file itself (a study document's PDF is drawn from its structured source,
        // not from this markup) hands it over; every other caller has it rendered from the markup, as always.
        $binary = isset($input['binary']) && is_string($input['binary']) && $input['binary'] !== ''
            ? $input['binary']
            : $this->resolvePresentationRenderer($isPresentation)($generated['text'], $chapterName, $contentType);
        // A presentation is now a real .pptx, so the extension and the stored
        // file_type have to follow the renderer rather than assuming PDF - the
        // content library uses file_type to decide how to offer the file.
        $extension = $this->presentationFileType($isPresentation);

        $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '_', $contentType . '_' . $chapterName));
        // A caller that needs a stable name (so a repeat finds the same row) supplies it.
        $fileName = $input['filename'] ?? (trim($slug, '_') . '_' . time() . '.' . $extension);
        $spacesPath = 'public/lms_content_file/' . $fileName;

        // Everything is stored and read back BEFORE the row exists; if any step fails what this call stored is
        // removed, so a row never points at a missing file and a file is never left without its row.
        $disk = Storage::disk('digitalocean');
        $stored = [];
        try {
            $disk->put($spacesPath, $binary, 'public');
            $stored[] = $spacesPath;
            foreach ((array) ($input['extra_files'] ?? []) as $extraPath => $extraBytes) {
                $disk->put($extraPath, $extraBytes, 'public');
                $stored[] = $extraPath;
                if (!$disk->exists($extraPath)) {
                    throw new \RuntimeException('Could not store ' . $extraPath);
                }
            }
            if (!$disk->exists($spacesPath)) {
                throw new \RuntimeException('Could not store ' . $spacesPath);
            }
        } catch (Throwable $e) {
            $this->forget($stored);

            throw $e;
        }
        $fileUrl = $disk->url($spacesPath);

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
            'title' => mb_substr((string) ($input['title'] ?? ($chapterName . ' ' . $contentType)), 0, 250),
            // The generated document itself. The Gamma/Gemini branches store the
            // prompt here instead; this is the column that makes the content
            // readable straight out of the database.
            'description' => $html,
            'file_folder' => '/lms_content_file',
            'filename' => $fileName,
            'url' => $fileUrl,
            'file_type' => $extension,
            'file_size' => strlen($binary) ?: null,
            'show_hide' => '1',
            'sort_order' => null,
            'meta_tags' => $input['meta_tags'] ?? null,
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

        try {
            $lastId = $this->insertContentRow($content, $input['in_transaction'] ?? null);
        } catch (Throwable $e) {
            $this->forget($stored);

            throw $e;
        }

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
                    'file_type' => $extension,
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
     * Insert the content_master row, and anything that must change with it, in one transaction.
     *
     * @param (callable(int):mixed)|null $inTransaction runs with the new id, inside the transaction
     */
    protected function insertContentRow(array $content, ?callable $inTransaction = null): int|string
    {
        return DB::transaction(function () use ($content, $inTransaction) {
            contentModel::insert($content);
            $id = DB::getPDO()->lastInsertId();
            if ($inTransaction) {
                $inTransaction((int) $id);
            }

            return $id;
        });
    }

    /** Remove objects this call stored when a later step failed. Best effort: nothing points at them. */
    private function forget(array $paths): void
    {
        foreach ($paths as $path) {
            try {
                Storage::disk('digitalocean')->delete($path);
            } catch (Throwable) {
            }
        }
    }

    /**
     * Pick the renderer for this content type.
     *
     * A presentation becomes a real, editable .pptx so a teacher can open it in
     * PowerPoint, drop a slide or add their own example. Everything else stays
     * on the HTML -> PDF path, which is the right output for a document.
     *
     * The earlier note here proposed a Claude Agent Skills call with
     * python-pptx. That turned out to be unnecessary: phpoffice/phppresentation
     * was already a dependency with zero usages, so the deck is built in-process
     * with no sandbox, no API key and no extra package. See
     * RendersContentPresentation.
     *
     * @return callable(string,string,string):string
     */
    protected function resolvePresentationRenderer(bool $isPresentation): callable
    {
        if ($isPresentation) {
            return fn (string $body, string $chapterName, string $contentType): string
                => $this->renderContentPresentationPptx($body, $chapterName, $contentType);
        }

        return fn (string $body, string $chapterName, string $contentType): string
            => $this->renderGeneratedContentPdf($body, $chapterName, $contentType);
    }

    /** File extension and content_master.file_type for a rendered artefact. */
    protected function presentationFileType(bool $isPresentation): string
    {
        return $isPresentation ? 'pptx' : 'pdf';
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
