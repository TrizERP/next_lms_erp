<?php

namespace App\Http\Controllers\student;

use App\Http\Controllers\Controller;
use GenTux\Jwt\GetsJwtToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use function App\Helpers\getStudents;
use function App\Helpers\is_mobile;
use function App\Helpers\SearchStudent;

class studentCertificateController extends Controller
{
    use GetsJwtToken;

    public function index(Request $request)
    {
        $type = $request->input('type');

        $res['status_code'] = "1";
        $res['message'] = "Success";

        return is_mobile($type, "student/student_certificate/show_student", $res, "view");
    }

    public function showStudent(Request $request)
    {
        $type = $request->input('type');
        $grade = $request->input('grade');
        $standard = $request->input('standard');
        $division = $request->input('division');

        $studentData = SearchStudent($grade, $standard, $division);
        if (! isset($studentData[0]['enrollment_no'])) {
            $res['status_code'] = 0;
            $res['message'] = "No student found please check your search panel";

            return is_mobile($type, "student_certificate.index", $res);
        }

        $res['status_code'] = 1;
        $res['message'] = "Success";
        $res['data'] = $studentData;
        $res['grade_id'] = $grade;
        $res['standard_id'] = $standard;
        $res['division_id'] = $division;

        return is_mobile($type, "student/student_certificate/show_student", $res, "view");
    }

    public function showStudentCertificate(Request $request)
    {
        $type = $request->input('type');
        $template = $request->input('template');
        $certificate_reason = $request->input('certificate_reason');
        $student_ids = $request->input('students');
        $syear = $request->session()->get('syear');
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $grade_id = $request->input('grade_id');
        $standard_id = $request->input('standard_id');

        $data = getStudents($student_ids);

        // START Dynamic Template Logic
        $tData = DB::table('template_master')
            ->where('module_name', $template)
            ->whereRaw('sub_institute_id = IFNULL(
                (SELECT sub_institute_id FROM template_master WHERE module_name ="'.$template.'" AND 
                    sub_institute_id = "'.session()->get('sub_institute_id').'"
                ),0)')->get()->toArray();
        $tData = json_decode(json_encode($tData), true);


        $result = DB::table('fees_receipt_book_master')
            ->selectRaw('*,GROUP_CONCAT(fees_head_id) heads')
            ->where('syear', $syear)
            ->where('sub_institute_id', $sub_institute_id)
            ->groupByRaw('receipt_line_1,receipt_line_2,receipt_line_3,receipt_line_4,receipt_prefix,receipt_logo,last_receipt_number')
            ->orderBy('sort_order')->limit(1)->get()->toArray();

        $receipt_book_arr = array();
        foreach ($result as $temp_id => $receipt_detail) {
            $receipt_book_arr = $receipt_detail;
        }

        $new_html = '';
        $insert_ids = '';
        $i = 0;
        foreach ($data as $key => $value) {

            $certificate_no_result = DB::table('certificate_history as c')
                ->selectRaw('(IFNULL(MAX(cast(c.certificate_number AS UNSIGNED)),0) + 1) AS certificate_no')
                ->where('c.sub_institute_id', $sub_institute_id)
                ->where('certificate_type', $template)
                ->where('syear', $syear)->get()->toArray();
            $certificate_no = $certificate_no_result[0]->certificate_no;
            $certificate_no1 = $certificate_no + $i;
            $i++;
            if(!isset($tData[0]['html_content']) || empty($tData[0]['html_content']) || $tData[0]['html_content']==null){
                $res['status_code'] = 0;
                $res['message'] = "Please Set Template For ".$request->template;
                return is_mobile($type, "student/student_certificate/show_student", $res, "view");                
            }
            else{
            $html_content = $tData[0]['html_content'];                
            }
            $new_html_content = $this->create_html_content($syear, $sub_institute_id, $html_content, $value,
                $receipt_book_arr, $template, $certificate_no1);

            if ($template == 'Transfer Certificate') {
                $new_html .= '<div class="row" style="margin-right: 2% !important;margin-left: 2% !important;">'.$new_html_content.'</div>
                              <div class="pagebreak"></div>';
            }
            else if($template == 'Student Fees Certificate'){
                $new_html .= '<div class="row" style="margin-right: 2% !important;margin-left: 2% !important;">'.$new_html_content.'</div>
                <div class="pagebreak"></div>';
            } else {
                $new_html .= '<div class="row" style="margin-right: 2% !important;margin-left: 2% !important;">'.$new_html_content.'</div>
                              <div class="pagebreak"></div>'; //margin-left: 5% !important;
            }

            $insert_ids .= $value['id'].',';
        }
        // END Dynamic Template Logic

        $insert_ids = rtrim($insert_ids, ',');

        $res['status_code'] = 1;
        $res['message'] = "Success";
        $res['data'] = $data;
        $res['template'] = $template;
        $res['str'] = $new_html;
        $res['insert_ids'] = $insert_ids;
        if ($certificate_reason != '') {
            $res['certificate_reason'] = $certificate_reason;
        }

        return is_mobile($type, "student/student_certificate/show_student_certificate", $res, "view");
    }

    public function studentCertificateAPI(Request $request)
    {
        try {
            if (! $this->jwtToken()->validate()) {
                $response = ['status' => '2', 'message' => 'Token Auth Failed', 'data' => []];

                return response()->json($response, 401);
            }
        } catch (\Exception $e) {
            $response = ['status' => '2', 'message' => $e->getMessage(), 'data' => []];

            return response()->json($response, 401);
        }

        $student_id = $request->input("student_id");
        $sub_institute_id = $request->input("sub_institute_id");
        $syear = $request->input("syear");

        if ($student_id != "" && $sub_institute_id != "" && $syear != "") {
            $data = DB::table('certificate_history')
                ->where('syear', $syear)
                ->where('sub_institute_id', $sub_institute_id)
                ->where('student_id', $student_id)->get()->toArray();

            $res['status_code'] = 1;
            $res['message'] = "Success";
            $res['data'] = $data;
        } else {
            $res['status_code'] = 0;
            $res['message'] = "Parameter Missing";
        }

        return json_encode($res);
    }

    public function create_html_content($syear,$sub_institute_id,$html_content,$value,$receipt_book_arr,$template,$certificate_no) {
        $display_year = $syear."-".($syear + 1);

        $image_path1 = "http://".$_SERVER['HTTP_HOST']."/storage/fees/".$receipt_book_arr->receipt_logo;
        $image_path = '<img src="'.$image_path1.'" alt="SCHOOL LOGO" style="width: 100px !important;height: 100px !important;">';

        $student_image_path1 = "http://".$_SERVER['HTTP_HOST']."/storage/student/".$value['image'];
        $student_image_path = '<img class="logo" src="'.$student_image_path1.'" alt="Student Logo" >';

        $html_content = str_replace(htmlspecialchars("<<receipt_logo>>"), $image_path, $html_content);
        if ($receipt_book_arr->receipt_line_1 != '') {
            $html_content = str_replace(htmlspecialchars("<<receipt_line_1>>"), $receipt_book_arr->receipt_line_1,
                $html_content);
        }
        if ($receipt_book_arr->receipt_line_2 != '') {
            $html_content = str_replace(htmlspecialchars("<<receipt_line_2>>"), $receipt_book_arr->receipt_line_2,
                $html_content);
        }
        if ($receipt_book_arr->receipt_line_3 != '') {
            $html_content = str_replace(htmlspecialchars("<<receipt_line_3>>"), $receipt_book_arr->receipt_line_3,
                $html_content);
        }
        if ($receipt_book_arr->receipt_line_4 != '') {
            $html_content = str_replace(htmlspecialchars("<<receipt_line_4>>"), $receipt_book_arr->receipt_line_4,
                $html_content);
        }

        $number_controller = new numberWordsController;
        $date_in_word = ucwords($number_controller->toWords(date('dS', strtotime($value['dob'])))." of ".
            date('F', strtotime($value['dob']))." ".
            $this->convert_number_to_words(date('Y', strtotime($value['dob']))));

        $his_her = '';
        if ($value['gender'] == 'male') {
            $his_her = 'His';
        } elseif ($value['gender'] == 'female') {
            $his_her = 'Her';
        }
        $he_she = '';

        if ($value['gender'] == 'male') {
            $he_she = 'he';
        } elseif ($value['gender'] == 'female') {
            $he_she = 'she';
        }

        //Start Bonafide certificate Tags
        $html_content = str_replace(htmlspecialchars("<<student_image_value>>"), $student_image_path, $html_content);
        $html_content = str_replace(htmlspecialchars("<<student_name_value>>"), strtoupper($value['student_full_name']),
            $html_content);
        $html_content = str_replace(htmlspecialchars("<<student_enrollment_value>>"), $value['enrollment_no'],
            $html_content);
        $html_content = str_replace(htmlspecialchars("<<student_standard_value>>"), $value['standard_name'],
            $html_content);
        $html_content = str_replace(htmlspecialchars("<<student_division_value>>"), $value['division_name'],
            $html_content);
        $html_content = str_replace(htmlspecialchars("<<student_year_value>>"), $display_year, $html_content);
        $html_content = str_replace(htmlspecialchars("<<student_mobile_value>>"), $value['mobile'], $html_content);
        $html_content = str_replace(htmlspecialchars("<<student_dob_value>>"), date('d-m-Y', strtotime($value['dob'])),
            $html_content);
        $html_content = str_replace(htmlspecialchars("<<current_date>>"), date('d-M-Y'), $html_content);
        $html_content = str_replace(htmlspecialchars("<<student_dob_word_value>>"), $date_in_word, $html_content);
        $html_content = str_replace(htmlspecialchars("<<student_dise_uid_value>>"), $value['dise_uid'], $html_content);
        $html_content = str_replace(htmlspecialchars("<<certificate_no>>"), $certificate_no, $html_content);
        $html_content = str_replace(htmlspecialchars("<<his_her_value>>"), $his_her, $html_content);
        $html_content = str_replace(htmlspecialchars("<<he_she_value>>"), $he_she, $html_content);
        //END Bonafide certificate Tags

        //Start Transfer certificate Tags
        $html_content = str_replace(htmlspecialchars("<<affiliation_no_value>>"), strtoupper($value['affiliation_no']),
            $html_content);
        $html_content = str_replace(htmlspecialchars("<<school_code_value>>"), strtoupper($value['school_code']),
            $html_content);
        $html_content = str_replace(htmlspecialchars("<<nationality_value>>"), strtoupper($value['nationality']),
            $html_content);
        $html_content = str_replace(htmlspecialchars("<<place_of_birth_value>>"), strtoupper($value['place_of_birth']),
            $html_content);
        $html_content = str_replace(htmlspecialchars("<<father_name_value>>"), strtoupper($value['father_name']),
            $html_content);
        $html_content = str_replace(htmlspecialchars("<<mother_name_value>>"), strtoupper($value['mother_name']),
            $html_content);
        $html_content = str_replace(htmlspecialchars("<<religion_name_value>>"), strtoupper($value['religion_name']),
            $html_content);
        $html_content = str_replace(htmlspecialchars("<<caste_name_value>>"), strtoupper($value['caste_name']),
            $html_content);
        $html_content = str_replace(htmlspecialchars("<<subcast_value>>"), strtoupper($value['subcast']),
            $html_content);
        $html_content = str_replace(htmlspecialchars("<<candidate_belongs_to_value>>"),
            strtoupper($value['candidate_belongs_to']), $html_content);
        $html_content = str_replace(htmlspecialchars("<<date_of_first_admission_value>>"),
            strtoupper($value['date_of_first_admission']), $html_content);
        $html_content = str_replace(htmlspecialchars("<<class_in_which_pupil_last_studied_value>>"),
            strtoupper($value['class_in_which_pupil_last_studied']), $html_content);
        $html_content = str_replace(htmlspecialchars("<<last_school_board_value>>"),
            strtoupper($value['last_school_board']), $html_content);
        $html_content = str_replace(htmlspecialchars("<<whether_failed_value>>"), strtoupper($value['whether_failed']),
            $html_content);
        $html_content = str_replace(htmlspecialchars("<<subjects_studied_value>>"),
            strtoupper($value['subjects_studied']), $html_content);
        $html_content = str_replace(htmlspecialchars("<<whether_qualified_value>>"),
            strtoupper($value['whether_qualified']), $html_content);
        $html_content = str_replace(htmlspecialchars("<<if_to_which_class_value>>"),
            strtoupper($value['if_to_which_class']), $html_content);
        $html_content = str_replace(htmlspecialchars("<<month_up_paid_school_dues_value>>"),
            strtoupper($value['month_up_paid_school_dues']), $html_content);
        $html_content = str_replace(htmlspecialchars("<<admission_under_value>>"),
            strtoupper($value['admission_under']), $html_content);
        $html_content = str_replace(htmlspecialchars("<<total_working_days_value>>"),
            strtoupper($value['total_working_days']), $html_content);
        $html_content = str_replace(htmlspecialchars("<<total_working_days_present_value>>"),
            strtoupper($value['total_working_days_present']), $html_content);
        $html_content = str_replace(htmlspecialchars("<<games_played_value>>"), strtoupper($value['games_played']),
            $html_content);
        $html_content = str_replace(htmlspecialchars("<<general_conduct_value>>"),
            strtoupper($value['general_conduct']), $html_content);
        $html_content = str_replace(htmlspecialchars("<<date_of_application_for_certificate_value>>"),
            date('d-m-Y', strtotime($value['date_of_application_for_certificate'])), $html_content);
        $html_content = str_replace(htmlspecialchars("<<date_of_issue_of_certificate_value>>"),
            date('d-m-Y', strtotime($value['date_of_issue_of_certificate'])), $html_content);
        $html_content = str_replace(htmlspecialchars("<<reason_leaving_school_value>>"),
            strtoupper($value['reason_leaving_school']), $html_content);

        $html_content = str_replace(htmlspecialchars("<<proof_for_dob_value>>"), strtoupper($value['proof_for_dob']),
            $html_content);
        $html_content = str_replace(htmlspecialchars("<<whether_school_is_under_goverment_value>>"),
            strtoupper($value['whether_school_is_under_goverment']), $html_content);
        $html_content = str_replace(htmlspecialchars("<<date_on_which_pupil_name_was_struck_value>>"),
            date('d-m-Y', strtotime($value['date_on_which_pupil_name_was_struck'])), $html_content);
        $html_content = str_replace(htmlspecialchars("<<any_fees_concession_value>>"),
            strtoupper($value['any_fees_concession']), $html_content);
        $html_content = str_replace(htmlspecialchars("<<whether_ncc_cadet_value>>"),
            strtoupper($value['whether_ncc_cadet']), $html_content);
        $html_content = str_replace(htmlspecialchars("<<any_other_remarks_value>>"),
            strtoupper($value['any_other_remarks']), $html_content);
        $html_content = str_replace(htmlspecialchars("<<student_uniqueid_value>>"), strtoupper($value['unique_id']),
            $html_content);
       
        // student fees cetificate

        $html_content = str_replace(htmlspecialchars("<<student_father_name>>"),  strtoupper($value['father_name']) ,$html_content);
        
    $fees_data = DB::table('fees_collect')
    ->where(['sub_institute_id' => $sub_institute_id, 'syear' => $syear, 'student_id' => $value['id']])
    ->get();


    $fees_heads = DB::table('fees_title')
    ->where(['sub_institute_id' => $sub_institute_id, 'mandatory' => 1,'syear'=>$syear])->orderBy('sort_order','ASC')
    ->get();

    $fees_month = DB::table('fees_collect')
    ->where(['sub_institute_id' => $sub_institute_id, 'syear' => $syear, 'student_id' => $value['id']])
    ->groupBy('term_id')->get();
    
        $totalAmount = 0; 
        $months = [
            1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr', 5 => 'May', 6 => 'Jun', 7 => 'Jul', 8 => 'Aug', 9 => 'Sep',
            10 => 'Oct', 11 => 'Nov', 12 => 'Dec',
        ];
        $fees_details = "<h4 style='font-weigth:bold'>Apr-".$syear." To Mar-".($syear+1)."</h4>
        <div class='table-responsive mb-2' style='display:inline-table;margin-top:10px;width:60%'>
            <table class='table table-striped'>";
        foreach ($fees_heads as $title) {
            $fees_details .= "<tr><td style='font-weight:600'>" . $title->display_name . "</td>";
            $termIds = [];
            $month_name=[];
            foreach ($fees_month as $fees) {
                if (isset($fees->{$title->fees_title})) {
                    $termIds[] = $fees->term_id;
                }
            }

        $month_name = [];

        foreach ($termIds as $arr) {
            $y = $arr / 10000;
            $month = (int)$y;
            $year = substr($arr, -4);
            $month_name[$year . '-' . str_pad($month, 2, '0', STR_PAD_LEFT)] = $months[$month] . '-' . $year;
        }

        if (!empty($month_name)) {
            uksort($month_name, function ($a, $b) {
                return strtotime($a . '-01') <=> strtotime($b . '-01');
            });

        }     
            $fees_details .= "<td>" . $fees_data->sum($title->fees_title) ?? 0 . "  ";
            $fees_details.="/- ";

            if (!empty($month_name)) {
                $firstMonth = reset($month_name);
                $lastMonth = end($month_name);
                $print_month =["Tuition Fees","Computer Fees","Security Charges","Smart Class"];
                if(in_array($title->display_name,$print_month)){        
                $fees_details .= "(".$firstMonth;
                    if ($firstMonth !== $lastMonth) {
                        $fees_details .= " To " . $lastMonth.")";
                    }else{
                        $fees_details.= ")";
                    }
                }
            
            }
            
            $fees_details .= "</td></tr>";
            
            $totalAmount += $fees_data->sum($title->fees_title); 
        }
        $fees_details .="<tr>
        <td>Total</td>
        <td>".$totalAmount."/- </td>
        </tr>";
        $fees_details .= "
            </table></div>
        ";
        // Output the total amount
        // $fees_details .= "<p>Total Amount: $totalAmount</p>";
        $html_content = str_replace(htmlspecialchars("<<fees_details>>"), $fees_details ,$html_content);
        $html_content = str_replace(htmlspecialchars("<<total_amount_in_words>>"), $this->convert_number_to_words($totalAmount) ,$html_content);
        return $html_content;
    }

    public function ajax_saveData(Request $request)
    {
        $student_id = $request->input('insert_student_ids');
        $syear = $request->session()->get('syear');
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $template = $request->input('template');
        $student_ids = explode(',', $student_id);

        $data = getStudents($student_ids);

        $tData = DB::table('template_master')
            ->where('module_name', $template)
            ->whereRaw('sub_institute_id = IFNULL(
                (SELECT sub_institute_id FROM template_master WHERE module_name ="'.$template.'" AND 
                    sub_institute_id = "'.session()->get('sub_institute_id').'"
                ),0)')->get()->toArray();
        $tData = json_decode(json_encode($tData), true);


        $result = DB::table('fees_receipt_book_master')
            ->selectRaw('*,GROUP_CONCAT(fees_head_id) heads')
            ->where('syear', $syear)
            ->where('sub_institute_id', $sub_institute_id)
            ->groupByRaw('receipt_line_1,receipt_line_2,receipt_line_3,receipt_line_4,receipt_prefix,receipt_logo,last_receipt_number')
            ->orderBy('sort_order')->limit(1)->get()->toArray();

        $receipt_book_arr = array();
        foreach ($result as $temp_id => $receipt_detail) {
            $receipt_book_arr = $receipt_detail;
        }

        $last_insert_ids = '';
        foreach ($data as $key => $value) {
            $certificate_no_result = DB::table('certificate_history as c')
                ->selectRaw('(IFNULL(MAX(cast(c.certificate_number AS UNSIGNED)),0) + 1) AS certificate_no')
                ->where('c.sub_institute_id', $sub_institute_id)
                ->where('certificate_type', $template)
                ->where('syear', $syear)->get()->toArray();

            $certificate_no = $certificate_no_result[0]->certificate_no;

            $html_content = $tData[0]['html_content'];
            $new_html_content = $this->create_html_content($syear, $sub_institute_id, $html_content, $value,
                $receipt_book_arr, $template, $certificate_no);
            DB::table('certificate_history')->insert([
                'syear'              => $syear,
                'student_id'         => $value['id'],
                'certificate_type'   => $template,
                'sub_institute_id'   => $sub_institute_id,
                'certificate_number' => $certificate_no,
                'certificate_html'   => $new_html_content,
                'created_at'         => now(),
                'updated_at'         => now(),
            ]);
            $last_inserted_id = DB::getPdo()->lastInsertId();
            $last_insert_ids .= $last_inserted_id.',';
        }

        return rtrim($last_insert_ids, ',');
    }

    public function convert_number_to_words($number)
    {
        $hyphen = '-';
        $conjunction = ' and ';
        $separator = ', ';
        $negative = 'negative ';
        $decimal = ' point ';
        $dictionary = [
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
        ];

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
            $words = [];
            foreach (str_split((string) $fraction) as $number) {
                $words[] = $dictionary[$number];
            }
            $string .= implode(' ', $words);
        }

        return $string;
    }

}
