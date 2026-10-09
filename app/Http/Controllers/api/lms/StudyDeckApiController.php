<?php

namespace App\Http\Controllers\api\lms;

use App\Http\Controllers\Controller;
use App\Services\ContentGenerationService;
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
 */
class StudyDeckApiController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        [$row, $error] = $this->scopedRow($request);
        if ($error) {
            return $error;
        }

        try {
            $deck = json_decode((string) Storage::disk('digitalocean')->get(ContentGenerationService::studyDeckSidecarPath($row->filename)), true);
        } catch (Throwable $e) {
            $deck = null;
        }
        if (!is_array($deck) || ($deck['version'] ?? 0) < 3) {
            return response()->json(['status_code' => 0, 'message' => 'The stored study deck could not be read.'], 502);
        }

        return response()->json(['status_code' => 1, 'content_id' => (int) $row->id, 'data' => $deck]);
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

        $disk = Storage::disk('digitalocean');
        $path = ContentGenerationService::studyDeckPdfPath($row->filename);
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
            'Content-Disposition' => 'attachment; filename="' . $name . '"',
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

        // A content item the learner chose is exactly that deck, never "the latest one".
        $contentId = (int) $request->input('content_id', 0);
        $row = $contentId > 0
            ? $visible()->where('id', $contentId)->first(['id', 'filename'])
            : $visible()->orderByDesc('id')->get(['id', 'filename'])->first(fn ($r) => $this->hasDeck((string) $r->filename));

        if (!$row) {
            return [null, response()->json(['status_code' => 0, 'message' => 'No interactive study deck has been stored for this chapter.'], 404)];
        }

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
