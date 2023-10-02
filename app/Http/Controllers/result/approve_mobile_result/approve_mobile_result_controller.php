<?php

namespace App\Http\Controllers\result\approve_mobile_result;

use App\Http\Controllers\Controller;
use GenTux\Jwt\GetsJwtToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use function App\Helpers\getStudents;
use function App\Helpers\is_mobile;
use function App\Helpers\SearchStudent;

class approve_mobile_result_controller extends Controller
{
    use GetsJwtToken;

    public function index(Request $request)
    {
        $type = $request->input('type');
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $syear = $request->session()->get('syear');
        $term_id = $request->input('term_id');

        $res['terms'] = DB::table('academic_year')->where('sub_institute_id', $sub_institute_id)->where('syear', $syear)->get()->toArray();

        $res['status_code'] = "1";
        $res['message'] = "Success";
        $res['syear'] = $syear;
        $res['sub_institute_id'] = $sub_institute_id;
        $res['term_id'] = $term_id;

        return is_mobile($type, "result/approve_mobile_result/approve_mobile_result", $res, "view");
    }

    public function create(Request $request)
    {
        $type = $request->input('type');
        $grade_id = $request->input('grade');
        $standard_id = $request->input('standard');
        $division_id = $request->input('division');
        $term_id = $request->input('term_id');
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $syear = $request->session()->get('syear');
        $marking_period_id = session()->get('term_id');

        $result = DB::table('result_html as rh')
            ->join('tblstudent as ts', function ($join) {
                $join->whereRaw('rh.student_id = ts.id');
            })
            ->join('tblstudent_enrollment as se', function ($join) {
                $join->whereRaw('se.student_id = ts.id');
            })
            ->join('standard as s', function ($join) use ($standard_id) {
                $join->whereRaw('s.id = se.STANDARD_ID');
            })
            ->join('division as d', function ($join) {
                $join->whereRaw('d.id = se.SECTION_ID');
            })
            ->selectRaw("rh.*, ts.id, ts.enrollment_no, CONCAT_WS(' ', ts.first_name, ts.last_name) AS student_name, s.name AS standard, d.name AS division")
            ->where('ts.sub_institute_id', $sub_institute_id)
            ->where('rh.syear', $syear)
            ->where('rh.term_id', $term_id)
            ->where('rh.standard_id', $standard_id)
            ->where('rh.division_id', $division_id)
            ->where('rh.grade_id', $grade_id);

        $result = $result->groupBy('rh.id')->get()->toArray();
        
        $res['status_code'] = 1;
        $res['message'] = "Success";
        $res['result_report'] = $result;
        $res['grade_id'] = $grade_id;
        $res['standard_id'] = $standard_id;
        $res['division_id'] = $division_id;
        $res['term_id'] = $term_id;
        $res['syear'] = $syear;
        $res['sub_institute_id'] = $sub_institute_id;

        return is_mobile($type, "result/approve_mobile_result/approve_mobile_result", $res, "view");
    }

    public function store(Request $request)
    {
        $type = $request->input('type');
        $student_ids = $request->input('students');
        $student_id_uncheck = $request->input('student_id');
        $syear = $request->session()->get('syear');
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $grade_id = $request->input('grade_id');
        $standard_id = $request->input('standard_id');
        $division_id = $request->input('division_id');
        $term_id = $request->input('term_id');

        foreach($student_ids as $student_id)
        {
            DB::table('result_html')
            ->where([
                'student_id' => $student_id,
                'grade_id' => $grade_id,
                'standard_id' => $standard_id,
                'division_id' => $division_id,
                'term_id' => $term_id,
                'syear' => $syear,
                'sub_institute_id' => $sub_institute_id,
            ])
            ->update(['is_allowed' => 'Y']);   
        }

        foreach($student_id_uncheck as $student_id)
        {
            DB::table('result_html')
            ->where([
                'student_id' => $student_id,
                'grade_id' => $grade_id,
                'standard_id' => $standard_id,
                'division_id' => $division_id,
                'term_id' => $term_id,
                'syear' => $syear,
                'sub_institute_id' => $sub_institute_id,
            ])
            ->update(['is_allowed' => 'N']);
        }

        $res['status_code'] = 1;
        $res['message'] = "Success";

        return is_mobile($type, "approve_mobile_result.index", $res);
    }
}
