<?php

namespace App\Http\Controllers\student;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use function App\Helpers\aut_token;
use function App\Helpers\is_mobile;
use function App\Helpers\SearchStudent;

class yearly_attendance_controller extends Controller
{
    public function index(Request $request)
    {
        $type = $request->input('type');

        $res['status_code'] = 1;
        $res['message'] = "Success";
        return is_mobile($type, "student/yearly_attendance_report", $res, "view");

    }

    public function showYearlyStudentAttendance(Request $request)
    {

        $type = $request->input('type');
        $from_date = $request->input('from_date');
        $to_date = $request->input('to_date');
        $grade_id = $request->input("grade");
        $standard_id = $request->input("standard");
        $division_id = $request->input("division");
        // $selected_year = $request->input("year");
        $syear = $request->session()->get('syear');
        $term_id = $request->session()->get('term_id');
        $sub_institute_id = $request->session()->get('sub_institute_id');

        $student_data = SearchStudent($grade_id, $standard_id, $division_id);

        // $from_date = $selected_year . "-" . $from_month . "-01";
        // $to_date = date('Y-m-t', strtotime($selected_year . "-" . $to_month));


        $whereAtt['syear'] = $syear;
        $whereAtt['sub_institute_id'] = $sub_institute_id;
         // $whereAtt['term_id'] = $term_id;
         if(isset($standard_id)){
            $whereAtt['standard_id'] = $standard_id;
        }
        if(isset($division_id)){
            $whereAtt['section_id'] = $division_id;    
        }
        
        $attendanceData = DB::table("attendance_student")
            ->where($whereAtt)
            ->whereBetween("attendance_date", [$from_date, $to_date])
            ->get()
            ->toArray();

        // dd(DB::getQueryLog());
        // echo "<pre>";print_r($attendanceData);
      
        $finalAttendanceArray = array();
        $count = array();

        foreach ($attendanceData as $key => $value) {
            if ($value->attendance_code == "P") {
                $count[$value->student_id][(int)date('m', strtotime($value->attendance_date))][(int)date('d', strtotime($value->attendance_date))] = $value->attendance_code;
                $finalAttendanceArray[$value->student_id][(int)date('m', strtotime($value->attendance_date))] = count($count[$value->student_id][(int)date('m', strtotime($value->attendance_date))]);
            }
            $Wcount[(int)date('m', strtotime($value->attendance_date))][(int)date('d', strtotime($value->attendance_date))] = $value->attendance_code;
            $working[(int)date('m', strtotime($value->attendance_date))] = count($Wcount[(int)date('m', strtotime($value->attendance_date))]);

        }

// echo "<pre>";print_r($working);exit;
        foreach ($finalAttendanceArray as $student_id => $months) {
            for ($i = 1; $i <= 12; $i++) {
                if (!isset($months[$i])) {
                    $finalAttendanceArray[$student_id][$i] = 0;
                    // $working[$i] = 0;
                }
            }
            ksort($finalAttendanceArray[$student_id]);
        }

        $monthss = [1 => 1, 2 => 2, 3 => 3, 4 => 4, 5 => 5, 6 => 6, 7 => 7, 8 => 8, 9 => 9, 10 => 10, 11 => 11, 12 => 12];


        $start_date = strtotime($from_date);
        $end_date = strtotime($to_date);

        $month = array();

        while ($start_date <= $end_date) {
            $month1 = date('n', $start_date);
            if (!in_array($month1, $month)) {
                $month[] = $month1;
            }
            $start_date = strtotime('+1 month', $start_date);
        }

        // $image_path = "http://" . $_SERVER['HTTP_HOST']."/storage/fees/" . $receipt_book_arr->receipt_logo;
        // return $image_path;exit;
        if (count($attendanceData) == 0) {
            $res['status_code'] = 0;
            $res['message'] = "No Attendance Found";
        }else{
            $res['status_code'] = 1;
            $res['message'] = "Success";
        }
       
        $res['month'] = $month;
        $res['to_month'] = (int)date('m', strtotime($to_date));
        // echo "<pre>";print_r($month);exit;
        $res['grade_id'] = $grade_id;
        $res['standard_id'] = $standard_id;
        $res['division_id'] = $division_id;
        $res['student_data'] = $student_data;
        $res['attendance_data'] = $finalAttendanceArray;
        $res['working_day'] = $working ?? '';
        $res['to_date'] = $to_date;
        $res['from_date'] = $from_date;
        // echo "<pre>";print_r($working);exit;
        return is_mobile($type, "student/yearly_attendance_report", $res, "view");
    }
}
