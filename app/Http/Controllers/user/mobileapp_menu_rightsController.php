<?php

namespace App\Http\Controllers\user;

use App\Http\Controllers\Controller;
use App\Models\mobile_homescreenModel;
use App\Models\teacher_mobile_homescreenModel;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use function App\Helpers\is_mobile;

class mobileapp_menu_rightsController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index(Request $request)
    {
        $type = $request->input('type');
        $syear = $request->session()->get('syear');
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $Profiles = ['Admin' => 'Admin', 'Teacher' => 'Teacher', 'Student' => 'Student'];
        $res['status_code'] = "1";
        $res['message'] = "Success";
        $res['profiles'] = $Profiles;

        return is_mobile($type, "user/add_mobileapp_menu_rights", $res, "view");
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return Response
     */
    public function create(Request $request)
    {
        $type = $request->input("type");
        $inactive = $request->input('inactive');
        $profile = $request->input('profile');
        $syear = $request->session()->get('syear');
        $sub_institute_id = $request->session()->get('sub_institute_id');

        if ($profile == 'Student') {

            $mobileapp_menu_data = DB::table("mobile_homescreen as mh")
                ->join('tbluserprofilemaster as up', function ($join) {
                    $join->whereRaw("up.id = mh.user_profile_id AND up.sub_institute_id = mh.sub_institute_id AND up.name = mh.user_profile_name");
                })
                ->selectRaw('mh.*')
                ->where("mh.sub_institute_id", "=", $sub_institute_id)
                ->where("up.name", "=", $profile)
                ->where(function ($q) use ($inactive) {
                    if (isset($inactive) && $inactive == 'No') {

                    } else {
                        $q->where('mh.status', '=', 'Yes');
                    }
                })
                ->orderByRaw('mh.main_sort_order,mh.sub_title_sort_order')
                ->get()->toArray();
        }

        if ($profile == 'Admin' || $profile == 'Teacher') {
            $mobileapp_menu_data = DB::table("teacher_mobile_homescreen as mh")
                ->join('tbluserprofilemaster as up', function ($join) {
                    $join->whereRaw("up.id = mh.user_profile_id AND up.sub_institute_id = mh.sub_institute_id AND up.name = mh.user_profile_name");
                })
                ->selectRaw('mh.*')
                ->where("mh.sub_institute_id", "=", $sub_institute_id)
                ->where("up.name", "=", $profile)
                ->where(function ($q) use ($inactive) {
                    if (isset($inactive) && $inactive == 'No') {

                    } else {
                        $q->where('mh.status', '=', 'Yes');
                    }
                })
                ->orderByRaw('mh.main_sort_order,mh.sub_title_sort_order')
                ->get()->toArray();
        }

        $mobileapp_menu_data = json_decode(json_encode($mobileapp_menu_data), true);

        $Profiles = ['Admin' => 'Admin', 'Teacher' => 'Teacher', 'Student' => 'Student'];

        $res['status_code'] = 1;
        $res['message'] = "Success";
        $res['mobileapp_menu_data'] = $mobileapp_menu_data;
        $res['profiles'] = $Profiles;
        $res['profile'] = $profile;
        $res['inactive'] = $inactive;

        return is_mobile($type, "user/add_mobileapp_menu_rights", $res, "view");
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  Request  $request
     * @return void
     */
    public function store(Request $request)
    {
        //
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
     * @return Response
     */
    public function update(Request $request, $id)
    {
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $syear = $request->session()->get('syear');
        $profile = $request->get('profile_hidden');
        $main_title = $request->get('main_title');
        $main_title_color_code = $request->get('main_title_color_code');
        $main_title_background_image = $request->get('main_title_background_image');
        $main_sort_order = $request->get('main_sort_order');
        $sub_title_of_main = $request->get('sub_title_of_main');
        $sub_title_icon = $request->get('sub_title_icon');
        $sub_title_sort_order = $request->get('sub_title_sort_order');
        $status = $request->get('status');
        $updated_by = session()->get('user_id');
        $updated_on = date('Y-m-d H:i:s');
        $updated_ip = $_SERVER['REMOTE_ADDR'];

        $main_data = [
            'updated_on'         => $updated_on,
            'updated_by'         => $updated_by,
            'updated_ip_address' => $updated_ip,
        ];

        $sub_data = [
            'sub_title_of_main'    => $sub_title_of_main,
            'sub_title_icon'       => $sub_title_icon,
            'sub_title_sort_order' => $sub_title_sort_order,
            'status'               => $status,
            'updated_on'           => $updated_on,
            'updated_by'           => $updated_by,
            'updated_ip_address'   => $updated_ip,
        ];

        if ($profile == 'Student') {
            $get_old_data = mobile_homescreenModel::where(["id" => $id, "sub_institute_id" => $sub_institute_id])
                ->get()->toArray();
            $get_old_data = $get_old_data[0];
            $old_main_title = $get_old_data['main_title'];
            $old_main_title_color_code = $get_old_data['main_title_color_code'];
            $old_main_title_background_image = $get_old_data['main_title_background_image'];
            $old_main_sort_order = $get_old_data['main_sort_order'];

            if ($main_title != $old_main_title) {
                $main_data['main_title'] = $main_title;
                mobile_homescreenModel::where([
                    "main_title" => $old_main_title, "sub_institute_id" => $sub_institute_id,
                ])
                    ->update($main_data);
            }

            if ($main_title_color_code != $old_main_title_color_code) {
                $main_data['main_title_color_code'] = $main_title_color_code;
                mobile_homescreenModel::where([
                    "main_title_color_code" => $old_main_title_color_code, "main_title" => $main_title,
                    "sub_institute_id"      => $sub_institute_id,
                ])
                    ->update($main_data);
            }

            if ($main_title_background_image != $old_main_title_background_image) {
                $main_data['main_title_background_image'] = $main_title_background_image;
                mobile_homescreenModel::where([
                    "main_title_background_image" => $old_main_title_background_image, "main_title" => $main_title,
                    "main_title_color_code"       => $main_title_color_code, "sub_institute_id" => $sub_institute_id,
                ])
                    ->update($main_data);
            }

            if ($main_sort_order != $old_main_sort_order) {
                $main_data['main_sort_order'] = $main_sort_order;
                mobile_homescreenModel::where([
                    "main_sort_order"             => $old_main_sort_order, "main_title" => $main_title,
                    "main_title_color_code"       => $main_title_color_code,
                    "main_title_background_image" => $main_title_background_image,
                    "sub_institute_id"            => $sub_institute_id,
                ])
                    ->update($main_data);
            }
            mobile_homescreenModel::where(["id" => $id, "sub_institute_id" => $sub_institute_id])->update($sub_data);
        }

        if ($profile == 'Admin' || $profile == 'Teacher') {
            $get_old_data1 = teacher_mobile_homescreenModel::where([
                "id" => $id, "sub_institute_id" => $sub_institute_id, 'user_profile_name' => $profile,
            ])->get()->toArray();
            $get_old_data1 = $get_old_data1[0];
            $old_main_title1 = $get_old_data1['main_title'];
            $old_main_title_color_code1 = $get_old_data1['main_title_color_code'];
            $old_main_title_background_image1 = $get_old_data1['main_title_background_image'];
            $old_main_sort_order1 = $get_old_data1['main_sort_order'];

            if ($main_title != $old_main_title1) {
                $main_data['main_title'] = $main_title;
                teacher_mobile_homescreenModel::where([
                    "main_title"        => $old_main_title1, "sub_institute_id" => $sub_institute_id,
                    'user_profile_name' => $profile,
                ])
                    ->update($main_data);
            }

            if ($main_title_color_code != $old_main_title_color_code1) {
                $main_data['main_title_color_code'] = $main_title_color_code;
                teacher_mobile_homescreenModel::where([
                    "main_title_color_code" => $old_main_title_color_code1, "main_title" => $main_title,
                    "sub_institute_id"      => $sub_institute_id, 'user_profile_name' => $profile,
                ])
                    ->update($main_data);
            }

            if ($main_title_background_image != $old_main_title_background_image1) {
                $main_data['main_title_background_image'] = $main_title_background_image;
                teacher_mobile_homescreenModel::where([
                    "main_title_background_image" => $old_main_title_background_image1, "main_title" => $main_title,
                    "main_title_color_code"       => $main_title_color_code, "sub_institute_id" => $sub_institute_id,
                    'user_profile_name'           => $profile,
                ])
                    ->update($main_data);
            }

            if ($main_sort_order != $old_main_sort_order1) {
                $main_data['main_sort_order'] = $main_sort_order;
                teacher_mobile_homescreenModel::where([
                    "main_sort_order"             => $old_main_sort_order1, "main_title" => $main_title,
                    "main_title_color_code"       => $main_title_color_code,
                    "main_title_background_image" => $main_title_background_image,
                    "sub_institute_id"            => $sub_institute_id, 'user_profile_name' => $profile,
                ])
                    ->update($main_data);
            }
            teacher_mobile_homescreenModel::where([
                "id" => $id, "sub_institute_id" => $sub_institute_id,
            ])->update($sub_data);
        }

        $res = [
            "status_code" => 1,
            "message"     => "Mobile App Menu Rights Updated Successfully",
        ];
        $type = $request->input('type');

        return is_mobile($type, "add_mobileapp_menu_rights.index", $res);
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
