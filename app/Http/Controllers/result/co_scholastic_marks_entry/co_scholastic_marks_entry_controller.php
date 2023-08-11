<?php

namespace App\Http\Controllers\result\co_scholastic_marks_entry;

use App\Http\Controllers\Controller;
use App\Models\result\co_scholastic\co_scholastic;
use App\Models\result\co_scholastic_marks_entry\co_scholastic_marks_entry;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use function App\Helpers\is_mobile;
use function App\Helpers\SearchStudent;

class co_scholastic_marks_entry_controller extends Controller
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
                $data['message'] = $data_arr['message'];
            }
            if (isset($data_arr['class'])) {
                $data['class'] = $data_arr['class'];
            }
        }

        $data['data'] = [];
        $type = $request->input('type');

        return is_mobile($type, "result/co_scholastic_marks_entry/show", $data, "view");
    }

    public function approve(Request $request)
    {
        // return $request;exit;
        $sub_institute_id = session()->get('sub_institute_id');
        $term_id=$request->term_id;
        $standard_id = $request->standard_id;
        $division_id = $request->division_id;
        $subject_id = $request->subject_id;
        $exam_id = $request->exam_id;
        $user = session()->get('user_id');        
        $module_name = "co_scholastic";

        $data=[
            "subject_id"=>$subject_id,
            "standard_id"=>$standard_id,
            "division_id"=>$division_id,
            "exam_id"=>$exam_id,
            "term_id"=>$term_id,      
            "sub_institute_id"=>$sub_institute_id,      
            "module_name"=>$module_name,
        ];
    
        $check = DB::table('result_exam_approve')->where($data)->get()->toArray();

        if(!empty($check) && $check > 0){
            $query = DB::table('result_exam_approve')->where($data)->update(['status'=>$request->approve ?? 0,'created_by'=>$user,'updated_at'=>now()]);
            $res = [
                "status_code" => 1,
                "message"     => "Data Upadted",
                "class"       => "success",
            ];
        }else{
            $data += ['status'=>$request->approve ?? 0,'created_by'=>$user,'created_at'=>now()];            
            $query = DB::table('result_exam_approve')->insert($data);
            $res = [
                "status_code" => 1,
                "message"     => "Data Saved",
                "class"       => "success",
            ];
        }
   
        $type = $request->input('type');

        return is_mobile($type, "co_scholastic_marks_entry.index", $res, "redirect");
    }
    /**
     * Show the form for creating a new resource.
     *
     * @param  Request  $request
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @return Response
     */
    public function create(Request $request)
    {
        $where = [
            'id' => $_REQUEST['co_scholastic'],
        ];

        $mark_type = co_scholastic::select('mark_type', 'max_mark', 'co_grade')
            ->where($where)->get()->toArray();

        $max_mark = $mark_type[0]['max_mark'];
        $co_grade = $mark_type[0]['co_grade'];
        $mark_type = $mark_type[0]['mark_type'];
        $responce_arr['mark_type'] = $mark_type;

        $student_data = SearchStudent($_REQUEST['grade'], $_REQUEST['standard'], $_REQUEST['division']);

        $type = $request->input('type');
        $where = [
            'grade_id'         => $_REQUEST['grade'],
            'standard_id'      => $_REQUEST["standard"],
            'term_id'          => $_REQUEST["term"],
            'co_scholastic_id' => $_REQUEST['co_scholastic'],
            'syear'            => session()->get('syear'),
            'sub_institute_id' => session()->get('sub_institute_id'),
        ];
        $marks_entry = co_scholastic_marks_entry::where($where)->get()->toArray();

        $approve_status=[
            "subject_id"=>"0",
            "standard_id"=>$_REQUEST["standard"],
            "division_id"=>$_REQUEST['division'],
            "exam_id"=>$_REQUEST['co_scholastic'],
            "term_id"=>$_REQUEST["term"],      
            "sub_institute_id"=>session()->get('sub_institute_id'),      
            "module_name"=>"co_scholastic",
        ];
        $check_approve = DB::table('result_exam_approve')->where($approve_status)->first();
        // print_r($check_approve);exit;
        if(isset($check_approve->created_by)){
            $approved_user = DB::table('tbluser')->where('id',$check_approve->created_by)->first();
        }
        $responce_arr['approve_status'] = $check_approve;
        $responce_arr['approved_user'] = $approved_user ?? '';    

        $attendance_data = "";
        $responce_arr['term_id'] = $_REQUEST["term"];
        $responce_arr['standard'] = $_REQUEST["standard"];
        $responce_arr['grade'] = $_REQUEST['grade'];
        $responce_arr['division'] = $_REQUEST['division'];
        $responce_arr['co_scholastic_parent_dd'] = $this->get_co_scholastic_parent_dd();
        $responce_arr['co_scholastic_parent'] = $_REQUEST['co_scholastic_parent'];
        $responce_arr['co_scholastic_dd'] = $this->get_co_scholastic_dd($_REQUEST["term"],
            $_REQUEST['co_scholastic_parent']);
        $responce_arr['co_scholastic'] = $_REQUEST['co_scholastic'];
        foreach ($student_data as $id => $arr) {
            $temp_arr = array();
            foreach ($marks_entry as $data_id => $data_arr) {
                if ($data_arr['student_id'] == $arr['student_id']) {
                    $temp_arr = $data_arr;
                }
            }


            if ($mark_type == 'GRADE') {
                $responce_arr['co_scholastic_grade_dd'] = $this->get_co_scholastic_grade($co_grade);
            }

            $responce_arr['stu_data'][$id]['sr.no'] = $id + 1;
            $responce_arr['stu_data'][$id]['name'] = $arr['first_name'].' '.$arr['middle_name'].' '.$arr['last_name'];

            if (count($temp_arr) > 0) {
//                if ($temp_arr['is_absent'] == "AB") {
//                    $responce_arr['stu_data'][$id]['points'] = $temp_arr['is_absent'];
//                } else {
                $responce_arr['stu_data'][$id]['points'] = $temp_arr["points"];
//                }
                $responce_arr['stu_data'][$id]['outof'] = $max_mark;
//                $responce_arr['stu_data'][$id]['per'] = $temp_arr["per"];
                $responce_arr['stu_data'][$id]['grade'] = $temp_arr["grade"];
                $responce_arr['stu_data'][$id][$arr['id']]['grade_marks'] = $temp_arr["grade"];
//                $responce_arr['stu_data'][$id]['comment'] = $temp_arr["comment"];
            } else {
                $responce_arr['stu_data'][$id]['points'] = 0;
                $responce_arr['stu_data'][$id]['outof'] = $max_mark;
//                $responce_arr['stu_data'][$id]['per'] = 0;
                $responce_arr['stu_data'][$id]['grade'] = "-";
                $responce_arr['stu_data'][$id][$arr['id']]['grade_marks'] = "-";
                
//                $responce_arr['stu_data'][$id]['comment'] = "";
            }
            $responce_arr['stu_data'][$id]['student_id'] = $arr['student_id'];
        }
        return is_mobile($type, "result/co_scholastic_marks_entry/add", $responce_arr, "view");
    }

    public function get_co_scholastic_parent_dd()
    {
        $where = [
            "re.sub_institute_id" => session()->get('sub_institute_id'),
        ];

        return DB::table('result_co_scholastic_parent as re')
            ->where($where)
            ->pluck('re.title', 're.id');
    }

    public function get_co_scholastic_dd($term, $parent_id)
    {
        $where = [
            "re.sub_institute_id" => session()->get('sub_institute_id'),
            "re.parent_id"        => $parent_id,
            "re.term_id"          => $term,
        ];

        return DB::table('result_co_scholastic as re')
            ->where($where)
            ->pluck('re.title', 're.id');
    }

    public function get_co_scholastic_grade($id)
    {
        $where = [
            "re.sub_institute_id" => session()->get('sub_institute_id'),
            "re.map_id"           => $id,
        ];

        return DB::table('result_co_scholastic_grades as re')
            ->where($where)
            ->pluck('re.title', 're.id');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  Request  $request
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @return Response
     */
    public function store(Request $request)
    {

        $sub_institute_id = session()->get('sub_institute_id');
        $syear = session()->get('syear');
        $all_data = [];
        if (isset($_REQUEST["type"]) && $_REQUEST["type"] == "API") {
            $sub_institute_id = $_REQUEST["sub_institute_id"];
            $syear = $_REQUEST["syear"];
            $all_data = json_decode($_REQUEST["data"], 1);
        } else {
            $all_data = $_REQUEST['values'];
        }
        foreach ($all_data as $student_id => $arr) {
           $check = co_scholastic_marks_entry::where([
                'grade_id'         => $arr['grade_id'],
                'standard_id'      => $arr["standard_id"],
                'term_id'          => $arr["term_id"],
                'co_scholastic_id' => $arr['co_scholastic'],
                'syear'            => $syear,
                'sub_institute_id' => $sub_institute_id,
                'student_id'       => $student_id,
            ])->get()->toArray();
            if(!empty($check)){
                $data = [
                    'grade_id'         => $arr['grade_id'],
                    'standard_id'      => $arr['standard_id'],
                    'term_id'          => $arr['term_id'],
                    'student_id'       => $student_id,
                    'co_scholastic_id' => $arr['co_scholastic'],
                    'sub_institute_id' => $sub_institute_id,
                    'syear'            => $syear,
                ];
                $update = DB::table('result_co_scholastic_marks_entries')->where($data)->update([
                'grade'=> $arr['grade'] ?? " ",
                'points'=> $arr['points'] ?? " "
                ]);
            }else{
                $data = new co_scholastic_marks_entry([ 
                    'grade_id'         => $arr['grade_id'],
                    'standard_id'      => $arr['standard_id'],
                    'term_id'          => $arr['term_id'],
                    'student_id'       => $student_id,
                    'co_scholastic_id' => $arr['co_scholastic'],
                    'grade'            => $arr['grade'] ?? " ",
                    'points'           => $arr['points'] ?? " ",
                    'sub_institute_id' => $sub_institute_id,
                    'syear'            => $syear,
                ]);
                $data->save();
        }
        }
        $res = [
            "status_code" => 1,
            "message"     => "Data Saved",
            "class"       => "success",
        ];

        $type = $request->input('type');

        return is_mobile($type, "co_scholastic_marks_entry.index", $res, "redirect");
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
