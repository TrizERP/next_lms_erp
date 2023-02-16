<?php

namespace App\Http\Controllers;

use App\Models\fees\fees_collect\fees_collect;
use App\Models\school_setup\SchoolModel;
use App\Models\student\tblstudentModel;
use App\Models\tblmenumasterModel;
use App\Models\user\tbluserModel;
use function App\Helpers\is_mobile;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class dashboardController extends Controller {
	/**
	 * Display a listing of the resource.
	 *
	 * @return \Illuminate\Http\Response
	 */
	public function index(Request $request) {
		// $data = session()->all();
		// dd($data);
		$type = $request->input('type');
		$sub_institute_id = $request->session()->get('sub_institute_id');
		$syear = $request->session()->get('syear');
		$user_profile_name = $request->session()->get("user_profile_name");
		$user_id = $request->session()->get("user_id");
		if ($sub_institute_id == 46) {
			$date = "2019-08-22"; //date('Y-m-d');
		} else {
			$date = date('Y-m-d');
		}
		$date15 = date('Y-m-d', strtotime($date . ' +15 day'));

		$users = tbluserModel::selectRaw("count(id) as users")->where(['sub_institute_id' => $sub_institute_id, 'status' => "1"])->get()->toArray();

		$user_image = tbluserModel::where(['id' => $user_id, 'status' => "1"])->get()->toArray();

		$student_image = tblstudentModel::where(['id' => $user_id, 'status' => "1"])->get()->toArray();

		$students = tblstudentModel::selectRaw("count(id) as students")->where(['sub_institute_id' => $sub_institute_id])->get()->toArray();

		$fees_collects = fees_collect::selectRaw("ifnull(sum(amount),0) as fees")->where(['sub_institute_id' => $sub_institute_id, 'is_deleted' => "N"])
			->whereRaw("date_format(created_date,'%Y-%m-%d') = '" . $date . "'")->get()->toArray();

		$parentCommunication = DB::select("SELECT p.*,CONCAT_WS(' ',s.first_name,s.last_name) as student_name,s.image as student_image
		FROM parent_communication p INNER JOIN tblstudent s on p.student_id = s.id WHERE date_ = '" . $date . "' AND p.sub_institute_id = '" . $sub_institute_id . "' order by p.id desc limit 10");

		$fees_collection = fees_collect::selectRaw('fees_collect.*,CONCAT_WS(" ",tblstudent.first_name,tblstudent.middle_name,tblstudent.last_name) as student_name,sum(amount) as total_fees')->join('tblstudent', 'tblstudent.id', '=', 'fees_collect.student_id')->where(['fees_collect.sub_institute_id' => $sub_institute_id, 'fees_collect.is_deleted' => "N"])
			->whereRaw("date_format(fees_collect.created_date,'%Y-%m-%d') = '" . $date . "'")
			->groupBy('payment_mode')
			->take(10)->get()->toArray();

		$studentBirthdayQuery = "SELECT CONCAT_WS(' ',s.first_name,s.middle_name,s.last_name) as student_name,st.name as standard_name,d.name as division_name FROM tblstudent s INNER JOIN tblstudent_enrollment ts on s.id = ts.student_id and ts.syear = '" . $syear . "' INNER JOIN standard st on ts.standard_id = st.id INNER JOIN division d on ts.section_id = d.id WHERE s.sub_institute_id = '" . $sub_institute_id . "' and date_format(dob,'%d-%m') = '" . date('d-m', strtotime($date)) . "'" AND st.end_date IS NULL;

		$studentBirthdays = DB::select($studentBirthdayQuery);

		$teacherBirthdayQuery = "SELECT CONCAT_WS(' ',s.first_name,s.middle_name,s.last_name) as teacher_name,tu.name as designation,s.mobile as contact_number FROM tbluser s INNER JOIN tbluserprofilemaster tu on s.user_profile_id = tu.id WHERE s.sub_institute_id = '" . $sub_institute_id . "' and date_format(s.birthdate,'%d-%m') = '" . date('d-m', strtotime($date)) . "'" AND s.status=1;

		$teacherBirthdays = DB::select($teacherBirthdayQuery);

		$calendarEventsQuery = "SELECT *
		FROM calendar_events where sub_institute_id = '" . $sub_institute_id . "' and school_date >= '" . $date . "' AND school_date <= '" . $date15 . "'";

		$calendarEvents = DB::select($calendarEventsQuery);

		$studentLeave = "SELECT l.*, CONCAT_WS(' ',s.first_name,s.middle_name,s.last_name) AS student_name,st.name AS standard_name,dt.name AS division_name
		FROM leave_applications l
		INNER JOIN tblstudent s ON l.student_id = s.id
		INNER JOIN tblstudent_enrollment se ON s.id = se.id AND se.syear = '" . $syear . "'
		INNER JOIN standard st ON st.id = se.standard_id
		INNER JOIN division dt ON dt.id = se.section_id
		WHERE l.sub_institute_id = '" . $sub_institute_id . "' AND '" . $date . "' BETWEEN from_date AND to_date";

		$studentLeaves = DB::select($studentLeave);

		$standards = array();
		$absents = array();
		$presants = array();

		$attendanceCharts = "SELECT st.name as standard,dt.name,s.attendance_code, SUM(CASE WHEN s.attendance_code = 'A' THEN 1 ELSE 0 END) AS absent, SUM(CASE WHEN s.attendance_code = 'P' THEN 1 ELSE 0 END) AS present
		FROM attendance_student s
		INNER JOIN standard st ON s.standard_id = st.id
		INNER JOIN division dt ON s.section_id = dt.id
		WHERE s.sub_institute_id = '" . $sub_institute_id . "' AND s.attendance_date = '" . $date . "'
		GROUP BY s.standard_id";

		$attendanceCharts = DB::select($attendanceCharts);

		foreach ($attendanceCharts as $key => $value) {
			// $standards = "'".$value->standard."',";
			$standards[] = $value->standard;
			$absents[] = (int) $value->absent;
			$presants[] = (int) $value->present;
		}

		$getAcademicTerms = DB::select("SELECT *
		FROM academic_year
		WHERE sub_institute_id = '" . $sub_institute_id . "' and syear = '" . $syear . "'");

		$getAcademicYear = DB::select("SELECT * FROM academic_year WHERE sub_institute_id = '" . $sub_institute_id . "' GROUP BY syear");

		$today = date("Y-m-d");
		$today = "2020-01-29";
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
		$today = "2020-01-29";
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
		$today = "2020-01-29";
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
        if(!isset($key))
        $key = 0;
		$next_id = $key + 1;
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
        // echo '<pre>'; print_r($final_chart1_data); exit;

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

		$data_of_ses = session()->all();
		
		$user_image[0]['image'] = '';
		if($user_image[0]['image'] != '')
		{
			$user_image[0]['image'] = $user_image[0]['image'];
		}
		if($user_profile_name == 'Student')
		{
			$user_image[0]['image'] = $student_image[0]['image'];
		}

		$res['status_code'] = 1;
		$res['message'] = "Success";
		$res['totalUser'] = $users[0]['users'];
		$res['userimage'] = $user_image[0]['image'];
		$res['totalStudent'] = $students[0]['students'];
		$res['totalFees'] = $fees_collects[0]['fees'];
		$res['totalAdmission'] = 0;
		$res['parentCommunications'] = $parentCommunication;
		$res['recentFeesCollection'] = $fees_collection;
		$res['studentBirthdays'] = $studentBirthdays;
		$res['teacherBirthdays'] = $teacherBirthdays;
		$res['calendarEvents'] = $calendarEvents;
		$res['academicTerms'] = $getAcademicTerms;
		$res['academicYears'] = $getAcademicYear;
		$res['studentLeaves'] = $studentLeaves;
		$res['standardsJson'] = json_encode($standards, true);
		$res['absentsJson'] = json_encode($absents, true);
		$res['presantsJson'] = json_encode($presants, true);
		$res['chartData'] = $final_chart_data;
		$res['unpaid_fees_data'] = $unpaid_data;
		$res['paid_fee_data'] = $paid_data;
		$res['std_data'] = $std_data;
		$res['chart1Data'] = $final_chart1_data;
		
		$request->session()->put('academicTerms', $getAcademicTerms);
		$request->session()->put('academicYears', $getAcademicYear);
		// dd($res['userimage']);
		$getSchoolData = SchoolModel::where(['id' => $sub_institute_id])->get()->toArray();
		$getUserData = tbluserModel::where(['sub_institute_id' => $sub_institute_id])->get()->toArray();
		// dd($getSchoolData[0]);
		if (isset($getSchoolData)) {
			$res['schooldata'] = $getSchoolData[0];
		}
		if (isset($getUserData)) {
			$res['userdata'] = $getUserData[0];
		}
		// $data_of_ses = session()->all();
		// echo '<pre>';
		// print_r($res);
		// print_r($data_of_ses);
		// die;
		return is_mobile($type, "home", $res, "view");
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
FROM tbluser u LEFT JOIN tblindividual_rights i ON u.id = i.user_id AND u.sub_institute_id = i.sub_institute_id LEFT JOIN tblgroupwise_rights g ON u.user_profile_id = g.profile_id AND u.sub_institute_id = g.sub_institute_id INNER JOIN tblmenumaster m ON (i.menu_id = m.id OR g.menu_id = m.id) AND FIND_IN_SET(" . $sub_institute_id . ", m.sub_institute_id) WHERE u.sub_institute_id = '" . $sub_institute_id . "' AND u.id = '" . $user_id . "'";

		$rightsQuery = DB::select($rightsQuery);

		$rightsQuery = array_map(function ($value) {
			return (array) $value;
		}, $rightsQuery);

		$rightsMenusIds = 0;

		if (isset($rightsQuery['0']['MID'])) {
			$rightsMenusIds = $rightsQuery['0']['MID'];
		}

		$data = tblmenumasterModel::where(['parent_menu_id' => "0", 'level' => "1"])->whereRaw("find_in_set('$sub_institute_id',sub_institute_id) and status = 1 and id in (" . $rightsMenusIds . ") ")->orderBy('sort_order')->get()->toArray();
		//        $subMenuData = tblmenumasterModel::where('parent_menu_id', '!=' , 0)->whereIn('sub_institute_id', [$user_id])->get()->toArray();
		$subMenuData = tblmenumasterModel::where('parent_menu_id', '!=', 0)->whereRaw("find_in_set('$sub_institute_id',sub_institute_id) AND level = 2 and id in (" . $rightsMenusIds . ") and status = 1 ")->orderBy('sort_order')->get()->toArray();
		//         dd($subMenuData);
		$i = 0;
		foreach ($subMenuData as $key => $value) {
			$finalSubMenu[$value['parent_menu_id']][$i] = $subMenuData[$key];
			$i++;
		}

		$subChildMenuData = tblmenumasterModel::where('parent_menu_id', '!=', 0)->whereRaw("find_in_set('$sub_institute_id',sub_institute_id) AND level = 3 and id in (" . $rightsMenusIds . ") and status = 1 ")->orderBy('sort_order')->get()->toArray();
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

	public function rights_issue(Request $request)
	{
		$type = $request->input('type');

		$res['status_code'] = 1;
		$res['message'] = "Success";

		return is_mobile($type, "dashboard", $res, "view");
	}
}
