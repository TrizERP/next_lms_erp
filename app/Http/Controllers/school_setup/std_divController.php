<?php

namespace App\Http\Controllers\school_setup;

use App\Http\Controllers\Controller;
use App\Models\school_setup\std_div_mappingModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use function App\Helpers\is_mobile;
use function App\Helpers\ValidateInsertData;

class std_divController extends Controller
{
    public function index(Request $request)
    {
        $data = $this->getData($request);
        $type = $request->input('type');
        $res['status_code'] = 1;
        $res['message'] = "SUCCESS";
        $res['data'] = $data;

        return is_mobile($type, 'school_setup/std_div_map', $res, "view");
    }

    public function getData(Request $request)
    {
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $marking_period_id = session()->get('term_id');

        $std_data = DB::table('standard')->select('id',
            'name')->where(['sub_institute_id' => $sub_institute_id])
            // ->when($marking_period_id,function($query) use($marking_period_id){
            //     $query->where('marking_period_id',$marking_period_id);
            // })
            ->get();
        $div_data = DB::table('division')->select('id',
            'name')->where(['sub_institute_id' => $sub_institute_id])->get();
        $std_div_map_data = DB::table('std_div_map')->select('standard_id',
            'division_id')->where(['sub_institute_id' => $sub_institute_id])->get()->toArray();
        $new_arr = array();
        if (! empty($std_div_map_data)) {
            foreach ($std_div_map_data as $key => $val) {
                $new_arr[$val->standard_id][] = $val->division_id;
            }
        }
        $data['std_data'] = $std_data;
        $data['div_data'] = $div_data;
        $data['std_div_map_data'] = $new_arr;

        return $data;
    }

    public function store(Request $request)
    {
        ValidateInsertData('std_div_map', $request);
        $sub_institute_id = $request->session()->get('sub_institute_id');
        std_div_mappingModel::where('sub_institute_id', $sub_institute_id)->delete();
        $type = $request->input('type');
        $isImplementation = $request->input('isImplementation');
        if ($type == "API") {
            $division_id = json_decode($request->division_id, true);
            $sub_institute_id = $request->input('sub_institute_id');
        } else {
            $division_id = $request->division_id;
            $sub_institute_id = $request->session()->get('sub_institute_id');
        }

        foreach ($division_id as $std => $divArr) {
            foreach ($divArr as $divkey => $divval) {
                $finalArray[] = [
                    'standard_id'      => $std,
                    'division_id'      => $divval,
                    'sub_institute_id' => $sub_institute_id,
                ];
            }
        }

        std_div_mappingModel::insert($finalArray);
        $data = $this->getData($request);
        $res['status_code'] = 1;
        $res['message'] = "Division Mapped Successfully.";
        $res['data'] = $data;

        // if (isset($isImplementation)) {
        // 	$res['isImplementation'] = "1";
        // 	return is_mobile($type, "add_implementation.index", $res);
        // } else {
        // 	return is_mobile($type, "school_setup/std_div_map", $res, "view");
        // }
        return is_mobile($type, "std_div_map.index", $res);
    }
}
