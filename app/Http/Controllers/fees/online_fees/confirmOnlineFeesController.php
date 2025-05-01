<?php

namespace App\Http\Controllers\fees\online_fees;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use function App\Helpers\is_mobile;
use function App\Helpers\SearchStudent;
use App\Http\Controllers\fees\fees_collect\fees_collect_controller;
use App\Http\Controllers\fees\online_fees\online_fees_collect_controller;
use GenTux\Jwt\GetsJwtToken;
use Carbon\Carbon;
use Illuminate\Support\Facades\Validator;
use DB;

class confirmOnlineFeesController extends Controller
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
        $syear = session()->get('syear');

        if(in_array($type,["API","JSON"])){
            try {
                if (! $this->jwtToken()->validate()) {
                    $response = ['status' => '2', 'message' => 'Token Auth Failed'];
    
                    return response()->json($response, 401);
                }

                $sub_institute_id = $request->sub_institute_id;
                $syear = $request->syear;

                $validate = Validator::make($request->all(),[
                    'sub_institute_id'=>'required|numeric',
                    'syear'=>'required|numeric',
                    'from_date'=>'required|date',
                    'to_date'=>'required|date',
                ]);

                if($validate->fails()){
                   return $response = ['status' => '2', 'message' => $validate->messages()->first()];
                }

            } catch (\Exception $e) {
                $response = ['status' => '2', 'message' => $e->getMessage()];
    
                return response()->json($response, 401);
            }
        }

        $res['from_date'] = now();
        $res['to_date'] = now();

        if($request->has('submit') && $request->submit=="Search"){
        
            $from_date = Carbon::parse($request->from_date)->startOfDay();
            $to_date = Carbon::parse($request->to_date)->endOfDay();
            // DB::enableQueryLog();
            $searchedData = DB::table('fees_payment as fp')
                ->Join('fees_collect as fc', function($join) use($sub_institute_id,$syear){
                    $join->on('fp.hdfc_order_id', '=', 'fc.cheque_no')
                    ->on('fp.student_id', '=', 'fc.student_id')
                    ->on('fc.sub_institute_id', '=', 'fp.sub_institute_id')
                    ->on('fc.syear','=',  'fp.syear')
                    ->whereNotNull('fc.cheque_no');
                })
                ->selectRaw('fp.*')
                ->where('fp.sub_institute_id', $sub_institute_id)
                ->where('fp.syear', $syear)
                ->where('fp.axis_order_id', "1")
                ->whereBetween('fp.hdfc_payment_date', [$from_date, $to_date])
                ->groupBy('fp.id')
                ->get()->toArray();
            // dd(DB::getQueryLog($searchedData));
            $studentData = [];
            // get student details
            foreach($searchedData as $key => $value){
                $studentDetails = SearchStudent("","","", $sub_institute_id, $syear,"","","","","", $value->student_id);
                if(isset($studentDetails[0])){
                    $studentDetails[0]['payment_id'] = $value->id;
                    $studentDetails[0]['hdfc_payment_date'] = $value->hdfc_payment_date;
                    $studentDetails[0]['amount'] = $value->amount;
                    $studentDetails[0]['hdfc_transaction_id'] = $value->hdfc_transaction_id;
                }
                $studentData[] = isset($studentDetails[0]) ? $studentDetails[0] : [];
            }
            // echo "<pre>";print_r($searchedData);die;
            if(!empty($searchedData)){
                $res['status'] = "1";
                $res['message'] = "Student Found !"; 
            }else{
                $res['status'] = "0";
                $res['message'] = "No Student Found !";
            }
            $res['searchedData'] = $studentData;
            $res['from_date'] = $from_date;
            $res['to_date'] = $to_date;
        }

        return is_mobile($type, "fees.online_fees_collect.confirmOnlineFees", $res, "view");
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
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
        //
        $type = $request->type;
        $sub_institute_id = session()->get('sub_institute_id');
        $syear = session()->get('syear');
        $students = $request->students;
        if(in_array($type,["API","JSON"])){
            try {
                if (! $this->jwtToken()->validate()) {
                    $response = ['status' => '2', 'message' => 'Token Auth Failed'];
    
                    return response()->json($response, 401);
                }

                $sub_institute_id = $request->sub_institute_id;
                $syear = $request->syear;

                $validate = Validator::make($request->all(),[
                    'sub_institute_id'=>'required|numeric',
                    'syear'=>'required|numeric',
                    'students'=>'required|array',
                ]);

                if($validate->fails()){
                   return $response = ['status' => '2', 'message' => $validate->messages()->first()];
                }
                
            } catch (\Exception $e) {
                $response = ['status' => '2', 'message' => $e->getMessage()];
    
                return response()->json($response, 401);
            }
        }
        $controller = new fees_collect_controller();
        $online_controller = new online_fees_collect_controller();
        $i=0;
        foreach ($students as $studentId => $paymentId) {
           // get student bk
           foreach ($paymentId as $key => $value) {
                // $bkStudent = $controller->getBk($request,$studentId);
                // get payment details
                $payementData = DB::table('fees_payment')
                    ->where('id', $paymentId)
                    ->first();
                // $order_id = $payementData->hdfc_order_id;
                // $mer_amount = $payementData->amount;
                // $data = $online_controller->pay_fees($request, $studentId, $syear, $sub_institute_id, $mer_amount, $order_id);
                if(isset($payementData->hdfc_order_id)){
                $feesData = DB::table('fees_collect')->where('cheque_no',$payementData->hdfc_order_id)->get()->toArray();
                foreach ($feesData as $keys => $values) {
                    $html = str_replace('In Processed',' ',$values->fees_html);
                    $getFeesCollect = DB::table('fees_collect')->where('cheque_no',$payementData->hdfc_order_id)->update(['fees_html'=>$html]);

                }
                
                // get  order and update fees_collect is_deleted
                $payementData = DB::table('fees_payment')
                ->where('id', $paymentId)
                ->update(["axis_order_id"=>"2"]);
            }
                $i=1;
           }
           
        }
        // echo "<pre>";print_r($request->all());die;
        if($i==0){
            $res['status'] = "0";
            $res['message'] = "Opps ! Something went wrong.";
        }else{
            $res['status'] = "1";
            $res['message'] = "Receipt Generated Successfully !";
        }
        return is_mobile($type, "confirm_online_fees.index", $res);
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
    public function destroy($id)
    {
        //
        $type = $request->type;
        $sub_institute_id = session()->get('sub_institute_id');
        $syear = session()->get('syear');

        if(in_array($type,["API","JSON"])){
            try {
                if (! $this->jwtToken()->validate()) {
                    $response = ['status' => '2', 'message' => 'Token Auth Failed'];
    
                    return response()->json($response, 401);
                }

                $sub_institute_id = $request->sub_institute_id;
                $syear = $request->syear;

                $validate = Validator::make($request->all(),[
                    'sub_institute_id'=>'required|numeric',
                    'syear'=>'required|numeric',
                ]);

                if($validate->fails()){
                   return $response = ['status' => '2', 'message' => $validate->messages()->first()];
                }
                
            } catch (\Exception $e) {
                $response = ['status' => '2', 'message' => $e->getMessage()];
    
                return response()->json($response, 401);
            }
        }
        return is_mobile($type, "fees.online_fees_collect.confirmOnlineFees", $res, "view");
    }
}
