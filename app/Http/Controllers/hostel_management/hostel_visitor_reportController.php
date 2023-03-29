<?php

namespace App\Http\Controllers\hostel_management;

use App\Http\Controllers\Controller;
use App\Models\hostel_management\hostel_visitor_masterModel;
use Illuminate\Http\Request;
use function App\Helpers\is_mobile;

class hostel_visitor_reportController extends Controller
{
    public function index(Request $request)
    {
        if (session()->has('data')) { // check if it exists
            $data_arr = session('data'); // to retrieve value
            if (isset($data_arr['message'])) {
                $visitor_data['message'] = $data_arr['message'];
            }
        }

        $sub_institute_id = $request->session()->get('sub_institute_id');

        $data = hostel_visitor_masterModel::where(['sub_institute_id' => $sub_institute_id])->get();
        $visitor_data['status_code'] = 1;
        $visitor_data['data'] = $data;
        $type = $request->input('type');

        return is_mobile($type, "hostel_management/show_hostel_visitor_report", $visitor_data, "view");
    }
}
