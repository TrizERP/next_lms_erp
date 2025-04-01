<?php

namespace App\Http\Controllers\lms\library;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use function App\Helpers\is_mobile;
use App\Models\lms\library\jobOccupation;
use GenTux\Jwt\GetsJwtToken;
use Validator;
use Illuminate\Support\Facades\DB;

class jobOccupationController extends Controller
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
        $user_id = session()->get('user_id');

        if(in_array($type,["API","JSON"])){
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
        // DB::enableQueryLog();
        $jobroleData = jobOccupation::when($request->has('industries'),function($query) use($request){
            $query->where('industries',$request->industries);
        })
        ->when($request->has('sector'),function($query) use($request){
            $query->where('sector',$request->sector);
        })
        ->when($request->has('track'),function($query) use($request){
            $query->where('track',$request->track);
        })
        ->when($request->has('jobrole'),function($query) use($request){
            $query->where('jobrole',$request->jobrole);
        })
        ->when($request->has('status'),function($query) use($request){
            $query->where('status',$request->status);
        })
        ->when($request->has('jobType'),function($query) use($request){
            $query->where('type',$request->jobType);
        })
        ->when($request->has('code'),function($query) use($request){
            $query->where('code',$request->code);
        })
        ->whereNull('deleted_at')
        ->when($request->has('groupBy'),function($query) use($request){
            $query->groupBy($request->groupBy);
        })
        ->get();
        // dd(DB::getQueryLog($jobroleData));
        if(count($jobroleData)>0){
            $res['status'] = "1";
            $res['message'] = "Job Occupation Found";
        }
        else{
            $res['status'] = "0";
            $res['message'] = "No Job Occupation Found";
        }

        $res['jobroleData'] = $jobroleData;
        
        // currently views for web are not created in  erp
        return is_mobile($type, "jobroleOccupation.index", $res);      

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
        //
        $type = $request->type;
        $sub_institute_id = session()->get('sub_institute_id');
        $user_id = session()->get('user_id');
        $industries = $request->industries;
        $sector = $request->sector;
        $track = $request->track;
        $jobrole = $request->jobrole;
        $description = $request->has('description') ? $request->description : null;
        $status = $request->status;
        $Jobtype = $request->has('Jobtype') ? $request->Jobtype : 'E';
        $code = $request->has('code') ? $request->code : null;

        if(in_array($type,["API","JSON"])){
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
                    'industries' => 'required',
                    'sector' => 'required',
                    'track' => 'required',
                    'jobrole' => 'required',
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

        $insertArr = [
            "industries"=>$industries,
            "sector"=>$sector,
            "track"=>$track,
            "jobrole"=>$jobrole,
            "description"=>$description,
            "status"=>$status,
            "type"=>$Jobtype,
            "code"=>$code,
            "sub_institute_id"=>$sub_institute_id,
            "created_by"=>$user_id,
            "created_at"=>now(),
        ];

        $insret = jobOccupation::insert($insertArr);

        if($insret){
            $res['status_code'] = 1;
            $res['message'] = "Job Occupation Added Successfully";
        }
        else{
            $res['status_code'] = 0;
            $res['message'] = "Job Occupation Failed to Add";
        }
        // currently views for web are not created in  erp
        return is_mobile($type, "jobroleOccupation.index", $res);      
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
    public function edit(Request $request, $id)
    {
        //
        $type = $request->type;
        $sub_institute_id = session()->get('sub_institute_id');
        $user_id = session()->get('user_id');

        if(in_array($type,["API","JSON"])){
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


        $editData = jobOccupation::find($id);

        if($editData){
            $res['status_code'] = 1;
            $res['message'] = "Job Occupation Data Found";
        }
        else{
            $res['status_code'] = 0;
            $res['message'] = "Job Occupation Failed to Find";
        }
        $res['editData']=$editData;
        // currently views for web are not created in  erp
        return is_mobile($type, "jobroleOccupation.index", $res);   
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
        $type = $request->type;
        $sub_institute_id = session()->get('sub_institute_id');
        $user_id = session()->get('user_id');
        $industries = $request->industries;
        $sector = $request->sector;
        $track = $request->track;
        $jobrole = $request->jobrole;
        $description = $request->has('description') ? $request->description : null;
        $status = $request->status;
        $Jobtype = $request->has('Jobtype') ? $request->Jobtype : 'E';
        $code = $request->has('code') ? $request->status : null;

        if(in_array($type,["API","JSON"])){
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
                    'industries' => 'required',
                    'sector' => 'required',
                    'track' => 'required',
                    'jobrole' => 'required',
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

        $updateArr = [
            "industries"=>$industries,
            "sector"=>$sector,
            "track"=>$track,
            "jobrole"=>$jobrole,
            "description"=>$description,
            "status"=>$status,
            "type"=>$Jobtype,
            "code"=>$code,
            "sub_institute_id"=>$sub_institute_id,
            "updated_by"=>$user_id,
            "updated_at"=>now(),
        ];

        $update = jobOccupation::where('id',$id)->update($updateArr);

        if($update){
            $res['status_code'] = 1;
            $res['message'] = "Job Occupation Updated Successfully";
        }
        else{
            $res['status_code'] = 0;
            $res['message'] = "Job Occupation Failed to Update";
        }
        // currently views for web are not created in  erp
        return is_mobile($type, "jobroleOccupation.index", $res);   
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy(Request $request,$id)
    {
        //
        $type = $request->type;
        $sub_institute_id = session()->get('sub_institute_id');
        $user_id = session()->get('user_id');

        if(in_array($type,["API","JSON"])){
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

        $delete = jobOccupation::where('id',$id)->update(['deleted_by'=>$user_id,'deleted_at'=>now()]);

        if($delete){
            $res['status_code'] = 1;
            $res['message'] = "Job Occupation Deleted Successfully";
        }
        else{
            $res['status_code'] = 0;
            $res['message'] = "Job Occupation Failed to Delete";
        }
        return is_mobile($type, "jobroleOccupation.index", $res);
    }
}
