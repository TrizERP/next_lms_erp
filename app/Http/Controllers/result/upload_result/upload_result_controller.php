<?php

namespace App\Http\Controllers\result\upload_result;

use App\Http\Controllers\Controller;
use App\Models\result\upload_result\upload_result_model;
use App\Models\student\tblstudentModel;
use GenTux\Jwt\GetsJwtToken;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use function App\Helpers\is_mobile;

class upload_result_controller extends Controller
{

    use GetsJwtToken;

    /**
     * Display a listing of the resource.
     *
     * @param  Request  $request
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @return Response
     */
    public function index(Request $request)
    {
        $type = $request->input('type');
        $submit = $request->input('submit');
        $sub_institute_id = session()->get('sub_institute_id');
        $syear = session()->get('syear');
        $res['status'] = 1;
        $res['message'] = "Success";

        return is_mobile($type, "result/upload_result/show_upload_result", $res, "view");
    }

    /**
     * Show the form for creating a new resource.
     *
     * @param  Request  $request
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @return Response
     */
    public function create(Request $request)
    {
        $type = $request->input('type');
        $sub_institute_id = session()->get('sub_institute_id');
        $syear = session()->get('syear');
        $grade = $request->input('grade');
        $standard = $request->input('standard');
        $division = $request->input('division');
        $term = $request->input('term');

        $extraSearchArray = [];
        $extraSearchArrayRaw = " 1=1 ";

        if ($grade != '') {
            $extraSearchArray['tblstudent_enrollment.grade_id'] = $grade;
        }

        if ($standard != '') {
            $extraSearchArray['tblstudent_enrollment.standard_id'] = $standard;
        }
        if ($division != '') {
            $extraSearchArray['tblstudent_enrollment.section_id'] = $division;
        }

        $extraSearchArrayRaw .= "  AND tblstudent_enrollment.end_date IS NULL ";
        $extraSearchArray['tblstudent_enrollment.syear'] = $syear;
        $extraSearchArray['tblstudent.sub_institute_id'] = $sub_institute_id;
        $extraSearchArray['tblstudent_enrollment.sub_institute_id'] = $sub_institute_id;

        $studentData = tblstudentModel::selectRaw("tblstudent.id AS CHECKBOX,CONCAT_WS(' ',tblstudent.first_name,tblstudent.middle_name,
            tblstudent.last_name) AS student_name,academic_section.title as grade,standard.name as standard_name,
            division.name as division_name,tblstudent.enrollment_no,tblstudent.mobile,tblstudent.uniqueid,upload_result.file_name,
            academic_year.title as term_name")
            ->join('tblstudent_enrollment', 'tblstudent.id', '=', 'tblstudent_enrollment.student_id')
            ->leftjoin('upload_result', function ($join) use ($term) {
                $join->on('upload_result.student_id', '=', 'tblstudent.id')
                    ->on('upload_result.grade_id', '=', 'tblstudent_enrollment.grade_id')
                    ->on('upload_result.standard_id', '=', 'tblstudent_enrollment.standard_id')
                    ->on('upload_result.term_id', '=', DB::raw("'".$term."'"));
            })
            ->leftjoin('academic_year', function ($join) {
                $join->on('academic_year.term_id', '=', 'upload_result.term_id')
                    ->on('academic_year.sub_institute_id', '=', 'upload_result.sub_institute_id');
            })
            ->join('academic_section', 'academic_section.id', '=', 'tblstudent_enrollment.grade_id')
            ->join('standard', 'standard.id', '=', 'tblstudent_enrollment.standard_id')
            ->join('division', 'division.id', '=', 'tblstudent_enrollment.section_id')
            ->where($extraSearchArray)
            ->whereRaw($extraSearchArrayRaw)
            ->groupby('tblstudent.id')
            ->get()
            ->toArray();

        $res['status_code'] = 1;
        $res['message'] = "Success";
        $res['student_data'] = $studentData;
        $res['grade_id'] = $grade;
        $res['standard_id'] = $standard;
        $res['division_id'] = $division;
        $res['term_id'] = $term;

        return is_mobile($type, "result/upload_result/show_upload_result", $res, "view");
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  Request  $request
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @return Response
     */
    public function store(Request $request)
    {
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $syear = $request->session()->get('syear');
        $type = $request->get('type');
        $students = $request->get('students');
        $grade_id = $request->get('grade_id');
        $standard_id = $request->get('standard_id');
        $division_id = $request->get('division_id');
        $term_id = $request->get('term_id');
        $created_on = now();
        $created_by = session()->get('user_id');
        $created_ip = $_SERVER['REMOTE_ADDR'];

        foreach ($students as $key => $student_id) {
            $check_sql = DB::table("upload_result")
                ->where("student_id", "=", $student_id)
                ->where("standard_id", "=", $standard_id)
                ->where("grade_id", "=", $grade_id)
                ->where("term_id", "=", $term_id)
                ->where("sub_institute_id", "=", $sub_institute_id)
                ->where("syear", "=", $syear)
                ->get()->toArray();

            $check_data = json_decode(json_encode($check_sql), true);

            $file_name = "";
            if ($request->hasFile('image')) {
                $random_no = rand(10000, 99999);
                $file = $request->file('image')[$student_id];
                $originalname = $file->getClientOriginalName();
                $name = "upload_result-".date('YmdHis').'-'.$random_no;
                $ext = File::extension($originalname);
                $file_name = $name.'.'.$ext;
                $path = $file->storeAs('public/upload_result/', $file_name);
            }

            if (count($check_data) == 0) {
                $insert_data = [
                    'syear'            => $syear,
                    'sub_institute_id' => $sub_institute_id,
                    'term_id'          => $term_id,
                    'grade_id'         => $grade_id,
                    'standard_id'      => $standard_id,
                    'student_id'       => $student_id,
                    'file_name'        => $file_name,
                    'created_on'       => $created_on,
                    'created_by'       => $created_by,
                    'created_ip'       => $created_ip,
                ];
                upload_result_model::insert($insert_data);
            } else {
                $update_data = [
                    'file_name'  => $file_name,
                    'created_on' => $created_on,
                    'created_by' => $created_by,
                    'created_ip' => $created_ip,
                ];
                upload_result_model::where([
                    'student_id'       => $student_id,
                    'grade_id'         => $grade_id,
                    'standard_id'      => $standard_id,
                    'term_id'          => $term_id,
                    'sub_institute_id' => $sub_institute_id,
                    'syear'            => $syear,
                ])->update($update_data);
            }
        }

        $res['status'] = "1";
        $res['message'] = "Result Uploaded Successfully";

        return is_mobile($type, "upload_result.index", $res, "redirect");
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return void
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return void
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  Request  $request
     * @param  int  $id
     * @return void
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return void
     */
    public function destroy($id)
    {
        //
    }

    public function uploadResultAPI(Request $request)
    {

        try {
            if (! $this->jwtToken()->validate()) {
                $response = ['status' => '2', 'message' => 'Token Auth Failed', 'data' => []];

                return response()->json($response, 401);
            }
        } catch (\Exception $e) {
            $response = ['status' => '2', 'message' => $e->getMessage(), 'data' => []];

            return response()->json($response, 401);
        }

        $type = $request->input("type");
        $student_id = $request->input("student_id");
        $sub_institute_id = $request->input("sub_institute_id");
        $syear = $request->input("syear");

        if ($student_id != "" && $sub_institute_id != "" && $syear != "") {
            $data = DB::table("upload_result as ur")
                ->join('academic_year as ay', function ($join) {
                    $join->whereRaw("ay.term_id = ur.term_id AND ay.sub_institute_id = ur.sub_institute_id");
                })
                ->selectRaw("ur.id,ur.syear,ur.sub_institute_id,ur.student_id,ay.title as term_name,
                if(ur.file_name = '','',concat('https://".$_SERVER['SERVER_NAME']."/storage/upload_result/',ur.file_name)) as file_name")
                ->where("ur.student_id", "=", $student_id)
                ->where("ur.sub_institute_id", "=", $sub_institute_id)
                ->where("ur.syear", "=", $syear)
                ->groupBy('ur.term_id')
                ->get()->toArray();

            $res['status'] = 1;
            $res['message'] = "Success";
            $res['data'] = $data;
        } else {
            $res['status'] = 0;
            $res['message'] = "Parameter Missing";
        }

        return json_encode($res);
    }
}
