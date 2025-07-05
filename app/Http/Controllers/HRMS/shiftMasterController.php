<?php

namespace App\Http\Controllers\HRMS;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use function App\Helpers\is_mobile;
use GenTux\Jwt\GetsJwtToken;
use GenTux\Jwt\JwtToken;
use Illuminate\Support\Facades\Validator;
use App\Models\HRMS\userShiftMaster;
use DB;

class shiftMasterController extends Controller
{
    use GetsJwtToken; // used to check security token only for API


    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        //
        $type = $request->input('type');
        $sub_institute_id = session()->get('sub_institute_id');

        if(in_array($type,["API","JSON"])){
            try {
                 // used to check security token only for API
                if (!$this->jwtToken()->validate()) {
                    $response = ['status' => '2', 'message' => 'Token Auth Failed'];
    
                    return response()->json($response, 401);
                }

                $validate = Validator::make($request->all(),[
                    'sub_institute_id'=>'required',
                ]);

                if($validate->fails()){
                    return response()->json(['status' =>0,'message'=>$validate->errors()->first()]);
                }
                $sub_institute_id = $request->sub_institute_id;
            } catch (\Exception $e) {
                $response = ['status' => '2', 'message' => $e->getMessage(), 'data' => []];
    
                return response()->json($response, 401);
            }          
        }
        $res['status'] = 1;
        $res['message'] = 'success';
        $res['shift_data'] = userShiftMaster::where('sub_institute_id',$sub_institute_id)->whereNull('deleted_at')->orderBy('id','DESC')->get()->toArray();
        // echo "<pre>";print_r($res);exit;
        return is_mobile($type, "HRMS.user_shifts.master_index", $res, "view");
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        // echo "<pre>";print_r($request->all());exit;
        $type = $request->input('type');
        $sub_institute_id = session()->get('sub_institute_id');
        $user_id = session()->get('user_id');

        if(in_array($type,["API","JSON"])){
            try {
                 // used to check security token only for API
                if (!$this->jwtToken()->validate()) {
                    $response = ['status' => '2', 'message' => 'Token Auth Failed'];
    
                    return response()->json($response, 401);
                }

                $validate = Validator::make($request->all(),[
                    'sub_institute_id'=>'required',
                    'user_id'=>'required',
                    'shift_title'=>'required',
                    'start_time'=>'required',
                    'end_time'=>'required',
                ]);

                if($validate->fails()){
                    return response()->json(['status' =>0,'message'=>$validate->errors()->first()]);
                }

                $sub_institute_id = $request->sub_institute_id;
                $user_id = $request->user_id;

            } catch (\Exception $e) {
                $response = ['status' => '2', 'message' => $e->getMessage(), 'data' => []];
    
                return response()->json($response, 401);
            }          
        }
        // Remove '_token' and 'token' from request data
        $data = $request->except(['_token', 'token','type']);
        $data['sub_institute_id'] = $sub_institute_id;
        // Check if a record with the same sub_institute_id, shift_title, start_time, and end_time already exists
        $exists = userShiftMaster::where([
            'sub_institute_id' => $sub_institute_id,
            'shift_name' => $data['shift_name']
        ])
        ->whereNull('deleted_at')->exists();
        
        $i = 0;
        if($request->has('id') && $request->id!=''){
            $i=2;
            $data['updated_at']= now();
            $data['updated_by'] = $user_id;
            userShiftMaster::where(['id' => $request->id])->update($data);
        }
        else if (!$exists) {
            $i=1;
            $data['created_at']= now();
            $data['created_by'] = $user_id;
            userShiftMaster::insert($data);
        }
        else{
            $i=1;
            $data['updated_at']= now();
            $data['updated_by'] = $user_id;
            userShiftMaster::where(['shift_name' => $data['shift_name']])->update($data);
        }

        $res['status'] = 0;
        $res['message'] = 'Failed to Add !';

        if($i==1){
            $res['status'] = 1;
            $res['message'] = 'Added Sucessfully !';
        }
        else if($i==2){
             $res['status'] = 2;
            $res['message'] = 'Updated Sucessfully !';
        }
       
        
        return is_mobile($type, "user_shift_master.index", $res);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
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
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id,Request $request)
    {
       // echo "<pre>";print_r($request->all());exit;
        $type = $request->input('type');
        $sub_institute_id = session()->get('sub_institute_id');
        $user_id = session()->get('user_id');

        if(in_array($type,["API","JSON"])){
            try {
                 // used to check security token only for API
                if (!$this->jwtToken()->validate()) {
                    $response = ['status' => '2', 'message' => 'Token Auth Failed'];
    
                    return response()->json($response, 401);
                }

                $validate = Validator::make($request->all(),[
                    'sub_institute_id'=>'required',
                    'user_id'=>'required'
                ]);

                if($validate->fails()){
                    return response()->json(['status' =>0,'message'=>$validate->errors()->first()]);
                }

                $sub_institute_id = $request->sub_institute_id;
                $user_id = $request->user_id;

            } catch (\Exception $e) {
                $response = ['status' => '2', 'message' => $e->getMessage(), 'data' => []];
    
                return response()->json($response, 401);
            }          
        }
      
        userShiftMaster::where(['id' => $id])->update(['deleted_at'=>$user_id,'deleted_at'=>now()]);
        
        $res['status'] = 1;
        $res['message'] = 'Deleted Sucessfully !';
        
        return is_mobile($type, "user_shift_master.index", $res);
    }
}
