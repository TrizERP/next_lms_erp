<?php

namespace App\Http\Controllers\learning_outcome\lo_marks_arNar;

// namespace  App\Http\Controllers\learning_outcome\lo_marks_arNar\lo_marks_arNar_controller;


//use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use DB;
use Illuminate\Http\Request;

class lo_marks_arNarController extends Controller
{

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
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

        return \App\Helpers\is_mobile($type, 'learning_outcome/lo_marks_arNar/show', $school_data, 'view');
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
        $str = 'SELECT MEDIUM FROM learning_outcome_pdf GROUP BY MEDIUM';
        $result = DB::select(DB::raw($str));

        $medium = array();
        foreach ($result as $id => $arr) {
            $medium[$arr->MEDIUM] = $arr->MEDIUM;
        }

        $str = 'SELECT STANDARD FROM learning_outcome_pdf GROUP BY STANDARD';
        $result = DB::select(DB::raw($str));

        $std = array();
        foreach ($result as $id => $arr) {
            $std[$arr->STANDARD] = $arr->STANDARD;
        }

        $str = 'SELECT section_id,section_name as DIVISION FROM school_sections';
        $result = DB::select(DB::raw($str));

        $div = array();
        foreach ($result as $id => $arr) {
            $div[$arr->section_id] = $arr->DIVISION;
        }

        $dataStore = array(
            'medium' => $medium,
            'std' => $std,
            'div' => $div,
        );


        return $dataStore;
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create(Request $request)
    {
        // echo('<pre>');
        // print_r($_REQUEST);
        // exit;

        // $from_date = $_REQUEST['examdate'];
        $medium = $_REQUEST['medium'];
        $std = $_REQUEST['std'];
        $lo = $_REQUEST['lo'];
        $subject = $_REQUEST['subject'];

        // $std_con = "";
        // $medium_con = "";
        // if (isset($medium) && $medium != '') {
        //     $medium_con .= " AND MEDIUM = '" . $medium . "'";
        // }
        // if (isset($std) && $std != '') {
        //     $std_con .= " AND STANDARD = '" . $std . "' ";
        // }

        // $standard_condition = "";
        // $division_condision = "";
        
        // if (isset($std) && $std != '') {
        //     $standard_condition .= " AND cs.Course_title = '" . $std . "'";
        // }
        // if (isset($div) && $div != '') {
        //     $division_condision .= " AND se.section_id = '" . $div . "' ";
        // }

        $lo_condition = "";
        if ($lo != '') {
            $lo_condition = " AND INDICATORE_ID = '".$lo."' ";
        }

        $sql = "SELECT *
                FROM learning_outcome_question_master
                WHERE MEDIUM = '" . $medium . "' 
                    AND STANDARD = '" . $std . "' 
                    AND SUBJECT = '" . $subject . "' 
                    AND SYEAR = '".$request->session()->get('syear')."' 
                    $lo_condition
                    ";

        // echo $sql;
        $result = DB::select(DB::raw($sql));
        // echo('<pre>');
        // print_r($result);
        // exit;

        

        $id_arr = array();
        $total = 0;
        foreach ($result as $key => $value) {
            $id_arr[] = $value->ID;
            $total = $total + $value->QUESTION_OUT_OF;
        }
        $ids = implode($id_arr, ',');
        // $getQuestionCases = rtrim($getQuestionCases, ",");
        // $getHaving = rtrim($getHaving, " OR ");
        // echo ('<pre>');
        // print_r($ids);
        // print_r($getQuestionCases);
        // exit;

        
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $syear = $request->session()->get('syear');

        $sql = "
        SELECT 
        concat_ws(' ',s.first_name,s.middle_name,s.last_name) stu_name,
        stds.name,
        lo.INDICATOR,
        if(ROUND((sum(lom.MARKS)*100/sum(li.QUESTION_OUT_OF)),2)<50,'NOT ACHIEVED','ACHIEVED') AR,
        sum(li.QUESTION_OUT_OF) out_of,
        sum(lom.MARKS) got_marks,
        ROUND((sum(lom.MARKS)*100/sum(li.QUESTION_OUT_OF)),2) as per
        FROM tblstudent s
        INNER JOIN tblstudent_enrollment se ON se.student_id = s.id
        INNER JOIN standard stds ON stds.id = se.standard_id
        INNER JOIN division d ON d.id = se.section_id
        INNER JOIN learning_outcome_question_master li ON li.ID IN ($ids)
        INNER JOIN learning_outcome_indicator lo ON lo.ID = li.INDICATORE_ID
        INNER JOIN learning_outcome_student_marks lom ON se.student_id = lom.STUDENT_ID AND li.ID = lom.QUESTION_ID AND lom.sub_institute_id = '$sub_institute_id'
        GROUP BY lo.ID,s.id
        ";

        // echo $sql;
        // exit;

        $students = DB::select(DB::raw($sql));
        // echo ('<pre>');print_r($students);exit;

        // $QUESTION_TITLE = array();
        // foreach ($students as $key => $value) {
        //     if(!in_array($value->QUESTION_TITLE,$QUESTION_TITLE))
        //     // $QUESTION_TITLE[$value->QUESTION_OUT_OF] = $value->QUESTION_TITLE;
        //     $QUESTION_TITLE[$value->QUESTION_TITLE] = $value->QUESTION_OUT_OF;
        // }

        // echo ('<pre>');
        // print_r($students);
        // print_r($QUESTION_TITLE);
        // exit;


        $type = $request->input('type');

        // $dataStore['dd'] = $this->get_all_dd();
        $dataStore['stud'] = $students;
        // $dataStore['questions'] = $QUESTION_TITLE;
        // $dataStore['questions_ids'] = $ids;
        
        // $dataStore['examdate'] = $from_date;
        // $dataStore['medium'] = $medium;
        // $dataStore['std'] = $std;
        // $dataStore['div'] = $div;
        // $dataStore['subject'] = $subject;

        return \App\Helpers\is_mobile($type, 'learning_outcome/lo_marks_arNar/add', $dataStore, 'view');
    }

  

    /**
     * Store a newly created resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        // echo('<pre>');
        // print_r($_REQUEST);
        // exit;

        foreach ($_REQUEST['result'] as $student_id => $value) {
            foreach ($value as $question_id => $mark) {
                if ($mark != '') {
                    $sub_institute_id = $request->session()->get('sub_institute_id');
                    $str = "SELECT * FROM learning_outcome_student_marks 
                            where STUDENT_ID = '" . $student_id . "' 
                            and SUB_INSTITUTE_ID = '" . $sub_institute_id . "' 
                            and MEDIUM = '" . $request->get('medium') . "' 
                            and STANDARD = '" . $request->get('std') . "' 
                            and SUBJECT = '" . $request->get('subject') . "' 
                            AND QUESTION_ID = '" . $question_id . "' 
                            AND DATE = '" . $request->get('examdate') . "'";
                    $result = DB::select(DB::raw($str));

                    if (count($result)==0) {
                        $data = array(
                            'SUB_INSTITUTE_ID' => $request->session()->get('sub_institute_id'),
                            'STUDENT_ID' => $student_id,
                            'MEDIUM' => $request->get('medium'),
                            'STANDARD' => $request->get('std'),
                            'SUBJECT' => $request->get('subject'),
                            'QUESTION_ID' => $question_id,
                            'DATE' => $request->get('examdate'),
                            'CREATED_BY' => $request->session()->get('user_id'),
                            'CREATED_ON' => now(),
                            'CREATED_BY_USER_GROUP_ID' => $request->session()->get('user_group_id'),
                            'SYEAR' =>  $request->session()->get('syear'),
                            'MARKS' => $mark,
                            );
        
                        DB::table('learning_outcome_student_marks')->insert(
                            $data
                        );
                    } else {
                        $where = array(
                            "STUDENT_ID"=>$student_id,
                            "SUB_INSTITUTE_ID"=>$sub_institute_id,
                            "MEDIUM"=>$request->get('medium'),
                            "STANDARD"=> $request->get('std'),
                            "SUBJECT"=>$request->get('subject'),
                            "QUESTION_ID"=>$question_id,
                            "DATE"=>$request->get('examdate'),
                        );

                        $data = array(
                            'SUB_INSTITUTE_ID' => $request->session()->get('sub_institute_id'),
                            'STUDENT_ID' => $student_id,
                            'MEDIUM' => $request->get('medium'),
                            'STANDARD' => $request->get('std'),
                            'SUBJECT' => $request->get('subject'),
                            'QUESTION_ID' => $question_id,
                            'DATE' => $request->get('examdate'),
                            'CREATED_BY' => $request->session()->get('user_id'),
                            'CREATED_ON' => now(),
                            'CREATED_BY_USER_GROUP_ID' => $request->session()->get('user_group_id'),
                            'SYEAR' =>  $request->session()->get('syear'),
                            'MARKS' => $mark,
                            );
        
                        DB::table('learning_outcome_student_marks')
                        ->where($where)
                        ->update($data);
                    }
                }
            }
        }

        // echo('<pre>');
        // print_r($_REQUEST);
        // exit;
  
        $res = array(
            'status_code' => 1,
            'message' => 'Data Saved',
        );

        $type = $request->input('type');

        return \App\Helpers\is_mobile($type, 'lo_marks_arNar.index', $res, 'redirect');

        // echo '<pre>';
        // print_r($request->Code);
        // exit;
    }

    /**
     * Display the specified resource.
     *
     * @param int $id
     *
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param int $id
     *
     * @return \Illuminate\Http\Response
     */
    public function edit(Request $request, $id)
    {
        $all_dd = $this->get_all_dd();

        // $allData = lo_marks_arNar::
        //     where(['SubInstituteId' => $sub_institute_id])
        //     ->get()->toArray();

        $str = 'SELECT * FROM learning_outcome_indicator WHERE ID = '.$id;
        $allData = DB::select(DB::raw($str));

        // $allData = lo_marks_arNar::find($id)->toArray();
        // echo('<pre>');
        // print_r($allData);
        // exit;

        $standard = $allData[0]->STANDARD;
        $medium = $allData[0]->MEDIUM;

        $where = array(
            'learning_outcome_pdf.standard' => $standard,
            'learning_outcome_pdf.medium' => $medium,
        );

        $std_sub_map = DB::table('learning_outcome_pdf')
            ->where($where)
            ->pluck('learning_outcome_pdf.DISPLAY_SUBJECT', 'learning_outcome_pdf.SUBJECTS');

        $data = array(
            'medium' => $all_dd['medium'],
            'std' => $all_dd['std'],
            'selected_medium' => $allData[0]->MEDIUM,
            'selected_std' => $allData[0]->STANDARD,
            'selected_subject' => $allData[0]->SUBJECT,
            'learning_outcome' => $allData[0]->INDICATOR,
            'subject' => $std_sub_map,
            'id' => $id,

        );
        // echo ('<pre>');print_r($data);exit;

        // $sub_institute_id = session()->get('sub_institute_id');
        $type = $request->input('type');

        // $data['ddValue'] = $ddvalue;
        return \App\Helpers\is_mobile($type, "learning_outcome/lo_marks_arNar/edit", $data, "view");
    }

    /**
     * Update the specified resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @param int                      $id
     *
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $data = array(
            'MEDIUM' => $request->get('medium'),
            'STANDARD' => $request->get('std'),
            'SUBJECT' => $request->get('subject'),
            'INDICATOR' => $request->get('learning_outcome'),
            'UPDATED_AT' => now(),
            'UPDATED_BY' => $request->session()->get('user_id'),
        );

        DB::table('learning_outcome_indicator')
        ->where(["ID" => $id])
        ->update($data);
        
        $res = array(
            "status_code" => 1,
            "message" => "Data Saved",
        );
        $type = $request->input('type');

        return \App\Helpers\is_mobile($type, "lo_marks_arNar.index", $res, "redirect");
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param int $id
     *
     * @return \Illuminate\Http\Response
     */
    public function destroy(Request $request, $id)
    {
        $type = $request->input('type');

        DB::table('learning_outcome_indicator')
        ->where(["ID" => $id])
        ->delete();

        $res = array(
            "status_code" => 1,
            "message" => "Data Deleted",
        );

        return \App\Helpers\is_mobile($type, "lo_marks_arNar.index", $res, "redirect");
    }
}
