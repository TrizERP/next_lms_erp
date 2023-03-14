<?php

namespace App\Http\Controllers\student;

use App\Http\Controllers\Controller;
use App\Models\student\studentVaccinationModel;
use GenTux\Jwt\GetsJwtToken;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use function App\Helpers\is_mobile;

class studentVaccinationController extends Controller
{

    use GetsJwtToken;

    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index(Request $request)
    {
        $type = $request->input('type');
        $sub_institute_id = $request->session()->get('sub_institute_id');

        $result = DB::table('student_vaccination as si')
            ->join('tblstudent as s', function ($join) {
                $join->whereRaw('si.student_id = s.id');
            })->selectRaw("si.*, CONCAT_WS(' ',s.first_name,s.middle_name,s.last_name) AS student_name")
            ->where('si.sub_institute_id', $sub_institute_id)
            ->orderBy('si.id', 'DESC')
            ->get()->toArray();

        $result = array_map(function ($value) {
            return (array) $value;
        }, $result);

        $res['status_code'] = 1;
        $res['message'] = "Success";
        $res['data'] = $result;

        return is_mobile($type, "student/vaccination/show_student_vaccination", $res, "view");
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return Application|Factory|View
     */
    public function create()
    {
        return view('student/vaccination/add_student_vaccination');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  Request  $request
     * @return Response
     */
    public function store(Request $request)
    {
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $term_id = $request->session()->get('term_id');
        $syear = $request->session()->get('syear');
        $type = $request->input('type');
        $user_id = $request->session()->get('user_id');

        $finalArray = $request->except('_method', '_token', 'submit');

        $STUDENT = $request->input("student_id");
        $STUDENT = explode("-", $STUDENT);
        $finalArray['student_id'] = trim($STUDENT[1]);

        $finalArray['created_by'] = $user_id;
        $finalArray['syear'] = $syear;
        $finalArray['marking_period_id'] = $term_id;
        $finalArray['sub_institute_id'] = $sub_institute_id;
        $finalArray['created_on'] = date('Y-m-d H:i:s');

        studentVaccinationModel::insert($finalArray);
        $id = DB::getPdo()->lastInsertId();

        $res['status_code'] = 1;
        $res['message'] = "Student Vaccination Successfully Created.";

        return is_mobile($type, "student_vaccination.index", $res);
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
     * @return Application|Factory|View
     */
    public function edit(Request $request, $id)
    {
        $sub_institute_id = $request->session()->get("sub_institute_id");

        $result = DB::table('student_vaccination as si')
            ->join('tblstudent as s', function ($join) {
                $join->whereRaw('si.student_id = s.id');
            })->selectRaw("si.*, CONCAT_WS(' ',s.first_name,s.middle_name,s.last_name) AS student_name")
            ->where('si.sub_institute_id', $sub_institute_id)
            ->where('si.id', $id)
            ->orderBy('si.id', 'DESC')
            ->get()->toArray();

        $result = array_map(function ($value) {
            return (array) $value;
        }, $result);

        $editData = $result[0];

        return view('student/vaccination/edit_student_vaccination', ['data' => $editData]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  Request  $request
     * @param  int  $id
     * @return Response
     */
    public function update(Request $request, $id)
    {
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $term_id = $request->session()->get('term_id');
        $syear = $request->session()->get('syear');
        $type = $request->input('type');
        $user_id = $request->session()->get('user_id');

        $finalArray = $request->except('_method', '_token', 'submit');

        $STUDENT = $request->input("student_id");
        $STUDENT = explode("-", $STUDENT);
        $finalArray['student_id'] = trim($STUDENT[1]);

        $data = studentVaccinationModel::where(['id' => $id])->update($finalArray);

        $res['status_code'] = 1;
        $res['message'] = "Student Vaccination successfully updated.";

        return is_mobile($type, "student_vaccination.index", $res);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return Response
     */
    public function destroy(Request $request, $id)
    {
        $type = $request->input('type');
        studentVaccinationModel::where(["id" => $id])->delete();
        $res['status_code'] = "1";
        $res['message'] = "Student Vaccination deleted successfully";

        return is_mobile($type, "student_vaccination.index", $res);
    }

    public function studentVaccinationAPI(Request $request)
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
            $data = DB::table('student_vaccination as si')
                ->selectRaw("si.id,si.student_id,si.doctor_name,si.doctor_contact,si.vaccination_type,
                    si.note,DATE_FORMAT(si.date,'%d-%m-%Y') AS date")
                ->where('si.sub_institute_id', $sub_institute_id)
                ->where('si.student_id', $student_id)
                ->where('si.syear', $syear)
                ->orderBy('si.date')
                ->get()->toArray();

            $res['status_code'] = 1;
            $res['message'] = "Success";
            $res['data'] = $data;

        } else {
            $res['status_code'] = 0;
            $res['message'] = "Parameter Missing";
        }

        return json_encode($res);

    }
}
