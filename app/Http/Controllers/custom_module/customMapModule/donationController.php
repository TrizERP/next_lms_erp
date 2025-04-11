<?php

namespace App\Http\Controllers\custom_module\customMapModule;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use function App\Helpers\is_mobile;
use App\Models\custom_module\customMapModule\donationModel;
use App\Models\fees\bank_master\bankmasterModel;
use Carbon\Carbon;
use DB;

class donationController extends Controller
{
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
            $sub_institute_id = $request->get('sub_institute_id');
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
        if(in_array($type,['API','JSON'])){
            $sub_institute_id = $request->get('sub_institute_id');
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
        // dd(DB::getQueryLog($res['donarData']));
        // echo "<pre>";print_r($res['donarData']);exit;
        if(empty($res['donarData'])){
            $res['status'] = 0;
            $res['message'] = 'Failed to Find Details !';
        }

        if($sub_institute_id==76){
            $res['paymentModes'] = ['Cash'=>'CASH','Cheque'=>'CHEQUE','POS'=>'POS','Online'=>'ONLINE','UPI'=>'UPI','RTGS/NEFT'=>'RTGS/NEFT'];
        }
        else{
            $res['paymentModes'] = ['Cash'=>'Cash','Cheque'=>'Cheque','DD'=>'DD','Online'=>'Online','NACH'=>'NACH','UPI'=>'UPI','Swipe1'=>'Swipe1','Swipe2'=>'Swipe2','Swipe3'=>'Swipe3','POS'=>'POS'];
        }
        $res['bank_data'] = bankmasterModel::get()->toArray();
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
            $sub_institute_id = $request->get('sub_institute_id');
            $syear = $request->get('syear');
            $user_id = $request->get('user_id');
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
            $recHtml = '<style>.fees-receipt{border:none !important;}</style>
                    <br><br><table class="fees-receipt" style="margin:0 auto;" width="80%">
                    <tbody>
      					<tr>
                        <td colspan="4" style="text-align:center !important;" align="center"> ';
        
            if ($receipt_book_arr->receipt_line_2 != '') {
                $recHtml .= '<span class="ma-hd">' . $receipt_book_arr->receipt_line_2 . '</span><br>';
            }
            if ($receipt_book_arr->receipt_line_3 != '') {
                $recHtml .= '<span class="rg-hd">' . $receipt_book_arr->receipt_line_3 . '</span><br>';
            }
            $recHtml .= '</td>';
            $recHtml .= '</tr>';

            $recHtml .= '<tr>
            <td colspan="4"><hr style="border-top: 5px solid black !important;"></td>
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
            $recHtml .= '       <tr>';
            $recHtml .= '               <td colspan="3" style="background:#ddd;"><b>PARTICULARS</b></td>';
            $recHtml .= '               <td style="white-space:nowrap;background:#ddd;"><b>AMOUNT (Rs.)</b></td>  ';
            $recHtml .= '           </tr>';
            $recHtml .= '           <tr>';
            $recHtml .= '               <td align="left" colspan="3">AMOUNT</td>';
            $recHtml .= '               <td align="right" >' . $request->amount . '</td>';
            $recHtml .= '           </tr>';
            $recHtml .= '           <tr>';
            $recHtml .= '               <td align="left" colspan="3"><b>Total</b></td>';
            $recHtml .= '               <td align="right" ><b>' . $request->amount . '</b></td>';
            $recHtml .= '           </tr>';
            $recHtml .= '       </table>';
            $recHtml .= '   </td>';
            $recHtml .= '</tr>';

            $total_amount_in_words = ucwords($this->convert_number_to_words($request->amountl));
            if ($total_amount_in_words != "") {
                $total_amount_in_words_str = "Rupees " . $total_amount_in_words . " Only";
            } else {
                $total_amount_in_words_str = "";
            }

            $recHtml .= '<tr>';
            $recHtml .= '   <td colspan="4" style="text-align:left !important;">';
            $recHtml .= '       <label><b>In Words : </b></label>';
            $recHtml .= '       <span>' . $total_amount_in_words_str . '</span>';
            $recHtml .= '   </td>';
            $recHtml .= '</tr>';

            $recHtml .= '<tr>';
            $recHtml .= '   <td colspan="4" class="padding" style="text-align:left !important;"><p><label><b>Payment By : </b></label>    <span><u>';
            $recHtml .= '       <label><b>Payment By : </b></label>';
            if ($request->payment_mode == 'Cash') {
                $recHtml .= '       <span><u>' . strtoupper($request->payment_mode) . '</u></span>';
            } else {
                $recHtml .= '       <span><u>' . strtoupper($request->payment_mode) . ' - ' . strtoupper($request->bank_name) . ' - ' . $request->cheque_no . '</u></span>';
            }
            $recHtml .= '   </td>';
            $recHtml .= '</tr>';

             $recHtml .= ' <tr>
            <td colspan="3" class="padding" style="font-size:12px !important;"><span>Has been Thanksfully Received by Shri Swaminarayan Mission
            <br><br>
            Income Tax Exemtion U/S 80G(5) No.AAAA53328NF20218/Tech/80G(5)/(05/1)
                2008-09.<br>Dt.31-05-2021 Valid from 01/04/2021 to 31/03/2025 to and onwards</span></td>
            <td class="padding"> <label><b>SIGNATURE</b></label><br><label><b>'.session()->get('name').'</b></label> </td>
            </tr>';

            $recHtml .= '</table>';
            // insert values into table
            $inertArr = array(
                'donar_id'=>$request->donar_id,
                'paid_date'=>Carbon::createFromFormat('d-m-Y', $request->paid_date)->format('Y-m-d'),
                'donation_amount'=>$request->amount,
                'payment_mode'=>$request->payment_mode,
                'cheque_number'=>$request->cheque_no ?? null,
                'cheque_date'=>$request->cheque_date ?? null,
                'bank_name'=>$request->bank_name ?? null,
                'bank_branch'=>$request->bank_branch ?? null,
                'remarks'=>$request->remarks ?? null,
                'reciept_no'=>$RECEIPT_NO,
                'reciept_html'=>$recHtml,
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
    public function destroy($id)
    {
        //
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
