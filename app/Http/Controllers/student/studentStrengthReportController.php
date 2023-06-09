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


    public function create(Request $request){
        // return $request;
$query = DB::table('standard')
    ->join('tblstudent_enrollment', 'tblstudent_enrollment.standard_id', '=', 'standard.id')
    ->join('tblstudent', 'tblstudent_enrollment.student_id', '=', 'tblstudent.id')
    ->join('division', 'tblstudent_enrollment.section_id', '=', 'division.id')
    ->select(
        'tblstudent.id as student_id',
        'standard.name as standard_name',
        'division.name as division_name',
        'tblstudent.sub_institute_id',
        DB::raw('COUNT(tblstudent.id) as total_students')
    )
    ->where('tblstudent_enrollment.sub_institute_id', session()->get('sub_institute_id'))
    ->where('tblstudent_enrollment.syear', session()->get('syear'))
    ->where('tblstudent.status', 1);

if (isset($request['religion'])) {
    $query->leftJoin('religion', 'tblstudent.religion', '=', 'religion.id');
    $query->whereIn('tblstudent.religion', $request['religion']);
}

$query->groupBy('standard.name', 'division.name', 'tblstudent.sub_institute_id');

$results = $query->get();

// Calculate religion counts separately for all students
if (isset($request['religion'])) {
    $religionCounts = [];
    foreach ($results as $result) {
        print_r($result);exit;
        $studentId = $result->student_id;
        foreach ($request['religion'] as $religionId) {
            $religionCount = $result->{'religion_' . $religionId} ?? 0;
            if (!isset($religionCounts[$studentId])) {
                $religionCounts[$studentId] = [];
            }
            $religionCounts[$studentId][$religionId] = $religionCount;
        }
    }
}

// Update the results with religion counts
foreach ($results as $result) {
    $studentId = $result->student_id;
    if (isset($religionCounts[$studentId])) {
        foreach ($religionCounts[$studentId] as $religionId => $count) {
            $result->{'religion_' . $religionId} = $count;
        }
    }
}

return $results;exit;

// Assuming $request holds the request parameters
// total = 3567 , getting = 3338
$query = DB::table('standard')
    ->join('tblstudent_enrollment', 'tblstudent_enrollment.standard_id', '=', 'standard.id')
    ->join('tblstudent', 'tblstudent_enrollment.student_id', '=', 'tblstudent.id')
    ->join('division', 'tblstudent_enrollment.section_id', '=', 'division.id')
    ->select(
        'standard.name as standard_name','division.name as division_name','tblstudent.sub_institute_id',
        DB::raw('COUNT(tblstudent.id) as total_students')
    )
    ->where('tblstudent_enrollment.sub_institute_id', session()->get('sub_institute_id'))
    ->where('tblstudent_enrollment.syear', session()->get('syear'))
    ->where('tblstudent.status', 1);
    // ->whereRaw('tblstudent_enrollment.sub_institute_id='.session()->get('sub_institute_id').' and tblstudent_enrollment.syear = "'.session()->get('syear').'"');

// Filter by start_date or admission_date
// if ($request['one_date'] === 'start') {
//     $query->whereDate('tblstudent_enrollment.start_date', $request['get_date']);
// } elseif ($request['one_date'] === 'add') {
//     $query->whereDate('tblstudent.admission_date', $request['get_date']);
// }

// Filter by religion
if (isset($request['religion'])) {
    $query->leftJoin('religion', 'tblstudent.religion', '=', 'religion.id');
    $query->whereIn('tblstudent.religion', $request['religion']);

    foreach ($request['religion'] as $religionId) {
        $query->addSelect(
            DB::raw("SUM(CASE WHEN religion.id = $religionId THEN 1 ELSE 0 END) as religion_$religionId")
        );
    }
}

// if (isset($request['religion'])) {
//     $query->leftJoin('religion', 'tblstudent.religion', '=', 'religion.id');
//     $query->whereIn('tblstudent.religion', $request['religion']);
//     foreach ($request['religion'] as $religionId) {
//         $query->addSelect(
//             DB::raw("SUM(CASE WHEN religion.id = $religionId THEN 1 ELSE 0 END) as religion_$religionId")
//         );
//     }
// }

// Filter by caste
if (isset($request['cast'])) {

    $query->join('caste', 'tblstudent.cast', '=', 'caste.id');
    $query->whereIn('tblstudent.cast', $request['cast']);
    foreach ($request['cast'] as $castId) {
        $query->addSelect(
            DB::raw("SUM(CASE WHEN caste.id = $castId THEN 1 ELSE 0 END) as cast_$castId")
        );
    }
}

// Filter by student_quota
if (isset($request['quota'])) {
    $query->join('student_quota', 'tblstudent_enrollment.student_quota', '=', 'student_quota.id');
    $query->whereIn('tblstudent_enrollment.student_quota', $request['quota']);
    foreach ($request['quota'] as $quotaId) {
        $query->addSelect(
            DB::raw("SUM(CASE WHEN tblstudent_enrollment.student_quota = $quotaId THEN 1 ELSE 0 END) as quota_$quotaId")
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
            $query->oRwhereDate('tblstudent.admission_date',  $request['get_date']);
            $query->oRwhere('tblstudent.sub_institute_id',session()->get('sub_institute_id'));
            $query->addSelect(
            DB::raw("SUM(CASE WHEN tblstudent.admission_date = '".$request['get_date']."' THEN 1 ELSE 0 END) as new_add")
        );
        } 
        if ($generalOption === 'take_lc') {
            $query->oRwhereDate('tblstudent_enrollment.end_date', date('y-m-d'));
            $query->oRwhere('tblstudent.sub_institute_id',session()->get('sub_institute_id'));
            $query->addSelect(
            DB::raw("SUM(CASE WHEN tblstudent_enrollment.end_date = '".date('y-m-d')."' THEN 1 ELSE 0 END) as take_lc ")
        );
        }
    }
}
    
// Add group by date, standard, and division
$query->groupBy('standard.name', 'division.name');
$query->orderByRaw('standard.id,division.id');

// DB::enableQueryLog();
// Retrieve the results
$res['result'] = $query->get();
         $res['status_code'] = 1;
        $res['message'] = "Success";
        $res['one_date'] = $request->one_date;
        $res['standard'] = $request->standard_wise;
        $res['date'] = $request->get_date;
        $res['general'] = $request->general;
        $res['strength'] = $request->strength;
// return $res['strength'];exit;
        $res['religion'] = $request->religion;
        $res['cast'] = $request->cast;
        $res['quota'] = $request->quota;

        // return $res;exit;
        $type = $request->type;
        return is_mobile($type, "student/student_strength_report", $res, "view");

    }
}
