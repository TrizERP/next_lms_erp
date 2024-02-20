<?php

namespace App\Http\Controllers\result\new_result;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use function App\Helpers\is_mobile;
use App\Models\result\result_template;
use App\Models\school_setup\standardModel;
use function App\Helpers\SearchStudent;
use function App\Helpers\getStudents;
use DB;

class studentResultRemarksController extends Controller
{
    public function index(Request $request)
    {
        $type = $request->input('type');
        $sub_institute_id = session()->get('sub_institute_id');
        $syear = session()->get('syear');

        $all_term = DB::table('academic_year')->where('sub_institute_id', $sub_institute_id)->where('syear', $syear)->get()->toArray();
       
        $get_standards = standardModel::where(['sub_institute_id' => $sub_institute_id])->get()->toArray();
        $get_divisions = DB::table('division')->where(['sub_institute_id' => $sub_institute_id])->get()->toArray();

        $res['get_standards'] = $get_standards;
        $res['get_divisions'] = $get_divisions;
        $res['all_term'] = $all_term;

        return is_mobile($type, "result/new_result/student_result_remarks/show", $res, "view");
    }

    public function create(Request $request)
    {
        $type = $request->input('type');
        $sub_institute_id = session()->get('sub_institute_id');
        $syear = session()->get('syear');
        $standard_id = $request->input('standard_id');
        $division_id = $request->input('division_id');
        $term_id = $request->input('all_term') ?? 0;

        $all_term = DB::table('academic_year')->where('sub_institute_id', $sub_institute_id)->where('syear', $syear)->get()->toArray();

        $get_standards = standardModel::where(['sub_institute_id' => $sub_institute_id])->get()->toArray();
        
        $get_divisions = DB::table('division')->where(['sub_institute_id' => $sub_institute_id])->get()->toArray();

        $get_students = DB::table('tblstudent_enrollment as te')
            ->selectRaw('u.*, te.id as gr_number, rr.result_remarks')
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
            ->orderBy('u.roll_no', 'asc')
            ->get()->toArray();
            
        $res['get_standards'] = $get_standards;
        $res['get_divisions'] = $get_divisions;
        $res['all_term'] = $all_term;
        $res['standard_id'] = $standard_id;
        $res['division_id'] = $division_id;
        $res['get_students'] = $get_students;
        $res['term_id'] = $term_id;

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
        $term_id = $request->input('term_id') ?? 0;

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
