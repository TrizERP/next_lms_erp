<?php

namespace App\Http\Controllers\custom_module\customMapModule;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use function App\Helpers\is_mobile;
use App\Models\custom_module\customMapModule\donationModel;
use App\Models\fees\bank_master\bankmasterModel;
use GenTux\Jwt\GetsJwtToken;
use Carbon\Carbon;
use validator;
use DB;

class donationController extends Controller
{
    use GetsJwtToken;

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $type = $request->input('type');
        $sub_institute_id = session()->get('sub_institute_id');
        if(in_array($type,['API','JSON'])){
            try {
                if (!$this->jwtToken()->validate()) {
                    $response = ['status' => '2', 'message' => 'Token Auth Failed', 'data' => []];
    
                    return response()->json($response, 401);
                }
            } catch (\Exception $e) {
                $response = ['status' => '2', 'message' => $e->getMessage(), 'data' => []];
    
                return response()->json($response, 401);
            }
            $sub_institute_id = $request->get('sub_institute_id');

            $validator = validator::make($request->all(), [
                'sub_institute_id' => 'required|integer',
            ]);

            if ($validator->fails()) {
                $response = ['status' => '2', 'message' => $validator->errors()->first(), 'data' => []];
                return response()->json($response, 400);
            }
        }
        $res['donarLists'] = DB::table('Z_donarDetails')->where('sub_institute_id',$sub_institute_id)->groupBy('full_name')->get();

        return is_mobile($type, "custom_modules.customMapModule.donation", $res, "view");
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create(Request $request)
    {
        // echo "<pre>";print_R($request->all());exit;
        $type = $request->input('type');
        $sub_institute_id = session()->get('sub_institute_id');
        $syear = session()->get('syear');

        if(in_array($type,['API','JSON'])){
            try {
                if (!$this->jwtToken()->validate()) {
                    $response = ['status' => '2', 'message' => 'Token Auth Failed', 'data' => []];
    
                    return response()->json($response, 401);
                }
            } catch (\Exception $e) {
                $response = ['status' => '2', 'message' => $e->getMessage(), 'data' => []];
    
                return response()->json($response, 401);
            }

            $validator = validator::make($request->all(), [
                'sub_institute_id' => 'required|integer',
                'syear' => 'required|integer',
            ]);

            if ($validator->fails()) {
                $response = ['status' => '2', 'message' => $validator->errors()->first(), 'data' => []];
                return response()->json($response, 400);
            }

            $sub_institute_id = $request->get('sub_institute_id');
            $syear = $request->get('syear');
        }
        $res['donarLists'] = DB::table('Z_donarDetails')->where('sub_institute_id',$sub_institute_id)->groupBy('full_name')->get();
        // DB::enableQueryLog();
        $res['donarData'] = DB::table('Z_donarDetails')
                            ->where('sub_institute_id',$sub_institute_id)
                            ->when($request->has('full_name') && $request->full_name!='',function($q) use($request){
                                $q->where('full_name',$request->full_name);
                            })
                            ->when($request->has('mobile_number') && $request->mobile_number!='',function($q) use($request){
                                $q->where('mobile_number',$request->mobile_number);
                            })
                            ->first();

        if(!empty($res['donarData']) && isset($res['donarData']->id)){
            $res['donationData'] = DB::table('donation_collection')
                ->where('sub_institute_id',$sub_institute_id)
                ->where('donar_id',$res['donarData']->id)
                ->whereNull('deleted_at')
                ->get()->toArray();

            $res['cancellData'] = DB::table('donation_collection')
                ->where('sub_institute_id',$sub_institute_id)
                ->where('donar_id',$res['donarData']->id)
                ->whereNotNull('deleted_at')
                ->get()->toArray();
        }
        // dd(DB::getQueryLog($res['donarData']));
        // echo "<pre>";print_r($res['donarData']);exit;
        if(empty($res['donarData'])){
            $res['status'] = 0;
            $res['message'] = 'Failed to Find Details !';
        }

        $res['donation_head'] = ['CORP./CONST.'=>'CORP./CONST.','EDUCATION'=>'EDUCATION','FOOD'=>'FOOD','OTHERS'=>'OTHERS'];

        if($sub_institute_id==76){
            $res['paymentModes'] = ['Cash'=>'CASH','Cheque'=>'CHEQUE','POS'=>'POS','Online'=>'ONLINE','UPI'=>'UPI','RTGS/NEFT'=>'RTGS/NEFT'];
        }
        else{
            $res['paymentModes'] = ['Cash'=>'Cash','Cheque'=>'Cheque','DD'=>'DD','Online'=>'Online','NACH'=>'NACH','UPI'=>'UPI','Swipe1'=>'Swipe1','Swipe2'=>'Swipe2','Swipe3'=>'Swipe3','POS'=>'POS'];
        }
        $res['bank_data'] = bankmasterModel::orderBy('bank_name', 'asc')->get()->toArray();

        $res['fees_config'] = DB::table('fees_config_master as fc')
        ->join('fees_receipt_css as frc', function ($join) {
            $join->whereRaw('frc.receipt_id = fc.fees_receipt_template');
        })->selectRaw('fc.* ,frc.css')
        ->where('fc.sub_institute_id', $sub_institute_id)
        ->where('fc.syear', $syear)->first();

        $res['full_name'] = $request->full_name;
        $res['mobile_number'] = $request->mobile_number;

        return is_mobile($type, "custom_modules.customMapModule.donation", $res, "view");
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $type = $request->input('type');
        $sub_institute_id = session()->get('sub_institute_id');
        $syear = session()->get('syear');
        $user_id = session()->get('user_id');
        if(in_array($type,['API','JSON'])){
            try {
                if (!$this->jwtToken()->validate()) {
                    $response = ['status' => '2', 'message' => 'Token Auth Failed', 'data' => []];
    
                    return response()->json($response, 401);
                }
            } catch (\Exception $e) {
                $response = ['status' => '2', 'message' => $e->getMessage(), 'data' => []];
    
                return response()->json($response, 401);
            }
            
            $sub_institute_id = $request->get('sub_institute_id');
            $syear = $request->get('syear');
            $user_id = $request->get('user_id');

            $validator = validator::make($request->all(), [
                'sub_institute_id' => 'required|integer',
                'syear' => 'required|integer',
                'user_id' => 'required|integer',
                'donar_id' => 'required|integer',
                'amount' => 'required|numeric',
                'paid_date' => 'required|date_format:d-m-Y',
                'payment_mode' => 'required|string',
                'donation_head' => 'required|string',
            ]);
            if ($validator->fails()) {
                $response = ['status' => '2', 'message' => $validator->errors()->first(), 'data' => []];
                return response()->json($response, 400);
            }
        }

        $fees_config = DB::table('fees_config_master as fc')
        ->join('fees_receipt_css as frc', function ($join) {
            $join->whereRaw('frc.receipt_id = fc.fees_receipt_template');
        })->selectRaw('fc.* ,frc.css')
        ->where('fc.sub_institute_id', $sub_institute_id)
        ->where('fc.syear', $syear)->first();

        $receipt_css = '<style>'.$fees_config->css.'</style>';

        $receipt_book_arr = DB::table('fees_receipt_book_master')
        ->selectRaw('*,GROUP_CONCAT(fees_head_id) heads')
        ->where('syear', $syear)
        ->where('sub_institute_id', $sub_institute_id)
        ->where('sort_order', 2)
        ->groupByRaw('receipt_line_1,receipt_line_2,receipt_line_3,receipt_line_4,receipt_prefix,receipt_logo,last_receipt_number')
        ->first();

        $RECEIPT_NO_max = donationModel::where('sub_institute_id',$sub_institute_id)->whereNull('deleted_at')->latest()->first();
        $RECEIPT_NO = (isset($RECEIPT_NO_max->reciept_no) && $RECEIPT_NO_max->reciept_no!='') ? ($RECEIPT_NO_max->reciept_no+1) : 1;
        // echo "<pre>";print_r($RECEIPT_NO);exit;
        if ($receipt_book_arr->receipt_logo != '') {
        $image_path = "http://" . $_SERVER['HTTP_HOST'] . "/storage/fees/" . $receipt_book_arr->receipt_logo;
        }
            $recHtml = '<style>.fees-receipt{border:none !important;}.fees-receipt th,
                            .fees-receipt td,
                            .sc-hd{
                            font-size:16px !important
                            }</style>
                    <br><br>
                    <div style="padding:20px 30px 0px 50px;width:100%;">
                    <table class="fees-receipt" border-collapse="collapse" style="margin:0 auto;border:0px !important" width="80%" cellspacing="0" border="0"%">
                    <tbody>
                    <tr>
                            <td colspan="4"> <br> <br> <br> <br> <br> <br><br></td>
                        </tr>
      					<tr>
                        <td colspan="4" style="text-align:center !important;" align="center"> ';
        
            if ($receipt_book_arr->receipt_line_2 != '') {
                $recHtml .= '<span class="sc-hd" style="padding:2px;">' . $receipt_book_arr->receipt_line_2 . '</span><br>';
            }
            if ($receipt_book_arr->receipt_line_3 != '') {
                $recHtml .= '<span class="sc-hd" style="padding:2px;">' . $receipt_book_arr->receipt_line_3 . '</span><br>';
            }
            $recHtml .= '</td>';
            $recHtml .= '</tr>';

            $recHtml .= '<tr>
            <td colspan="4"><hr style="padding:2px;border:1px solid black"></td>
            </tr>';

            $syear1 = $syear;
            $syear2 = $syear1 + 1;
            $edu_year = "$syear1-$syear2";

            $recHtml .= '<tr>';
            $recHtml .= '   <td colspan="2" style="white-space:nowrap;" align="left">';
            $recHtml .= '       REC.NO. : <label><b>' . $RECEIPT_NO . '</b></label>';
            $recHtml .= '   </td>';
            $recHtml .= '   <td colspan="2" align="right">';
            $recHtml .= '      Date : <label><b>' . $request->paid_date . '</b></label>';
            $recHtml .= '   </td>';
            $recHtml .= '</tr>';

            $recHtml .= '<tr>';
            $recHtml .= '   <td colspan="2" align="left">';
            $recHtml .= '       NAME : <label><b>' . $request->full_name . '</b></label>';
            $recHtml .= '   </td>';
            $recHtml .= '   <td colspan="2" align="right">';
            $recHtml .= '       PAN : <label><b>' . $request->pan_number . '</b></label>';
            $recHtml .= '   </td>';
            $recHtml .= '</tr>';

            $recHtml .= '<tr>';
            $recHtml .= '   <td colspan="2" align="left">';
            $recHtml .= '       ADDRESS : <label><b>' . $request->address . '</b></label>';
            $recHtml .= '   </td>';
            $recHtml .= '   <td colspan="2" align="right">';
            $recHtml .= '       MOB : <label><b>' . $request->mobile_number . '</b></label>';
            $recHtml .= '   </td>';
            $recHtml .= '</tr>';


            $recHtml .= '<tr>';
            $recHtml .= '   <td colspan="4" valign="top">';
            $recHtml .= '       <table class="particulars" width="100%" border="0">';
            $recHtml .= '       <tr>
                                    <td colspan="3" style="background-color:lightgray"><b>Particulars</b></td>
                                    <td style="background-color:lightgray;white-space:nowrap;"><b>Amount (Rs.)</b></td>
                                 </tr>';
            $recHtml .= '           <tr>';
            $recHtml .= '               <td align="left" colspan="3">' . $request->donation_head . '</td>';
            $recHtml .= '               <td align="right" >' . $request->amount . '</td>';
            $recHtml .= '           </tr>';
            $recHtml .= '           <tr>';
            $recHtml .= '               <td align="left" colspan="3"><b>Total</b></td>';
            $recHtml .= '               <td align="right" ><b>' . $request->amount . '</b></td>';
            $recHtml .= '           </tr>';
            $recHtml .= '       </table>';
            $recHtml .= '   </td>';
            $recHtml .= '</tr>';

            $total_amount_in_words = ucwords($this->convert_number_to_words($request->amount));
            if ($total_amount_in_words != "") {
                $total_amount_in_words_str = "Rupees " . $total_amount_in_words . " Only";
            } else {
                $total_amount_in_words_str = "";
            }

            $recHtml .= '<tr>';
            $recHtml .= '   <td colspan="4" style="text-align:left !important;">';
            $recHtml .= '       <label><b>Amount In Words : </b></label>';
            $recHtml .= '       <span>' . $total_amount_in_words_str . '</span>';
            $recHtml .= '   </td>';
            $recHtml .= '</tr>';

            $recHtml .= '<tr>';
            $recHtml .= '   <td colspan="4" class="padding" style="text-align:left !important;">';
            $recHtml .= '       <label><b>Payment Mode : </b></label>';
            if ($request->payment_mode == 'Cash') {
                $recHtml .= '       <span><u>' . strtoupper($request->payment_mode) . '</u></span>';
            } else {
                $recHtml .= '       <span><u>' . strtoupper($request->payment_mode) . ' - ' . strtoupper($request->bank_name) . ' - ' . $request->cheque_no . '</u></span>';
            }
            $recHtml .= '   </td>';
            $recHtml .= '</tr>';

             $recHtml .= ' <tr>
            <td colspan="3" class="padding" style="font-size:14px !important;"><span>Has been Thanksfully Received by Shri Swaminarayan Mission
            <br><br>
            Income Tax Exemtion U/S 80G(5) No.AAAA53328NF20218/Tech/80G(5)/(05/1)
                2008-09.<br>Dt.31-05-2021 Valid from 01/04/2021 to 31/03/2025 to and onwards</span></td>
            <td class="padding"> <label><b>SIGNATURE</b></label><br><label><b>'.session()->get('name').'</b></label> </td>
            </tr>';

            $recHtml .= '</tbody></table></div>';
            // insert values into table
            $inertArr = array(
                'donar_id'=>$request->donar_id,
                'paid_date'=>Carbon::createFromFormat('d-m-Y', $request->paid_date)->format('Y-m-d'),
                'donation_head'=>$request->donation_head,
                'donation_amount'=>$request->amount,
                'payment_mode'=>$request->payment_mode,
                'cheque_number'=>$request->cheque_no ?? null,
                'cheque_date'=>$request->cheque_date ?? null,
                'bank_name'=>$request->bank_name ?? null,
                'bank_branch'=>$request->bank_branch ?? null,
                'remarks'=>$request->remarks ?? null,
                'reciept_no'=>$RECEIPT_NO,
                'reciept_html'=>$receipt_css . $recHtml,
                'sub_institute_id'=>$sub_institute_id,
                'created_by'=>$user_id,
                'created_at'=>now()
            );
            // echo "<pre>";print_r($inertArr);exit;
            donationModel::insert($inertArr);
            $last_inserted_id = DB::getPdo()->lastInsertId();

            $new_html = '<div class="row">' . $receipt_css . $recHtml . '</div>
            <div class="pagebreak"></div> <br><br>';

            $res['status'] = "1";
            $res['receipt_html'] = $new_html;
            $res['last_inserted_ids'] = $last_inserted_id;
            $res['pageSize'] = $fees_config->fees_receipt_template;
            $res['message'] = "Donation collect successfully";
        // echo "<pre>";print_r($recHtml);exit;
        return is_mobile($type, "custom_modules.customMapModule.donationReceipt", $res, "view");
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
    public function destroy(Request $request,$id)
    {
        // return $request->all();
        $type= $request->input('type');
        $sub_institute_id = session()->get('sub_institute_id');
        $user_id = session()->get('user_id');
        if(in_array($type,['API','JSON'])){
            try {
                if (!$this->jwtToken()->validate()) {
                    $response = ['status' => '2', 'message' => 'Token Auth Failed', 'data' => []];
    
                    return response()->json($response, 401);
                }
            } catch (\Exception $e) {
                $response = ['status' => '2', 'message' => $e->getMessage(), 'data' => []];
    
                return response()->json($response, 401);
            }
            
            $sub_institute_id = $request->get('sub_institute_id');
            $user_id = $request->get('user_id');

            $validator = validator::make($request->all(), [
                'sub_institute_id' => 'required|integer',
                'user_id' => 'required|integer',
                'deleteData' => 'required|array',
                'deleteData.*' => 'required|integer',
            ]);
            if ($validator->fails()) {
                $response = ['status' => '2', 'message' => $validator->errors()->first(), 'data' => []];
                return response()->json($response, 400);
            }
        }
        $i = 0;
        foreach ($request->deleteData as $key => $value) {
            $i++;
            DB::table('donation_collection')->where('id',$value)->update(['deleted_at'=>now(),'deleted_by'=>$user_id]);
        }

        if($i>0){
            $res['status'] = 1;
            $res['message'] = 'Donation Deleted Successfully !';
        }else{
            $res['status'] = 0;
            $res['message'] = 'Failed to Delete Donation !';
        }
        return is_mobile($type, "donation_collection.index", $res);
    }

    public function donationReport(Request $request){
        $type = $request->input('type');
        $sub_institute_id = session()->get('sub_institute_id');
        $syear = session()->get('syear');
        $payment_mode = $request->input('payment_mode');

        if(in_array($type,['API','JSON'])){
            try {
                if (!$this->jwtToken()->validate()) {
                    $response = ['status' => '2', 'message' => 'Token Auth Failed', 'data' => []];
    
                    return response()->json($response, 401);
                }
            } catch (\Exception $e) {
                $response = ['status' => '2', 'message' => $e->getMessage(), 'data' => []];
    
                return response()->json($response, 401);
            }
            $sub_institute_id = $request->get('sub_institute_id');

            $validator = validator::make($request->all(), [
                'sub_institute_id' => 'required|integer',
            ]);

            if ($validator->fails()) {
                $response = ['status' => '2', 'message' => $validator->errors()->first(), 'data' => []];
                return response()->json($response, 400);
            }
        }
        $receipt_book= DB::table('fees_receipt_book_master')->where(['sub_institute_id' => $sub_institute_id,'status' => 1,'syear'=>$syear])
        ->selectRaw('*,GROUP_CONCAT(DISTINCT standard_id) as standards,GROUP_CONCAT(DISTINCT fees_head_id) as heads')    
        ->when($request->has('receipt_title'),function($q) use($request){
             $q->where('sort_order',$request->receipt_title);
         })
         ->first();

        $datewiseData = [];
        $reportData = [];
        if($request->has('Search') && $request->Search=='Search'){
            $reportData = DB::table('donation_collection as dc')
            ->join('Z_donarDetails as dd', function ($join) {
                $join->on('dc.donar_id', '=', 'dd.id');
            })
            ->selectRaw('dd.*,dc.*,dc.id as id')
            ->where('dc.sub_institute_id', $sub_institute_id)
            ->when($request->has('full_name') && $request->full_name != '', function ($q) use ($request) {
                $q->where('dd.full_name', $request->full_name);
            })
            ->when($request->has('mobile_number') && $request->mobile_number != '', function ($q) use ($request) {
                $q->where('dd.mobile_number', $request->mobile_number);
            })
            ->when($request->has('from_date') && $request->from_date != '', function ($q) use ($request) {
                $q->whereBetween('dc.paid_date', [$request->from_date,$request->to_date]);
            })
            ->when($request->has('payment_mode'),function($q) use($request){
                    $q->where('dc.payment_mode',$request->payment_mode);
            })
            ->whereNull('dc.deleted_at')
            ->orderByRaw('dc.paid_date,CAST(reciept_no AS UNSIGNED)')
            ->get()
            ->toArray();
        }

        foreach ($reportData as $key => $value) {
            if($value->donation_amount!=0){
                $datewiseData[$value->paid_date.'||'.$value->payment_mode][]=$value;
            }
        }
        // echo "<pre>";print_r($datewiseData);exit;
        if(empty($datewiseData)){
            $res['status']=0;
            $res['message']='No Data Found';
        }

        $res['payment_mode'] = ['Cash'=>'CASH','Cheque'=>'CHEQUE','POS'=>'POS','Online'=>'ONLINE','UPI'=>'UPI','RTGS/NEFT'=>'RTGS/NEFT'];

        $res['datewiseData'] = $datewiseData;
        $res['school_details'] = $receipt_book;
        // echo "<pre>";print_r($res);exit;
        $from_date = $request->from_date ?? now();
        $to_date = $request->to_date ?? now();
        $res['selPaymentMode'] = isset($request->payment_mode) ? $request->payment_mode : '';
        $res['from_date'] = $from_date;
        $res['to_date'] = $to_date;
        $res['full_name'] = $request->full_name;
        $res['mobile_number'] = $request->mobile_number;
        $res['donarLists'] = DB::table('Z_donarDetails')->where('sub_institute_id',$sub_institute_id)->groupBy('full_name')->get();

        return is_mobile($type, "custom_modules.customMapModule.donationReport", $res, "view");
    }
    public function convert_number_to_words($number)
    {
        $hyphen = '-';
        $conjunction = ' and ';
        $separator = ', ';
        $negative = 'negative ';
        $decimal = ' point ';
        $dictionary = array(
            0 => 'zero',
            1 => 'one',
            2 => 'two',
            3 => 'three',
            4 => 'four',
            5 => 'five',
            6 => 'six',
            7 => 'seven',
            8 => 'eight',
            9 => 'nine',
            10 => 'ten',
            11 => 'eleven',
            12 => 'twelve',
            13 => 'thirteen',
            14 => 'fourteen',
            15 => 'fifteen',
            16 => 'sixteen',
            17 => 'seventeen',
            18 => 'eighteen',
            19 => 'nineteen',
            20 => 'twenty',
            30 => 'thirty',
            40 => 'fourty',
            50 => 'fifty',
            60 => 'sixty',
            70 => 'seventy',
            80 => 'eighty',
            90 => 'ninety',
            100 => 'hundred',
            1000 => 'thousand',
            1000000 => 'million',
            1000000000 => 'billion',
            1000000000000 => 'trillion',
            1000000000000000 => 'quadrillion',
            1000000000000000000 => 'quintillion'
        );

        if (!is_numeric($number)) {
            return false;
        }

        if (($number >= 0 && (int)$number < 0) || (int)$number < 0 - PHP_INT_MAX) {
            // overflow
            trigger_error(
                'convert_number_to_words only accepts numbers between -' . PHP_INT_MAX . ' and ' . PHP_INT_MAX,
                E_USER_WARNING
            );
            return false;
        }

        if ($number < 0) {
            return $negative . $this->convert_number_to_words(abs($number));
        }

        $string = $fraction = null;

        if (strpos($number, '.') !== false) {
            list($number, $fraction) = explode('.', $number);
        }

        switch (true) {
            case $number < 21:
                $string = $dictionary[$number];
                break;
            case $number < 100:
                $tens = ((int)($number / 10)) * 10;
                $units = $number % 10;
                $string = $dictionary[$tens];
                if ($units) {
                    $string .= $hyphen . $dictionary[$units];
                }
                break;
            case $number < 1000:
                $hundreds = $number / 100;
                $remainder = $number % 100;
                $string = $dictionary[$hundreds] . ' ' . $dictionary[100];
                if ($remainder) {
                    $string .= $conjunction . $this->convert_number_to_words($remainder);
                }
                break;
            default:
                $baseUnit = pow(1000, floor(log($number, 1000)));
                $numBaseUnits = (int)($number / $baseUnit);
                $remainder = $number % $baseUnit;
                $string = $this->convert_number_to_words($numBaseUnits) . ' ' . $dictionary[$baseUnit];
                if ($remainder) {
                    $string .= $remainder < 100 ? $conjunction : $separator;
                    $string .= $this->convert_number_to_words($remainder);
                }
                break;
        }

        if (null !== $fraction && is_numeric($fraction)) {
            $string .= $decimal;
            $words = array();
            foreach (str_split((string)$fraction) as $number) {
                $words[] = $dictionary[$number];
            }
            $string .= implode(' ', $words);
        }

        return $string;
    }
}
