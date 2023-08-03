<?php

namespace App\Http\Controllers\lms\leaderboard;

use App\Http\Controllers\Controller;
use App\Models\lms\leaderboard\lb_masterModel;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use function App\Helpers\is_mobile;

class lbMasterController extends Controller
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
        $res['data'] = $data['lbmaster_data'];

        return is_mobile($type, 'lms/leaderboard/show_lbmaster', $res, "view");
    }

    public function getData($request)
    {
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $marking_period_id = session()->get('term_id');

        $data['lbmaster_data'] = lb_masterModel::select('lb_master.*', 'a.title', 's.name')
            ->join('academic_section as a', 'a.id', 'lb_master.grade_id')
            ->join('standard as s',function($join) use($marking_period_id){
                $join->on('s.id', 'lb_master.standard_id');
                //  ->when($marking_period_id,function($query) use($marking_period_id){
                //      $query->where('s.marking_period_id',$marking_period_id);
                // });
            })
            ->where(['lb_master.sub_institute_id' => $sub_institute_id])
            ->get();

        return $data;
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return Response
     */
    public function create(Request $request)
    {
        $type = $request->input('type');
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $syear = $request->session()->get('syear');

        $data = [];

        return is_mobile($type, 'lms/leaderboard/add_lbmaster', $data, "view");
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
        $show_hide = $request->get('show_hide');
        $show_hide_val = $show_hide ?? '';
        $per_value = 0;
        if ($request->has('per_value')) {
            $per_value = $request->get('per_value');
        }


        //Check if Subject Already Exist or not
        $exist = $this->check_exist($request->get('grade'), $request->get('standard'), $request->get('module_name'),
            $sub_institute_id);
        if ($exist == 0) {
            $content = [
                'grade_id'         => $request->get('grade'),
                'standard_id'      => $request->get('standard'),
                'module_name'      => $request->get('module_name'),
                'per_value'        => $per_value,
                'description'      => $request->get('description'),
                'points'           => $request->get('points'),
                'icon'             => $request->get('icon'),
                'status'           => $show_hide_val,
                'sub_institute_id' => $sub_institute_id,
            ];

            lb_masterModel::insert($content);

            $res = [
                "status_code" => 1,
                "message"     => "Leader board Master Added Successfully",
            ];
        } else {
            $res = [
                "status_code" => 0,
                "message"     => "Leader board Master Already Exist",
            ];
        }

        $type = $request->input('type');

        return is_mobile($type, "lb_master.index", $res, "redirect");
    }

    public function check_exist($grade_id, $standard_id, $module_name, $sub_institute_id, $id = null)
    {
        $data = DB::table('lb_master')
            ->selectRaw('count(*) as tot')
            ->where('sub_institute_id', $sub_institute_id)
            ->where('grade_id', $grade_id)
            ->where('standard_id', $standard_id)
            ->where('module_name', $module_name);
        if ($id != "") {
            $data = $data->where('id', $id);
        }

        $data = $data->get()->toArray();

        return $data[0]->tot;
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return void
     */
    public function show(Request $request, $id)
    {
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return Response
     */
    public function edit(Request $request, $id)
    {
        $type = $request->input('type');

        $sub_institute_id = $request->session()->get('sub_institute_id');

        $data['lbmaster_data'] = lb_masterModel::find($id)->toArray();

        return is_mobile($type, "lms/leaderboard/add_lbmaster", $data, "view");
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
        $show_hide = $request->get('show_hide');
        $show_hide_val = $show_hide ?? '';

        //Check if Subject Already Exist or not
        $exist = $this->check_exist($request->get('grade'), $request->get('standard'), $request->get('module_name'),
            $sub_institute_id, $id);
        if ($exist == 0) {
            $content = [
                'grade_id'         => $request->get('grade'),
                'standard_id'      => $request->get('standard'),
                'module_name'      => $request->get('module_name'),
                'description'      => $request->get('description'),
                'points'           => $request->get('points'),
                'status'           => $show_hide_val,
                'icon'             => $request->get('icon'),
                'sub_institute_id' => $sub_institute_id,
            ];

            lb_masterModel::where(["id" => $id])->update($content);

            $res = [
                "status_code" => 1,
                "message"     => "Leader board Master Updated Successfully",
            ];
        } else {
            $res = [
                "status_code" => 0,
                "message"     => "Leader board Master Already Exist",
            ];
        }
        $type = $request->input('type');

        return is_mobile($type, "lb_master.index", $res, "redirect");
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
        lb_masterModel::where(["id" => $id])->delete();

        $res['status_code'] = "1";
        $res['message'] = "Leader Board Master Deleted Successfully";

        return is_mobile($type, "lb_master.index", $res);
    }

}
