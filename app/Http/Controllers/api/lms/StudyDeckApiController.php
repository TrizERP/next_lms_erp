<?php

namespace App\Http\Controllers\api\lms;

use App\Http\Controllers\Controller;
use App\Services\ContentGenerationService;
use App\Services\StudyDeck\Documents\DocumentKind;
use App\Services\StudyDeck\StudyDeckImageUrls;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Throwable;

/**
 * Reads the interactive deck behind a chapter's study-deck presentation.
 *
 * It adds no storage: the deck is the JSON sidecar that ContentGenerationService writes
 * beside the presentation file, found from the content_master row's own filename. The
 * questions are NOT in it - the deck carries question ids and the player reads the rows
 * from the existing question bank, so a question edited in the bank changes in the deck.
 *
 * Tenant scoping mirrors `lms-chapter-content` (ApiLmsCourseController): a school with
 * is_Lms = 'Y' sees the platform library (tenant 1) as well as its own rows.
 *
 * The same two reads also serve a STUDY DOCUMENT (revision notes, remedial class, classroom activities) when the request
 * names its content item: its structured source (`.doc.json`) and its PDF, with the same school rule. A document is only
 * ever found by its content id, and only when the row's file name and category agree on which kind it is.
 */
class StudyDeckApiController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        [$row, $error] = $this->scopedRow($request);
        if ($error) {
            return $error;
        }

        if ($row->kind !== null) {
            return $this->document($row);
        }

        try {
            $deck = json_decode((string) Storage::disk('digitalocean')->get(ContentGenerationService::studyDeckSidecarPath($row->filename)), true);
        } catch (Throwable $e) {
            $deck = null;
        }
        if (!is_array($deck) || ($deck['version'] ?? 0) < 3) {
            return response()->json(['status_code' => 0, 'message' => 'The stored study deck could not be read.'], 502);
        }

        // The stored deck names its pictures by reference; the browser gets addresses it can load, issued to the
        // school that passed the check above. A deck stored before pictures moved to the database already has
        // absolute addresses, and those are left as they are.
        $deck = (new StudyDeckImageUrls())->hydrate($deck, (int) $row->tenant);

        return response()->json(['status_code' => 1, 'content_id' => (int) $row->id, 'data' => $deck]);
    }

    /**
     * The structured source of a study document, its pictures given addresses the browser can load (as for a deck).
     */
    private function document(object $row): JsonResponse
    {
        $document = $this->readDocument($row);
        if ($document === null) {
            return response()->json(['status_code' => 0, 'message' => 'The stored document could not be read.'], 502);
        }

        $document = (new StudyDeckImageUrls())->hydrate($document, (int) $row->tenant);

        return response()->json(['status_code' => 1, 'content_id' => (int) $row->id, 'kind' => $row->kind, 'data' => $document]);
    }

    /** The stored document, or null when it is missing, unreadable or not the kind its row says. @return array<string,mixed>|null */
    private function readDocument(object $row): ?array
    {
        try {
            $document = json_decode((string) Storage::disk('digitalocean')->get(ContentGenerationService::studyDocumentSidecarPath($row->filename)), true);
        } catch (Throwable) {
            return null;
        }

        return is_array($document) && ($document['version'] ?? 0) === DocumentKind::VERSION && ($document['kind'] ?? '') === $row->kind ? $document : null;
    }

    /**
     * Addresses for the pictures of a deck the player holds without the server having read it (the local review copy):
     * the same signed addresses `show` puts in a stored deck, for the pictures this school may see.
     *
     * Requires the caller's token (config/api_guard.php), whose school must be the one named here.
     */
    public function imageUrls(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'sub_institute_id' => 'required|integer|min:1',
            'image_ids' => 'required|array|min:1|max:200',
            'image_ids.*' => 'integer|min:1',
        ]);
        if ($validator->fails()) {
            return response()->json(['status_code' => 0, 'message' => 'Validation failed.', 'errors' => $validator->errors()->messages()], 422);
        }

        $urls = (new StudyDeckImageUrls())->forIds(array_map('intval', (array) $request->input('image_ids')), (int) $request->input('sub_institute_id'));

        return response()->json(['status_code' => 1, 'data' => (object) $urls]);
    }

    /**
     * The classroom PDF of one study deck, as a download.
     *
     * The file is found from the content row (its own file name), never from anything the caller sends, and the row
     * must belong to the chapter and school named in the request, so changing an id by hand reaches nothing else.
     */
    public function pdf(Request $request)
    {
        if ((int) $request->input('content_id', 0) < 1) {
            return response()->json(['status_code' => 0, 'message' => 'Validation failed.', 'errors' => ['content_id' => ['The content id is required.']]], 422);
        }
        [$row, $error] = $this->scopedRow($request);
        if ($error) {
            return $error;
        }

        // `inline` is for a viewer that shows the PDF where the learner already is; the default is a download.
        $disposition = $request->input('disposition') === 'inline' ? 'inline' : 'attachment';
        if ($row->kind !== null) {
            return $this->documentPdf($request, $row, $disposition);
        }

        $disk = Storage::disk('digitalocean');
        $path = ContentGenerationService::studyDeckPdfPath($row->filename);

        // The practice copy (no answers) is drawn on request from the stored deck and is not kept anywhere.
        if ($request->input('variant') === 'practice') {
            try {
                $deck = json_decode((string) $disk->get(ContentGenerationService::studyDeckSidecarPath($row->filename)), true);
                if (!is_array($deck) || ($deck['version'] ?? 0) < 3) {
                    return response()->json(['status_code' => 0, 'message' => 'The stored study deck could not be read.'], 502);
                }
                // Drawn from pictures in the database, as the school that owns the deck.
                $bytes = app(ContentGenerationService::class)->studyDeckPdfBytes($deck, ['variant' => 'practice', 'content_id' => (int) $row->id, 'tenant' => (int) $row->owner]);
            } catch (Throwable $e) {
                return response()->json(['status_code' => 0, 'message' => 'The practice copy could not be made.'], 502);
            }

            return response($bytes, 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => $disposition . '; filename="' . preg_replace('/\.pdf$/', '', basename($path)) . '-practice.pdf"',
                'Content-Length' => (string) strlen($bytes),
                'Cache-Control' => 'private, max-age=0, must-revalidate',
                'X-Content-Type-Options' => 'nosniff',
            ]);
        }

        try {
            if (!$disk->exists($path)) {
                return response()->json(['status_code' => 0, 'message' => 'This study deck has no PDF yet.'], 404);
            }
            $bytes = (string) $disk->get($path);
        } catch (Throwable $e) {
            return response()->json(['status_code' => 0, 'message' => 'The PDF could not be read.'], 502);
        }

        $name = preg_replace('/[^A-Za-z0-9._-]+/', '_', basename($path)) ?: 'study-deck.pdf';

        return response($bytes, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => $disposition . '; filename="' . $name . '"',
            'Content-Length' => (string) strlen($bytes),
            'Cache-Control' => 'private, max-age=0, must-revalidate',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * The PDF of a study document. Its stored copy (teacher edition / answers shown) is read from the store; the other
     * copy (student handout / answers hidden) is drawn on request from the stored source and is not kept anywhere.
     */
    private function documentPdf(Request $request, object $row, string $disposition)
    {
        $name = preg_replace('/[^A-Za-z0-9._-]+/', '_', basename(ContentGenerationService::studyDocumentPdfPath($row->filename))) ?: 'study-document.pdf';

        if ($request->input('variant') === 'practice') {
            // The other copy is stored with the document: opening it is a read. Only a document stored before that was kept falls through to drawing.
            try {
                $disk = Storage::disk('digitalocean');
                $kept = ContentGenerationService::studyDocumentPracticePdfPath($row->filename);
                if ($disk->exists($kept)) {
                    return $this->pdfResponse((string) $disk->get($kept), preg_replace('/\.pdf$/', '', $name) . '-practice.pdf', $disposition);
                }
            } catch (Throwable $e) {
                // not readable now: draw it, as before
            }
            $document = $this->readDocument($row);
            if ($document === null) {
                return response()->json(['status_code' => 0, 'message' => 'The stored document could not be read.'], 502);
            }
            try {
                // Drawn from pictures in the database, as the school that owns the document.
                $bytes = app(ContentGenerationService::class)->studyDocumentPdfBytes($document, ['variant' => 'practice', 'tenant' => (int) $row->owner]);
            } catch (Throwable $e) {
                return response()->json(['status_code' => 0, 'message' => 'The other copy could not be made.'], 502);
            }

            return $this->pdfResponse($bytes, preg_replace('/\.pdf$/', '', $name) . '-practice.pdf', $disposition);
        }

        try {
            $disk = Storage::disk('digitalocean');
            $path = ContentGenerationService::studyDocumentPdfPath($row->filename);
            if (!$disk->exists($path)) {
                return response()->json(['status_code' => 0, 'message' => 'This document has no PDF yet.'], 404);
            }
            $bytes = (string) $disk->get($path);
        } catch (Throwable $e) {
            return response()->json(['status_code' => 0, 'message' => 'The PDF could not be read.'], 502);
        }

        return $this->pdfResponse($bytes, $name, $disposition);
    }

    private function pdfResponse(string $bytes, string $name, string $disposition)
    {
        return response($bytes, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => $disposition . '; filename="' . $name . '"',
            'Content-Length' => (string) strlen($bytes),
            'Cache-Control' => 'private, max-age=0, must-revalidate',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * The one study-deck row this request may see: chapter and school as named in the request, the same tenant rule
     * as the Classroom Resource list, and (when a content id is given) exactly that item.
     *
     * @return array{0:?object,1:?JsonResponse} the row, or the error response to send
     */
    private function scopedRow(Request $request): array
    {
        $validator = Validator::make($request->all(), ['chapter_id' => 'required|integer|min:1', 'content_id' => 'nullable|integer|min:1']);
        if ($validator->fails()) {
            return [null, response()->json(['status_code' => 0, 'message' => 'Validation failed.', 'errors' => $validator->errors()->messages()], 422)];
        }

        $chapterId = (int) $request->input('chapter_id');
        $tenant = $request->input('sub_institute_id') ?? $request->session()->get('sub_institute_id');

        $chapter = DB::table('chapter_master')->where('id', $chapterId)
            ->when($tenant, fn ($q) => $q->where(fn ($w) => $w->where('sub_institute_id', $tenant)->orWhere('sub_institute_id', 1)))
            ->first(['id', 'sub_institute_id']);
        if (!$chapter) {
            return [null, response()->json(['status_code' => 0, 'message' => 'Chapter not found.'], 404)];
        }
        $tenant = $tenant ?: $chapter->sub_institute_id;

        $isLms = DB::table('school_setup')->where('Id', $tenant)->value('is_Lms');

        // The rows this school may see for this chapter: the same tenant rule as the Classroom Resource list.
        $visible = fn () => DB::table('content_master')
            ->where('chapter_id', $chapterId)
            ->where('source', config('claude.source_label', 'Claude AI'))
            ->where('content_category', 'Classroom Presentation')
            ->where('file_type', 'pptx')
            ->where('filename', 'like', 'study\_deck\_%')
            ->where(fn ($q) => $q->whereNull('show_hide')->orWhere('show_hide', '<>', 0))
            ->where(fn ($q) => $isLms === 'Y'
                ? $q->where('sub_institute_id', 1)->orWhere('sub_institute_id', $tenant)
                : $q->where('sub_institute_id', $tenant));

        // The rows of this chapter that are study DOCUMENTS (revision notes, remedial class, classroom activities): the same
        // school rule, a PDF as the primary file, and the name the publisher writes. Found only by their content id.
        $documents = fn () => DB::table('content_master')
            ->where('chapter_id', $chapterId)
            ->where('source', config('claude.source_label', 'Claude AI'))
            ->whereIn('content_category', array_map(fn (DocumentKind $k) => $k->category(), DocumentKind::cases()))
            ->where('file_type', 'pdf')
            ->where('filename', 'like', 'study\_%')
            ->where(fn ($q) => $q->whereNull('show_hide')->orWhere('show_hide', '<>', 0))
            ->where(fn ($q) => $isLms === 'Y'
                ? $q->where('sub_institute_id', 1)->orWhere('sub_institute_id', $tenant)
                : $q->where('sub_institute_id', $tenant));

        // A content item the learner chose is exactly that deck, never "the latest one".
        $contentId = (int) $request->input('content_id', 0);
        $row = $contentId > 0
            ? $visible()->where('id', $contentId)->first(['id', 'filename', 'sub_institute_id'])
            : $visible()->orderByDesc('id')->get(['id', 'filename', 'sub_institute_id'])->first(fn ($r) => $this->hasDeck((string) $r->filename));

        $kind = null;
        if (!$row && $contentId > 0) {
            $candidate = $documents()->where('id', $contentId)->first(['id', 'filename', 'sub_institute_id', 'content_category']);
            $kind = $candidate ? DocumentKind::fromFilename((string) $candidate->filename) : null;
            // What the file is called and what the library files it under must say the same thing.
            $row = $kind !== null && DocumentKind::fromCategory((string) $candidate->content_category) === $kind ? $candidate : null;
        }

        if (!$row) {
            return [null, response()->json(['status_code' => 0, 'message' => 'No interactive study deck has been stored for this chapter.'], 404)];
        }

        // `tenant` is the school asking (pictures are issued to it); `owner` is the school the deck belongs to.
        // `kind` is set for a study document and null for a study deck.
        $row->tenant = (int) $tenant;
        $row->owner = (int) $row->sub_institute_id;
        $row->kind = $kind?->value;

        return [$row, null];
    }
    private function hasDeck(string $filename): bool
    {
        if ($filename === '') {
            return false;
        }
        try {
            return Storage::disk('digitalocean')->exists(ContentGenerationService::studyDeckSidecarPath($filename));
        } catch (Throwable) {
            return false;
        }
    }
}
