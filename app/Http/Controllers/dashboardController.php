<?php

namespace App\Http\Controllers;

use App\Models\fees\fees_collect\fees_collect;
use App\Models\student\tblstudentModel;
use App\Models\tblmenumasterModel;
use App\Models\user\tbluserModel;
use function App\Helpers\is_mobile;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\school_setup\facultywisetimetableController;
use Illuminate\Support\Facades\Storage;

class dashboardController extends Controller {
	/**
	 * Display a listing of the resource.
	 *
	 * @return \Illuminate\Http\Response
	 */	

	public function index(Request $request) {
		// dd(session()->all());
		$type = $request->input('type');
		$client_id = session()->get('client_id');
		$is_admin = session()->get('is_admin');
		$syear = $request->session()->get('syear');
		$user_profile_name = $request->session()->get("user_profile_name");
		$user_profile_id = $request->session()->get("user_profile_id");
		$user_id = $request->session()->get("user_id");

		$sub_institute_id = session()->get('sub_institute_id');			

		// dd($request);

		//START Dynamic Dashboard
		$userMenu = "SELECT *
            FROM dynamic_dashboard 	            
            WHERE sub_institute_id = '" . $sub_institute_id . "' AND user_id = '" . $user_id . "'
            ANd user_profile_id = '" . $user_profile_id . "'";
        $userMenu = DB::select($userMenu);
		$userMenu = array_map(function ($value) {
			return (array) $value;
		}, $userMenu);			
		$final_userMenu = array();			
		if (isset($userMenu)) {				
			foreach($userMenu as $key => $val)
			{
				$final_userMenu[] = $val['menu_id'];	
				$final_userMenuTitle[$val['menu_title']] = $val['menu_id'];	
			}				
		}	    						
		//END Dynamic Dashboard
										
		if ($user_profile_name == 'Super Admin' || $user_profile_name == 'Admin' || $user_profile_name == 'ADMIN' || $user_profile_name == 'admin' || $user_profile_name == 'school admin' || $user_profile_name == 'SCHOOL ADMIN' || $user_profile_name == 'School Admin') 
		{	

			if($sub_institute_id != 0 && $is_admin == '')
			{

			$date = date('Y-m-d');
			$date15 = date('Y-m-d', strtotime($date . ' +15 day'));

			$users = tbluserModel::selectRaw("count(id) as users")->where(['sub_institute_id' => $sub_institute_id, 'status' => "1"])->get()->toArray();

			$students = DB::select("SELECT COUNT(ts.id) students FROM tblstudent ts
INNER JOIN tblstudent_enrollment se ON se.student_id = ts.id AND se.sub_institute_id = ts.sub_institute_id
INNER JOIN standard s ON s.id = se.standard_id AND se.sub_institute_id = s.sub_institute_id
WHERE ts.sub_institute_id = '".$sub_institute_id."' AND se.syear = '".$syear."' AND se.end_date IS NULL");
			// dd($students[0]->students);

			$total_admission = DB::select("SELECT COUNT(id) as total_admissions FROM admission_enquiry 
				WHERE syear = '".$syear."' AND sub_institute_id = '".$sub_institute_id."' ");

			$fees_collects = fees_collect::selectRaw("ifnull(sum(amount),0) as fees")->where(['sub_institute_id' => $sub_institute_id,'syear' => $syear, 'is_deleted' => "N"])
				->whereRaw("date_format(created_date,'%Y-%m-%d') = '" . $date . "'")->get()->toArray();

			$other_fees_collects = DB::select("SELECT IFNULL(SUM(actual_amountpaid),0) AS fees
												FROM fees_paid_other
												WHERE (`sub_institute_id` = '".$sub_institute_id."' AND `syear` = '".$syear."' ) 
												AND DATE_FORMAT(created_date,'%Y-%m-%d') = '" . $date . "' AND is_deleted = 'N' ");	
			$other_fees_collects = json_decode(json_encode($other_fees_collects),true);

			// dd($other_fees_collects);
			$parentCommunication = DB::select("SELECT p.*,CONCAT_WS(' ',s.first_name,s.last_name) as student_name,s.image as student_image
			FROM parent_communication p INNER JOIN tblstudent s on p.student_id = s.id WHERE date_ = '" . $date . "' 
			AND p.sub_institute_id = '" . $sub_institute_id . "' order by p.id desc limit 10");

			$fees_collection = fees_collect::selectRaw('fees_collect.*,CONCAT_WS(" ",tblstudent.first_name,tblstudent.middle_name,tblstudent.last_name) as 
				student_name,sum(amount) as total_fees')
				->join('tblstudent', 'tblstudent.id', '=', 'fees_collect.student_id')
				->where(['fees_collect.sub_institute_id' => $sub_institute_id, 'fees_collect.is_deleted' => "N"])
				->whereRaw("date_format(fees_collect.created_date,'%Y-%m-%d') = '" . $date . "'")
				->groupBy('payment_mode')
				->take(10)->get()->toArray();

			$admissionBlockQuery = "SELECT COUNT(e.id) AS total_enquiry, COUNT(f.id) AS total_form ,COUNT(r.id) as total_registration,
					s.name AS standard_name
					FROM standard s
					LEFT JOIN admission_enquiry e ON s.id = e.admission_standard and e.sub_institute_id=s.sub_institute_id
					LEFT JOIN admission_form f ON f.admission_standard = s.id and f.sub_institute_id = s.sub_institute_id and e.enquiry_no = f.enquiry_no
					LEFT JOIN admission_registration r ON r.enquiry_no = f.enquiry_no and r.sub_institute_id = s.sub_institute_id
					WHERE s.sub_institute_id = '" . $sub_institute_id . "'
					GROUP BY s.id
					HAVING total_enquiry <> 0
					";
			$admissionBlock = DB::select($admissionBlockQuery);		

			$visitorBlockQuery = "SELECT appointment_type,concat(u.first_name,' ',u.middle_name,' ',u.last_name) as staff_name ,
					name,contact
					FROM visitor_master v 
					INNER JOIN tbluser u on u.id = v.to_meet
					WHERE v.sub_institute_id = '" . $sub_institute_id . "'	AND meet_date = '".date('Y-m-d')."'
					LIMIT 10";
			$visitorBlock = DB::select($visitorBlockQuery);	

			$smsParentQuery = "SELECT COUNT(*) as total_sms_parents FROM sms_sent_parents s WHERE s.sub_institute_id = '" . $sub_institute_id . "'";
			$smsParentBlock = DB::select($smsParentQuery);	

			$smsStaffQuery = "SELECT COUNT(*) as total_sms_staff FROM sms_sent_staff s WHERE s.sub_institute_id = '" . $sub_institute_id . "'";
			$smsStaffBlock = DB::select($smsStaffQuery);	

			$emailParentQuery = "SELECT COUNT(*) as total_email_parents FROM email_sent_parents s WHERE s.sub_institute_id = '" . $sub_institute_id . "'";
			$emailParentBlock = DB::select($emailParentQuery);	
			
			$smsNotificationBlock['Total Sms Parents'] = $smsParentBlock[0]->total_sms_parents;		
			$smsNotificationBlock['Total Sms Staff'] = $smsStaffBlock[0]->total_sms_staff;
			$smsNotificationBlock['Total Email Parents'] = $emailParentBlock[0]->total_email_parents;			

			$homeworkQuery = "SELECT COUNT(*) as total_homework FROM homework s WHERE s.sub_institute_id = '" . $sub_institute_id . "'
			AND date ='".date('Y-m-d')."'";
			$homeworkBlock = DB::select($homeworkQuery);
			
			$circularQuery = "SELECT COUNT(*) as total_circular FROM circular s WHERE s.sub_institute_id = '" . $sub_institute_id . "'
			AND date_ ='".date('Y-m-d')."'";
			$circularBlock = DB::select($circularQuery);

			$diciplineQuery = "SELECT COUNT(*) as total_dicipline FROM dicipline s WHERE s.sub_institute_id = '" . $sub_institute_id . "'
			AND date_ ='".date('Y-m-d')."'";
			$diciplineBlock = DB::select($diciplineQuery);

			$NQuery = "SELECT count(*) as total_notification,notification_type
						FROM app_notification
						WHERE sub_institute_id = '" . $sub_institute_id . "'
						GROUP BY notification_type";
			$NotificationBlock = DB::select($NQuery);
			if(count($NotificationBlock) > 0)
			{
				foreach($NotificationBlock as $nkey => $nval)
				{
					$ntitle = $nval->notification_type." Notification";
					$academicBlock[$ntitle] = $nval->total_notification;
				}
			}
			$academicBlock['Total Homework'] = $homeworkBlock[0]->total_homework;			
			$academicBlock['Total Circular'] = $circularBlock[0]->total_circular;			
			$academicBlock['Total Dicipline'] = $diciplineBlock[0]->total_dicipline;	


			$studentBirthdayQuery = "SELECT CONCAT_WS(' ',s.first_name,s.middle_name,s.last_name) as student_name,st.name as standard_name,d.name as division_name, DATE_FORMAT(s.dob, '%d-%m-%Y') AS dob
			FROM tblstudent s 
			INNER JOIN tblstudent_enrollment ts on s.id = ts.student_id and ts.syear = '" . $syear . "' 
			INNER JOIN standard st on ts.standard_id = st.id INNER JOIN division d on ts.section_id = d.id 
			WHERE s.sub_institute_id = '" . $sub_institute_id . "' and ts.end_date is null and DATE_FORMAT(s.dob, '%m-%d') >= DATE_FORMAT(NOW(), '%m-%d') and DATE_FORMAT(s.dob, '%m-%d') <= DATE_FORMAT((NOW() + INTERVAL +7 DAY), '%m-%d')
			ORDER BY DATE_FORMAT(s.dob, '%d')";
			// echo ('<pre>');print_r($studentBirthdayQuery);exit;

			$studentBirthdays = DB::select($studentBirthdayQuery);

			$teacherBirthdayQuery = "SELECT CONCAT_WS(' ',s.first_name,s.middle_name,s.last_name) as teacher_name,tu.name as designation,s.mobile as contact_number, DATE_FORMAT(s.birthdate, '%d-%m-%Y') AS birthdate
			FROM tbluser s 
			INNER JOIN tbluserprofilemaster tu on s.user_profile_id = tu.id 
			WHERE s.sub_institute_id = '" . $sub_institute_id . "' AND s.status !=0 and date_format(s.birthdate,'%m-%d') >= DATE_FORMAT(NOW(), '%m-%d') and DATE_FORMAT(s.birthdate, '%m-%d') <= DATE_FORMAT((NOW() + INTERVAL +7 DAY), '%m-%d')
			ORDER BY DATE_FORMAT(s.birthdate, '%d')";

			$teacherBirthdays = DB::select($teacherBirthdayQuery);

			$calendarEventsQuery = "SELECT *
			FROM calendar_events where sub_institute_id = '" . $sub_institute_id . "' and school_date >= '" . $date . "' AND school_date <= '" . $date15 . "'";

			$calendarEvents = DB::select($calendarEventsQuery);

			$studentLeave = "SELECT l.*, CONCAT_WS(' ',s.first_name,s.middle_name,s.last_name) AS student_name,st.name AS standard_name,
			dt.name AS division_name
			FROM leave_applications l
			INNER JOIN tblstudent s ON l.student_id = s.id
			INNER JOIN tblstudent_enrollment se ON s.id = se.student_id AND se.syear = '" . $syear . "'
			INNER JOIN standard st ON st.id = se.standard_id
			INNER JOIN division dt ON dt.id = se.section_id
			WHERE l.sub_institute_id = '" . $sub_institute_id . "' AND apply_date = '".$date."'
			LIMIT 10";//AND '" . $date . "' BETWEEN from_date AND to_date
			$studentLeaves = DB::select($studentLeave);

			 //echo ('<pre>');print_r($studentLeaves);exit;
		


			$standards_att = array();
			$absents = array();
			$presants = array();

			$attendanceCharts = "SELECT st.name as standard,dt.name,s.attendance_code, SUM(CASE WHEN s.attendance_code = 'A' THEN 1 ELSE 0 END) AS absent, 
			SUM(CASE WHEN s.attendance_code = 'P' THEN 1 ELSE 0 END) AS present
			FROM attendance_student s
			INNER JOIN standard st ON s.standard_id = st.id
			INNER JOIN division dt ON s.section_id = dt.id
			WHERE s.sub_institute_id = '" . $sub_institute_id . "' AND s.attendance_date = '" . $date . "'
			GROUP BY s.standard_id";

			$attendanceCharts = DB::select($attendanceCharts);

			foreach ($attendanceCharts as $key => $value) {
				// $standards = "'".$value->standard."',";
				$standards_att[] = $value->standard;
				$absents[] = (int) $value->absent;
				$presants[] = (int) $value->present;
			}

			$today = date("Y-m-d");
			$parameters = array(
				":dt" => $today,
				":sb" => $sub_institute_id,
				":syear" => $syear,
			);
			$fees_chart_data = DB::select('select sum(fc.amount) amount,s.name
	        from fees_collect fc
	        inner join tblstudent_enrollment se on se.student_id = fc.student_id and se.syear = :syear
	        inner join standard s on s.id = se.standard_id
	        where DATE_FORMAT(fc.created_date, "%Y-%m-%d") = :dt and fc.sub_institute_id = :sb group by se.standard_id', $parameters);

			$parameters = array(
				":syear" => $syear,
				":sb" => $sub_institute_id,
			);
			$student_chart_data = DB::select('select count(se.student_id) total_student,s.name
	        from tblstudent_enrollment se
	        inner join standard s on s.id = se.standard_id
	        where se.sub_institute_id = :sb  and se.syear = :syear
	        group by se.standard_id,s.id
	        order by s.sort_order
        	', $parameters);

			$total_fees = 0;
			$total_student = 0;
			$final_chart_data = " [{
            'id': '0.0',
            'parent': '',
            'name': 'Main Chart'
        }, {
            id: '1.1',
            parent: '0.0',
            name: 'Fees'
        }, {
            id: '1.2',
            parent: '0.0',
            name: 'Student'
        }, ";

			foreach ($fees_chart_data as $key => $value) {
				$total_fees = $total_fees + $value->amount;
				$final_chart_data .= "{
                'id': '2." . $key . "',
                'parent': '1.1',
                'name': '" . $value->name . "',
                'value':" . $value->amount . "
            },";
			}
			if (isset($next_id)) {
				$next_id = $key + 1;
			} else {
				$next_id = 0;
			}

			foreach ($student_chart_data as $key => $value) {
				$total_student = $total_student + $value->total_student;
				$ids = $next_id + $key;
				$final_chart_data .= "{
                'id': '2." . $ids . "',
                'parent': '1.2',
                'name': '" . $value->name . "',
                'value':" . $value->total_student . "
            },";
			}
			$final_chart_data = rtrim($final_chart_data, ",");
			$final_chart_data .= '];';

			$today = date("Y-m-d");
			$parameters = array(
				":dt" => $today,
				":sb" => $sub_institute_id,
				":syear" => $syear,
				":mode" => "cash",
			);
			$fees_chart1_cash_data = DB::select('select fc.amount,s.name
        from fees_collect fc
        inner join tblstudent_enrollment se on se.student_id = fc.student_id and se.syear = :syear
        inner join standard s on s.id = se.standard_id
        where DATE_FORMAT(fc.created_date, "%Y-%m-%d") = :dt
        and fc.sub_institute_id = :sb and payment_mode = :mode group by se.standard_id', $parameters);
			// echo ('<pre>');print_r($fees_chart1_cash_data);exit;
			$today = date("Y-m-d");
			$parameters = array(
				":dt" => $today,
				":sb" => $sub_institute_id,
				":syear" => $syear,
				":mode" => "cheque",
			);
			$fees_chart1_cheque_data = DB::select('select fc.amount,s.name
        from fees_collect fc
        inner join tblstudent_enrollment se on se.student_id = fc.student_id and se.syear = :syear
        inner join standard s on s.id = se.standard_id
        where DATE_FORMAT(fc.created_date, "%Y-%m-%d") = :dt
        and fc.sub_institute_id = :sb and payment_mode = :mode', $parameters);

			$final_chart1_data = " [{
            'id': '0.0',
            'parent': '',
            'name': 'Cash/Cheque Chart'
        }, {
            id: '1.1',
            parent: '0.0',
            name: 'Cash Fees'
        }, {
            id: '1.2',
            parent: '0.0',
            name: 'Cheque Fees'
        }, ";

			foreach ($fees_chart1_cash_data as $key => $value) {
				// $total_fees = $total_fees + $value->amount;
				$final_chart1_data .= "{
                'id': '2." . $key . "',
                'parent': '1.1',
                'name': '" . $value->name . "',
                'value':" . $value->amount . "
            },";
			}

			if (isset($key)) {
				$next_id = $key + 1;
			} else {
				$next_id = 0;
			}

			// $next_id = $key + 1;
			// echo('<pre>');
			// print_r($next_id);
			// exit;
			foreach ($fees_chart1_cheque_data as $key => $value) {
				// $total_student = $total_student + $value->total_student;
				$ids = $next_id + $key;
				$final_chart1_data .= "{
                'id': '2." . $ids . "',
                'parent': '1.2',
                'name': '" . $value->name . "',
                'value':" . $value->amount . "
            },";
			}
			$final_chart1_data = rtrim($final_chart1_data, ",");
			$final_chart1_data .= '];';

			$fees_chart2_bkoff_data = DB::select('SELECT SUM(fb.amount) amt,st.name
        FROM tblstudent s
        INNER JOIN tblstudent_enrollment se ON se.student_id = s.id
        INNER JOIN academic_section g ON g.id = se.grade_id
        INNER JOIN standard st ON st.id = se.standard_id
        LEFT JOIN division d ON d.id = se.section_id
        INNER JOIN fees_breackoff fb ON
         (fb.syear = "' . $syear . '" AND
         fb.admission_year = s.admission_year AND
         fb.quota = se.student_quota AND
         fb.grade_id = se.grade_id AND
         fb.standard_id = se.standard_id AND
         fb.sub_institute_id = "' . $sub_institute_id . '"
        )
        WHERE s.sub_institute_id = "' . $sub_institute_id . '" AND se.syear = "' . $syear . '"
        GROUP BY st.id ORDER BY st.id');
			// echo('<pre>');
			// print_r($fees_chart2_bkoff_data);
			// exit;

			$unpaid_data = "[";
			$std_data = "[";
			foreach ($fees_chart2_bkoff_data as $id => $arr) {
				$unpaid_data .= $arr->amt . ",";
				$std_data .= "'" . $arr->name . "'" . ",";
			}
			$unpaid_data = rtrim($unpaid_data, ",");
			$std_data = rtrim($std_data, ",");
			$unpaid_data .= "]";
			$std_data .= "]";

			// echo('<pre>');
			// print_r($unpaid_data);
			// print_r($std_data);
			// exit;

			$fees_chart2_fees_data = DB::select('SELECT SUM(fc.amount)+ SUM(fc.fees_discount) amount,st.name
        FROM tblstudent s
        INNER JOIN tblstudent_enrollment se ON se.student_id = s.id
        INNER JOIN academic_section g ON g.id = se.grade_id
        INNER JOIN standard st ON st.id = se.standard_id
        LEFT JOIN division d ON d.id = se.section_id
        INNER JOIN fees_collect fc ON
         (
         fc.student_id = s.id AND
         fc.sub_institute_id = "' . $sub_institute_id . '" AND
         fc.syear = "' . $syear . '"
        )
        WHERE s.sub_institute_id = "' . $sub_institute_id . '"
        GROUP BY st.id ORDER BY st.id');

			// echo('<pre>');
			// print_r($fees_chart2_fees_data);
			// exit;

			$paid_data = "[";
			foreach ($fees_chart2_fees_data as $id => $arr) {
				$paid_data .= $arr->amount . ",";
			}
			$paid_data = rtrim($paid_data, ",");
			$paid_data .= "]";

			$academicSections = DB::select("select * from academic_section where sub_institute_id = '" . $sub_institute_id . "'");

			$academicSections = array_map(function ($value) {
				return (array) $value;
			}, $academicSections);

			$gradeIds = '';
			foreach ($academicSections as $key => $value) {
				$gradeIds .= $value['id'] . ',';
			}

			$standards = DB::select("select * from standard where grade_id IN (" . rtrim($gradeIds, ",") . ") ");

			$standards = array_map(function ($value) {
				return (array) $value;
			}, $standards);

			$standardsArray = array();

			foreach ($standards as $key => $value) {
				$standardsArray[$value['grade_id']][] = $standards[$key];
			}

			$chartStudents = DB::select("SELECT s.id,se.grade_id,se.standard_id
		FROM tblstudent s
		INNER JOIN tblstudent_enrollment se ON s.id = se.student_id
		WHERE s.sub_institute_id = '" . $sub_institute_id . "' and grade_id != '' and standard_id != ''");

			// dd($chartStudents);
			$chartAS = array();
			$chartS = array();

			foreach ($chartStudents as $k => $v) {
				$chartAs[$v->grade_id][] = $v->id;
				$chartS[$v->standard_id][] = $v->id;
			}

			$chartFAs = array();
			$chartFS = array();

			$chartFees = DB::select("SELECT fc.amount,s.name,se.grade_id,se.standard_id
		FROM fees_collect fc
		INNER JOIN tblstudent_enrollment se ON se.student_id = fc.student_id AND se.syear = '" . $syear . "'
		INNER JOIN standard s ON s.id = se.standard_id
		WHERE fc.sub_institute_id = '" . $sub_institute_id . "'");

			foreach ($academicSections as $key => $value) {
				$chartFAs[$value['id']] = 0;
			}

			foreach ($standards as $key => $value) {
				$chartFS[$value['id']] = 0;
			}
			$totalFeesCF = 0;
			foreach ($chartFees as $k => $v) {
				$chartFAs[$v->grade_id] += $v->amount;
				$chartFS[$v->standard_id] += $v->amount;
				$totalFeesCF += $v->amount;
			}

			$chart = "[{
    id: '0.0',
    parent: '',
    name: 'Triz ERP',
    value: " . $students[0]->students . ",
    label: " . $students[0]->students . "
}, {
    id: '1.3',
    parent: '0.0',
    name: 'Student',
    value: " . $students[0]->students . ",
    label: " . $students[0]->students . ",
    events: {click: function (event) {alertValue('Student');}}
}, {
    id: '1.1',
    parent: '0.0',
    name: 'Fees',
    value: " . $students[0]->students . ",
    label: " . $totalFeesCF . ",
    events: {click: function (event) {alertValue('Fees');}}
}, {
    id: '1.2',
    parent: '0.0',
    name: 'Admission',
    value: " . $students[0]->students . ",
    label: " . $students[0]->students . ",
    events: {click: function (event) {alertValue('Admission');}}
}, {
    id: '1.4',
    parent: '0.0',
    name: 'Attendance',
    value: " . $students[0]->students . ",
    label: " . $students[0]->students . ",
    events: {click: function (event) {alertValue('Attendance');}}
}, {
    id: '1.5',
    parent: '0.0',
    name: 'Homework',
    value: " . $students[0]->students . ",
    label: " . $students[0]->students . ",
    events: {click: function (event) {alertValue('Homework');}}
},";

			$j = 6;
			$child = 1;
			$childL = 1;
			foreach ($academicSections as $k => $v) {
				if (isset($chartFAs[$v['id']])) {
					$ca = $chartFAs[$v['id']];
				} else {
					$ca = 0;
				}
				$chart .= "{id: '2." . $child . "',
    parent: '1.1',
    name: '" . $v['short_name'] . "',
    value: " . $ca . ",
    label: " . $ca . ",
    events: {click: function (event) {alertValue('Fees');}}
},";

				$childP = 1;
				$value = $ca / count($standardsArray[$v['id']]);
				foreach ($standardsArray[$v['id']] as $ke => $va) {
					if (isset($chartFS[$va['id']])) {
						$cs = $chartFS[$va['id']];
					} else {
						$cs = 0;
					}
					$j++;
					$chart .= "{id: '3." . $childL . $childP . "',
				    parent: '2." . $child . "',
				    name: '" . $va['short_name'] . "',
				    value: " . $cs . ",
				    label: " . $cs . ",
				    events: {click: function (event) {alertValue('Fees');}}
				},";
					$childP++;

				}
				$child++;
				$childL++;
				$j++;
			}

			$child = 1;
			$childL = 1;
			foreach ($academicSections as $k => $v) {
				if (isset($chartAs[$v['id']])) {
					$ca = count($chartAs[$v['id']]);
				} else {
					$ca = 0;
				}
				$chart .= "{id: '3." . $child . "',
    parent: '1.2',
    name: '" . $v['short_name'] . "',
    value: " . $ca . ",
    label: " . $ca . ",
    events: {click: function (event) {alertValue('Admission');}}
},";

				$childP = 1;
				$value = $ca / count($standardsArray[$v['id']]);
				foreach ($standardsArray[$v['id']] as $ke => $va) {
					if (isset($chartS[$va['id']])) {
						$cs = count($chartS[$va['id']]);
					} else {
						$cs = 0;
					}
					$j++;
					$chart .= "{id: '4." . $childL . $childP . "',
				    parent: '3." . $child . "',
				    name: '" . $va['short_name'] . "',
				    value: " . $cs . ",
				    label: " . $cs . ",
				    events: {click: function (event) {alertValue('Admission');}}
				},";
					$childP++;

				}
				$child++;
				$childL++;
				$j++;
			}

			$child = 1;
			$childL = 1;
			foreach ($academicSections as $k => $v) {
				if (isset($chartAs[$v['id']])) {
					$ca = count($chartAs[$v['id']]);
				} else {
					$ca = 0;
				}
				$chart .= "{id: '4." . $child . "',
    parent: '1.3',
    name: '" . $v['short_name'] . "',
    value: " . $ca . ",
    label: " . $ca . ",
    events: {click: function (event) {alertValue('Student');}}
},";

				$childP = 1;
				$value = $ca / count($standardsArray[$v['id']]);
				foreach ($standardsArray[$v['id']] as $ke => $va) {
					if (isset($chartS[$va['id']])) {
						$cs = count($chartS[$va['id']]);
					} else {
						$cs = 0;
					}
					$j++;
					$chart .= "{id: '5." . $childL . $childP . "',
				    parent: '4." . $child . "',
				    name: '" . $va['short_name'] . "',
				    value: " . $cs . ",
				    label: " . $cs . ",
				    events: {click: function (event) {alertValue('Student');}}
				},";
					$childP++;

				}
				$child++;
				$childL++;
				$j++;
			}

			$child = 1;
			$childL = 1;
			foreach ($academicSections as $k => $v) {
				if (isset($chartAs[$v['id']])) {
					$ca = count($chartAs[$v['id']]);
				} else {
					$ca = 0;
				}
				$chart .= "{id: '5." . $child . "',
    parent: '1.4',
    name: '" . $v['short_name'] . "',
    value: " . $ca . ",
    label: " . $ca . ",
    events: {click: function (event) {alertValue('Attendance');}}
},";

				$childP = 1;
				$value = $ca / count($standardsArray[$v['id']]);
				foreach ($standardsArray[$v['id']] as $ke => $va) {
					if (isset($chartS[$va['id']])) {
						$cs = count($chartS[$va['id']]);
					} else {
						$cs = 0;
					}
					$j++;
					$chart .= "{id: '6." . $childL . $childP . "',
				    parent: '5." . $child . "',
				    name: '" . $va['short_name'] . "',
				    value: " . $cs . ",
				    label: " . $cs . ",
				    events: {click: function (event) {alertValue('Attendance');}}
				},";
					$childP++;

				}
				$child++;
				$childL++;
				$j++;
			}

			$child = 1;
			$childL = 1;
			foreach ($academicSections as $k => $v) {
				if (isset($chartAs[$v['id']])) {
					$ca = count($chartAs[$v['id']]);
				} else {
					$ca = 0;
				}
				$chart .= "{id: '6." . $child . "',
    parent: '1.5',
    name: '" . $v['short_name'] . "',
    value: " . $ca . ",
    label: " . $ca . ",
    events: {click: function (event) {alertValue('Homework');}}
},";

				$childP = 1;
				$value = $ca / count($standardsArray[$v['id']]);
				foreach ($standardsArray[$v['id']] as $ke => $va) {
					if (isset($chartS[$va['id']])) {
						$cs = count($chartS[$va['id']]);
					} else {
						$cs = 0;
					}
					$j++;
					$chart .= "{id: '7." . $childL . $childP . "',
				    parent: '6." . $child . "',
				    name: '" . $va['short_name'] . "',
				    value: " . $cs . ",
				    label: " . $cs . ",
				    events: {click: function (event) {alertValue('Homework');}}
				},";
					$childP++;

				}
				$child++;
				$childL++;
				$j++;
			}

			$chart = rtrim($chart, ",");

			$chart .= "]";

			$res['status_code'] = 1;
			$res['message'] = "Success";
			$res['totalUser'] = $users[0]['users'];
			$res['totalStudent'] = $students[0]->students;
			$res['totalFees'] = ($fees_collects[0]['fees'] + $other_fees_collects[0]['fees']);
			$res['totalAdmission'] = $total_admission[0]->total_admissions;
			
			
			$res['studentBirthdays'] = $studentBirthdays;
			$res['teacherBirthdays'] = $teacherBirthdays;
			
			
			$res['chartData'] = $final_chart_data;
			$res['unpaid_fees_data'] = $unpaid_data;
			$res['paid_fee_data'] = $paid_data;
			$res['std_data'] = $std_data;
			$res['chart1Data'] = $final_chart1_data;
			$res['chart'] = $chart;
			//$res['final_dynamic_dashboard'] = $final_dynamic_dashboard;
			$res['final_userMenu'] = $final_userMenu;

			if(isset($final_userMenuTitle))
			{
				foreach($final_userMenuTitle as $key => $val)
				{
					if($key == "Student Attendance")
					{
						$res['standardsJson'] = json_encode($standards_att, true);
						$res['absentsJson'] = json_encode($absents, true);
						$res['presantsJson'] = json_encode($presants, true);					
					}
					elseif($key == "Recent Parent Communication")
					{
						$res['parentCommunications'] = $parentCommunication;			
					}
					elseif($key == "Student Fees Chart")
					{
						$res['studentFeesChart'] = 1;			
					}
					elseif($key == "Recent fees collection")
					{
						$res['recentFeesCollection'] = $fees_collection;
					}					
					elseif($key == "Events")
					{
						$res['calendarEvents'] = $calendarEvents;
					}
					elseif($key == "Student Leaves")
					{
						$res['studentLeaves'] = $studentLeaves;
					}
					elseif($key == "Admission Information")
					{
						$res['admissionBlock'] = $admissionBlock;
					}
					elseif($key == "Recent Visitors")
					{
						$res['visitorBlock'] = $visitorBlock;
					}
					elseif($key == "Sms Notification")
					{
						$res['smsNotificationBlock'] = $smsNotificationBlock;
					}
					elseif($key == "Academic Information")
					{
						$res['academicBlock'] = $academicBlock;
					}
																			
				}
			}

			$current_date = date('Y-m-d');
			$school_setup_data = DB::select("SELECT *,DATEDIFF(expire_date, '".$current_date."') as remaining_days,SUM(IFNULL(given_space_mb,0)) as given_space_mb FROM school_setup WHERE Id = '" . $sub_institute_id . "'");
			$school_setup_data = json_decode(json_encode($school_setup_data[0]),true);
			$res['school_setup_data'] = $school_setup_data;			

			//START Code for calculate used space using folder-wise table
			$photo_video_data = DB::select("SELECT SUM(IFNULL(file_size,0)) as file_size FROM photo_video_gallary WHERE sub_institute_id = '" . $sub_institute_id . "'");
			$photo_video_data_size = 0;
			if(count($photo_video_data) != 0)
			{
				$photo_video_data_size = $photo_video_data[0]->file_size;
			}

			$leave_applications_data = DB::select("SELECT SUM(IFNULL(file_size,0)) as file_size FROM leave_applications WHERE sub_institute_id = '" . $sub_institute_id . "'");
			$leave_applications_data_size = 0;
			if(count($leave_applications_data) != 0)
			{
				$leave_applications_data_size = $leave_applications_data[0]->file_size;
			}

			$exam_schedule_data = DB::select("SELECT SUM(IFNULL(file_size,0)) as file_size FROM exam_schedule WHERE sub_institute_id = '" . $sub_institute_id . "'");
			$exam_schedule_data_size = 0;
			if(count($exam_schedule_data) != 0)
			{
				$exam_schedule_data_size = $exam_schedule_data[0]->file_size;
			}

			$inward_data = DB::select("SELECT SUM(IFNULL(attachment_size,0)) as file_size FROM inward WHERE sub_institute_id = '" . $sub_institute_id . "'");
			$inward_data_size = 0;
			if(count($inward_data) != 0)
			{
				$inward_data_size = $inward_data[0]->file_size;
			}

			$outward_data = DB::select("SELECT SUM(IFNULL(attachment_size,0)) as file_size FROM outward WHERE sub_institute_id = '" . $sub_institute_id . "'");
			$outward_data_size = 0;
			if(count($outward_data) != 0)
			{
				$outward_data_size = $outward_data[0]->file_size;
			}
			
			$petty_cash_data = DB::select("SELECT SUM(IFNULL(file_size,0)) as file_size FROM petty_cash WHERE sub_institute_id = '" . $sub_institute_id . "'");
			$petty_cash_size = 0;
			if(count($petty_cash_data) != 0)
			{
				$petty_cash_size = $petty_cash_data[0]->file_size;
			}

			$homework_data = DB::select("SELECT IFNULL(SUM(distinct image_size),0) as file_size,image_size 
										FROM homework 
										WHERE sub_institute_id = '" . $sub_institute_id . "' 
										GROUP BY image
										HAVING image_size > 0 ");
			$homework_size = 0;
			if(count($homework_data) != 0)
			{
				$homework_size = $homework_data[0]->file_size;
			}
			
			$homework_submission_data = DB::select("SELECT SUM(IFNULL(submission_image_size,0)) as file_size FROM homework WHERE sub_institute_id = '" . $sub_institute_id . "'");
			$homework_submission_size = 0;
			if(count($homework_submission_data) != 0)
			{
				$homework_submission_size = $homework_submission_data[0]->file_size;
			}

			$visitor_data = DB::select("SELECT SUM(IFNULL(file_size,0)) as file_size FROM visitor_master WHERE sub_institute_id = '" . $sub_institute_id . "'");
			$visitor_size = 0;
			if(count($visitor_data) != 0)
			{
				$visitor_size = $visitor_data[0]->file_size;
			}

			$frontdesk_data = DB::select("SELECT SUM(IFNULL(FILE_SIZE,0)) as file_size FROM front_desk WHERE sub_institute_id = '" . $sub_institute_id . "'");
			$frontdesk_size = 0;
			if(count($frontdesk_data) != 0)
			{
				$frontdesk_size = $frontdesk_data[0]->file_size;
			}

			$task_data = DB::select("SELECT SUM(IFNULL(FILE_SIZE,0)) as file_size FROM front_desk WHERE sub_institute_id = '" . $sub_institute_id . "'");
			$task_size = 0;
			if(count($task_data) != 0)
			{
				$task_size = $task_data[0]->file_size;
			}

			$complaint_data = DB::select("SELECT SUM(IFNULL(FILE_SIZE,0)) as file_size FROM complaint WHERE sub_institute_id = '" . $sub_institute_id . "'");
			$complaint_size = 0;
			if(count($complaint_data) != 0)
			{
				$complaint_size = $complaint_data[0]->file_size;
			}

			$student_data = DB::select("SELECT SUM(IFNULL(file_size,0)) as file_size FROM tblstudent WHERE sub_institute_id = '" . $sub_institute_id . "'");
			$student_size = 0;
			if(count($student_data) != 0)
			{
				$student_size = $student_data[0]->file_size;
			}

			$student_health_data = DB::select("SELECT SUM(IFNULL(file_size,0)) as file_size FROM student_health WHERE sub_institute_id = '" . $sub_institute_id . "'");
			$student_health_size = 0;
			if(count($student_health_data) != 0)
			{
				$student_health_size = $student_health_data[0]->file_size;
			}

			$total_used_size = $photo_video_data_size + $leave_applications_data_size + $exam_schedule_data_size + $inward_data_size + $outward_data_size + $petty_cash_size + $homework_size + $homework_submission_size + $visitor_size + $frontdesk_size + $task_size + $complaint_size + $student_size + $student_health_size;
			$used_space_in_MB = $this->formatBytes($total_used_size);
			$explod_used_space = explode(' ', $used_space_in_MB);
			$occupied_space_in_MB = $school_setup_data['given_space_mb'];
			$available_space_in_MB = ($occupied_space_in_MB - $explod_used_space[0]);

			$res['occupied_space_in_MB'] = $occupied_space_in_MB;
            $res['used_space_in_MB'] = $used_space_in_MB;
            $res['available_space_in_MB'] = $available_space_in_MB;
			//END Code for calculate used space using folder-wise table   

			// START Code for calculate used space in MB (using sub_institute_wise folder)
			/*$size = 0;
			$directories = Storage::disk('public')->allDirectories($sub_institute_id);
            foreach ($directories as $directory) {
            	$files = Storage::disk('public')->files($directory);
                foreach ($files as $file) {
                    $size += Storage::size('public/'.$file);
                }
            }
            $occupied_space_in_MB = $school_setup_data['given_space_mb'];
            $used_space_in_MB = $this->formatBytes($size);
            $available_space_in_MB = ($occupied_space_in_MB - $used_space_in_MB);

            $res['occupied_space_in_MB'] = $occupied_space_in_MB;
            $res['used_space_in_MB'] = $used_space_in_MB;
            $res['available_space_in_MB'] = $available_space_in_MB;*/
            // END Code for calculate used space in MB (using sub_institute_wise folder) 

				return is_mobile($type, "home", $res, "view");
			}else{
				$type = $request->input('type');
				$res['status_code'] = 1;
				$res['message'] = "Success";
				return is_mobile($type, "home", $res, "view");
			}
		} else {

			// if ($sub_institute_id == 46) {
			// 	$date = "2019-08-22"; //date('Y-m-d');
			// } else {
				$date = date('Y-m-d');
			// }

			$date15 = date('Y-m-d', strtotime($date . ' +15 day'));

			$users = tbluserModel::selectRaw("count(id) as users")->where(['sub_institute_id' => $sub_institute_id, 'status' => "1"])->get()->toArray();

			$students = DB::select("SELECT COUNT(ts.id) students FROM tblstudent ts
			INNER JOIN tblstudent_enrollment se ON se.student_id = ts.id AND se.sub_institute_id = se.sub_institute_id
			INNER JOIN standard s ON s.id = se.standard_id AND se.sub_institute_id = s.sub_institute_id
			WHERE ts.sub_institute_id = '".$sub_institute_id."' AND se.syear = '".$syear."' AND se.end_date IS NULL");
			// dd($students[0]->students);
			// $students = tblstudentModel::selectRaw("count(id) as students")->where(['sub_institute_id' => $sub_institute_id])->get()->toArray();

			$total_admission = DB::select("SELECT COUNT(id) as total_admissions FROM admission_enquiry 
				WHERE syear = '".$syear."' AND sub_institute_id = '".$sub_institute_id."' ");

			$fees_collects = fees_collect::selectRaw("ifnull(sum(amount),0) as fees")->where(['sub_institute_id' => $sub_institute_id,'syear' => $syear, 'is_deleted' => "N"])
				->whereRaw("date_format(created_date,'%Y-%m-%d') = '" . $date . "'")->get()->toArray();

			$other_fees_collects = DB::select("SELECT IFNULL(SUM(actual_amountpaid),0) AS fees
												FROM fees_paid_other
												WHERE (`sub_institute_id` = '".$sub_institute_id."' AND `syear` = '".$syear."' ) 
												AND DATE_FORMAT(created_date,'%Y-%m-%d') = '" . $date . "' AND is_deleted = 'N' ");	
			$other_fees_collects = json_decode(json_encode($other_fees_collects),true);	

			if($user_profile_name == 'Student')
			{
				$parentCommunication = DB::select("SELECT p.*,CONCAT_WS(' ',s.first_name,s.last_name) as student_name,
						s.image as student_image
					FROM parent_communication p 
					INNER JOIN tblstudent s on p.student_id = s.id WHERE date_ = '" . $date . "' AND 
					p.sub_institute_id = '" . $sub_institute_id . "' AND s.id = '".$user_id."' order by p.id desc limit 10");
			}else{
				$parentCommunication = DB::select("SELECT p.*,CONCAT_WS(' ',s.first_name,s.last_name) as student_name,s.image as student_image
					FROM parent_communication p INNER JOIN tblstudent s on p.student_id = s.id WHERE date_ = '" . $date . "' AND 
					p.sub_institute_id = '" . $sub_institute_id . "' order by p.id desc limit 10");
			}

			
			// dd($parentCommunication);
			
			$standards_att = $absents = $presants = array();

			$attendanceCharts = "SELECT st.name as standard,dt.name,s.attendance_code, SUM(CASE WHEN s.attendance_code = 'A' THEN 1 ELSE 0 END) AS absent, 
			SUM(CASE WHEN s.attendance_code = 'P' THEN 1 ELSE 0 END) AS present
			FROM attendance_student s
			INNER JOIN standard st ON s.standard_id = st.id
			INNER JOIN division dt ON s.section_id = dt.id
			WHERE s.sub_institute_id = '" . $sub_institute_id . "' AND s.attendance_date = '" . $date . "'
			GROUP BY s.standard_id";

			$attendanceCharts = DB::select($attendanceCharts);

			foreach ($attendanceCharts as $key => $value) {
				// $standards = "'".$value->standard."',";
				$standards_att[] = $value->standard;
				$absents[] = (int) $value->absent;
				$presants[] = (int) $value->present;
			}

			$admissionBlockQuery = "SELECT COUNT(e.id) AS total_enquiry, COUNT(f.id) AS total_form ,COUNT(r.id) as total_registration,
					s.name AS standard_name
					FROM standard s
					LEFT JOIN admission_enquiry e ON s.id = e.admission_standard and e.sub_institute_id=s.sub_institute_id
					LEFT JOIN admission_form f ON f.admission_standard = s.id and f.sub_institute_id = s.sub_institute_id and e.enquiry_no = f.enquiry_no
					LEFT JOIN admission_registration r ON r.enquiry_no = f.enquiry_no and r.sub_institute_id = s.sub_institute_id
					WHERE s.sub_institute_id = '" . $sub_institute_id . "'
					GROUP BY s.id
					HAVING total_enquiry <> 0
					";
			$admissionBlock = DB::select($admissionBlockQuery);		

			$visitorBlockQuery = "SELECT appointment_type,concat(u.first_name,' ',u.middle_name,' ',u.last_name) as staff_name ,
					name,contact
					FROM visitor_master v 
					INNER JOIN tbluser u on u.id = v.to_meet
					WHERE v.sub_institute_id = '" . $sub_institute_id . "'	AND meet_date = '".date('Y-m-d')."'
					LIMIT 10";
			$visitorBlock = DB::select($visitorBlockQuery);	

			$smsParentQuery = "SELECT COUNT(*) as total_sms_parents FROM sms_sent_parents s WHERE s.sub_institute_id = '" . $sub_institute_id . "'";
			$smsParentBlock = DB::select($smsParentQuery);	

			$smsStaffQuery = "SELECT COUNT(*) as total_sms_staff FROM sms_sent_staff s WHERE s.sub_institute_id = '" . $sub_institute_id . "'";
			$smsStaffBlock = DB::select($smsStaffQuery);	

			$emailParentQuery = "SELECT COUNT(*) as total_email_parents FROM email_sent_parents s WHERE s.sub_institute_id = '" . $sub_institute_id . "'";
			$emailParentBlock = DB::select($emailParentQuery);	
			
			$smsNotificationBlock['Total Sms Parents'] = $smsParentBlock[0]->total_sms_parents;		
			$smsNotificationBlock['Total Sms Staff'] = $smsStaffBlock[0]->total_sms_staff;
			$smsNotificationBlock['Total Email Parents'] = $emailParentBlock[0]->total_email_parents;			

			$homeworkQuery = "SELECT COUNT(*) as total_homework FROM homework s WHERE s.sub_institute_id = '" . $sub_institute_id . "'
			AND date = '".date('Y-m-d')."' AND created_by = '".$user_id."'";
			$homeworkBlock = DB::select($homeworkQuery);
			
			$circularQuery = "SELECT COUNT(*) as total_circular FROM circular s WHERE s.sub_institute_id = '" . $sub_institute_id . "'
			AND date_ ='".date('Y-m-d')."'";
			$circularBlock = DB::select($circularQuery);

			$diciplineQuery = "SELECT COUNT(*) as total_dicipline FROM dicipline s WHERE s.sub_institute_id = '" . $sub_institute_id . "'
			AND date_ ='".date('Y-m-d')."' AND created_by = '".$user_id."'";
			$diciplineBlock = DB::select($diciplineQuery);

			$NQuery = "SELECT count(*) as total_notification,notification_type
						FROM app_notification
						WHERE sub_institute_id = '" . $sub_institute_id . "'
						AND (student_id = '".$user_id."' OR student_id IS NULL)
						GROUP BY notification_type";
			$NotificationBlock = DB::select($NQuery);
			
			if(count($NotificationBlock) > 0)
			{
				foreach($NotificationBlock as $nkey => $nval)
				{
					$ntitle = $nval->notification_type." Notification";
					$academicBlock[$ntitle] = $nval->total_notification;
				}
			}			

			$academicBlock['Total Homework'] = $homeworkBlock[0]->total_homework;			
			$academicBlock['Total Circular'] = $circularBlock[0]->total_circular;			
			$academicBlock['Total Dicipline'] = $diciplineBlock[0]->total_dicipline;						

			$studentBirthdayQuery = "SELECT CONCAT_WS(' ',s.first_name,s.middle_name,s.last_name) as student_name,st.name as standard_name,d.name as division_name, DATE_FORMAT(s.dob, '%d-%m-%Y') AS dob
			FROM tblstudent s 
			INNER JOIN tblstudent_enrollment ts on s.id = ts.student_id and ts.syear = '" . $syear . "' 
			INNER JOIN standard st on ts.standard_id = st.id INNER JOIN division d on ts.section_id = d.id 
			WHERE s.sub_institute_id = '" . $sub_institute_id . "' and ts.end_date is null  and DATE_FORMAT(s.dob, '%m-%d') >= DATE_FORMAT(NOW(), '%m-%d') and DATE_FORMAT(s.dob, '%m-%d') <= DATE_FORMAT((NOW() + INTERVAL +7 DAY), '%m-%d')
			ORDER BY DATE_FORMAT(s.dob, '%d')";

			$studentBirthdays = DB::select($studentBirthdayQuery);

			$teacherBirthdayQuery = "SELECT CONCAT_WS(' ',s.first_name,s.middle_name,s.last_name) as teacher_name,tu.name as designation,s.mobile as contact_number, DATE_FORMAT(s.birthdate, '%d-%m-%Y') AS birthdate
			FROM tbluser s 
			INNER JOIN tbluserprofilemaster tu on s.user_profile_id = tu.id 
			WHERE s.sub_institute_id = '" . $sub_institute_id . "' AND s.status !=0 and date_format(s.birthdate,'%m-%d') >= DATE_FORMAT(NOW(), '%m-%d') and DATE_FORMAT(s.birthdate, '%m-%d') <= DATE_FORMAT((NOW() + INTERVAL +7 DAY), '%m-%d')
			ORDER BY DATE_FORMAT(s.birthdate, '%d')";

			$teacherBirthdays = DB::select($teacherBirthdayQuery);

			$calendarEventsQuery = "SELECT *
		    FROM calendar_events where sub_institute_id = '" . $sub_institute_id . "' and school_date >= '" . $date . "' AND school_date <= '" . $date15 . "'";

			$calendarEvents = DB::select($calendarEventsQuery);

			$res['totalUser'] = $users[0]['users'];
			$res['totalStudent'] = $students[0]->students;
			$res['totalFees'] = ($fees_collects[0]['fees'] + $other_fees_collects[0]['fees']);
			$res['totalAdmission'] = $total_admission[0]->total_admissions;
						
			$res['studentBirthdays'] = $studentBirthdays;
			$res['teacherBirthdays'] = $teacherBirthdays;	
			if($user_profile_name == 'Student')
			{
				$stu_homework = DB::select("SELECT h.*,CONCAT_WS(' ',s.first_name,s.last_name) as student_name,
									s.image as student_image
								  FROM homework h 
								  INNER JOIN tblstudent s ON s.id = h.student_id AND s.sub_institute_id = h.sub_institute_id
								  WHERE h.sub_institute_id = '" . $sub_institute_id . "'
								  AND h.date = '".date('Y-m-d')."' 
								  AND h.student_id = '".$user_id."'");

				$res['parentCommunications'] = $parentCommunication;
				$res['stu_homework'] = $stu_homework;
			}

			$res['final_userMenu'] = $final_userMenu;

			// dd($final_userMenuTitle);

			if(isset($final_userMenuTitle))
			{
				foreach($final_userMenuTitle as $key => $val)
				{
					if($key == "Student Attendance")
					{
						$res['standardsJson'] = json_encode($standards_att, true);
						$res['absentsJson'] = json_encode($absents, true);
						$res['presantsJson'] = json_encode($presants, true);					
					}
					elseif($key == "Recent Parent Communication")
					{
						$res['parentCommunications'] = $parentCommunication;			
					}
					elseif($key == "Student Fees Chart")
					{
						$res['studentFeesChart'] = 1;			
					}
					elseif($key == "Recent fees collection")
					{
						$res['recentFeesCollection'] = $fees_collection;
					}					
					elseif($key == "Events")
					{
						$res['calendarEvents'] = $calendarEvents;
					}
					elseif($key == "Student Leaves")
					{
						$res['studentLeaves'] = $studentLeaves;
					}
					elseif($key == "Admission Information")
					{
						$res['admissionBlock'] = $admissionBlock;
					}
					elseif($key == "Recent Visitors")
					{
						$res['visitorBlock'] = $visitorBlock;
					}
					elseif($key == "Sms Notification")
					{
						$res['smsNotificationBlock'] = $smsNotificationBlock;
					}
					elseif($key == "Academic Information")
					{
						$res['academicBlock'] = $academicBlock;
					}
					elseif($key == "Faculty Timetable")
					{
						$foo = new facultywisetimetableController();					
						$tdata = $foo->getTimetable_data($request,$user_id,$sub_institute_id,$syear);	
						$res['facultytimetableBlock'] = $tdata['HTML'];
					}
										
				}
			}

			//START Code for calculate used space using folder-wise table

			$school_setup_data = DB::select("SELECT *,SUM(IFNULL(given_space_mb,0)) as given_space_mb FROM school_setup WHERE Id = '" . $sub_institute_id . "'");
			$school_setup_data = json_decode(json_encode($school_setup_data[0]),true);
			
			$petty_cash_data = DB::select("SELECT SUM(IFNULL(file_size,0)) as file_size FROM petty_cash WHERE sub_institute_id = '" . $sub_institute_id . "' AND user_id = '".$user_id."' ");
			$petty_cash_size = 0;
			if($petty_cash_data[0]->file_size != 0)
			{
				$petty_cash_size = $petty_cash_data[0]->file_size;
			}

			$get_photo_data = DB::select("SELECT SUM(IFNULL(p.file_size,0)) AS file_size 
                                    FROM photo_video_gallary p
                                    INNER JOIN tblstudent_enrollment se ON se.standard_id = p.standard_id AND se.section_id = p.division_id
                                    AND se.sub_institute_id = p.sub_institute_id
                                    INNER JOIN tblstudent s ON s.id = se.student_id AND s.sub_institute_id = se.sub_institute_id
                                    WHERE p.sub_institute_id = '".$sub_institute_id."' AND s.id = '".$user_id."' ");
			$photo_video_size = 0;
			if($get_photo_data[0]->file_size != 0)
			{
				$photo_video_size = $get_photo_data[0]->file_size;
			}

			$get_leave_app_data = DB::select("SELECT SUM(IFNULL(l.file_size,0)) AS file_size
                                    FROM leave_applications l
                                    INNER JOIN tblstudent s ON s.id = l.student_id AND s.sub_institute_id = l.sub_institute_id
                                    WHERE l.sub_institute_id = '".$sub_institute_id."' AND s.id = '".$user_id."' ");
			$leave_app_size = 0;
			if($get_leave_app_data[0]->file_size != 0)
			{
				$leave_app_size = $get_leave_app_data[0]->file_size;
			}

			$get_exam_schedule_data = DB::select("SELECT SUM(IFNULL(e.file_size,0)) AS file_size
                                        FROM exam_schedule e
                                        INNER JOIN tblstudent_enrollment se ON se.standard_id = e.standard_id AND se.section_id = e.division_id 
                                        AND se.sub_institute_id = e.sub_institute_id
                                        INNER JOIN tblstudent s ON s.id = se.student_id AND s.sub_institute_id = se.sub_institute_id
                                        WHERE e.sub_institute_id = '".$sub_institute_id."' AND s.id = '".$user_id."' ");
			$exam_schedule_size = 0;
			if(count($get_exam_schedule_data) > 0)
			{
				$exam_schedule_size = $get_exam_schedule_data[0]->file_size;
			}

			if($user_profile_name == 'Student')
            {
                $homework_data = DB::select("SELECT IFNULL(SUM(DISTINCT p.image_size),0) AS file_size,
                                    CONCAT_WS(' ',s.first_name,s.last_name) AS user_name,
                                    p.image_size
                                    FROM homework p
                                    INNER JOIN tblstudent s ON s.id = p.student_id AND s.sub_institute_id = p.sub_institute_id
                                    WHERE p.sub_institute_id = '".$sub_institute_id."' AND s.id = '".$user_id."' ");
            }else{

				$homework_data = DB::select("SELECT IFNULL(SUM(distinct image_size),0) as file_size,image_size 
										FROM homework 
										WHERE sub_institute_id = '" . $sub_institute_id . "' AND created_by = '".$user_id."'
										GROUP BY image
										HAVING image_size > 0 ");
			}	
			$homework_size = 0;
			if(count($homework_data) > 0)
			{
				$homework_size = $homework_data[0]->file_size;
			}
			
			if($user_profile_name == 'Student')
            {
                $homework_submission_data = DB::select("SELECT IFNULL(SUM(DISTINCT p.submission_image_size),0) AS file_size,
                                            CONCAT_WS(' ',s.first_name,s.last_name) AS user_name,
                                            p.submission_image_size
                                            FROM homework p
                                            INNER JOIN tblstudent s ON s.id = p.student_id AND s.sub_institute_id = p.sub_institute_id
                                            WHERE p.sub_institute_id = '".$sub_institute_id."' AND s.id = '".$user_id."' ");
            }else{
				$homework_submission_data = DB::select("SELECT SUM(IFNULL(submission_image_size,0)) as file_size FROM homework WHERE sub_institute_id = '" . $sub_institute_id . "' AND created_by = '".$user_id."' ");
			}	
			$homework_submission_size = 0;
			if(count($homework_submission_data) > 0)
			{
				$homework_submission_size = $homework_submission_data[0]->file_size;
			}

			$get_frontdesk_data = DB::select("SELECT SUM(IFNULL(l.FILE_SIZE,0)) AS file_size
                                    FROM front_desk l                
                                    WHERE l.SUB_INSTITUTE_ID = '".$sub_institute_id."' AND l.CREATED_BY = '".$user_id."' ");
			$frontdesk_size = 0;
			if($get_frontdesk_data[0]->file_size != 0)
			{
				$frontdesk_size = $get_frontdesk_data[0]->file_size;
			}

			$get_task_data = DB::select("SELECT SUM(IFNULL(l.FILE_SIZE,0)) AS file_size
                                    FROM task l                
                                    WHERE l.SUB_INSTITUTE_ID = '".$sub_institute_id."' AND l.CREATED_BY = '".$user_id."' ");
			$task_size = 0;
			if($get_task_data[0]->file_size != 0)
			{
				$task_size = $get_task_data[0]->file_size;
			}

			$get_complaint_data = DB::select("SELECT SUM(IFNULL(l.FILE_SIZE,0)) AS file_size
                                    FROM complaint l                
                                    WHERE l.SUB_INSTITUTE_ID = '".$sub_institute_id."' AND l.COMPLAINT_BY = '".$user_id."' ");
			$complaint_size = 0;
			if($get_complaint_data[0]->file_size != 0)
			{
				$complaint_size = $get_complaint_data[0]->file_size;
			}

            $student_size = 0;
			if($user_profile_name == 'Student')
            {
                $student_data = DB::select("SELECT IFNULL(SUM(s.file_size),0) AS file_size
                                            FROM tblstudent s
                                            WHERE s.sub_institute_id = '".$sub_institute_id."' AND s.id = '".$user_id."' ");
				if(count($student_data) > 0)
				{
					$student_size = $student_data[0]->file_size;
				}
            }


			$get_student_health_data = DB::select("SELECT SUM(IFNULL(l.file_size,0)) AS file_size
                                    FROM student_health l                
                                    WHERE l.sub_institute_id = '".$sub_institute_id."' AND l.created_by = '".$user_id."' ");
			$student_health_size = 0;
			if($get_student_health_data[0]->file_size != 0)
			{
				$student_health_size = $get_student_health_data[0]->file_size;
			}

			// echo 'petty_cash_size : '.$petty_cash_size.'<br>';
			// echo 'photo_video_size : '.$photo_video_size.'<br>';
			// echo 'leave_app_size : '.$leave_app_size.'<br>';
			// echo 'exam_schedule_size : '.$exam_schedule_size.'<br>';
			// echo 'homework_size : '.$homework_size.'<br>';
			// echo 'homework_submission_size : '.$homework_submission_size.'<br>';
			// die;
			$total_used_size = $petty_cash_size + $photo_video_size + $leave_app_size + $exam_schedule_size + $homework_size + $homework_submission_size + $frontdesk_size + $task_size + $complaint_size + $student_size + $student_health_size;
			$used_space_in_MB = $this->formatBytes($total_used_size);
			$explod_used_space = explode(' ', $used_space_in_MB);
			$occupied_space_in_MB = $school_setup_data['given_space_mb'];
			$available_space_in_MB = ($occupied_space_in_MB - $explod_used_space[0]);

			$res['occupied_space_in_MB'] = $occupied_space_in_MB;
            $res['used_space_in_MB'] = $used_space_in_MB;
            $res['available_space_in_MB'] = $available_space_in_MB;
			//END Code for calculate used space using folder-wise table  
            // dd($res);
			return is_mobile($type, "teacher_home", $res, "view");
		}
	}

	public function chart(Request $request) {

		// dd(session()->all());

		$type = $request->input('type');
		$sub_institute_id = $request->session()->get('sub_institute_id');
		$syear = $request->session()->get('syear');
		$user_profile_name = $request->session()->get("user_profile_name");

		if ($user_profile_name == 'Admin' || $user_profile_name == 'ADMIN' || $user_profile_name == 'admin' || $user_profile_name == 'school admin' 
			|| $user_profile_name == 'SCHOOL ADMIN' || $user_profile_name == 'School Admin') {

			// if ($sub_institute_id == 46) {
				// $date = "2019-08-22"; //date('Y-m-d');
			// } else {
				$date = date('Y-m-d');
			// }
			$date15 = date('Y-m-d', strtotime($date . ' +15 day'));

			$users = tbluserModel::selectRaw("count(id) as users")->where(['sub_institute_id' => $sub_institute_id, 'status' => "1"])->get()->toArray();

			// $students = tblstudentModel::selectRaw("count(id) as students")->where(['sub_institute_id' => $sub_institute_id])->get()->toArray();

			$students = DB::select("SELECT COUNT(ts.id) students FROM tblstudent ts
INNER JOIN tblstudent_enrollment se ON se.student_id = ts.id AND se.sub_institute_id = se.sub_institute_id
INNER JOIN standard s ON s.id = se.standard_id AND se.sub_institute_id = s.sub_institute_id
WHERE ts.sub_institute_id = '".$sub_institute_id."' AND se.syear = '".$syear."' AND se.end_date IS NULL");
			// dd($students[0]->students);

			$total_admission = DB::select("SELECT COUNT(id) as total_admissions FROM admission_enquiry 
				WHERE syear = '".$syear."' AND sub_institute_id = '".$sub_institute_id."' ");

			$fees_collects = fees_collect::selectRaw("ifnull(sum(amount),0) as fees")->where(['sub_institute_id' => $sub_institute_id,'syear' => $syear, 'is_deleted' => "N"])
				->whereRaw("date_format(created_date,'%Y-%m-%d') = '" . $date . "'")->get()->toArray();

			$other_fees_collects = DB::select("SELECT IFNULL(SUM(actual_amountpaid),0) AS fees
												FROM fees_paid_other
												WHERE (`sub_institute_id` = '".$sub_institute_id."' AND `syear` = '".$syear."' ) 
												AND DATE_FORMAT(created_date,'%Y-%m-%d') = '" . $date . "' AND is_deleted = 'N' ");	
			$other_fees_collects = json_decode(json_encode($other_fees_collects),true);	

			$parentCommunication = DB::select("SELECT p.*,CONCAT_WS(' ',s.first_name,s.last_name) as student_name,s.image as student_image
			FROM parent_communication p INNER JOIN tblstudent s on p.student_id = s.id WHERE date_ = '" . $date . "' AND p.sub_institute_id = '" . $sub_institute_id . "' order by p.id desc limit 10");

			$fees_collection = fees_collect::selectRaw('fees_collect.*,CONCAT_WS(" ",tblstudent.first_name,tblstudent.middle_name,tblstudent.last_name) 
				as student_name,sum(amount) as total_fees')
			->join('tblstudent', 'tblstudent.id', '=', 'fees_collect.student_id')
			->where(['fees_collect.sub_institute_id' => $sub_institute_id, 'fees_collect.is_deleted' => "N"])
				->whereRaw("date_format(fees_collect.created_date,'%Y-%m-%d') = '" . $date . "'")
				->groupBy('payment_mode')
				->take(10)->get()->toArray();

			$studentBirthdayQuery = "SELECT CONCAT_WS(' ',s.first_name,s.middle_name,s.last_name) as student_name,st.name as standard_name,d.name as division_name, DATE_FORMAT(s.dob, '%d-%m-%Y') AS dob
			FROM tblstudent s 
			INNER JOIN tblstudent_enrollment ts on s.id = ts.student_id and ts.syear = '" . $syear . "' 
			INNER JOIN standard st on ts.standard_id = st.id INNER JOIN division d on ts.section_id = d.id 
			WHERE s.sub_institute_id = '" . $sub_institute_id . "'and ts.end_date is null  and DATE_FORMAT(s.dob, '%m-%d') >= DATE_FORMAT(NOW(), '%m-%d') and DATE_FORMAT(s.dob, '%m-%d') <= DATE_FORMAT((NOW() + INTERVAL +7 DAY), '%m-%d')";

			$studentBirthdays = DB::select($studentBirthdayQuery);

			$teacherBirthdayQuery = "SELECT CONCAT_WS(' ',s.first_name,s.middle_name,s.last_name) as teacher_name,tu.name as designation,s.mobile as contact_number, DATE_FORMAT(s.birthdate, '%d-%m-%Y') AS birthdate
			FROM tbluser s 
			INNER JOIN tbluserprofilemaster tu on s.user_profile_id = tu.id 
			WHERE s.sub_institute_id = '" . $sub_institute_id . "' AND s.status !=0 and date_format(s.birthdate,'%m-%d') >= DATE_FORMAT(NOW(), '%m-%d') and DATE_FORMAT(s.birthdate, '%m-%d') <= DATE_FORMAT((NOW() + INTERVAL +7 DAY), '%m-%d')
			ORDER BY DATE_FORMAT(s.birthdate, '%d')";
			$teacherBirthdays = DB::select($teacherBirthdayQuery);

			$calendarEventsQuery = "SELECT *
		FROM calendar_events where sub_institute_id = '" . $sub_institute_id . "' and school_date >= '" . $date . "' AND school_date <= '" . $date15 . "'";

			$calendarEvents = DB::select($calendarEventsQuery);

			$studentLeave = "SELECT l.*, CONCAT_WS(' ',s.first_name,s.middle_name,s.last_name) AS student_name,st.name AS standard_name,dt.name AS division_name
		FROM leave_applications l
		INNER JOIN tblstudent s ON l.student_id = s.id
		INNER JOIN tblstudent_enrollment se ON s.id = se.id AND se.syear = '" . $syear . "'
		INNER JOIN standard st ON st.id = se.standard_id
		INNER JOIN division1 dt ON dt.id = se.section_id
		WHERE l.sub_institute_id = '" . $sub_institute_id . "' AND '" . $date . "' BETWEEN from_date AND to_date";

			$studentLeaves = DB::select($studentLeave);

			$standards_att = array();
			$absents = array();
			$presants = array();

			$attendanceCharts = "SELECT st.name as standard,dt.name,s.attendance_code, SUM(CASE WHEN s.attendance_code = 'A' THEN 1 ELSE 0 END) AS absent, 
			SUM(CASE WHEN s.attendance_code = 'P' THEN 1 ELSE 0 END) AS present
		FROM attendance_student s
		INNER JOIN standard st ON s.standard_id = st.id
		INNER JOIN division dt ON s.section_id = dt.id
		WHERE s.sub_institute_id = '" . $sub_institute_id . "' AND s.attendance_date = '" . $date . "'
		GROUP BY s.standard_id";

			$attendanceCharts = DB::select($attendanceCharts);

			foreach ($attendanceCharts as $key => $value) {
				// $standards = "'".$value->standard."',";
				$standards_att[] = $value->standard;
				$absents[] = (int) $value->absent;
				$presants[] = (int) $value->present;
			}

			$today = date("Y-m-d");
			$parameters = array(
				":dt" => $today,
				":sb" => $sub_institute_id,
				":syear" => $syear,
			);
			$fees_chart_data = DB::select('select sum(fc.amount) amount,s.name
        from fees_collect fc
        inner join tblstudent_enrollment se on se.student_id = fc.student_id and se.syear = :syear
        inner join standard s on s.id = se.standard_id
        where DATE_FORMAT(fc.created_date, "%Y-%m-%d") = :dt and fc.sub_institute_id = :sb group by se.standard_id', $parameters);

			$parameters = array(
				":syear" => $syear,
				":sb" => $sub_institute_id,
			);
			$student_chart_data = DB::select('select count(se.student_id) total_student,s.name
        from tblstudent_enrollment se
        inner join standard s on s.id = se.standard_id
        where se.sub_institute_id = :sb  and se.syear = :syear
        group by se.standard_id,s.id
        order by s.sort_order
        ', $parameters);
			// echo ('<pre>');print_r($fees_chart_data);
			// echo ('<pre>');print_r($student_chart_data);exit;

			$total_fees = 0;
			$total_student = 0;
			$final_chart_data = " [{
            'id': '0.0',
            'parent': '',
            'name': 'Main Chart'
        }, {
            id: '1.1',
            parent: '0.0',
            name: 'Fees'
        }, {
            id: '1.2',
            parent: '0.0',
            name: 'Student'
        }, ";

			foreach ($fees_chart_data as $key => $value) {
				$total_fees = $total_fees + $value->amount;
				$final_chart_data .= "{
                'id': '2." . $key . "',
                'parent': '1.1',
                'name': '" . $value->name . "',
                'value':" . $value->amount . "
            },";
			}
			if (isset($next_id)) {
				$next_id = $key + 1;
			} else {
				$next_id = 0;
			}
			// echo('<pre>');
			// print_r($next_id);
			// exit;
			foreach ($student_chart_data as $key => $value) {
				$total_student = $total_student + $value->total_student;
				$ids = $next_id + $key;
				$final_chart_data .= "{
                'id': '2." . $ids . "',
                'parent': '1.2',
                'name': '" . $value->name . "',
                'value':" . $value->total_student . "
            },";
			}
			$final_chart_data = rtrim($final_chart_data, ",");
			$final_chart_data .= '];';

			$today = date("Y-m-d");
			$parameters = array(
				":dt" => $today,
				":sb" => $sub_institute_id,
				":syear" => $syear,
				":mode" => "cash",
			);
			$fees_chart1_cash_data = DB::select('select fc.amount,s.name
        from fees_collect fc
        inner join tblstudent_enrollment se on se.student_id = fc.student_id and se.syear = :syear
        inner join standard s on s.id = se.standard_id
        where DATE_FORMAT(fc.created_date, "%Y-%m-%d") = :dt
        and fc.sub_institute_id = :sb and payment_mode = :mode group by se.standard_id', $parameters);
			// echo ('<pre>');print_r($fees_chart1_cash_data);exit;
			$today = date("Y-m-d");
			$parameters = array(
				":dt" => $today,
				":sb" => $sub_institute_id,
				":syear" => $syear,
				":mode" => "cheque",
			);
			$fees_chart1_cheque_data = DB::select('select fc.amount,s.name
        from fees_collect fc
        inner join tblstudent_enrollment se on se.student_id = fc.student_id and se.syear = :syear
        inner join standard s on s.id = se.standard_id
        where DATE_FORMAT(fc.created_date, "%Y-%m-%d") = :dt
        and fc.sub_institute_id = :sb and payment_mode = :mode', $parameters);

			$final_chart1_data = " [{
            'id': '0.0',
            'parent': '',
            'name': 'Cash/Cheque Chart'
        }, {
            id: '1.1',
            parent: '0.0',
            name: 'Cash Fees'
        }, {
            id: '1.2',
            parent: '0.0',
            name: 'Cheque Fees'
        }, ";

        

			foreach ($fees_chart1_cash_data as $key => $value) {
				// $total_fees = $total_fees + $value->amount;
				$final_chart1_data .= "{
                'id': '2." . $key . "',
                'parent': '1.1',
                'name': '" . $value->name . "',
                'value':" . $value->amount . "
            },";
			}
			
			if (isset($next_id)) {
				$next_id = $key + 1;
			} else {
				$next_id = 0;
			}

			// $next_id = $key + 1;
			// echo('<pre>');
			// print_r($next_id);
			// exit;
			foreach ($fees_chart1_cheque_data as $key => $value) {
				// $total_student = $total_student + $value->total_student;
				$ids = $next_id + $key;
				$final_chart1_data .= "{
                'id': '2." . $ids . "',
                'parent': '1.2',
                'name': '" . $value->name . "',
                'value':" . $value->amount . "
            },";
			}
			$final_chart1_data = rtrim($final_chart1_data, ",");
			$final_chart1_data .= '];';

			$fees_chart2_bkoff_data = DB::select('SELECT SUM(fb.amount) amt,st.name
        FROM tblstudent s
        INNER JOIN tblstudent_enrollment se ON se.student_id = s.id
        INNER JOIN academic_section g ON g.id = se.grade_id
        INNER JOIN standard st ON st.id = se.standard_id
        LEFT JOIN division d ON d.id = se.section_id
        INNER JOIN fees_breackoff fb ON
         (fb.syear = "' . $syear . '" AND
         fb.admission_year = s.admission_year AND
         fb.quota = se.student_quota AND
         fb.grade_id = se.grade_id AND
         fb.standard_id = se.standard_id AND
         fb.sub_institute_id = "' . $sub_institute_id . '"
        )
        WHERE s.sub_institute_id = "' . $sub_institute_id . '" AND se.syear = "' . $syear . '"
        GROUP BY st.id ORDER BY st.id');
			// echo('<pre>');
			// print_r($fees_chart2_bkoff_data);
			// exit;

			$unpaid_data = "[";
			$std_data = "[";
			foreach ($fees_chart2_bkoff_data as $id => $arr) {
				$unpaid_data .= $arr->amt . ",";
				$std_data .= "'" . $arr->name . "'" . ",";
			}
			$unpaid_data = rtrim($unpaid_data, ",");
			$std_data = rtrim($std_data, ",");
			$unpaid_data .= "]";
			$std_data .= "]";

			// echo('<pre>');
			// print_r($unpaid_data);
			// print_r($std_data);
			// exit;

			$fees_chart2_fees_data = DB::select('SELECT SUM(fc.amount)+ SUM(fc.fees_discount) amount,st.name
        FROM tblstudent s
        INNER JOIN tblstudent_enrollment se ON se.student_id = s.id
        INNER JOIN academic_section g ON g.id = se.grade_id
        INNER JOIN standard st ON st.id = se.standard_id
        LEFT JOIN division d ON d.id = se.section_id
        INNER JOIN fees_collect fc ON
         (
         fc.student_id = s.id AND
         fc.sub_institute_id = "' . $sub_institute_id . '" AND
         fc.syear = "' . $syear . '"
        )
        WHERE s.sub_institute_id = "' . $sub_institute_id . '"
        GROUP BY st.id ORDER BY st.id');

			// echo('<pre>');
			// print_r($fees_chart2_fees_data);
			// exit;

			$paid_data = "[";
			foreach ($fees_chart2_fees_data as $id => $arr) {
				$paid_data .= $arr->amount . ",";
			}
			$paid_data = rtrim($paid_data, ",");
			$paid_data .= "]";
			// $paid_data .= "]";
			// echo('<pre>');
			// print_r($paid_data);
			// exit;
			// echo $final_chart_data;
			// echo ('<pre>');print_r($final_chart1_data);exit;

			// echo ('<pre>');print_r($fees_chart_data);exit;

			// if($standards != '')
			// {
			// 	$standards = rtrim($standards,",");
			// }

			// $value = array(
			//     array(
			//     'id'=>'1',
			//     'parent'=>'2'
			//     ),
			//     array(
			//     'id'=>'1',
			//     'parent'=>'2'
			//     )
			//     );

			// $result = json_encode($value);

			$academicSections = DB::select("select * from academic_section where sub_institute_id = '" . $sub_institute_id . "'");

			$academicSections = array_map(function ($value) {
				return (array) $value;
			}, $academicSections);

			$gradeIds = '';
			foreach ($academicSections as $key => $value) {
				$gradeIds .= $value['id'] . ',';
			}

			$standards = DB::select("select * from standard where grade_id IN (" . rtrim($gradeIds, ",") . ") ");

			$standards = array_map(function ($value) {
				return (array) $value;
			}, $standards);

			$standardsArray = array();

			foreach ($standards as $key => $value) {
				$standardsArray[$value['grade_id']][] = $standards[$key];
			}

			$chartStudents = DB::select("SELECT s.id,se.grade_id,se.standard_id
		FROM tblstudent s
		INNER JOIN tblstudent_enrollment se ON s.id = se.student_id
		WHERE s.sub_institute_id = '" . $sub_institute_id . "' and grade_id != '' and standard_id != ''");

			// dd($chartStudents);
			$chartAS = array();
			$chartS = array();

			foreach ($chartStudents as $k => $v) {
				$chartAs[$v->grade_id][] = $v->id;
				$chartS[$v->standard_id][] = $v->id;
			}

			$chartFAs = array();
			$chartFS = array();

			$chartFees = DB::select("SELECT fc.amount,s.name,se.grade_id,se.standard_id
		FROM fees_collect fc
		INNER JOIN tblstudent_enrollment se ON se.student_id = fc.student_id AND se.syear = '" . $syear . "'
		INNER JOIN standard s ON s.id = se.standard_id
		WHERE fc.sub_institute_id = '" . $sub_institute_id . "'");

			foreach ($academicSections as $key => $value) {
				$chartFAs[$value['id']] = 0;
			}

			foreach ($standards as $key => $value) {
				$chartFS[$value['id']] = 0;
			}
			$totalFeesCF = 0;
			foreach ($chartFees as $k => $v) {
				$chartFAs[$v->grade_id] += $v->amount;
				$chartFS[$v->standard_id] += $v->amount;
				$totalFeesCF += $v->amount;
			}

			$chart = "[{
    id: '0.0',
    parent: '',
    name: 'Triz ERP',
    value: " . $students[0]->students . ",
    label: " . $students[0]->students . "
}, {
    id: '1.3',
    parent: '0.0',
    name: 'Student',
    value: " . $students[0]->students . ",
    label: " . $students[0]->students . ",
    events: {click: function (event) {alertValue('Student');}}
}, {
    id: '1.1',
    parent: '0.0',
    name: 'Fees',
    value: " . $students[0]->students . ",
    label: " . $totalFeesCF . ",
    events: {click: function (event) {alertValue('Fees');}}
}, {
    id: '1.2',
    parent: '0.0',
    name: 'Admission',
    value: " . $students[0]->students . ",
    label: " . $students[0]->students . ",
    events: {click: function (event) {alertValue('Admission');}}
}, {
    id: '1.4',
    parent: '0.0',
    name: 'Attendance',
    value: " . $students[0]->students . ",
    label: " . $students[0]->students . ",
    events: {click: function (event) {alertValue('Attendance');}}
}, {
    id: '1.5',
    parent: '0.0',
    name: 'Homework',
    value: " . $students[0]->students . ",
    label: " . $students[0]->students . ",
    events: {click: function (event) {alertValue('Homework');}}
},";

			$j = 6;
			$child = 1;
			$childL = 1;
			foreach ($academicSections as $k => $v) {
				if (isset($chartFAs[$v['id']])) {
					$ca = $chartFAs[$v['id']];
				} else {
					$ca = 0;
				}
				$chart .= "{id: '2." . $child . "',
    parent: '1.1',
    name: '" . $v['short_name'] . "',
    value: " . $ca . ",
    label: " . $ca . ",
    events: {click: function (event) {alertValue('Fees');}}
},";

				$childP = 1;
				$value = $ca / count($standardsArray[$v['id']]);
				foreach ($standardsArray[$v['id']] as $ke => $va) {
					if (isset($chartFS[$va['id']])) {
						$cs = $chartFS[$va['id']];
					} else {
						$cs = 0;
					}
					$j++;
					$chart .= "{id: '3." . $childL . $childP . "',
				    parent: '2." . $child . "',
				    name: '" . $va['short_name'] . "',
				    value: " . $cs . ",
				    label: " . $cs . ",
				    events: {click: function (event) {alertValue('Fees');}}
				},";
					$childP++;

				}
				$child++;
				$childL++;
				$j++;
			}

			$child = 1;
			$childL = 1;
			foreach ($academicSections as $k => $v) {
				if (isset($chartAs[$v['id']])) {
					$ca = count($chartAs[$v['id']]);
				} else {
					$ca = 0;
				}
				$chart .= "{id: '3." . $child . "',
    parent: '1.2',
    name: '" . $v['short_name'] . "',
    value: " . $ca . ",
    label: " . $ca . ",
    events: {click: function (event) {alertValue('Admission');}}
},";

				$childP = 1;
				$value = $ca / count($standardsArray[$v['id']]);
				foreach ($standardsArray[$v['id']] as $ke => $va) {
					if (isset($chartS[$va['id']])) {
						$cs = count($chartS[$va['id']]);
					} else {
						$cs = 0;
					}
					$j++;
					$chart .= "{id: '4." . $childL . $childP . "',
				    parent: '3." . $child . "',
				    name: '" . $va['short_name'] . "',
				    value: " . $cs . ",
				    label: " . $cs . ",
				    events: {click: function (event) {alertValue('Admission');}}
				},";
					$childP++;

				}
				$child++;
				$childL++;
				$j++;
			}

			$child = 1;
			$childL = 1;
			foreach ($academicSections as $k => $v) {
				if (isset($chartAs[$v['id']])) {
					$ca = count($chartAs[$v['id']]);
				} else {
					$ca = 0;
				}
				$chart .= "{id: '4." . $child . "',
    parent: '1.3',
    name: '" . $v['short_name'] . "',
    value: " . $ca . ",
    label: " . $ca . ",
    events: {click: function (event) {alertValue('Student');}}
},";

				$childP = 1;
				$value = $ca / count($standardsArray[$v['id']]);
				foreach ($standardsArray[$v['id']] as $ke => $va) {
					if (isset($chartS[$va['id']])) {
						$cs = count($chartS[$va['id']]);
					} else {
						$cs = 0;
					}
					$j++;
					$chart .= "{id: '5." . $childL . $childP . "',
				    parent: '4." . $child . "',
				    name: '" . $va['short_name'] . "',
				    value: " . $cs . ",
				    label: " . $cs . ",
				    events: {click: function (event) {alertValue('Student');}}
				},";
					$childP++;

				}
				$child++;
				$childL++;
				$j++;
			}

			$child = 1;
			$childL = 1;
			foreach ($academicSections as $k => $v) {
				if (isset($chartAs[$v['id']])) {
					$ca = count($chartAs[$v['id']]);
				} else {
					$ca = 0;
				}
				$chart .= "{id: '5." . $child . "',
    parent: '1.4',
    name: '" . $v['short_name'] . "',
    value: " . $ca . ",
    label: " . $ca . ",
    events: {click: function (event) {alertValue('Attendance');}}
},";

				$childP = 1;
				$value = $ca / count($standardsArray[$v['id']]);
				foreach ($standardsArray[$v['id']] as $ke => $va) {
					if (isset($chartS[$va['id']])) {
						$cs = count($chartS[$va['id']]);
					} else {
						$cs = 0;
					}
					$j++;
					$chart .= "{id: '6." . $childL . $childP . "',
				    parent: '5." . $child . "',
				    name: '" . $va['short_name'] . "',
				    value: " . $cs . ",
				    label: " . $cs . ",
				    events: {click: function (event) {alertValue('Attendance');}}
				},";
					$childP++;

				}
				$child++;
				$childL++;
				$j++;
			}

			$child = 1;
			$childL = 1;
			foreach ($academicSections as $k => $v) {
				if (isset($chartAs[$v['id']])) {
					$ca = count($chartAs[$v['id']]);
				} else {
					$ca = 0;
				}
				$chart .= "{id: '6." . $child . "',
    parent: '1.5',
    name: '" . $v['short_name'] . "',
    value: " . $ca . ",
    label: " . $ca . ",
    events: {click: function (event) {alertValue('Homework');}}
},";

				$childP = 1;
				$value = $ca / count($standardsArray[$v['id']]);
				foreach ($standardsArray[$v['id']] as $ke => $va) {
					if (isset($chartS[$va['id']])) {
						$cs = count($chartS[$va['id']]);
					} else {
						$cs = 0;
					}
					$j++;
					$chart .= "{id: '7." . $childL . $childP . "',
				    parent: '6." . $child . "',
				    name: '" . $va['short_name'] . "',
				    value: " . $cs . ",
				    label: " . $cs . ",
				    events: {click: function (event) {alertValue('Homework');}}
				},";
					$childP++;

				}
				$child++;
				$childL++;
				$j++;
			}

			$chart = rtrim($chart, ",");

			$chart .= "]";

			$res['status_code'] = 1;
			$res['message'] = "Success";
			$res['totalUser'] = $users[0]['users'];
			$res['totalStudent'] = $students[0]->students;
			$res['totalFees'] = ($fees_collects[0]['fees'] + $other_fees_collects[0]['fees']);
			$res['totalAdmission'] = $total_admission[0]->total_admissions;
			$res['parentCommunications'] = $parentCommunication;
			$res['recentFeesCollection'] = $fees_collection;
			$res['studentBirthdays'] = $studentBirthdays;
			$res['teacherBirthdays'] = $teacherBirthdays;
			$res['calendarEvents'] = $calendarEvents;

			$res['studentLeaves'] = $studentLeaves;

			$res['standardsJson'] = json_encode($standards_att, true);
			$res['absentsJson'] = json_encode($absents, true);
			$res['presantsJson'] = json_encode($presants, true);
			$res['chartData'] = $final_chart_data;
			$res['unpaid_fees_data'] = $unpaid_data;
			$res['paid_fee_data'] = $paid_data;
			$res['std_data'] = $std_data;
			$res['chart1Data'] = $final_chart1_data;
			$res['chart'] = $chart;

			return is_mobile($type, "chart_home", $res, "view");
		} else {

			// if ($sub_institute_id == 46) {
			// 	$date = "2019-08-22"; //date('Y-m-d');
			// } else {
				$date = date('Y-m-d');
			// }

			$date15 = date('Y-m-d', strtotime($date . ' +15 day'));

			$users = tbluserModel::selectRaw("count(id) as users")->where(['sub_institute_id' => $sub_institute_id, 'status' => "1"])->get()->toArray();

			// $students = tblstudentModel::selectRaw("count(id) as students")->where(['sub_institute_id' => $sub_institute_id])->get()->toArray();

			$students = DB::select("SELECT COUNT(ts.id) students FROM tblstudent ts
INNER JOIN tblstudent_enrollment se ON se.student_id = ts.id AND se.sub_institute_id = se.sub_institute_id
INNER JOIN standard s ON s.id = se.standard_id AND se.sub_institute_id = s.sub_institute_id
WHERE ts.sub_institute_id = '".$sub_institute_id."' AND se.syear = '".$syear."' AND se.end_date IS NULL");
			// dd($students[0]->students);

			$total_admission = DB::select("SELECT COUNT(id) as total_admissions FROM admission_enquiry 
				WHERE syear = '".$syear."' AND sub_institute_id = '".$sub_institute_id."' ");

			$fees_collects = fees_collect::selectRaw("ifnull(sum(amount),0) as fees")->where(['sub_institute_id' => $sub_institute_id,'syear' => $syear, 'is_deleted' => "N"])
				->whereRaw("date_format(created_date,'%Y-%m-%d') = '" . $date . "'")->get()->toArray();

			$other_fees_collects = DB::select("SELECT IFNULL(SUM(actual_amountpaid),0) AS fees
												FROM fees_paid_other
												WHERE (`sub_institute_id` = '".$sub_institute_id."' AND `syear` = '".$syear."' ) 
												AND DATE_FORMAT(created_date,'%Y-%m-%d') = '" . $date . "' AND is_deleted = 'N' ");	
			$other_fees_collects = json_decode(json_encode($other_fees_collects),true);	

			$parentCommunication = DB::select("SELECT p.*,CONCAT_WS(' ',s.first_name,s.last_name) as student_name,s.image as student_image
			FROM parent_communication p INNER JOIN tblstudent s on p.student_id = s.id WHERE date_ = '" . $date . "' AND p.sub_institute_id = '" . $sub_institute_id . "' order by p.id desc limit 10");

			$studentBirthdayQuery = "SELECT CONCAT_WS(' ',s.first_name,s.middle_name,s.last_name) as student_name,st.name as standard_name,
			d.name as division_name 
			FROM tblstudent s 
			INNER JOIN tblstudent_enrollment ts on s.id = ts.student_id and ts.syear = '" . $syear . "' 
			INNER JOIN standard st on ts.standard_id = st.id INNER JOIN division d on ts.section_id = d.id 
			WHERE s.sub_institute_id = '" . $sub_institute_id . "' and ts.end_date is null and DATE_FORMAT(s.dob, '%m-%d') >= DATE_FORMAT(NOW(), '%m-%d') and DATE_FORMAT(s.dob, '%m-%d') <= DATE_FORMAT((NOW() + INTERVAL +7 DAY), '%m-%d')
			ORDER BY DATE_FORMAT(s.dob, '%d')";

			$studentBirthdays = DB::select($studentBirthdayQuery);

			$teacherBirthdayQuery = "SELECT CONCAT_WS(' ',s.first_name,s.middle_name,s.last_name) as teacher_name,tu.name as designation,s.mobile as contact_number, DATE_FORMAT(s.birthdate, '%d-%m-%Y') AS birthdate
			FROM tbluser s 
			INNER JOIN tbluserprofilemaster tu on s.user_profile_id = tu.id 
			WHERE s.sub_institute_id = '" . $sub_institute_id . "' AND s.status !=0 and date_format(s.birthdate,'%m-%d') >= DATE_FORMAT(NOW(), '%m-%d') and DATE_FORMAT(s.birthdate, '%m-%d') <= DATE_FORMAT((NOW() + INTERVAL +7 DAY), '%m-%d')
			ORDER BY DATE_FORMAT(s.birthdate, '%d')";
			$teacherBirthdays = DB::select($teacherBirthdayQuery);

			$calendarEventsQuery = "SELECT *
		FROM calendar_events where sub_institute_id = '" . $sub_institute_id . "' and school_date >= '" . $date . "' AND school_date <= '" . $date15 . "'";

			$calendarEvents = DB::select($calendarEventsQuery);

			$res['totalUser'] = $users[0]['users'];
			$res['totalStudent'] = $students[0]->students;
			$res['totalFees'] = ($fees_collects[0]['fees'] + $other_fees_collects[0]['fees']);
			$res['totalAdmission'] = $total_admission[0]->total_admissions;
			$res['parentCommunications'] = $parentCommunication;
			$res['studentBirthdays'] = $studentBirthdays;
			$res['teacherBirthdays'] = $teacherBirthdays;
			$res['calendarEvents'] = $calendarEvents;

			return is_mobile($type, "teacher_home", $res, "view");
		}
	}

	/**
	 * Show the form for creating a new resource.
	 *
	 * @return \Illuminate\Http\Response
	 */
	public function create() {
		//
	}

	/**
	 * Store a newly created resource in storage.
	 *
	 * @param  \Illuminate\Http\Request  $request
	 * @return \Illuminate\Http\Response
	 */
	public function store(Request $request) {
		//
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

	public function siteMap(Request $request) {
		$type = $request->input('type');
		$sub_institute_id = $request->session()->get('sub_institute_id');
		$user_id = $request->session()->get('user_id');

		$rightsQuery = "SELECT GROUP_CONCAT(distinct m.id) AS MID
FROM tbluser u LEFT JOIN tblindividual_rights i ON u.id = i.user_id AND u.sub_institute_id = i.sub_institute_id 
LEFT JOIN tblgroupwise_rights g ON u.user_profile_id = g.profile_id AND u.sub_institute_id = g.sub_institute_id 
INNER JOIN tblmenumaster m ON (i.menu_id = m.id OR g.menu_id = m.id) AND FIND_IN_SET(" . $sub_institute_id . ", m.sub_institute_id) 
WHERE u.sub_institute_id = '" . $sub_institute_id . "' AND u.id = '" . $user_id . "'";

		$rightsQuery = DB::select($rightsQuery);

		$rightsQuery = array_map(function ($value) {
			return (array) $value;
		}, $rightsQuery);

		$rightsMenusIds = 0;

		if (isset($rightsQuery['0']['MID'])) {
			$rightsMenusIds = $rightsQuery['0']['MID'];
		}
		$rightsMenusIds = rtrim($rightsMenusIds, ',');//RAJESH
		$data = tblmenumasterModel::where(['parent_menu_id' => "0", 'level' => "1"])->whereRaw("find_in_set('$sub_institute_id',sub_institute_id) 
			and status = 1 and id in (" . $rightsMenusIds . ") ")->orderBy('sort_order')->get()->toArray();
		//        $subMenuData = tblmenumasterModel::where('parent_menu_id', '!=' , 0)->whereIn('sub_institute_id', [$user_id])->get()->toArray();
		$subMenuData = tblmenumasterModel::where('parent_menu_id', '!=', 0)->whereRaw("find_in_set('$sub_institute_id',sub_institute_id) 
			AND level = 2 and id in (" . $rightsMenusIds . ") and status = 1 ")->orderBy('sort_order')->get()->toArray();
		//         dd($subMenuData);
		$i = 0;
		foreach ($subMenuData as $key => $value) {
			$finalSubMenu[$value['parent_menu_id']][$i] = $subMenuData[$key];
			$i++;
		}

		$subChildMenuData = tblmenumasterModel::where('parent_menu_id', '!=', 0)->whereRaw("find_in_set('$sub_institute_id',sub_institute_id) 
			AND level = 3 and id in (" . $rightsMenusIds . ") and status = 1 ")->orderBy('sort_order')->get()->toArray();
		$i = 0;
		foreach ($subChildMenuData as $key => $value) {
			$finalSubChildMenu[$value['parent_menu_id']][$i] = $subChildMenuData[$key];
			$i++;
		}

		// view()->share('groupwisemenuMaster', $data);
		// if (isset($finalSubMenu)) {
		// 	view()->share('groupwisesubmenuMaster', $finalSubMenu);
		// }

		// if (isset($finalSubSubMenu)) {
		// 	view()->share('groupwiseSubsubmenuMaster', $finalSubChildMenu);
		// }

		$res['status_code'] = 1;
		$res['message'] = "Success";
		$res['groupwisemenuMaster'] = $data;
		$res['groupwisesubmenuMaster'] = $finalSubMenu;
		$res['groupwiseSubsubmenuMaster'] = $finalSubChildMenu;

		return is_mobile($type, 'sitemap', $res, 'view');
	}

	public function knowledge_base(Request $request)
	{
		$type = $request->input('type');

		$query = "select * from knowledge_base where status = 1";

		$data = DB::select($query);

		$data = array_map(function ($value) {
			return (array) $value;
		}, $data);

		$res['status_code'] = 1;
		$res['message'] = "Success";
		$res['data'] = $data;

		return is_mobile($type, "kb", $res, "view");
	}

	public function knowledge_base_detail(Request $request, $id, $title)
	{
		$type = $request->input('type');

		$query = "select kbd.*,kb.name as kname from knowledge_base_detail kbd INNER JOIN knowledge_base kb on kbd.kb_id = kb.id where kb.status = 1";

		$data = DB::select($query);

		$data = array_map(function ($value) {
			return (array) $value;
		}, $data);

		$res['status_code'] = 1;
		$res['message'] = "Success";
		$res['data'] = $data;

		return is_mobile($type, "kbd", $res, "view");
	}

	public function formatBytes($bytes, $precision = 2) { 
	    $units = array('B', 'KB', 'MB', 'GB', 'TB'); 

	    $bytes = max($bytes, 0); 
	    $pow = floor(($bytes ? log($bytes) : 0) / log(1024)); 
	    $pow = min($pow, count($units) - 1); 

	    // Uncomment one of the following alternatives
	    // $bytes /= pow(1024, $pow);
	    $bytes /= (1 << (10 * $pow)); 

	    return round($bytes, $precision) . ' ' . $units[$pow]; 
	    // return round($bytes, $precision); 
	}

}
