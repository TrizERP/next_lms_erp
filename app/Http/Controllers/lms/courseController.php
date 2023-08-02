<?php

namespace App\Http\Controllers\lms;

use App\Http\Controllers\Controller;
use App\Models\lms\lmsContentCategoryModel;
use App\Models\student\tblstudentEnrollmentModel;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use function App\Helpers\is_mobile;

class courseController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index(Request $request)
    {

        $data = $this->getData($request);
   
        $type = $request->input('type');
        $res['status_code'] = 1;
        $res['message'] = "SUCCESS";
        $res['lms_subject'] = $data['mycourse_arr'];
        $res['content_category'] = $data['content_category'];
        return is_mobile($type, 'lms/show_course', $res, "view");
    }

    public function getData($request)
    {
        if(isset($_REQUEST['preload_lms'])){
            $sub_institute_id =1;
            $year = DB::table('academic_year')->where('sub_institute_id',$sub_institute_id)->get()->toArray();
            $syear =$year[0]->syear;
            $user_profile_name = "Admin";
            $user_id = 1;
        }else{
            $sub_institute_id = $request->session()->get('sub_institute_id');
            $syear = $request->session()->get('syear');
            $user_profile_name = session()->get('user_profile_name');
            $user_id = session()->get('user_id');
        }
        $marking_period_id = session()->get('term_id');
        $mycourse_arr = [];

        $extra = "1=1";
        if (strtoupper(session()->get('user_profile_name')) == "STUDENT") {
            $student_id = session()->get('user_id');
            $stu_data = tblstudentEnrollmentModel::select('standard_id')
                ->where([
                    'student_id' => $student_id, 'syear' => $syear, 'sub_institute_id' => $sub_institute_id,
                ])->get()->toArray();

            $extra .= " AND FIND_IN_SET(s.standard_id, (SELECT CONCAT_WS(',', '" . $stu_data[0]['standard_id'] . "', GROUP_CONCAT(id)) FROM standard WHERE sub_institute_id = '" . $sub_institute_id . "' AND grade_id IN (SELECT id FROM academic_section WHERE sub_institute_id = '" . $sub_institute_id . "' AND title = 'Other')))";
        }

        $getIsLms = DB::table('school_setup')
            ->where('Id', $sub_institute_id)
            ->value('is_Lms');

        $sub_institute_id_by_lms = ($getIsLms == 'Y') ? "(s.sub_institute_id = 1 OR s.sub_institute_id = $sub_institute_id)" : "s.sub_institute_id = $sub_institute_id";

        $query = DB::table('sub_std_map AS s')  
            ->join('standard AS STD', function ($join) use ($marking_period_id){
                $join->on('STD.id', '=', 's.standard_id')->when($marking_period_id,function ($query) use ($marking_period_id){
                    $query->where('STD.marking_period_id',$marking_period_id);
                }); })
            ->leftJoin('timetable AS t', function ($join) use ($syear, $user_id, $sub_institute_id, $extra) {
                $join->on('t.standard_id', '=', 's.standard_id')
                    ->on('t.subject_id', '=', 's.subject_id')
                    ->on('t.sub_institute_id', '=', 's.sub_institute_id')
                    ->where('t.syear', '=', $syear)
                    ->where('t.teacher_id', '=', $user_id)
                    ->where('s.allow_content', '=', 'Yes')
                    ->whereRaw($extra);
            })
            ->leftJoin('chapter_master AS cp', function ($join) use ($sub_institute_id) {
                $join->on('cp.subject_id', '=', 's.subject_id')
                    ->on('cp.standard_id', '=', 's.standard_id');
            })
            ->leftJoin('content_master AS c', function ($join) use ($sub_institute_id) {
                $join->on('c.subject_id', '=', 's.subject_id')
                    ->on('c.standard_id', '=', 's.standard_id')
                    ->on('c.sub_institute_id', '=', 's.sub_institute_id');
            })
            ->whereRaw($sub_institute_id_by_lms)
            ->where('s.allow_content', '=', 'Yes')
            ->groupBy('s.subject_id', 's.standard_id', 's.subject_category')
            ->orderBy('s.sort_order')
            ->select(
                'STD.name AS standard_name',
                's.display_name AS subject_name',
                's.subject_id',
                'STD.id AS standard_id',
                's.display_image',
                DB::raw("GROUP_CONCAT(DISTINCT CONCAT_WS('/', cp.chapter_name, cp.id) SEPARATOR '#') AS chapter_list"),
                DB::raw("IFNULL(s.subject_category, 'My Course') AS content_category")
            );

        if (strtoupper($user_profile_name) == 'TEACHER') {
            $query->where('s.sub_institute_id', '=', $sub_institute_id);
        } else {
            $query->whereRaw($sub_institute_id_by_lms);
        }

        $arr = $query->get()->toArray();

        $arr = json_decode(json_encode($arr), true);
        if (count($arr) > 0) {
            foreach ($arr as $key => $val) {
                $mycourse_arr[$val['content_category']][] = $val;
            }
        }

        //START Get Content Category
        $content_category = lmsContentCategoryModel::where('status', '1')
            ->where(function ($query) use ($sub_institute_id) {
                $query->where('sub_institute_id', '=', $sub_institute_id)
                    ->orWhere('sub_institute_id', '=', '0');
            })
            ->get()->toArray();
        //END Get Content Category

        $data['content_category'] = $content_category;
        $data['mycourse_arr'] = $mycourse_arr;

        //dd($data);
        return $data;
    }
   
    public function course_search(Request $request)
    {
        $type = $request->input('type');
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $grade = $request->input('grade');
        $standard = $request->input('standard');

        $mycourse_arr = [];
        $extra = "";
        if (strtoupper(session()->get('user_profile_name')) == "STUDENT") {
            $student_id = session()->get('user_id');
            $stu_data = tblstudentEnrollmentModel::select('standard_id')->where(['student_id' => $student_id])->get()->toArray();
            //$extra = " AND s.standard_id = '".$stu_data[0]['standard_id']."'";
            $extra = " AND 
            find_in_set(
                s.standard_id,
                (SELECT concat_ws(',','".$stu_data[0]['standard_id']."',group_concat(id))
                FROM standard
                WHERE sub_institute_id = '".$sub_institute_id."' AND grade_id IN (
                SELECT id
                FROM academic_section
                WHERE sub_institute_id = '".$sub_institute_id."' AND title = 'Other'))
            )";
        }

        if ($grade != "") {
            $extra .= " AND STD.grade_id = '".$grade."'";
        }
        if ($standard != "") {
            $extra .= " AND STD.id = '".$standard."'";
        }

        $arr = DB::select("SELECT STD.name AS standard_name,s.display_name AS subject_name,s.subject_id,STD.id AS standard_id,
                s.display_image,GROUP_CONCAT(DISTINCT(CONCAT_WS('/',cp.chapter_name,cp.id))) AS chapter_list,
                ifnull(s.subject_category,'My Course') AS content_category
                FROM sub_std_map s
                INNER JOIN standard STD ON STD.id = s.standard_id
                LEFT JOIN chapter_master cp ON cp.subject_id = s.subject_id
                LEFT JOIN content_master c ON c.subject_id = s.subject_id AND c.standard_id = s.standard_id AND c.sub_institute_id = s.sub_institute_id
                WHERE s.sub_institute_id = '".$sub_institute_id."' AND allow_content = 'Yes'
                 ".$extra."
                GROUP BY s.subject_id,s.standard_id,s.subject_category ORDER BY s.sort_order");

        $arr = json_decode(json_encode($arr), true);
        if (count($arr) > 0) {
            foreach ($arr as $key => $val) {
                $mycourse_arr[$val['content_category']][] = $val;
            }
        }
        //START Get Content Category
        $content_category = lmsContentCategoryModel::where('status', '1')->get()->toArray();
        //END Get Content Category

        $res['content_category'] = $content_category;
        $res['lms_subject'] = $mycourse_arr;
        $res['status_code'] = 1;
        $res['message'] = "SUCCESS";
        $res['grade'] = $grade;
        $res['standard'] = $standard;

        return is_mobile($type, 'lms/show_course', $res, "view");
    }


}
