<?php

namespace App\Http\Controllers\api\lms;

use App\Http\Controllers\Controller;
use App\Services\lms\Content\ClassroomActivityDocument;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * Reads the interactive Classroom Activity a chapter's Classroom Resources list opens (read-only; nothing is generated
 * here and nothing a learner types ever arrives here).
 *
 * The activity is the JSON document in one content_master row (see ClassroomActivityDocument). Tenant scoping mirrors
 * `lms-vocabulary-class`: a school with is_Lms = 'Y' sees the platform library (tenant 1) as well as its own rows. The row
 * must be an activity of the chapter's own standard, subject AND chapter, so changing an id by hand reaches nothing else.
 */
class ClassroomActivityApiController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), ['chapter_id' => 'required|integer|min:1', 'content_id' => 'required|integer|min:1']);
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

        $row = DB::table('content_master')
            ->where('id', (int) $request->input('content_id'))
            ->where('content_category', ClassroomActivityDocument::CATEGORY)
            ->where('filename', 'like', ClassroomActivityDocument::FILE_PREFIX . '%')
            ->where('chapter_id', $chapter->id)
            ->where('standard_id', $chapter->standard_id)
            ->where('subject_id', $chapter->subject_id)
            ->where(fn ($q) => $q->whereNull('show_hide')->orWhere('show_hide', '<>', 0))
            ->where(fn ($q) => $isLms === 'Y'
                ? $q->where('sub_institute_id', 1)->orWhere('sub_institute_id', $tenant)
                : $q->where('sub_institute_id', $tenant))
            ->first();

        // LIKE's `_` matches any character, so the name is confirmed here too.
        if (!$row || !ClassroomActivityDocument::isActivityFilename($row->filename)) {
            return response()->json(['status_code' => 0, 'message' => 'There is no interactive activity stored for this chapter with that id.'], 404);
        }
        $doc = ClassroomActivityDocument::decode($row->description);
        if ($doc === null) {
            return response()->json(['status_code' => 0, 'message' => 'The stored activity could not be read.'], 502);
        }

        return response()->json(['status_code' => 1, 'content_id' => (int) $row->id, 'chapter_id' => $chapterId, 'data' => $doc]);
    }
}
