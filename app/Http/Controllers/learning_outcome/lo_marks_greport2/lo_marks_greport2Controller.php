<?php

namespace App\Http\Controllers\learning_outcome\lo_marks_greport2;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use function App\Helpers\getStudents;
use function App\Helpers\is_mobile;

class lo_marks_greport2Controller extends Controller
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

        if (isset($request['id'])) {

            $exam_type = $request->session()->get('exam_type');
            $medium = $request->session()->get('medium');
            $std = $request->session()->get('std');
            $div = $request->session()->get('div');

            $student_id_arr = [
                0 => $request['id'],
            ];
            $student = getStudents($student_id_arr);
            $student = $student[$request['id']];

            $standard = $request->session()->get('std');
            $medium = $request->session()->get('medium');

            $where = [
                'learning_outcome_pdf.standard' => $standard,
                'learning_outcome_pdf.medium'   => $medium,
            ];

            $sub_dd = DB::table('learning_outcome_pdf')
                ->where($where)
                ->pluck('learning_outcome_pdf.DISPLAY_SUBJECT', 'learning_outcome_pdf.SUBJECTS');

            $dd = $this->get_all_dd();

            $per_arr = [
                'RED'    => 40,
                'YELLOW' => 70,
                'GREEN'  => 100,
            ];

            $all_final_data = [];
            $all_subject_lo = [];
            $line_chart_data = [];
            foreach ($sub_dd as $keys => $subject) {
                $result = DB::table("learning_outcome_indicator as LO")
                    ->join('learning_outcome_question_master as LQ', function ($join) {
                        $join->whereRaw("LO.ID = LQ.INDICATORE_ID");
                    })
                    ->join('learning_outcome_student_marks as LOM', function ($join) {
                        $join->whereRaw("LOM.QUESTION_ID = LQ.ID");
                    })
                    ->selectRaw('LO.SUBJECT,LO.INDICATOR,LQ.QUESTION_TITLE,sum(LQ.QUESTION_OUT_OF) tot,sum(LOM.MARKS) mar,
                    (sum(LOM.MARKS)*100/sum(LQ.QUESTION_OUT_OF)) per')
                    ->where("LOM.STUDENT_ID", "=", $request['id'])
                    ->where("LQ.EXAM_TYPE", "=", $exam_type)
                    ->where("LQ.medium", "=", $medium)
                    ->where("LQ.standard", "=", $std)
                    ->where("LQ.subject", "=", $subject)
                    ->groupBy('LO.INDICATOR')
                    ->get()->toArray();

                $all_data = [];
                foreach ($result as $key => $value) {

                    $all_data[$subject][$value->INDICATOR] = number_format($value->per, 2);
                }

                $all_final_data[$subject] = $all_data[$subject];

                $result = DB::table("learning_outcome_indicator as LO")
                    ->join('learning_outcome_question_master as LQ', function ($join) {
                        $join->whereRaw("LO.ID = LQ.INDICATORE_ID");
                    })
                    ->join('learning_outcome_student_marks as LOM', function ($join) {
                        $join->whereRaw("LOM.QUESTION_ID = LQ.ID");
                    })
                    ->selectRaw('LO.SUBJECT,LO.INDICATOR,LQ.EXAM_CODE,SUM(LQ.QUESTION_OUT_OF) tot, SUM(LOM.MARKS) mar,
                    (SUM(LOM.MARKS)*100/ SUM(LQ.QUESTION_OUT_OF)) per')
                    ->where("LOM.STUDENT_ID", "=", $request['id'])
                    ->where("LQ.EXAM_TYPE", "=", $exam_type)
                    ->where("LQ.medium", "=", $medium)
                    ->where("LQ.standard", "=", $std)
                    ->where("LQ.subject", "=", $subject)
                    ->groupBy('LQ.EXAM_CODE')
                    ->orderBy('LQ.ID')
                    ->get()->toArray();


                foreach ($result as $key => $value) {
                    $line_chart_data[$subject][$value->EXAM_CODE] = number_format($value->per, 2);
                }
            }

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

            $final_subject_marks = 0;


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
                }
                $round_chart = rtrim($round_chart, ',');

                $round_chart .= "   ]
                                },";
            }
            $round_chart = rtrim($round_chart, ',');
            $round_chart .= ' ]
                } ';
            Storage::disk('public')->put('data.json', ($round_chart));

            $school_data['data'] = [
                'student_info'        => $student,
                'selected_exam_type'  => $exam_type,
                'all_dd'              => $dd,
                'subject_dd'          => $sub_dd,
                'triangle'            => $all_final_data,
                'all_subject_lo'      => $all_subject_lo,
                'line_chart_data'     => $linechart_json,
                'total_subject_marks' => $final_subject_marks,
            ];

            $type = $request->input('type');

            return is_mobile($type, 'learning_outcome/lo_marks_greport2/FinalShow', $school_data, 'view');
        } else {
            $school_data['data'] = $this->get_all_dd();
            $type = $request->input('type');

            return is_mobile($type, 'learning_outcome/lo_marks_greport2/show', $school_data, 'view');
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

        $exam_type_dd = DB::table('learning_outcome_exam_type_master')
            ->pluck('EXAM_TYPE', 'EXAM_TYPE');


        return [
            'medium'       => $medium,
            'std'          => $std,
            'div'          => $div,
            'exam_type_dd' => $exam_type_dd,
        ];
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return Response
     */
    public function create(Request $request)
    {
        $medium = $_REQUEST['medium'];
        $std = $_REQUEST['std'];
        $div = $_REQUEST['div'];
        $exam_type = $_REQUEST['exam_type'];

        session([
            'exam_type' => $exam_type,
            'medium'    => $medium,
            'std'       => $std,
            'div'       => $div,
        ]);


        $sub_institute_id = $request->session()->get('sub_institute_id');
        $syear = $request->session()->get('syear');

        $students = DB::table("tblstudent as s")
            ->join('tblstudent_enrollment as se', function ($join) {
                $join->whereRaw("se.student_id = s.id");
            })
            ->join('standard as stds', function ($join) {
                $join->whereRaw("stds.id = se.standard_id");
            })
            ->join('division as d', function ($join) {
                $join->whereRaw("d.id = se.section_id");
            })
            ->selectRaw("CONCAT_WS(' ',s.last_name,s.first_name,s.middle_name) AS name,
             s.enrollment_no as roll_no,stds.name AS STD,se.student_id")
            ->where("se.syear", "=", $syear)
            ->where("s.sub_institute_id", "=", $sub_institute_id)
            ->whereRaw("stds.name like '%$std' and stds.name not like '%1$std'")
            ->where("d.name", "=", $div)
            ->groupBy('se.student_id')
            ->get()->toArray();

        $type = $request->input('type');


        $dataStore['stud'] = $students;
        $dataStore['medium'] = $medium;
        $dataStore['std'] = $std;
        $dataStore['div'] = $div;

        return is_mobile($type, 'learning_outcome/lo_marks_greport2/add', $dataStore, 'view');
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

        return is_mobile($type, 'lo_marks_greport2.index', $res, 'redirect');
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

        return is_mobile($type, "learning_outcome/lo_marks_greport2/edit", $data, "view");
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

        return is_mobile($type, "lo_marks_greport2.index", $res, "redirect");
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

        return is_mobile($type, "lo_marks_greport2.index", $res, "redirect");
    }
}
