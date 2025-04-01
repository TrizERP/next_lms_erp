<?php

namespace App\Http\Controllers\lms\library;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use function App\Helpers\is_mobile;
use App\Models\lms\library\jobroleTaskModel;
use GenTux\Jwt\GetsJwtToken;
use Validator;
use Illuminate\Support\Facades\DB;

class jobroleTaskController extends Controller
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
        $jobroleData = jobroleTaskModel::when($request->has('sector'),function($query) use($request){
            $query->where('sector',$request->sector);
        })
        ->when($request->has('track'),function($query) use($request){
            $query->where('track',$request->track);
        })
        ->when($request->has('jobrole'),function($query) use($request){
            $query->where('jobrole',$request->jobrole);
        })
        ->when($request->has('critical_work_function'),function($query) use($request){
            $query->where('critical_work_function',$request->critical_work_function);
        })
        ->when($request->has('task'),function($query) use($request){
            $query->where('task',$request->task);
        })
        ->whereNull('deleted_at')
        ->when($request->has('groupBy'),function($query) use($request){
            $query->groupBy($request->groupBy);
        })
        ->get();
        // dd(DB::getQueryLog($jobroleData));
        if(count($jobroleData)>0){
            $res['status'] = "1";
            $res['message'] = "Job Task Found";
        }
        else{
            $res['status'] = "0";
            $res['message'] = "No Job Task Found";
        }

        $res['jobroleData'] = $jobroleData;
        
        // currently views for web are not created in  erp
        return is_mobile($type, "jobroleTask.index", $res);  
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
       $sector = $request->sector;
       $track = $request->track;
       $jobrole = $request->jobrole;
       $critical_work_function = $request->critical_work_function;
       $task = $request->task;

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
                   'sector' => 'required',
                   'track' => 'required',
                   'jobrole' => 'required',
                   'task' => 'required',
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
           "sector"=>$sector,
           "track"=>$track,
           "jobrole"=>$jobrole, 
           "critical_work_function"=>$critical_work_function,
           "task"=>$task,
           "sub_institute_id"=>$sub_institute_id,
           "created_by"=>$user_id,
           "created_at"=>now(),
       ];

       $insret = jobroleTaskModel::insert($insertArr);

       if($insret){
           $res['status_code'] = 1;
           $res['message'] = "Job Task Added Successfully";
       }
       else{
           $res['status_code'] = 0;
           $res['message'] = "Job Task Failed to Add";
       }
       // currently views for web are not created in  erp
       return is_mobile($type, "jobroleTask.index", $res); 
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
    public function edit(Request $request,$id)
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

        $editData = jobroleTaskModel::find($id);
        // return $editData;exit;

        if($editData){
            $res['status_code'] = 1;
            $res['message'] = "Job Task Data Found";
        }
        else{
            $res['status_code'] = 0;
            $res['message'] = "Job Task Failed to Find";
        }
        $res['editData']=$editData;
        // currently views for web are not created in  erp
        return is_mobile($type, "jobroleTask.index", $res); 
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
        $sector = $request->sector;
        $track = $request->track;
        $jobrole = $request->jobrole;
        $critical_work_function = $request->critical_work_function;
        $task = $request->task;
 
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
                    'sector' => 'required',
                    'track' => 'required',
                    'jobrole' => 'required',
                    'task' => 'required',
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
            "sector"=>$sector,
            "track"=>$track,
            "jobrole"=>$jobrole, 
            "critical_work_function"=>$critical_work_function,
            "task"=>$task,
            "sub_institute_id"=>$sub_institute_id,
            "updated_by"=>$user_id,
            "updated_at"=>now(),
        ];
 
        $insret = jobroleTaskModel::where('id',$id)->update($updateArr);
 
        if($insret){
            $res['status_code'] = 1;
            $res['message'] = "Job Task Updated Successfully";
        }
        else{
            $res['status_code'] = 0;
            $res['message'] = "Job Task Failed to Update";
        }
        // currently views for web are not created in  erp
        return is_mobile($type, "jobroleTask.index", $res); 
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

        $delete = jobroleTaskModel::where('id',$id)->update(['deleted_by'=>$user_id,'deleted_at'=>now()]);

        if($delete){
            $res['status_code'] = 1;
            $res['message'] = "Job Task Deleted Successfully";
        }
        else{
            $res['status_code'] = 0;
            $res['message'] = "Job Task Failed to Delete";
        }
        return is_mobile($type, "jobroleTask.index", $res);
    }
}
