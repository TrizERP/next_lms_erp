<?php

namespace App\Http\Controllers\settings;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use function App\Helpers\is_mobile;
use GenTux\Jwt\GetsJwtToken;
use GenTux\Jwt\JwtToken;
use App\Models\tblmenumasterModel;
use App\Models\user\tblgroupwise_rightsModel;
use App\Models\user\tbluserprofilemasterModel;
use Validator;
use DB;

class configurationController extends Controller
{
    // call index blade for view
    public function index(Request $request)
    {
        // echo "<pre>";print_r(session()->all());exit;
        $type = $request->input('type');
        $sub_institute_id = session()->get('sub_institute_id');
        $user_profile_id = session()->get('user_profile_id');

        if(in_array($type,["API","JSON"])){
            $validator = Validator::make($request->all(), [
                'sub_institute_id' => 'required|numeric',
                'user_profile_id' => 'required|numeric',
            ]);

            $sub_institute_id = $request->get('sub_institute_id');
            $user_profile_id = $request->get('user_profile_id');

            if ($validator->fails()) {
                $response['status'] = '0';
                $response['message'] = $validator->messages();
                return response()->json($response);
            } 
        }

        $masterMenu = tblmenumasterModel::join('tblgroupwise_rights','tblmenumaster.id','=','tblgroupwise_rights.menu_id')
        ->select('tblmenumaster.*')
        ->where(['parent_menu_id' => "0", 'level' => "1",'status' => 1,'tblgroupwise_rights.sub_institute_id'=>$sub_institute_id,'tblgroupwise_rights.profile_id'=>$user_profile_id])
        ->whereRaw("(menu_type != 'MASTER' or menu_type IS NULL)")
        ->groupBy('tblmenumaster.id')
        ->when('tblgroupwise_rights.sort_order'!=null,function($q){
            $q->orderBy('tblgroupwise_rights.sort_order','ASC');
        },function($q){
            $q->orderBy('tblmenumaster.sort_order','ASC');
        })
        ->get()->toArray();

        $MainMenu = $subMenu = [];
        foreach ($masterMenu as $key => $value) {
          $getSubMenu = tblmenumasterModel::join('tblgroupwise_rights','tblmenumaster.id','=','tblgroupwise_rights.menu_id')
          ->select('tblmenumaster.*')
          ->where(['parent_menu_id' =>$value['id'], 'level' => "2",'status' => 1,'tblgroupwise_rights.sub_institute_id'=>$sub_institute_id,'menu_type'=>'Entry','tblgroupwise_rights.profile_id'=>$user_profile_id])
          ->groupBy('tblmenumaster.id')
          ->when('tblgroupwise_rights.sort_order'!=null,function($q){
                $q->orderBy('tblgroupwise_rights.sort_order','ASC');
            },function($q){
                $q->orderBy('tblmenumaster.sort_order','ASC');
            })
          ->get()->toArray();

          if(!empty($getSubMenu)){
                if(!in_array($value['name'],$MainMenu)){
                    $MainMenu[$value['name']] = $value;
                }
                $subMenu[$value['name']] = $getSubMenu;
            }
        }
        // echo "<pre>";print_r($MainMenu);exit;
        $res['main_menu'] = $MainMenu;
        $res['sub_menu'] = $subMenu;

        return is_mobile($type, "settings/configuration/index", $res, "view");
    }

    public function updateMenuSortOrder(Request $request){
        $type = $request->type;
        $sub_institute_id = session()->get('sub_institute_id');
        $user_profile_id = session()->get('user_profile_id');

        if(in_array($type,["API","JSON"])){
            $validator = Validator::make($request->all(), [
                'sub_institute_id' => 'required|numeric',
                'user_profile_id' => 'required|numeric',
            ]);

            $sub_institute_id = $request->get('sub_institute_id');
            $user_profile_id = $request->get('user_profile_id');

            if ($validator->fails()) {
                $response['status'] = '0';
                $response['message'] = $validator->messages();
                return response()->json($response);
            } 
        }

        $orderArr = $request->orderArr;
        $i=0;
        if($request->has('orderArr') && !empty($orderArr)){
            foreach ($orderArr as $key => $menuId) {
                $sortOrder = ($key+1);
                $updateRights = DB::table('tblgroupwise_rights')
                ->where(['sub_institute_id'=>$sub_institute_id,'profile_id'=>$user_profile_id,'menu_id'=>$menuId])
                ->update(['sort_order'=>$sortOrder]);
                $i++;
             }
        }

        if($i>0){
            $res['status'] = 1;
            $res['message'] = "Sort order updated successfully.";
        }else{
            $res['status'] = 0;
            $res['message'] = "No changes were made to the sort order.";
        }
        return $res;
    }
}
