<?php

namespace App\Http\Controllers\student;

use App\Http\Controllers\Controller;
use App\Models\student\studentChangeRequestTypeModel;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use function App\Helpers\is_mobile;

class studentChangeRequestTypeController extends Controller
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

        $data = studentChangeRequestTypeModel::where([
            'SUB_INSTITUTE_ID' => $sub_institute_id, 'SYEAR' => $syear,
        ])->get()->toArray();

        $res['status_code'] = 1;
        $res['message'] = "Success";
        $res['request_data'] = $data;

        return is_mobile($type, "student/create_request_type", $res, "view");
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return void
     */
    public function create()
    {
        //
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
        $syear = $request->session()->get('syear');
        $type = $request->input('type');

        $input = $request->except('_method', '_token', 'submit');
        $input['SYEAR'] = $syear;
        $input['SUB_INSTITUTE_ID'] = $sub_institute_id;
        $input['CREATED_BY'] = $sub_institute_id;

        studentChangeRequestTypeModel::insert($input);

        $res['status_code'] = "1";
        $res['message'] = "Student Request Type Created Successfully";

        return is_mobile($type, "student_change_request_type.index", $res);
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
    public function edit($id)
    {
        $editData = studentChangeRequestTypeModel::find($id)->toArray();

        return view('student/create_request_type', ['edit_data' => $editData]);
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
        $syear = $request->session()->get('syear');
        $type = $request->input('type');

        $input = $request->except('_method', '_token', 'submit');
        $input['SYEAR'] = $syear;
        $input['SUB_INSTITUTE_ID'] = $sub_institute_id;
        $input['CREATED_BY'] = $sub_institute_id;

        $data = studentChangeRequestTypeModel::where(['id' => $id])->update($input);

        $res['status_code'] = "1";
        $res['message'] = "Student Request Type Updated Successfully";

        return is_mobile($type, "student_change_request_type.index", $res);
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
        studentChangeRequestTypeModel::where(["id" => $id])->delete();
        $res['status_code'] = "1";
        $res['message'] = "Student Request Type deleted successfully";

        return is_mobile($type, "student_change_request_type.index", $res);
    }
}
