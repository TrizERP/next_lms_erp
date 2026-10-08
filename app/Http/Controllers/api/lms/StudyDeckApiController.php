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
        $validator = Validator::make($request->all(), ['chapter_id' => 'required|integer|min:1']);
        if ($validator->fails()) {
            return response()->json(['status_code' => 0, 'message' => 'Validation failed.', 'errors' => $validator->errors()->messages()], 422);
        }

        $chapterId = (int) $request->input('chapter_id');
        $tenant = $request->input('sub_institute_id') ?? $request->session()->get('sub_institute_id');

        $chapter = DB::table('chapter_master')->where('id', $chapterId)
            ->when($tenant, fn ($q) => $q->where(fn ($w) => $w->where('sub_institute_id', $tenant)->orWhere('sub_institute_id', 1)))
            ->first(['id', 'sub_institute_id']);
        if (!$chapter) {
            return response()->json(['status_code' => 0, 'message' => 'Chapter not found.'], 404);
        }
        $tenant = $tenant ?: $chapter->sub_institute_id;

        $isLms = DB::table('school_setup')->where('Id', $tenant)->value('is_Lms');

        $row = DB::table('content_master')
            ->where('chapter_id', $chapterId)
            ->where('source', config('claude.source_label', 'Claude AI'))
            ->where('content_category', 'Classroom Presentation')
            ->where('file_type', 'pptx')
            ->where(fn ($q) => $q->whereNull('show_hide')->orWhere('show_hide', '<>', 0))
            ->where(fn ($q) => $isLms === 'Y'
                ? $q->where('sub_institute_id', 1)->orWhere('sub_institute_id', $tenant)
                : $q->where('sub_institute_id', $tenant))
            ->orderByDesc('id')
            ->get(['id', 'filename'])
            ->first(fn ($r) => $this->hasDeck((string) $r->filename));

        if (!$row) {
            return response()->json(['status_code' => 0, 'message' => 'No interactive study deck has been stored for this chapter.'], 404);
        }

        try {
            $deck = json_decode((string) Storage::disk('digitalocean')->get(ContentGenerationService::studyDeckSidecarPath($row->filename)), true);
        } catch (Throwable $e) {
            $deck = null;
        }
        if (!is_array($deck) || ($deck['version'] ?? 0) < 2) {
            return response()->json(['status_code' => 0, 'message' => 'The stored study deck could not be read.'], 502);
        }

        return response()->json(['status_code' => 1, 'content_id' => (int) $row->id, 'data' => $deck]);
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
