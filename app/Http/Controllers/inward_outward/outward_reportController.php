<?php

namespace App\Http\Controllers\inward_outward;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use function App\Helpers\is_mobile;

class outward_reportController extends Controller
{
    public function index(Request $request)
    {
        if (session()->has('data')) { // check if it exists
            $data_arr = session('data'); // to retrieve value
            if (isset($data_arr['message'])) {
                $outward_data['message'] = $data_arr['message'];
            }
        }

        $sub_institute_id = $request->session()->get('sub_institute_id');
        $syear = $request->session()->get('syear');

        $outward = DB::table("outward")
            ->join('place_master', function ($join) {
                $join->whereRaw("outward.place_id = place_master.id");
            })
            ->join('physical_file_location', function ($join) {
                $join->whereRaw("outward.file_location_id = physical_file_location.id");
            })
            ->selectRaw('outward.*, place_master.title AS place_id, physical_file_location.title AS file_name,
            physical_file_location.file_location AS file_location_id, 
            date_format(outward.outward_date,"%d-%m-%Y") AS outward_date')
            ->where("outward.sub_institute_id", "=", $sub_institute_id)
            ->where("outward.syear", "=", $syear)
            ->get()->toArray();

        $outward_data['status_code'] = 1;
        $outward_data['data'] = $outward;
        $type = $request->input('type');

        return is_mobile($type, "inward_outward/show_outward_report", $outward_data, "view");
    }
}
