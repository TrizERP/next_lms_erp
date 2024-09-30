<?php

namespace App\Http\Controllers\result\result_activity_marks;

use App\Http\Controllers\Controller;
use App\Models\student\studentVaccinationModel;
use GenTux\Jwt\GetsJwtToken;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use function App\Helpers\is_mobile;
use function App\Helpers\SearchStudent;

class resultActivityMarksController extends Controller
{
    use GetsJwtToken;

    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index(Request $request)
    {
        $type = $request->input('type');
        $sub_institute_id = $request->session()->get('sub_institute_id');

        $get_result_skillsets = DB::table('result_skillset')
            ->where('sub_institute_id', $sub_institute_id)
            ->get()->toArray();

        $res['status_code'] = 1;
        $res['message'] = "Success";
        $res['result_skillsets'] = $get_result_skillsets;

        return is_mobile($type, "result/result_activity_marks/show_result_activity_marks", $res, "view");
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return Application|Factory|View
     */
    public function create(Request $request)
    {
        // echo "<pre>";print_r($request->all());exit;
        $type = $request->input('type');
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $res['standard'] = $request->standard;
        $res['grade'] = $request->grade;
        $res['division'] = $request->division;
        $res['skillset_id'] = $request->skillset_id;

        $student_datas = SearchStudent($request->grade, $request->standard, $request->division);
        // echo "<pre>";print_r($request->all());exit;
        $get_activity_marks=[];
        foreach($student_datas as $student_data)
        {
            if($request->sub_activity_master != '')
            {
                $get_activity_marks[$student_data['id']] = DB::table('result_activity_marks as rams')
                ->selectRaw('rams.*, rag.id as group_id, rag.title as group_title')
                ->join('result_sub_activity as rsm', function ($join) {
                    $join->whereRaw("rsm.id = rams.sub_activity_id");
                })
                ->join('result_activity_group as rag', function ($join) {
                    $join->whereRaw("rag.id = rams.group_id");
                })
                ->where(['rams.sub_institute_id' => $sub_institute_id, 'rams.student_id' => $student_data['id'], 'rams.sub_activity_id' => $request->sub_activity_master])
                ->first();
            }else{
                $get_activity_marks[$student_data['id']] = DB::table('result_activity_marks as rams')
                ->selectRaw('rams.*, rag.id as group_id, rag.title as group_title')
                ->join('result_activity_master as ram', function ($join) {
                    $join->whereRaw("ram.id = rams.activity_id");
                })
                ->join('result_activity_group as rag', function ($join) {
                    $join->whereRaw("rag.id = rams.group_id");
                })
                ->where(['rams.sub_institute_id' => $sub_institute_id, 'rams.student_id' => $student_data['id'], 'rams.activity_id' => $request->activity_master])
                ->first();
            }
            
        }
        
        $get_result_skillsets = DB::table('result_skillset')
            ->where('sub_institute_id', $sub_institute_id)
            ->get()->toArray();

        $get_result_skillset = DB::table('result_skillset')
            ->select('group')
            ->where('sub_institute_id', $sub_institute_id)
            ->where('id', $request->skillset_id)
            ->first();
        
        $get_result_activity_groups = DB::table('result_activity_group')
            ->where('sub_institute_id', $sub_institute_id)
            ->whereIn('group', [$get_result_skillset->group])
            ->get()->toArray();
        
        $res['result_skillsets'] = $get_result_skillsets;

        if($request->activity_master != '')
        {
            $res['activity_master'] = $this->getRActivityMaster($request->skillset_id,$_REQUEST['standard']);
        }
        $res['sub_activity_master'] = null;

        if($request->sub_activity_master != '')
        {
            $res['sub_activity_value'] = DB::table('result_sub_activity')->where('sub_institute_id', $sub_institute_id)->where('sub_skill_id',$_REQUEST['activity_master'])->get()->toArray();
            $res['sub_activity_master'] = $request->sub_activity_master;
        }
        
        $res['result_skillsets'] = $get_result_skillsets;
        $res['activity_value'] = $_REQUEST['activity_master'];
        $res['student_datas'] = $student_datas;
        $res['result_activity_groups'] = $get_result_activity_groups;
        $res['get_activity_marks'] = $get_activity_marks;
        // echo "<pre>";print_r($res['sub_activity_value']);exit;
        return is_mobile($type, "result/result_activity_marks/add_result_activity_marks", $res, "view");
    }

    public function getRActivityMaster($activity_master,$standard)
    {
        $where = [
            "ram.sub_institute_id" => session()->get('sub_institute_id'),
            "ram.skill_id" => $activity_master,
            'ram.standard'=>$standard,
        ];

        return DB::table('result_activity_master as ram')
            ->where($where)
            ->pluck('ram.title', 'ram.id');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  Request  $request
     * @return Response
     */
    public function store(Request $request)
    {
        // echo "<pre>";print_r($request->all());exit;
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $syear = $request->session()->get('syear');
        $term_id = $request->session()->get('term_id');
        $syear = $request->session()->get('syear');
        $user_id = $request->session()->get('user_id');
        $type = $request->input('type');
        $student_id = $request->get('student_id');
        $activity_id = $request->get('activity_id');
        $group_id = $request->get('group_id');
        $activity_groups = $request->get('activity_group');

        $sub_activity_master=null;
        if(isset($request->sub_activity_master)){
            $sub_activity_master = $request->get('sub_activity_master');
        }
        foreach($activity_groups as $key => $activity_group)
        {
            $finalArray = [
                'student_id' => $key,
                'activity_id' => $activity_id,
                'group_id' => $activity_group,
                'created_by' => $user_id,
                'created_at' => now(),
                'updated_at' => now(),
            ];
            if($sub_activity_master!=null){
                DB::table('result_activity_marks')->updateOrInsert(
                    [
                        'sub_institute_id' => $sub_institute_id,
                        'student_id' => $key,
                        'activity_id' => $activity_id,
                        'sub_activity_id' => $sub_activity_master,
                        'syear' => $syear,
                    ],
                    $finalArray
                );
            }else{
                DB::table('result_activity_marks')->updateOrInsert(
                    [
                        'sub_institute_id' => $sub_institute_id,
                        'student_id' => $key,
                        'activity_id' => $activity_id,
                        'sub_activity_id' => $sub_activity_master,
                        'syear' => $syear,
                    ],
                    $finalArray
                );
            }
        }
        
        $res['status_code'] = 1;
        $res['message'] = "Result activity marks added/updated successfully.";

        return is_mobile($type, "result_activity_marks.index", $res);
    }
}
