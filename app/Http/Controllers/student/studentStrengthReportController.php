<?php

namespace App\Http\Controllers\student;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use function App\Helpers\is_mobile;

class studentStrengthReportController extends Controller
{
    //
    public function index(Request $request){
        $type = $request->input('type');
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $syear = $request->session()->get('syear');

        $res['status_code'] = 1;
        $res['message'] = "Success";

        return is_mobile($type, "student/student_strength_report", $res, "view");
    }
}
