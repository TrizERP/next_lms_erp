<?php

namespace App\Http\Controllers\inward_outward;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\inward_outward\outwardModel;
use App\Models\inward_outward\place_masterModel;
use App\Models\inward_outward\physical_file_locationModel;
use Illuminate\Support\Facades\DB;

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
        
        $outward = DB::select('SELECT outward.*, place_master.title AS place_id, physical_file_location.title AS file_name,
                     physical_file_location.file_location AS file_location_id, 
                     date_format(outward.outward_date,"%d-%m-%Y") AS outward_date
                    FROM `outward`
                    INNER JOIN `place_master` ON `outward`.`place_id` = `place_master`.`id`
                    INNER JOIN `physical_file_location` ON `outward`.`file_location_id` = `physical_file_location`.`id`
                    WHERE `outward`.`sub_institute_id` = "'.$sub_institute_id.'" ');

        $outward_data['status_code'] = 1;
        $outward_data['data'] = $outward;
        $type = $request->input('type');
        return \App\Helpers\is_mobile($type, "inward_outward/show_outward_report", $outward_data, "view");

    }
}
