<?php

namespace App\Http\Controllers\result\co_scholastic_marks_entry;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\result\co_scholastic_marks_entry\co_scholastic_marks_entry;
use App\Models\result\co_scholastic\co_scholastic;
use DB;

class co_scholastic_marks_entry_controller extends Controller {

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request) {
        if (session()->has('data')) { // check if it exists
            $data_arr = session('data'); // to retrieve value
            if (isset($data_arr['message'])) {
                $data['message'] = $data_arr['message'];
            }
            if (isset($data_arr['class'])) {
                $data['class'] = $data_arr['class'];
            }
        }

        $data['data'] = array();
        $type = $request->input('type');
        return \App\Helpers\is_mobile($type, "result/co_scholastic_marks_entry/show", $data, "view");
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create(Request $request) {
    //    echo "<pre>";
    //    print_r($_REQUEST);
    //    exit;


        $where = array(
            'id' => $_REQUEST['co_scholastic'],
        );
        $mark_type = co_scholastic::
                        select('mark_type', 'max_mark', 'co_grade')
                        ->where($where)->get()->toArray();
                        // echo "<pre>";
                        // print_r($mark_type);
                        // exit;
        $max_mark = $mark_type[0]['max_mark'];
        $co_grade = $mark_type[0]['co_grade'];
        $mark_type = $mark_type[0]['mark_type'];
        $responce_arr['mark_type'] = $mark_type;
  
        $student_data = \App\Helpers\SearchStudent($_REQUEST['grade'], $_REQUEST['standard'], $_REQUEST['division']);
//        $grd_data = $this->getGreadData($_REQUEST['standard']);
//marks_entry
        $type = $request->input('type');
        $where = array(
            'grade_id' => $_REQUEST['grade'],
            'standard_id' => $_REQUEST["standard"],
            'term_id' => $_REQUEST["term"],
            'co_scholastic_id' => $_REQUEST['co_scholastic'],
            'syear' => session()->get('syear'),
            'sub_institute_id' => session()->get('sub_institute_id'),
        );
        $marks_entry = co_scholastic_marks_entry::
                where($where)->get()->toArray();

        $attendance_data = "";
        $responce_arr['term_id'] = $_REQUEST["term"];
        $responce_arr['standard'] = $_REQUEST["standard"];
        $responce_arr['grade'] = $_REQUEST['grade'];
        $responce_arr['division'] = $_REQUEST['division'];
        $responce_arr['co_scholastic_parent_dd'] = $this->get_co_scholastic_parent_dd();
        $responce_arr['co_scholastic_parent'] = $_REQUEST['co_scholastic_parent'];
        $responce_arr['co_scholastic_dd'] = $this->get_co_scholastic_dd($_REQUEST["term"], $_REQUEST['co_scholastic_parent']);
        $responce_arr['co_scholastic'] = $_REQUEST['co_scholastic'];
        foreach ($student_data as $id => $arr) {
            $temp_arr = array();
            foreach ($marks_entry as $data_id => $data_arr) {
                if ($data_arr['student_id'] == $arr['student_id']) {
                    $temp_arr = $data_arr;
                }
            }


//            $responce_arr['grd_data'] = $grd_data;
            if ($mark_type == 'GRADE') {
                $responce_arr['co_scholastic_grade_dd'] = $this->get_co_scholastic_grade($co_grade);
//                echo "<pre>";
//                print_r($responce_arr['co_scholastic_grade_dd']);
//                exit;
                
            }

            $responce_arr['stu_data'][$id]['sr.no'] = $id + 1;
            $responce_arr['stu_data'][$id]['name'] = $arr['first_name'] . ' ' . $arr['middle_name'] . ' ' . $arr['last_name'];

            if (count($temp_arr) > 0) {
//                if ($temp_arr['is_absent'] == "AB") {
//                    $responce_arr['stu_data'][$id]['points'] = $temp_arr['is_absent'];
//                } else {
                $responce_arr['stu_data'][$id]['points'] = $temp_arr["points"];
//                }
                $responce_arr['stu_data'][$id]['outof'] = $max_mark;
//                $responce_arr['stu_data'][$id]['per'] = $temp_arr["per"];
                $responce_arr['stu_data'][$id]['grade'] = $temp_arr["grade"];
//                $responce_arr['stu_data'][$id]['comment'] = $temp_arr["comment"];
            } else {
                $responce_arr['stu_data'][$id]['points'] = 0;
                $responce_arr['stu_data'][$id]['outof'] = $max_mark;
//                $responce_arr['stu_data'][$id]['per'] = 0;
                $responce_arr['stu_data'][$id]['grade'] = "-";
//                $responce_arr['stu_data'][$id]['comment'] = "";
            }
            $responce_arr['stu_data'][$id]['student_id'] = $arr['student_id'];
        }
//        echo "<pre>";
//        print_r($responce_arr);
//        exit;
        return \App\Helpers\is_mobile($type, "result/co_scholastic_marks_entry/add", $responce_arr, "view");
        
    //
    }

    public function get_co_scholastic_parent_dd() {
        $where = array(
            "re.sub_institute_id" => session()->get('sub_institute_id'),
        );

        $co_scholastic_parent = DB::table('result_co_scholastic_parent as re')
                ->where($where)
                ->pluck('re.title', 're.id');

        return $co_scholastic_parent;
    }

    public function get_co_scholastic_dd($term, $parent_id) {
        $where = array(
            "re.sub_institute_id" => session()->get('sub_institute_id'),
            "re.parent_id" => $parent_id,
            "re.term_id" => $term,
        );

        $co_scholastic_parent = DB::table('result_co_scholastic as re')
                ->where($where)
                ->pluck('re.title', 're.id');

        return $co_scholastic_parent;
    }

    public function get_co_scholastic_grade($id) {
        $where = array(
            "re.sub_institute_id" => session()->get('sub_institute_id'),
            "re.map_id" => $id,
        );

        $co_scholastic_dd = DB::table('result_co_scholastic_grades as re')
                ->where($where)
                ->pluck('re.title', 're.id');

        return $co_scholastic_dd;
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request) {
        //
//        echo "<pre>";
//        print_r($_REQUEST);
//        exit;
$sub_institute_id = session()->get('sub_institute_id');
$syear = session()->get('syear');
        $all_data = array();
        if(isset($_REQUEST["type"]) && $_REQUEST["type"] == "API"){
            $sub_institute_id = $_REQUEST["sub_institute_id"];
            $syear = $_REQUEST["syear"];
            $all_data = json_decode($_REQUEST["data"],1);
        }else{
            $all_data = $_REQUEST['values'];
        }
        foreach ($all_data as $student_id => $arr) {
            co_scholastic_marks_entry::where([
                'grade_id' => $arr['grade_id'],
                'standard_id' => $arr["standard_id"],
                'term_id' => $arr["term_id"],
                'co_scholastic_id' => $arr['co_scholastic'],
                'syear' => $syear,
                'sub_institute_id' => $sub_institute_id,
                'student_id' => $student_id,
            ])->delete();
//            if (isset($arr['points'])
//            ) {
            if (isset($arr['points'])) {
                    $data = new co_scholastic_marks_entry([
                        'grade_id' => $arr['grade_id'],
                        'standard_id' => $arr['standard_id'],
                        'term_id' => $arr['term_id'],
                        'student_id' => $student_id,
                        'co_scholastic_id' => $arr['co_scholastic'],
                        'grade' => "",
                        'points' => $arr['points'],
                        'sub_institute_id' => $sub_institute_id,
                        'syear' => $syear,
                    ]);
                    $data->save();
                } else {
                    $data = new co_scholastic_marks_entry([
                        'grade_id' => $arr['grade_id'],
                        'standard_id' => $arr['standard_id'],
                        'term_id' => $arr['term_id'],
                        'student_id' => $student_id,
                        'co_scholastic_id' => $arr['co_scholastic'],
                        'grade' => $arr['grade'],
                        'points' => "",
                        'sub_institute_id' => $sub_institute_id,
                        'syear' => $syear,
                    ]);
                    $data->save();
                }
//            }
        }
        $res = array(
            "status_code" => 1,
            "message" => "Data Saved",
            "class" => "success",
        );

        $type = $request->input('type');
        return \App\Helpers\is_mobile($type, "co_scholastic_marks_entry.index", $res, "redirect");
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id) {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id) {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id) {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id) {
        //
    }

}
