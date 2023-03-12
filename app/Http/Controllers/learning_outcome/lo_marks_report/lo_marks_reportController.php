<?php

namespace App\Http\Controllers\learning_outcome\lo_marks_report;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use function App\Helpers\is_mobile;


class lo_marks_reportController extends Controller
{

    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index(Request $request)
    {
        if (session()->has('data')) { // check if it exists
            $data_arr = session('data'); // to retrieve value
            if (isset($data_arr['message'])) {
                $school_data['message'] = $data_arr['message'];
            }
        }

        $school_data['data'] = $this->get_all_dd();


        $type = $request->input('type');

        return is_mobile($type, 'learning_outcome/lo_marks_report/show', $school_data, 'view');
    }

    public function getData()
    {
        $data = DB::table('learning_outcome_indicator')->get();
        $i = 1;
        foreach ($data as $key => $arr) {
            $arr->SrNo = $i;
            $i++;
        }

        return $data;
    }

    public function get_all_dd()
    {
        $result = DB::table("learning_outcome_pdf")
            ->selectRaw('MEDIUM')
            ->groupBy('MEDIUM')
            ->get()->toArray();

        $medium = [];
        foreach ($result as $id => $arr) {
            $medium[$arr->MEDIUM] = $arr->MEDIUM;
        }

        $result = DB::table("learning_outcome_pdf")
            ->selectRaw('STANDARD')
            ->groupBy('STANDARD')
            ->get()->toArray();

        $std = [];
        foreach ($result as $id => $arr) {
            $std[$arr->STANDARD] = $arr->STANDARD;
        }

        $result = DB::table("school_sections")
            ->selectRaw('section_id,section_name as DIVISION')
            ->get()->toArray();

        $div = [];
        foreach ($result as $id => $arr) {
            $div[$arr->section_id] = $arr->DIVISION;
        }

        return [
            'medium' => $medium,
            'std'    => $std,
            'div'    => $div,
        ];
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return Response
     */
    public function create(Request $request)
    {
        $from_date = $_REQUEST['examdate'];
        $medium = $_REQUEST['medium'];
        $std = $_REQUEST['std'];
        $div = $_REQUEST['div'];
        $subject = $_REQUEST['subject'];

        $std_con = "";
        $medium_con = "";
        if (isset($medium) && $medium != '') {
            $medium_con .= " AND MEDIUM = '".$medium."'";
        }
        if (isset($std) && $std != '') {
            $std_con .= " AND STANDARD = '".$std."' ";
        }

        $standard_condition = "";
        $division_condision = "";

        if (isset($std) && $std != '') {
            $standard_condition .= " AND cs.Course_title = '".$std."'";
        }
        if (isset($div) && $div != '') {
            $division_condision .= " AND se.section_id = '".$div."' ";
        }

        $result = DB::table("learning_outcome_question_master")
            ->where("MEDIUM", "=", $medium)
            ->where("STANDARD", "=", $std)
            ->where("SUBJECT", "=", $subject)
            ->where("SYEAR", "=", $request->session()->get('syear'))
            ->where("DATE", "=", $from_date)
            ->get()->toArray();

        $id_arr = [];
        $QUESTION_TITLE = [];
        $getQuestionCases = '';
        $getHaving = ' ';
        foreach ($result as $key => $value) {
            $id_arr[] = $value->ID;
            $getQuestionCases .= "SUM(CASE WHEN ID = '".$value->ID."' THEN MARKS ELSE NULL END) AS 'QID".$value->ID."',";
            $getHaving .= " QID".$value->ID." IS NOT NULL OR ";
            $name = $value->QUESTION_TITLE.'--'.$value->ID;
            $QUESTION_TITLE[$name] = $value->QUESTION_OUT_OF;
        }
        $ids = implode(',', $id_arr);
        $getQuestionCases = rtrim($getQuestionCases, ",");
        $getHaving = rtrim($getHaving, " OR ");

        $sub_institute_id = $request->session()->get('sub_institute_id');
        $syear = $request->session()->get('syear');

        $sql = "
        SELECT *, SUM(MARKS) AS total,".$getQuestionCases." FROM(
        select CONCAT_WS(' ',s.last_name,s.first_name,s.middle_name) AS name,
        s.enrollment_no as roll_no,stds.name AS STD,se.student_id,li.QUESTION_TITLE,li.QUESTION_OUT_OF,lo.MARKS,li.ID
        from tblstudent s
        inner join tblstudent_enrollment se on se.student_id = s.id
        inner join standard stds on stds.id = se.standard_id
        inner join division d on d.id = se.section_id
        INNER JOIN learning_outcome_question_master li ON li.ID IN ($ids)
        LEFT JOIN learning_outcome_student_marks lo ON se.student_id = lo.STUDENT_ID 
            AND li.ID = lo.QUESTION_ID AND lo.DATE = '$from_date' 
            AND lo.sub_institute_id = '$sub_institute_id'
        WHERE se.syear = $syear AND s.sub_institute_id = '$sub_institute_id'
            AND (stds.name like '%$std' and stds.name not like '%1$std')
            AND d.name = '$div'
        GROUP BY se.student_id,li.ID) as a
        GROUP BY a.student_id
        HAVING $getHaving
        ORDER BY CAST(a.roll_no AS SIGNED)
        ";

        $students = DB::select(DB::raw($sql));

        $type = $request->input('type');

        $dataStore['dd'] = $this->get_all_dd();
        $dataStore['stud'] = $students;
        $dataStore['questions'] = $QUESTION_TITLE;
        $dataStore['questions_ids'] = $ids;

        $dataStore['examdate'] = $from_date;
        $dataStore['medium'] = $medium;
        $dataStore['std'] = $std;
        $dataStore['div'] = $div;
        $dataStore['subject'] = $subject;

        return is_mobile($type, 'learning_outcome/lo_marks_report/add', $dataStore, 'view');
    }


    /**
     * Store a newly created resource in storage.
     *
     * @param  Request  $request
     *
     * @return Response
     */
    public function store(Request $request)
    {
        foreach ($_REQUEST['result'] as $student_id => $value) {
            foreach ($value as $question_id => $mark) {
                if ($mark != '') {
                    $sub_institute_id = $request->session()->get('sub_institute_id');
                    $result = DB::table("learning_outcome_student_marks")
                        ->where("STUDENT_ID", "=", $student_id)
                        ->where("SUB_INSTITUTE_ID", "=", $sub_institute_id)
                        ->where("MEDIUM", "=", $request->get('medium'))
                        ->where("STANDARD", "=", $request->get('std'))
                        ->where("SUBJECT", "=", $request->get('subject'))
                        ->where("QUESTION_ID", "=", $question_id)
                        ->where("DATE", "=", $request->get('examdate'))
                        ->get()->toArray();

                    if (count($result) == 0) {
                        $data = [
                            'SUB_INSTITUTE_ID'         => $request->session()->get('sub_institute_id'),
                            'STUDENT_ID'               => $student_id,
                            'MEDIUM'                   => $request->get('medium'),
                            'STANDARD'                 => $request->get('std'),
                            'SUBJECT'                  => $request->get('subject'),
                            'QUESTION_ID'              => $question_id,
                            'DATE'                     => $request->get('examdate'),
                            'CREATED_BY'               => $request->session()->get('user_id'),
                            'CREATED_ON'               => now(),
                            'CREATED_BY_USER_GROUP_ID' => $request->session()->get('user_group_id'),
                            'SYEAR'                    => $request->session()->get('syear'),
                            'MARKS'                    => $mark,
                        ];

                        DB::table('learning_outcome_student_marks')->insert(
                            $data
                        );
                    } else {
                        $where = [
                            "STUDENT_ID"       => $student_id,
                            "SUB_INSTITUTE_ID" => $sub_institute_id,
                            "MEDIUM"           => $request->get('medium'),
                            "STANDARD"         => $request->get('std'),
                            "SUBJECT"          => $request->get('subject'),
                            "QUESTION_ID"      => $question_id,
                            "DATE"             => $request->get('examdate'),
                        ];

                        $data = [
                            'SUB_INSTITUTE_ID'         => $request->session()->get('sub_institute_id'),
                            'STUDENT_ID'               => $student_id,
                            'MEDIUM'                   => $request->get('medium'),
                            'STANDARD'                 => $request->get('std'),
                            'SUBJECT'                  => $request->get('subject'),
                            'QUESTION_ID'              => $question_id,
                            'DATE'                     => $request->get('examdate'),
                            'CREATED_BY'               => $request->session()->get('user_id'),
                            'CREATED_ON'               => now(),
                            'CREATED_BY_USER_GROUP_ID' => $request->session()->get('user_group_id'),
                            'SYEAR'                    => $request->session()->get('syear'),
                            'MARKS'                    => $mark,
                        ];

                        DB::table('learning_outcome_student_marks')
                            ->where($where)
                            ->update($data);
                    }
                }
            }
        }

        $res = [
            'status_code' => 1,
            'message'     => 'Data Saved',
        ];

        $type = $request->input('type');

        return is_mobile($type, 'lo_marks_report.index', $res, 'redirect');
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     *
     * @return void
     */
    public function show($id)
    {
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     *
     * @return Response
     */
    public function edit(Request $request, $id)
    {
        $all_dd = $this->get_all_dd();

        $allData = DB::table("learning_outcome_indicator")
            ->where("ID", "=", $id)
            ->get()->toArray();

        $standard = $allData[0]->STANDARD;
        $medium = $allData[0]->MEDIUM;

        $where = [
            'learning_outcome_pdf.standard' => $standard,
            'learning_outcome_pdf.medium'   => $medium,
        ];

        $std_sub_map = DB::table('learning_outcome_pdf')
            ->where($where)
            ->pluck('learning_outcome_pdf.DISPLAY_SUBJECT', 'learning_outcome_pdf.SUBJECTS');

        $data = [
            'medium'           => $all_dd['medium'],
            'std'              => $all_dd['std'],
            'selected_medium'  => $allData[0]->MEDIUM,
            'selected_std'     => $allData[0]->STANDARD,
            'selected_subject' => $allData[0]->SUBJECT,
            'learning_outcome' => $allData[0]->INDICATOR,
            'subject'          => $std_sub_map,
            'id'               => $id,
        ];

        $type = $request->input('type');

        return is_mobile($type, "learning_outcome/lo_marks_report/edit", $data, "view");
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  Request  $request
     * @param  int  $id
     *
     * @return Response
     */
    public function update(Request $request, $id)
    {
        $data = [
            'MEDIUM'     => $request->get('medium'),
            'STANDARD'   => $request->get('std'),
            'SUBJECT'    => $request->get('subject'),
            'INDICATOR'  => $request->get('learning_outcome'),
            'UPDATED_AT' => now(),
            'UPDATED_BY' => $request->session()->get('user_id'),
        ];

        DB::table('learning_outcome_indicator')
            ->where(["ID" => $id])
            ->update($data);

        $res = [
            "status_code" => 1,
            "message"     => "Data Saved",
        ];

        $type = $request->input('type');

        return is_mobile($type, "lo_marks_report.index", $res, "redirect");
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     *
     * @return Response
     */
    public function destroy(Request $request, $id)
    {
        $type = $request->input('type');

        DB::table('learning_outcome_indicator')
            ->where(["ID" => $id])
            ->delete();

        $res = [
            "status_code" => 1,
            "message"     => "Data Deleted",
        ];

        return is_mobile($type, "lo_marks_report.index", $res, "redirect");
    }
}
