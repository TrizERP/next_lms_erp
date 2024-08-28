<?php

namespace App\Http\Controllers\result\result_activity_master;

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

class resultActivityMasterController extends Controller
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

        $get_result_activity_masters = DB::table('result_activity_master as ram')
            ->selectRaw('ram.*, rs.main_title as result_main_title, rs.title as result_title')
            ->join('result_skillset as rs', 'rs.id', '=', 'ram.skill_id')
            ->where('ram.sub_institute_id', $sub_institute_id)
            ->get()->toArray();

        $res['status_code'] = 1;
        $res['message'] = "Success";
        $res['result_activity_masters'] = $get_result_activity_masters;

        return is_mobile($type, "result/result_activity_master/show_result_activity_master", $res, "view");
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

        $get_result_skillsets = DB::table('result_skillset')
            ->where('sub_institute_id', $sub_institute_id)
            ->get()->toArray();
            
        $res['result_skillsets'] = $get_result_skillsets;

        return is_mobile($type, "result/result_activity_master/add_result_activity_master", $res, "view");
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
        $title = $request->get('title');
        $skill_id = $request->get('skill_id');
        $sort_order = $request->get('sort_order');

        $finalArray = [
            'title' => $title,
            'skill_id' => $skill_id,
            'sort_order' => $sort_order,
            'sub_institute_id' => $sub_institute_id,
            'created_by' => $user_id,
            'created_at' => now(),
        ];

        DB::table('result_activity_master')->insert($finalArray);

        $res['status_code'] = 1;
        $res['message'] = "Result activity master added successfully.";

        return is_mobile($type, "result_activity_master.index", $res);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return Application|Factory|View
     */
    public function edit(Request $request, $id)
    {
        $sub_institute_id = $request->session()->get("sub_institute_id");

        $get_result_activity_masters = DB::table('result_activity_master')
            ->where('sub_institute_id', $sub_institute_id)
            ->where('id', $id)
            ->first();

        $get_result_skillsets = DB::table('result_skillset')
            ->where('sub_institute_id', $sub_institute_id)
            ->get()->toArray();

        $res['result_activity_masters'] = $get_result_activity_masters;
        $res['result_skillsets'] = $get_result_skillsets;
        $type = $request->input('type');

        return is_mobile($type, "result/result_activity_master/edit_result_activity_master", $res, "view");
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  Request  $request
     * @param  int  $id
     * @return Response
     */
    public function update(Request $request, $id)
    {
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $term_id = $request->session()->get('term_id');
        $syear = $request->session()->get('syear');
        $type = $request->input('type');
        $user_id = $request->session()->get('user_id');

        $finalArray = $request->except('_method', '_token', 'submit');

        DB::table('result_activity_master')->where(['id' => $id])->update($finalArray);

        $res['status_code'] = 1;
        $res['message'] = "Result activity master updated successfully.";

        return is_mobile($type, "result_activity_master.index", $res);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return Response
     */
    public function destroy(Request $request, $id)
    {
        $type = $request->input('type');

        DB::table('result_activity_master')->where(["id" => $id])->delete();

        $res['status_code'] = "1";
        $res['message'] = "Result activity master deleted successfully";

        return is_mobile($type, "result_activity_master.index", $res);
    }
}
