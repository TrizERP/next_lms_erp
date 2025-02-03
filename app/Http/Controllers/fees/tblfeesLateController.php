<?php

namespace App\Http\Controllers\fees;

use App\Http\Controllers\Controller;
use App\Models\fees\tblfeesLateModel;
use App\Models\school_setup\academic_yearModel;
use App\Models\school_setup\standardModel;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use function App\Helpers\is_mobile;
use Validator;

class tblfeesLateController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index(Request $request)
    {
        $type = $request->input('type');
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $syear = $request->session()->get('syear');

        $data = tblfeesLateModel::selectRaw('fees_late_master.*')
            ->selectRaw("CONCAT_WS(' ',tbluser.first_name,tbluser.last_name) as user")
            ->selectRaw("standard.name as standard")
            ->join('tbluser', 'fees_late_master.created_by', '=', 'tbluser.id')
            ->join('standard', 'fees_late_master.standard_id', '=', 'standard.id')
            ->where('tbluser.status',1)// 23-04-24 by uma
            ->where(['fees_late_master.sub_institute_id' => $sub_institute_id, 'fees_late_master.syear' => $syear])
            ->get();

        $res['status_code'] = 1;
        $res['message'] = "Success";
        $res['data'] = $data;

        return is_mobile($type, "fees/show_fees_late", $res, "view");
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return Application|Factory|View
     */
    public function create(Request $request)
    {
        $type = $request->input('type');

        $sub_institute_id = $request->session()->get('sub_institute_id');
        $syear = $request->session()->get('syear');
        $user_id = $request->session()->get('user_id');

        if($type=="API"){
            try {
                if (!$this->jwtToken()->validate()) {
                    $response = ['status' => '2', 'message' => 'Token Auth Failed', 'data' => []];
    
                    return response()->json($response, 401);
                }
                $sub_institute_id = $request->sub_institute_id;
                $syear = $request->syear;
                $user_id = $request->user_id;

                $validator = Validator::make($request->all(), [
                    'sub_institute_id' => 'required|numeric',
                    'syear' => 'required|numeric',
                    'user_id' => 'required|numeric',
                ]);
        
                if ($validator->fails()) {
                    $response['status'] = '0';
                    $response['message'] = $validator->messages();

                    return response()->json($response);
                }
            } catch (\Exception $e) {
                $response = ['status' => '2', 'message' => $e->getMessage(), 'data' => []];
    
                return response()->json($response, 401);
            }          
        }

        $data = standardModel::where(['sub_institute_id' => $sub_institute_id])->get()->toArray();
        
        $term_list = academic_yearModel::where([
            'sub_institute_id' => $sub_institute_id, 'syear' => $syear,
        ])->get()->toArray();

        // view()->share('standard_list', $data);
        // view()->share('term_list', $term_list);
        // return view('fees/add_fees_late');

        $res['standard_list'] =$data;
        $res['term_list'] =$term_list;
        $res['fine_types'] = ["Monthly","Weekly","Daily"];

        return is_mobile($type, "fees/add_fees_late", $res, "view");
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  Request  $request
     * @return Response
     */
    public function store(Request $request)
    {
        // echo "<pre>";print_r($request->all());exit;
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $syear = $request->session()->get('syear');
        $user_id = $request->session()->get('user_id');
        $type = $request->input('type');
        $standard_ids = $request->standard_id;
        $late_date=$request->late_date;
        $term_id=$request->term_id;
        $fine_type=$request->fine_type;
        $status=$request->status;

        if($type=="API"){
            try {
                if (!$this->jwtToken()->validate()) {
                    $response = ['status' => '2', 'message' => 'Token Auth Failed', 'data' => []];
    
                    return response()->json($response, 401);
                }

                $sub_institute_id = $request->sub_institute_id;
                $syear = $request->syear;
                $user_id = $request->user_id;

                $validator = Validator::make($request->all(), [
                    'sub_institute_id' => 'required|numeric',
                    'syear' => 'required|numeric',
                    'user_id' => 'required|numeric',
                    'standard_id' => 'required',
                    'late_date' => 'required',
                    'term_id' => 'required',
                    'fine_type' => 'required',
                    'status' => 'required',
                ]);
        
                if ($validator->fails()) {
                    $response['status'] = '0';
                    $response['message'] = $validator->messages();

                    return response()->json($response);
                }
            } catch (\Exception $e) {
                $response = ['status' => '2', 'message' => $e->getMessage(), 'data' => []];
    
                return response()->json($response, 401);
            }       
        }
        $i=0;
        foreach ($standard_ids as $key => $value) {

            $Newrequest = [
                'late_date'=>$late_date,
                'standard_id'=>$value,
                'syear'=>$syear,
                'term_id'=>$term_id,
                'fine_type'=>$fine_type,
                'status'=>$status,
                'sub_institute_id'=>$sub_institute_id,
                'created_by'=>$user_id,
                'created_on'=>now()
            ];

            $data = $this->saveData($Newrequest,'insert');
            if($data){
                $i++;
            }
        }
        if($i>0){
            $res['status_code'] = "1";
            $res['message'] = "Fees Late Start Date Added successfully";
        }else{
            $res['status_code'] = "0";
            $res['message'] = "Fees Late Start Date Failed to Add";
        }

        return is_mobile($type, "fees_late_master.index", $res);
    }

    public function saveData($request,$action,$id='')
    {
        $data = 0;
        if($action=='update' && $id!=''){
            $data = tblfeesLateModel::where('id',$id)->update($request);
        }else{
            $checkData = tblfeesLateModel::where('standard_id',$request['standard_id'])->where(['sub_institute_id'=>$request['sub_institute_id'],'syear'=>$request['syear']])->get()->toArray();  
            if(empty($checkData)){
                $data = tblfeesLateModel::insert($request);
            }
        }
        return $data;
    }

    // public function updateData(Request $request,$id)
    // {
    //     $newRequest = $request->all();
    //     $id = $newRequest['id'];
    //     foreach ($newRequest as $key => $value) {
    //         if ($key != '_method' && $key != '_token' && $key != 'submit' && $key != 'id') {
    //             if (is_array($value)) {
    //                 $value = implode(",", $value);
    //             }
    //             $finalArray[$key] = $value;
    //         }
    //     }

    //     return tblfeesLateModel::where(['id' => $id])->update($finalArray);

    // }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return void
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return Application|Factory|View
     */
    public function edit(Request $request, $id)
    {
        $type = $request->type;
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $syear = $request->session()->get('syear');
        $user_id = $request->session()->get('user_id');

        if($type=="API"){
            try {
                if (!$this->jwtToken()->validate()) {
                    $response = ['status' => '2', 'message' => 'Token Auth Failed', 'data' => []];
    
                    return response()->json($response, 401);
                }
                $sub_institute_id = $request->sub_institute_id;
                $syear = $request->syear;
                $user_id = $request->user_id;
                $validator = Validator::make($request->all(), [
                    'sub_institute_id' => 'required|numeric',
                    'syear' => 'required|numeric',
                    'user_id' => 'required|numeric',
                ]);
        
                if ($validator->fails()) {
                    $response['status'] = '0';
                    $response['message'] = $validator->messages();

                    return response()->json($response);
                }
            } catch (\Exception $e) {
                $response = ['status' => '2', 'message' => $e->getMessage(), 'data' => []];
    
                return response()->json($response, 401);
            }
        }
        $data = standardModel::where(['sub_institute_id' => $sub_institute_id])->get()->toArray();

        $term_list = academic_yearModel::where([
            'sub_institute_id' => $sub_institute_id, 'syear' => $syear,
        ])->get()->toArray();

        // view()->share('standard_list', $data);
        // view()->share('term_list', $term_list);
        $editData = tblfeesLateModel::find($id)->toArray();

        // return view('fees/edit_fees_late', ['data' => $editData]);
        $res['standard_list'] =$data;
        $res['term_list'] =$term_list;
        $res['fine_types'] = ["Monthly","Weekly","Daily"];
        $res['editData'] = $editData;
        return is_mobile($type, "fees/edit_fees_late", $res, "view");
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  Request  $request
     * @param  int  $id
     * @return Response
     */
    public function update(Request $request, $id)
    {
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $syear = $request->session()->get('syear');
        $user_id = $request->session()->get('user_id');
        $type = $request->input('type');
        $standard_id = $request->standard_id;
        $late_date=$request->late_date;
        $term_id=$request->term_id;
        $fine_type=$request->fine_type;
        $status=$request->status;

        if($type=="API"){
            try {
                if (!$this->jwtToken()->validate()) {
                    $response = ['status' => '2', 'message' => 'Token Auth Failed', 'data' => []];
    
                    return response()->json($response, 401);
                }
                $sub_institute_id = $request->sub_institute_id;
                $syear = $request->syear;
                $user_id = $request->user_id;
                $validator = Validator::make($request->all(), [
                    'sub_institute_id' => 'required|numeric',
                    'syear' => 'required|numeric',
                    'user_id' => 'required|numeric',
                    'standard_ids' => 'required',
                    'late_date' => 'required',
                    'term_id' => 'required',
                    'fine_type' => 'required',
                    'status' => 'required',
                ]);
        
                if ($validator->fails()) {
                    $response['status'] = '0';
                    $response['message'] = $validator->messages();

                    return response()->json($response);
                }
            } catch (\Exception $e) {
                $response = ['status' => '2', 'message' => $e->getMessage(), 'data' => []];
    
                return response()->json($response, 401);
            }        
        }

        // $request->request->add(['id' => $id]); //add request

        // $this->updateData($request);
        $Newrequest = [
            'late_date'=>$late_date,
            'standard_id'=>$standard_id,
            'fine_type'=>$fine_type,
            'status'=>$status,
            'updated_on'=>now()
        ];
        $data = $this->saveData($Newrequest,'update',$id);

        $res['status_code'] = "1";
        $res['message'] = "Fees Late Start Date Updated successfully";

        return is_mobile($type, "fees_late_master.index", $res);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return Response
     */
    public function destroy(Request $request, $id)
    {
        $type = $request->input('type');
        tblfeesLateModel::where(["id" => $id])->delete();
        $res['status_code'] = "1";
        $res['message'] = "Fees Late Start Date deleted successfully";

        return is_mobile($type, "fees_late_master.index", $res);
    }
}
