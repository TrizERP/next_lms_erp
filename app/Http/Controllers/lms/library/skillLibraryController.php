<?php

namespace App\Http\Controllers\lms\library;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use function App\Helpers\is_mobile;
use App\Models\lms\masterSkill;
use GenTux\Jwt\GetsJwtToken;
use Validator;
use Illuminate\Support\Facades\DB;

class skillLibraryController extends Controller
{
    use GetsJwtToken;

    //
    public function index(Request $request){
        $type = $request->type;
        $sub_institute_id = session()->get('sub_institute_id');
        $user_id = session()->get('user_id');

        if($type=="API"){
            try {
                if (!$this->jwtToken()->validate()) {
                    $response = ['status' => '2', 'message' => 'Token Auth Failed', 'data' => []];
    
                    return response()->json($response, 200);
                }
    
                $sub_institute_id = $request->get('sub_institute_id');
                $validator = Validator::make($request->all(), [
                    'sub_institute_id' => 'required|numeric',
                ]);
    
                if ($validator->fails()) {
                    $response['status'] = '0';
                    $response['message'] = $validator->messages();
                    return response()->json($response, 200);
                }
    
            } catch (\Exception $e) {
                $response = ['status' => '2', 'message' => $e->getMessage(), 'data' => []];
                return response()->json($response, 200);
            }
        }

        $skillData = masterSkill::whereNull('deleted_at')->where('status', 'Active')->get()->toArray();
        $skills = DB::table('s_jobrole')->get();
        
        $treeData = [];
        foreach ($skillData as $key => $value) {
            if(isset($value['sub_category']) && $value['sub_category']!=null && $value['sub_category']!='')
            {
                $treeData[$value['category']][$value['sub_category']][] = $value;
            }
            else
            {
                $treeData[$value['category']]['no_sub_category'][] = $value;
            }
        }
        // echo "<pre>";print_r($treeData);exit;
        $res['status'] = 1;
        $res['message'] = "success";
        $res['user_id'] = $user_id;
        $res['tableData'] = $skillData;
        $res['treeData'] = $treeData;
        $res['skills'] = $skills;
        
        return is_mobile($type, "lms/library/skill_library/index", $res, "view");        
    }

    public function create(Request $request){
        $type = $request->type;
        $sub_institute_id = session()->get('sub_institute_id');

        if($type=="API"){
            try {
                if (!$this->jwtToken()->validate()) {
                    $response = ['status' => '2', 'message' => 'Token Auth Failed', 'data' => []];
    
                    return response()->json($response, 200);
                }
    
                $sub_institute_id = $request->get('sub_institute_id');
                $validator = Validator::make($request->all(), [
                    'sub_institute_id' => 'required|numeric',
                ]);
    
                if ($validator->fails()) {
                    $response['status'] = '0';
                    $response['message'] = $validator->messages();
                    return response()->json($response, 200);
                }
    
            } catch (\Exception $e) {
                $response = ['status' => '2', 'message' => $e->getMessage(), 'data' => []];
                return response()->json($response, 200);
            }
        }

        $categoryArr = masterSkill::whereNull('deleted_at')->groupBy('category')->pluck('category')->toArray();
        $subCategoryArr =masterSkill::whereNull('deleted_at')->groupBy('sub_category')->pluck('sub_category')->toArray();

        $res['status'] = 1;
        $res['message'] = "success";
        $res['categoryArr'] = $categoryArr;
        $res['subCategoryArr'] = $subCategoryArr;
        
        return is_mobile($type, "lms/library/skill_library/add", $res, "view");        
    }

    public function store(Request $request){
        $type = $request->type;
        $sub_institute_id = session()->get('sub_institute_id');
        $user_id = session()->get('user_id');
        $category = $request->category;
        $sub_category = $request->sub_category;
        $title = $request->title;
        $description = $request->description;

        if($type=="API"){
            try {
                if (!$this->jwtToken()->validate()) {
                    $response = ['status' => '2', 'message' => 'Token Auth Failed', 'data' => []];
    
                    return response()->json($response, 200);
                }
    
                $sub_institute_id = $request->get('sub_institute_id');
                $user_id = $request->get('user_id');

                $validator = Validator::make($request->all(), [
                    'sub_institute_id' => 'required|numeric',
                    'user_id' => 'required|numeric',
                    'category' => 'required',
                    'title' => 'required',
                ]);
    
                if ($validator->fails()) {
                    $response['status'] = '0';
                    $response['message'] = $validator->messages();
                    return response()->json($response, 200);
                }
    
            } catch (\Exception $e) {
                $response = ['status' => '2', 'message' => $e->getMessage(), 'data' => []];
                return response()->json($response, 200);
            }
        }
        $res['status'] = "0";
        $res['message'] = "Failed to Add Skill";

        $data = [
            "category"=>$category,
            "sub_category" => $sub_category,
            "title"=>$title,
            "description"=>$description,
            "status"=>"Active",
            "sub_institute_id"=>$sub_institute_id,
            "created_by"=>$user_id,
            "created_at"=>now(),
        ];
        $insert = masterSkill::insert($data);

        if($insert){
            $res['status'] = "1";
            $res['message'] = "Skill Added Succefully";
        }
        
        return is_mobile($type, "skill_library.index", $res);        
    }

    public function edit(Request $request,$id){
        $type = $request->type;
        $sub_institute_id = session()->get('sub_institute_id');

        if($type=="API"){
            try {
                if (!$this->jwtToken()->validate()) {
                    $response = ['status' => '2', 'message' => 'Token Auth Failed', 'data' => []];
    
                    return response()->json($response, 200);
                }
    
                $sub_institute_id = $request->get('sub_institute_id');
                $validator = Validator::make($request->all(), [
                    'sub_institute_id' => 'required|numeric',
                ]);
    
                if ($validator->fails()) {
                    $response['status'] = '0';
                    $response['message'] = $validator->messages();
                    return response()->json($response, 200);
                }
    
            } catch (\Exception $e) {
                $response = ['status' => '2', 'message' => $e->getMessage(), 'data' => []];
                return response()->json($response, 200);
            }
        }
        $skillData = masterSkill::find($id);

        $categoryArr = masterSkill::whereNull('deleted_at')->groupBy('category')->pluck('category')->toArray();
        $subCategoryArr =masterSkill::whereNull('deleted_at')->groupBy('sub_category')->pluck('sub_category')->toArray();
        
        $res['status'] = 1;
        $res['message'] = "success";
        $res['editData'] = $skillData;
        $res['categoryArr'] = $categoryArr;
        $res['subCategoryArr'] = $subCategoryArr;
        
        return is_mobile($type, "lms/library/skill_library/edit", $res, "view");        
    }

    public function update(Request $request,$id){
        $type = $request->type;
        $sub_institute_id = session()->get('sub_institute_id');
        $user_id = session()->get('user_id');
        $category = $request->category;
        $sub_category = $request->sub_category;
        $title = $request->title;
        $description = $request->description;
        $active_status = isset($request->active_status) ? $request->active_status : 'Inactive';

        if($type=="API"){
            try {
                if (!$this->jwtToken()->validate()) {
                    $response = ['status' => '2', 'message' => 'Token Auth Failed', 'data' => []];
    
                    return response()->json($response, 200);
                }
    
                $sub_institute_id = $request->get('sub_institute_id');
                $user_id = $request->get('user_id');

                $validator = Validator::make($request->all(), [
                    'sub_institute_id' => 'required|numeric',
                    'user_id' => 'required|numeric',
                    'category' => 'required',
                    'title' => 'required',
                ]);
    
                if ($validator->fails()) {
                    $response['status'] = '0';
                    $response['message'] = $validator->messages();
                    return response()->json($response, 200);
                }
    
            } catch (\Exception $e) {
                $response = ['status' => '2', 'message' => $e->getMessage(), 'data' => []];
                return response()->json($response, 200);
            }
        }
        $res['status'] = "0";
        $res['message'] = "Failed to Update Skill";

        $data = [
            "category"=>$category,
            "sub_category" => $sub_category,
            "title"=>$title,
            "description"=>$description,
            "status"=>$active_status,
            "sub_institute_id"=>$sub_institute_id,
            "updated_at"=>now(),
        ];
        $update = masterSkill::where('id',$id)->update($data);

        if($update){
            $res['status'] = "1";
            $res['message'] = "Skill Updated Succefully";
        }
        
        return is_mobile($type, "skill_library.index", $res);        
    }

    public function destroy(Request $request,$id){
        $type = $request->type;
        $sub_institute_id = session()->get('sub_institute_id');

        if($type=="API"){
            try {
                if (!$this->jwtToken()->validate()) {
                    $response = ['status' => '2', 'message' => 'Token Auth Failed', 'data' => []];
    
                    return response()->json($response, 200);
                }
    
                $sub_institute_id = $request->get('sub_institute_id');
                $validator = Validator::make($request->all(), [
                    'sub_institute_id' => 'required|numeric',
                ]);
    
                if ($validator->fails()) {
                    $response['status'] = '0';
                    $response['message'] = $validator->messages();
                    return response()->json($response, 200);
                }
    
            } catch (\Exception $e) {
                $response = ['status' => '2', 'message' => $e->getMessage(), 'data' => []];
                return response()->json($response, 200);
            }
        }
        $res['status'] = "0";
        $res['message'] = "Failed to Delete Skill";

        $findData = masterSkill::find($id);

        if($findData){
            $findData->delete();
            $res['status'] = 1;
            $res['message'] = "Skill Deleted Successfully";
        }

        return is_mobile($type, "skill_library.index", $res);    
    }

    public function show(Request $request,$id){
        $type = $request->type;
        $sub_institute_id = session()->get('sub_institute_id');
        $user_id = session()->get('user_id');

        if($type=="API"){
            try {
                if (!$this->jwtToken()->validate()) {
                    $response = ['status' => '2', 'message' => 'Token Auth Failed', 'data' => []];
    
                    return response()->json($response, 200);
                }
    
                $sub_institute_id = $request->get('sub_institute_id');
                $validator = Validator::make($request->all(), [
                    'sub_institute_id' => 'required|numeric',
                ]);
    
                if ($validator->fails()) {
                    $response['status'] = '0';
                    $response['message'] = $validator->messages();
                    return response()->json($response, 200);
                }
    
            } catch (\Exception $e) {
                $response = ['status' => '2', 'message' => $e->getMessage(), 'data' => []];
                return response()->json($response, 200);
            }
        }

        $skillData = masterSkill::find($id);

        $res['status'] = 1;
        $res['message'] = "success";
        $res['user_id'] = $user_id;
        $res['editData'] = $skillData;
        
        return is_mobile($type, "lms/library/skill_library/show", $res, "view");   
    }
}
