<?php

namespace App\Http\Controllers\ptm;

use App\Http\Controllers\Controller;
use App\Models\ptm\ptmtimeslotmasterModel;
use App\Models\school_setup\standardModel;
use App\Models\school_setup\std_div_mappingModel;
use Illuminate\Http\Request;
use function App\Helpers\is_mobile;

class ptmtimeslotmasterController extends Controller
{
    public function index(Request $request)
    {
        $data = $this->getData($request);
        $type = $request->input('type');
        $res['status_code'] = 1;
        $res['message'] = "SUCCESS";
        $res['data'] = $data;

        return is_mobile($type, 'ptm/show_ptm_time_slot_master', $res, "view");
    }

    public function getData($request)
    {
        $sub_institute_id = $request->session()->get('sub_institute_id');

        return ptmtimeslotmasterModel::select('ptm_time_slots_master.*', 'ptm_time_slots_master.standard_id',
            'standard.name as standard_name', 'division.name as division_name')
            ->join('standard', 'standard.id', '=', 'ptm_time_slots_master.standard_id')
            ->join('division', 'division.id', '=', 'ptm_time_slots_master.division_id')
            ->where(['ptm_time_slots_master.sub_institute_id' => $sub_institute_id])
            ->orderBy('ptm_time_slots_master.standard_id', 'asc')
            ->get();
    }

    public function create(Request $request)
    {
        $type = $request->input('type');
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $standard_data = standardModel::where(['sub_institute_id' => $sub_institute_id])->get();

        $data['menu'] = $standard_data;
        $data['menu1'] = [];

        return view('ptm/add_ptm_time_slot_master', $data);
    }

    public function store(Request $request)
    {
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $syear = $request->session()->get('syear');
        $created_by = $request->session()->get('user_id');
        $created_on = date('Y-m-d');
        $created_ip = $_SERVER['REMOTE_ADDR'];
        $ptm_date = date('Y-m-d', strtotime($request->get('ptm_date')));

        $from_time = $request->get('from_time');

        foreach ($from_time as $key => $val) {
            $from_time_Arr[] = [
                'from_time'        => $val,
                'to_time'          => $request->get('to_time')[$key],
                'ptm_date'         => $ptm_date,
                'title'            => $request->get('title'),
                'standard_id'      => $request->get('standard_id'),
                'division_id'      => $request->get('division_id'),
                'sub_institute_id' => $sub_institute_id,
                'syear'            => $syear,
                'created_by'       => $created_by,
                'created_ip'       => $created_ip,
            ];
        }
        
        ptmtimeslotmasterModel::insert($from_time_Arr);

        $res = [
            "status_code" => 1,
            "message"     => "PTM time slot Added Successfully",
        ];
        $type = $request->input('type');

        return is_mobile($type, "add_ptm_time_slot_master.index", $res, "redirect");
    }

    public function edit(Request $request, $id)
    {
        $type = $request->input('type');
        $data = ptmtimeslotmasterModel::find($id);
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $standard_data = standardModel::where(['sub_institute_id' => $sub_institute_id])->get();

        $division_data = $division_data = std_div_mappingModel::select('division.id', 'division.name')
            ->join('division', 'division.id', '=', 'std_div_map.division_id')
            ->where(['std_div_map.sub_institute_id' => $sub_institute_id, 'standard_id' => $data['standard_id']])
            ->get();

        view()->share('menu', $standard_data);
        view()->share('menu1', $division_data);

        return view('ptm/add_ptm_time_slot_master', ['data' => $data]);
    }

    public function update(Request $request, $id)
    {
        $ptm_date = date('Y-m-d', strtotime($request->get('ptm_date')));

        $data = [
            'from_time'   => $request->get('from_time')[0],
            'to_time'     => $request->get('to_time')[0],
            'ptm_date'    => $ptm_date,
            'title'       => $request->get('title'),
            'standard_id' => $request->get('standard_id'),
            'division_id' => $request->get('division_id'),
        ];

        ptmtimeslotmasterModel::where(["id" => $id])->update($data);

        $res = [
            "status_code" => 1,
            "message"     => "PTM time slot Updated Successfully",
        ];
        $type = $request->input('type');

        return is_mobile($type, "add_ptm_time_slot_master.index", $res, "redirect");
    }

    public function destroy(Request $request, $id)
    {
        $type = $request->input('type');

        ptmtimeslotmasterModel::where(["id" => $id])->delete();

        $message['status_code'] = "1";
        $message = [
            "message" => "PTM time slot Deleted successfully",
        ];

        return is_mobile($type, "add_ptm_time_slot_master.index", $message, "redirect");
    }

}
