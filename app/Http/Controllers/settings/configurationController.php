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
use App\Models\settings\masterFieldModel;
use App\Models\settings\masterFieldInstituteModel;
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
        // check request type is API or JSON
        if(in_array($type,["API","JSON"])){
            try {
                if (! $this->jwtToken()->validate()) {
                    $response = ['status' => '2', 'message' => 'Token Auth Failed', 'data' => []];
    
                    return response()->json($response, 200);
                }

                $validator = Validator::make($request->all(), [
                    'sub_institute_id' => 'required|numeric',
                    'user_profile_id' => 'required|numeric',
                ]);
    
                $sub_institute_id = $request->get('sub_institute_id');
                $user_profile_id = $request->get('user_profile_id');
                // validation check only for API and JSON
                if ($validator->fails()) {
                    $response['status'] = '0';
                    $response['message'] = $validator->messages();
                    return response()->json($response);
                } 

            } catch (\Exception $e) {
                $response = ['status' => '2', 'message' => $e->getMessage(), 'data' => []];
    
                return response()->json($response, 200);
            }
        }
        // get master menu which have entry module as a child menu
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
        // get menu which have entry module as per master menu id
          $getSubMenu = tblmenumasterModel::join('tblgroupwise_rights','tblmenumaster.id','=','tblgroupwise_rights.menu_id')
          ->select('tblmenumaster.*')
          ->where(['parent_menu_id' =>$value['id'], 'level' => "2",'status' => 1,'tblgroupwise_rights.sub_institute_id'=>$sub_institute_id,'tblgroupwise_rights.profile_id'=>$user_profile_id,"link"=>"javascript:void(0);"])
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

    // add menu fields as per master fields
    public function create(Request $request){
        $type = $request->input('type');
        $sub_institute_id = session()->get('sub_institute_id');
        $user_profile_id = session()->get('user_profile_id');
        // check request type is API or JSON
        if(in_array($type,["API","JSON"])){
            try {
                if (! $this->jwtToken()->validate()) {
                    $response = ['status' => '2', 'message' => 'Token Auth Failed', 'data' => []];
    
                    return response()->json($response, 200);
                }

                $validator = Validator::make($request->all(), [
                    'sub_institute_id' => 'required|numeric',
                    'user_profile_id' => 'required|numeric',
                ]);
    
                $sub_institute_id = $request->get('sub_institute_id');
                $user_profile_id = $request->get('user_profile_id');
                // validation check only for API and JSON
                if ($validator->fails()) {
                    $response['status'] = '0';
                    $response['message'] = $validator->messages();
                    return response()->json($response);
                } 
                
            } catch (\Exception $e) {
                $response = ['status' => '2', 'message' => $e->getMessage(), 'data' => []];
    
                return response()->json($response, 200);
            }
        }

        $res['modulesList'] = masterFieldModel::when($request->has('main_menu_id'),function($q) use($request){
            $q->where('main_menu',$request->main_menu_id);
        })
        ->groupBy('module')
        ->get();
        // echo "<pre>";print_r($res);exit;
    
        return is_mobile($type, "settings/configuration/add", $res, "view");
    }

    public function edit(Request $request,$id){
        $type = $request->input('type');
        $sub_institute_id = session()->get('sub_institute_id');
        $user_profile_id = session()->get('user_profile_id');
        // check request type is API or JSON
        if(in_array($type,["API","JSON"])){
            try {
                if (! $this->jwtToken()->validate()) {
                    $response = ['status' => '2', 'message' => 'Token Auth Failed', 'data' => []];
    
                    return response()->json($response, 200);
                }

                $validator = Validator::make($request->all(), [
                    'sub_institute_id' => 'required|numeric',
                    'user_profile_id' => 'required|numeric',
                ]);
    
                $sub_institute_id = $request->get('sub_institute_id');
                $user_profile_id = $request->get('user_profile_id');
                // validation check only for API and JSON
                if ($validator->fails()) {
                    $response['status'] = '0';
                    $response['message'] = $validator->messages();
                    return response()->json($response);
                } 
                
            } catch (\Exception $e) {
                $response = ['status' => '2', 'message' => $e->getMessage(), 'data' => []];
    
                return response()->json($response, 200);
            }
        }
        $checkExists = masterFieldInstituteModel::where(['sub_institute_id'=>$sub_institute_id])->get();
        if(empty($checkExists)){
            $masterData = masterFieldModel::all();
            if(!empty($masterData)){
                foreach ($masterData as $key => $value) {
                    $insertArr = ['module'=>$value['module'],
                    'label'=>$value['field_name'],
                    'field_type'=>$value['field_type'],
                    'field_value'=>$value['field_value'],
                    'is_mandatpry'=>$value['is_mandatpry'],
                    'is_visible'=>$value['is_visible'],
                    'validation_rule'=>$value['validation_rule'],
                    'order'=>$value['order'],
                    'section'=>$value['section']
                ];
                }
            }
        }
        return $request;
    }
    // update sort order of tblgroupwise rights as per profile id
    public function updateMenuSortOrder(Request $request){
        $type = $request->type;
        $sub_institute_id = session()->get('sub_institute_id');
        $user_profile_id = session()->get('user_profile_id');
        $masterType = $request->masterType;

        // check request type is API or JSON
        if(in_array($type,["API","JSON"])){
            try {
                if (! $this->jwtToken()->validate()) {
                    $response = ['status' => '2', 'message' => 'Token Auth Failed', 'data' => []];
    
                    return response()->json($response, 200);
                }

                $validator = Validator::make($request->all(), [
                    'sub_institute_id' => 'required|numeric',
                    'user_profile_id' => 'required|numeric',
                    'masterType'=>'required',
                ]);
    
                $sub_institute_id = $request->get('sub_institute_id');
                $user_profile_id = $request->get('user_profile_id');
                // validation check only for API and JSON
                if ($validator->fails()) {
                    $response['status'] = '0';
                    $response['message'] = $validator->messages();
                    return response()->json($response);
                } 
                
            } catch (\Exception $e) {
                $response = ['status' => '2', 'message' => $e->getMessage(), 'data' => []];
    
                return response()->json($response, 200);
            }
        }

        if($masterType!='' && $masterType=='institute'){
            $i=1;
            // menu field sort order module wise
            if($request->has('module') && $request->has('section')){
                $module = $request->module;
                $section = $request->section;
                $checkData = masterFieldInstituteModel::where(['sub_institute_id'=>$sub_institute_id,'module'=>$module,'section'=>$section])->get();

            }else{
                $res['status'] = 0;
                $res['message'] = "Module OR Section name missing.";
            }
        }
        else if($masterType!='' && $masterType=='rights'){
            // groupwise rights sortorder
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

    // get module wise field lists 
    public function getFeildLists(Request $request){
        $modelData = masterFieldModel::where('module',$request->module)->get();
        return $modelData;
    }
}
