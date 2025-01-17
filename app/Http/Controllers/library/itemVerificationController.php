<?php

namespace App\Http\Controllers\library;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use function App\Helpers\is_mobile;
use App\Models\library\itemStatus;
use GenTux\Jwt\GetsJwtToken;
use Validator;

class itemVerificationController extends Controller
{
    use GetsJwtToken;

    //
    public function index(Request $request){
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
        
        $res['all_items'] = itemStatus::where('sub_institute_id',$sub_institute_id)
        ->when($request->item_status_name!='',function($q) use($request){
            $q->where('item_status_name',$request->item_status_name);
        })
        ->whereNull('deleted_at')->get()->toArray();
        
        $res['status'] = 1;
        $res['message'] = "success";
        $res['searchedItem'] = $request->item_status_name;
        
        // return "hello";
        return is_mobile($type, "library/bookVarification/itemStatus", $res, "view");        
    }

    public function store(Request $request){
        $type = $request->type;
        $sub_institute_id = session()->get('sub_institute_id');
        $user_id = session()->get('user_id');
        $item_status_name = $request->get('item_status_name');
        $no_loan = $request->get('no_loan');

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
                    'item_status_name' => 'required',
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

        $data = [
            'item_status_name'=>$item_status_name,
            'sub_institute_id'=>$sub_institute_id,
        ];

        $checkExist = itemStatus::where($data)->whereNull('deleted_at')->first();

        if(!empty($checkExist))
        {
            $res['status'] = 0;
            $res['message'] = "Item Status Name Already Exists. Please Edit Data";
        }
        else
        {
            $data['no_loan'] = ($no_loan!='') ? $no_loan : 0;
            $data['created_by'] = $user_id;
            $data['created_at'] = now();

            $insert = itemStatus::insert($data);

            if($insert){
                $res['status'] = 1;
                $res['message'] = "Item Status Added Successfully";
            }
        }
        return is_mobile($type, "item_verification_status.index", $res);        
    }

    public function update(Request $request,$id){
        $type = $request->type;
        $sub_institute_id = session()->get('sub_institute_id');
        $user_id = session()->get('user_id');
        $item_status_name = $request->get('item_status_name');
        $no_loan = $request->get('no_loan');

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
                    'item_status_name' => 'required',
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
       
        $data = [
            'item_status_name'=>$item_status_name,
            'sub_institute_id'=>$sub_institute_id,
            'no_loan' => ($no_loan!='') ? $no_loan : 0,
            'updated_at' => now(),
        ];

        $findData = itemStatus::find($id);

        if($findData){
            itemStatus::where('id',$id)->update($data);
            $res['status'] = 1;
            $res['message'] = "Updated Successfully";
        }else{
            $res['status'] = 0;
            $res['message'] = "Oops ! Something Went wrong";    
        }
        // return is_mobile($type, "item_verification_status.index", $res);
        return is_mobile($type, "item_verification_status.index", $res);   
    }

    public function destroy(Request $request,$id){
        $type = $request->type;

        if($type=="API"){
            try {
                if (!$this->jwtToken()->validate()) {
                    $response = ['status' => '2', 'message' => 'Token Auth Failed', 'data' => []];
    
                    return response()->json($response, 200);
                }
    
            } catch (\Exception $e) {
                $response = ['status' => '2', 'message' => $e->getMessage(), 'data' => []];
                return response()->json($response, 200);
            }
        }

        $findData = itemStatus::find($id);

        if($findData){
            $findData->delete();
            $res['status'] = 1;
            $res['message'] = "Item Status Deleted Successfully";
        }

        return is_mobile($type, "item_verification_status.index", $res);   
    }
}
