<?php

namespace App\Http\Controllers\user;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\user\tbluserprofilemasterModel;
use function App\Helpers\is_mobile;


class tbluserprofilemasterController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $user_data =  tbluserprofilemasterModel::where(['sub_institute_id'=>$sub_institute_id])->get();
        $res['status_code'] = 1;
        $res['message'] = "Success";
        $res['data'] = $user_data;
        $type = $request->input('type');
        return is_mobile($type, "user/show_user_profile", $res, "view");
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create(Request $request)
    {
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $data =  tbluserprofilemasterModel::where(['sub_institute_id'=>$sub_institute_id,'parent_id'=>'0'])->get()->toArray();
        return view('user/add_user_profile',['menu' => $data]);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $user = new tbluserprofilemasterModel([
            'parent_id' => $request->get('parent_id'),
            'name' => $request->get('profile_name'),
            'description' => $request->get('profile_description'),
            'sort_order' => $request->get('sort_order'),
            'status' => "1",
            'sub_institute_id' => $sub_institute_id,
        ]);
        // echo "<pre>";
        // print_r($user);
        // die;
        $user->save();
        $data = tbluserprofilemasterModel::where(['sub_institute_id' => $sub_institute_id])->get();
       
        $res['status_code'] = "1";
        $res['message'] = "User Profile created successfully";
        $res['data'] = $data;
        $type = $request->input('type');
        return is_mobile($type, "add_user_profile.index", $res);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit(Request $request, $id)
    {
        $type = $request->input('type');
        // echo $type;

        $editData = tbluserprofilemasterModel::find($id)->toArray();
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $data =  tbluserprofilemasterModel::where(['sub_institute_id' => $sub_institute_id, 'parent_id'=>'0'])->where('id', '!=', $id)->get()->toArray();
        view()->share('menu', $data);
        return view('user/add_user_profile',['data' => $editData]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $user = array(
            'parent_id' => $request->get('parent_id'),
            'name' => $request->get('profile_name'),
            'description' => $request->get('profile_description'),
            'sort_order' => $request->get('sort_order'),
            'status' => "1",
            'sub_institute_id' => $sub_institute_id,
        );
        
        $type = $request->input('type');
        $data = tbluserprofilemasterModel::where(["id" => $id])->update($user);
        $res['status_code'] = "1";
        $res['message'] = "User Profile updated successfully";
        $res['data'] = $data;
        return is_mobile($type, "add_user_profile.index", $res);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy(Request $request, $id)
    {
        $type = $request->input('type');
        tbluserprofilemasterModel::where(["id" => $id])->delete();
        $res['status_code'] = "1";
        $res['message'] = "User Profile deleted successfully";
        return is_mobile($type, "add_user_profile.index", $res);
    }
}
