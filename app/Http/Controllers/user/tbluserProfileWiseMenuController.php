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

        $user_data = DB::table('tblprofilewise_menu')->select('tblprofilewise_menu.*', 'tbluserprofilemaster.name as profile_name', 'tblmenumaster.name as menu_name')
            ->join('tbluserprofilemaster', 'tblprofilewise_menu.user_profile_id', '=', 'tbluserprofilemaster.id')
            ->join('tblmenumaster', 'tblprofilewise_menu.menu_id', '=', 'tblmenumaster.id')
            ->where(['tblprofilewise_menu.sub_institute_id' => $sub_institute_id])
            ->get()->toArray();

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

        $check2 = DB::table('tblprofilewise_menu')->where($finalArray2)->pluck('id','menu_id')->toArray();
              
        foreach ($arrayKeys as $key => $value) 
        {
            $finalArray = array(
                'menu_id' => $key,
                'user_profile_id' => $request->input('profile_id'),
                'sub_institute_id' => $sub_institute_id,
            );

            $check = DB::table('tblprofilewise_menu')->where($finalArray)->get()->toArray();
           
            if(!empty($check))
            {
                foreach ($check2 as $key2 => $value2) 
                {
                    if($key2 != $key)
                    {
                        DB::table('tblprofilewise_menu')->where(['menu_id' => $key2, 'user_profile_id' => $request->input('profile_id'), 'sub_institute_id' => $sub_institute_id])->delete();

                        $res['message'] = "User Profile Wise Rights Deleted Successfully";
                    }
                }
            }
            else
            {
                $existingRecord = DB::table('tblprofilewise_menu')
                ->where([
                    'menu_id' => $key,
                    'user_profile_id' => $request->input('profile_id'),
                    'sub_institute_id' => $sub_institute_id,
                ])
                ->first();
               
                if ($existingRecord) 
                {
                    DB::table('tblprofilewise_menu')
                        ->where([
                            'menu_id' => $key,
                            'user_profile_id' => $request->input('profile_id'),
                            'sub_institute_id' => $sub_institute_id,
                        ])
                        ->update(['updated_at' => now()]);

                        $res['message'] = "User Profile Wise Rights Updated Successfully";
                } 
                else 
                {
                    // Record doesn't exist, insert a new one
                    DB::table('tblprofilewise_menu')
                        ->insert([
                            'menu_id' => $key,
                            'user_profile_id' => $request->input('profile_id'),
                            'sub_institute_id' => $sub_institute_id,
                            'created_at' => now(),
                        ]);
                        
                        $res['message'] = "User Profile Wise Rights Added Successfully";
                }          
            }
        } 

        $type = $request->input('type');

        return is_mobile($type, "user_profile_wise_menu_rights.index", $res);
    }

    public function displayUserProfileWiseRights(Request $request) 
    {
        $profile_id = $request->input("profile_id");
        $sub_institute_id = $request->session()->get('sub_institute_id');

        //$get_menus = tblmenumasterModel::where(['status' => 1])->get()->toArray();

        /* $rightsData = DB::table('tblmenumaster')->leftJoin('tblprofilewise_menu','tblprofilewise_menu.menu_id','=','tblmenumaster.id')->selectRaw('tblmenumaster.id,tblmenumaster.name as menu_name,tblmenumaster.level,tblprofilewise_menu.id as pid,tblprofilewise_menu.menu_id,tblprofilewise_menu.user_profile_id')->where(['tblprofilewise_menu.user_profile_id' => $profile_id])->orderBy('tblmenumaster.sort_order','ASC')->get()->toArray(); */

        $data = tblmenumasterModel::where(['LEVEL' => 1,'status' => 1])->groupBy('id')->orderBy('sort_order','ASC')->get()->toArray();

        $subMenuData = tblmenumasterModel::where(['LEVEL' => 2,'status' => 1])->orderBy('sort_order','ASC')->get()->toArray();
       
        $SubsubMenuData = tblmenumasterModel::where(['LEVEL' => 3,'status' => 1])->orderBy('sort_order','ASC')->get()->toArray();

        $i = 0;
        foreach ($subMenuData as $key => $value) {
            $finalSubMenu[$value['parent_menu_id']][$i] = $subMenuData[$key];
            $i++;
        }

        $i = 0;
        foreach ($SubsubMenuData as $key => $value) {
            $finalSubSubMenu[$value['parent_menu_id']][$i] = $SubsubMenuData[$key];
            $i++;
        }

        view()->share('groupwisemenuMaster', $data);
        if (isset($finalSubMenu)) {
            view()->share('groupwisesubmenuMaster', $finalSubMenu);
        }else{
            $finalSubMenu = array();
        }

        if (isset($finalSubSubMenu)) {
            view()->share('groupwiseSubsubmenuMaster', $finalSubSubMenu);
        }

        $rightsData = DB::table('tblprofilewise_menu')->join('tblmenumaster','tblprofilewise_menu.menu_id','=','tblmenumaster.id')->where(['tblprofilewise_menu.user_profile_id' => $profile_id])->get()->toArray();
       
        $rights = array();
        if (count($rightsData) > 0) 
        {
            foreach ($rightsData as $key => $value) 
            {
                $rights['rights'][] = $value->menu_id;
            }
        }

        $response = array(
            $data,
            $finalSubMenu ?? [],
            $finalSubSubMenu ?? [],
            $rights
        );
        return $response;
    }
}
