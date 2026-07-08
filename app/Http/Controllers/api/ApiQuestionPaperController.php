<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\lms\answermasterModel;
use App\Models\lms\lmsmappingtypeModel;
use App\Models\lms\lmsQuestionMappingModel;
use App\Models\lms\lmsQuestionMasterModel;
use App\Models\lms\questionpaperModel;
use App\Models\school_setup\sub_std_mapModel;
use App\Models\school_setup\subjectModel;
use App\Models\student\tblstudentEnrollmentModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ApiQuestionPaperController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $sub_institute_id = $request->input('sub_institute_id') ?? $request->session()->get('sub_institute_id');
        $syear = $request->input('syear') ?? $request->session()->get('syear');
        $user_profile_name = $request->input('user_profile_name') ?? session()->get('user_profile_name');
        $user_id = $request->input('user_id') ?? session()->get('user_id');

        if (!$sub_institute_id || !$syear) {
            return response()->json([
                'status_code' => 0,
                'message' => 'Missing required parameters: sub_institute_id and syear are required'
            ], 422);
        }

        $getIsLms = DB::table('school_setup')
            ->where('Id', $sub_institute_id)
            ->value('is_Lms');

        $sub_institute_id_by_lms = ($getIsLms == 'Y') ?
            "(question_paper.sub_institute_id = 1 or question_paper.sub_institute_id = $sub_institute_id)" :
            "question_paper.sub_institute_id = $sub_institute_id";

        if (strtoupper($user_profile_name) == "STUDENT") {
            $student_id = $user_id;
            $stu_data = tblstudentEnrollmentModel::select('standard_id')->where([
                'student_id' => $student_id,
                'syear' => $syear,
            ])->get()->toArray();

            $questionpaper_data = questionpaperModel::select(
                'question_paper.*',
                'standard.name as standard_name',
                'academic_section.title as grade_name',
                'ssm.display_name as subject_name',
                DB::raw('count(lms_online_exam.id) as total_attempt'),
                DB::raw('date_format(open_date, "%Y-%m-%d") as open_date'),
                DB::raw('date_format(close_date, "%Y-%m-%d") as close_date'),
                DB::raw('if(now() between open_date and close_date, "yes", "no") as active_exam')
            )
            ->join('standard', 'standard.id', '=', 'question_paper.standard_id')
            ->join('tblstudent_enrollment as se', function ($join) use ($student_id, $syear, $sub_institute_id) {
                $join->on('se.student_id', '=', $student_id)
                    ->on('se.syear', '=', $syear)
                    ->on('se.sub_institute_id', '=', $sub_institute_id);
            })
            ->join('academic_section', 'academic_section.id', '=', 'question_paper.grade_id')
            ->join('sub_std_map as ssm', function ($join) use ($sub_institute_id) {
                $join->on('ssm.subject_id', '=', 'question_paper.subject_id')
                    ->on('ssm.standard_id', '=', 'se.standard_id')
                    ->where('ssm.sub_institute_id', $sub_institute_id);
            })
            ->leftJoin('lms_online_exam', function ($join) use ($student_id) {
                $join->on('lms_online_exam.question_paper_id', '=', 'question_paper.id')
                    ->on('lms_online_exam.student_id', '=', $student_id);
            })
            ->whereRaw($sub_institute_id_by_lms)
            ->where('question_paper.syear', $syear)
            ->where('standard.id', $stu_data[0]['standard_id'] ?? null)
            ->where('question_paper.exam_type', 'online')
            ->where(function ($query) use ($sub_institute_id, $syear, $student_id) {
                $query->where('ssm.elective_subject', '!=', 'Yes')
                    ->orWhereIn('ssm.subject_id', function ($inQuery) use ($sub_institute_id, $syear, $student_id) {
                        $inQuery->select('sos.subject_id')
                            ->from('student_optional_subject as sos')
                            ->where('sos.sub_institute_id', $sub_institute_id)
                            ->where('sos.syear', $syear)
                            ->where('sos.student_id', $student_id);
                    });
            })
            ->groupBy('question_paper.id')
            ->get();
        } elseif ($user_profile_name == "Teacher") {
            $questionpaper_data = questionpaperModel::select('question_paper.*',
                'standard.name as standard_name',
                'academic_section.title as grade_name',
                'subject.subject_name',
                DB::raw('date_format(open_date,"%Y-%m-%d") as open_date,
                date_format(close_date,"%Y-%m-%d") as close_date,
                if(now() between open_date and close_date,"yes","no") as active_exam'))
            ->join('standard', function($join) {
                $join->on('standard.id', '=', 'question_paper.standard_id');
            })
            ->whereRaw($sub_institute_id_by_lms)
            ->join('academic_section', 'academic_section.id', '=', 'question_paper.grade_id')
            ->leftJoin('subject', 'subject.id', '=', 'question_paper.subject_id')
            ->where('question_paper.syear', $syear)
            ->where('question_paper.created_by', $user_id)
            ->orderBy('question_paper.id', 'desc')
            ->get();
        } else {
            $questionpaper_data = questionpaperModel::select('question_paper.*',
                'standard.name as standard_name',
                'academic_section.title as grade_name',
                'subject.subject_name',
                DB::raw('date_format(open_date,"%Y-%m-%d") as open_date,
                date_format(close_date,"%Y-%m-%d") as close_date,
                if(now() between open_date and close_date,"yes","no") as active_exam'))
            ->join('standard', function($join) {
                $join->on('standard.id', '=', 'question_paper.standard_id');
            })
            ->whereRaw($sub_institute_id_by_lms)
            ->join('academic_section', 'academic_section.id', '=', 'question_paper.grade_id')
            ->leftJoin('subject', 'subject.id', '=', 'question_paper.subject_id')
            ->where('question_paper.syear', $syear)
            ->orderBy('question_paper.id', 'desc')
            ->get();
        }

        return response()->json([
            'status_code' => 1,
            'message' => 'SUCCESS',
            'data' => $questionpaper_data
        ], 200);
    }

    public function store(Request $request): JsonResponse
    {
        $sub_institute_id = $request->input('sub_institute_id') ?? $request->session()->get('sub_institute_id');
        $syear = $request->input('syear') ?? $request->session()->get('syear');
        $user_id = $request->input('user_id') ?? $request->session()->get('user_id');

        $open_date = $request->input('open_date') ? date('Y-m-d H:i:s', strtotime($request->input('open_date'))) : null;
        $close_date = $request->input('close_date') ? date('Y-m-d 23:59:59', strtotime($request->input('close_date'))) : null;

        $question_ids = $request->input('question_ids') ? implode(",", $request->input('question_ids')) : '';

        $questionpaper = [
            'grade_id' => $request->input('grade'),
            'standard_id' => $request->input('standard'),
            'subject_id' => $request->input('subject'),
            'paper_name' => $request->input('paper_name'),
            'paper_desc' => $request->input('paper_desc'),
            'open_date' => $open_date,
            'close_date' => $close_date,
            'timelimit_enable' => $request->input('timelimit_enable') ?? '',
            'time_allowed' => $request->input('time_allowed'),
            'total_ques' => $request->input('total_ques'),
            'total_marks' => $request->input('total_marks'),
            'question_ids' => $question_ids,
            'shuffle_question' => $request->input('shuffle_question') ?? '',
            'attempt_allowed' => $request->input('attempt_allowed'),
            'show_feedback' => $request->input('show_feedback') ?? '',
            'show_hide' => $request->input('show_hide') ?? '',
            'result_show_ans' => $request->input('result_show_ans') ?? '',
            'created_by' => $user_id,
            'sub_institute_id' => $sub_institute_id,
            'syear' => $syear,
            'exam_type' => $request->input('exam_type'),
        ];

        $questionpaper_id = questionpaperModel::insertGetId($questionpaper);

        return response()->json([
            'status_code' => 1,
            'message' => 'Question-Paper Added Successfully',
            'id' => $questionpaper_id
        ], 200);
    }

    public function show(Request $request, $id): JsonResponse
    {
        $sub_institute_id = $request->input('sub_institute_id') ?? $request->session()->get('sub_institute_id');

        $questionpaper = questionpaperModel::find($id);

        if (!$questionpaper) {
            return response()->json([
                'status_code' => 0,
                'message' => 'Question paper not found'
            ], 404);
        }

        $question_ids = explode(",", $questionpaper->question_ids);
        $question_arr = lmsQuestionMasterModel::whereIn("id", $question_ids)->get()->toArray();

        $answer = [];
        foreach ($question_arr as $key => $val) {
            $answer_arr = answermasterModel::where("question_id", $val['id'])->get()->toArray();
            if (count($answer_arr) > 0) {
                foreach ($answer_arr as $anskey => $ansval) {
                    $answer[$val['id']][] = $ansval;
                }
            }
        }

        return response()->json([
            'status_code' => 1,
            'message' => 'SUCCESS',
            'data' => array_merge($questionpaper->toArray(), [
                'question_arr' => $question_arr,
                'answer_arr' => $answer
            ])
        ], 200);
    }

    public function update(Request $request, $id): JsonResponse
    {
        $sub_institute_id = $request->input('sub_institute_id') ?? $request->session()->get('sub_institute_id');
        $syear = $request->input('syear') ?? $request->session()->get('syear');
        $user_id = $request->input('user_id') ?? $request->session()->get('user_id');
        $question_ids = $request->input('questions') ? implode(",", $request->input('questions')) : '';

        $questionpaper = [
            'grade_id' => $request->input('grade'),
            'standard_id' => $request->input('standard'),
            'subject_id' => $request->input('subject'),
            'paper_name' => $request->input('paper_name'),
            'paper_desc' => $request->input('paper_desc'),
            'timelimit_enable' => $request->input('timelimit_enable') ?? '',
            'time_allowed' => $request->input('time_allowed'),
            'total_ques' => $request->input('total_ques'),
            'total_marks' => $request->input('total_marks'),
            'question_ids' => $question_ids,
            'shuffle_question' => $request->input('shuffle_question') ?? '',
            'attempt_allowed' => $request->input('attempt_allowed'),
            'show_feedback' => $request->input('show_feedback') ?? '',
            'show_hide' => $request->input('show_hide') ?? '',
            'result_show_ans' => $request->input('result_show_ans') ?? '',
            'created_by' => $user_id,
            'sub_institute_id' => $sub_institute_id,
            'syear' => $syear,
            'exam_type' => $request->input('exam_type'),
        ];

        $open_date = $request->input('open_date');
        if ($open_date) {
            $questionpaper['open_date'] = date('Y-m-d H:i:s', strtotime($open_date));
        }

        $close_date = $request->input('close_date');
        if ($close_date) {
            $questionpaper['close_date'] = date('Y-m-d 23:59:59', strtotime($close_date));
        }

        $updated = questionpaperModel::where("id", $id)->update($questionpaper);

        if ($updated) {
            return response()->json([
                'status_code' => 1,
                'message' => 'Question-Paper Updated Successfully'
            ], 200);
        }

        return response()->json([
            'status_code' => 0,
            'message' => 'Question-Paper Update Failed'
        ], 200);
    }

    public function destroy(Request $request, $id): JsonResponse
    {
        $deleted = questionpaperModel::where(["id" => $id])->delete();

        if ($deleted) {
            return response()->json([
                'status_code' => 1,
                'message' => 'Question-Paper Deleted Successfully'
            ], 200);
        }

        return response()->json([
            'status_code' => 0,
            'message' => 'Question-Paper Failed Delete'
        ], 200);
    }

    public function search(Request $request): JsonResponse
    {
        $sub_institute_id = $request->input('sub_institute_id') ?? $request->session()->get('sub_institute_id');
        $user_id = $request->input('user_id') ?? session()->get('user_id');
        $user_profile_name = $request->input('user_profile_name') ?? session()->get('user_profile_name');

        $grade = $request->input('grade');
        $subject = $request->input('subject');
        $standard = $request->input('standard');
        $search_chapter = $request->input('search_chapter', []);
        $search_topic = $request->input('search_topic', []);
        $search_mapping_type = $request->input('search_mapping_type', []);
        $search_mapping_value = $request->input('search_mapping_value', []);

        if (!$subject || !$standard) {
            return response()->json([
                'status_code' => 0,
                'message' => 'Missing required parameters: standard and subject are required'
            ], 422);
        }

        $sub_institute_id_for_query = $sub_institute_id ?? 0;

        $getIsLms = $sub_institute_id ? DB::table('school_setup')
            ->where('Id', $sub_institute_id)
            ->value('is_Lms') : null;

        $sub_institute_id_by_lms = ($getIsLms == 'Y') ?
            "(qm.sub_institute_id = 1 or qm.sub_institute_id = $sub_institute_id_for_query)" :
            "qm.sub_institute_id = $sub_institute_id_for_query";

        $extra = "";
        if (!empty($search_chapter)) {
            $search_chapter = is_array($search_chapter) ? array_filter($search_chapter) : [$search_chapter];
            if (!empty($search_chapter)) {
                $extra .= " AND qm.chapter_id IN (" . implode(",", $search_chapter) . ")";
            }
        }

        if (!empty($search_topic)) {
            $search_topic = is_array($search_topic) ? array_filter($search_topic) : [$search_topic];
            if (!empty($search_topic)) {
                $extra .= " AND qm.topic_id IN (" . implode(",", $search_topic) . ")";
            }
        }

        $sql = "SELECT qm.id,question_title,points,t.question_type,
            ifnull(GROUP_CONCAT(DISTINCT(am.answer)),'-') AS correct_answer,c.chapter_name,c.sort_order,
            tm.name as topic_name,GROUP_CONCAT(lqm.mapping_type_id) as mapping_type,GROUP_CONCAT(lqm.mapping_value_id) as mapping_value
            FROM lms_question_master qm
            INNER JOIN question_type_master t ON t.id = qm.question_type_id
            INNER JOIN chapter_master c ON c.id = qm.chapter_id
            LEFT JOIN topic_master tm ON tm.id = qm.topic_id
            LEFT JOIN lms_question_mapping lqm ON lqm.questionmaster_id = qm.id
            LEFT JOIN answer_master am ON am.question_id = qm.id AND correct_answer=1
            WHERE qm.standard_id = ? AND qm.subject_id = ? AND qm.status = 1
            AND $sub_institute_id_by_lms $extra
            GROUP BY qm.id
            ORDER BY c.chapter_name";

        $questionData = DB::select($sql, [$standard, $subject]);
        $questionData = json_decode(json_encode($questionData), true);

        foreach ($questionData as $key => $val) {
            $lmsquestionmapping_arr = lmsQuestionMappingModel::select(
                    'lms_question_mapping.questionmaster_id',
                    't.name as type_name',
                    't.id as type_id',
                    't1.name as value_name',
                    't1.id as value_id'
                )
                ->join('lms_mapping_type as t', 't.id', 'lms_question_mapping.mapping_type_id')
                ->join('lms_mapping_type as t1', 't1.id', 'lms_question_mapping.mapping_value_id')
                ->where(["questionmaster_id" => $val['id']])
                ->get()
                ->toArray();

            if (count($lmsquestionmapping_arr) > 0) {
                $mapping_html = "";
                $i = 1;
                foreach ($lmsquestionmapping_arr as $lkey => $lval) {
                    $mapping_html .= $i++ . ") " . $lval['type_name'] . " - " . $lval['value_name'] . "<br><br>";
                }
                $questionData[$key]['LMS_MAPPING_DATA'] = $mapping_html;
            } else {
                $questionData[$key]['LMS_MAPPING_DATA'] = "";
            }
        }

        $stdData = [];
        if ($user_profile_name == 'Teacher') {
            $wherecondition = [
                't.sub_institute_id' => $sub_institute_id,
                't.teacher_id' => $user_id,
                't.subject_id' => $subject
            ];
            if ($standard != "") {
                $wherecondition['t.standard_id'] = $standard;
            }

            $stdData = subjectModel::from("timetable as t")
                ->select('sst.display_name', 'sst.subject_id')
                ->join('subject as s', 's.id', '=', 't.subject_id')
                ->join("sub_std_map as sst", function ($join) {
                    $join->on("sst.subject_id", "=", "s.id")
                        ->on("sst.standard_id", "=", "t.standard_id");
                })
                ->where($wherecondition)
                ->groupBy('sst.id')
                ->orderBy('sst.display_name')
                ->get()
                ->toArray();
        } else {
            $subInstWhere = $sub_institute_id ? "(sub_institute_id = $sub_institute_id OR sub_institute_id = 1)" : "1 = 1";
            $stdData = DB::select("SELECT * FROM sub_std_map WHERE standard_id = ? AND subject_id = ? AND $subInstWhere ORDER BY display_name", [$standard, $subject]);
            $stdData = json_decode(json_encode($stdData), true);
        }

        $chapters = DB::table('chapter_master')
            ->where([
                'subject_id' => $subject,
                'standard_id' => $standard,
            ])
            ->when($sub_institute_id, function ($query) use ($sub_institute_id) {
                return $query->where("sub_institute_id", $sub_institute_id);
            })
            ->get()
            ->toArray();

        $chapter_ids = is_array($search_chapter) ? $search_chapter : [];

        $topics = [];
        if (!empty($chapter_ids)) {
            $topics = DB::table('topic_master')
                ->whereIn("chapter_id", $chapter_ids)
                ->when($sub_institute_id, function ($query) use ($sub_institute_id) {
                    return $query->where(['sub_institute_id' => $sub_institute_id]);
                })
                ->get()
                ->toArray();
        }

        $lms_mapping = lmsmappingtypeModel::select('*')
            ->where(['globally' => '1', 'parent_id' => '0'])
            ->get()
            ->toArray();

        $mapping_values = [];
        if (!empty($search_mapping_type)) {
            $mapping_values = DB::table('lms_mapping_type')
                ->select(['id', 'name'])
                ->whereIn("parent_id", $search_mapping_type)
                ->where(['status' => '1'])
                ->get()
                ->toArray();
        }

        return response()->json([
            'status_code' => 1,
            'message' => 'Success',
            'grade_id' => $grade,
            'standard_id' => $standard,
            'subject_id' => $subject,
            'chapter_id' => $chapter_ids,
            'topic_id' => $search_topic,
            'map_type' => $search_mapping_type,
            'map_value' => $search_mapping_value,
            'subjects' => array_map(function($item) { return (array)$item; }, $stdData),
            'questionData' => $questionData,
            'chapters' => array_map(function($item) { return (array)$item; }, $chapters),
            'lms_mapping_type' => $lms_mapping
        ], 200);
    }

    public function getMappingTypes(Request $request): JsonResponse
    {
        $parent_id = $request->input('parent_id');

        $mapping_types = lmsmappingtypeModel::select('*')
            ->where(['parent_id' => $parent_id, 'status' => '1'])
            ->orWhere(function($query) use ($parent_id) {
                $query->where('parent_id', $parent_id)->where('globally', '1');
            })
            ->get()
            ->toArray();

        return response()->json([
            'status_code' => 1,
            'data' => $mapping_types
        ], 200);
    }
}