<?php

namespace App\Http\Controllers\hostel_management;

use App\Http\Controllers\Controller;
use App\Models\hostel_management\admission_category_masterModel;
use App\Models\hostel_management\hostel_masterModel;
use App\Models\student\tblstudentModel;
use App\Models\user\tbluserModel;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use function App\Helpers\is_mobile;


class hostel_reportController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index(Request $request)
    {
        $type = $request->input('type');
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $tblhostelRoomAllocationController = new tblhostelRoomAllocationController;
        $profiles = $tblhostelRoomAllocationController->userProfileList($request);

        $admissionCategory = $this->admissionCategoryList($request);

        $hostel = $this->hostelList($request);

        $res['status_code'] = 1;
        $res['message'] = "Success";
        $res['profiles'] = $profiles;
        $res['hostelList'] = $hostel;
        $res['admissionCategoryList'] = $admissionCategory;

        return is_mobile($type, "hostel_management/hostel_report", $res, "view");
    }

    public function showHostelReport(Request $request)
    {
        $type = $request->input('type');
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $syear = $request->session()->get('syear');
        $grade_id = $request->input("grade");
        $standard_id = $request->input("standard");
        $division_id = $request->input("division");
        $gender = $request->input("gender");
        $user = $request->input("user");
        $admissionCategory = $request->input("admissionCategory");
        $hostel = $request->input("hostel");
        $room = $request->input("room");

        $tblhostelRoomAllocationController = new tblhostelRoomAllocationController;
        if ($hostel != '') {
            $request['hostel_id'] = $hostel;
        }

        $hostel_room_masterController = new hostel_room_masterController;

        $roomList = $hostel_room_masterController->hostelWiseRoomList($request);

        $rooms = array();

        foreach ($roomList as $rid => $rdata) {
            $rooms[$rdata['id']] = $rdata['room_name'];
        }

        $profiles = $tblhostelRoomAllocationController->userProfileList($request);

        $request['user_profile_id'] = $user;
        $userProfile = $tblhostelRoomAllocationController->userProfileList($request);
        if ($userProfile[0]['name'] == 'Student' || $userProfile[0]['name'] == 'student' || $userProfile[0]['name'] == 'STUDENT') {
            $data = $this->studentsForAllocation($request);
        } else {
            $data = $this->staffForAllocation($request);
        }

        if (count($data) == 0) {
            $res['status_code'] = 0;
            $res['message'] = "No ".$userProfile[0]['name']." Found";

            return is_mobile($type, "hostel_report.index", $res);
        }

        $tableHeads = array_keys($data[0]);

        $key1 = array_search("admission_category_id", $tableHeads);
        $key2 = array_search("hostel_id", $tableHeads);
        $key3 = array_search("room_id", $tableHeads);
        $key4 = array_search("bed_no", $tableHeads);
        $key5 = array_search("locker_no", $tableHeads);
        $key6 = array_search("table_no", $tableHeads);
        $key7 = array_search("bedsheet_no", $tableHeads);

        unset($tableHeads[$key1]);
        unset($tableHeads[$key2]);
        unset($tableHeads[$key3]);
        unset($tableHeads[$key4]);
        unset($tableHeads[$key5]);
        unset($tableHeads[$key6]);
        unset($tableHeads[$key7]);

        $admissionCategoryList = $this->admissionCategoryList($request);

        $hostelList = $this->hostelList($request);

        $res['status_code'] = 1;
        $res['message'] = "Success";
        $res['profiles'] = $profiles;
        $res['hostelList'] = $hostelList;
        $res['roomList'] = $rooms;
        $res['userProfile'] = $userProfile;
        $res['gender'] = $gender;
        if ($admissionCategory != '') {
            $res['admissionCategory'] = $admissionCategory;
        }
        if ($hostel != '') {
            $res['hostel'] = $hostel;
        }
        if ($room != '') {
            $res['room'] = $room;
        }

        $res['admissionCategoryList'] = $admissionCategoryList;
        $res['tableHeads'] = $tableHeads;
        $res['data'] = $data;

        return is_mobile($type, "hostel_management/hostel_report", $res, "view");
    }

    public function admissionCategoryList(Request $request)
    {
        $sub_institute_id = $request->session()->get("sub_institute_id");
        $admissionCategoryList = admission_category_masterModel::select('id', 'title')
            ->where(['sub_institute_id' => $sub_institute_id])
            ->get()->toArray();

        $admissionCategory = [];
        foreach ($admissionCategoryList as $id => $title) {
            $admissionCategory[$title['id']] = $title['title'];
        }

        return $admissionCategory;
    }

    public function hostelList(Request $request)
    {
        $sub_institute_id = $request->session()->get("sub_institute_id");
        $hostelList = hostel_masterModel::select('id', 'name')->where(['sub_institute_id' => $sub_institute_id])
            ->get()->toArray();

        $hostel = [];
        foreach ($hostelList as $id => $title) {
            $hostel[$title['id']] = $title['name'];
        }

        return $hostel;
    }

    public function roomIndex(Request $request)
    {
        $type = $request->input('type');
        $sub_institute_id = $request->session()->get('sub_institute_id');

        $hostel = $this->hostelList($request);

        $res['status_code'] = 1;
        $res['message'] = "Success";
        $res['hostelList'] = $hostel;

        return is_mobile($type, "hostel_management/room_report", $res, "view");
    }

    public function roomReport(Request $request)
    {
        $type = $request->input('type');
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $syear = $request->session()->get('syear');
        $hostel = $request->input("hostel");
        $room = $request->input("room");

        $hostelList = $this->hostelList($request);

        if ($hostel != '') {
            $res['hostel'] = $hostel;
        }
        if ($room != '') {
            $res['room'] = $room;
        }

        $data = DB::table('hostel_room_master as hrm')
            ->join('hostel_floor_master as hfm', function ($join) {
                $join->whereRaw('hrm.floor_id = hfm.id AND hrm.sub_institute_id = hfm.sub_institute_id');
            })->join('hostel_building_master as hbm', function ($join) {
                $join->whereRaw('hfm.building_id = hbm.id AND hbm.sub_institute_id = hrm.sub_institute_id');
            })->join('hostel_master as hm', function ($join) {
                $join->whereRaw('hbm.hostel_id = hm.id AND hbm.sub_institute_id = hm.sub_institute_id');
            })->join('hostel_type_master as tht', function ($join) {
                $join->whereRaw('hm.hostel_type_id = tht.id AND tht.sub_institute_id = hm.sub_institute_id');
            })->selectRaw('hrm.id,hrm.room_name,hfm.floor_name,hbm.building_name,hm.name,tht.hostel_type')
            ->where('hrm.sub_institute_id', $sub_institute_id)
            ->whereRaw('hrm.id NOT IN (SELECT room_id FROM hostel_room_allocation WHERE sub_institute_id = '.$sub_institute_id.'
                AND syear = '.$syear.')')
            ->where(function ($q) use ($hostel, $room) {
                if ($hostel != '') {
                    $q->where('hm.id', $hostel);
                }
                if ($room != '') {
                    $q->where('hrm.id', $room);
                }
            })
            ->get()->toArray();

        $data = json_decode(json_encode($data), true);

        if (count($data) == 0) {
            $res['status_code'] = 0;
            $res['message'] = "No Rooms Available";

            return is_mobile($type, "room_report", $res);
        }

        $res['status_code'] = 1;
        $res['message'] = "Success";
        $res['hostelList'] = $hostelList;
        $res['data'] = $data;

        return is_mobile($type, "hostel_management/room_report", $res, "view");
    }


    public function studentsForAllocation(Request $request)
    {
        $grade_id = $request->input("grade");
        $standard_id = $request->input("standard");
        $division_id = $request->input("division");
        $gender = $request->input("gender");
        $type = $request->input('type');
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $syear = $request->session()->get('syear');
        $admissionCategory = $request->input("admissionCategory");
        $hostel = $request->input("hostel");
        $room = $request->input("room");
        $marking_period_id = session()->get('term_id');
        $extraSearchArray = [];
        $extraSearchArray['tblstudent_enrollment.sub_institute_id'] = $sub_institute_id;
        $extraSearchArray['tblstudent_enrollment.syear'] = $syear;
        $extraSearchArray['tblstudent.status'] = 1;
        if ($grade_id != '') {
            $extraSearchArray['tblstudent_enrollment.grade_id'] = $grade_id;
        }
        if ($standard_id != '') {
            $extraSearchArray['tblstudent_enrollment.standard_id'] = $standard_id;
        }
        if ($division_id != '') {
            $extraSearchArray['tblstudent_enrollment.section_id'] = $division_id;
        }
        if ($gender != '') {
            $extraSearchArray['tblstudent.gender'] = $gender;
        }
        if ($admissionCategory != '') {
            $extraSearchArray['hostel_room_allocation.admission_category_id'] = $admissionCategory;
        }
        if ($hostel != '') {
            $extraSearchArray['hostel_room_allocation.hostel_id'] = $hostel;
        }
        if ($room != '') {
            $extraSearchArray['hostel_room_allocation.room_id'] = $room;
        }

        return tblstudentModel::selectRaw('CONCAT_WS(" ",tblstudent.first_name,tblstudent.middle_name,tblstudent.last_name) as name')
            ->select('tblstudent.id', 'tblstudent.enrollment_no', 'tblstudent.mobile', 'standard.name as standard',
                'division.name as division', 'academic_section.title as grade')
            ->selectRaw(" CASE WHEN tblstudent.gender = 'F' THEN 'Female' ELSE 'Male' END as gender")
            ->selectRaw("hostel_room_allocation.admission_category_id, hostel_room_allocation.hostel_id, hostel_room_allocation.room_id, hostel_room_allocation.bed_no,hostel_room_allocation.locker_no,hostel_room_allocation.table_no,hostel_room_allocation.bedsheet_no")
            ->join('tblstudent_enrollment', 'tblstudent.id', '=', 'tblstudent_enrollment.student_id')
            ->join('academic_section', 'academic_section.id', '=', 'tblstudent_enrollment.grade_id')
            ->join('standard', function($join) use($marking_period_id){
                $join->on('standard.id', '=', 'tblstudent_enrollment.standard_id');
                // ->when($marking_period_id,function($query) use($marking_period_id){
                //     $query->where('standard.marking_period_id',$marking_period_id);
                // });
            })
            ->join('division', 'division.id', '=', 'tblstudent_enrollment.section_id')
            ->join('tbluserprofilemaster', 'tbluserprofilemaster.Id', '=', DB::raw('8'))
            ->join('hostel_room_allocation', function ($join) use ($syear) {
                $join->on('tblstudent.id', '=', 'hostel_room_allocation.user_id');
                $join->on('hostel_room_allocation.syear', '=', DB::raw($syear));
                $join->on('tbluserprofilemaster.id', '=', 'hostel_room_allocation.user_group_id');
                $join->on('tblstudent.sub_institute_id', '=', 'hostel_room_allocation.sub_institute_id');
            })
            ->where($extraSearchArray)
            ->whereRaw('tblstudent_enrollment.end_date is NULL')
            ->get()
            ->toArray();
    }

    public function staffForAllocation(Request $request)
    {

        $sub_institute_id = $request->session()->get('sub_institute_id');
        $syear = $request->session()->get('syear');
        $user = $request->input('user');
        $gender = $request->input("gender");
        $admissionCategory = $request->input("admissionCategory");
        $hostel = $request->input("hostel");
        $room = $request->input("room");

        $extraSearchArray['tbluser.sub_institute_id'] = $sub_institute_id;
        $extraSearchArray['tbluser.status'] = "1";

        if ($gender != '') {
            $extraSearchArray['tbluser.gender'] = $gender;
        }

        if ($user != '') {
            $extraSearchArray['tbluser.user_profile_id'] = $user;
        }

        if ($admissionCategory != '') {
            $extraSearchArray['hostel_room_allocation.admission_category_id'] = $admissionCategory;
        }
        if ($hostel != '') {
            $extraSearchArray['hostel_room_allocation.hostel_id'] = $hostel;
        }
        if ($room != '') {
            $extraSearchArray['hostel_room_allocation.room_id'] = $room;
        }

        return tbluserModel::select('tbluser.id', 'tbluser.mobile', 'tbluser.gender',
            'tbluserprofilemaster.name as profile_name')
            ->selectRaw('CONCAT_WS(" ",tbluser.first_name,tbluser.middle_name,tbluser.last_name) as name')
            ->selectRaw(" CASE WHEN tbluser.gender = 'F' THEN 'Female' ELSE 'Male' END as gender")
            ->selectRaw("hostel_room_allocation.admission_category_id, hostel_room_allocation.hostel_id, hostel_room_allocation.room_id, hostel_room_allocation.bed_no,hostel_room_allocation.locker_no,hostel_room_allocation.table_no,hostel_room_allocation.bedsheet_no")
            ->join('tbluserprofilemaster', 'tbluser.user_profile_id', '=', 'tbluserprofilemaster.id')
            ->join('hostel_room_allocation', function ($join) use ($syear) {
                $join->on('tbluser.id', '=', 'hostel_room_allocation.user_id');
                $join->on('hostel_room_allocation.syear', '=', DB::raw($syear));
                $join->on('tbluserprofilemaster.id', '=', 'hostel_room_allocation.user_group_id');
                $join->on('tbluser.sub_institute_id', '=', 'hostel_room_allocation.sub_institute_id');
            })
            ->where($extraSearchArray)
            ->get()->toArray();
    }
}
