<?php

namespace App\Http\Controllers\fees\other_fees_cancel;

use App\Http\Controllers\Controller;
use App\Models\fees\bank_master\bankmasterModel;
use App\Models\fees\other_fees_cancel\other_fees_cancel;
use App\Models\fees\other_fees_collect\other_fees_collect;
use App\Models\fees\other_fees_title\other_fees_title;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use function App\Helpers\is_mobile;

class other_fees_cancel_controller extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @param Request $request
     * @return false|Application|Factory|View|RedirectResponse|string
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function index(Request $request)
    {
        $type = $request->input('type');
        $submit = $request->input('submit');
        $sub_institute_id = session()->get('sub_institute_id');
        $syear = session()->get('syear');
        $res['status'] = 1;
        $res['message'] = "Success";

        $other_fees_title = other_fees_title::where(['sub_institute_id' => $sub_institute_id, 'syear' => $syear, 'status' => '1'])->get()->toArray();
        $res['other_fees_title'] = $other_fees_title;

        return is_mobile($type, "fees/other_fees_cancel/show_other_fees_cancel", $res, "view");
    }

    /**
     * Show the form for creating a new resource.
     *
     * @param Request $request
     * @return Response
     */
    public function create(Request $request)
    {
        $grade = $request->input('grade');
        $standard = $request->input('standard');
        $division = $request->input('division');
        $enrollment_no = $request->input('enrollment_no');
        $first_name = $request->input('first_name');
        $last_name = $request->input('last_name');
        $mobile_no = $request->input('mobile_no');
        $uniqueid = $request->input('uniqueid');
        $other_fees_title_selected = $request->input('other_fees_title');
        $type = $request->input('type');
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $syear = $request->session()->get('syear');
        $marking_period_id = session()->get('term_id');
        $extraSearchArray = [];
        $extraSearchArrayRaw = " 1=1 ";

        if ($grade != '') {
            $extraSearchArray['tblstudent_enrollment.grade_id'] = $grade;
        }

        if ($standard != '') {
            $extraSearchArray['tblstudent_enrollment.standard_id'] = $standard;
        }

        if ($division != '') {
            $extraSearchArray['tblstudent_enrollment.section_id'] = $division;
        }

        if ($enrollment_no != '') {
            $extraSearchArray['tblstudent.enrollment_no'] = $enrollment_no;
        }

        if ($mobile_no != '') {
            $extraSearchArray['tblstudent.mobile'] = $mobile_no;
        }

        if ($uniqueid != '') {
            $extraSearchArray['tblstudent.uniqueid'] = $uniqueid;
        }

        if ($other_fees_title_selected != '') {
            $extraSearchArray['fees_other_collection.deduction_head_id'] = $other_fees_title_selected;
        }

        if ($first_name != '') {
            $extraSearchArrayRaw .= "  AND tblstudent.first_name like '%" . $first_name . "%' ";
        }

        if ($last_name != '') {
            $extraSearchArrayRaw .= "  AND tblstudent.last_name like '%" . $last_name . "%' ";
        }

        $extraSearchArrayRaw .= " AND tblstudent_enrollment.end_date IS NULL ";
        $extraSearchArrayRaw .= " AND fees_other_collection.is_deleted = 'N' ";
        $extraSearchArray['tblstudent_enrollment.syear'] = $syear;
        $extraSearchArray['tblstudent.sub_institute_id'] = $sub_institute_id;
        $extraSearchArray['tblstudent_enrollment.sub_institute_id'] = $sub_institute_id;
        $extraSearchArray['student_quota.sub_institute_id'] = $sub_institute_id;
        $extraSearchArray['fees_other_collection.syear'] = $syear;
        $extraSearchArray['fees_other_collection.sub_institute_id'] = $sub_institute_id;
        $extraSearchArray['fees_other_head.sub_institute_id'] = $sub_institute_id;

        $studentData = other_fees_collect::selectRaw("fees_other_collection.id,fees_other_collection.deduction_head_id,fees_other_collection.deduction_amount,fees_other_collection.receipt_id,fees_other_head.display_name,CONCAT_WS(' ',tblstudent.first_name,tblstudent.middle_name,tblstudent.last_name) AS stu_name,
tblstudent.enrollment_no,tblstudent.mobile,standard.name AS std_name,division.name AS div_name,student_quota.title AS stu_quota,fees_other_collection.paid_fees_html,tblstudent.id as student_id")
            ->join('fees_other_head', 'fees_other_head.id', '=', 'fees_other_collection.deduction_head_id')
            ->join('tblstudent', 'tblstudent.id', '=', 'fees_other_collection.student_id')
            ->join('tblstudent_enrollment', 'tblstudent.id', '=', 'tblstudent_enrollment.student_id')
            ->join('academic_section', 'academic_section.id', '=', 'tblstudent_enrollment.grade_id')
            ->join('standard', function ($join) use ($marking_period_id) {
                $join->on('standard.id', '=', 'tblstudent_enrollment.standard_id')
                    ->when($marking_period_id, function ($query) use ($marking_period_id) {
                        $query->where('standard.marking_period_id', $marking_period_id);
                    });
            })
            ->join('division', 'division.id', '=', 'tblstudent_enrollment.section_id')
            ->join('student_quota', 'student_quota.id', '=', 'tblstudent_enrollment.student_quota')
            ->where($extraSearchArray)
            ->whereRaw($extraSearchArrayRaw)
            ->get()
            ->toArray();

        $other_fees_title = other_fees_title::where(['sub_institute_id' => $sub_institute_id, 'syear' => $syear, 'status' => '1'])->get()->toArray();

        $res['status_code'] = 1;
        $res['message'] = "Success";
        $res['student_data'] = $studentData;
        $res['other_fees_title_selected'] = $other_fees_title_selected;
        $res['grade_id'] = $grade;
        $res['standard_id'] = $standard;
        $res['division_id'] = $division;
        $res['enrollment_no'] = $enrollment_no;
        $res['first_name'] = $first_name;
        $res['last_name'] = $last_name;
        $res['mobile_no'] = $mobile_no;
        $res['uniqueid'] = $uniqueid;
        $res['other_fees_title'] = $other_fees_title;
        $res['bank_data'] = bankmasterModel::get()->toArray();

        return is_mobile($type, "fees/other_fees_cancel/show_other_fees_cancel", $res, "view");
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @return false|Application|Factory|View|RedirectResponse|Response|string
     */
    public function store(Request $request)
    {
        // dd($request);
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $syear = $request->session()->get('syear');
        $type = $request->get('type');
        $students = $request->get('students');
        $division_id = $request->get('division_id');
        $standard_id = $request->get('standard_id');
        $deduction_head_id = $request->get('other_fees_title');
        $date_of_cancel = $request->input('date_of_cancel');
        $reason_of_cancel = $request->input('reason_of_cancel');
        $created_by = session()->get('user_id');
        $created_ip = $_SERVER['REMOTE_ADDR'];

        foreach ($students as $key => $id) {

            $getdata = other_fees_collect::where(['id' => $id, 'syear' => $syear, 'sub_institute_id' => $sub_institute_id, 'is_deleted' => 'N'])->get()->toArray();
            // dd($getdata);
            $cancelFeesArray = array(
                'receipt_id' => $getdata[0]['receipt_id'],
                'syear' => $syear,
                'sub_institute_id' => $sub_institute_id,
                'fees_other_collection_id' => $id,
                'student_id' => $getdata[0]['student_id'],
                'deduction_head_id' => $getdata[0]['deduction_head_id'],
                'cancellation_date' => $date_of_cancel[$id],
                'cancellation_remarks' => $reason_of_cancel[$id],
                'cancellation_amount' => $getdata[0]['deduction_amount'],
                'created_by' => $created_by,
                'created_on' => now(),
                'created_ip' => $created_ip
            );
            other_fees_cancel::insert($cancelFeesArray);

            DB::table('fees_other_collection')->where(['id' => $id, 'receipt_id' => $getdata[0]['receipt_id'], 'syear' => $syear, 'sub_institute_id' => $sub_institute_id])->update(['is_deleted' => 'Y']);
        }

        $res['status'] = "1";
        $res['message'] = "Other fees cancel successfully";

        return is_mobile($type, "other_fees_cancel.index", $res);
    }

    /**
     * Display the specified resource.
     *
     * @param int $id
     * @return void
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param int $id
     * @return void
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @param int $id
     * @return Response
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param int $id
     * @return Response
     */
    public function destroy($id)
    {
        //
    }

}
