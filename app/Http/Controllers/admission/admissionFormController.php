<?php

namespace App\Http\Controllers\admission;

use App\Http\Controllers\Controller;
use App\Models\admission\admissionEnquiryModel;
use App\Models\admission\admissionFormModel;
use App\Models\school_setup\standardModel;
use App\Models\settings\tblcustomfieldsModel;
use App\Models\settings\tblfields_dataModel;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use function App\Helpers\is_mobile;
use GenTux\Jwt\GetsJwtToken;

class admissionFormController extends Controller
{
    use GetsJwtToken;
    
    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index(Request $request)
    {
        $type = $request->input('type');
        $sub_institute_id = $request->session()->get("sub_institute_id");
        $syear = session()->get("syear");
        $marking_period_id = session()->get('term_id');
        if($type=="API"){
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
        }

        $data = DB::table('admission_enquiry as ae')
            ->leftJoin('admission_form as af', function ($join) {
                $join->whereRaw('ae.id = af.enquiry_id');
            })->leftJoin('tblstudent as ts', function ($join) {
                $join->whereRaw('ts.admission_id = ae.id AND ts.admission_year = ae.syear AND ts.sub_institute_id = ae.sub_institute_id');
            })->leftJoin('standard as s', function ($join) use ($sub_institute_id,$marking_period_id) {
                $join->whereRaw("s.id = ae.admission_standard AND s.sub_institute_id = '".$sub_institute_id."'");
                // ->when($marking_period_id,function($query) use ($marking_period_id){
                //     $query->where('tblstudent.marking_period_id',$marking_period_id);
                // });
            })
            ->selectRaw('ae.*,COUNT(ts.id) AS total_student_count,ae.remarks AS enquiry_remark,s.name AS std_name,af.form_no,
                af.admission_docket_no,af.registration_no,af.id as form_id,af.admission_form_fee,af.receipt_id,af.receipt_html')
            ->where('ae.sub_institute_id', $sub_institute_id)
            ->where('ae.syear', $syear)->groupBy('ae.id')->get()->toArray();

        $data = array_map(function ($value) {
            return (array) $value;
        }, $data);

        $res['status_code'] = 1;
        $res['message'] = "Success";
        $res['data'] = $data;

        return is_mobile($type, 'admission/form/show_admission_form', $res, 'view');
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return Response
     */
    public function edit(Request $request, $id)
    {
        $type = $request->input('type');
        $sub_institute_id = $request->session()->get("sub_institute_id");
        $syear = $request->session()->get("syear");
        $marking_period_id = session()->get('term_id');

        if($type=="API"){
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
        }

        if ($sub_institute_id == 46) //for Mountlitera Zee School
        {
            $extra_fileds = ",ae.counciler_name";
        } else {
            $extra_fileds = ",af.counciler_name";
        }

        if ($sub_institute_id == 198)//for MAHESHWARI school
        {
            $data = DB::table('admission_enquiry as ae')
                ->leftJoin('admission_form as af', function ($join) {
                    $join->whereRaw('ae.id = af.enquiry_id');
                })
                ->selectRaw("*,ae.id as id,ae.enquiry_no,CONCAT_WS(',',ae.house_no,ae.`building_name_appratment_name_society_name`,
                ae.district_name,ae.pin_code,ae.state) AS address,ae.father_occupation,ae.mother_occupation,ae.annual_income,af.form_no")
                ->where('ae.id', $id)->get()->toArray();
        } else {
            $data = DB::table('admission_enquiry as ae')
                ->leftJoin('admission_form as af', function ($join) {
                    $join->whereRaw('ae.id = af.enquiry_id');
                })
                ->selectRaw("*,ae.id as id,ae.enquiry_no,ae.admission_standard,af.form_no ".$extra_fileds)
                ->where('ae.id', $id)->get()->toArray();
        }

        $data = array_map(function ($value) {
            return (array) $value;
        }, $data);

        $editData = $data;

        $selected_standard = DB::table('standard as s')
            ->join('academic_section as a', function ($join) use($marking_period_id) {
                $join->whereRaw('a.id = s.grade_id AND a.sub_institute_id = s.sub_institute_id');
                // ->when($marking_period_id,function($join) use ($marking_period_id){
                //     $query->where('tblstudent.marking_period_id',$marking_period_id);
                // });
            })
            ->selectRaw("s.id,s.grade_id,s.sub_institute_id,s.name AS std_name,s.short_name AS std_sort_name,
                a.title AS grade,a.short_name AS grade_short_name")
            ->where('s.id', $editData[0]['admission_standard'])
            ->where('s.sub_institute_id', $sub_institute_id)
            ->get()->toArray();
        $selected_standard = json_decode(json_encode($selected_standard), true);

        if (isset($editData[0]['form_no']) && $editData[0]['form_no'] != '') {
            $FORM_NO = $editData[0]['form_no'];
        } else {
            $FORM_NO = $this->get_form_no($sub_institute_id, $syear, $selected_standard[0]['grade']);
        }

        $standard = standardModel::where(['sub_institute_id' => $sub_institute_id])
        // ->when($marking_period_id,function($query) use ($marking_period_id){
        //     $query->where('marking_period_id',$marking_period_id);
        // })
        ->get()->toArray();

        $dataCustomFields = tblcustomfieldsModel::where(['status' => "1", 'table_name' => "admission_form"])
            ->whereRaw('(sub_institute_id = '.$sub_institute_id.' OR common_to_all = 1)')
            ->get();

        $fieldsData = tblfields_dataModel::get()->toArray();
        $i = 0;
        $finalfieldsData = array();
        foreach ($fieldsData as $key => $value) {
            $finalfieldsData[$value['field_id']][$i]['display_text'] = $value['display_text'];
            $finalfieldsData[$value['field_id']][$i]['display_value'] = $value['display_value'];
            $i++;
        }
        $res['status_code'] = "1";
        $res['message'] = "Successfully";
        $res['editData'] = $editData['0'];
        $res['form_no'] = $FORM_NO;
        $res['standard'] = $standard;
        $res['custom_fields'] = $dataCustomFields;
        if (count($finalfieldsData) > 0) {
            $res['data_fields'] = $finalfieldsData;
        }

        return is_mobile($type, 'admission/form/edit_admission_form', $res, 'view');
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
        $type = $request->input("type");
        $sub_institute_id = $request->session()->get("sub_institute_id");
        $user_id = $request->session()->get("user_id");
        $marking_period_id = session()->get('term_id');

         if($type=="API"){
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
        }
        $editdata['first_name'] = $request->input("first_name");
        $editdata['middle_name'] = $request->input("middle_name");
        $editdata['last_name'] = $request->input("last_name");
        $editdata['mobile'] = $request->input("mobile");
        $editdata['email'] = $request->input("email");
        $editdata['date_of_birth'] = $request->input("date_of_birth");
        $editdata['age'] = $request->input("age");
        $editdata['address'] = $request->input("address");
        $editdata['previous_school_name'] = $request->input("previous_school_name");
        // $editdata['previous_standard'] = $request->input("previous_standard");
        $editdata['source_of_enquiry'] = $request->input("source_of_enquiry");
        // $editdata['remarks'] = $request->input("remarks");
        // $editdata['followup_date'] = $request->input("followup_date");

        admissionEnquiryModel::where(['id' => $id, 'sub_institute_id' => $sub_institute_id])->update($editdata);

        $data = $request->except([
            '_method', '_token','token','user_id', 'submit', 'type', 'first_name', 'middle_name', 'last_name', 'mobile', 'email','syear',
            'date_of_birth', 'age', 'address', 'previous_school_name', 'previous_standard', 'source_of_enquiry',
        ]); //,'remarks','followup_date'

        $checkForm = admissionFormModel::where(['enquiry_id' => $id])->get()->toArray();

        if ($sub_institute_id != 74 && $sub_institute_id != 181)//for LancerArmy school & Millennium Surat
        {
            if (count($checkForm) > 0) {
                $data['enquiry_id'] = $id;
                $data['created_by'] = $user_id;
                $data['created_on'] = date('Y-m-d H:i:s');
                $data['sub_institute_id'] = $sub_institute_id;

                admissionFormModel::where(['enquiry_id' => $id])->update($data);
            } else {
                $data['enquiry_id'] = $id;
                $data['created_by'] = $user_id;
                $data['created_on'] = date('Y-m-d H:i:s');
                $data['sub_institute_id'] = $sub_institute_id;

                admissionFormModel::insert($data);
            }

            $res['status_code'] = "1";
            $res['message'] = "Added successfully";

            return is_mobile($type, "admission_registration.index", $res);
        } else {
            $all_data = admissionEnquiryModel::where([
                'id' => $id, 'sub_institute_id' => $sub_institute_id,
            ])->get()->toArray();

            $standard = standardModel::where([
                'id' => $all_data[0]['admission_standard'], 'sub_institute_id' => $sub_institute_id,
            ])
            // ->when($marking_period_id,function($query) use ($marking_period_id){
            //     $query->where('marking_period_id',$marking_period_id);
            // })
            ->get()->toArray();
            $standard_name = $standard[0]['name'];

            $style = '<style type="text/css">
	        	body {
	        		background: #ffffff;
	    		} 
			    table.fees-receipt {
	                border-collapse: collapse !important;
	            }
	            .fees-receipt {
	                border: 1px solid #888;
	                height: 510px;
	                overflow: hidden
	            }
	            .particulars {
	                border-collapse: collapse !important;
	            }
	            .particulars td {
	                border: 1px solid #888;
	                border-collapse: inherit !important;
	            }
	            .fees-receipt td {
	                font-family: Arial, Helvetica, sans-serif !important;
	                padding: 6px 8px;
	                font-size: 13px
	            }
	            .fees-receipt img.logo {
	                width: 100px;
	                height: 90px;
	                margin: 0
	            }
	            .double-border {
	                border-bottom: 1px double #000;
	            }
	            .particulars {        
	                overflow: hidden;
	                display: block;
	                vertical-align: top
	            }
	            .particulars td {
	                width: 100%;
	                height: 20px;
	                font-size: 12px
	            }
	            .mg-top {
	                top: 10px;
	                position: relative
	            }
	            .mg-top label {
	                border-radius: 3px;
	                font-weight: 700;
	                font-size: 14px;
	                top: 5px;
	                position: relative
	            }
	            .receipt-hd {
	                border: 1px solid #000;
	                padding: 5px 15px;
	                margin-top: 15px
	            }
	            .sc-hd {
	                font-size: 26px;
	                font-weight: 700;
	                font-family: Arial, Helvetica, sans-serif !important
	            }
	            .ma-hd {
	                font-size: 18px;
	                font-weight: 700;
	                font-family: Arial, Helvetica, sans-serif !important
	            }
	            .rg-hd {
	                font-size: 14px;
	                font-weight: 600;
	                font-family: Arial, Helvetica, sans-serif !important
	            }
	            .padding {
	                padding-bottom: 20px !important
	            }
	            .logo-width {
	                width: 165px;
	                text-align: center
	            }
	            br {
	                display: block;
	            }
			</style>';

            $result = DB::table('fees_receipt_book_master')
                ->selectRaw('*,GROUP_CONCAT(fees_head_id) heads')
                ->where('syear', $syear)
                ->where('sub_institute_id', $sub_institute_id)
                ->groupByRaw('receipt_line_1,receipt_line_2,receipt_line_3,receipt_line_4,receipt_prefix,receipt_logo,last_receipt_number')
                ->get()->toArray();

            $RECEIPT_NO_result = DB::table('admission_form')
                ->selectRaw('(IFNULL(MAX(CAST(receipt_id AS UNSIGNED)),1) + 1) as rid')
                ->where('SUB_INSTITUTE_ID', $sub_institute_id)
                ->get()->toArray();
            $RECEIPT_NO = $RECEIPT_NO_result[0]->rid;

            $receipt_book_arr = array();
            foreach ($result as $temp_id => $receipt_detail) {
                $receipt_book_arr = $receipt_detail;
            }

            $image_path = "/storage/fees/".$receipt_book_arr->receipt_logo;
            $recHtml = '
		            <table class="fees-receipt" border-collapse="collapse" style="margin:0 auto;" width="100%">
		            <tbody>
		                <tr class="double-border">
		                    <td class="logo-width" align="left">
		        ';
            if ($sub_institute_id == 181)// For Millennium Surat
            {
                $recHtml .= '    <img class="logo" src="'.$image_path.'" alt="SCHOOL LOGO" style="width: 172px !important;height: 70px;margin: 0;">';
            } else {
                $recHtml .= '    <img class="logo" src="'.$image_path.'" alt="SCHOOL LOGO" style="width: auto !important;height: 90px;margin: 0;">';
            }
            $recHtml .= '</td>';
            $recHtml .= '<td colspan="3" style="text-align: center !important;">    ';
            if ($receipt_book_arr->receipt_line_1 != '') {
                if ($sub_institute_id == 181)// For Millennium Surat
                {
                    $recHtml .= '   <span class="ma-hd">'.$receipt_book_arr->receipt_line_1.'</span><br>';
                } else {
                    $recHtml .= '   <span class="sc-hd">'.$receipt_book_arr->receipt_line_1.'</span><br>';
                }
            }
            if ($receipt_book_arr->receipt_line_2 != '') {
                if ($sub_institute_id == 181)// For Millennium Surat
                {
                    $recHtml .= '   <span class="rg-hd">'.$receipt_book_arr->receipt_line_2.'</span><br>';
                } else {
                    $recHtml .= '   <span class="ma-hd">'.$receipt_book_arr->receipt_line_2.'</span><br>';
                }
            }
            if ($receipt_book_arr->receipt_line_3 != '') {
                $recHtml .= '   <span class="rg-hd">'.$receipt_book_arr->receipt_line_3.'</span><br>';
            }
            if ($receipt_book_arr->receipt_line_4 != '') {
                $recHtml .= '   <span class="rg-hd">'.$receipt_book_arr->receipt_line_4.'</span><br>';
            }
            $recHtml .= '</td>';
            $recHtml .= '</tr>';
            $recHtml .= '<tr>';
            $recHtml .= '<td class="mg-top" colspan="4" style="padding-bottom:20px;text-align: center !important;">';
            $recHtml .= '   <label class="receipt-hd">Admission Form Receipt</label>';
            $recHtml .= '</td>';
            $recHtml .= '</tr>';

            $syear1 = $syear;
            $syear2 = $syear1 + 1;
            $edu_year = "$syear1-$syear2";

            $recHtml .= '<tr>';
            $recHtml .= '   <td align="left" colspan="2" style="white-space:nowrap;">';
            $recHtml .= '       Receipt No. : <label><b>'.$RECEIPT_NO.'</b></label>';
            $recHtml .= '   </td>';
            $recHtml .= '   <td align="right" colspan="2">';
            $recHtml .= '       Edu Year/Session : <label><b>'.$edu_year.'</b></label>';
            $recHtml .= '   </td>';
            $recHtml .= '</tr>';

            $recHtml .= '<tr>';
            $recHtml .= '   <td align="left" colspan="2">';
            $recHtml .= '       Name : <label><b>'.$all_data[0]['first_name'].' '.$all_data[0]['last_name'].'</b></label>';
            $recHtml .= '   </td>';
            $recHtml .= '   <td align="right" colspan="2">';
            $recHtml .= '       Date : <label><b>'.date("d-m-Y").'</b></label>';
            $recHtml .= '   </td>';
            $recHtml .= '</tr>';

            $recHtml .= '<tr>';
            $recHtml .= '   <td align="left" colspan="2">';
            $recHtml .= '       Std : <label><b>'.$standard_name.'</b></label>';
            $recHtml .= '   </td>';
            $recHtml .= '   <td align="right" colspan="2">';
            $recHtml .= '       Mobile : <label><b>'.$all_data[0]['mobile'].'</b></label>';
            $recHtml .= '   </td>';
            $recHtml .= '</tr>';

            if ($sub_institute_id == 181) // FOr Millennium Surat
            {
                $recHtml .= '<tr>';
                $recHtml .= '   <td colspan="4" style="text-align:left !important;">';
                $recHtml .= '       Registration No. : <label><b>'.$data['registration_no'].'</b></label>';
                $recHtml .= '   </td>';
                $recHtml .= '</tr>';
            }


            $recTotal = $data['admission_form_fee'];

            $recHtml .= '<tr>';
            $recHtml .= '   <td colspan="4" valign="top">';
            $recHtml .= '       <table class="particulars" width="100%" border="0">';
            $recHtml .= '       <tr>';
            $recHtml .= '               <td colspan="3"><b>Description</b></td>';
            $recHtml .= '               <td style="white-space:nowrap;"><b>Received (Rs.)</b></td>  ';
            $recHtml .= '           </tr>';
            $recHtml .= '           <tr>';
            $recHtml .= '               <td align="left" colspan="3">Admission Form Fees</td>';
            $recHtml .= '               <td align="right" >'.$data['admission_form_fee'].'</td>';
            $recHtml .= '           </tr>';
            $recHtml .= '           <tr>';
            $recHtml .= '               <td align="left" colspan="3"><b>Total</b></td>';
            $recHtml .= '               <td align="right" ><b>'.$recTotal.'</b></td>';
            $recHtml .= '           </tr>';
            $recHtml .= '       </table>';
            $recHtml .= '   </td>';
            $recHtml .= '</tr>';

            $total_amount_in_words = ucwords($this->convert_number_to_words($recTotal));
            if ($total_amount_in_words != "") {
                $total_amount_in_words_str = "Rupees ".$total_amount_in_words." Only";
            } else {
                $total_amount_in_words_str = "";
            }

            $recHtml .= '<tr>';
            $recHtml .= '   <td colspan="4" style="text-align: left !important;">';
            $recHtml .= '       <label><b>In Words : </b></label>';
            $recHtml .= '       <span>'.$total_amount_in_words_str.'</span>';
            $recHtml .= '   </td>';
            $recHtml .= '</tr>';

            $payMethod = 'Cash';
            $REMARKS = "";
            $recHtml .= '<tr>';
            $recHtml .= '   <td colspan="4" class="padding" style="text-align: left !important;">';
            $recHtml .= '       <label><b>Payment By : </b></label>';
            if ($payMethod == '') {
                $recHtml .= '       <span><u>'.$payMethod.'</u></span>';
            } else {
                $recHtml .= '       <span><u>'.$payMethod.'</u></span>';
            }
            $recHtml .= '   </td>';
            $recHtml .= '</tr>';

            $FEES_NOTE = "THIS IS A COMPUTER GENERATED RECEIPT.";
            if ($sub_institute_id == 181) // FOr Millennium Surat
            {
                $recHtml .= '<tr>';
                $recHtml .= '   <td colspan="3"><b>'.$FEES_NOTE.'</b></td>';
                $recHtml .= '   <td class="logo-width" style="text-align:center !important;">
			    					<label>Signature</label>
			    				</td>';
                $recHtml .= '</tr>';
            } else {
                $recHtml .= '<tr>';
                $recHtml .= '   <td colspan="3"><b>'.$FEES_NOTE.'</b></td>';
                $recHtml .= '   <td class="logo-width"><label style="text-align:center;">'.session()->get('name').'<br>Signature</label></td>';
                $recHtml .= '</tr>';
            }

            $recHtml .= '</table><br>';

            $recHtml_for_insert = $recHtml;

            $data['receipt_id'] = $RECEIPT_NO;
            $data['receipt_html'] = $style.$recHtml_for_insert;

            if (count($checkForm) > 0) {
                $data['enquiry_id'] = $id;
                $data['created_by'] = $user_id;
                $data['created_on'] = date('Y-m-d H:i:s');
                $data['sub_institute_id'] = $sub_institute_id;

                admissionFormModel::where(['enquiry_id' => $id])->update($data);

            } else {
                $data['enquiry_id'] = $id;
                $data['created_by'] = $user_id;
                $data['created_on'] = date('Y-m-d H:i:s');
                $data['sub_institute_id'] = $sub_institute_id;

                admissionFormModel::insert($data);
            }

            if ($sub_institute_id == 181) // For Millennium Surat
            {
                $paper_size = 'A5DB';
            } else {
                $paper_size = 'A5';
            }

            $fees_config = DB::table('fees_receipt_css')->select('css')
                ->where('receipt_id', $paper_size)->get()->toArray();

            $res['status_code'] = "1";
            $res['data'] = $data['receipt_html'];
            $res['css'] = $fees_config[0]->css;
            $res['paper'] = $paper_size;
            $res['message'] = "Added successfully";

            return is_mobile($type, "admission/form/receipt_view", $res, "view");
        }

    }

    public function get_form_no($sub_institute_id, $syear, $grade_name)
    {

        $GET_ENQUIRY_No = admissionFormModel::select(DB::raw('MAX((CAST(IFNULL(form_no,0) AS INT))) as LAST_FORM_NO'))
            ->where(['sub_institute_id' => $sub_institute_id])->get()->toArray();
        $FORM_NO = $GET_ENQUIRY_No[0]['LAST_FORM_NO'] + 1;

        if (strstr($grade_name, 'PREP')) {
            $post_char = 'P';
        } elseif (strstr($grade_name, 'CBSE')) {
            $post_char = 'C';
        } elseif (strstr($grade_name, 'GSEB')) {
            $post_char = 'G';
        } else {
            $post_char = '';
        }

        return "0".$FORM_NO.$post_char;
    }

    public function convert_number_to_words($number)
    {
        $hyphen = '-';
        $conjunction = ' and ';
        $separator = ', ';
        $negative = 'negative ';
        $decimal = ' point ';
        $dictionary = array(
            0                   => 'zero',
            1                   => 'one',
            2                   => 'two',
            3                   => 'three',
            4                   => 'four',
            5                   => 'five',
            6                   => 'six',
            7                   => 'seven',
            8                   => 'eight',
            9                   => 'nine',
            10                  => 'ten',
            11                  => 'eleven',
            12                  => 'twelve',
            13                  => 'thirteen',
            14                  => 'fourteen',
            15                  => 'fifteen',
            16                  => 'sixteen',
            17                  => 'seventeen',
            18                  => 'eighteen',
            19                  => 'nineteen',
            20                  => 'twenty',
            30                  => 'thirty',
            40                  => 'fourty',
            50                  => 'fifty',
            60                  => 'sixty',
            70                  => 'seventy',
            80                  => 'eighty',
            90                  => 'ninety',
            100                 => 'hundred',
            1000                => 'thousand',
            1000000             => 'million',
            1000000000          => 'billion',
            1000000000000       => 'trillion',
            1000000000000000    => 'quadrillion',
            1000000000000000000 => 'quintillion',
        );

        if (! is_numeric($number)) {
            return false;
        }

        if (($number >= 0 && (int) $number < 0) || (int) $number < 0 - PHP_INT_MAX) {
            // overflow
            trigger_error(
                'convert_number_to_words only accepts numbers between -'.PHP_INT_MAX.' and '.PHP_INT_MAX,
                E_USER_WARNING
            );

            return false;
        }

        if ($number < 0) {
            return $negative.$this->convert_number_to_words(abs($number));
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
                $tens = ((int) ($number / 10)) * 10;
                $units = $number % 10;
                $string = $dictionary[$tens];
                if ($units) {
                    $string .= $hyphen.$dictionary[$units];
                }
                break;
            case $number < 1000:
                $hundreds = $number / 100;
                $remainder = $number % 100;
                $string = $dictionary[$hundreds].' '.$dictionary[100];
                if ($remainder) {
                    $string .= $conjunction.$this->convert_number_to_words($remainder);
                }
                break;
            default:
                $baseUnit = pow(1000, floor(log($number, 1000)));
                $numBaseUnits = (int) ($number / $baseUnit);
                $remainder = $number % $baseUnit;
                $string = $this->convert_number_to_words($numBaseUnits).' '.$dictionary[$baseUnit];
                if ($remainder) {
                    $string .= $remainder < 100 ? $conjunction : $separator;
                    $string .= $this->convert_number_to_words($remainder);
                }
                break;
        }

        if (null !== $fraction && is_numeric($fraction)) {
            $string .= $decimal;
            $words = array();
            foreach (str_split((string) $fraction) as $number) {
                $words[] = $dictionary[$number];
            }
            $string .= implode(' ', $words);
        }

        return $string;
    }
}
