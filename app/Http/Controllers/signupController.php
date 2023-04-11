<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use function App\Helpers\is_mobile;

class signupController extends Controller
{

    public function index(Request $request)
    {
        $type = $request->input('type');
        $res['status_code'] = 1;

        return is_mobile($type, "signup", $res, "view");
    }

    public function get_trizStandard(Request $request)
    {
        $data = DB::table('standard as s')
            ->join('academic_section as a', function ($join) {
                $join->whereRaw('s.sub_institute_id = a.sub_institute_id AND s.grade_id = a.id');
            })->where("s.sub_institute_id", '=', '1')->where('a.title', '!=', 'OTHERS')
            ->selectRaw('s.*')
            ->get()->toArray();

        return json_decode(json_encode($data), true);
    }
}
