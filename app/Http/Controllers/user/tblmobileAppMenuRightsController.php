<?php

namespace App\Http\Controllers\user;

use App\Http\Controllers\Controller;
use App\Models\tblmenumasterModel;
use App\Models\user\tblgroupwise_rightsModel;
use App\Models\user\tbluserprofilemasterModel;
use function App\Helpers\is_mobile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Response;
use DB;

class tblmobileAppMenuRightsController extends Controller 
{
    public function create(Request $request) {

        $sub_institute_id = $request->session()->get('sub_institute_id');
        
        $user_profiles = tbluserprofilemasterModel::where(['sub_institute_id' => $sub_institute_id, 'status' => '1'])->orderBy('sort_order')->get()->toArray();

        return view('user/add_mobile_app_menu_rights', ['user_profiles' => $user_profiles]);
    }

    public function store(Request $request) 
    {
        /* echo("<pre>");
print_r($request->all());
echo("</pre>");
die; */
        $rights = $request->input('rights');
        
        if (!isset($rights)) 
        {
            $rights = array();
        }

        $arrayKeys = array_replace($rights);

        $sub_institute_id = $request->session()->get('sub_institute_id');
        $res = array();
        $res['status_code'] = "1";

        $finalArray2 = array(
            'user_profile_id' => $request->input('profile_id'),
            'sub_institute_id' => $sub_institute_id,
        );

        $get_user_profile_master = DB::table("tbluserprofilemaster")
            ->select('name')
            ->where("sub_institute_id", "=", $sub_institute_id)
            ->where("id", "=", $request->input('profile_id'))
            ->first();

        if($get_user_profile_master->name == "Student")
        {
            $check2 = DB::table('mobile_homescreen')->where($finalArray2)->pluck('id', 'screen_name')->toArray();
                
            foreach ($arrayKeys as $key => $value) 
            {
                $finalArray = array(
                    'user_profile_id' => $request->input('profile_id'),
                    'user_profile_name' => $get_user_profile_master->name,
                    'screen_name' => $key,
                    'sub_institute_id' => $sub_institute_id,
                );

                $check = DB::table('mobile_homescreen')->where($finalArray)->get()->toArray();
                
                if(!empty($check))
                {
                    foreach ($check2 as $key2 => $value2) 
                    {
                        if($key2 != $key)
                        {
                            DB::table('mobile_homescreen')->where(['user_profile_name' => $get_user_profile_master->name,'screen_name' => $key2, 'user_profile_id' => $request->input('profile_id'), 'sub_institute_id' => $sub_institute_id])->delete();

                            $res['message'] = "Mobile App Menu Rights Deleted Successfully";
                        }
                    }
                }
                else
                {
                    $existingRecord = DB::table('mobile_homescreen')
                    ->where([
                        'user_profile_name' => $get_user_profile_master->name,
                        'user_profile_id' => $request->input('profile_id'),
                        'sub_institute_id' => $sub_institute_id,
                        'screen_name' => $key,
                    ])->first();
                    
                    if ($existingRecord) 
                    {
                        DB::table('mobile_homescreen')
                        ->where([
                            'user_profile_name' => $get_user_profile_master->name,
                            'user_profile_id' => $request->input('profile_id'),
                            'sub_institute_id' => $sub_institute_id,
                        ])
                        ->update(['updated_on' => now()]);

                        $res['message'] = "Mobile App Menu Rights Updated Successfully";
                    }
                    else 
                    {
                        // Record doesn't exist, insert a new one
                        DB::table('mobile_homescreen')
                        ->insert([
                            'user_profile_name' => $get_user_profile_master->name,
                            'user_profile_id' => $request->input('profile_id'),
                            'sub_institute_id' => $sub_institute_id,
                            /* 'main_title' => $request->input('main_title') ?? '',
                            'menu_type' => $request->input('menu_type') ?? '',
                            'main_title_color_code' => $request->input('main_title_color_code') ?? '',
                            'main_title_background_image' => $request->input('main_title_background_image') ?? '',
                            'sub_title_of_main' => $request->input('sub_title_of_main') ?? '',
                            'sub_title_icon' => $request->input('sub_title_icon') ?? '',
                            'sub_title_api' => $request->input('sub_title_api') ?? '',
                            'sub_title_api_param' => $request->input('sub_title_api_param') ?? '',
                            'main_sort_order' => $request->input('main_sort_order') ?? '',
                            'sub_title_sort_order' => $request->input('sub_title_sort_order') ?? '',
                            'status' => $request->input('status') ?? '', */
                            'screen_name' => $key ?? '',
                            'created_on' => now(),
                        ]);
                        
                        $res['message'] = "Mobile App Menu Rights Added Successfully";
                    }          
                }
            }
        }
        else if($get_user_profile_master->name == "Admin" || $get_user_profile_master->name == "Teacher")
        {
            $check2 = DB::table('teacher_mobile_homescreen')->where($finalArray2)->pluck('id', 'screen_name')->toArray();
                
            foreach ($arrayKeys as $key => $value) 
            {
                $finalArray = array(
                    'user_profile_id' => $request->input('profile_id'),
                    'user_profile_name' => $get_user_profile_master->name,
                    'screen_name' => $key,
                    'sub_institute_id' => $sub_institute_id,
                );

                $check = DB::table('teacher_mobile_homescreen')->where($finalArray)->get()->toArray();
            
                if(!empty($check))
                {
                    foreach ($check2 as $key2 => $value2) 
                    {
                        if($key2 != $key)
                        {
                            DB::table('teacher_mobile_homescreen')->where(['user_profile_name' => $get_user_profile_master->name,'screen_name' => $key2, 'user_profile_id' => $request->input('profile_id'), 'sub_institute_id' => $sub_institute_id])->delete();

                            $res['message'] = "Mobile App Menu Rights Deleted Successfully";
                        }
                    }
                }
                else
                {
                    $existingRecord = DB::table('teacher_mobile_homescreen')
                    ->where([
                        'user_profile_name' => $get_user_profile_master->name,
                        'user_profile_id' => $request->input('profile_id'),
                        'sub_institute_id' => $sub_institute_id,
                        'screen_name' => $key,
                    ])->first();
                
                    if ($existingRecord) 
                    {
                        DB::table('teacher_mobile_homescreen')
                        ->where([
                            'user_profile_name' => $get_user_profile_master->name,
                            'user_profile_id' => $request->input('profile_id'),
                            'sub_institute_id' => $sub_institute_id,
                        ])
                        ->update(['updated_on' => now()]);

                        $res['message'] = "Mobile App Menu Rights Updated Successfully";
                    } 
                    else 
                    {
                        // Record doesn't exist, insert a new one
                        DB::table('teacher_mobile_homescreen')
                        ->insert([
                            'user_profile_name' => $get_user_profile_master->name,
                            'user_profile_id' => $request->input('profile_id'),
                            'sub_institute_id' => $sub_institute_id,
                            /* 'main_title' => $existingRecord->main_title ?? '',
                            'menu_type' => $existingRecord->menu_type ?? '',
                            'main_title_color_code' => $existingRecord->main_title_color_code ?? '',
                            'main_title_background_image' => $existingRecord->main_title_background_image ?? '',
                            'sub_title_of_main' => $existingRecord->sub_title_of_main ?? '',
                            'sub_title_icon' => $existingRecord->sub_title_icon ?? '',
                            'sub_title_api' => $existingRecord->sub_title_api ?? '',
                            'sub_title_api_param' => $existingRecord->sub_title_api_param ?? '',
                            'main_sort_order' => $existingRecord->main_sort_order ?? '',
                            'sub_title_sort_order' => $existingRecord->sub_title_sort_order ?? '',
                            'status' => $existingRecord->status ?? '', */
                            'screen_name' => $key ?? '',
                            'created_on' => now(),
                        ]);
                        
                        $res['message'] = "Mobile App Menu Rights Added Successfully";
                    }          
                }
            }
        }

        $type = $request->input('type');

        return is_mobile($type, "mobile_app_menu_rights", $res);
    }

    public function displayMobileAppMenuRights(Request $request) 
    {
        $profile_id = $request->input("profile_id");
        $type = $request->input("type");
        $syear = $request->session()->get('syear');
        $sub_institute_id = $request->session()->get('sub_institute_id');

        $get_user_profile_master = DB::table("tbluserprofilemaster")
            ->select('name')
            ->where("sub_institute_id", "=", $sub_institute_id)
            ->where("id", "=", $profile_id)
            ->first();

        if ($get_user_profile_master->name == "Student") 
        {
            $all_rights = DB::table("mobile_homescreen")
                ->where("sub_institute_id", "=", 1)
                ->orderByRaw('main_sort_order,sub_title_sort_order')
                ->get()->toArray();

            $mobileapp_menu_data = DB::table("mobile_homescreen as mh")
                ->join('tbluserprofilemaster as up', function ($join) {
                    $join->whereRaw("up.id = mh.user_profile_id AND up.sub_institute_id = mh.sub_institute_id AND up.name = mh.user_profile_name");
                })
                ->selectRaw('mh.*')
                ->where("mh.sub_institute_id", "=", $sub_institute_id)
                ->where("up.id", "=", $profile_id)
                ->orderByRaw('mh.main_sort_order,mh.sub_title_sort_order')
                ->get()->toArray();
        }
        else if ($get_user_profile_master->name == "Admin" || $get_user_profile_master->name == "Teacher") 
        {
            $all_rights = DB::table("teacher_mobile_homescreen")
                ->where("sub_institute_id", "=", 1)
                ->where("user_profile_name", "=", $get_user_profile_master->name)
                ->orderByRaw('main_sort_order,sub_title_sort_order')
                ->get()->toArray();

            $mobileapp_menu_data = DB::table("teacher_mobile_homescreen as mh")
                ->join('tbluserprofilemaster as up', function ($join) {
                    $join->whereRaw("up.id = mh.user_profile_id AND up.sub_institute_id = mh.sub_institute_id AND up.name = mh.user_profile_name");
                })
                ->selectRaw('mh.*')
                ->where("mh.sub_institute_id", "=", $sub_institute_id)
                ->where("up.id", "=", $profile_id)
                ->orderByRaw('mh.main_sort_order,mh.sub_title_sort_order')
                ->get()->toArray();
        }

        $rights = array();
        if (count($mobileapp_menu_data) > 0) 
        {
            foreach ($mobileapp_menu_data as $key => $value) 
            {
                $rights['rights'][] = $value->main_title;
                $rights['rights'][] = $value->menu_type;
                $rights['rights'][] = $value->main_title_color_code;
                $rights['rights'][] = $value->main_title_background_image;
                $rights['rights'][] = $value->sub_title_of_main;
                $rights['rights'][] = $value->sub_title_icon;
                $rights['rights'][] = $value->sub_title_api;
                $rights['rights'][] = $value->sub_title_api_param;
                $rights['rights'][] = $value->main_sort_order;
                $rights['rights'][] = $value->sub_title_sort_order;
                $rights['rights'][] = $value->screen_name;
                $rights['rights'][] = $value->status;
            }
        }

        $response = array(
            $all_rights,
            $rights
        );
        return $response;
    }
}
