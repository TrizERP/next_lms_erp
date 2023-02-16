<?php

namespace App\Http\Controllers\student;

use App\Http\Controllers\Controller;
use App\Models\school_setupModel;
use App\Models\school_setup\academic_sectionModel;
use App\Models\school_setup\standardModel;
use App\Models\school_setup\std_div_mappingModel;
use App\Models\student\tblstudentPastEducationModel;
use App\Models\student\tblstudentFamilyHistoryModel;
use App\Models\student\studentInfirmaryModel;
use App\Models\student\studentVaccinationModel;
use App\Models\student\studentHWModel;
use App\Models\student\studentHealthModel;
use App\Models\student\tblstudentDocumentModel;
use App\Models\transportation\map_student\map_student;
use function App\Helpers\is_mobile;
use function App\Helpers\SearchStudent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class studentTransferController extends Controller {
	/**
	 * Display a listing of the resource.
	 *
	 * @return \Illuminate\Http\Response
	 */
	public function index(Request $request) 
	{
		$type = $request->input('type');
		$submit = $request->input('submit');
		$sub_institute_id = session()->get('sub_institute_id');
		$syear = session()->get('syear');
		$res['status'] = 1;
		$res['message'] = "Success";

		$from_institute_details = school_setupModel::where(['id' => $sub_institute_id])->get()->toArray();
        $from_institute_name = $from_client_id = '';
        if(count($from_institute_details) > 0)
        {
            $from_institute_name = $from_institute_details[0]['SchoolName'];
            $from_client_id = $from_institute_details[0]['client_id'];
        }

        $to_institute_details = school_setupModel::where(['client_id' => $from_client_id])->get()->toArray();

        $res['from_institute_name'] = $from_institute_name;
        $res['from_client_id'] = $from_client_id;
		$res['to_institute_details'] = $to_institute_details;

		return is_mobile($type, "student/show_student_transfer", $res, "view");
	}

	/**
	 * Show the form for creating a new resource.
	 *
	 * @return \Illuminate\Http\Response
	 */
	public function create(Request $request) 
	{
        // dd($request);
        $from_sub_institute_id = session()->get('sub_institute_id');
        $from_sub_institute_name = $request->input('from_institute_name');
		$from_client_id = $request->input('from_client_id');
		$from_syear = $request->input('from_syear');
		$from_grade = $request->input('grade');
		$from_standard = $request->input('standard');
		$from_division = $request->input('division');
        $to_sub_institute_id = $request->input('to_sub_institute_id');
        $to_syear = $request->input('to_syear');
        $to_academic_section = $request->input('to_academic_section');
        $to_standard = $request->input('to_standard');
		$to_division = $request->input('to_division');
		$type = $request->input('type');

        $studentData = SearchStudent($from_grade, $from_standard, $from_division, $from_sub_institute_id, $from_syear);

        $modules_array = array(
                    "general_information" => "General Information",
                    "past_education" => "Past Education",
                    "family_history" => "Family History",
                    "infirmary" => "Infirmary",
                    "vaccination" => "Vaccination",
                    "height_weight" => "Height & Weight",
                    "health" => "Health",
                    "document" => "Document",
                    "student_transportation" => "Student Transportation",
                );

        $to_institute_details = school_setupModel::where(['client_id' => $from_client_id])->get()->toArray();
        $to_academic_sections = academic_sectionModel::where(['sub_institute_id' => $to_sub_institute_id])->get()->toArray();
        $to_standards = standardModel::where(['grade_id' => $to_academic_section,'sub_institute_id' => $to_sub_institute_id])->get()->toArray();
        $to_divisions = std_div_mappingModel::select('division.*')
                    ->join("division",function($join){
                        $join->on("division.id","=","std_div_map.division_id")
                            ->on("division.sub_institute_id","=","std_div_map.sub_institute_id");
                        })
                    ->where(['std_div_map.standard_id' => $to_standard,'std_div_map.sub_institute_id' => $to_sub_institute_id])
                    ->get()->toArray();

		$res['status'] = 1;
		$res['message'] = "Success";
        $res['student_data'] = $studentData;
		$res['modules_array'] = $modules_array;
        $res['to_institute_details'] = $to_institute_details;
        $res['from_institute_name'] = $from_sub_institute_name;
		$res['from_syear'] = $from_syear;
		$res['grade'] = $from_grade;
		$res['standard'] = $from_standard;
		$res['division'] = $from_division;
		$res['to_sub_institute_id'] = $to_sub_institute_id;
        $res['to_syear'] = $to_syear;
        $res['to_academic_section'] = $to_academic_section;
        $res['to_standard'] = $to_standard;
        $res['to_division'] = $to_division;
        $res['to_academic_sections'] = $to_academic_sections;
        $res['to_standards'] = $to_standards;
        $res['to_divisions'] = $to_divisions;

		return is_mobile($type, "student/show_student_transfer", $res, "view");
	}

	/**
	 * Store a newly created resource in storage.
	 *
	 * @param  \Illuminate\Http\Request  $request
	 * @return \Illuminate\Http\Response
	 */
	public function store(Request $request) 
	{
		$from_sub_institute_id = session()->get('sub_institute_id');
		$from_syear = $request->get('from_syear');
		$from_grade = $request->get('grade');
		$from_standard = $request->get('standard');
		$from_division = $request->get('division');
		$to_sub_institute_id = $request->get('to_sub_institute_id');
		$to_syear = $request->get('to_syear');
		$to_academic_section = $request->get('to_academic_section');
		$to_standard = $request->get('to_standard');
		$to_division = $request->get('to_division');
		$students = $request->get('students');
		$type = $request->get('type');

		foreach ($students as $key => $student_id) 
		{

            // START Check student is already exist in other institute 
            $check_student = DB::select("SELECT * FROM tblstudent WHERE id = '".$student_id."' ");
            if(count($check_student) > 0)
            {
                $first_name = $check_student[0]->first_name;
                $last_name = $check_student[0]->last_name;
                $mobile = $check_student[0]->mobile;
                $email = $check_student[0]->email;

                $get_old_student_details = DB::select("SELECT count(*) as total_student FROM tblstudent
                    WHERE first_name = '".$first_name."' AND last_name = '".$last_name."' AND mobile = '".$mobile."' AND email = '".$email."' AND sub_institute_id = '".$to_sub_institute_id."' ");

                if($get_old_student_details[0]->total_student != 0)
                {
                    $res['status'] = 0;
                    $res['message'] = "Student is already exist in other institute.";
                    return \App\Helpers\is_mobile($type, "student_transfer.index", $res, "redirect");
                }   

            }
            // END Check student is already exist in other institute

            // START Check student's fees is paid or not
            $check_fees_paid = DB::select("SELECT IFNULL(SUM(fc.amount),0) as tot_paid_amt
                                        FROM fees_collect fc
                                        WHERE sub_institute_id = '".$from_sub_institute_id."' AND student_id = '".$student_id."' 
                                        AND syear = '".$from_syear."' AND is_deleted = 'N' ");
            if(count($check_fees_paid) > 0)
            {
                if($check_fees_paid[0]->tot_paid_amt != 0)
                {
                    $res['status'] = 0;
                    $res['message'] = "Student has already paid fees for the current institute.";
                    return \App\Helpers\is_mobile($type, "student_transfer.index", $res, "redirect");
                }
            }
            // END Check student's fees is paid or not

            // START UPDATE in tblstudent 
            $tblstudent_update = DB::select("UPDATE tblstudent SET sub_institute_id = '".$to_sub_institute_id."' ,
                                            user_profile_id = (
                                            SELECT id
                                            FROM tbluserprofilemaster
                                            WHERE sub_institute_id = '".$to_sub_institute_id."' AND name = 'Student')
                                            WHERE sub_institute_id = '".$from_sub_institute_id."' AND id = '".$student_id."' ");
            // END UPDATE in tblstudent 

            // START UPDATE in tblstudent_enrollment
            $get_new_student_quota = DB::select("SELECT sq.*
                                                FROM student_quota sq
                                                INNER JOIN tblstudent_enrollment se ON se.sub_institute_id = sq.sub_institute_id 
                                                AND sq.id = se.student_quota
                                                WHERE sq.sub_institute_id = '".$from_sub_institute_id."' 
                                                AND se.student_id = '".$student_id."' ");
            $new_student_quota = '';
            if(count($get_new_student_quota) > 0)
            {
                $new_student_quota = $get_new_student_quota[0]->title;

                $get_new_student_quota_id = DB::select("SELECT * FROM student_quota  
                                                        WHERE title = '".$new_student_quota."' 
                                                        AND sub_institute_id = '".$to_sub_institute_id."' ");

                $get_new_student_quota_id = $get_new_student_quota_id['0']->id;

            }

            $tblstudentenrollment_update = DB::select("UPDATE tblstudent_enrollment SET syear = '".$to_syear."',
                                                        sub_institute_id = '".$to_sub_institute_id."',grade_id='".$to_academic_section."',standard_id='".$to_standard."',section_id='".$to_division."',
                                                        student_quota='".$get_new_student_quota_id."'
                                                    WHERE sub_institute_id = '".$from_sub_institute_id."' 
                                                    AND student_id = '".$student_id."' ");
            // END UPDATE in tblstudent_enrollment

            if($request->has('modules'))
            {
                $modules = $request->get('modules');
                foreach ($modules as $k => $module_name)
                {
                    // START UPDATE in tblstudent_past_education 
                    if($module_name == 'past_education')
                    {
                        $past_education_update['sub_institute_id'] = $to_sub_institute_id;
                        tblstudentPastEducationModel::where(['student_id' => $student_id,'sub_institute_id' => $from_sub_institute_id])
                        ->update($past_education_update);
                    }
                    // END UPDATE in tblstudent_past_education

                    // START UPDATE in tblstudent_family_history 
                    if($module_name == 'family_history')
                    {
                        $family_history_update['sub_institute_id'] = $to_sub_institute_id;
                        tblstudentFamilyHistoryModel::where(['student_id' => $student_id,'sub_institute_id' => $from_sub_institute_id])
                        ->update($family_history_update);
                    }
                    // END UPDATE in tblstudent_family_history

                    // START UPDATE in student_infirmary 
                    if($module_name == 'infirmary')
                    {
                        $infirmary_update['sub_institute_id'] = $to_sub_institute_id;
                        $infirmary_update['syear'] = $to_syear;
                        studentInfirmaryModel::where(['student_id' => $student_id,'sub_institute_id' => $from_sub_institute_id])
                        ->update($infirmary_update);
                    }
                    // END UPDATE in student_infirmary

                    // START UPDATE in student_vaccination 
                    if($module_name == 'vaccination')
                    {
                        $vaccination_update['sub_institute_id'] = $to_sub_institute_id;
                        $vaccination_update['syear'] = $to_syear;
                        studentVaccinationModel::where(['student_id' => $student_id,'sub_institute_id' => $from_sub_institute_id])
                        ->update($vaccination_update);
                    }
                    // END UPDATE in student_vaccination

                    // START UPDATE in student_height_weight 
                    if($module_name == 'height_weight')
                    {
                        $hw_update['sub_institute_id'] = $to_sub_institute_id;
                        $hw_update['syear'] = $to_syear;
                        studentHWModel::where(['student_id' => $student_id,'sub_institute_id' => $from_sub_institute_id])
                        ->update($hw_update);
                    }
                    // END UPDATE in student_height_weight

                    // START UPDATE in student_health 
                    if($module_name == 'health')
                    {
                        $health_update['sub_institute_id'] = $to_sub_institute_id;
                        $health_update['syear'] = $to_syear;
                        studentHealthModel::where(['student_id' => $student_id,'sub_institute_id' => $from_sub_institute_id])
                        ->update($health_update);
                    }
                    // END UPDATE in student_health

                    // START UPDATE in tblstudent_document 
                    if($module_name == 'document')
                    {
                        $document_update['sub_institute_id'] = $to_sub_institute_id;
                        tblstudentDocumentModel::where(['student_id' => $student_id,'sub_institute_id' => $from_sub_institute_id])
                        ->update($document_update);
                    }
                    // END UPDATE in tblstudent_document

                    // START UPDATE in transport_map_student 
                    if($module_name == 'student_transportation')
                    {
                        $student_transportation_update['sub_institute_id'] = $to_sub_institute_id;
                        $student_transportation_update['syear'] = $to_syear;
                        map_student::where(['student_id' => $student_id,'sub_institute_id' => $from_sub_institute_id])
                        ->update($student_transportation_update);
                    }
                    // END UPDATE in transport_map_student
                }
            }
		}

        $res['status'] = "1";
        $res['message'] = "Student Transfer Successfully.";
        return \App\Helpers\is_mobile($type, "student_transfer.index", $res, "redirect");
	}

	/**
	 * Display the specified resource.
	 *
	 * @param  int  $id
	 * @return \Illuminate\Http\Response
	 */
	public function show($id) {
		//
	}

	/**
	 * Show the form for editing the specified resource.
	 *
	 * @param  int  $id
	 * @return \Illuminate\Http\Response
	 */
	public function edit($id) {
		//
	}

	/**
	 * Update the specified resource in storage.
	 *
	 * @param  \Illuminate\Http\Request  $request
	 * @param  int  $id
	 * @return \Illuminate\Http\Response
	 */
	public function update(Request $request, $id) {
		//
	}

	/**
	 * Remove the specified resource from storage.
	 *
	 * @param  int  $id
	 * @return \Illuminate\Http\Response
	 */
	public function destroy($id) {
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

        if (($number >= 0 && (int) $number < 0) || (int) $number < 0 - PHP_INT_MAX) {
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
                $tens = ((int) ($number / 10)) * 10;
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
                $numBaseUnits = (int) ($number / $baseUnit);
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
            foreach (str_split((string) $fraction) as $number) {
                $words[] = $dictionary[$number];
            }
            $string .= implode(' ', $words);
        }

        return $string;
    }

    public function ajax_toAcademicSections(Request $request)
    {
        $to_sub_institute_id = $request->input("to_sub_institute_id");   
        $academic_section_data = academic_sectionModel::where(['sub_institute_id' => $to_sub_institute_id])->get()->toArray();       
        return $academic_section_data; 
    }

    public function ajax_toStandards(Request $request)
    {
        $to_academic_section = $request->input("to_academic_section");   
        $standard_data = standardModel::where(['grade_id' => $to_academic_section])->get()->toArray();       
        return $standard_data; 
    }

    public function ajax_toDivisions(Request $request)
    {
        $to_standard = $request->input("to_standard");   
        $div_data = std_div_mappingModel::select('division.*')
                    ->join("division",function($join){
                        $join->on("division.id","=","std_div_map.division_id")
                            ->on("division.sub_institute_id","=","std_div_map.sub_institute_id");
                        })
                    ->where(['std_div_map.standard_id' => $to_standard])
                    ->get()->toArray();
        return $div_data; 
    }

}
