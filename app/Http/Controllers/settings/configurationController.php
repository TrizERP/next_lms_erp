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
use App\Models\settings\mastreFieldsTable;
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

        $res['fieldTypes'] = ['text','textarea','dropdown','image','file','checkbox','radio','email','date','time'];
        // echo "<pre>";print_r($res);exit;
        $res['deletedData'] = masterFieldInstituteModel::where('sub_institute_id',$sub_institute_id)->onlyTrashed()->get();
        $res['UserProfile'] = DB::table('tbluserprofilemaster')->where('sub_institute_id',$sub_institute_id)->get()->toArray();
        return is_mobile($type, "settings/configuration/add", $res, "view");
    }

    public function store(Request $request){
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
                    'formType' => 'required',
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
        $res['status'] = 0;
        $res['message'] = "Failed";
        // store fields 
        $this->masterInsertUpdate($request,'checkInsert',$sub_institute_id,$user_profile_id);
        if($request->formType=="addFields"){
            $insert = $this->masterInsertUpdate($request,'insert',$sub_institute_id,$user_profile_id);
    
            if($insert!=0){
                $res['status'] = 1;
                $res['message'] = "Success";
            }
        }
        // duplicate prevention 
        if($request->formType=="duplicatePrevent"){
            // echo "<pre>";print_r($request->all());exit;
            $duplicate = mastreFieldsTable::where(['sub_institute_id'=>$sub_institute_id,'main_menu'=>$request->main_menu,'module'=>$request->module])->update([
                'duplicate_fields'=>($request->duplicate_status==1) ? $request->selectedValues : null,
                'duplicate_status'=>$request->duplicate_status
            ]);
            if($duplicate!=0){
                $res['status'] = 1;
                $res['message'] = "Success";
            }
        }
        // user rights 
        if($request->formType=="usersRights"){
            echo "<pre>";print_r($request->all());exit;
        }

        // return is_mobile($type, "configurations.index", $res);
        if(in_array($type,["API","JSON"])){
            return is_mobile($type, "configurations.index", $res);
        }else{
            return redirect('/settings/configurations/create?main_menu_id='.$request->main_menu)->with(['data'=>$res]);
        }
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

        // $res['modulesList'] = masterFieldModel::when($request->has('main_menu_id'),function($q) use($request){
        //     $q->where('main_menu',$request->main_menu_id);
        // })
        // ->groupBy('module')
        // ->get();

        // $res['deletedData'] = masterFieldInstituteModel::where('sub_institute_id',$sub_institute_id)->onlyTrashed()->get();

        // $res['fieldTypes'] = ['text','textarea','dropdown','file','checkbox','radio','email','date','time'];
        // return is_mobile($type, "settings/configuration/add", $res, "view");
        if(!empty($update)){
            $res['status']=1;
            $res['message']='succes';
        }else{
            $res['status']='0';
            $res['message']='No Data Found';
        }

        if(in_array($type,["API","JSON"])){
            return is_mobile($type, "configurations.index", $res);
        }else{
            return redirect('/settings/configurations/create?main_menu_id='.$request->main_menu_id)->with(['data'=>$res]);
        }
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
                            ->where(['sub_institute_id'=>$sub_institute_id,'section'=>$request->section,'field_name'=>$value])
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
                    'main_menu_id' => 'required|numeric',
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
        $modelData = masterFieldInstituteModel::where(['sub_institute_id'=>$sub_institute_id,'module'=>$request->module])->orderBy('sort_order','ASC')->get();
        if(count($modelData)==0){
            $modelData = masterFieldModel::where('module',$request->module)->orderBy('sort_order','ASC')->get();
        }
        $tableFields = $duplicateFields = $sectionData = [];
        $excludedColumns = [];
        foreach ($modelData as $key => $value) {
            // if(!in_array($value['table_name'],$tableName)){
            //     $tableData = DB::getSchemaBuilder()->getColumnListing($value['table_name']);
                 $excludedColumns[] = $value['field_name'];
            //     $tableFields = array_diff($tableData, $excludedColumns);
            // }
            $sectionData[$value->section][] =$value; 
        }
        // get master table data
        $masterTable  = MastreFieldsTable::where([
            'main_menu' => $request->main_menu_id,
            'module' => $request->module,
        ])
        ->whereNull('deleted_at')
        ->where(function ($query) use ($sub_institute_id) {
            $query->where('sub_institute_id', $sub_institute_id)
                  ->orWhere(function ($subQuery) use ($sub_institute_id) {
                      if (!MastreFieldsTable::where('sub_institute_id', $sub_institute_id)->exists()) {
                          $subQuery->where('sub_institute_id', 0);
                      }
                  });
        })
        ->first();
    

        if(isset($masterTable->table_name) && $masterTable->table_name!=''){
            // explode excludeColumns
            $excludedColumnsArr = is_string($masterTable->exclude_columns) ? explode(',', $masterTable->exclude_columns) : [];
            $mergedColumns = array_merge($excludedColumnsArr,$excludedColumns);
            $tableData = DB::getSchemaBuilder()->getColumnListing($masterTable->table_name);
            $tableFields = array_diff($tableData, $mergedColumns);
            $duplicateFields = $tableData;
        }

        $res['tableData'] = $sectionData;
        $res['masterTable'] = $masterTable;
        $res['table_fields'] = $tableFields;
        $res['duplicateFields'] = $duplicateFields;
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
            
            $checkMasterTableExists = mastreFieldsTable::where('sub_institute_id',$sub_institute_id)->get();
            if(count($checkMasterTableExists)==0){
                $checkMasterTable = mastreFieldsTable::where('sub_institute_id',0)->get();
                foreach ($checkMasterTable as $key => $value) {
                    $tableData = mastreFieldsTable::insert([
                        'main_menu'=>$value['main_menu'],
                        'module'=>$value['module'],
                        'table_name'=>$value['table_name'],
                        'exclude_columns'=>$value['exclude_columns'],
                        'sub_institute_id'=>$sub_institute_id,
                        'created_by'=>$user_profile_id,
                        'created_at'=>now(),
                    ]);
                }
            }
            
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
                        // 'table_name'=>$value['table_name'],
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
            // 'table_name'=>$request->table_name ?? null,
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

    public function restoreData(Request $request)
    {
        // echo "<pre>";print_r($request->all());exit;
        $type = $request->input('type');
        $sub_institute_id = session()->get('sub_institute_id');
        $user_profile_id = session()->get('user_profile_id');

        // Check if the request type is API or JSON
        if (in_array($type, ["API", "JSON"])) {
            try {
                if (! $this->jwtToken()->validate()) {
                    return response()->json(['status' => '2', 'message' => 'Token Auth Failed', 'data' => []], 200);
                }

                $validator = Validator::make($request->all(), [
                    'sub_institute_id' => 'required|numeric',
                    'user_profile_id' => 'required|numeric',
                    'fieldIds' => 'required|array', 
                ]);

                if ($validator->fails()) {
                    return response()->json([
                        'status' => '0',
                        'message' => $validator->messages()
                    ]);
                }

            } catch (\Exception $e) {
                return response()->json(['status' => '2', 'message' => $e->getMessage(), 'data' => []], 200);
            }
        }

        $i=0;
        foreach ($request->fieldIds as $key => $fieldId) {
            // Find soft-deleted record using withTrashed()
            $record = masterFieldInstituteModel::withTrashed()->find($fieldId);

            if ($record) {
                $record->restore(); // Restore the record
                $i++;
            }
        }

        if($i>0){
            $res = ['status' => '1', 'message' => "Successfully Restored"];
        }
        else{
          $res = ['status' => '0', 'message' => "Failed to restore"];
        }

        return is_mobile($type, "configurations.index", $res);
    }

}
