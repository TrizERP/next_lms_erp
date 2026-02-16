<?php

namespace App\Http\Controllers\front_desk;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use function App\Helpers\is_mobile;
use function App\Helpers\SearchStudent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class classFaceAttendanceController extends Controller
{
    public function index(Request $request)
    {
        // return "Hello";
        $type = $request->type;
        $res['message'] = 'Hello';
        return is_mobile($type, 'front_desk/faceAttendance/classAttendance/index', $res, 'view');
    }

    public function store(Request $request)
    {
        // return $request;
        $type = $request->type;
        $sub_institute_id = session()->get('sub_institute_id');
        $syear = session()->get('syear');
        $user_id = session()->get('user_id');
        $user_profile_id = session()->get('user_profile_id');
        $date = $request->date;
        $standard_division = explode('||', $request->standard_division);
        $standard_id = $standard_division[0] ?? 0;
        $division_id = $standard_division[1] ?? 0;
        $getAllStudents = SearchStudent('', $standard_id, $division_id);

        // Array to store student_id and API response
        $harshitApiResponses = [];

        // Call harshit API outside of getAllStudents loop
            $url = 'https://harshit20991999-face-recognition-attandance-project.hf.space/api/v1/attendance';
            $postData = [
                'type' => 'API',
                'sub_institute_id' => $sub_institute_id,
                'syear' => $syear,
                'date' => $date,
                'user_profile_name' => 'admin',
                'files'=>$request->images,
                'submit' => 1,
            ];

            $response = \Illuminate\Support\Facades\Http::asForm()
                ->withOptions(['verify' => false])
                ->post($url, $postData)
                ->body();

            $data = json_decode($response, true);
            $matchedStudents = [];
            if(isset($data['matchedStudent']) && !empty($data['matchedStudent'])){
                foreach($data['matchedStudent'] as $key=>$value){
                    $matchedStudents[$value] = 'P';
                }
            }
        // teacher captured photos
        foreach ($request->images as $image) {
            $file_name = $file_size = $ext = "";
            $random_no = rand(10000, 99999);
            $originalname = $image->getClientOriginalName();
            $file_size = $image->getSize();
            $name = $standard_id . '-' . $division_id . '_' . $random_no;
            $ext = File::extension($originalname);
            $file_name = $name . '.' . $ext;
            $file_folder = '/capture_attendance/' . $date . '/' . $standard_id . '-' . $division_id;
            File::makeDirectory($file_folder, $mode = 0777, true, true);
            $path = $image->storeAs(
                'public/capture_attendance/' . $date . '/' . $standard_id . '-' . $division_id,
                $file_name
            );

            DB::table('student_capture_attendance')->insert([
                'sub_institute_id' => $sub_institute_id,
                'syear' => $syear,
                'date' => $date,
                'standard_id' => $standard_id,
                'division_id' => $division_id,
                'image' => $file_name,
                'created_on' => now(),
            ]);
        }

        // add student attendance
        foreach ($getAllStudents as $key => $student) {
            $student_id = $student['id'];
            $data = $harshitApiResponses[$student_id] ?? [];
            $attendance = 'A';
            if (isset($matchedStudents[$student_id]) && !empty($matchedStudents[$student_id])) {
                $attendance = 'P';
            }
            $attendanceArray = [];
            $attendanceArray['syear'] = $syear;
            $attendanceArray['sub_institute_id'] = $sub_institute_id;
            $attendanceArray['student_id'] = $student_id;
            $attendanceArray['attendance_date'] = $date;
            $attendanceArray['standard_id'] = $standard_id;
            $attendanceArray['section_id'] = $division_id;

            $existing = DB::table("attendance_student")->where($attendanceArray)->get()->toArray();

            $attendanceArray['attendance_code'] = $attendance;
            $attendanceArray['teacher_id'] = $user_id;
            $attendanceArray['user_group_id'] = $user_profile_id;
            $attendanceArray['created_by'] = $user_id;

            if (count($existing) > 0) {
                DB::table("attendance_student")->where(['id' => $existing[0]->id])->update($attendanceArray);
            } else {
                DB::table("attendance_student")->insert($attendanceArray);

                $sendRequest = new Request(['student_id' => $student_id, 'attendance' => $attendance, 'date' => $date, 'created_by' => $user_id, 'syear' => $syear, 'sub_institute_id' => $sub_institute_id]);

                if ($date == date('Y-m-d')) {
                    // $sendNotification = $this->sendNotificationAtt($sendRequest);
                }
            }
        }

        $res['status'] = $getAllStudents ? 1 : 0;
        $res['message'] = $getAllStudents ? 'Attendance Marked Successfully' : 'Attendance Marked Failed';

        return is_mobile($type, 'class_face_attendance.index', $res);
    }
}
