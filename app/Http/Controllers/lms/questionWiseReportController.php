<?php

namespace App\Http\Controllers\lms;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use App\Models\lms\questionpaperModel;
use App\Models\school_setup\sub_std_mapModel;

use function App\Helpers\is_mobile;

class questionWiseReportController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index(Request $request)
    {
        $type = $request->input('type');
        $res['status_code'] = 1;
        $res['message'] = "Success";
        $res['subject_data'] = array();
        $res['exams_data'] = array();
        return is_mobile($type, "student/question_wise_report/show_question_wise_report", $res, "view");
    }

    /**
     * show_question_wise_report
     */
    public function show_question_wise_report(Request $request)
    {

        $type = $request->input('type');
        $grade = $request->grade;
        $standard = $request->standard;
        $division = $request->division;
        $subject = $request->subject;
        $order_by = $request->order_by;
        $question_paper_id = $request->exam;
        $sub_institute_id = session()->get('sub_institute_id');
        $syear = session()->get('syear');
        $marking_period_id = session()->get('term_id');
        // return $request;exit;
        $examData = questionpaperModel::where([
            'sub_institute_id' => $sub_institute_id, 'standard_id' => $standard, 'subject_id' => $subject,
        ])
            ->where('id', $question_paper_id)
            ->orderby('id')
            ->get();
        $queryResult = DB::table('tblstudent as ts')
            ->join('tblstudent_enrollment as tse', function ($join) {
                $join->whereRaw('tse.student_id = ts.id');
            })->join('standard as std', function ($join) use($marking_period_id){
                $join->whereRaw('std.id = tse.standard_id');
                // ->when($marking_period_id,function($query) use($marking_period_id){
                //     $query->where('std.marking_period_id',$marking_period_id);
                // });
            })->join('std_div_map as sdm', function ($join) {
                $join->whereRaw('sdm.standard_id = std.id');
            })->join('division as divi', function ($join) {
                $join->whereRaw('divi.id = sdm.division_id');
            })->join('sub_std_map as ssm', function ($join) {
                $join->whereRaw('ssm.standard_id = sdm.standard_id');
            })->join('subject as sub', function ($join) {
                $join->whereRaw('sub.id = ssm.subject_id');
            })->join('question_paper as qp', function ($join) {
                $join->whereRaw('qp.subject_id = ssm.subject_id and qp.standard_id = sdm.standard_id');
            })->join('lms_question_master as lqm', function ($join) {
                $join->whereRaw('lqm.subject_id = ssm.subject_id
                    and lqm.standard_id = sdm.standard_id
                    and lqm.id in (
                        SELECT
                            lqm.id
                        FROM
                            lms_question_master as lqm,
                            question_paper as qp2
                        WHERE
                            qp.id = qp2.id
                            AND FIND_IN_SET(lqm.id, qp.question_ids)
                    )');
            })->join('lms_online_exam_answer as am', function ($join) use ($question_paper_id) {
                $join->whereRaw("am.question_paper_id = qp.id and am.student_id = ts.id and am.question_id = lqm.id  
                    AND am.online_exam_id = (SELECT lo.id FROM lms_online_exam lo WHERE lo.question_paper_id = $question_paper_id 
                    AND lo.student_id = ts.id and lo.total_right = ( SELECT MAX(total_right)
                                    FROM lms_online_exam
                                    WHERE question_paper_id = $question_paper_id
                                    AND student_id = ts.id
                                    AND question_id = lqm.id) ORDER BY id DESC LIMIT 1)");
            })->selectRaw("ts.id,CONCAT_WS(' ',ts.first_name,ts.middle_name,ts.last_name) as student_name,ts.roll_no,
                std.name as standerd_name,divi.name as division_name,sub.subject_name as subject_name,qp.paper_name as 
                question_paper_name,qp.total_ques as total_question,qp.id as question_paper_id,lqm.id as question_id,
                am.online_exam_id as online_exam_id,lqm.question_title as questions,am.ans_status as ans_status")
            ->where([
                'std.grade_id'         => $grade,
                'std.id'               => $standard,
                'tse.section_id'       => $division,
                'sdm.division_id'      => $division,
                'sub.id'               => $subject,
                'ts.sub_institute_id'  => $sub_institute_id,
                'tse.syear'            => $syear,
                'tse.sub_institute_id' => $sub_institute_id,
                'qp.id'                => $question_paper_id,
                'qp.syear'             => $syear,
            ])->orderBy('ts.roll_no')->get()->toArray();
// DB::enableQueryLog();
    //     $queryResult =  DB::table('tblstudent as ts')
    //         ->join('tblstudent_enrollment as tse', function ($join) {
    //             $join->whereRaw('tse.student_id = ts.id');
    //         })->join('standard as std', function ($join) {
    //             $join->whereRaw('std.id = tse.standard_id');
    //         })->join('std_div_map as sdm', function ($join) {
    //             $join->whereRaw('sdm.standard_id = std.id');
    //         })->join('division as divi', function ($join) {
    //             $join->whereRaw('divi.id = sdm.division_id');
    //         })->join('sub_std_map as ssm', function ($join) {
    //             $join->whereRaw('ssm.standard_id = sdm.standard_id');
    //         })->join('subject as sub', function ($join) {
    //             $join->whereRaw('sub.id = ssm.subject_id');
    //         })->join('question_paper as qp', function ($join) {
    //             $join->whereRaw('qp.subject_id = ssm.subject_id and qp.standard_id = sdm.standard_id');
    //         })->join('lms_question_master as lqm', function ($join) {
    //             $join->whereRaw('lqm.subject_id = ssm.subject_id
    //     and lqm.standard_id = sdm.standard_id
    //     and lqm.id in (
    //         SELECT
    //             lqm.id
    //         FROM
    //             lms_question_master as lqm,
    //             question_paper as qp2
    //         WHERE
    //             qp.id = qp2.id
    //             AND FIND_IN_SET(lqm.id, qp.question_ids)
    //     )');
    //         })
    //         ->join('lms_online_exam_answer as am', function ($join) use ($question_paper_id) {
    //             $join->whereRaw("am.question_paper_id = qp.id and am.student_id = ts.id and am.question_id = lqm.id  
    //         AND am.online_exam_id = (
    //             SELECT lo.id
    //             FROM lms_online_exam lo
    //             WHERE lo.question_paper_id = $question_paper_id 
    //             AND lo.student_id = ts.id
    //             AND lo.obtain_marks = (
    //                 SELECT MAX(total_right)
    //                 FROM lms_online_exam
    //                 WHERE question_paper_id = $question_paper_id
    //                 AND student_id = ts.id
    //                 AND question_id = lqm.id
    //             )
    //             ORDER BY id DESC
    //             LIMIT 1
    //         )");
    //         })->selectRaw("ts.id,CONCAT_WS(' ',ts.first_name,ts.middle_name,ts.last_name) as student_name,ts.roll_no,
    // std.name as standerd_name,divi.name as division_name,sub.subject_name as subject_name,qp.paper_name as 
    // question_paper_name,qp.total_ques as total_question,qp.id as question_paper_id,lqm.id as question_id,
    // am.online_exam_id as online_exam_id,lqm.question_title as questions,am.ans_status as ans_status")
    //         ->where([
    //             'std.grade_id'         => $grade,
    //             'std.id'               => $standard,
    //             'tse.section_id'       => $division,
    //             'sdm.division_id'      => $division,
    //             'sub.id'               => $subject,
    //             'ts.sub_institute_id'  => $sub_institute_id,
    //             'tse.syear'            => $syear,
    //             'tse.sub_institute_id' => $sub_institute_id,
    //             'qp.id'                => $question_paper_id,
    //             'qp.syear'             => $syear,
    //         ])
    //         ->orderBy('ts.roll_no')
    //         ->get()
    //         ->toArray();
// dd(DB::getQueryLog($queryResult));
        if ($queryResult) {
            $resultArr = [];
            foreach ($queryResult as $result) {
                $online_exam_id = $result->online_exam_id;
                $question_paper_id = $result->question_paper_id;

                if (!isset($resultArr[$question_paper_id])) {
                    $resultArr[$question_paper_id][$result->id][$online_exam_id][] = $result;
                } else {
                    if (isset($resultArr[$question_paper_id][$result->id])) {
                        $resultArr[$question_paper_id][$result->id][$online_exam_id][] = $result;
                    } else {
                        $resultArr[$question_paper_id][$result->id][$online_exam_id][] = $result;
                    }
                }
            }
        }

        $standard_name = DB::table('standard')->select('name')->where('id', $standard)->first();
        $division_name = DB::table('division')->select('name')->where('id', $division)->first();
        $subject_name = DB::table('subject')->select('subject_name')->where('id', $subject)->first();
        $subject_data = sub_std_mapModel::where(['sub_institute_id' => $sub_institute_id, 'standard_id' => $standard])
        ->orderBy('display_name')->get()->toArray();
        if (!empty($resultArr)) {
            $res['results'] = $resultArr;
        }
        $res['grade_id'] = $grade;
        $res['standard_id'] = $standard;
        $res['division_id'] = $division;
        $res['subject_id'] = $subject;
        $res['exam_id'] = $question_paper_id;
        $res['exams_data'] = $examData;
        $res['subject_data'] = $subject_data;
        if ($subject_name) {
            $res['subject_name'] = $subject_name->subject_name;
        }
        if ($standard_name) {
            $res['standard_name'] = $standard_name->name;
        }
        if ($division_name) {
            $res['division_name'] = $division_name->name;
        }

        return is_mobile($type, "student/question_wise_report/show_question_wise_report", $res, "view");
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return void
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  Request  $request
     * @return void
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return void
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return void
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  Request  $request
     * @param  int  $id
     * @return void
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return void
     */
    public function destroy($id)
    {
        //
    }
}
