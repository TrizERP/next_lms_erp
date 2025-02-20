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

        $res['fieldTypes'] = ['text','textarea','dropdown','file','checkbox','radio','email','date','time'];
        // echo "<pre>";print_r($res);exit;
        $res['deletedData'] = masterFieldInstituteModel::where('sub_institute_id',$sub_institute_id)->onlyTrashed()->get();

        return is_mobile($type, "settings/configuration/add", $res, "view");
    }

    public function store(Request $request){
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
        $this->masterInsertUpdate($request,'checkInsert',$sub_institute_id,$user_profile_id);
        $insert = $this->masterInsertUpdate($request,'insert',$sub_institute_id,$user_profile_id);

        if($insert!=0){
            $res['status'] = 1;
            $res['message'] = "Success";
        }else{
            $res['status'] = 0;
            $res['message'] = "Failed";
        }

        return is_mobile($type, "configurations.index", $res);
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

        $this->masterInsertUpdate($request,'checkInsert',$sub_institute_id,$user_profile_id);
        
        $editData = masterFieldInstituteModel::where(['sub_institute_id'=>$sub_institute_id,'field_name'=>$id,'section'=>$request->sectionName,'module'=>$request->module])->first();

        if(!empty($editData)){
            $res['status']=1;
            $res['message']='succes';
            $res['editData'] = $editData;
        }else{
            $res['status']='0';
            $res['message']='No Data Found';
            $res['editData'] = [];
        }

        return response()->json($res);
    }

    public function update(Request $request,$id){
        // echo "<pre>";print_r($request->all());exit;
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

        $update = $this->masterInsertUpdate($request,'update',$sub_institute_id,$user_profile_id,$id);
        $res['modulesList'] = masterFieldModel::when($request->has('main_menu_id'),function($q) use($request){
            $q->where('main_menu',$request->main_menu_id);
        })
        ->groupBy('module')
        ->get();

        $res['deletedData'] = masterFieldInstituteModel::where('sub_institute_id',$sub_institute_id)->onlyTrashed()->get();

        $res['fieldTypes'] = ['text','textarea','dropdown','file','checkbox','radio','email','date','time'];
        return is_mobile($type, "settings/configuration/add", $res, "view");
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
        $i=0;
        if($masterType!='' && $masterType=='institute'){
            // menu field sort order module wise
            if($request->has('module') && $request->has('section')){
                $this->masterInsertUpdate($request,'checkInsert',$sub_institute_id,$user_profile_id);
                if($request->has('orderArr')){
                    foreach ($request->orderArr as $key => $value) {
                        $sortOrder = ($key+1);
                        $updateRights = DB::table('master_fields_institute')
                            ->where(['sub_institute_id'=>$sub_institute_id,'section'=>$request->section,'module'=>$request->module,'field_name'=>$value])
                            ->update(['sort_order'=>$sortOrder]);
                            $i++;
                    }
                }
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
        // echo "<pre>";print_r($request->all());exit;
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
        $modelData = masterFieldInstituteModel::where(['sub_institute_id'=>$sub_institute_id,'module'=>$request->module])->orderBy('id','ASC')->get();
        if(count($modelData)==0){
            $modelData = masterFieldModel::where('module',$request->module)->orderBy('id','ASC')->get();
        }
        $tableFields = [];
        $tableName = [];
        $sectionData = [];
        foreach ($modelData as $key => $value) {
            if(!in_array($value['table_name'],$tableName)){
                $tableData = DB::getSchemaBuilder()->getColumnListing($value['table_name']);
                $excludedColumns = ['id','student_id', 'grade_id', 'standard_id','division_id', 'grade', 'standard','division', 'sub_institute_id', 'syear', 'created_by', 'deleted_at', 'created_at', 'updated_at', 'created_on', 'updated_on'];
                foreach ($modelData as $k => $v) {
                    $excludedColumns[] = $v->field_name;
                }
                $tableFields = array_diff($tableData, $excludedColumns);
            }
            $sectionData[$value->section][] =$value; 
        }
        $res['tableData'] = $sectionData;
        $res['table_fields'] = $tableFields;
        return $res;
    }

    public function masterInsertUpdate($request,$action,$sub_institute_id,$user_profile_id,$id=''){
        $data = 0;
        $jsonValues=[];
        if($request->has('option_keys') && $request->has('option_values')){
            foreach($request->option_keys as $key=>$jsonKey){
                if($jsonKey!=''){
                    $jsonVal = isset($request->option_values[$key]) ? $request->option_values[$key] : '-';
                    $jsonValues[$jsonKey] = $jsonVal;
                }
            }
        }
        $jsonEncode = !empty($jsonValues) ? json_encode($jsonValues) : null;
        $i = 0;

        if($action=='checkInsert'){
            $checkExists = masterFieldInstituteModel::where(['sub_institute_id'=>$sub_institute_id])->get();
            if(count($checkExists)==0){
                $masterData = masterFieldModel::all();
                if(count($masterData)>0){
                    foreach ($masterData as $key => $value) {
                        $i++;
                        $insertArr = [
                        'module'=>$value['module'],
                        'field_label'=>$value['field_label'],
                        'field_name'=>$value['field_name'],
                        'field_type'=>$value['field_type'],
                        'field_value'=>$value['field_value'],
                        'is_mandatory'=>$value['is_mandatory'],
                        'is_visible'=>$value['is_visible'],
                        'validation_rules'=>$value['validation_rules'],
                        'sort_order'=>$value['sort_order'] ?? ($key+1),
                        'section'=>$value['section'],
                        'sub_institute_id'=>$sub_institute_id,
                        'created_by'=>$user_profile_id,
                        'main_menu'=>$value['main_menu'],
                        'table_name'=>$value['table_name'],
                        'created_at'=>now()
                    ];
                    $data = masterFieldInstituteModel::insert($insertArr);
                    }
                }
            }
        }

        if($action=='insert'){
            $i++;
            $maxSortOrder = masterFieldInstituteModel::where([
                'sub_institute_id' => $sub_institute_id,
                'section' => 'Custom Details'
            ])->max('sort_order') ?? 0;
            $insertArr = [
            'module'=>$request->module,
            'field_label'=>$request->field_label ?? null,
            'field_name'=>$request->field_name ?? null,
            'field_type'=>$request->field_type,
            'field_value'=>$jsonEncode ?? null,
            'default_value'=>$request->default_value ?? null,
            'is_mandatory'=>$request->is_mandatory ?? 0,
            'is_visible'=>$request->is_visible ?? 0,
            'validation_rules'=>$request->validation_rules ?? null,
            'sort_order'=>($maxSortOrder+1),
            'section'=> 'Custom Details',
            'sub_institute_id'=>$sub_institute_id,
            'created_by'=>$user_profile_id,
            'main_menu'=>$request->main_menu ?? null,
            'table_name'=>$request->table_name ?? null,
            'created_at'=>now()
        ];
        $data = masterFieldInstituteModel::insert($insertArr);
        }

        if($action=='update'){
            $updateArr = [
                'field_label'=>$request->field_label ?? null,
                'field_value'=>$jsonEncode ?? null,
                'default_value'=>$request->default_value ?? null,
                'is_mandatory'=>$request->is_mandatory ?? null,
                'is_visible'=>$request->is_visible ?? null,
                'validation_rules'=>$request->validation_rules ?? null,
                'updated_at'=>now()
            ];
            $data = masterFieldInstituteModel::where('id',$id)->update($updateArr);
        }
        return $data;
    }

    public function destroy(Request $request, $id)
    {
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

        $record = masterFieldInstituteModel::find($id);

        if ($record) {
            $record->delete(); 
        }

        if($record){
            $response['status'] = '1';
            $response['message'] = "Successfully Deleted";
        }else{
            $response['status'] = '0';
            $response['message'] = "Failed to Delete";
        }
        
        return response()->json($response);
    }

}
