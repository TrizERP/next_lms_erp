<?php

namespace App\Http\Controllers\student;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use function App\Helpers\is_mobile;
use DB;

class questionWiseReportController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        //
        $type = $request->input('type');
        // $tblcustom_fields = $this->customFields($request);
        // dd($tblcustom_fields);
        $res['status_code'] = 1;
        $res['message'] = "Success";
        // $res['data'] = $tblcustom_fields;

        return is_mobile($type, "student/question_wise_report/show_question_wise_report", $res, "view");
    }

    /**
     * show_question_wise_report
     */
    public function show_question_wise_report(Request $request) {

        $type = $request->input('type');
        $grade = $request->grade;
        $standard = $request->standard;
        $division = $request->division;
        $subject = $request->subject;
        $order_by = $request->order_by;
        $question_paper_id = $request->exam;
        $sub_institute_id = session()->get('sub_institute_id');
        $syear = session()->get('syear');

        $where = "where std.grade_id = $grade
            and std.id = $standard
            and tse.section_id = $division 
            and sdm.division_id= $division
            and sub.id = $subject
            and ts.sub_institute_id = $sub_institute_id
            and tse.syear = $syear
            and tse.sub_institute_id = $sub_institute_id
            and qp.id = $question_paper_id 
            and qp.syear = $syear

        ";

        // DB::enableQueryLog();
        $queryResult = DB::select("
        select
            ts.id,
            CONCAT_WS(' ',ts.first_name,ts.middle_name,ts.last_name) as student_name,
            ts.roll_no,
            std.name as standerd_name,
            divi.name as division_name,
            sub.subject_name as subject_name,
            qp.paper_name as question_paper_name,
            qp.total_ques as total_question,
            qp.id as question_paper_id,
            lqm.id as question_id,
            am.online_exam_id as online_exam_id,
            lqm.question_title as questions,
            am.ans_status as ans_status
        from
            tblstudent as ts
            inner join tblstudent_enrollment as tse on tse.student_id = ts.id 
            inner join standard as std on std.id = tse.standard_id
            inner join std_div_map as sdm on sdm.standard_id = std.id
            inner join division as divi on divi.id = sdm.division_id
            inner join sub_std_map as ssm on ssm.standard_id = sdm.standard_id
            inner join subject as sub on sub.id = ssm.subject_id
            inner join question_paper as qp on qp.subject_id = ssm.subject_id
            and qp.standard_id = sdm.standard_id
            inner join lms_question_master as lqm on lqm.subject_id = ssm.subject_id
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
            )
            inner join lms_online_exam_answer as am on am.question_paper_id = qp.id and am.student_id = ts.id and am.question_id = lqm.id  AND am.online_exam_id = (SELECT lo.id FROM lms_online_exam lo WHERE lo.question_paper_id = $question_paper_id AND lo.student_id = ts.id ORDER BY id DESC LIMIT 1)
        $where
        ORDER BY ts.roll_no
        ");
        //echo "<pre>"; print_r(()); exit;

        if ( $queryResult ) {
            $resultArr = [];
            foreach ( $queryResult as $result ) {
                $online_exam_id = $result->online_exam_id;
                $question_paper_id = $result->question_paper_id;

                if ( !isset($resultArr[$question_paper_id]) ) {
                    $resultArr[$question_paper_id][$result->id][$online_exam_id][] = $result;
                } else {
                    if ( isset($resultArr[$question_paper_id][$result->id]) ) {
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
        // echo "<pre>"; print_r($subject_name->subject_name); exit;
        
        if (!empty($resultArr)) {
            $res['results'] = $resultArr;
        }
        $res['grade_id'] = $grade;
        $res['standard_id'] = $standard;
        $res['division_id'] = $division;
        $res['subject_id'] = $subject;
        $res['question_paper_id'] = $question_paper_id;
        if ( $subject_name ) {
            $res['subject_name'] = $subject_name->subject_name;
        }
        if ( $standard_name ) {
            $res['standard_name'] = $standard_name->name;
        }
        if ( $division_name ) {
            $res['division_name'] = $division_name->name;
        }

        return is_mobile($type, "student/question_wise_report/show_question_wise_report", $res, "view");
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }
}
