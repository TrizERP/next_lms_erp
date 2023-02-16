<?php

namespace App\Http\Controllers\inward_outward;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use App\Models\inward_outward\inwardModel;
use App\Models\inward_outward\place_masterModel;
use App\Models\inward_outward\physical_file_locationModel;

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
        $inward = DB::select('SELECT inward.*, place_master.title AS place_id, physical_file_location.title AS file_name,
                     physical_file_location.file_location AS file_location_id, 
                     date_format(inward.inward_date,"%d-%m-%Y") AS inward_date
                    FROM `inward`
                    INNER JOIN `place_master` ON `inward`.`place_id` = `place_master`.`id`
                    INNER JOIN `physical_file_location` ON `inward`.`file_location_id` = `physical_file_location`.`id`
                    WHERE `inward`.`sub_institute_id` = "'.$sub_institute_id.'" ');

        $inward_data['status_code'] = 1;
        $inward_data['data'] = $inward;
        $type = $request->input('type');
        return \App\Helpers\is_mobile($type, "inward_outward/show_inward_report", $inward_data, "view");

    }
}
