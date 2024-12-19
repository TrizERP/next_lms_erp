<?php

namespace App\Http\Controllers\lms;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use function App\Helpers\is_mobile;
use Illuminate\Support\Facades\Http;
use App\Http\Controllers\result\result_api\resultAPIController;
use DB;

class lmsDashboardController extends Controller
{
    public function index(Request $request)
    {
        $userProfile = session()->get('user_profile_name');
        return $this->getDashboard($request, $userProfile);
    }

    public function getDashboard(Request $request, $userProfile)
    {
        $res = session()->get('data');
        $type = $request->input('type');
        $sub_institute_id = session()->get('sub_institute_id');
        $syear = session()->get('syear');
        
        if ($userProfile == "Student") {
            $user_id = session()->get('user_id');
        } else {
            $user_id = $request->students_id;
        }
        
        if ($type == "API") {
            $sub_institute_id = $request->sub_institute_id;
            $syear = $request->syear;
            $user_id = $request->user_id;
        }

        // Get standard data
        $res['standardData'] = DB::table('tblstudent_enrollment as se')
            ->join('tblstudent as s', 's.id', '=', 'se.student_id')
            ->join('standard as std', 'std.id', '=', 'se.standard_id')
            ->selectRaw('se.student_id as id, s.enrollment_no, std.id as standardId, std.name as standardName, se.syear')
            ->where('se.student_id', $user_id)
            ->orderBy('se.syear')
            ->get()->toArray();
        
        // Get current data
        $currentData = DB::table('tblstudent_enrollment as se')
            ->join('tblstudent as s', 's.id', '=', 'se.student_id')
            ->join('standard as std', 'std.id', '=', 'se.standard_id')
            ->selectRaw('se.student_id as id, s.enrollment_no, std.id as standardId, std.name as standardName, se.syear, se.standard_id')
            ->where('se.student_id', $user_id)
            ->where('se.syear', $syear)
            ->orderBy('se.id', 'DESC')
            ->get()->take(1);

        $resultAPIController = new resultAPIController;

        // Check for current data and call result personalization if available
        if (isset($currentData[0]->id)) {
            $request2 = new Request(['type' => "API", 'sub_institute_id' => $sub_institute_id, 'enrollment_no' => $currentData[0]->enrollment_no]);
            $res['previousData'] = $resultAPIController->resultPersonalize($request2);

            $request3 = new Request(['type' => "API", 'sub_institute_id' => $sub_institute_id, 'enrollment_no' => $currentData[0]->enrollment_no, 'student_id' => $currentData[0]->id, 'standard' => $currentData[0]->standard_id, 'syear' => $syear]);
            $res['selectedCurrentData'] = $resultAPIController->currentResult($request3);
            $res['currentStandard'] = $currentData[0]->standard_id;
            $res['currentStudentId'] = $currentData[0]->id;
        }

        // Get student data
        $res['studentData'] = DB::table('tblstudent')->where('id', $user_id)->first();
        $res['standardCount'] = count($res['standardData']);
        $res['user_id'] = $user_id;
        $res['sub_institute_id'] = $sub_institute_id;

        // Additional data for teacher
        if ($userProfile != "Student") {
            $res['studentDetails'] = 1;
            $res['grade'] = $request->grade;
            $res['standard'] = $request->standard;
            $res['division'] = $request->division;
            $res['students_id'] = $request->students_id;
        }
        // echo "<pre>";print_r($res);exit;
        if ($userProfile == "Student") {
            return is_mobile($type, "lms/lmsDashboard", $res, "view");
        } else {
            return is_mobile($type, "lms/lmsDashboardTeacher", $res, "view");
        }
    }

}
