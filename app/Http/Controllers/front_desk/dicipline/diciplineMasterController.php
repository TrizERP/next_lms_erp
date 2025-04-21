<?php

namespace App\Http\Controllers\front_desk\dicipline;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\front_desk\dicipline\diciplineDD;
use App\Models\front_desk\dicipline\diciplineMaster;
use GenTux\Jwt\GetsJwtToken;
use function App\Helpers\is_mobile;

class diciplineMasterController extends Controller
{
    use GetsJwtToken;

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        //
        $type = $request->type;
        $sub_institute_id = session()->get('sub_institute_id');
        // only for API
        if(in_array($type,["API","JSON"])){
            try {
                if (! $this->jwtToken()->validate()) {
                    $response = ['status' => '2', 'message' => 'Token Auth Failed', 'data' => []];
    
                    return response()->json($response, 401);
                }
                
              $sub_institute_id = $request->input('sub_institute_id');
              $validator = Validator::make($request->all(), [
                    'sub_institute_id'  => 'required|numeric',
                ]);
                
                if ($validator->fails()) {
                    $response['status'] = '0';
                    $response['response'] = $validator->messages()->first();
                    return response()->json($res, 401);
                } 
            } catch (\Exception $e) {
                $response = ['status' => '2', 'message' => $e->getMessage(), 'data' => []];
    
                return response()->json($response, 401);
            }
        }
       
        $response['addedData'] = diciplineMaster::join('dicipline_dd as dd',function($join){
            $join->on('dicipline_master.dicipline_id', '=', 'dd.id');
        })  
        ->selectRaw('dicipline_master.*, dd.*,dicipline_master.id as id,dicipline_master.message as message,dd.message as title')
        ->where('dicipline_master.sub_institute_id', $sub_institute_id)
        ->whereNull('dicipline_master.deleted_at') 
        ->whereNull('dd.deleted_at')
        ->get();
        
        $response['diciplineDD'] = diciplineDD::where('sub_institute_id', $sub_institute_id)->whereNull('deleted_at')->get();
        // echo "<pre>";
        // print_r($response['diciplineDD']);    exit;
        return is_mobile($type, "front_desk.dicipline_master.index", $response, "view");
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create(Request $request)
    {
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $type = $request->type;
        $sub_institute_id = session()->get('sub_institute_id');
        $user_id = session()->get('user_id');
        
        if(in_array($type,["API","JSON"])){
            try {
                if (! $this->jwtToken()->validate()) {
                    $response = ['status' => '2', 'message' => 'Token Auth Failed', 'data' => []];
    
                    return response()->json($response, 401);
                }
                
              $sub_institute_id = $request->input('sub_institute_id');
              $validator = Validator::make($request->all(), [
                    'sub_institute_id'  => 'required|numeric',
                    'user_id'   => 'required|numeric',
                ]);
                
                if ($validator->fails()) {
                    $response['status'] = '0';
                    $response['response'] = $validator->messages()->first();
                    return response()->json($res, 401);
                } 
            } catch (\Exception $e) {
                $response = ['status' => '2', 'message' => $e->getMessage(), 'data' => []];
    
                return response()->json($response, 401);
            }
        }
        $insert = null;
        $response['status'] = '0';
        $response['message'] = 'Something went wrong';

        if($request->formType == 'title'){
            $message = $request->input('title');
            $existingRecord = diciplineDD::where('sub_institute_id', $sub_institute_id)
                ->where('message', $message)
                ->whereNull('deleted_at')
                ->first();

            if (!$existingRecord) {
                $insert = diciplineDD::insert([
                    'sub_institute_id' => $sub_institute_id,
                    'message' => $message,
                    'created_by' => $user_id,
                    'created_at' => now(),
                ]);
            }
        }
        else{
            $message = $request->input('message');
            $insert = diciplineMaster::insert([
                'dicipline_id' => $request->input('dicipline_id'),
                'sub_institute_id' => $sub_institute_id,
                'message' => $message,
                'created_by' => $user_id,
                'created_at' => now(),
            ]);
        }

        if($insert){
            $response['status'] = '1';
            $response['message'] = 'Data inserted successfully';
        }

        return is_mobile($type, "dicipline-Master.index", $response);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show(Request $request,$id)
    {
        $type = $request->type;
        $sub_institute_id = session()->get('sub_institute_id');
        $user_id = session()->get('user_id');
        
        if(in_array($type,["API","JSON"])){
            try {
                if (! $this->jwtToken()->validate()) {
                    $response = ['status' => '2', 'message' => 'Token Auth Failed', 'data' => []];
    
                    return response()->json($response, 401);
                }
                
              $sub_institute_id = $request->input('sub_institute_id');
                $user_id = $request->input('user_id');
              $validator = Validator::make($request->all(), [
                    'sub_institute_id'  => 'required|numeric',
                    'user_id'  => 'required|numeric',
                ]);
                
                if ($validator->fails()) {
                    $response['status'] = '0';
                    $response['response'] = $validator->messages()->first();
                    return response()->json($res, 401);
                } 
            } catch (\Exception $e) {
                $response = ['status' => '2', 'message' => $e->getMessage(), 'data' => []];
    
                return response()->json($response, 401);
            }
        }
        $data = null;
        $response['status'] = '0';
        $response['message'] = 'Something went wrong';

        $response['masterData'] = diciplineMaster::where('dicipline_id', $id)->whereNull('deleted_at')->get();
        
        if($data){
            $response['status'] = '1';
            $response['message'] = 'Data deleted successfully';
        }
        return response()->json($response, 200);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
    
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        // echo "<pre>";print_r($request->all());exit;
        $type = $request->type;
        $sub_institute_id = session()->get('sub_institute_id');
        $user_id = session()->get('user_id');
        
        if(in_array($type,["API","JSON"])){
            try {
                if (! $this->jwtToken()->validate()) {
                    $response = ['status' => '2', 'message' => 'Token Auth Failed', 'data' => []];
    
                    return response()->json($response, 401);
                }
                
              $sub_institute_id = $request->input('sub_institute_id');
              $user_id = $request->input('user_id');

              $validator = Validator::make($request->all(), [
                    'sub_institute_id'  => 'required|numeric',
                    'user_id'  => 'required|numeric',
                ]);
                
                if ($validator->fails()) {
                    $response['status'] = '0';
                    $response['response'] = $validator->messages()->first();
                    return response()->json($res, 401);
                } 
            } catch (\Exception $e) {
                $response = ['status' => '2', 'message' => $e->getMessage(), 'data' => []];
    
                return response()->json($response, 401);
            }
        }

        $update = null;
        $response['status'] = '0';
        $response['message'] = 'Something went wrong';

        if($request->formType == 'title'){
            $message = $request->input('title');

                $update = diciplineDD::where('id',$id)->update([
                    'sub_institute_id' => $sub_institute_id,
                    'message' => $message,
                    'updated_by' => $user_id,
                    'updated_at' => now(),
                ]);
            
        }
        else{
            $message = $request->input('message');
            $update = diciplineMaster::where('id',$id)->update([
                'dicipline_id' => $request->input('dicipline_id'),
                'sub_institute_id' => $sub_institute_id,
                'message' => $message,
                'updated_by' => $user_id,
                'updated_at' => now(),
            ]);
        }

        if($update){
            $response['status'] = '1';
            $response['message'] = 'Data updated successfully';
        }
        return is_mobile($type, "dicipline-Master.index", $response);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy(Request $request,$id)
    {
        $type = $request->type;
        $sub_institute_id = session()->get('sub_institute_id');
        $user_id = session()->get('user_id');
        
        if(in_array($type,["API","JSON"])){
            try {
                if (! $this->jwtToken()->validate()) {
                    $response = ['status' => '2', 'message' => 'Token Auth Failed', 'data' => []];
    
                    return response()->json($response, 401);
                }
                
              $sub_institute_id = $request->input('sub_institute_id');
                $user_id = $request->input('user_id');
              $validator = Validator::make($request->all(), [
                    'sub_institute_id'  => 'required|numeric',
                    'user_id'  => 'required|numeric',
                ]);
                
                if ($validator->fails()) {
                    $response['status'] = '0';
                    $response['response'] = $validator->messages()->first();
                    return response()->json($res, 401);
                } 
            } catch (\Exception $e) {
                $response = ['status' => '2', 'message' => $e->getMessage(), 'data' => []];
    
                return response()->json($response, 401);
            }
        }
        $delete = null;
        $response['status'] = '0';
        $response['message'] = 'Something went wrong';
        if($request->formType == 'title'){
            $delete = diciplineDD::where('id', $id)->update([
                'deleted_by' => $user_id,
                'deleted_at' => now(),
            ]);
        }
        else{
            $delete = diciplineMaster::where('id', $id)->update([
                'deleted_by' => $user_id,
                'deleted_at' => now(),
            ]);
        }
        if($delete){
            $response['status'] = '1';
            $response['message'] = 'Data deleted successfully';
        }
        return is_mobile($type, "dicipline-Master.index", $response);
    }
}
