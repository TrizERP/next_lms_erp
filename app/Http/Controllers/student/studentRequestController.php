<?php

namespace App\Http\Controllers\student;

use App\Http\Controllers\Controller;
use App\Models\student\studentChangeRequestTypeModel;
use App\Models\student\studentRequestModel;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use function App\Helpers\is_mobile;
use function App\Helpers\SearchStudent;

class studentRequestController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index(Request $request)
    {
        $type = $request->input('type');
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $syear = $request->session()->get('syear');

        $res['status_code'] = 1;
        $res['message'] = "Success";

        return is_mobile($type, "student/student_request", $res, "view");
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return Response
     */
    public function create(Request $request)
    {
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $syear = $request->session()->get('syear');
        $type = $request->input('type');
        $grade = $request->input('grade');
        $standard = $request->input('standard');
        $division = $request->input('division');
        $student_data = SearchStudent($grade, $standard, $division);

        $request_type_data = studentChangeRequestTypeModel::where([
            'SUB_INSTITUTE_ID' => $sub_institute_id, 'SYEAR' => $syear,
        ])->get()->toArray();

        $res['status_code'] = 1;
        $res['message'] = "Success";
        $res['student_data'] = $student_data;
        $res['request_type_data'] = $request_type_data;
        $res['grade_id'] = $grade;
        $res['standard_id'] = $standard;
        $res['division_id'] = $division;

        return is_mobile($type, "student/student_request", $res, "view");
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  Request  $request
     * @return Response
     */
    public function store(Request $request)
    {
        $type = $request->input('type');
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $syear = $request->session()->get('syear');
        $student_request = $request->input('student_request');
        $CHANGE_REQUEST_IDS = $request->input('CHANGE_REQUEST_IDS');
        $PROOF_OF_DOCUMENTS = $request->input('PROOF_OF_DOCUMENTS');
        $REASONS = $request->input('REASONS');
        $DESCRIPTIONS = $request->input('DESCRIPTIONS');
        $STANDARD_IDS = $request->input('STANDARD_IDS');
        $SECTION_IDS = $request->input('SECTION_IDS');
        $user_id = $request->session()->get('user_id');

        if ($student_request == '') {
            $res['status_code'] = 0;
            $res['message'] = "Please select student to proceed";

            return is_mobile($type, "student_request.index", $res);
        }

        foreach ($student_request as $key => $student_id) {
            $studentRequest['STUDENT_ID'] = $student_id;
            $studentRequest['SYEAR'] = $syear;
            $studentRequest['SUB_INSTITUTE_ID'] = $sub_institute_id;
            $studentRequest['CHANGE_REQUEST_ID'] = $CHANGE_REQUEST_IDS[$student_id] ?? '';
            $studentRequest['REASON'] = $REASONS[$student_id];
            $studentRequest['PROOF_OF_DOCUMENT'] = $DESCRIPTIONS[$student_id];
            $studentRequest['DESCRIPTION'] = $PROOF_OF_DOCUMENTS[$student_id]??'';
            $studentRequest['STANDARD_ID'] = $STANDARD_IDS[$student_id];
            $studentRequest['SECTION_ID'] = $SECTION_IDS[$student_id];
            $studentRequest['CREATED_BY'] = $user_id;

            studentRequestModel::insert($studentRequest);
        }

        $res['status_code'] = 1;
        $res['message'] = "Student Request Successfully Added";

        return is_mobile($type, "student_request.index", $res);
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
}
