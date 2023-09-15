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

    public function create(Request $request)
    {
        $query = DB::table('standard')
        ->leftJoin('tblstudent_enrollment', function ($join) use($request){
            $join->on('tblstudent_enrollment.standard_id', '=', 'standard.id')
                ->where('tblstudent_enrollment.sub_institute_id', session()->get('sub_institute_id'))
                ->when(!isset($request['general']), function ($query) {
                    return $query->whereNull('tblstudent_enrollment.end_date');
                })
                ->where('tblstudent_enrollment.syear', session()->get('syear'));
        })
        ->leftJoin('tblstudent', 'tblstudent_enrollment.student_id', '=', 'tblstudent.id')
        ->Join('division', 'tblstudent_enrollment.section_id', '=', 'division.id')
        ->select(
            'standard.name as standard_name',
            'division.name as division_name',
            'tblstudent.sub_institute_id',
            DB::raw('COUNT(tblstudent.id) as total_students')
        );
        // ->where('standard.name', '!=', 'Nursery')
        // ->orWhere('standard.name', '1');
       // Add group by date, standard, and division
       if(!in_array('division',$request['standard_wise']) ){
            $query->groupBy('standard.name');
       }else{
            $query->groupBy('standard.name', 'division.name');
       }
       
        $query->orderByRaw('standard.id,division.id');
        // Filter by start_date or admission_date
        if ($request['one_date'] === 'start') {
            $query->whereBetween('tblstudent_enrollment.start_date', [date('Y-m-d', strtotime($request['from_date'])), date('Y-m-d', strtotime($request['to_date']))]);

        } elseif ($request['one_date'] === 'add') {
            $query->whereBetween('tblstudent.admission_date', [date('Y-m-d', strtotime($request['from_date'])), date('Y-m-d', strtotime($request['to_date']))]);

        }
    
        // Filter by religion
        if (isset($request['religion'])) {
            $query->leftJoin('religion', 'tblstudent.religion', '=', 'religion.id');
            $query->whereIn('tblstudent.religion', $request['religion']);
            foreach ($request['religion'] as $religionId) {
                $query->addSelect(
                    DB::raw("SUM(CASE WHEN religion.id = $religionId and tblstudent.gender = 'M' THEN 1 ELSE 0 END) as m_religion_$religionId")
                );

                $query->addSelect(
                    DB::raw("SUM(CASE WHEN religion.id = $religionId and tblstudent.gender = 'F' THEN 1 ELSE 0 END) as f_religion_$religionId")
                );
            }
        }
    
        // Filter by caste
        if (isset($request['cast'])) {
            $castId = implode(",", $request['cast']);
            $query->leftJoin('caste', 'tblstudent.cast', '=', 'caste.id');
            $query->whereRaw('tblstudent.cast IN (' . $castId . ')');
            foreach ($request['cast'] as $castId) {
                $query->addSelect(
                    DB::raw("SUM(CASE WHEN caste.id = $castId and tblstudent.gender = 'M' THEN 1 ELSE 0 END) as m_cast_$castId")
                );

                $query->addSelect(
                    DB::raw("SUM(CASE WHEN caste.id = $castId and tblstudent.gender = 'F' THEN 1 ELSE 0 END) as f_cast_$castId")
                );
            }
        }
    
        // Filter by student_quota
        if (isset($request['quota'])) {
            $quotaId = implode(",", $request['quota']);
            $query->leftJoin('student_quota', 'tblstudent_enrollment.student_quota', '=', 'student_quota.id');
            $query->whereRaw('tblstudent_enrollment.student_quota IN (' . $quotaId . ')');
            foreach ($request['quota'] as $quotaId) {
                $query->addSelect(
                    DB::raw("SUM(CASE WHEN tblstudent_enrollment.student_quota = $quotaId and tblstudent.gender = 'M' THEN 1 ELSE 0 END) as m_quota_$quotaId")
                );

                $query->addSelect(
                    DB::raw("SUM(CASE WHEN tblstudent_enrollment.student_quota = $quotaId and tblstudent.gender = 'F' THEN 1 ELSE 0 END) as f_quota_$quotaId")
                );
            }
        }
    
        // Filter by strength (M/F)
        if (isset($request['strength'])) {
            foreach ($request['strength'] as $gender) {
                $query->addSelect(
                    DB::raw("SUM(CASE WHEN tblstudent.gender = '$gender' THEN 1 ELSE 0 END) as $gender")
                );
            }
        }
    
        // Filter by general options
        if (isset($request['general'])) {
            foreach ($request['general'] as $generalOption) {
                if ($generalOption === 'new_add') {
                    $query->WhereBetween('tblstudent.admission_date', [date('Y-m-d', strtotime($request['from_date'])), date('Y-m-d', strtotime($request['to_date']))]);

                    $query->addSelect(
                        DB::raw("SUM(CASE WHEN tblstudent.admission_date BETWEEN '" . date('y-m-d', strtotime($request['from_date'])) . "' AND '" . date('y-m-d', strtotime($request['to_date'])) . "' THEN 1 ELSE 0 END) as new_add")
                    );
                }
                if ($generalOption === 'take_lc') {
                    $query->WhereBetween('tblstudent_enrollment.end_date', [date('Y-m-d', strtotime($request['from_date'])), date('Y-m-d', strtotime($request['to_date']))]);
                    
                    $query->addSelect(
                        DB::raw("SUM(CASE WHEN tblstudent_enrollment.end_date BETWEEN '" . date('y-m-d', strtotime($request['from_date'])) . "' AND '" . date('y-m-d', strtotime($request['to_date'])) . "' THEN 1 ELSE 0 END) as take_lc")
                    );
                }
            }
        }
        // Retrieve the results
        $res['result'] = $query->get();

        // return $request->one_date;exit;
        $res['status_code'] = 1;
        $res['message'] = "Success";
        $res['one_date'] = $request->one_date;
        $res['standard'] = $request->standard_wise;
        $res['from_date'] = $request->from_date;
        $res['to_date'] = $request->to_date;        
        $res['general'] = $request->general;
        $res['strength'] = $request->strength;
        $res['religion'] = $request->religion;
        $res['cast'] = $request->cast;
        $res['quota'] = $request->quota;
    
        $type = $request->type;
        return is_mobile($type, "student/student_strength_report", $res, "view");
    }
    

}
