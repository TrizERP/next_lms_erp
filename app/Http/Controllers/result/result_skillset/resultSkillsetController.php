<?php

namespace App\Http\Controllers\result\result_skillset;

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

class resultSkillsetController extends Controller
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

        $result_skillsets = DB::table('result_skillset')
            ->where('sub_institute_id', $sub_institute_id)
            ->get()->toArray();

        $res['status_code'] = 1;
        $res['message'] = "Success";
        $res['result_skillsets'] = $result_skillsets;

        return is_mobile($type, "result/result_skillset/show_skillset", $res, "view");
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

        $get_result_activity_groups = DB::table('result_activity_group')
            ->where('sub_institute_id', $sub_institute_id)
            ->get()->toArray();

        $res['get_result_activity_groups'] = $get_result_activity_groups;

        return is_mobile($type, "result/result_skillset/add_skillset", $res, "view");
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
        $main_title = $request->get('main_title');
        $title = $request->get('title');
        $group = $request->get('group');
        $sort_order = $request->get('sort_order');

        $finalArray = [
            'main_title' => $main_title,
            'title' => $title,
            'group' => $group,
            'sort_order' => $sort_order,
            'sub_institute_id' => $sub_institute_id,
            'created_by' => $user_id,
            'created_at' => now(),
        ];

        DB::table('result_skillset')->insert($finalArray);

        $res['status_code'] = 1;
        $res['message'] = "Skillset added successfully.";

        return is_mobile($type, "result_skillset.index", $res);
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
     * @return Application|Factory|View
     */
    public function edit(Request $request, $id)
    {
        $sub_institute_id = $request->session()->get("sub_institute_id");

        $result_skillset = DB::table('result_skillset')
            ->where('sub_institute_id', $sub_institute_id)
            ->where('id', $id)
            ->first();

        $get_result_activity_groups = DB::table('result_activity_group')
            ->where('sub_institute_id', $sub_institute_id)
            ->get()->toArray();

        $res['get_result_activity_groups'] = $get_result_activity_groups;
        $res['result_skillset'] = $result_skillset;
        $type = $request->input('type');

        return is_mobile($type, "result/result_skillset/edit_skillset", $res, "view");
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

        DB::table('result_skillset')->where(['id' => $id])->update($finalArray);

        $res['status_code'] = 1;
        $res['message'] = "Skillset updated successfully.";

        return is_mobile($type, "result_skillset.index", $res);
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

        DB::table('result_skillset')->where(["id" => $id])->delete();

        $res['status_code'] = "1";
        $res['message'] = "Skillset deleted successfully";

        return is_mobile($type, "result_skillset.index", $res);
    }
}
