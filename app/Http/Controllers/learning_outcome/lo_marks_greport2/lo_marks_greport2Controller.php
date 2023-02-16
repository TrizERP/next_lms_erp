<?php

namespace App\Http\Controllers\learning_outcome\lo_marks_greport2;
use Illuminate\Support\Facades\Storage; 

// namespace  App\Http\Controllers\learning_outcome\lo_marks_greport2\lo_marks_greport2_controller;


//use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use DB;
use Illuminate\Http\Request;
use function App\Helpers\getStudents;

class lo_marks_greport2Controller extends Controller
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

        if (isset($request['id'])) {
            // echo ('<pre>');print_r($request->session());exit;
            // $school_data['data'] = $this->get_all_dd();

            $exam_type = $request->session()->get('exam_type');
            $medium = $request->session()->get('medium');
            $std = $request->session()->get('std');
            $div = $request->session()->get('div');
            // echo ('<pre>');print_r($request->session());exit;
            // echo ('<pre>');print_r($exam_type);exit;

            $student_id_arr = array(
                0=>$request['id']
            );
            $student = \App\Helpers\getStudents($student_id_arr);
            $student = $student[$request['id']];
            // echo ('<pre>');print_r($student);exit;


            $standard = $request->session()->get('std');
            $medium = $request->session()->get('medium');
    
            $where = array(
            'learning_outcome_pdf.standard' => $standard,
            'learning_outcome_pdf.medium' => $medium,
            );
            // echo ('<pre>');print_r($where);exit;
    
            $sub_dd = DB::table('learning_outcome_pdf')
                        ->where($where)
                        ->pluck('learning_outcome_pdf.DISPLAY_SUBJECT', 'learning_outcome_pdf.SUBJECTS');
            // echo ('<pre>');print_r($sub_dd);exit;

            $dd = $this->get_all_dd();

            $per_arr = array(
                'RED' => 40,
                'YELLOW' => 70,
                'GREEN' => 100
            );

            $all_final_data = array();
            $all_subject_lo = array();
            $line_chart_data = array();
            foreach ($sub_dd as $keys => $subject) {
                // $subject = "English";
                $sql = "select LO.SUBJECT,LO.INDICATOR,LQ.QUESTION_TITLE,sum(LQ.QUESTION_OUT_OF) tot,sum(LOM.MARKS) mar,(sum(LOM.MARKS)*100/sum(LQ.QUESTION_OUT_OF)) per
                from learning_outcome_indicator LO 
                inner join  learning_outcome_question_master LQ on LO.ID = LQ.INDICATORE_ID
                inner join learning_outcome_student_marks LOM on LOM.QUESTION_ID = LQ.ID 
                where LOM.STUDENT_ID = '".$request['id']."' 
                and LQ.EXAM_TYPE = '$exam_type' 
                and LQ.medium = '$medium'
                and LQ.standard = '$std'
                and LQ.subject = '$subject'
                group by LO.INDICATOR
                ";
                // echo $sql;
                // exit;
            
                $result = DB::select(DB::raw($sql));

                $all_data = array();
                foreach ($result as $key => $value) {
                    // if (!isset($all_data[$subject]['TOT'])) {
                    //     $all_data[$subject]['TOT'] = 0;
                    // }
                    // if (!isset($all_data[$subject]['MAR'])) {
                    //     $all_data[$subject]['MAR'] = 0;
                    // }
                    
                    $all_data[$subject][$value->INDICATOR] = number_format($value->per, 2);
                    // $all_data[$subject][$value->INDICATOR]['PER'] = number_format($value->per, 2);
                    // $all_data[$subject]['TOT'] = $value->tot + $all_data[$subject]['TOT'];
                    // $all_data[$subject]['MAR'] = $value->mar + $all_data[$subject]['MAR'];
                    // foreach ($per_arr as $color => $per) {
                    //     if ($value->per <= $per) {
                    //         $all_data[$subject][$value->INDICATOR]['COLOR'] = $color;
                    //     break;
                    //     }
                    // }
                }
                
                // echo ('<pre>');print_r($all_data);exit;
                $all_final_data[$subject] = $all_data[$subject];

                
                // foreach ($all_data[$subject] as $key => $value) {
                //     if ($value['COLOR'] == 'RED') {
                //         $all_final_data[$subject]['RED']['lo'][] = $key;
                //     }
                //     if ($value['COLOR'] == 'YELLOW') {
                //         $all_final_data[$subject]['YELLOW']['lo'][] = $key;
                //     }
                //     if ($value['COLOR'] == 'GREEN') {
                //         $all_final_data[$subject]['GREEN']['lo'][] = $key;
                //     }
                // }
                

                // $totla_lo = count($all_data[$subject]);
                // $totla_lo = $totla_lo -2 ;
                
                // if (isset($all_final_data[$subject]['RED'])) {
                //     $all_final_data[$subject]['RED']['per'] = count($all_final_data[$subject]['RED']['lo'])*100/$totla_lo;
                // } else {
                //     $all_final_data[$subject]['RED']['per'] = 0;
                //     $all_final_data[$subject]['RED']['lo'] = array();
                // }
                // if (isset($all_final_data[$subject]['YELLOW'])) {
                //     $all_final_data[$subject]['YELLOW']['per'] = count($all_final_data[$subject]['YELLOW']['lo'])*100/$totla_lo;
                // } else {
                //     $all_final_data[$subject]['YELLOW']['per'] = 0;
                //     $all_final_data[$subject]['YELLOW']['lo'] = array();
                // }
                // if (isset($all_final_data[$subject]['GREEN'])) {
                //     $all_final_data[$subject]['GREEN']['per'] = count($all_final_data[$subject]['GREEN']['lo'])*100/$totla_lo;
                
                // } else {
                //     $all_final_data[$subject]['GREEN']['per'] = 0;
                //     $all_final_data[$subject]['GREEN']['lo'] = array();
                // }
                // $all_final_data[$subject]['tot'] = $all_data[$subject]['TOT'];
                // $all_final_data[$subject]['mar'] = $all_data[$subject]['MAR'];

                // unset($all_data[$subject]['MAR']);
                // unset($all_data[$subject]['TOT']);
                // $all_subject_lo[$subject] = $all_data[$subject];


                $sql = "select LO.SUBJECT,LO.INDICATOR,LQ.EXAM_CODE,SUM(LQ.QUESTION_OUT_OF) tot, SUM(LOM.MARKS) mar,(SUM(LOM.MARKS)*100/ SUM(LQ.QUESTION_OUT_OF)) per
                from learning_outcome_indicator LO 
                inner join  learning_outcome_question_master LQ on LO.ID = LQ.INDICATORE_ID
                inner join learning_outcome_student_marks LOM on LOM.QUESTION_ID = LQ.ID 
                where LOM.STUDENT_ID = '".$request['id']."' 
                and LQ.EXAM_TYPE = '$exam_type' 
                and LQ.medium = '$medium'
                and LQ.standard = '$std'
                and LQ.subject = '$subject'
                GROUP BY LQ.EXAM_CODE
                order by LQ.ID
                ";
                // echo $sql;
                // exit;
            
                $result = DB::select(DB::raw($sql));

                
                foreach ($result as $key => $value) {
                    $line_chart_data[$subject][$value->EXAM_CODE] = number_format($value->per, 2);
                }
            }
            // $json_data = json_encode($line_chart_data);
            $linechart_json = "";
            foreach ($line_chart_data as $subject => $value) {
                $linechart_json .= "{";
                $linechart_json .= "name:'".$subject."',";
                $linechart_json .= "data:[";
                foreach ($value as $exam => $per) {
                    $linechart_json .= $per.",";
                }
                $linechart_json = rtrim($linechart_json, ',');
                $linechart_json .= "]";
                $linechart_json .= "},";
            }

            //getting total marks

            // $total_subject_mark = 0;
            // $get_subject_mark = 0;
            // foreach ($all_final_data as $subject => $arr) {
            //     $total_subject_mark = $total_subject_mark + $arr['tot'];
            //     $get_subject_mark = $get_subject_mark + $arr['mar'];
            // }
            // $final_subject_marks = $get_subject_mark."/".$total_subject_mark;

            $final_subject_marks=0;


            // echo('<pre>');
            // print_r($final_subject_marks);
            // exit;
            // echo('<pre>');
            // print_r($all_data);
            // print_r($all_final_data);
            // exit;


            // echo ('<pre>');print_r($sub_dd);exit;

            $round_chart = '{
                "name": "variants",
                "children": [ ';
            foreach ($all_final_data as $subject => $arr) {
                $round_chart .= "{";
                $round_chart .= '  "name": "'.$subject.'-asd",
                        "children": [
                                ';
                foreach ($arr as $lo => $per) {
                    $string = $lo." - ".$per;
                    $round_chart .= '{ "name": ';
                    $round_chart .= '"'.$string.'"';
                    $round_chart .= ', "size": 5 },';
                    // { "name":
                }
                $round_chart = rtrim($round_chart, ',');

                $round_chart .= "   ]
                                },";
            }
            $round_chart = rtrim($round_chart, ',');
            $round_chart .= ' ]
                } ';
            Storage::disk('public')->put('data.json', ($round_chart));
            // echo('<pre>');
            // print_r($round_chart);
            // exit;

            $school_data['data'] = array(
                'student_info' => $student,
                'selected_exam_type' => $exam_type,
                'all_dd' => $dd,
                'subject_dd' => $sub_dd,
                'triangle'  => $all_final_data,
                // 'triangle'  => $all_data,
                'all_subject_lo'  => $all_subject_lo,
                'line_chart_data'  => $linechart_json,
                'total_subject_marks'  => $final_subject_marks,
            );
            // echo('<pre>');
            // print_r($school_data);
            // exit;
            $type = $request->input('type');
            return \App\Helpers\is_mobile($type, 'learning_outcome/lo_marks_greport2/FinalShow', $school_data, 'view');
        } else {
            $school_data['data'] = $this->get_all_dd();
            $type = $request->input('type');
            return \App\Helpers\is_mobile($type, 'learning_outcome/lo_marks_greport2/show', $school_data, 'view');
        }
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

        $exam_type_dd = DB::table('learning_outcome_exam_type_master')
        ->pluck('EXAM_TYPE', 'EXAM_TYPE');


        $dataStore = array(
            'medium' => $medium,
            'std' => $std,
            'div' => $div,
            'exam_type_dd' => $exam_type_dd,
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

        $medium = $_REQUEST['medium'];
        $std = $_REQUEST['std'];
        $div = $_REQUEST['div'];
        $exam_type = $_REQUEST['exam_type'];

        // $request->session()->put('exam_type') = $exam_type;
        session([
            'exam_type' => $exam_type,
            'medium' => $medium,
            'std' => $std,
            'div' => $div
            ]);

        
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $syear = $request->session()->get('syear');

        $sql = "
        select CONCAT_WS(' ',s.last_name,s.first_name,s.middle_name) AS name,
        s.enrollment_no as roll_no,stds.name AS STD,se.student_id
        from tblstudent s
        inner join tblstudent_enrollment se on se.student_id = s.id
        inner join standard stds on stds.id = se.standard_id
        inner join division d on d.id = se.section_id
        WHERE se.syear = $syear AND s.sub_institute_id = '$sub_institute_id'
            AND (stds.name like '%$std' and stds.name not like '%1$std')
            AND d.name = '$div'
        GROUP BY se.student_id
        ";

        // echo $sql;

        $students = DB::select(DB::raw($sql));
        // echo ('<pre>');print_r($students);exit;

        $type = $request->input('type');

        
        $dataStore['stud'] = $students;
        $dataStore['medium'] = $medium;
        $dataStore['std'] = $std;
        $dataStore['div'] = $div;
        // $dataStore['subject'] = $subject;

        return \App\Helpers\is_mobile($type, 'learning_outcome/lo_marks_greport2/add', $dataStore, 'view');
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

        return \App\Helpers\is_mobile($type, 'lo_marks_greport2.index', $res, 'redirect');

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

        // $allData = lo_marks_greport2::
        //     where(['SubInstituteId' => $sub_institute_id])
        //     ->get()->toArray();

        $str = 'SELECT * FROM learning_outcome_indicator WHERE ID = '.$id;
        $allData = DB::select(DB::raw($str));

        // $allData = lo_marks_greport2::find($id)->toArray();
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
        return \App\Helpers\is_mobile($type, "learning_outcome/lo_marks_greport2/edit", $data, "view");
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

        return \App\Helpers\is_mobile($type, "lo_marks_greport2.index", $res, "redirect");
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

        return \App\Helpers\is_mobile($type, "lo_marks_greport2.index", $res, "redirect");
    }
}
