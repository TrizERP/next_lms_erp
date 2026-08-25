<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\lms\lmsContentCategoryModel;
use App\Models\lms\contentModel;
use App\Models\lms\contentmappingtypeModel;
use App\Models\lms\lmsQuestionMasterModel;
use App\Models\lms\answermasterModel;
use App\Models\lms\topicModel;
use App\Models\student\tblstudentEnrollmentModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class ApiLmsCourseController extends Controller
{
    private function sessionValue(Request $request, string $key)
    {
        if ($request->hasSession() && $request->session()->has($key)) {
            return $request->session()->get($key);
        }
        return null;
    }

    private function resolveContentUrl(array $contentArray): string
    {
        $url = $contentArray['url'] ?? '';
        if (!empty($url)) {
            return $url;
        }

        $filename = $contentArray['filename'] ?? '';
        if (empty($filename) || preg_match('/^https?:\/\//i', $filename)) {
            return $filename;
        }

        $fileFolder = trim($contentArray['file_folder'] ?? '/lms_content_file', '/');

        return 'https://s3-triz.fra1.cdn.digitaloceanspaces.com/public/' . $fileFolder . '/' . $filename;
    }

    public function index(Request $request): JsonResponse
    {
        $sub_institute_id = $request->input('sub_institute_id') ?? $this->sessionValue($request, 'sub_institute_id');
        $syear = $request->input('syear') ?? $this->sessionValue($request, 'syear');
        $user_profile_name = $request->input('user_profile_name') ?? $this->sessionValue($request, 'user_profile_name');
        $user_id = $request->input('user_id') ?? $this->sessionValue($request, 'user_id');

        $data = $this->getCourseData($sub_institute_id, $syear, $user_profile_name, $user_id);

        return response()->json([
            'status_code' => 1,
            'message' => 'SUCCESS',
            'lms_subject' => $data['mycourse_arr'],
            'content_category' => $data['content_category'],
            'sub_institute_id' => $sub_institute_id,
        ], 200);
    }

    public function search(Request $request): JsonResponse
    {
        $sub_institute_id = $request->input('sub_institute_id') ?? $this->sessionValue($request, 'sub_institute_id');
        $grade = $request->input('grade');
        $standard = $request->input('standard');
        $user_profile_name = $request->input('user_profile_name') ?? $this->sessionValue($request, 'user_profile_name');

        $mycourse_arr = [];
        $extra = "";

        if ($user_profile_name && strtoupper($user_profile_name) == "STUDENT") {
            $student_id = $request->input('user_id') ?? $this->sessionValue($request, 'user_id');
            $stu_data = tblstudentEnrollmentModel::select('standard_id')->where(['student_id' => $student_id])->get()->toArray();
            $extra = " AND 
                find_in_set(
                    s.standard_id,
                    (SELECT concat_ws(',','" . $stu_data[0]['standard_id'] . "',group_concat(id))
                    FROM standard
                    WHERE sub_institute_id IN (" . implode(',', $sub_institute_id) . ") AND grade_id IN (
                    SELECT id
                    FROM academic_section
                    WHERE sub_institute_id IN (" . implode(',', $sub_institute_id) . ") AND title = 'Other'))
                )";
        }

        if ($grade != "") {
            $extra .= " AND STD.grade_id = '" . $grade . "'";
        }
        if ($standard != "") {
            $extra .= " AND STD.id = '" . $standard . "'";
        }

        $arr = DB::select("SELECT STD.name AS standard_name,s.display_name AS subject_name,s.subject_id,STD.id AS standard_id,s.sub_institute_id,
                s.display_image,ifnull(s.subject_category,'My Course') AS content_category
                FROM sub_std_map s
                INNER JOIN standard STD ON STD.id = s.standard_id AND STD.sub_institute_id = s.sub_institute_id
                WHERE s.sub_institute_id in (" . $sub_institute_id . ",1) AND allow_content = 'Yes'
                 " . $extra . " AND s.subject_category!='SEL'
                GROUP BY s.subject_id,s.standard_id,s.subject_category ORDER BY s.sort_order");

        $arr = json_decode(json_encode($arr), true);
        if (count($arr) > 0) {
            foreach ($arr as $key => $val) {
                $val['chapters'] = $this->getChaptersWithContentCategories($val['subject_id'], $val['standard_id'], $sub_institute_id);
                $mycourse_arr[$val['content_category']][] = $val;
            }
        }

        $getSEL = DB::select("SELECT STD.name AS standard_name,s.display_name AS subject_name,s.subject_id,STD.id AS standard_id,s.sub_institute_id,
                s.display_image,ifnull(s.subject_category,'My Course') AS content_category
                FROM sub_std_map s
                INNER JOIN standard STD ON STD.id = s.standard_id AND STD.sub_institute_id = s.sub_institute_id
                WHERE s.sub_institute_id IN (1," . $sub_institute_id . ") AND allow_content = 'Yes'
                 AND s.subject_category='SEL'
                GROUP BY s.subject_id,s.standard_id,s.subject_category ORDER BY s.sort_order");

        $getSEL = json_decode(json_encode($getSEL), true);
        if (count($getSEL) > 0) {
            foreach ($getSEL as $key => $val) {
                $val['chapters'] = $this->getChaptersWithContentCategories($val['subject_id'], $val['standard_id'], $sub_institute_id);
                $mycourse_arr[$val['content_category']][] = $val;
            }
        }

        $content_category = lmsContentCategoryModel::where('status', '1')->get()->toArray();

        return response()->json([
            'status_code' => 1,
            'message' => 'SUCCESS',
            'lms_subject' => $mycourse_arr,
            'content_category' => $content_category,
            'grade' => $grade,
            'standard' => $standard,
            'sub_institute_id' => $sub_institute_id,
        ], 200);
    }

    private function getCourseData($sub_institute_id, $syear, $user_profile_name, $user_id): array
    {
        $mycourse_arr = [];

        $extra = " 1=1 ";
        if (strtoupper($user_profile_name) == "STUDENT") {
            $student_id = $user_id;
            $stu_data = tblstudentEnrollmentModel::select('standard_id')
                ->where(['student_id' => $student_id, 'syear' => $syear, 'sub_institute_id' => $sub_institute_id])
                ->get()
                ->toArray();
            $extra = " AND 
                find_in_set(
                    s.standard_id,
                    (SELECT concat_ws(',','" . $stu_data[0]['standard_id'] . "',group_concat(id))
                    FROM standard
                    WHERE sub_institute_id = '" . $sub_institute_id . "' AND grade_id IN (
                    SELECT id
                    FROM academic_section
                    WHERE sub_institute_id = '" . $sub_institute_id . "' AND title = 'Other')
                    )
                )";
            $extra .= " AND (s.elective_subject != 'Yes' OR s.subject_id IN (SELECT sos.subject_id FROM student_optional_subject sos 
                WHERE sos.sub_institute_id = '" . $sub_institute_id . "' AND sos.syear = '" . $syear . "' AND sos.student_id = '" . $student_id . "'))";
        }

        $getIsLms = DB::table('school_setup')
            ->where('Id', $sub_institute_id)
            ->value('is_Lms');

        $sub_institute_id_by_lms = ($getIsLms == 'Y') ? "(s.sub_institute_id = 1 or s.sub_institute_id = $sub_institute_id)" : "s.sub_institute_id = $sub_institute_id";

        if (in_array($user_profile_name, ['Teacher', 'Lms Teacher'])){
            $arr = DB::table('sub_std_map as s')
                ->selectRaw("STD.name AS standard_name,s.display_name AS subject_name,s.subject_id,STD.id AS standard_id,
                    s.display_image,IFNULL(s.subject_category,'My Course') AS content_category,s.sub_institute_id")
                ->join('standard AS STD', function ($join) {
                    $join->on('STD.id', '=', 's.standard_id')
                         ->on('STD.sub_institute_id', '=', 's.sub_institute_id');
                })
                ->Join('timetable AS t', function ($join) use ($user_id, $syear, $sub_institute_id, $extra) {
                    $join->on('t.standard_id', '=', 's.standard_id')
                        ->on('t.subject_id', '=', 's.subject_id')
                        ->on('t.sub_institute_id', '=', 's.sub_institute_id')
                        ->where('t.teacher_id', '=', $user_id)
                        ->where('t.syear', '=', $syear)
                        ->whereRaw($extra);
                })
                ->where('s.sub_institute_id', '=', $sub_institute_id)
                ->where('s.subject_category', '!=', 'SEL')
                ->groupBy('s.subject_id', 's.standard_id', 's.subject_category')
                ->orderBy('s.sort_order')
                ->get();

            $getSEL = DB::table('sub_std_map as s')
                ->selectRaw("STD.name AS standard_name,s.display_name AS subject_name,s.subject_id,STD.id AS standard_id,
                    s.display_image,IFNULL(s.subject_category,'SEL') AS content_category,s.sub_institute_id")
                ->join('standard AS STD', function ($join) {
                    $join->on('STD.id', '=', 's.standard_id')
                         ->on('STD.sub_institute_id', '=', 's.sub_institute_id');
                })
                ->whereRaw($sub_institute_id_by_lms)
                ->where('s.allow_content', '=', 'Yes')
                ->where('s.subject_category', '=', 'SEL')
                ->groupBy('s.subject_id', 's.standard_id', 's.subject_category')
                ->orderBy('s.sort_order')
                ->get()->toArray();

            if (count($getSEL) > 0) {
                foreach ($getSEL as $key => $val) {
                    $valArray = (array)$val;
                    $valArray['chapters'] = $this->getChaptersWithContentCategories($val->subject_id, $val->standard_id, $sub_institute_id);
                    $mycourse_arr[$valArray['content_category']][] = $valArray;
                }
            }
        } else {
            $whereExtra = trim(ltrim($extra, ' AND '));
            $arr = DB::table('sub_std_map as s')
                ->selectRaw("STD.name AS standard_name,s.display_name AS subject_name,s.subject_id,STD.id AS standard_id,
                    s.display_image,IFNULL(s.subject_category,'My Course') AS content_category,s.sub_institute_id")
                ->join('standard AS STD', function ($join) {
                    $join->on('STD.id', '=', 's.standard_id')
                         ->on('STD.sub_institute_id', '=', 's.sub_institute_id');
                })
                ->whereRaw($sub_institute_id_by_lms)
                ->where('s.allow_content', '=', 'Yes')
                ->whereRaw($whereExtra)
                ->groupBy('s.subject_id', 's.standard_id', 's.subject_category')
                ->orderBy('s.sort_order')
                ->get();
        }

        $arr = $arr->toArray();
        if (count($arr) > 0) {
            foreach ($arr as $key => $val) {
                $valArray = (array)$val;
                $valArray['chapters'] = $this->getChaptersWithContentCategories($val->subject_id, $val->standard_id, $sub_institute_id);
                $mycourse_arr[$valArray['content_category']][] = $valArray;
            }
        }

        $content_category = lmsContentCategoryModel::where('status', '1')
            ->where(function ($query) use ($sub_institute_id) {
                $query->where('sub_institute_id', '=', $sub_institute_id)
                    ->orWhere('sub_institute_id', '=', '0');
            })
            ->get()
            ->toArray();

        return [
            'content_category' => $content_category,
            'mycourse_arr' => $mycourse_arr,
        ];
    }

    private function getChaptersWithContentCategories($subject_id, $standard_id, $sub_institute_id): array
    {
        $hasContentUserProfile = Schema::hasColumn('content_master', 'user_profile_name');

        $chapters = DB::table('chapter_master')
            ->select('chapter_master.id', 'chapter_master.chapter_name', 'chapter_master.chapter_desc', 'chapter_master.sort_order',
                DB::raw('COUNT(content_master.id) as total_content'),
                DB::raw("sum(if(content_category = 'Triz', 1, 0)) AS total_triz_content"),
                DB::raw("sum(if(content_category = 'OER', 1, 0)) AS total_OER_content"))
            ->leftJoin('content_master', function ($join) use ($hasContentUserProfile) {
                $join->on('content_master.chapter_id', '=', 'chapter_master.id');
                if ($hasContentUserProfile) {
                    $join->where(function ($query) {
                        $query->whereNull('content_master.user_profile_name')
                            ->orWhereRaw("LOWER(content_master.user_profile_name) != 'student'");
                    });
                }
            })
            ->where(function ($query) use ($sub_institute_id) {
                $query->where('chapter_master.sub_institute_id', $sub_institute_id)
                    ->orWhere('chapter_master.sub_institute_id', 1);
            })
            ->where('chapter_master.subject_id', $subject_id)
            ->where('chapter_master.standard_id', $standard_id)
            ->groupBy('chapter_master.id')
            ->orderBy('chapter_master.sort_order')
            ->get()
            ->toArray();

        $chapters = array_map(function ($chapter) {
            return (array)$chapter;
        }, $chapters);

        $getIsLms = DB::table('school_setup')
            ->where('Id', $sub_institute_id)
            ->value('is_Lms');

        foreach ($chapters as &$chapter) {
            $content_data = DB::table('content_master')
                ->where(function ($query) use ($getIsLms, $sub_institute_id) {
                    if ($getIsLms == 'Y') {
                        $query->where('content_master.sub_institute_id', '1')
                            ->orWhere('content_master.sub_institute_id', $sub_institute_id);
                    } else {
                        $query->where('content_master.sub_institute_id', $sub_institute_id);
                    }
                })
                ->where('content_master.subject_id', $subject_id)
                ->where('content_master.standard_id', $standard_id)
                ->where('content_master.chapter_id', $chapter['id'])
                ->where(function ($query) {
                    $query->whereNull('content_master.topic_id')
                        ->orWhere('content_master.topic_id', '0');
                })
                ->get()
                ->toArray();

            $content_by_category = [];
            foreach ($content_data as $content) {
                $contentArray = (array)$content;
                $contentArray['url'] = $this->resolveContentUrl($contentArray);
                $cat = $contentArray['content_category'] ?? 'General';
                $content_by_category[$cat][] = $contentArray;
            }

            $chapter['content_categories'] = $content_by_category;

            $flash = DB::table('lms_flashcard')
                ->where(['chapter_id' => $chapter['id'], 'sub_institute_id' => $sub_institute_id, 'status' => 1])
                ->get()
                ->toArray();
            $chapter['content_categories']['Flash Cards'] = array_map(function ($f) {
                return (array)$f;
            }, $flash);

            $chapter['content_categories']['Mindmap'] = [];
            $chapter['content_categories']['Virtual Lab'] = [];
        }

        return $chapters;
    }

    private function getChapterContentCategories($chapter_id, $subject_id, $standard_id, $sub_institute_id): array
    {
        $getIsLms = DB::table('school_setup')
            ->where('Id', $sub_institute_id)
            ->value('is_Lms');

        $content_data = DB::table('content_master')
            ->where(function ($query) use ($getIsLms, $sub_institute_id) {
                if ($getIsLms == 'Y') {
                    $query->where('content_master.sub_institute_id', '1')
                        ->orWhere('content_master.sub_institute_id', $sub_institute_id);
                } else {
                    $query->where('content_master.sub_institute_id', $sub_institute_id);
                }
            })
            ->where('content_master.subject_id', $subject_id)
            ->where('content_master.standard_id', $standard_id)
            ->where('content_master.chapter_id', $chapter_id)
            ->where(function ($query) {
                $query->whereNull('content_master.topic_id')
                    ->orWhere('content_master.topic_id', '0');
            })
            ->get()
            ->toArray();

        $content_by_category = [];
        foreach ($content_data as $content) {
            $contentArray = (array)$content;
            $contentArray['url'] = $this->resolveContentUrl($contentArray);
            $cat = $contentArray['content_category'] ?? 'General';
            $content_by_category[$cat][] = $contentArray;
        }

        $flash = DB::table('lms_flashcard')
            ->where(['chapter_id' => $chapter_id, 'sub_institute_id' => $sub_institute_id, 'status' => 1])
            ->get()
            ->toArray();

        $content_by_category['Flash Cards'] = array_map(function ($f) {
            return (array)$f;
        }, $flash);
        $content_by_category['Mindmap'] = [];
        $content_by_category['Virtual Lab'] = [];

        return $content_by_category;
    }

    public function chapterContent(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'chapter_id' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status_code' => 0,
                'message' => 'Validation failed.',
                'errors' => $validator->errors()->messages(),
            ], 422);
        }

        $chapter_id = (int)$request->input('chapter_id');
        $sub_institute_id = $request->input('sub_institute_id') ?? $this->sessionValue($request, 'sub_institute_id');

        $chapterQuery = DB::table('chapter_master')
            ->select('id', 'syear', 'sub_institute_id', 'grade_id', 'standard_id', 'subject_id', 'chapter_name', 'chapter_desc', 'availability', 'show_hide', 'sort_order')
            ->where('id', $chapter_id);

        if ($sub_institute_id) {
            $chapterQuery->where(function ($query) use ($sub_institute_id) {
                $query->where('sub_institute_id', $sub_institute_id)
                    ->orWhere('sub_institute_id', 1);
            });
        }

        $chapter = $chapterQuery->first();

        if (!$chapter) {
            return response()->json([
                'status_code' => 0,
                'message' => 'Chapter not found.',
                'chapter_id' => $chapter_id,
            ], 404);
        }

        $sub_institute_id = $sub_institute_id ?: $chapter->sub_institute_id;
        $chapterArray = (array)$chapter;
        $chapterArray['content_categories'] = $this->getChapterContentCategories(
            $chapter->id,
            $chapter->subject_id,
            $chapter->standard_id,
            $sub_institute_id
        );

        return response()->json([
            'status_code' => 1,
            'message' => 'SUCCESS',
            'data' => $chapterArray,
            'content_data' => [
                $chapter->id => $chapterArray['content_categories'],
            ],
            'chapter_id' => $chapter->id,
            'sub_institute_id' => $sub_institute_id,
        ], 200);
    }

    public function chapters(Request $request): JsonResponse
    {
        $sub_institute_id = $request->input('sub_institute_id') ?? $request->session()->get('sub_institute_id');
        $subject_id = $request->input('subject_id');
        $standard_id = $request->input('standard_id');
        $user_profile_name = $request->input('user_profile_name') ?? session()->get('user_profile_name');

        if (!in_array(strtoupper($user_profile_name), ['TEACHER', 'ADMIN'])) {
            return response()->json([
                'status_code' => 0,
                'message' => 'Unauthorized. Admin or Teacher access required.',
            ], 403);
        }

        $chapters = $this->getChaptersWithContentCategories($subject_id, $standard_id, $sub_institute_id);

        $subject = DB::table('sub_std_map as s')
            ->select('s.display_name as subject_name', 'STD.name as standard_name')
            ->join('standard AS STD', 'STD.id', '=', 's.standard_id')
            ->where('s.subject_id', $subject_id)
            ->where('s.standard_id', $standard_id)
            ->first();

        return response()->json([
            'status_code' => 1,
            'message' => 'SUCCESS',
            'data' => $chapters,
            'subject_name' => $subject->subject_name ?? '',
            'standard_name' => $subject->standard_name ?? '',
            'sub_institute_id' => $sub_institute_id,
        ], 200);
    }

    public function storeChapter(Request $request): JsonResponse
    {
        $sub_institute_id = $request->input('sub_institute_id') ? $request->input('sub_institute_id') : ($request->session()->get('sub_institute_id') ?: null);
        $syear = $request->input('syear') ? $request->input('syear') : ($request->session()->get('syear') ?: null);
        $user_id = $request->input('user_id') ? $request->input('user_id') : (session()->get('user_id') ?: null);
        $user_profile_name = $request->input('user_profile_name') ? $request->input('user_profile_name') : (session()->get('user_profile_name') ?: null);

        if (!in_array(strtoupper($user_profile_name), ['TEACHER', 'ADMIN'])) {
            return response()->json([
                'status_code' => 0,
                'message' => 'Unauthorized. Admin or Teacher access required.',
            ], 403);
        }

        $standard_id = $request->input('standard_id');
        $subject_id = $request->input('subject_id');
        $chapter_name = $request->input('chapter_name') ?: $request->input('chapter_name_');
        $grade_id = $request->input('grade_id') ?: $request->input('grade');

        if (!$standard_id || !$subject_id || !$chapter_name) {
            return response()->json([
                'status_code' => 0,
                'message' => 'Missing required fields. Check if field names match.',
                'received' => $request->all(),
                'debug' => [
                    'standard_id' => $standard_id,
                    'subject_id' => $subject_id,
                    'chapter_name' => $chapter_name,
                ],
            ], 422);
        }

        // Ensure grade_id is set
        if (!$grade_id) {
            $grade_id = DB::table('standard')->where('id', $standard_id)->value('grade_id');
        }

        $chapter_desc = $request->input('chapter_desc') ?? '';
        $availability = $request->input('availability') ?? '';
        $sort_order = $request->input('sort_order') ?? '';
        $show_hide = $request->input('show_hide') ?? '1';

        $chapter = [
            'grade_id' => $grade_id,
            'standard_id' => $standard_id,
            'subject_id' => $subject_id,
            'chapter_name' => $chapter_name,
            'chapter_desc' => $chapter_desc,
            'availability' => $availability,
            'show_hide' => $show_hide,
            'created_by' => $user_id,
            'sub_institute_id' => $sub_institute_id,
            'sort_order' => $sort_order,
            'syear' => $syear,
            'created_at' => now()->toDateTimeString(),
        ];

        try {
            $insertedId = DB::table('chapter_master')->insertGetId($chapter);
        } catch (\Exception $e) {
            return response()->json([
                'status_code' => 0,
                'message' => 'Database error: ' . $e->getMessage(),
                'debug_data' => $chapter,
            ], 500);
        }

        return response()->json([
            'status_code' => 1,
            'message' => 'Chapter Added Successfully',
            'subject_id' => $subject_id,
            'chapter_id' => $insertedId ?? null,
        ], 200);
    }

    public function createContent(Request $request): JsonResponse
    {
        $sub_institute_id = $request->input('sub_institute_id') ?? $request->session()->get('sub_institute_id');
        $chapter_id = $request->input('chapter_id');
        $user_profile_name = $request->input('user_profile_name') ?? session()->get('user_profile_name');

        if (!in_array(strtoupper($user_profile_name), ['TEACHER', 'ADMIN'])) {
            return response()->json([
                'status_code' => 0,
                'message' => 'Unauthorized. Admin or Teacher access required.',
            ], 403);
        }

        $breadcrum_data = DB::table('chapter_master as c')
            ->join('sub_std_map as s', function ($join) {
                $join->whereRaw('s.subject_id = c.subject_id AND s.standard_id = c.standard_id');
            })->join('standard as st', function ($join) {
                $join->whereRaw('st.id = c.standard_id');
            })
            ->selectRaw('c.subject_id,s.display_name AS subject_name,c.standard_id,st.name AS standard_name,
                c.id AS chapter_id, c.chapter_name')
            ->where('c.sub_institute_id', $sub_institute_id)
            ->where('c.id', $chapter_id)
            ->first();

        $lms_mapping_type = DB::table('lms_mapping_type')
            ->where('status', '=', 1)
            ->where('parent_id', '=', 0)
            ->where(function ($q) use ($chapter_id) {
                $q->where('globally', '=', 1)
                    ->orWhere('chapter_id', $chapter_id);
            })
            ->get()->toArray();

        $content_category = DB::table('lms_content_category')
            ->where('status', '2')
            ->get()->toArray();

        return response()->json([
            'status_code' => 1,
            'message' => 'SUCCESS',
            'data' => [
                'breadcrum_data' => $breadcrum_data,
                'lms_mapping_type' => $lms_mapping_type,
                'content_category' => $content_category,
                'standard_id' => $breadcrum_data->standard_id ?? null,
                'subject_id' => $breadcrum_data->subject_id ?? null,
            ],
            'sub_institute_id' => $sub_institute_id,
        ], 200);
    }

    public function getContentMappingValues(Request $request): JsonResponse
    {
        $mapping_type = $request->input('mapping_type');
        $mapping_types = explode(',', $mapping_type ?? '');

        $values = DB::table('lms_mapping_type')
            ->select(['id', 'name'])
            ->whereIn('parent_id', $mapping_types)
            ->where('status', '1')
            ->get()->toArray();

        return response()->json([
            'status_code' => 1,
            'message' => 'SUCCESS',
            'data' => $values,
        ], 200);
    }

    public function storeContent(Request $request): JsonResponse
    {
        $sub_institute_id = $request->input('sub_institute_id') ? $request->input('sub_institute_id') : ($request->session()->get('sub_institute_id') ?: null);
        $syear = $request->input('syear') ? $request->input('syear') : ($request->session()->get('syear') ?: date('Y'));
        $user_id = $request->input('user_id') ? $request->input('user_id') : (session()->get('user_id') ?: null);
        $user_profile_name = $request->input('user_profile_name') ? $request->input('user_profile_name') : (session()->get('user_profile_name') ?: null);

        if (!in_array(strtoupper($user_profile_name), ['TEACHER', 'ADMIN'])) {
            return response()->json([
                'status_code' => 0,
                'message' => 'Unauthorized. Admin or Teacher access required.',
            ], 403);
        }

        $standard_id = $request->input('standard_id');
        $subject_id = $request->input('subject_id');
        $chapter_id = $request->input('chapter_id');
        $topic_id = $request->input('topic_id', 0);
        $grade_id = $request->input('grade_id') ?: DB::table('standard')->where('id', $standard_id)->value('grade_id');

        $show_hide = $request->input('show_hide');
        $show_hide_val = $show_hide ?? '1';

        $basic_advanced = $request->input('toggle_basic_advanced');
        $basic_advanced_val = isset($basic_advanced) ? '1' : '0';

        $file_folder = $ext = $size = $newfilename = $file_url = '';

        $gamma_presentation_url = $request->input('gamma_presentation_url');
        $ibl_generated_url = $request->input('ibl_generated_url');
        $ibl_content_type = $request->input('ibl_content_type');

        if (!empty($gamma_presentation_url)) {
            $newfilename = $gamma_presentation_url;
            $ext = 'link';
            $file_folder = '/lms_content_file';
            $file_url = $gamma_presentation_url;
        } elseif (!empty($ibl_generated_url)) {
            $newfilename = $ibl_generated_url;
            $ext = $ibl_content_type === 'link' ? 'link' : ($ibl_content_type ?? 'pdf');
            $file_folder = '/lms_content_file';
            $file_url = $ibl_generated_url;
        } elseif (!empty($request->input('ai_generated_filename'))) {
            $ai_filename = $request->input('ai_generated_filename');
            $sourcePath = storage_path('app/public/pdfs/' . $ai_filename);
            if (file_exists($sourcePath)) {
                $newfilename = 'lms_' . date('Y-m-d_h-i-s') . '.pdf';
                $fileContent = file_get_contents($sourcePath);
                Storage::disk('digitalocean')->put('public/lms_content_file/' . $newfilename, $fileContent, 'public');
            } else {
                $newfilename = $ai_filename;
            }
            $file_folder = '/lms_content_file';
            $ext = 'pdf';
        } elseif ($request->hasFile('filename')) {
            $img = $request->file('filename');
            $ext = $img->getClientOriginalExtension();
            $size = $img->getSize();
            $newfilename = 'lms_' . date('Y-m-d_h-i-s') . '.' . $ext;
            $file_folder = '/lms_content_file';
            Storage::disk('digitalocean')->putFileAs('public/lms_content_file/', $img, $newfilename, 'public');
        } elseif ($request->input('link') || $request->has('contentType')) {
            $newfilename = $request->input('link');
            $ext = 'link';
            $file_url = $request->input('link');
            $file_folder = '/lms_content_file';
        } else {
            $file_folder = '/lms_content_file';
        }

        $pre_topic = $post_topic = $cross_curriculum_topic = '';
        if ($request->input('prechapter')) {
            $pre_topic = $request->input('prechapter') . '####' . $request->input('pretopic');
        }
        if ($request->input('postchapter')) {
            $post_topic = $request->input('postchapter') . '####' . $request->input('posttopic');
        }
        if ($request->input('cross_curriculumchapter')) {
            $cross_curriculum_topic = $request->input('cross_curriculumchapter') . '####' . $request->input('cross_curriculumtopic');
        }

$restrict_date = $request->input('restrict_date');
        $content = [
            'grade_id' => $grade_id,
            'standard_id' => $standard_id,
            'subject_id' => $subject_id,
            'chapter_id' => $chapter_id,
            'topic_id' => $topic_id,
            'sub_topic_id' => $request->input('sub_topic_id'),
            'title' => $request->input('title'),
            'description' => $request->input('description'),
            'file_folder' => $file_folder,
            'filename' => $newfilename,
            'url' => $file_url,
            'file_type' => $ext,
            'file_size' => $size ?: 0,
            'show_hide' => $show_hide_val ?: '1',
            'sort_order' => $request->input('sort_order'),
            'meta_tags' => $request->input('meta_tags'),
            'content_category' => $request->input('content_category'),
            'created_by' => $user_id,
            'sub_institute_id' => $sub_institute_id,
            'restrict_date' => $restrict_date ? date('Y-m-d', strtotime($restrict_date)) : null,
            'pre_grade_topic' => $pre_topic ?: null,
            'post_grade_topic' => $post_topic ?: null,
            'cross_curriculum_grade_topic' => $cross_curriculum_topic ?: null,
            'basic_advance' => $basic_advanced_val ?: '0',
            'user_profile_name' => $user_profile_name,
            'syear' => $syear,
        ];

        try {
            $last_id = DB::table('content_master')->insertGetId($content);
        } catch (\Exception $e) {
            return response()->json([
                'status_code' => 0,
                'message' => 'Database error: ' . $e->getMessage(),
                'debug_data' => $content,
            ], 500);
        }

        $mapping_type = $request->input('mapping_type', []);
        $mapping_value = $request->input('mapping_value', []);
        if (is_string($mapping_type)) {
            $mapping_type = array_map('intval', explode(',', $mapping_type));
        }
        if (is_string($mapping_value)) {
            $mapping_value = array_map('intval', explode(',', $mapping_value));
        }

        if (!empty($mapping_type) && is_array($mapping_type)) {
            foreach ($mapping_type as $key => $val) {
                if (!empty($val) && isset($mapping_value[$key]) && !empty($mapping_value[$key])) {
                    contentmappingtypeModel::insert([
                        'content_id' => $last_id,
                        'mapping_type_id' => $val,
                        'mapping_value_id' => $mapping_value[$key],
                    ]);
                }
            }
        }

        return response()->json([
            'status_code' => 1,
            'message' => 'Content Added Successfully',
            'content_id' => $last_id,
            'chapter_id' => $chapter_id,
            'subject_id' => $subject_id,
            'standard_id' => $standard_id,
        ], 200);
    }

    public function storeSubject(Request $request): JsonResponse
    {
        $sub_institute_id = $request->input('sub_institute_id') ? $request->input('sub_institute_id') : ($request->session()->get('sub_institute_id') ?: null);
        $user_id = $request->input('user_id') ? $request->input('user_id') : (session()->get('user_id') ?: null);
        $user_profile_name = $request->input('user_profile_name') ? $request->input('user_profile_name') : (session()->get('user_profile_name') ?: null);

        if (!in_array(strtoupper($user_profile_name), ['TEACHER', 'ADMIN'])) {
            return response()->json([
                'status_code' => 0,
                'message' => 'Unauthorized. Admin or Teacher access required.',
            ], 403);
        }

        $standard_id = $request->input('standard_id');
        $subject_name = $request->input('subject_name');
        $subject_id = $request->input('subject_id');

        if (!$standard_id) {
            return response()->json([
                'status_code' => 0,
                'message' => 'Missing required field: standard_id is required.',
            ], 422);
        }

        if (!$subject_id && !$subject_name) {
            return response()->json([
                'status_code' => 0,
                'message' => 'Missing required field: subject_id or subject_name is required.',
            ], 422);
        }

        $grade_id = DB::table('standard')->where('id', $standard_id)->value('grade_id');

        if ($subject_id) {
            $existingSubject = DB::table('sub_std_map')
                ->where('subject_id', $subject_id)
                ->where('standard_id', $standard_id)
                ->where('sub_institute_id', $sub_institute_id)
                ->first();

            if ($existingSubject) {
                return response()->json([
                    'status_code' => 0,
                    'message' => 'Subject already exists for this standard.',
                ], 409);
            }
        } else {
            $lastSubjectCode = DB::table('subject')
                ->where('sub_institute_id', $sub_institute_id)
                ->orderByDesc('id')
                ->value('subject_code');

            $newSubjectCode = $lastSubjectCode ? $lastSubjectCode + 1 : 1;

            $subject = [
                'subject_name' => $subject_name,
                'subject_code' => $newSubjectCode,
                'sub_institute_id' => $sub_institute_id,
                'status' => '1',
                'created_at' => now()->toDateTimeString(),
            ];

            try {
                $subject_id = DB::table('subject')->insertGetId($subject);
            } catch (\Exception $e) {
                return response()->json([
                    'status_code' => 0,
                    'message' => 'Failed to create subject: ' . $e->getMessage(),
                ], 500);
            }
        }

        $map_data = [
            'standard_id' => $standard_id,
            'subject_id' => $subject_id,
            'display_name' => $request->input('display_name') ?: $subject_name,
            'allow_content' => $request->input('allow_content', 'Yes'),
            'subject_category' => $request->input('subject_category'),
            'sort_order' => $request->input('sort_order'),
            'sub_institute_id' => $sub_institute_id,
            'created_at' => now()->toDateTimeString(),
        ];

        try {
            $insertedId = DB::table('sub_std_map')->insertGetId($map_data);
        } catch (\Exception $e) {
            return response()->json([
                'status_code' => 0,
                'message' => 'Database error: ' . $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'status_code' => 1,
            'message' => 'Subject Created and Added Successfully',
            'subject_id' => $subject_id,
            'standard_id' => $standard_id,
            'id' => $insertedId,
        ], 200);
    }

    public function getChapterConcepts(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'chapter_id' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status_code' => 0,
                'message' => 'Validation failed.',
                'errors' => $validator->errors()->messages(),
            ], 422);
        }

        $chapter_id = (int) $request->input('chapter_id');
        $sub_institute_id = $request->input('sub_institute_id') ?? $this->sessionValue($request, 'sub_institute_id');

        $query = DB::table('lms_concept')
            ->select('*')
            ->where('chapter_id', $chapter_id);

        if ($sub_institute_id) {
            $query->where(function ($q) use ($sub_institute_id) {
                $q->where('sub_institute_id', $sub_institute_id)
                    ->orWhere('sub_institute_id', 1);
            });
        }

        $concepts = $query->orderBy('id')->get()->toArray();

        return response()->json([
            'status_code' => 1,
            'message' => 'SUCCESS',
            'data' => $concepts,
            'chapter_id' => $chapter_id,
            'sub_institute_id' => $sub_institute_id,
            'total' => count($concepts),
        ], 200);
    }
    // public function getQuestionBank(Request $request): JsonResponse
    // {
    //     $chapterId = $request->input('chapter_id');
    //     $topicId = $request->input('topic_id');
    //     $questionType = $request->input('question_type');

    //     if (!$chapterId) {
    //         return response()->json([
    //             'status' => false,
    //             'message' => 'Missing required field: chapter_id is required.',
    //             'data' => [],
    //         ], 422);
    //     }

    //     $query = lmsQuestionMasterModel::query()
    //         ->where('chapter_id', $chapterId)
    //         ->join('question_type_master', 'question_type_master.id', '=', 'lms_question_master.question_type_id')
    //         ->select(
    //             'lms_question_master.id',
    //             'lms_question_master.chapter_id',
    //             'lms_question_master.topic_id',
    //             'lms_question_master.question_title',
    //             'question_type_master.question_type',
    //             'lms_question_master.points as marks',
    //             'lms_question_master.answer as model_answer'
    //         );

    //     if ($topicId) {
    //         $query->where('lms_question_master.topic_id', $topicId);
    //     }

    //     if ($questionType) {
    //         if (is_numeric($questionType)) {
    //             $query->where('lms_question_master.question_type_id', (int) $questionType);
    //         } else {
    //             $query->where('question_type_master.question_type', trim($questionType));
    //         }
    //     }

    //     $questions = $query->orderByDesc('lms_question_master.id')->get()->toArray();

    //     $questionIds = array_column($questions, 'id');
    //     $optionsByQuestion = [];

    //     if (!empty($questionIds)) {
    //         $options = \App\Models\lms\answermasterModel::whereIn('question_id', $questionIds)  
    //             ->orderBy('id')
    //             ->get(['question_id', 'answer', 'correct_answer'])
    //             ->toArray();

    //         $labels = ['A', 'B', 'C', 'D', 'E', 'F'];
    //         foreach ($options as $option) {
    //             $qid = (int) $option['question_id'];
    //             $idx = count($optionsByQuestion[$qid] ?? []);
    //             $label = $labels[$idx] ?? chr(65 + $idx);

    //             $optionsByQuestion[$qid][] = [
    //                 'label' => $label,
    //                 'text' => (string) $option['answer'],
    //                 'is_correct' => (bool) $option['correct_answer'],
    //             ];
    //         }
    //     }

    //     $data = [];
    //     foreach ($questions as $question) {
    //         $qid = (int) $question['id'];
    //         $questionTypeLabel = strtoupper((string) ($question['question_type'] ?? ''));
    //         if ($questionTypeLabel === 'MCQ' || $questionTypeLabel === 'MULTIPLE_CHOICE') {
    //             $questionTypeLabel = 'MCQ';
    //         } elseif ($questionTypeLabel === 'NARRATIVE' || $questionTypeLabel === 'SUBJECTIVE') {
    //             $questionTypeLabel = 'Narrative';
    //         }

    //         $data[] = [
    //             'id' => (int) $question['id'],
    //             'chapter_id' => (int) $question['chapter_id'],
    //             'topic_id' => $question['topic_id'] !== null ? (int) $question['topic_id'] : null,
    //             'question' => (string) ($question['question_title'] ?? ''),
    //             'question_type' => $questionTypeLabel,
    //             'options' => $optionsByQuestion[$qid] ?? [],
    //             'model_answer' => $question['model_answer'] !== null ? (string) $question['model_answer'] : null,
    //             'marks' => (int) ($question['marks'] ?? 1),
    //         ];
    //     }

    //     return response()->json([
    //         'status' => true,
    //         'message' => 'Questions fetched successfully.',
    //         'data' => $data,
    //     ], 200);
    // }

    /**
     * GET /api/question-mapping-levels
     *
     * DOK and Bloom level options for question filtering, straight from
     * lms_mapping_type (children of parent 9 = Depth of Knowledge and
     * parent 82 = Blooms Taxonomy). Nothing is hardcoded client-side, so
     * adding a new level row (e.g. a DOK level 4 label) shows up automatically.
     */
    // public function getQuestionMappingLevels(): JsonResponse
    // {
    //     $children = DB::table('lms_mapping_type')
    //         ->whereIn('parent_id', [9, 82])
    //         ->where('status', 1)
    //         ->orderBy('id')
    //         ->get(['id', 'name', 'parent_id']);

    //     $dok = [];
    //     $bloom = [];
    //     foreach ($children as $child) {
    //         $option = ['id' => (int) $child->id, 'name' => trim($child->name)];
    //         if ((int) $child->parent_id === 9) {
    //             $dok[] = $option;
    //         } else {
    //             $bloom[] = $option;
    //         }
    //     }

    //     return response()->json([
    //         'status_code' => 1,
    //         'message' => 'SUCCESS',
    //         'data' => [
    //             'dok' => $dok,
    //             'bloom' => $bloom,
    //         ],
    //     ], 200);
    // }
    public function getLmsQuestions(Request $request): JsonResponse
    {
        $sub_institute_id = $request->input('sub_institute_id');
        if (!$sub_institute_id) {
            try {
                $sub_institute_id = $request->session()->get('sub_institute_id');
            } catch (\Throwable $e) {
                $sub_institute_id = null;
            }
        }

        $subject_id = $request->input('subject_id');

        $chapter_ids = $request->input('chapter_id', []);
        $concept_ids = $request->input('concept_id', []);

        if (is_string($chapter_ids)) {
            $chapter_ids = $chapter_ids ? explode(',', $chapter_ids) : [];
        }
        if (is_string($concept_ids)) {
            $concept_ids = $concept_ids ? explode(',', $concept_ids) : [];
        }

        $chapter_ids = array_filter(array_map('intval', (array) $chapter_ids));
        $concept_ids = array_filter(array_map('intval', (array) $concept_ids));

        if (!$subject_id) {
            return response()->json([
                'status_code' => 0,
                'message' => 'Missing required field: subject_id is required.',
            ], 422);
        }

        $query = lmsQuestionMasterModel::query()->where('subject_id', $subject_id);

        if ($sub_institute_id) {
            $query->where('sub_institute_id', $sub_institute_id);
        }

        if (!empty($chapter_ids)) {
            $query->whereIn('chapter_id', $chapter_ids);
        }

        if (!empty($concept_ids)) {
            $query->whereIn('concept_id', $concept_ids);
        }

        $questions = $query->get([
            'id', 'question_type_id', 'grade_id', 'standard_id',
            'subject_id', 'chapter_id', 'concept_id', 'topic_id',
            'question_title', 'description', 'points', 'multiple_answer',
            'concept', 'subconcept', 'pre_grade_topic', 'post_grade_topic',
            'cross_curriculum_grade_topic', 'status', 'created_by', 'created_on'
        ])->toArray();

        return response()->json([
            'status_code' => 1,
            'message' => 'SUCCESS',
            'data' => $questions,
            'total' => count($questions),
            'subject_id' => $subject_id,
            'chapter_ids' => $chapter_ids,
            'concept_ids' => $concept_ids,
        ], 200);
    }

    private function normalizeConceptId($conceptId): ?int
    {
        if ($conceptId === null) {
            return null;
        }

        if (is_string($conceptId)) {
            $conceptId = trim($conceptId);
        }

        if ($conceptId === '' || strtolower((string) $conceptId) === 'all') {
            return null;
        }

        $conceptId = (int) $conceptId;

        return $conceptId > 0 ? $conceptId : null;
    }

    private function resolveContentMedia(Request $request, bool $applyPresentationRules = false): array
    {
        $file_folder = '/lms_content_file';
        $contentType = strtolower(trim((string) $request->input('content_type', $request->input('contentType', ''))));
        $presentationType = strtolower(trim((string) $request->input('presentation_type', $request->input('presentationType', ''))));
        $restrictToPresentationFiles = $applyPresentationRules
            || $contentType === 'presentation'
            || str_contains($presentationType, 'presentation');

        $gamma_presentation_url = trim((string) $request->input('gamma_presentation_url', ''));
        if ($gamma_presentation_url !== '') {
            return [
                'file_folder' => $file_folder,
                'filename' => $gamma_presentation_url,
                'url' => $gamma_presentation_url,
                'file_type' => 'link',
                'file_size' => 0,
            ];
        }

        $ibl_generated_url = trim((string) $request->input('ibl_generated_url', ''));
        if ($ibl_generated_url !== '') {
            $ibl_content_type = strtolower(trim((string) $request->input('ibl_content_type', 'link')));

            return [
                'file_folder' => $file_folder,
                'filename' => $ibl_generated_url,
                'url' => $ibl_generated_url,
                'file_type' => $ibl_content_type ?: 'link',
                'file_size' => 0,
            ];
        }

        $aiGeneratedFilename = trim((string) $request->input('ai_generated_filename', ''));
        if ($aiGeneratedFilename !== '') {
            $sourcePath = storage_path('app/public/pdfs/' . $aiGeneratedFilename);
            if (file_exists($sourcePath)) {
                $filename = 'lms_' . date('Y-m-d_h-i-s') . '.pdf';
                $fileContent = file_get_contents($sourcePath);
                Storage::disk('digitalocean')->put('public/lms_content_file/' . $filename, $fileContent, 'public');
            } else {
                $filename = $aiGeneratedFilename;
            }

            return [
                'file_folder' => $file_folder,
                'filename' => $filename,
                'url' => null,
                'file_type' => 'pdf',
                'file_size' => 0,
            ];
        }

        if ($request->hasFile('filename')) {
            $img = $request->file('filename');
            $ext = strtolower((string) $img->getClientOriginalExtension());

            if ($restrictToPresentationFiles && !in_array($ext, ['pdf', 'ppt', 'pptx'], true)) {
                return [
                    'error' => 'Invalid file type. Allowed files: PDF, PPT, and PPTX.',
                ];
            }

            if ($restrictToPresentationFiles && $img->getSize() > (100 * 1024 * 1024)) {
                return [
                    'error' => 'File size must be 100 MB or less.',
                ];
            }

            $filename = 'lms_' . date('Y-m-d_h-i-s') . '.' . $ext;
            Storage::disk('digitalocean')->putFileAs('public/lms_content_file/', $img, $filename, 'public');

            return [
                'file_folder' => $file_folder,
                'filename' => $filename,
                'url' => null,
                'file_type' => $ext,
                'file_size' => $img->getSize(),
            ];
        }

        $link = trim((string) $request->input('link', ''));
        if ($link !== '') {
            return [
                'file_folder' => $file_folder,
                'filename' => $link,
                'url' => $link,
                'file_type' => 'link',
                'file_size' => 0,
            ];
        }

        return [
            'error' => 'Please upload a file or provide a link.',
        ];
    }

    private function persistContent(Request $request, bool $applyPresentationRules = false): JsonResponse
    {
        $sub_institute_id = $request->input('sub_institute_id') ?: $this->sessionValue($request, 'sub_institute_id');
        $syear = $request->input('syear') ?: ($this->sessionValue($request, 'syear') ?: date('Y'));
        $user_id = $request->input('user_id') ?: $this->sessionValue($request, 'user_id');
        $user_profile_name = trim((string) ($request->input('user_profile_name') ?: $this->sessionValue($request, 'user_profile_name')));

        $contentRole = strtoupper($user_profile_name);
        if (!in_array($contentRole, ['TEACHER', 'LMS TEACHER', 'ADMIN', 'SUPER ADMIN'], true)) {
            return response()->json([
                'status_code' => 0,
                'message' => 'Unauthorized. Admin or Teacher access required.',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'standard_id' => 'required|integer',
            'subject_id' => 'required|integer',
            'chapter_id' => 'required|integer',
            'title' => 'required|string|max:250',
            'concept_id' => 'nullable',
            'presentation_type' => 'nullable|string|max:150',
            'content_type' => 'nullable|string|max:100',
            'content_category' => 'nullable|string|max:250',
            'link' => 'nullable|string|max:2048',
            'filename' => $applyPresentationRules ? 'nullable|file|mimes:pdf,ppt,pptx|max:102400' : 'nullable|file|max:102400',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status_code' => 0,
                'message' => 'Validation failed.',
                'errors' => $validator->errors()->messages(),
            ], 422);
        }

        $standard_id = $request->input('standard_id');
        $subject_id = $request->input('subject_id');
        $chapter_id = $request->input('chapter_id');
        $topic_id = $request->input('topic_id', 0);
        $grade_id = $request->input('grade_id') ?: DB::table('standard')->where('id', $standard_id)->value('grade_id');
        $show_hide_val = $request->input('show_hide') ?? '1';
        $concept_id = $this->normalizeConceptId($request->input('concept_id'));
        $content_type = trim((string) $request->input('content_type', $request->input('contentType', '')));
        $presentation_type = trim((string) $request->input('presentation_type', $request->input('presentationType', '')));
        $content_category = trim((string) $request->input('content_category', ''));

        if ($content_category === '' && $presentation_type !== '') {
            $content_category = $presentation_type;
        }

        if ($content_category === '') {
            $content_category = $content_type !== '' ? $content_type : 'Content';
        }

        $media = $this->resolveContentMedia($request, $applyPresentationRules);
        if (isset($media['error'])) {
            return response()->json([
                'status_code' => 0,
                'message' => $media['error'],
            ], 422);
        }

        $pre_topic = $post_topic = $cross_curriculum_topic = '';
        if ($request->input('prechapter')) {
            $pre_topic = $request->input('prechapter') . '####' . $request->input('pretopic');
        }
        if ($request->input('postchapter')) {
            $post_topic = $request->input('postchapter') . '####' . $request->input('posttopic');
        }
        if ($request->input('cross_curriculumchapter')) {
            $cross_curriculum_topic = $request->input('cross_curriculumchapter') . '####' . $request->input('cross_curriculumtopic');
        }

        $content = [
            'grade_id' => $grade_id,
            'standard_id' => $standard_id,
            'subject_id' => $subject_id,
            'chapter_id' => $chapter_id,
            'concept_id' => $concept_id,
            'topic_id' => $topic_id,
            'sub_topic_id' => $request->input('sub_topic_id'),
            'title' => $request->input('title'),
            'description' => $request->input('description'),
            'file_folder' => $media['file_folder'],
            'filename' => $media['filename'],
            'url' => $media['url'],
            'file_type' => $media['file_type'],
            'file_size' => $media['file_size'],
            'show_hide' => $show_hide_val,
            'sort_order' => $request->input('sort_order'),
            'meta_tags' => $request->input('meta_tags'),
            'content_category' => $content_category,
            'created_by' => $user_id,
            'sub_institute_id' => $sub_institute_id,
            'restrict_date' => $request->input('restrict_date') ? date('Y-m-d', strtotime($request->input('restrict_date'))) : null,
            'pre_grade_topic' => $pre_topic ?: null,
            'post_grade_topic' => $post_topic ?: null,
            'cross_curriculum_grade_topic' => $cross_curriculum_topic ?: null,
            'basic_advance' => $request->input('toggle_basic_advanced') ? '1' : '0',
            'user_profile_name' => $user_profile_name,
            'syear' => $syear,
        ];

        if (Schema::hasColumn('content_master', 'content_type')) {
            $content['content_type'] = $content_type !== '' ? $content_type : null;
        }

        if (Schema::hasColumn('content_master', 'presentation_type')) {
            $content['presentation_type'] = $presentation_type !== '' ? $presentation_type : null;
        }

        try {
            $last_id = DB::table('content_master')->insertGetId($content);
        } catch (\Exception $e) {
            return response()->json([
                'status_code' => 0,
                'message' => 'Database error: ' . $e->getMessage(),
                'debug_data' => $content,
            ], 500);
        }

        $mapping_type = $request->input('mapping_type', []);
        $mapping_value = $request->input('mapping_value', []);
        if (is_string($mapping_type)) {
            $mapping_type = array_map('intval', explode(',', $mapping_type));
        }
        if (is_string($mapping_value)) {
            $mapping_value = array_map('intval', explode(',', $mapping_value));
        }

        if (!empty($mapping_type) && is_array($mapping_type)) {
            foreach ($mapping_type as $key => $val) {
                if (!empty($val) && isset($mapping_value[$key]) && !empty($mapping_value[$key])) {
                    contentmappingtypeModel::insert([
                        'content_id' => $last_id,
                        'mapping_type_id' => $val,
                        'mapping_value_id' => $mapping_value[$key],
                    ]);
                }
            }
        }

        return response()->json([
            'status_code' => 1,
            'message' => 'Content Added Successfully',
            'content_id' => $last_id,
            'chapter_id' => $chapter_id,
            'subject_id' => $subject_id,
            'standard_id' => $standard_id,
            'concept_id' => $concept_id,
            'content_type' => $content_type,
            'presentation_type' => $presentation_type,
            'content_category' => $content_category,
        ], 200);
    }

    public function uploadContent(Request $request): JsonResponse
    {
        return $this->persistContent($request, true);
    }

    public function getQuestionBank(Request $request): JsonResponse
    {
        $chapterId = $request->input('chapter_id');
        $topicId = $request->input('topic_id');
        $questionType = $request->input('question_type');

        if (!$chapterId) {
            return response()->json([
                'status' => false,
                'message' => 'Missing required field: chapter_id is required.',
                'data' => [],
            ], 422);
        }

        $query = lmsQuestionMasterModel::query()
            ->where('chapter_id', $chapterId)
            ->join('question_type_master', 'question_type_master.id', '=', 'lms_question_master.question_type_id')
            ->select(
                'lms_question_master.id',
                'lms_question_master.chapter_id',
                'lms_question_master.topic_id',
                'lms_question_master.question_title',
                'question_type_master.question_type',
                'lms_question_master.points as marks',
                'lms_question_master.answer as model_answer'
            );

        if ($topicId) {
            $query->where('lms_question_master.topic_id', $topicId);
        }

        if ($questionType) {
            if (is_numeric($questionType)) {
                $query->where('lms_question_master.question_type_id', (int) $questionType);
            } else {
                $query->where('question_type_master.question_type', trim($questionType));
            }
        }

        $questions = $query->orderByDesc('lms_question_master.id')->get()->toArray();

        $questionIds = array_column($questions, 'id');
        $optionsByQuestion = [];

        if (!empty($questionIds)) {
            $options = \App\Models\lms\answermasterModel::whereIn('question_id', $questionIds)
                ->orderBy('id')
                ->get(['question_id', 'answer', 'correct_answer'])
                ->toArray();

            $labels = ['A', 'B', 'C', 'D', 'E', 'F'];
            foreach ($options as $option) {
                $qid = (int) $option['question_id'];
                $idx = count($optionsByQuestion[$qid] ?? []);
                $label = $labels[$idx] ?? chr(65 + $idx);

                $optionsByQuestion[$qid][] = [
                    'label' => $label,
                    'text' => (string) $option['answer'],
                    'is_correct' => (bool) $option['correct_answer'],
                ];
            }
        }

        $data = [];
        foreach ($questions as $question) {
            $qid = (int) $question['id'];
            $questionTypeLabel = strtoupper((string) ($question['question_type'] ?? ''));
            if ($questionTypeLabel === 'MCQ' || $questionTypeLabel === 'MULTIPLE_CHOICE') {
                $questionTypeLabel = 'MCQ';
            } elseif ($questionTypeLabel === 'NARRATIVE' || $questionTypeLabel === 'SUBJECTIVE') {
                $questionTypeLabel = 'Narrative';
            }

            $data[] = [
                'id' => (int) $question['id'],
                'chapter_id' => (int) $question['chapter_id'],
                'topic_id' => $question['topic_id'] !== null ? (int) $question['topic_id'] : null,
                'question' => (string) ($question['question_title'] ?? ''),
                'question_type' => $questionTypeLabel,
                'options' => $optionsByQuestion[$qid] ?? [],
                'model_answer' => $question['model_answer'] !== null ? (string) $question['model_answer'] : null,
                'marks' => (int) ($question['marks'] ?? 1),
            ];
        }

        return response()->json([
            'status' => true,
            'message' => 'Questions fetched successfully.',
            'data' => $data,
        ], 200);
    }

    /**
     * GET /api/question-mapping-levels
     *
     * DOK and Bloom level options for question filtering, straight from
     * lms_mapping_type (children of parent 9 = Depth of Knowledge and
     * parent 82 = Blooms Taxonomy). Nothing is hardcoded client-side, so
     * adding a new level row (e.g. a DOK level 4 label) shows up automatically.
     */
    public function getQuestionMappingLevels(): JsonResponse
    {
        $children = DB::table('lms_mapping_type')
            ->whereIn('parent_id', [9, 82])
            ->where('status', 1)
            ->orderBy('id')
            ->get(['id', 'name', 'parent_id']);

        $dok = [];
        $bloom = [];
        foreach ($children as $child) {
            $option = ['id' => (int) $child->id, 'name' => trim($child->name)];
            if ((int) $child->parent_id === 9) {
                $dok[] = $option;
            } else {
                $bloom[] = $option;
            }
        }

        return response()->json([
            'status_code' => 1,
            'message' => 'SUCCESS',
            'data' => [
                'dok' => $dok,
                'bloom' => $bloom,
            ],
        ], 200);
    }
}
