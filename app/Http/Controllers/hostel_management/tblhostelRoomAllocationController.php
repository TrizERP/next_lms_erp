<?php

namespace App\Http\Controllers\hostel_management;

use App\Http\Controllers\Controller;
use App\Models\hostel_management\admission_category_masterModel;
use App\Models\hostel_management\hostel_masterModel;
use App\Models\hostel_management\tblhostelRoomAllocationModel;
use App\Models\student\tblstudentModel;
use App\Models\user\tbluserModel;
use App\Models\user\tbluserprofilemasterModel;
use GenTux\Jwt\GetsJwtToken;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use function App\Helpers\aut_token;
use function App\Helpers\is_mobile;


class tblhostelRoomAllocationController extends Controller
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

        $profiles = $this->userProfileList($request);

        $res['status_code'] = 1;
        $res['message'] = "Success";
        $res['profiles'] = $profiles;

        return is_mobile($type, "hostel_management/hostel_room_allocation", $res, "view");
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return void
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  Request  $request
     * @return Response
     */
    public function store(Request $request)
    {
        $students = $request->input('students');
        $admission_category = $request->input('admission_category');
        $hostelno = $request->input('hostelno');
        $roomno = $request->input('roomno');
        $bedno = $request->input('bedno');
        $lockerno = $request->input('lockerno');
        $tableno = $request->input('tableno');
        $bedsheetno = $request->input('bedsheetno');
        $user_group_id = $request->input('user_group_id');
        $type = $request->input('type');
        $term_id = $request->session()->get('term_id');
        $syear = $request->session()->get('syear');
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $hostelAllocation = [];

        if ($students == '') {
            $res['status_code'] = 0;
            $res['message'] = "Please select at least one user to allocate rooms.";

            return is_mobile($type, "hostel_room_allocation.index", $res);
        }

        foreach ($students as $key => $user_id) {
            $checkAllocation = tblhostelRoomAllocationModel::where([
                'user_id'          => $user_id, 'user_group_id' => $user_group_id, 'syear' => $syear,
                'sub_institute_id' => $sub_institute_id,
            ])->get()->toArray();

            if (count($checkAllocation) == 0) {
                $hostelAllocation['user_id'] = $user_id;
                $hostelAllocation['user_group_id'] = $user_group_id;
                $hostelAllocation['admission_category_id'] = $admission_category[$user_id];
                $hostelAllocation['hostel_id'] = $hostelno[$user_id];
                $hostelAllocation['room_id'] = $roomno[$user_id];
                $hostelAllocation['bed_no'] = $bedno[$user_id];
                $hostelAllocation['locker_no'] = $lockerno[$user_id];
                $hostelAllocation['table_no'] = $tableno[$user_id];
                $hostelAllocation['bedsheet_no'] = $bedsheetno[$user_id];
                $hostelAllocation['term_id'] = $term_id;
                $hostelAllocation['syear'] = $syear;
                $hostelAllocation['sub_institute_id'] = $sub_institute_id;

                tblhostelRoomAllocationModel::insert($hostelAllocation);

            } else {

                $hostelAllocation['admission_category_id'] = $admission_category[$user_id];
                $hostelAllocation['hostel_id'] = $hostelno[$user_id];
                $hostelAllocation['room_id'] = $roomno[$user_id];
                $hostelAllocation['bed_no'] = $bedno[$user_id];
                $hostelAllocation['locker_no'] = $lockerno[$user_id];
                $hostelAllocation['table_no'] = $tableno[$user_id];
                $hostelAllocation['bedsheet_no'] = $bedsheetno[$user_id];
                $hostelAllocation['term_id'] = $term_id;

                tblhostelRoomAllocationModel::where([
                    "user_id"          => $user_id, 'user_group_id' => $user_group_id, 'syear' => $syear,
                    'sub_institute_id' => $sub_institute_id,
                ])->update($hostelAllocation);
            }

        }

        $res['status_code'] = 1;
        $res['message'] = "Room Allocation Successfully";

        return is_mobile($type, "hostel_room_allocation.index", $res);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return void
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return void
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  Request  $request
     * @param  int  $id
     * @return void
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return void
     */
    public function destroy($id)
    {
        //
    }

    public function student_hostel_room_allocation(Request $request)
    {
        $type = $request->input('type');
        $user = $request->input("user");
        $gender = $request->input("gender");
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $syear = $request->session()->get('syear');
        $profiles = $this->userProfileList($request);
        $request['user_profile_id'] = $user;
        $userProfile = $this->userProfileList($request);
        $marking_period_id = session()->get('term_id');

        if ($userProfile[0]['name'] == 'Student' || $userProfile[0]['name'] == 'student' || $userProfile[0]['name'] == 'STUDENT') {
            $data = $this->studentsForAllocation($request);
        } else {
            $data = $this->staffForAllocation($request);
        }

        $admissionCategory = admission_category_masterModel::select('id', 'title')
            ->where(['sub_institute_id' => $sub_institute_id])->get()->toArray();

        $hostelList = hostel_masterModel::select('id', 'name')->where(['sub_institute_id' => $sub_institute_id])
            ->get()->toArray();

        if (count($data) == 0) {
            $res['status_code'] = 0;
            $res['message'] = "No ".$userProfile[0]['name']." Found";

            return is_mobile($type, "hostel_room_allocation.index", $res);
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

        $res['status_code'] = 1;
        $res['message'] = "Success";
        $res['hostelList'] = $hostelList;
        $res['admissionCategoryList'] = $admissionCategory;
        $res['tableHeads'] = $tableHeads;
        $res['profiles'] = $profiles;
        $res['userProfile'] = $userProfile;
        $res['gender'] = $gender;
        $res['data'] = $data;

        return is_mobile($type, "hostel_management/hostel_room_allocation", $res, "view");
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
            ->selectRaw("hostel_room_allocation.admission_category_id, hostel_room_allocation.hostel_id, hostel_room_allocation.room_id, 
               hostel_room_allocation.bed_no,hostel_room_allocation.locker_no,hostel_room_allocation.table_no,hostel_room_allocation.bedsheet_no")
            ->join('tblstudent_enrollment', 'tblstudent.id', '=', 'tblstudent_enrollment.student_id')
            ->join('academic_section', 'academic_section.id', '=', 'tblstudent_enrollment.grade_id')
            ->join('standard', function($join) use($marking_period_id) {
                $join->on('standard.id', '=', 'tblstudent_enrollment.standard_id');
                // ->when($marking_period_id,function($query)use($marking_period_id){
                //     $query->where('standard.marking_period_id',$marking_period_id);
                // });
            })
            ->join('division', 'division.id', '=', 'tblstudent_enrollment.section_id')
            ->join('tbluserprofilemaster', 'tbluserprofilemaster.Id', '=', DB::raw('8'))
            ->leftjoin('hostel_room_allocation', function ($join) use ($syear) {

                $join->on('tblstudent.id', '=', 'hostel_room_allocation.user_id');
                $join->on('hostel_room_allocation.syear', '=', DB::raw($syear));
                $join->on('tbluserprofilemaster.id', '=', 'hostel_room_allocation.user_group_id');
                $join->on('tblstudent.sub_institute_id', '=', 'hostel_room_allocation.sub_institute_id');

            })
            ->where($extraSearchArray)
            ->whereRaw('tblstudent_enrollment.end_date is NULL')
            ->get()->toArray();
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
            ->selectRaw("hostel_room_allocation.admission_category_id, hostel_room_allocation.hostel_id, hostel_room_allocation.room_id, 
               hostel_room_allocation.bed_no,hostel_room_allocation.locker_no,hostel_room_allocation.table_no,hostel_room_allocation.bedsheet_no")
            ->join('tbluserprofilemaster', 'tbluser.user_profile_id', '=', 'tbluserprofilemaster.id')
            ->leftjoin('hostel_room_allocation', function ($join) use ($syear) {

                $join->on('tbluser.id', '=', 'hostel_room_allocation.user_id');
                $join->on('hostel_room_allocation.syear', '=', DB::raw($syear));
                $join->on('tbluserprofilemaster.id', '=', 'hostel_room_allocation.user_group_id');
                $join->on('tbluser.sub_institute_id', '=', 'hostel_room_allocation.sub_institute_id');

            })
            ->where($extraSearchArray)
            ->get()->toArray();
    }

    public function userProfileList(Request $request)
    {
        $sub_institute_id = $request->session()->get('sub_institute_id');

        $extraSearchArray['sub_institute_id'] = $sub_institute_id;

        if ($request->input('user_profile_id') != '') {
            $extraSearchArray['id'] = $request->input('user_profile_id');
        }

        return tbluserprofilemasterModel::select('id', 'name')->where($extraSearchArray)->get()->toArray();
    }

    public function studentHostelAllocationAPI(Request $request)
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

            $data = DB::table('hostel_room_allocation as h')
                ->join('hostel_master as hm', function ($join) {
                    $join->whereRaw('hm.id = h.hostel_id');
                })->join('hostel_room_master as r', function ($join) {
                    $join->whereRaw('r.id = h.room_id');
                })->selectRaw("h.hostel_id,h.room_id,bed_no,locker_no,table_no,bedsheet_no,hm.name AS hostel_name,
                    hm.description,hm.warden,hm.warden_contact,r.room_name")
                ->where('h.syear', $syear)
                ->where('h.sub_institute_id', $sub_institute_id)
                ->where('h.user_id', $student_id)->get()->toArray();

            $res['status_code'] = 1;
            $res['message'] = "Success";
            $res['data'] = $data;
        } else {
            $res['status_code'] = 0;
            $res['message'] = "Parameter Missing";
        }

        return json_encode($res);
    }
}
