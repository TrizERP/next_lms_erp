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
        $type = $request->input('type');
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $res['standard'] = $_REQUEST["standard"];
        $res['grade'] = $_REQUEST['grade'];
        $res['division'] = $_REQUEST['division'];
        $res['skillset_id'] = $_REQUEST['skillset_id'];
        
        $student_data = SearchStudent($_REQUEST['grade'], $_REQUEST['standard'], $_REQUEST['division']);
        
        $get_result_skillsets = DB::table('result_skillset')
            ->where('sub_institute_id', $sub_institute_id)
            ->get()->toArray();

        $get_result_skillset = DB::table('result_skillset')
            ->select('group')
            ->where('sub_institute_id', $sub_institute_id)
            ->where('id', $_REQUEST['skillset_id'])
            ->first();
        
        $get_result_activity_groups = DB::table('result_activity_group')
            ->where('sub_institute_id', $sub_institute_id)
            ->whereIn('group', [$get_result_skillset->group])
            ->get()->toArray();
        
        $res['result_skillsets'] = $get_result_skillsets;

        if($request->activity_master != '')
        {
            $res['activity_master'] = $this->getRActivityMaster($_REQUEST['skillset_id']);
        }
        
        $res['result_skillsets'] = $get_result_skillsets;
        $res['activity_value'] = $_REQUEST['activity_master'];
        $res['student_datas'] = $student_data;
        $res['result_activity_groups'] = $get_result_activity_groups;

        return is_mobile($type, "result/result_activity_marks/add_result_activity_marks", $res, "view");
    }

    public function getRActivityMaster($activity_master)
    {
        $where = [
            "ram.sub_institute_id" => session()->get('sub_institute_id'),
            "ram.skill_id" => $activity_master,
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
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $term_id = $request->session()->get('term_id');
        $syear = $request->session()->get('syear');
        $user_id = $request->session()->get('user_id');
        $type = $request->input('type');
        $student_id = $request->get('student_id');
        $activity_id = $request->get('activity_id');
        $group_id = $request->get('group_id');
        $activity_groups = $request->get('activity_group');
        
        foreach($activity_groups as $key => $activity_group)
        {
            $finalArray = [
                'student_id' => $key,
                'activity_id' => $activity_id,
                'group_id' => $activity_group,
                'sub_institute_id' => $sub_institute_id,
                'created_by' => $user_id,
                'created_at' => now(),
            ];

            DB::table('result_activity_marks')->insert($finalArray);
        }

        $res['status_code'] = 1;
        $res['message'] = "Result activity marks added successfully.";

        return is_mobile($type, "result_activity_marks.index", $res);
    }
}
