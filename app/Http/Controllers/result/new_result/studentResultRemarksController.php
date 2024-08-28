<?php

namespace App\Http\Controllers\result\new_result;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use function App\Helpers\is_mobile;
use DB;

class studentResultRemarksController extends Controller
{
    public function index(Request $request)
    {
        $type = $request->input('type');
        $res['sub_institute_id'] = session()->get('sub_institute_id');
        $res['syear'] = session()->get('syear');

        return is_mobile($type, "result/new_result/student_result_remarks/show", $res, "view");
    }

    public function create(Request $request)
    {
        $type = $request->input('type');
        $sub_institute_id = session()->get('sub_institute_id');
        $syear = session()->get('syear');
        $grade = $request->input('grade');
        $standard_id = $request->input('standard');
        $division_id = $request->input('division');
        $term = $request->input('term');

        $get_students = DB::table('tblstudent_enrollment as te')
            ->selectRaw('u.*,te.roll_no, te.id as gr_number, rr.result_remarks')
            ->join('tblstudent as u', function ($join) {
                $join->on('u.id', '=', 'te.student_id');
            })
            ->leftJoin('result_remarks as rr', function ($join) use ($syear) {
                $join->on('rr.student_id', '=', 'te.student_id')
                ->where(['rr.syear' => $syear]);
            })
            ->where(['te.standard_id' => $standard_id])
            ->where(['te.section_id' => $division_id])
            ->where(['te.syear' => $syear])
            ->where(['te.sub_institute_id' => $sub_institute_id])
            ->whereNull('te.end_date')
            ->orderBy('te.roll_no', 'asc')
            ->get()->toArray();
        
        $res['grade_id'] = $grade;
        $res['standard_id'] = $standard_id;
        $res['division_id'] = $division_id;
        $res['term_id'] = $term;
        $res['get_students'] = $get_students;

        return is_mobile($type, "result/new_result/student_result_remarks/show", $res, "view");
    }

    public function store(Request $request)
    {
        $type = $request->input('type');
        $syear = $request->session()->get('syear');
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $user_id = $request->session()->get('user_id');
        $student_ids = $request->get('student_id');
        $result_remarks = $request->get('result_remarks');
        $grade_id = $request->get('grade_id');
        $standard_id = $request->get('standard_id');
        $division_id = $request->get('division_id');
        $term_id = $request->get('term_id');

        foreach($student_ids as $student_id)
        {
            $get_result_remarks = DB::table('result_remarks')->where(['student_id' => $student_id, 'sub_institute_id' => $sub_institute_id, 'syear' => $syear, 'term_id' => $term_id])->first();
   
            if(isset($result_remarks[$student_id]))
            {
                if(isset($get_result_remarks))
                {
                    DB::table('result_remarks')
                    ->where('student_id', $get_result_remarks->student_id)
                    ->update([
                        'result_remarks' => $result_remarks[$student_id] ?? '',
                        'updated_at' => now(),
                    ]);
                } 
                else
                {
                    DB::table('result_remarks')
                    ->insert([
                        'student_id' => $student_id,
                        'result_remarks' => $result_remarks[$student_id] ?? '',
                        'term_id' => $term_id,
                        'syear' => $syear,
                        'sub_institute_id' => $sub_institute_id,
                        'created_by' => $user_id,
                        'created_at' => now(),
                    ]);
                }
            }
        }

        $request->session()->flash('success', 'Student result remarks added & updated successfully.');
        
        return is_mobile($type, "student-result-remarks.index", null, "redirect");
    }
}
