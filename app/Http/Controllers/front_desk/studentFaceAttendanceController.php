<?php

namespace App\Http\Controllers\front_desk;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use function App\Helpers\is_mobile;
use GenTux\Jwt\GetsJwtToken;
use File;

class studentFaceAttendanceController extends Controller
{
    use GetsJwtToken;
    //
    public function index(Request $request)
    {
        // return "hello";
        $type = $request->type;
        $sub_institute_id = session()->get('sub_institute_id');
        $syear = session()->get('syear');
        $userProfile = session()->get('user_profile_name');
        $student_id = session()->get('user_id');
        // return  session()->all();
        if ($type == "API") {
            // try {
            //     if (! $this->jwtToken()->validate()) {
            //         $response = ['status' => '2', 'message' => 'Token Auth Failed'];

            //         return response()->json($response, 401);
            //     }
            // } catch (\Exception $e) {
            //     $response = ['status' => '2', 'message' => $e->getMessage()];

            //     return response()->json($response, 401);
            // }
            $sub_institute_id=$request->sub_institute_id;
            $syear=$request->syear;
            $userProfile=$request->user_profile_name;
            $student_id=$request->student_id;
        }
            
        // Only run the query if at least one filter is provided
        if (strtoupper($userProfile) === 'STUDENT' || $request->has('submit')) {
            $q = DB::table('student_capture_photos as s')
                ->join('tblstudent as ts', fn($j) => $j->on('s.student_id','=','ts.id')
                    ->where('ts.sub_institute_id',$sub_institute_id))
                ->join('tblstudent_enrollment as se', fn($j) => $j->on('s.student_id','=','se.student_id')
                    ->where('se.syear',$syear)->where('ts.sub_institute_id',$sub_institute_id))->whereNull('se.end_date')
                ->join('standard as std', fn($j) => $j->on('se.standard_id','=','std.id')
                    ->where('std.sub_institute_id',$sub_institute_id))
                ->join('division as dev', fn($j) => $j->on('se.section_id','=','dev.id')
                    ->where('dev.sub_institute_id',$sub_institute_id))
                ->selectRaw("s.*,CONCAT_WS(' ',COALESCE(ts.first_name,'-'),COALESCE(ts.middle_name,'-'),COALESCE(ts.last_name,'-')) as full_name,ts.enrollment_no,ts.mobile,std.name as standard_name,dev.name as division_name")
                ->where('s.sub_institute_id',$sub_institute_id)->where('s.syear',$syear)
                ->when(strtoupper($userProfile)=="STUDENT", fn($q) => $q->where('s.student_id',$student_id))
                ->when($request->has('submit'), fn($q) => $q
                    ->when($request->grade && $request->grade!='', fn($q) => $q->where('se.grade_id',$request->grade))
                    ->when($request->standard && $request->standard!='', fn($q) => $q->where('se.standard_id',$request->standard))
                    ->when($request->division && $request->division!='', fn($q) => $q->where('se.section_id',$request->division))
                );
            $getData = $q->get()->toArray();
        } else {
            $getData = [];
        }

        $res['grade_id'] = $request->grade;
        $res['standard_id'] = $request->standard;
        $res['division_id'] = $request->division;
        $res['allCapturedImage'] = $getData ?? [];
        // return $student_id;
        return is_mobile($type, 'front_desk/faceAttendance/studentAttendance/index', $res, 'view');
    }


    public function create(Request $request)
    {
        $type = $request->type;
        $res['message'] = 'Hello';
        return is_mobile($type, 'front_desk/faceAttendance/studentAttendance/create', $res, 'view');
    }
    
    public function store(Request $request)
    {
       $type = $request->type;
        $sub_institute_id = session()->get('sub_institute_id');
        $syear = session()->get('syear');
        $userProfile = session()->get('user_profile_name');
        $student_id = session()->get('user_id');
        // return  session()->all();
        if ($type == "API") {
            try {
                if (! $this->jwtToken()->validate()) {
                    $response = ['status' => '2', 'message' => 'Token Auth Failed'];

                    return response()->json($response, 401);
                }
            } catch (\Exception $e) {
                $response = ['status' => '2', 'message' => $e->getMessage()];

                return response()->json($response, 401);
            }
            $sub_institute_id=$request->sub_institute_id;
            $syear=$request->syear;
            $userProfile=$request->user_profile_name;
            $student_id=$request->student_id;
        }

        if(strtoupper($userProfile)=="ADMIN"){
            // $res['message'] = 'Hello';
            $getStudentId = explode('|',$request->enrollment_no);
            $student_id = $getStudentId[1] ?? 0;
        }

        $insert = 0; // initialize to avoid undefined variable warning

         $check_data = DB::table('student_capture_photos')
                ->selectRaw('COUNT(*) AS total_record,group_concat(stu_image) as stu_images')
                ->where('student_id', $student_id)
                ->where('sub_institute_id', $sub_institute_id)
                ->where('syear', $syear)->where('type_id',$request->type_id)->get()->toArray();

            if ($check_data[0]->total_record > 0) {
                $files_array = explode(',', $check_data[0]->stu_images);

                // START UNLINK files 
                $folder_path = $_SERVER['DOCUMENT_ROOT'].'/storage/capture_photos/'.$student_id;
                foreach ($files_array as $file) { // iterate files
                    if (is_file($file)) {
                        unlink($folder_path.'/'.$file); // delete file
                    }
                }
                // END UNLINK files
                $delete_record = DB::table('student_capture_photos')
                    ->where('student_id', $student_id)
                    ->where('sub_institute_id', $sub_institute_id)
                    ->where('syear', $syear)->where('type_id',$request->type_id)->delete();
                $insert = 0;
                if ($request->hasFile('stu_image')) {
                    foreach ($request->file('stu_image') as $key => $file_data) {
                        $file_name = $file_size = $ext = "";
                        $random_no = rand(10000, 99999);
                        $originalname = $file_data->getClientOriginalName();
                        $file_size = $file_data->getSize();
                        $name = $student_id.'_'.$random_no;
                        $ext = File::extension($originalname);
                        $file_name = $name.'.'.$ext;
                        $file_folder = '/capture_photos/'.$student_id;
                        File::makeDirectory($file_folder, $mode = 0777, true, true);
                        $path = $file_data->storeAs('public/capture_photos/'.$student_id, $file_name);

                        $data['syear'] = $syear;
                        $data['sub_institute_id'] = $sub_institute_id;
                        $data['student_id'] = $student_id;
                        $data['stu_image'] = $file_name;
                        $data['type_id'] = $request->type_id ?? null;
                        $data['created_on'] = date('Y-m-d_h-i-s');

                        $insert = DB::table('student_capture_photos')->insert($data);

                    }
                }

            } else {
                if ($request->hasFile('stu_image')) {
                    foreach ($request->file('stu_image') as $key => $file_data) {
                        $file_name = $file_size = $ext = "";
                        $random_no = rand(10000, 99999);
                        $originalname = $file_data->getClientOriginalName();
                        $file_size = $file_data->getSize();
                        $name = $student_id.'_'.$random_no;
                        $ext = File::extension($originalname);
                        $file_name = $name.'.'.$ext;
                        $file_folder = '/capture_photos/'.$student_id;
                        File::makeDirectory($file_folder, $mode = 0777, true, true);
                        $path = $file_data->storeAs('public/capture_photos/'.$student_id, $file_name);

                        $data['syear'] = $syear;
                        $data['sub_institute_id'] = $sub_institute_id;
                        $data['student_id'] = $student_id;
                        $data['stu_image'] = $file_name;
                        $data['type_id'] = $request->type_id ?? null;
                        $data['created_on'] = date('Y-m-d_h-i-s');

                       $insert = DB::table('student_capture_photos')->insert($data);

                    }
                }
            }


            $res['status'] = $insert ? 1 : 0;
            $res['message'] = $insert ? 'Record Added' : 'Record not Added';
        // return $student_id;
        return is_mobile($type, 'student_face_attendance.index', $res);
    }
    public function destroy(Request $request,$id)
    {
        $type = $request->id;
        $del = DB::table('student_capture_photos')->where('id',$id)->delete();
        $res['status'] = $del ? 1 : 0;
        $res['message'] = $del ? 'Image deleted successfully' : 'Image not deleted';
        return is_mobile($type, 'front_desk/faceAttendance/studentAttendance/index', $res, 'view');
    }   
}
