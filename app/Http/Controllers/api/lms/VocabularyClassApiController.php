<?php

namespace App\Http\Controllers\api\lms;

use App\Http\Controllers\Controller;
use App\Services\lms\Content\VocabularyClassDeck;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * Reads the Vocabulary Class deck a chapter's Classroom Resources list opens (read-only; nothing is generated here).
 *
 * The deck is the JSON document in one content_master row (see VocabularyClassDeck). Tenant scoping mirrors
 * `lms-chapter-content` and `lms-study-deck`: a school with is_Lms = 'Y' sees the platform library (tenant 1) as well as
 * its own rows. The row must be a deck of the chapter's own standard and subject, and its topics must name the chapter,
 * so changing an id by hand reaches nothing else.
 */
class VocabularyClassApiController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), ['chapter_id' => 'required|integer|min:1', 'content_id' => 'nullable|integer|min:1']);
        if ($validator->fails()) {
            return response()->json(['status_code' => 0, 'message' => 'Validation failed.', 'errors' => $validator->errors()->messages()], 422);
        }

        $chapterId = (int) $request->input('chapter_id');
        $tenant = $request->input('sub_institute_id') ?? $request->session()->get('sub_institute_id');

        $chapter = DB::table('chapter_master')->where('id', $chapterId)
            ->when($tenant, fn ($q) => $q->where(fn ($w) => $w->where('sub_institute_id', $tenant)->orWhere('sub_institute_id', 1)))
            ->first(['id', 'sub_institute_id', 'standard_id', 'subject_id']);
        if (!$chapter) {
            return response()->json(['status_code' => 0, 'message' => 'Chapter not found.'], 404);
        }
        $tenant = $tenant ?: $chapter->sub_institute_id;
        $isLms = DB::table('school_setup')->where('Id', $tenant)->value('is_Lms');

        $rows = DB::table('content_master')
            ->where('content_category', VocabularyClassDeck::CATEGORY)
            ->where('filename', 'like', VocabularyClassDeck::FILE_PREFIX . '%')
            ->where('standard_id', $chapter->standard_id)
            ->where('subject_id', $chapter->subject_id)
            ->where(fn ($q) => $q->whereNull('show_hide')->orWhere('show_hide', '<>', 0))
            ->where(fn ($q) => $isLms === 'Y'
                ? $q->where('sub_institute_id', 1)->orWhere('sub_institute_id', $tenant)
                : $q->where('sub_institute_id', $tenant))
            ->when((int) $request->input('content_id', 0) > 0, fn ($q) => $q->where('id', (int) $request->input('content_id')))
            ->orderByDesc('id')
            ->get();

        $sawUnreadable = false;
        foreach ($rows as $row) {
            if (!VocabularyClassDeck::isVocabularyFilename($row->filename)) {
                continue;
            }
            $deck = VocabularyClassDeck::decode($row->description);
            if ($deck === null) {
                $sawUnreadable = true;
                continue;
            }
            if (!in_array($chapterId, VocabularyClassDeck::chapterIds($deck), true)) {
                continue;
            }

            return response()->json(['status_code' => 1, 'content_id' => (int) $row->id, 'chapter_id' => $chapterId, 'data' => VocabularyClassDeck::hydrate($deck, (int) $tenant)]);
        }

        // Nothing is generated on open: a chapter with no stored deck says so.
        return $sawUnreadable
            ? response()->json(['status_code' => 0, 'message' => 'The stored vocabulary class could not be read.'], 502)
            : response()->json(['status_code' => 0, 'message' => 'There is no vocabulary class stored for this chapter yet.'], 404);
    }
}
