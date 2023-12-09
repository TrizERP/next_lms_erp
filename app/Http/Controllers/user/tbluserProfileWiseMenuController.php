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

class tbluserProfileWiseMenuController extends Controller 
{
    public function index(Request $request) 
    {
        $sub_institute_id = $request->session()->get('sub_institute_id');

        $user_data = tblgroupwise_rightsModel::select('tblgroupwise_rights.*', 'tbluserprofilemaster.name as profile_name', 'tblmenumaster.name as menu_name')
            ->join('tbluserprofilemaster', 'tblgroupwise_rights.profile_id', '=', 'tbluserprofilemaster.id')
            ->join('tblmenumaster', 'tblgroupwise_rights.menu_id', '=', 'tblmenumaster.id')
            ->where(['tblgroupwise_rights.sub_institute_id' => $sub_institute_id])
            ->get();

        $res['status_code'] = 1;
        $res['message'] = "Success";
        $res['data'] = $user_data;
        $type = $request->input('type');

        return is_mobile($type, "user/show_user_profile_wise_menu_rights", $res, "view");
    }

    public function create(Request $request) {

        $sub_institute_id = $request->session()->get('sub_institute_id');

        $user_profiles = tbluserprofilemasterModel::where(['sub_institute_id' => $sub_institute_id, 'status' => '1'])->orderBy('sort_order')->get()->toArray();

        return view('user/add_user_wise_menu_rights', ['user_profiles' => $user_profiles]);
    }

    public function store(Request $request) 
    {
        $rights = $request->input('rights');
        $initialRights = $request->input('initial_rights');
       
        if (!isset($rights)) 
        {
            $rights = array();
        }

        $arrayKeys = array_replace($rights);
        $sub_institute_id = $request->session()->get('sub_institute_id');
                
        foreach ($arrayKeys as $key => $value) 
        {
            $finalArray = array(
                'menu_id' => $key,
                'user_profile_id' => $request->input('profile_id'),
                'sub_institute_id' => $sub_institute_id,
            );

            // Check if the checkbox was initially selected and is now unselected
            if (isset($initialRights[$key]) && $initialRights[$key] == 1 && !in_array($key, $value)) 
            {
                // Checkbox was unselected, delete the record
                
                DB::table('tblprofilewise_menu')->where(['menu_id' => $key, 'user_profile_id' => $request->input('profile_id')])->delete();

                $res['status_code'] = "1";
                $res['message'] = "User Profile wise Rights Deleted Successfully";
            } 
            else 
            {
                DB::table('tblprofilewise_menu')
                ->updateOrInsert(
                    ['menu_id' => $key, 'user_profile_id' => $request->input('profile_id'), 'sub_institute_id' => $sub_institute_id],
                    $finalArray
                );

                $res['status_code'] = "1";
                $res['message'] = "User Profile wise Rights Added Successfully";
            }
        }
    
        $type = $request->input('type');

        return is_mobile($type, "user_profile_wise_menu_rights.index", $res);
    }

    public function displayUserProfileWiseRights(Request $request) 
    {
        $profile_id = $request->input("profile_id");
        $sub_institute_id = $request->session()->get('sub_institute_id');

        $get_menus = tblmenumasterModel::where(['status' => 1])->get()->toArray();

        $rightsData = DB::table('tblmenumaster')->leftJoin('tblprofilewise_menu','tblprofilewise_menu.menu_id','=','tblmenumaster.id')->selectRaw('tblmenumaster.id,tblmenumaster.name as menu_name,tblmenumaster.level,tblprofilewise_menu.id as pid,tblprofilewise_menu.menu_id,tblprofilewise_menu.user_profile_id')->where(['tblprofilewise_menu.user_profile_id' => $profile_id])->get()->toArray();
       
        $rights = array();
        if (count($rightsData) > 0) 
        {
            foreach ($rightsData as $key => $value) 
            {
                if ($value->id == $value->menu_id) 
                {
                    $rights['rights'][] = $value->menu_id;
                }
            }
        }

        $response = array(
            $get_menus,
            $rights
        );
        return $response;
    }
}
