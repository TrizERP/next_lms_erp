<?php

namespace App\Http\Controllers;

use App\Http\Controllers\school_setup\facultywisetimetableController;
use App\Models\fees\fees_collect\fees_collect;
use App\Models\tblmenumasterModel;
use App\Models\user\tbluserModel;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use function App\Helpers\is_mobile;
use Illuminate\Support\Facades\Schema;

class dashboardController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @param Request $request
     * @return false|Application|Factory|View|RedirectResponse|string
     * @throws NotFoundExceptionInterface
     * @throws ContainerExceptionInterface
     */
    public function index(Request $request)
    {
        $type = $request->input('type');
        $client_id = session()->get('client_id');
        $is_admin = session()->get('is_admin');
        $syear = $request->session()->get('syear');
        $user_profile_name = $request->session()->get("user_profile_name");
        $user_profile_id = $request->session()->get("user_profile_id");
        $user_id = $request->session()->get("user_id");

        $sub_institute_id = session()->get('sub_institute_id');

        //START Dynamic Dashboard
        $userMenu = DB::table("dynamic_dashboard")->where([
            ["sub_institute_id", $sub_institute_id],
            ["user_id", $user_id],
            ["user_profile_id", $user_profile_id],
        ])->get()->toArray();
        $userMenu = array_map(static function ($value) {
            return (array)$value;
        }, $userMenu);

        $final_userMenu = [];
        foreach ($userMenu as $key => $val) {
            $final_userMenu[] = $val['menu_id'];
            $final_userMenuTitle[$val['menu_title']] = $val['menu_id'];
        }
        //END Dynamic Dashboard

        if ($user_profile_name == 'Super Admin' || $user_profile_name == 'Admin' || $user_profile_name == 'ADMIN' ||
            $user_profile_name == 'admin' || $user_profile_name == 'school admin' || $user_profile_name == 'SCHOOL ADMIN'
            || $user_profile_name == 'School Admin') {
            if ($sub_institute_id != 0 && $is_admin == '') {

                $date = date('Y-m-d');
                $date15 = date('Y-m-d', strtotime($date . ' +15 day'));

                $users = tbluserModel::selectRaw("count(id) as users")->where([
                    'sub_institute_id' => $sub_institute_id, 'status' => "1",
                ])->get()->toArray();

                $students = DB::table("tblstudent as ts")
                    ->selectRaw("COUNT(ts.id) students")
                    ->join("tblstudent_enrollment as se", function ($join) use ($sub_institute_id) {
                        $join->on('se.student_id', '=', 'ts.id')
                            ->where('se.sub_institute_id', '=', $sub_institute_id);
                    })
                    ->join("standard as s", function ($join) use ($sub_institute_id) {
                        $join->on('s.id', '=', 'se.standard_id')
                            ->where('s.sub_institute_id', '=', $sub_institute_id);
                    })
                    ->where([
                        ["ts.sub_institute_id", "=", $sub_institute_id],
                        ["se.syear", "=", $syear],
                        ["se.end_date", "=", null],
                    ])
                    ->get()
                    ->toArray();

                $total_admission = DB::table("admission_enquiry")
                    ->where([
                        ["syear", "=", $syear],
                        ["sub_institute_id", "=", $sub_institute_id],
                    ])
                    ->count();


                $fees_collects = fees_collect::selectRaw("IFNULL(sum(amount),0) as fees")
                    ->where(["sub_institute_id" => $sub_institute_id, "syear" => $syear, "is_deleted" => "N"])
                    ->whereDate("receiptdate", $date)->get()->toArray();

                $other_fees_collects = DB::table("fees_paid_other")
                    ->selectRaw("IFNULL(sum(actual_amountpaid), 0) as fees")
                    ->where(["sub_institute_id" => $sub_institute_id, "syear" => $syear, "is_deleted" => "N"])
                    ->whereDate("receiptdate", $date)->get()->toArray();
                $other_fees_collects = json_decode(json_encode($other_fees_collects), true);

                $parentCommunication = DB::table("parent_communication as p")
                    ->selectRaw("p.* , CONCAT_WS(' ', s.first_name, s.last_name) as student_name, s.image as student_image")
                    ->join("tblstudent as s", function ($join) {
                        $join->on("p.student_id", "=", "s.id");
                    })
                    ->where("date_", "=", $date)
                    ->where("p.sub_institute_id", "=", $sub_institute_id)
                    ->limit(10)
                    ->orderBy("p.id", "desc")
                    ->get()->toArray();

                $fees_collection = fees_collect::selectRaw('fees_collect.*,CONCAT_WS(" ",tblstudent.first_name,tblstudent.middle_name,tblstudent.last_name) as student_name,sum(amount) as total_fees')
                    ->join('tblstudent', 'tblstudent.id', '=', 'fees_collect.student_id')
                    ->where(['fees_collect.sub_institute_id' => $sub_institute_id, 'fees_collect.is_deleted' => "N"])
                    ->whereRaw("date_format(fees_collect.receiptdate,'%Y-%m-%d') = '" . $date . "'")
                    ->groupBy('payment_mode')
                    ->take(10)->get()->toArray();

                $admissionBlock = DB::table("standard as s")
                    ->leftJoin("admission_enquiry as e", function ($join) {
                        $join->whereRaw('s.id = e.admission_standard and e.sub_institute_id=s.sub_institute_id');
                    })
                    ->leftJoin("admission_form as f", function ($join) {
                        $join->whereRaw('f.admission_standard = s.id and f.sub_institute_id = s.sub_institute_id and e.enquiry_no = f.enquiry_no');
                    })
                    ->leftJoin("admission_registration as r", function ($join) {
                        $join->whereRaw('r.enquiry_no = f.enquiry_no and r.sub_institute_id = s.sub_institute_id');
                    })
                    ->selectRaw("COUNT(e.id) as total_enquiry, COUNT(f.id) as total_form, COUNT(r.id) as total_registration, s.name as standard_name")
                    ->where("s.sub_institute_id", "=", $sub_institute_id)
                    ->groupBy("s.id")->having('total_enquiry', '<>', 0)->get()->toArray();

                $visitorBlock = DB::table("visitor_master as v")
                    ->join("tbluser as u", function ($join) {
                        $join->whereRaw("u.id = v.to_meet");
                    })
                    ->selectRaw("appointment_type, CONCAT(u.first_name, ' ', u.middle_name, ' ', u.last_name) as staff_name, name, contact")
                    ->where("v.sub_institute_id", "=", $sub_institute_id)
                    ->where("meet_date", "=", date('Y-m-d'))
                    ->limit(10)->get()->toArray();

                $smsParentBlock = DB::table("sms_sent_parents as s")
                    ->selectRaw("COUNT(*) as total_sms_parents")
                    ->where("s.sub_institute_id", "=", $sub_institute_id)
                    ->get()->toArray();

                $smsStaffBlock = DB::table("sms_sent_staff as s")
                    ->selectRaw("COUNT(*) as total_sms_staff")
                    ->where("s.sub_institute_id", "=", $sub_institute_id)
                    ->get()->toArray();

                $emailParentBlock = DB::table("email_sent_parents as s")
                    ->selectRaw("COUNT(*) as total_email_parents")
                    ->where("s.sub_institute_id", "=", $sub_institute_id)
                    ->get()->toArray();

                $smsNotificationBlock['Total Sms Parents'] = $smsParentBlock[0]->total_sms_parents;
                $smsNotificationBlock['Total Sms Staff'] = $smsStaffBlock[0]->total_sms_staff;
                $smsNotificationBlock['Total Email Parents'] = $emailParentBlock[0]->total_email_parents;

                $homeworkBlock = DB::table("homework as s")
                    ->selectRaw("COUNT(*) as total_homework")
                    ->where("s.sub_institute_id", "=", $sub_institute_id)
                    ->where("s.date", "=", date('Y-m-d'))
                    ->get()->toArray();

                $circularBlock = DB::table("circular as s")
                    ->selectRaw("COUNT(*) as total_circular")
                    ->where("s.sub_institute_id", "=", $sub_institute_id)
                    ->where("s.date_", "=", date('Y-m-d'))
                    ->get()->toArray();

                $diciplineBlock = DB::table("dicipline as s")
                    ->selectRaw("COUNT(*) as total_dicipline")
                    ->where("s.sub_institute_id", "=", $sub_institute_id)
                    ->where("s.date_", "=", date('Y-m-d'))
                    ->get()->toArray();

                $NotificationBlock = DB::table("app_notification")
                    ->selectRaw("COUNT(*) as total_notification, notification_type")
                    ->where("sub_institute_id", "=", $sub_institute_id)
                    ->groupBy('notification_type')->get()->toArray();

                if (count($NotificationBlock) > 0) {
                    foreach ($NotificationBlock as $nkey => $nval) {
                        $ntitle = $nval->notification_type . " Notification";
                        $academicBlock[$ntitle] = $nval->total_notification;
                    }
                }
                $academicBlock['Total Homework'] = $homeworkBlock[0]->total_homework;
                $academicBlock['Total Circular'] = $circularBlock[0]->total_circular;
                $academicBlock['Total Dicipline'] = $diciplineBlock[0]->total_dicipline;

                $studentBirthdays = DB::table("tblstudent as s")
                    ->join("tblstudent_enrollment as ts", function ($join) use ($syear) {
                        $join->whereRaw("s.id = ts.student_id and ts.syear = " . $syear);
                    })
                    ->join("standard as st", function ($join) {
                        $join->whereRaw("ts.standard_id = st.id");
                    })
                    ->join("division as d", function ($join) {
                        $join->whereRaw("ts.section_id = d.id");
                    })
                    ->selectRaw("CONCAT_WS(' ', s.first_name, s.middle_name, s.last_name) as student_name, st.name as standard_name, d.name as division_name, DATE_FORMAT(s.dob, '%d-%m-%Y') as dob")
                    ->where("s.sub_institute_id", "=", $sub_institute_id)
                    ->whereNull("ts.end_date")
                    ->whereRaw("DATE_FORMAT(s.dob, '%m-%d') >= DATE_FORMAT(NOW(), '%m-%d') and DATE_FORMAT(s.dob, '%m-%d') <= DATE_FORMAT((NOW() + INTERVAL +7 DAY), '%m-%d')")
                    ->orderByRaw("DATE_FORMAT(s.dob, '%m-%d')")
                    ->get()->toArray();

                $teacherBirthdays = DB::table("tbluser as s")
                    ->join("tbluserprofilemaster as tu", function ($join) use ($syear) {
                        $join->whereRaw("s.user_profile_id = tu.id");
                    })
                    ->selectRaw("CONCAT_WS(' ',s.first_name,s.middle_name,s.last_name) as teacher_name,tu.name as designation,s.mobile as contact_number, DATE_FORMAT(s.birthdate, '%d-%m-%Y') AS birthdate")
                    ->where("s.sub_institute_id", "=", $sub_institute_id)
                    ->where("s.status", "!=", 0)
                    ->whereRaw("DATE_FORMAT(s.birthdate,'%m-%d') >= DATE_FORMAT(NOW(), '%m-%d') and DATE_FORMAT(s.birthdate, '%m-%d') <= DATE_FORMAT((NOW() + INTERVAL +7 DAY), '%m-%d')")
                    ->orderByRaw("DATE_FORMAT(s.birthdate, '%m-%d')")
                    ->get()->toArray();

                $calendarEvents = DB::table("calendar_events")
                    ->where("sub_institute_id", "=", $sub_institute_id)
                    ->where("school_date", ">=", $date)
                    ->where("school_date", "<=", $date15)
                    ->get()->toArray();

                $studentLeaves = DB::table("leave_applications as l")
                    ->join("tblstudent as s", function ($join) {
                        $join->whereRaw("l.student_id = s.id");
                    })
                    ->join("tblstudent_enrollment as se", function ($join) use ($syear) {
                        $join->whereRaw("s.id = se.student_id AND se.syear = " . $syear);
                    })
                    ->join("standard as st", function ($join) {
                        $join->whereRaw("st.id = se.standard_id");
                    })
                    ->join("division as dt", function ($join) {
                        $join->whereRaw("dt.id = se.section_id");
                    })
                    ->selectRaw("l.*, CONCAT_WS(' ',s.first_name,s.middle_name,s.last_name) AS student_name,st.name AS standard_name,
            dt.name AS division_name")
                    ->where("l.sub_institute_id", "=", $sub_institute_id)
                    ->where("l.apply_date", "=", $date)
                    ->limit(10)
                    ->get()->toArray();

                $standards_att = [];
                $absents = [];
                $presants = [];

                $attendanceCharts = DB::table("attendance_student as s")
                    ->join("standard as st", function ($join) {
                        $join->whereRaw("s.standard_id = st.id");
                    })
                    ->join("division as dt", function ($join) {
                        $join->whereRaw("s.section_id = dt.id");
                    })
                    ->selectRaw("st.name as standard, dt.name, s.attendance_code, SUM(CASE WHEN s.attendance_code = 'A' THEN 1 ELSE 0 END) as absent, SUM(CASE WHEN s.attendance_code = 'P' THEN 1 ELSE 0 END) as present")
                    ->where("s.sub_institute_id", "=", $sub_institute_id)
                    ->where("s.attendance_date", "=", $date)
                    ->groupBy("s.standard_id")
                    ->get()->toArray();

                foreach ($attendanceCharts as $key => $value) {
                    $standards_att[] = $value->standard;
                    $absents[] = (int)$value->absent;
                    $presants[] = (int)$value->present;
                }

                $today = date("Y-m-d");
                $parameters = array(
                    ":dt" => $today,
                    ":sb" => $sub_institute_id,
                    ":syear" => $syear,
                );

                $fees_chart_data = DB::table('fees_collect as fc')
                    ->join('tblstudent_enrollment as se', function ($join) use ($syear) {
                        $join->whereRaw("se.student_id = fc.student_id and se.syear = " . $syear);
                    })
                    ->join('standard as s', function ($join) {
                        $join->whereRaw("s.id = se.standard_id");
                    })
                    ->whereRaw('DATE_FORMAT(fc.receiptdate, "%Y-%m-%d") = ' . $today . ' and fc.sub_institute_id = ' . $sub_institute_id . ' group by se.standard_id')
                    ->get()->toArray();

                $parameters = array(
                    ":syear" => $syear,
                    ":sb" => $sub_institute_id,
                );

                $student_chart_data = DB::table('tblstudent_enrollment as se')
                    ->join('standard as s', function ($join) {
                        $join->whereRaw("s.id = se.standard_id");
                    })
                    ->selectRaw('COUNT(se.student_id) total_student,s.name')
                    ->where('se.sub_institute_id', '=', $sub_institute_id)
                    ->where('se.syear', '=', $syear)
                    ->groupByRaw('se.standard_id,s.id')
                    ->orderBy('s.sort_order')
                    ->get()->toArray();

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

                $fees_chart1_cash_data = DB::table('fees_collect as fc')
                    ->join('tblstudent_enrollment as se', function ($join) use ($syear) {
                        $join->whereRaw("se.student_id = fc.student_id and se.syear = " . $syear);
                    })
                    ->join('standard as s', function ($join) use ($syear) {
                        $join->whereRaw("s.id = se.standard_id");
                    })->whereDate('fc.receiptdate', $today)
                    ->where("fc.sub_institute_id", "=", $sub_institute_id)
                    ->where("fc.payment_mode", "=", "cash")
                    ->groupBy('se.standard_id')->get()->toArray();

                $today = date("Y-m-d");
                $parameters = array(
                    ":dt" => $today,
                    ":sb" => $sub_institute_id,
                    ":syear" => $syear,
                    ":mode" => "cheque",
                );

                $fees_chart1_cheque_data = DB::table('fees_collect as fc')
                    ->join('tblstudent_enrollment as se', function ($join) use ($syear) {
                        $join->whereRaw("se.student_id = fc.student_id and se.syear = " . $syear);
                    })
                    ->join('standard as s', function ($join) {
                        $join->whereRaw("s.id = se.standard_id");
                    })->whereDate('fc.receiptdate', $today)
                    ->where("fc.sub_institute_id", "=", $sub_institute_id)
                    ->where("fc.payment_mode", "=", "cheque")
                    ->get()->toArray();

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

                $fees_chart2_bkoff_data = DB::table('tblstudent as s')
                    ->join('tblstudent_enrollment as se', function ($join) {
                        $join->whereRaw("se.student_id = s.id");
                    })
                    ->join('academic_section as g', function ($join) {
                        $join->whereRaw("g.id = se.grade_id");
                    })
                    ->join('standard as st', function ($join) {
                        $join->whereRaw("st.id = se.standard_id");
                    })
                    ->leftJoin('division as d', function ($join) {
                        $join->whereRaw("d.id = se.section_id");
                    })->join('fees_breackoff as fb', function ($join) use ($syear, $sub_institute_id) {
                        $join->whereRaw("fb.syear = " . $syear . " AND fb.admission_year = s.admission_year AND fb.quota = se.student_quota AND fb.grade_id = se.grade_id AND fb.standard_id = se.standard_id AND fb.sub_institute_id = " . $sub_institute_id);
                    })
                    ->selectRaw("SUM(fb.amount) amt,st.name")
                    ->where("s.sub_institute_id", '=', $sub_institute_id)->where("se.syear", "=", $syear)
                    ->groupBy('st.id')->orderBy("st.id")->get()->toArray();

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

                $fees_chart2_fees_data = DB::table('tblstudent as s')
                    ->join('tblstudent_enrollment as se', function ($join) {
                        $join->whereRaw("se.student_id = s.id");
                    })
                    ->join('academic_section as g', function ($join) {
                        $join->whereRaw("g.id = se.grade_id");
                    })
                    ->join('standard as st', function ($join) {
                        $join->whereRaw("st.id = se.standard_id");
                    })
                    ->leftJoin('division as d', function ($join) {
                        $join->whereRaw("d.id = se.section_id");
                    })->join('fees_collect as fc', function ($join) use ($syear, $sub_institute_id) {
                        $join->whereRaw("fc.student_id = s.id AND fc.syear = " . $syear . " AND fc.sub_institute_id = " . $sub_institute_id);
                    })
                    ->selectRaw("SUM(fc.amount) + SUM(fc.fees_discount) as amount,st.name")
                    ->where("s.sub_institute_id", '=', $sub_institute_id)
                    ->groupBy('st.id')->orderBy("st.id")->get()->toArray();

                $paid_data = "[";
                foreach ($fees_chart2_fees_data as $id => $arr) {
                    $paid_data .= $arr->amount . ",";
                }
                $paid_data = rtrim($paid_data, ",");
                $paid_data .= "]";


                $academicSections = DB::table('academic_section')->where('sub_institute_id', '=', $sub_institute_id)
                    ->get()->toArray();

                $academicSections = array_map(function ($value) {
                    return (array)$value;
                }, $academicSections);

                $gradeIds = '';
                foreach ($academicSections as $key => $value) {
                    $gradeIds .= $value['id'] . ',';
                }

                $standards = DB::table('standard')->whereIn(
                    'grade_id',
                    collect($academicSections)->pluck('id')
                )->get()->toArray();

                $standards = array_map(function ($value) {
                    return (array)$value;
                }, $standards);

                $standardsArray = array();

                foreach ($standards as $key => $value) {
                    $standardsArray[$value['grade_id']][] = $standards[$key];
                }

                $chartStudents = DB::table('tblstudent as s')
                    ->join('tblstudent_enrollment as se', function ($join) {
                        $join->whereRaw("s.id = se.student_id");
                    })
                    ->selectRaw("s.id,se.grade_id,se.standard_id")
                    ->where('s.sub_institute_id', '=', $sub_institute_id)
                    ->where('se.grade_id', '!=', '')
                    ->where('se.standard_id', '!=', '')
                    ->get()->toArray();

                $chartAS = array();
                $chartS = array();

                foreach ($chartStudents as $k => $v) {
                    $chartAs[$v->grade_id][] = $v->id;
                    $chartS[$v->standard_id][] = $v->id;
                }

                $chartFAs = array();
                $chartFS = array();

                $chartFees = DB::table('fees_collect as fc')
                    ->join('tblstudent_enrollment as se', function ($join) use ($syear) {
                        $join->whereRaw("se.student_id = fc.student_id AND se.syear = " . $syear);
                    })
                    ->join('standard as s', function ($join) use ($syear) {
                        $join->whereRaw("s.id = se.standard_id");
                    })
                    ->selectRaw("fc.amount,s.name,se.grade_id,se.standard_id")
                    ->where('s.sub_institute_id', '=', $sub_institute_id)
                    ->get()->toArray();

                foreach ($academicSections as $key => $value) {
                    $chartFAs[$value['id']] = 0;
                }

                foreach ($standards as $key => $value) {
                    $chartFS[$value['id']] = 0;
                }
                $totalFeesCF = 0;
                foreach ($chartFees as $k => $v) {
                    if (!isset($chartFAs[$v->grade_id])) {
                        $chartFAs[$v->grade_id] = 0;
                    }
                    if (!isset($chartFS[$v->standard_id])) {
                        $chartFS[$v->standard_id] = 0;
                    }
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
                    if (isset($standardsArray[$v['id']])) {
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
                    if (isset($standardsArray[$v['id']])) {

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
                    if (isset($standardsArray[$v['id']])) {

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
                    if (isset($standardsArray[$v['id']])) {

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
                    if (isset($standardsArray[$v['id']])) {

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
                $res['totalAdmission'] = $total_admission;


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

                if (isset($final_userMenuTitle)) {
                    foreach ($final_userMenuTitle as $key => $val) {
                        if ($key == "Student Attendance") {
                            $res['standardsJson'] = json_encode($standards_att, true);
                            $res['absentsJson'] = json_encode($absents, true);
                            $res['presantsJson'] = json_encode($presants, true);
                        } elseif ($key == "Recent Parent Communication") {
                            $res['parentCommunications'] = $parentCommunication;
                        } elseif ($key == "Student Fees Chart") {
                            $res['studentFeesChart'] = 1;
                        } elseif ($key == "Recent fees collection") {
                            $res['recentFeesCollection'] = $fees_collection;
                        } elseif ($key == "Events") {
                            $res['calendarEvents'] = $calendarEvents;
                        } elseif ($key == "Student Leaves") {
                            $res['studentLeaves'] = $studentLeaves;
                        } elseif ($key == "Admission Information") {
                            $res['admissionBlock'] = $admissionBlock;
                        } elseif ($key == "Recent Visitors") {
                            $res['visitorBlock'] = $visitorBlock;
                        } elseif ($key == "Sms Notification") {
                            $res['smsNotificationBlock'] = $smsNotificationBlock;
                        } elseif ($key == "Academic Information") {
                            $res['academicBlock'] = $academicBlock;
                        }

                    }
                }

                $current_date = date('Y-m-d');


                $school_setup_data = DB::table("school_setup")
                    ->selectRaw("*, DATEDIFF(expire_date, " . $current_date . ") as remaining_days, SUM(ifnull(given_space_mb, 0)) as given_space_mb")
                    ->where("id", "=", $sub_institute_id)
                    ->get()->toArray();
                $school_setup_data = json_decode(json_encode($school_setup_data[0]), true);
                $res['school_setup_data'] = $school_setup_data;

                //START Code for calculate used space using folder-wise table
                $photo_video_data = DB::table('photo_video_gallary')->selectRaw('SUM(IFNULL(file_size,0)) as file_size')
                    ->where('sub_institute_id', '=', $sub_institute_id)->get()->toArray();
                $photo_video_data_size = 0;
                if (count($photo_video_data) != 0) {
                    $photo_video_data_size = $photo_video_data[0]->file_size;
                }

                $leave_applications_data = DB::table('leave_applications')->selectRaw('SUM(IFNULL(file_size,0)) as file_size')
                    ->where('sub_institute_id', '=', $sub_institute_id)->get()->toArray();
                $leave_applications_data_size = 0;
                if (count($leave_applications_data) != 0) {
                    $leave_applications_data_size = $leave_applications_data[0]->file_size;
                }

                $exam_schedule_data = DB::table('exam_schedule')->selectRaw('SUM(IFNULL(file_size,0)) as file_size')
                    ->where('sub_institute_id', '=', $sub_institute_id)->get()->toArray();
                $exam_schedule_data_size = 0;
                if (count($exam_schedule_data) != 0) {
                    $exam_schedule_data_size = $exam_schedule_data[0]->file_size;
                }

                $inward_data = DB::table('inward')->selectRaw('SUM(IFNULL(attachment_size,0)) as file_size')
                    ->where('sub_institute_id', '=', $sub_institute_id)->get()->toArray();
                $inward_data_size = 0;
                if (count($inward_data) != 0) {
                    $inward_data_size = $inward_data[0]->file_size;
                }

                $outward_data = DB::table('outward')->selectRaw('SUM(IFNULL(attachment_size,0)) as file_size')
                    ->where('sub_institute_id', '=', $sub_institute_id)->get()->toArray();
                $outward_data_size = 0;
                if (count($outward_data) != 0) {
                    $outward_data_size = $outward_data[0]->file_size;
                }

                $petty_cash_data = DB::table('petty_cash')->selectRaw('SUM(IFNULL(file_size,0)) as file_size')
                    ->where('sub_institute_id', '=', $sub_institute_id)->get()->toArray();
                $petty_cash_size = 0;
                if (count($petty_cash_data) != 0) {
                    $petty_cash_size = $petty_cash_data[0]->file_size;
                }


                $homework_data = DB::table('homework')->selectRaw('IFNULL(SUM(distinct image_size),0) as file_size,image_size')
                    ->where('sub_institute_id', '=', $sub_institute_id)
                    ->groupBy('image')->having('image_size', '>', 0)->get()->toArray();
                $homework_size = 0;
                if (count($homework_data) != 0) {
                    $homework_size = $homework_data[0]->file_size;
                }

                $homework_submission_data = DB::table('homework')->selectRaw('SUM(IFNULL(submission_image_size,0)) as file_size')
                    ->where('sub_institute_id', '=', $sub_institute_id)->get()->toArray();
                $homework_submission_size = 0;
                if (count($homework_submission_data) != 0) {
                    $homework_submission_size = $homework_submission_data[0]->file_size;
                }


                $visitor_data = DB::table('visitor_master')->selectRaw('SUM(IFNULL(file_size,0)) as file_size')
                    ->where('sub_institute_id', '=', $sub_institute_id)->get()->toArray();
                $visitor_size = 0;
                if (count($visitor_data) != 0) {
                    $visitor_size = $visitor_data[0]->file_size;
                }

                $frontdesk_data = DB::table('front_desk')->selectRaw('SUM(IFNULL(FILE_SIZE,0)) as file_size')
                    ->where('sub_institute_id', '=', $sub_institute_id)->get()->toArray();
                $frontdesk_size = 0;
                if (count($frontdesk_data) != 0) {
                    $frontdesk_size = $frontdesk_data[0]->file_size;
                }

                $task_data = DB::table('front_desk')->selectRaw('SUM(IFNULL(FILE_SIZE,0)) as file_size')
                    ->where('sub_institute_id', '=', $sub_institute_id)->get()->toArray();
                $task_size = 0;
                if (count($task_data) != 0) {
                    $task_size = $task_data[0]->file_size;
                }

                $complaint_data = DB::table('complaint')->selectRaw('SUM(IFNULL(FILE_SIZE,0)) as file_size')
                    ->where('sub_institute_id', '=', $sub_institute_id)->get()->toArray();
                $complaint_size = 0;
                if (count($complaint_data) != 0) {
                    $complaint_size = $complaint_data[0]->file_size;
                }

                $student_data = DB::table('tblstudent')->selectRaw('SUM(IFNULL(FILE_SIZE,0)) as file_size')
                    ->where('sub_institute_id', '=', $sub_institute_id)->get()->toArray();
                $student_size = 0;
                if (count($student_data) != 0) {
                    $student_size = $student_data[0]->file_size;
                }

                $student_health_data = DB::table('student_health')->selectRaw('SUM(IFNULL(file_size,0)) as file_size')
                    ->where('sub_institute_id', '=', $sub_institute_id)->get()->toArray();
                $student_health_size = 0;
                if (count($student_health_data) != 0) {
                    $student_health_size = $student_health_data[0]->file_size;
                }

                $total_used_size = $photo_video_data_size + $leave_applications_data_size + $exam_schedule_data_size + $inward_data_size
                    + $outward_data_size + $petty_cash_size + $homework_size + $homework_submission_size + $visitor_size
                    + $frontdesk_size + $task_size + $complaint_size + $student_size + $student_health_size;
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
            } else {
                $type = $request->input('type');
                $res['status_code'] = 1;
                $res['message'] = "Success";

                return is_mobile($type, "home", $res, "view");
            }
        } else {

            $date = date('Y-m-d');

            $date15 = date('Y-m-d', strtotime($date . ' +15 day'));

            $users = tbluserModel::selectRaw("count(id) as users")->where([
                'sub_institute_id' => $sub_institute_id, 'status' => "1",
            ])->get()->toArray();

            $students = DB::table("tblstudent as ts")
                ->join("tblstudent_enrollment as se", function ($join) {
                    $join->whereRaw("se.student_id = ts.id AND se.sub_institute_id = se.sub_institute_id");
                })
                ->join("standard as s", function ($join) {
                    $join->whereRaw("s.id = se.standard_id AND se.sub_institute_id = s.sub_institute_id");
                })
                ->selectRaw("COUNT(ts.id) as students")
                ->where("ts.sub_institute_id", "=", $sub_institute_id)
                ->where("se.syear", "=", $syear)
                ->whereNull("se.end_date")
                ->get()->toArray();

            $total_admission = DB::table('admission_enquiry')->selectRaw('COUNT(id) as total_admissions')
                ->where('syear', '=', $syear)
                ->where('sub_institute_id', '=', $sub_institute_id)->get()->toArray();

            $fees_collects = fees_collect::selectRaw("ifnull(sum(amount),0) as fees")
                ->where(['sub_institute_id' => $sub_institute_id, 'syear' => $syear, 'is_deleted' => "N"])
                ->whereRaw("date_format(receiptdate,'%Y-%m-%d') = '" . $date . "'")->get()->toArray();


            $other_fees_collects = DB::table('fees_paid_other')->selectRaw("IFNULL(SUM(actual_amountpaid),0) AS fees")
                ->where(['sub_institute_id' => $sub_institute_id, 'syear' => $syear, 'is_deleted' => "N"])
                ->whereRaw("DATE_FORMAT(receiptdate,'%Y-%m-%d') = '" . $date . "'")->get()->toArray();
            $other_fees_collects = json_decode(json_encode($other_fees_collects), true);

            if ($user_profile_name == 'Student') {
                $parentCommunication = DB::table('parent_communication as p')
                    ->join('tblstudent as s', function ($join) {
                        $join->whereRaw('p.student_id = s.id');
                    })
                    ->selectRaw("p.*,CONCAT_WS(' ',s.first_name,s.last_name) as student_name, s.image as student_image")
                    ->whereDate('p.date_', '=', $date)->where('p.sub_institute_id', '=', $sub_institute_id)
                    ->where('s.id', '=', $user_id)->orderBy('p.id', 'DESC')->limit(10)->get()->toArray();
            } else {
                $parentCommunication = DB::table('parent_communication as p')
                    ->join('tblstudent as s', function ($join) {
                        $join->whereRaw('p.student_id = s.id');
                    })
                    ->selectRaw("p.*,CONCAT_WS(' ',s.first_name,s.last_name) as student_name,s.image as student_image")
                    ->whereDate('p.date_', '=', $date)->where('p.sub_institute_id', '=', $sub_institute_id)
                    ->orderBy('p.id', 'DESC')->limit(10)->get()->toArray();
            }


            $standards_att = $absents = $presants = array();

            $attendanceCharts = DB::table("attendance_student as s")
                ->join("standard as st", function ($join) {
                    $join->whereRaw("s.standard_id = st.id");
                })
                ->join("division as dt", function ($join) {
                    $join->whereRaw("s.section_id = dt.id");
                })
                ->selectRaw("st.name as standard,dt.name,s.attendance_code, SUM(CASE WHEN s.attendance_code = 'A' THEN 1 ELSE 0 END) AS absent,
                    SUM(CASE WHEN s.attendance_code = 'P' THEN 1 ELSE 0 END) AS present")
                ->where("s.sub_institute_id", "=", $sub_institute_id)
                ->where("s.attendance_date", "=", $date)
                ->groupBy("s.standard_id")
                ->get()->toArray();

            foreach ($attendanceCharts as $key => $value) {
                // $standards = "'".$value->standard."',";
                $standards_att[] = $value->standard;
                $absents[] = (int)$value->absent;
                $presants[] = (int)$value->present;
            }

            $admissionBlock = DB::table("standard as s")
                ->leftJoin("admission_enquiry as e", function ($join) {
                    $join->whereRaw("s.id = e.admission_standard and e.sub_institute_id=s.sub_institute_id");
                })
                ->leftJoin("admission_form as f", function ($join) {
                    $join->whereRaw("f.admission_standard = s.id and f.sub_institute_id = s.sub_institute_id and e.enquiry_no = f.enquiry_no");

                })
                ->leftJoin("admission_registration as r", function ($join) {
                    $join->whereRaw("r.enquiry_no = f.enquiry_no and r.sub_institute_id = s.sub_institute_id");
                })
                ->selectRaw("COUNT(e.id) AS total_enquiry, COUNT(f.id) AS total_form ,COUNT(r.id) as total_registration,
                    s.name AS standard_name")
                ->where("s.sub_institute_id", "=", $sub_institute_id)
                ->groupBy("s.id")->having('total_enquiry', '<>', 0)
                ->get()->toArray();

            $visitorBlock = DB::table("visitor_master as v")
                ->join("tbluser as u", function ($join) {
                    $join->whereRaw("u.id = v.to_meet");
                })
                ->selectRaw("appointment_type, CONCAT(u.first_name,' ',u.middle_name,' ',u.last_name) as staff_name,name,contact")
                ->where("v.sub_institute_id", "=", $sub_institute_id)
                ->where("v.meet_date", "=", date('Y-m-d'))
                ->limit(10)
                ->get()->toArray();

            $smsParentBlock = DB::table('sms_sent_parents')->selectRaw('COUNT(*) as total_sms_parents')
                ->where('sub_institute_id', $sub_institute_id)->get()->toArray();

            $smsStaffBlock = DB::table('sms_sent_staff')->selectRaw('COUNT(*) as total_sms_staff')
                ->where('sub_institute_id', $sub_institute_id)->get()->toArray();

            $emailParentBlock = DB::table('email_sent_parents')->selectRaw('COUNT(*) as total_email_parents')
                ->where('sub_institute_id', $sub_institute_id)->get()->toArray();

            $smsNotificationBlock['Total Sms Parents'] = $smsParentBlock[0]->total_sms_parents;
            $smsNotificationBlock['Total Sms Staff'] = $smsStaffBlock[0]->total_sms_staff;
            $smsNotificationBlock['Total Email Parents'] = $emailParentBlock[0]->total_email_parents;

            $homeworkBlock = DB::table('homework')->selectRaw('COUNT(*) as total_homework')
                ->where('sub_institute_id', $sub_institute_id)
                ->where('date', date('Y-m-d'))
                ->where('created_by', $user_id)
                ->get()->toArray();

            $circularBlock = DB::table('circular')->selectRaw('COUNT(*) as total_circular')
                ->where('sub_institute_id', $sub_institute_id)
                ->where('date_', date('Y-m-d'))
                ->get()->toArray();

            $diciplineBlock = DB::table('dicipline')->selectRaw('COUNT(*) as total_dicipline')
                ->where('sub_institute_id', $sub_institute_id)
                ->where('date_', date('Y-m-d'))
                ->where('created_by', $user_id)
                ->get()->toArray();

            $NotificationBlock = DB::table('app_notification')->selectRaw('count(*) as total_notification,notification_type')
                ->where('sub_institute_id', $sub_institute_id)
                ->where(function ($q) use ($user_id) {
                    $q->where('student_id', $user_id)->orWhereNull('student_id');
                })
                ->groupBy('notification_type')
                ->get()->toArray();

            if (count($NotificationBlock) > 0) {
                foreach ($NotificationBlock as $nkey => $nval) {
                    $ntitle = $nval->notification_type . " Notification";
                    $academicBlock[$ntitle] = $nval->total_notification;
                }
            }

            $academicBlock['Total Homework'] = $homeworkBlock[0]->total_homework;
            $academicBlock['Total Circular'] = $circularBlock[0]->total_circular;
            $academicBlock['Total Dicipline'] = $diciplineBlock[0]->total_dicipline;

            $studentBirthdays = DB::table("tblstudent as s")
                ->join("tblstudent_enrollment as ts", function ($join) use ($syear) {
                    $join->whereRaw("s.id = ts.student_id and ts.syear = '" . $syear . "'");
                })
                ->join("standard as st", function ($join) {
                    $join->whereRaw("ts.standard_id = st.id");
                })
                ->join("division as d", function ($join) {
                    $join->whereRaw("ts.section_id = d.id");
                })
                ->selectRaw("CONCAT_WS(' ',s.first_name,s.middle_name,s.last_name) as student_name,st.name as standard_name,d.name as division_name, DATE_FORMAT(s.dob, '%d-%m-%Y') AS dob")
                ->where("s.sub_institute_id", "=", $sub_institute_id)
                ->whereRaw("DATE_FORMAT(s.dob, '%m-%d') >= DATE_FORMAT(NOW(), '%m-%d') and DATE_FORMAT(s.dob, '%m-%d') <= DATE_FORMAT((NOW() + INTERVAL +7 DAY), '%m-%d')")
                ->whereNull("ts.end_date")
                ->orderByRaw("DATE_FORMAT(s.dob, '%m-%d')")
                ->get()->toArray();

            $teacherBirthdays = DB::table("tbluser as s")
                ->join("tbluserprofilemaster as tu", function ($join) {
                    $join->whereRaw("s.user_profile_id = tu.id");
                })
                ->selectRaw("CONCAT_WS(' ',s.first_name,s.middle_name,s.last_name) as teacher_name,tu.name as designation,s.mobile as contact_number, DATE_FORMAT(s.birthdate, '%d-%m-%Y') AS birthdate")
                ->where("s.sub_institute_id", "=", $sub_institute_id)
                ->where("s.status", "!=", 0)
                ->whereRaw("date_format(s.birthdate,'%m-%d') >= DATE_FORMAT(NOW(), '%m-%d') and DATE_FORMAT(s.birthdate, '%m-%d') <= DATE_FORMAT((NOW() + INTERVAL +7 DAY), '%m-%d')")
                ->orderByRaw("DATE_FORMAT(s.birthdate, '%m-%d')")
                ->get()->toArray();


            $calendarEvents = DB::table('calendar_events')->where('sub_institute_id', $sub_institute_id)
                ->where('school_date', ">=", $date)->where('school_date', "<=", $date15)->get()->toArray();

            $res['totalUser'] = $users[0]['users'];
            $res['totalStudent'] = $students[0]->students;
            $res['totalFees'] = ($fees_collects[0]['fees'] + $other_fees_collects[0]['fees']);
            $res['totalAdmission'] = $total_admission[0]->total_admissions;

            $res['studentBirthdays'] = $studentBirthdays;
            $res['teacherBirthdays'] = $teacherBirthdays;
            if ($user_profile_name == 'Student') {
                $stu_homework = DB::table('homework as h')
                    ->join('tblstudent as s', function ($join) {
                        $join->whereRaw("s.id = h.student_id AND s.sub_institute_id = h.sub_institute_id");
                    })
                    ->selectRaw("h.*,CONCAT_WS(' ',s.first_name,s.last_name) as student_name, s.image as student_image")
                    ->where('h.sub_institute_id', $sub_institute_id)
                    ->where('h.date', date('Y-m-d'))
                    ->where('h.student_id', $user_id)->get()->toArray();

                $res['parentCommunications'] = $parentCommunication;
                $res['stu_homework'] = $stu_homework;
            }

            $res['final_userMenu'] = $final_userMenu;


            if (isset($final_userMenuTitle)) {
                foreach ($final_userMenuTitle as $key => $val) {
                    if ($key == "Student Attendance") {
                        $res['standardsJson'] = json_encode($standards_att, true);
                        $res['absentsJson'] = json_encode($absents, true);
                        $res['presantsJson'] = json_encode($presants, true);
                    } elseif ($key == "Recent Parent Communication") {
                        $res['parentCommunications'] = $parentCommunication;
                    } elseif ($key == "Student Fees Chart") {
                        $res['studentFeesChart'] = 1;
                    } elseif ($key == "Recent fees collection") {
                        $res['recentFeesCollection'] = $fees_collection;
                    } elseif ($key == "Events") {
                        $res['calendarEvents'] = $calendarEvents;
                    } elseif ($key == "Student Leaves") {
                        $res['studentLeaves'] = $studentLeaves;
                    } elseif ($key == "Admission Information") {
                        $res['admissionBlock'] = $admissionBlock;
                    } elseif ($key == "Recent Visitors") {
                        $res['visitorBlock'] = $visitorBlock;
                    } elseif ($key == "Sms Notification") {
                        $res['smsNotificationBlock'] = $smsNotificationBlock;
                    } elseif ($key == "Academic Information") {
                        $res['academicBlock'] = $academicBlock;
                    } elseif ($key == "Faculty Timetable") {
                        $foo = new facultywisetimetableController();
                        $tdata = $foo->getTimetable_data($request, $user_id, $sub_institute_id, $syear);
                        $res['facultytimetableBlock'] = $tdata['HTML'];
                    }

                }
            }

            //START Code for calculate used space using folder-wise table

            $school_setup_data = DB::table('school_setup')->selectRaw("*,SUM(IFNULL(given_space_mb,0)) as given_space_mb")
                ->where('id', $sub_institute_id)->get()->toArray();
            $school_setup_data = json_decode(json_encode($school_setup_data[0]), true);


            $petty_cash_data = DB::table('petty_cash')->selectRaw("SUM(IFNULL(file_size,0)) as file_size")
                ->where('sub_institute_id', $sub_institute_id)->where('user_id', $user_id)->get()->toArray();
            $petty_cash_size = 0;
            if ($petty_cash_data[0]->file_size != 0) {
                $petty_cash_size = $petty_cash_data[0]->file_size;
            }

            $get_photo_data = DB::table("photo_video_gallary as p")
                ->join('tblstudent_enrollment as se', function ($join) {
                    $join->whereRaw("se.standard_id = p.standard_id AND se.section_id = p.division_id AND se.sub_institute_id = p.sub_institute_id");
                })
                ->join('tblstudent as s', function ($join) {
                    $join->whereRaw("s.id = se.student_id AND s.sub_institute_id = se.sub_institute_id");
                })
                ->selectRaw("SUM(IFNULL(p.file_size,0)) AS file_size")
                ->where("p.sub_institute_id", $sub_institute_id)->where('s.id', $user_id)->get()->toArray();
            $photo_video_size = 0;
            if ($get_photo_data[0]->file_size != 0) {
                $photo_video_size = $get_photo_data[0]->file_size;
            }

            $get_leave_app_data = DB::table("leave_applications as l")
                ->join('tblstudent as s', function ($join) {
                    $join->whereRaw("s.id = l.student_id AND s.sub_institute_id = l.sub_institute_id");
                })
                ->selectRaw("SUM(IFNULL(l.file_size,0)) AS file_size")
                ->where("l.sub_institute_id", $sub_institute_id)->where('s.id', $user_id)->get()->toArray();
            $leave_app_size = 0;
            if ($get_leave_app_data[0]->file_size != 0) {
                $leave_app_size = $get_leave_app_data[0]->file_size;
            }

            $get_exam_schedule_data = DB::table("exam_schedule as e")
                ->join('tblstudent_enrollment as se', function ($join) {
                    $join->whereRaw("se.standard_id = e.standard_id AND se.section_id = e.division_id AND se.sub_institute_id = e.sub_institute_id");
                })
                ->join('tblstudent as s', function ($join) {
                    $join->whereRaw("s.id = se.student_id AND s.sub_institute_id = se.sub_institute_id");
                })
                ->selectRaw("SUM(IFNULL(e.file_size,0)) AS file_size")
                ->where("e.sub_institute_id", $sub_institute_id)->where('s.id', $user_id)->get()->toArray();
            $exam_schedule_size = 0;
            if (count($get_exam_schedule_data) > 0) {
                $exam_schedule_size = $get_exam_schedule_data[0]->file_size;
            }

            if ($user_profile_name == 'Student') {

                $homework_data = DB::table("homework as p")
                    ->join('tblstudent as s', function ($join) {
                        $join->whereRaw("s.id = p.student_id AND s.sub_institute_id = p.sub_institute_id");
                    })
                    ->selectRaw("IFNULL(SUM(DISTINCT p.image_size),0) AS file_size, CONCAT_WS(' ',s.first_name,s.last_name) AS user_name,
                        p.image_size")
                    ->where("p.sub_institute_id", $sub_institute_id)->where('s.id', $user_id)->get()->toArray();
            } else {
                $homework_data = DB::table("homework")
                    ->selectRaw("IFNULL(SUM(distinct image_size),0) as file_size,image_size")
                    ->where("sub_institute_id", $sub_institute_id)->where('created_by', $user_id)
                    ->groupBy('image')->having('image_size', '>', 0)->get()->toArray();
            }
            $homework_size = 0;
            if (count($homework_data) > 0) {
                $homework_size = $homework_data[0]->file_size;
            }

            if ($user_profile_name == 'Student') {
                $homework_submission_data = DB::table("homework as p")
                    ->join('tblstudent as s', function ($join) {
                        $join->whereRaw("s.id = p.student_id AND s.sub_institute_id = p.sub_institute_id");
                    })
                    ->selectRaw("IFNULL(SUM(DISTINCT p.submission_image_size),0) AS file_size, CONCAT_WS(' ',s.first_name,s.last_name) AS user_name, p.submission_image_size")
                    ->where("p.sub_institute_id", $sub_institute_id)->where('s.id', $user_id)->get()->toArray();
            } else {

                $homework_submission_data = DB::table("homework")->selectRaw("SUM(IFNULL(submission_image_size,0)) as file_size")
                    ->where("sub_institute_id", $sub_institute_id)->where('created_by', $user_id)->get()->toArray();
            }
            $homework_submission_size = 0;
            if (count($homework_submission_data) > 0) {
                $homework_submission_size = $homework_submission_data[0]->file_size;
            }

            $get_frontdesk_data = DB::table("front_desk")->selectRaw("SUM(IFNULL(FILE_SIZE,0)) AS file_size")
                ->where("SUB_INSTITUTE_ID", $sub_institute_id)->where('CREATED_BY', $user_id)->get()->toArray();

            $frontdesk_size = 0;
            if ($get_frontdesk_data[0]->file_size != 0) {
                $frontdesk_size = $get_frontdesk_data[0]->file_size;
            }

            $get_task_data = DB::table("task")->selectRaw("SUM(IFNULL(FILE_SIZE,0)) AS file_size")
                ->where("SUB_INSTITUTE_ID", $sub_institute_id)->where('CREATED_BY', $user_id)->get()->toArray();

            $task_size = 0;
            if ($get_task_data[0]->file_size != 0) {
                $task_size = $get_task_data[0]->file_size;
            }

            $get_complaint_data = DB::table("complaint")->selectRaw("SUM(IFNULL(FILE_SIZE,0)) AS file_size")
                ->where("SUB_INSTITUTE_ID", $sub_institute_id)->where('COMPLAINT_BY', $user_id)->get()->toArray();

            $complaint_size = 0;
            if ($get_complaint_data[0]->file_size != 0) {
                $complaint_size = $get_complaint_data[0]->file_size;
            }

            $student_size = 0;
            if ($user_profile_name == 'Student') {
                $student_data = DB::table("tblstudent")->selectRaw("IFNULL(SUM(file_size),0) AS file_size")
                    ->where("SUB_INSTITUTE_ID", $sub_institute_id)->where('id', $user_id)->get()->toArray();
                if (count($student_data) > 0) {
                    $student_size = $student_data[0]->file_size;
                }
            }

            $get_student_health_data = DB::table("student_health")->selectRaw("SUM(IFNULL(file_size,0)) AS file_size")
                ->where("sub_institute_id", $sub_institute_id)->where('created_by', $user_id)->get()->toArray();
            $student_health_size = 0;
            if ($get_student_health_data[0]->file_size != 0) {
                $student_health_size = $get_student_health_data[0]->file_size;
            }

            // echo 'petty_cash_size : '.$petty_cash_size.'<br>';
            // echo 'photo_video_size : '.$photo_video_size.'<br>';
            // echo 'leave_app_size : '.$leave_app_size.'<br>';
            // echo 'exam_schedule_size : '.$exam_schedule_size.'<br>';
            // echo 'homework_size : '.$homework_size.'<br>';
            // echo 'homework_submission_size : '.$homework_submission_size.'<br>';
            // die;
            $total_used_size = $petty_cash_size + $photo_video_size + $leave_app_size + $exam_schedule_size + $homework_size
                + $homework_submission_size + $frontdesk_size + $task_size + $complaint_size + $student_size + $student_health_size;
            $used_space_in_MB = $this->formatBytes($total_used_size);
            $explod_used_space = explode(' ', $used_space_in_MB);
            $occupied_space_in_MB = $school_setup_data['given_space_mb'];
            $available_space_in_MB = ($occupied_space_in_MB - $explod_used_space[0]);

            $res['occupied_space_in_MB'] = $occupied_space_in_MB;
            $res['used_space_in_MB'] = $used_space_in_MB;
            $res['available_space_in_MB'] = $available_space_in_MB;
            //END Code for calculate used space using folder-wise table

            return is_mobile($type, "teacher_home", $res, "view");
        }
    }

    public function chart(Request $request)
    {

        $type = $request->input('type');
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $syear = $request->session()->get('syear');
        $user_profile_name = $request->session()->get("user_profile_name");

        if ($user_profile_name == 'Admin' || $user_profile_name == 'ADMIN' || $user_profile_name == 'admin' || $user_profile_name == 'school admin'
            || $user_profile_name == 'SCHOOL ADMIN' || $user_profile_name == 'School Admin') {

            $date = date('Y-m-d');
            $date15 = date('Y-m-d', strtotime($date . ' +15 day'));

            $users = tbluserModel::selectRaw("count(id) as users")->where([
                'sub_institute_id' => $sub_institute_id, 'status' => "1",
            ])->get()->toArray();

            $students = DB::table('tblstudent as ts')
                ->join('tblstudent_enrollment as se', function ($join) {
                    $join->whereRaw("se.student_id = ts.id AND se.sub_institute_id = se.sub_institute_id");
                })
                ->join('standard as s', function ($join) {
                    $join->whereRaw("s.id = se.standard_id AND se.sub_institute_id = s.sub_institute_id");
                })
                ->selectRaw("COUNT(ts.id) students")
                ->where("ts.sub_institute_id", "=", $sub_institute_id)
                ->where("se.syear", "=", $syear)
                ->whereNull("se.end_date")->get()->toArray();

            $total_admission = DB::table('admission_enquiry')
                ->selectRaw("COUNT(id) as total_admissions")
                ->where('syear', '=', $syear)
                ->where('sub_institute_id', '=', $sub_institute_id)
                ->get()->toArray();

            $fees_collects = fees_collect::selectRaw("ifnull(sum(amount),0) as fees")
                ->where(['sub_institute_id' => $sub_institute_id, 'syear' => $syear, 'is_deleted' => "N"])
                ->whereRaw("date_format(receiptdate,'%Y-%m-%d') = '" . $date . "'")->get()->toArray();


            $other_fees_collects = DB::table('fees_paid_other')
                ->selectRaw("IFNULL(SUM(actual_amountpaid),0) AS fees")
                ->where('sub_institute_id', '=', $sub_institute_id)
                ->where('syear', '=', $syear)
                ->whereRaw("DATE_FORMAT(receiptdate,'%Y-%m-%d') = '" . $date . "'")
                ->where('is_deleted', '=', 'N')->get()->toArray();
            $other_fees_collects = json_decode(json_encode($other_fees_collects), true);

            $parentCommunication = DB::table('parent_communication as p')
                ->join('tblstudent as s', function ($join) {
                    $join->whereRaw("p.student_id = s.id");
                })
                ->selectRaw("p.*,CONCAT_WS(' ',s.first_name,s.last_name) as student_name,s.image as student_image")
                ->where('p.sub_institute_id', '=', $sub_institute_id)
                ->where('p.date_', '=', $date)
                ->orderBy('p.id')->limit(10)->get()->toArray();

            $fees_collection = fees_collect::selectRaw('fees_collect.*,CONCAT_WS(" ",tblstudent.first_name,tblstudent.middle_name,
                tblstudent.last_name) as student_name,sum(amount) as total_fees')
                ->join('tblstudent', 'tblstudent.id', '=', 'fees_collect.student_id')
                ->where(['fees_collect.sub_institute_id' => $sub_institute_id, 'fees_collect.is_deleted' => "N"])
                ->whereRaw("date_format(fees_collect.receiptdate,'%Y-%m-%d') = '" . $date . "'")
                ->groupBy('payment_mode')
                ->take(10)->get()->toArray();
            
            $studentBirthdays = DB::table('tblstudent as s')
                ->join('tblstudent_enrollment as ts', function ($join) use ($syear) {
                    $join->whereRaw("s.id = ts.student_id and ts.syear = '" . $syear . "'");
                })
                ->join('standard as st', function ($join) {
                    $join->whereRaw("ts.standard_id = st.id");
                })
                ->join('division as d', function ($join) {
                    $join->whereRaw("ts.section_id = d.id");
                })
                ->selectRaw("CONCAT_WS(' ',s.first_name,s.middle_name,s.last_name) as student_name,st.name as standard_name,d.name as division_name, DATE_FORMAT(s.dob, '%d-%m-%Y') AS dob")
                ->where("s.sub_institute_id", $sub_institute_id)
                ->whereNull("ts.end_date")
                ->whereRaw("DATE_FORMAT(s.dob, '%m-%d') >= DATE_FORMAT(NOW(), '%m-%d') and DATE_FORMAT(s.dob, '%m-%d') <= DATE_FORMAT((NOW() + INTERVAL +7 DAY), '%m-%d')")
                ->orderByRaw("DATE_FORMAT(s.dob, '%m-%d')")
                ->get()->toArray();

            $teacherBirthdays = DB::table('tbluser as s')
                ->join('tbluserprofilemaster as tu', function ($join) {
                    $join->whereRaw("s.user_profile_id = tu.id");
                })
                ->selectRaw("CONCAT_WS(' ',s.first_name,s.middle_name,s.last_name) as teacher_name,tu.name as designation,s.mobile as contact_number, DATE_FORMAT(s.birthdate, '%d-%m-%Y') AS birthdate")
                ->where("s.sub_institute_id", $sub_institute_id)
                ->where("s.status", "!=", 0)
                ->whereRaw("date_format(s.birthdate,'%m-%d') >= DATE_FORMAT(NOW(), '%m-%d') and DATE_FORMAT(s.birthdate, '%m-%d') <= DATE_FORMAT((NOW() + INTERVAL +7 DAY), '%m-%d')")
                ->orderByRaw("DATE_FORMAT(s.birthdate, '%m-%d')")
                ->get()->toArray();

            $calendarEvents = DB::table('calendar_events')->where('sub_institute_id', $sub_institute_id)
                ->where('school_date', '>=', $date)->where('school_date', '<=', $date15)->get()->toArray();

            $studentLeaves = DB::table('leave_applications as l')
                ->join('tblstudent as s', function ($join) {
                    $join->whereRaw("l.student_id = s.id");
                })
                ->join('tblstudent_enrollment as se', function ($join) use ($syear) {
                    $join->whereRaw("s.id = se.id AND se.syear = '" . $syear . "'");
                })
                ->join('standard as st', function ($join) {
                    $join->whereRaw("st.id = se.standard_id");
                })
                ->join('division as dt', function ($join) {
                    $join->whereRaw("dt.id = se.section_id");
                })
                ->selectRaw("l.*, CONCAT_WS(' ',s.first_name,s.middle_name,s.last_name) AS student_name,st.name AS standard_name,dt.name AS division_name")
                ->whereRaw("l.sub_institute_id = '" . $sub_institute_id . "' AND '" . $date . "' BETWEEN from_date AND to_date")
                ->get()->toArray();

            $standards_att = [];
            $absents = [];
            $presants = [];

            $attendanceCharts = DB::table('attendance_student as s')
                ->join('standard as st', function ($join) {
                    $join->whereRaw("s.standard_id = st.id");
                })
                ->join('division as dt', function ($join) {
                    $join->whereRaw("s.section_id = dt.id");
                })
                ->selectRaw("st.name as standard,dt.name,s.attendance_code, SUM(CASE WHEN s.attendance_code = 'A' THEN 1 ELSE 0 END) AS absent, SUM(CASE WHEN s.attendance_code = 'P' THEN 1 ELSE 0 END) AS present")
                ->where("s.sub_institute_id", '=', $sub_institute_id)
                ->where("s.attendance_date", '=', $date)
                ->groupBy("s.standard_id")->get()->toArray();

            foreach ($attendanceCharts as $key => $value) {
                // $standards = "'".$value->standard."',";
                $standards_att[] = $value->standard;
                $absents[] = (int)$value->absent;
                $presants[] = (int)$value->present;
            }

            $today = date("Y-m-d");
            $parameters = array(
                ":dt" => $today,
                ":sb" => $sub_institute_id,
                ":syear" => $syear,
            );

            $fees_chart_data = DB::table('fees_collect as fc')
                ->join('tblstudent_enrollment as se', function ($join) use ($syear) {
                    $join->whereRaw("se.student_id = fc.student_id and se.syear = " . $syear);
                })
                ->join('standard as s', function ($join) {
                    $join->whereRaw("s.id = se.standard_id");
                })
                ->selectRaw("sum(fc.amount) amount,s.name")
                ->whereRaw('DATE_FORMAT(fc.receiptdate, "%Y-%m-%d") = ' . $today . ' and fc.sub_institute_id = ' . $sub_institute_id)
                ->groupBy('se.standard_id')->get()->toArray();

            $parameters = array(
                ":syear" => $syear,
                ":sb" => $sub_institute_id,
            );

            $student_chart_data = DB::table('tblstudent_enrollment as se')
                ->join('standard as s', function ($join) {
                    $join->whereRaw("s.id = se.standard_id");
                })
                ->selectRaw("count(se.student_id) total_student,s.name")
                ->where('se.sub_institute_id', $sub_institute_id)
                ->where('se.syear', $syear)
                ->groupByRaw('se.standard_id,s.id')->orderBy('s.sort_order')->get()->toArray();

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

            $fees_chart1_cash_data = DB::table('fees_collect as fc')
                ->join('tblstudent_enrollment as se', function ($join) use ($syear) {
                    $join->whereRaw("se.student_id = fc.student_id and se.syear = " . $syear);
                })
                ->join('standard as s', function ($join) {
                    $join->whereRaw("s.id = se.standard_id");
                })
                ->selectRaw("fc.amount,s.name")
                ->whereRaw('DATE_FORMAT(fc.receiptdate, "%Y-%m-%d") = ' . $today . ' and fc.sub_institute_id = ' . $sub_institute_id)
                ->where('payment_mode', 'cash')
                ->groupBy('se.standard_id')->get()->toArray();

            $today = date("Y-m-d");
            $parameters = array(
                ":dt" => $today,
                ":sb" => $sub_institute_id,
                ":syear" => $syear,
                ":mode" => "cheque",
            );

            $fees_chart1_cheque_data = DB::table('fees_collect as fc')
                ->join('tblstudent_enrollment as se', function ($join) use ($syear) {
                    $join->whereRaw("se.student_id = fc.student_id and se.syear = " . $syear);
                })
                ->join('standard as s', function ($join) {
                    $join->whereRaw("s.id = se.standard_id");
                })
                ->selectRaw("fc.amount,s.name")
                ->whereRaw('DATE_FORMAT(fc.receiptdate, "%Y-%m-%d") = ' . $today . ' and fc.sub_institute_id = ' . $sub_institute_id)
                ->where('payment_mode', 'cheque')
                ->get()->toArray();

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

            $fees_chart2_bkoff_data = DB::table('tblstudent as s')
                ->join('tblstudent_enrollment as se', function ($join) use ($syear) {
                    $join->whereRaw("se.student_id = s.id");
                })->join('academic_section as g', function ($join) {
                    $join->whereRaw("g.id = se.grade_id");
                })->join('standard as st', function ($join) {
                    $join->whereRaw("st.id = se.standard_id");
                })->leftJoin('division as d', function ($join) {
                    $join->whereRaw("d.id = se.section_id");
                })->join('fees_breackoff as fb', function ($join) use ($syear, $sub_institute_id) {
                    $join->whereRaw("fb.syear = " . $syear . " AND fb.admission_year = s.admission_year AND fb.quota = se.student_quota AND
                        fb.grade_id = se.grade_id AND fb.standard_id = se.standard_id AND fb.sub_institute_id = " . $sub_institute_id);
                })
                ->selectRaw("SUM(fb.amount) amt,st.name")
                ->where('s.sub_institute_id', $sub_institute_id)
                ->where('se.syear', $syear)->groupBy('st.id')->orderBy('st.id')
                ->get()->toArray();

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

            $fees_chart2_fees_data =
                DB::table('tblstudent as s')
                ->join('tblstudent_enrollment as se', function ($join) use ($syear) {
                    $join->whereRaw("se.student_id = s.id");
                })->join('academic_section as g', function ($join) {
                    $join->whereRaw("g.id = se.grade_id");
                })->join('standard as st', function ($join) {
                    $join->whereRaw("st.id = se.standard_id");
                })->leftJoin('division as d', function ($join) {
                    $join->whereRaw("d.id = se.section_id");
                })->join('fees_collect as fc', function ($join) use ($syear, $sub_institute_id) {
                    $join->whereRaw("fc.student_id = s.id AND fc.sub_institute_id = " . $sub_institute_id . " AND fc.syear = " . $syear);
                })
                ->selectRaw("SUM(fc.amount)+ SUM(fc.fees_discount) amount,st.name")
                ->where('s.sub_institute_id', $sub_institute_id)
                ->groupBy('st.id')->orderBy('st.id')
                ->get()->toArray();

            $paid_data = "[";
            foreach ($fees_chart2_fees_data as $id => $arr) {
                $paid_data .= $arr->amount . ",";
            }
            $paid_data = rtrim($paid_data, ",");
            $paid_data .= "]";


            $academicSections = DB::table('academic_section')->where('sub_institute_id', $sub_institute_id)->get()->toArray();

            $academicSections = array_map(function ($value) {
                return (array)$value;
            }, $academicSections);

            $gradeIds = '';
            foreach ($academicSections as $key => $value) {
                $gradeIds .= $value['id'] . ',';
            }

            $standards = DB::table('standard')->whereIn('grade_id', explode(',', rtrim($gradeIds, ",")))->get()->toArray();

            $standards = array_map(function ($value) {
                return (array)$value;
            }, $standards);

            $standardsArray = array();

            foreach ($standards as $key => $value) {
                $standardsArray[$value['grade_id']][] = $standards[$key];
            }

            $chartStudents = DB::table('tblstudent as s')
                ->join('tblstudent_enrollment as se', function ($join) {
                    $join->whereRaw("s.id = se.student_id");
                })
                ->selectRaw('s.id,se.grade_id,se.standard_id')
                ->where('s.sub_institute_id', $sub_institute_id)
                ->where('grade_id', '!=', '')->where('standard_id', '!=', '')->get()->toArray();

            $chartAS = [];
            $chartS = [];

            foreach ($chartStudents as $k => $v) {
                $chartAs[$v->grade_id][] = $v->id;
                $chartS[$v->standard_id][] = $v->id;
            }

            $chartFAs = [];
            $chartFS = [];

            $chartFees = DB::table('fees_collect as fc')
                ->join('tblstudent_enrollment as se', function ($join) use ($syear) {
                    $join->whereRaw("se.student_id = fc.student_id AND se.syear = " . $syear);
                })->join('standard as s', function ($join) {
                    $join->whereRaw("s.id = se.standard_id");
                })
                ->selectRaw("fc.amount,s.name,se.grade_id,se.standard_id")
                ->where('s.sub_institute_id', $sub_institute_id)->get()->toArray();

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
                if (isset($standardsArray[$v['id']])) {

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
                if (isset($standardsArray[$v['id']])) {

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
                if (isset($standardsArray[$v['id']])) {

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
                if (isset($standardsArray[$v['id']])) {

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
                if (isset($standardsArray[$v['id']])) {

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

            $date = date('Y-m-d');

            $date15 = date('Y-m-d', strtotime($date . ' +15 day'));

            $users = tbluserModel::selectRaw("count(id) as users")->where([
                'sub_institute_id' => $sub_institute_id, 'status' => "1",
            ])->get()->toArray();

            $students = DB::table('tblstudent as ts')
                ->join('tblstudent_enrollment as se', function ($join) {
                    $join->whereRaw("se.student_id = ts.id AND se.sub_institute_id = se.sub_institute_id");
                })->join('standard as s', function ($join) {
                    $join->whereRaw("s.id = se.standard_id AND se.sub_institute_id = s.sub_institute_id");
                })
                ->selectRaw("COUNT(ts.id) students")
                ->where('ts.sub_institute_id', $sub_institute_id)
                ->where('se.syear', $syear)
                ->whereNull('se.end_date')->get()->toArray();

            $total_admission = DB::table('admission_enquiry')
                ->selectRaw("COUNT(id) as total_admissions")
                ->where('sub_institute_id', $sub_institute_id)
                ->where('syear', $syear)
                ->get()->toArray();

            $fees_collects = fees_collect::where([
                'sub_institute_id' => $sub_institute_id,
                'syear' => $syear,
                'is_deleted' => "N",
            ])
                ->whereDate('receiptdate', $date)
                ->sum('amount');

            $other_fees_collects = DB::table('fees_paid_other')
                ->where([
                    'sub_institute_id' => $sub_institute_id,
                    'syear' => $syear,
                    'is_deleted' => 'N'
                ])
                ->whereDate('receiptdate', $date)
                ->sum('actual_amountpaid');

            $other_fees_collects = json_decode(json_encode($other_fees_collects), true);
            $parentCommunication = ParentCommunication::with(['student' => function ($query) {
                $query->select('id', 'first_name', 'last_name', 'image');
            }])
                ->where('sub_institute_id', $sub_institute_id)
                ->where('date_', $date)
                ->orderBy('id', 'desc')
                ->limit(10)
                ->get()
                ->toArray();

            $studentBirthdays = tblStudent::selectRaw("CONCAT_WS(' ',first_name, middle_name, last_name) as student_name, standard.name as standard_name, division.name as division_name, DATE_FORMAT(dob, '%d-%m-%Y') as dob")
                ->join('tblstudent_enrollment as ts', function ($join) use ($syear) {
                    $join->on('tblstudent.id', '=', 'ts.student_id')
                        ->where('ts.syear', $syear);
                })
                ->join('standard', 'ts.standard_id', '=', 'standard.id')
                ->join('division as d', 'ts.section_id', '=', 'd.id')
                ->where('tblstudent.sub_institute_id', $sub_institute_id)
                ->whereNull('ts.end_date')
                ->whereRaw("DATE_FORMAT(tblstudent.dob, '%m-%d') >= DATE_FORMAT(NOW(), '%m-%d') and DATE_FORMAT(tblstudent.dob, '%m-%d') <= DATE_FORMAT((NOW() + INTERVAL +7 DAY), '%m-%d')")
                ->orderByRaw("DATE_FORMAT(tblstudent.dob, '%m-%d')")
                ->get()
                ->toArray();


            $teacherBirthdays = DB::table('tbluser')
                ->join('tbluserprofilemaster as tu', 'tbluser.user_profile_id', '=', 'tu.id')
                ->selectRaw("CONCAT_WS(' ',tbluser.first_name, tbluser.middle_name, tbluser.last_name) as teacher_name, tu.name as designation, tbluser.mobile as contact_number, DATE_FORMAT(tbluser.birthdate, '%d-%m-%Y') AS birthdate")
                ->where('tbluser.sub_institute_id', $sub_institute_id)
                ->where('tbluser.status', '!=', 0)
                ->whereBetween(DB::raw("DATE_FORMAT(tbluser.birthdate, '%m-%d')"), [now()->format('m-d'), now()->addDays(7)->format('m-d')])
                ->orderByRaw("DATE_FORMAT(tbluser.birthdate, '%m-%d')")
                ->get()
                ->toArray();
            $calendarEvents = DB::table('calendar_events')->where('sub_institute_id', $sub_institute_id)
                ->where('school_date', '>=', $date)->where('school_date', '<=', $date15)->get()->toArray();

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


    public function siteMap(Request $request)
    {
        $type = $request->input('type');
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $user_id = $request->session()->get('user_id');
        $rightsQuery = DB::table('tbluser as u')
            ->leftJoin('tblindividual_rights as i', function ($join) {
                $join->on('u.id', '=', 'i.user_id')
                    ->whereColumn('u.sub_institute_id', '=', 'i.sub_institute_id');
            })
            ->leftJoin('tblgroupwise_rights as g', function ($join) {
                $join->on('u.user_profile_id', '=', 'g.profile_id')
                    ->whereColumn('u.sub_institute_id', '=', 'g.sub_institute_id');
            })
            ->join('tblmenumaster as m', function ($join) use ($sub_institute_id) {
                $join->on(function ($query) {
                    $query->whereColumn('i.menu_id', '=', 'm.id')
                        ->orWhereColumn('g.menu_id', '=', 'm.id');
                })->whereRaw("FIND_IN_SET(" . $sub_institute_id . ", m.sub_institute_id)");
            })
            ->selectRaw('GROUP_CONCAT(distinct m.id) AS MID')
            ->where([
                ['u.sub_institute_id', '=', $sub_institute_id],
                ['u.id', '=', $user_id],
            ])
            ->get()
            ->toArray();


        $rightsQuery = array_map(function ($value) {
            return (array)$value;
        }, $rightsQuery);

        $rightsMenusIds = 0;

        if (isset($rightsQuery['0']['MID'])) {
            $rightsMenusIds = $rightsQuery['0']['MID'];
        }
        $rightsMenusIds = rtrim($rightsMenusIds, ',');//RAJESH
        $data = tblmenumasterModel::where([
            'parent_menu_id' => "0",
            'level' => "1",
            'status' => 1,
        ])
        ->whereIn('id', explode(',', $rightsMenusIds))
        ->whereRaw("find_in_set('$sub_institute_id', sub_institute_id)")
        ->orderBy('sort_order')
        ->get()
        ->toArray();
    

        $subMenuData = tblmenumasterModel::where('parent_menu_id', '!=', 0)
            ->whereRaw("find_in_set('$sub_institute_id',sub_institute_id)")
            ->where('level',2 )
            ->whereIn('id',explode(',',$rightsMenusIds))
            ->where("status",1)
            ->orderBy('sort_order')->get()->toArray();

        $i = 0;
        foreach ($subMenuData as $key => $value) {
            $finalSubMenu[$value['parent_menu_id']][$i] = $subMenuData[$key];
            $i++;
        }

        $subChildMenuData = tblmenumasterModel::where('parent_menu_id', '!=', 0)
            ->whereRaw("find_in_set('$sub_institute_id',sub_institute_id)")
            ->where('level',3)
            ->whereIn('id',explode(',',$rightsMenusIds))
            ->where("status",1)
            ->orderBy('sort_order')->get()->toArray();

        $i = 0;
        foreach ($subChildMenuData as $key => $value) {
            $finalSubChildMenu[$value['parent_menu_id']][$i] = $subChildMenuData[$key];
            $i++;
        }


        $res['status_code'] = 1;
        $res['message'] = "Success";
        $res['groupwisemenuMaster'] = $data;
        $res['groupwisesubmenuMaster'] = $finalSubMenu;
        $res['groupwiseSubsubmenuMaster'] = $finalSubChildMenu;

        return is_mobile($type, 'sitemap', $res, 'view');
    }

    public function privacyPolicy(Request $request)
    {
        $type = $request->input('type');

        return is_mobile($type, 'privacypolicy', $request, 'view');
    }

    public function termAndCondition(Request $request)
    {
        $type = $request->input('type');

        return is_mobile($type, 'term_&_condition', $request, 'view');
    }

    public function otherPolicy(Request $request)
    {
        $type = $request->input('type');

        return is_mobile($type, 'other_policy', $request, 'view');
    }

    public function knowledge_base(Request $request)
    {
        $type = $request->input('type');

        $data = DB::table('knowledge_base')->where('status', 1)->get()->toArray();

        $data = array_map(function ($value) {
            return (array)$value;
        }, $data);

        $res['status_code'] = 1;
        $res['message'] = "Success";
        $res['data'] = $data;

        return is_mobile($type, "kb", $res, "view");
    }

    public function knowledge_base_detail(Request $request, $id, $title)
    {
        $type = $request->input('type');

        $data = DB::table('knowledge_base_detail as kbd')
            ->join('knowledge_base as kb', 'kbd.kb_id = kb.id')
            ->selectRaw("kbd.*,kb.name as kname")
            ->where('kb.status', '=', 1)->get()->toArray();

        $data = array_map(function ($value) {
            return (array)$value;
        }, $data);

        $res['status_code'] = 1;
        $res['message'] = "Success";
        $res['data'] = $data;

        return is_mobile($type, "kbd", $res, "view");
    }

    public function formatBytes($bytes, $precision = 2)
    {
        $units = array('B', 'KB', 'MB', 'GB', 'TB');

        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);

        // Uncomment one of the following alternatives
        // $bytes /= pow(1024, $pow);
        $bytes /= (1 << (10 * $pow));

        return round($bytes, $precision) . ' ' . $units[$pow];
    }

    public function setup_details(Request $request)
    {
        $type = "";
        // return session();exit;
        $sub_institute_id = session()->get('sub_institute_id');
        $user_id = $request->session()->get('user_id');

        $rightsQuery = DB::table('tbluser as u')
        ->leftJoin('tblindividual_rights as i', function ($join) {
            $join->on('u.id', '=', 'i.user_id')
                ->whereColumn('u.sub_institute_id', '=', 'i.sub_institute_id');
        })
        ->leftJoin('tblgroupwise_rights as g', function ($join) {
            $join->on('u.user_profile_id', '=', 'g.profile_id')
                ->whereColumn('u.sub_institute_id', '=', 'g.sub_institute_id');
        })
        ->join('tblmenumaster as m', function ($join) use ($sub_institute_id) {
            $join->on(function ($query) use ($sub_institute_id) {
                $query->whereColumn('i.menu_id', '=', 'm.id')
                    ->orWhereColumn('g.menu_id', '=', 'm.id');
            })->whereIn($sub_institute_id, DB::raw("FIND_IN_SET(m.sub_institute_id, m.sub_institute_id)"));
        })
        ->selectRaw('GROUP_CONCAT(distinct m.id) AS MID')
        ->where('u.sub_institute_id', $sub_institute_id)
        ->where('u.id', $user_id)
        ->get()
        ->toArray();    

        $rightsQuery = array_map(function ($value) {
            return (array)$value;
        }, $rightsQuery);

        $rightsMenusIds = 0;

        if (isset($rightsQuery['0']['MID'])) {
            $rightsMenusIds = $rightsQuery['0']['MID'];
        }
        $rightsMenusIds = rtrim($rightsMenusIds, ',');//RAJESH

        $data = tblmenumasterModel::whereRaw("find_in_set('$sub_institute_id',sub_institute_id)")->where('status',1)
            ->orderBy('sort_order')->groupBy('menu_title')->get()->toArray();

        $databaseTables = tblmenumasterModel::select('database_table')
            ->whereRaw("find_in_set('$sub_institute_id', sub_institute_id)")->where('status',1)
            ->pluck('database_table')
            ->toArray();

       // Check if the specified sub_institute exists in the tables
        $subInstituteExists = [];

        foreach ($databaseTables as $tableName) {
            if (Schema::hasTable($tableName)) {
            // Check if the table has the sub_institute_id column
                if (Schema::hasColumn($tableName, 'sub_institute_id')) {
                    $exists = DB::table($tableName)
                        ->where('sub_institute_id', $sub_institute_id)
                        ->exists();
                } else {
                // If the sub_institute_id column doesn't exist, consider it as not found
                    $exists = false;
                }
            } else {
                $exists = false;
            }

            $subInstituteExists[$tableName] = $exists;
        }
    // return $subInstituteExists;exit;
        $master = tblmenumasterModel::whereRaw("find_in_set('$sub_institute_id',sub_institute_id)")->where('status',1) ->where('menu_type','=','MASTER')
            ->orderBy('sort_order')->get()->toArray();
        $i = 0;

        foreach ($master as $key => $value) {
                // print_r($value);
            $mastermenu[$value['menu_title']][$i] = $master[$key];
            $i++;
        }
        $entry = tblmenumasterModel::where('parent_menu_id', '!=', 0)
            ->whereRaw("find_in_set('$sub_institute_id',sub_institute_id)")->where('status',1)->where("menu_type","=","ENTRY")
            ->orderBy('sort_order')->get()->toArray();

        $i = 0;
        foreach ($entry as $key => $value) {
            $finalSubMenu[$value['menu_title']][$i] = $entry[$key];
            $i++;
        }

        $report = tblmenumasterModel::where('parent_menu_id', '!=', 0)
            ->whereRaw("find_in_set('$sub_institute_id',sub_institute_id)")->where('status',1)->where("menu_type","=","REPORT")
            ->orderBy('sort_order')->get()->toArray();

        $i = 0;
        foreach ($report as $key => $value) {
            $finalSubChildMenu[$value['menu_title']][$i] = $report[$key];
            $i++;
        }
        $database_table = tblmenumasterModel::select('database_table')->whereRaw("find_in_set('$sub_institute_id',sub_institute_id)")->where('status',1)->get();

        $res['status_code'] = 1;
        $res['message'] = "Success";
        $res['head'] = $data;
        $res['table_name'] = $subInstituteExists;
        $res['groupwisemenuMaster'] = $mastermenu;
        $res['groupwisesubmenuMaster'] = $finalSubMenu ?? [];
        $res['groupwiseSubsubmenuMaster'] = $finalSubChildMenu ?? [];
        $rr = [];

        return is_mobile($type, "setup_institute_details", $res, 'view');
    }

    public function ajaxMenuSession_setup(Request $request)
    {
        $type = $request->input("type");
        $menu_id = $request->input("menu_id");

        if ($menu_id != '') {
            $request->session()->put('right_menu_id', $menu_id);

            $res['status_code'] = 1;
            $res['message'] = "Success";

        } else {
            $res['status_code'] = 0;
            $res['message'] = "Parameter Missing";
        }

        return is_mobile($type, "setup_institute_details", $res, 'view');
    }

}
 