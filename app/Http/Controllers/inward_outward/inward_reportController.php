<?php

namespace App\Http\Controllers\inward_outward;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use function App\Helpers\is_mobile;

class inward_reportController extends Controller
{
    public function index(Request $request)
    {
        if (session()->has('data')) {
            $data_arr = session('data');
            if (isset($data_arr['message'])) {
                $inward_data['message'] = $data_arr['message'];
            }
        }
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $syear = $request->session()->get('syear');

        $inward = DB::table("inward")
            ->join('place_master', function ($join) {
                $join->whereRaw("inward.place_id = place_master.id");
            })
            ->join('physical_file_location', function ($join) {
                $join->whereRaw("inward.file_location_id = physical_file_location.id");
            })
            ->selectRaw('inward.*, place_master.title AS place_id, physical_file_location.title AS file_name,
                physical_file_location.file_location AS file_location_id, 
                date_format(inward.inward_date,"%d-%m-%Y") AS inward_date')
            ->where("inward.sub_institute_id", "=", $sub_institute_id)
            ->where("inward.syear", "=", $syear)
            ->get()->toArray();

        $inward_data['status_code'] = 1;
        $inward_data['data'] = $inward;
        $type = $request->input('type');

        return is_mobile($type, "inward_outward/show_inward_report", $inward_data, "view");
    }
}
