<?php

namespace App\Http\Controllers\school_setup;

use App\Http\Controllers\Controller;
use App\Models\school_setup\SchoolModel;
use App\Models\student\tblstudentModel;
use App\Models\user\tbluserModel;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use function App\Helpers\is_mobile;

class changePasswordController extends Controller
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

        $getSchoolData = SchoolModel::where(['id' => $sub_institute_id])->get()->toArray();
        $getUserData = tbluserModel::where(['sub_institute_id' => $sub_institute_id])->get()->toArray();

        $res['status_code'] = 1;
        $res['message'] = "Success";
        if (isset($getSchoolData)) {
            $res['schooldata'] = $getSchoolData[0];
        }
        if (isset($getUserData)) {
            $res['userdata'] = $getUserData[0];
        }

        return is_mobile($type, '/change_password', $res, 'view');

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
        $type = $request->input('type');
        $password = $request->input('password');
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $user_id = $request->session()->get('user_id');
        $user_profile_name = $request->session()->get('user_profile_name');

        if ($user_profile_name == 'Student') {
            $finalArray['password'] = MD5($password);
            $data = tblstudentModel::where(['id' => $user_id])->update($finalArray);
        } else {
            $finalArray['password'] = $password;
            $data = tbluserModel::where(['id' => $user_id])->update($finalArray);
        }

        $res['status_code'] = 1;
        $res['message'] = "Password Change Successfully";

        return is_mobile($type, "change_password", $res, "view");
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

    public function device_check(request $request)
    {
        $type = $request->input('type');
        $sub_institute_id = $request->session()->get('sub_institute_id');

        $res['status_code'] = 1;
        $res['message'] = "Success";

        return is_mobile($type, '/device_check', $res, 'view');

    }
}
