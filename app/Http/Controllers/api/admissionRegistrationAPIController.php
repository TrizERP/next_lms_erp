<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\admission\admissionEnquiryModel;
use App\Models\admission\admissionRegistrationModel;
use App\Models\school_setup\bloodgroupModel;
use App\Models\school_setup\standardModel;
use App\Models\settings\tblcustomfieldsModel;
use App\Models\settings\tblfields_dataModel;
use App\Models\student\studentQuotaModel;
use App\Models\student\tblstudentEnrollmentModel;
use App\Models\student\tblstudentModel;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use function App\Helpers\is_mobile;
use App\Models\school_setup\casteModel;
use App\Models\school_setup\religionModel;

class admissionRegistrationAPIController extends Controller
{
    
    /**
     * Display a listing of the resource.calendar
     *
     * @return Response
     */
    public function index(Request $request)
    {
        $type = $request->input('type', 'API');
        $sub_institute_id = $request->input('sub_institute_id');
        $syear = $request->input('syear');

        $data = DB::table('admission_enquiry as ae')
            ->join('admission_form as af', function ($join) {
                $join->on('ae.id', '=', 'af.enquiry_id');
            })->leftJoin('tblstudent as ts', function ($join) {
                $join->on('ts.admission_id', '=', 'ae.id')->on('ts.admission_year', '=', 'ae.syear')->on('ts.sub_institute_id', '=', 'ae.sub_institute_id');
            })->leftJoin('standard as s', function ($join) {
                $join->on('s.id', '=', 'ae.admission_standard')->on('ts.sub_institute_id', '=', 'ae.sub_institute_id');
            })
            ->leftJoin('admission_registration_v1 as ar', function ($join) use($sub_institute_id) {
                $join->whereRaw('ar.enquiry_id = af.enquiry_id')->where('ar.sub_institute_id', $sub_institute_id);
            })
            ->selectRaw("ae.*,COUNT(ts.id) AS total_student_count,ae.remarks AS enquiry_remark,s.name AS std_name,ar.transport_fees")
            ->where('ae.sub_institute_id', $sub_institute_id)
            ->where('ae.syear', $syear)
            ->where('ae.status','!=', 'cancel')
            ->groupBy(['ae.first_name','ae.middle_name','ae.last_name'])
            ->get()->toArray();

        $data = array_map(function ($value) {
            return (array) $value;
        }, $data);

        $customFields = tblcustomfieldsModel::where(['status' => "1", 'table_name' => "admission_registration"])
        ->whereRaw('(sub_institute_id = '.$sub_institute_id.' OR common_to_all = 1) and user_type="" ')
        ->orderBy('sort_order', 'ASC')
        ->get();

        $res['custom_fields']=$customFields;

        $res['status_code'] = 1;
        $res['message'] = "Success";
        $res['data'] = $data;

        return is_mobile($type, 'admission/registration/show_admission_registration', $res, 'view');
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return Response
     */
    public function edit(Request $request, $id)
    {
        $type = $request->input('type', 'API');
        $sub_institute_id = $request->input('sub_institute_id');
        $marking_period_id = $request->input('term_id');
        $syear = $request->input('syear');

        if ($sub_institute_id == 198) // For Mahaeshvari school
        {
            $data = DB::table('admission_enquiry as ae')
                ->leftJoin('admission_form as af', function ($join) {
                    $join->on('ae.id', '=', 'af.enquiry_id');
                })
                ->leftJoin('admission_registration as ar', function ($join) {
                    $join->on('ae.id', '=', 'ar.enquiry_id');
                })
                ->selectRaw("ae.*,af.*,ar.*,ae.id as id,ae.enquiry_no as enquiry_no,ae.admission_standard as admission_standard,CONCAT_WS(',',ae.house_no,
                    ae.`building_name_appratment_name_society_name`,ae.district_name,ae.pin_code,ae.state) AS address,
                    ae.previous_standard,ae.mother_name,ae.mobile_number_mother ,ae.place_of_birth,ar.enquiry_id as registration_enquiry_id, ae.remarks AS enquiry_remark, ae.fees_remark AS enquiry_remark2")
                ->where('ae.id', $id)
                ->where('ae.sub_institute_id', $sub_institute_id)
                ->get()->toArray();
        } else {
            $data = DB::table('admission_enquiry as ae')
                ->leftJoin('admission_form as af', function ($join) use ($sub_institute_id) {
                    $join->on('ae.id', '=', 'af.enquiry_id')->where('af.sub_institute_id', $sub_institute_id);
                })->leftJoin('admission_registration as ar', function ($join) use ($sub_institute_id) {
                    $join->on('ae.id', '=', 'ar.enquiry_id')->where('ar.sub_institute_id', $sub_institute_id);
                })
                ->selectRaw("ae.*,af.*,ar.*,ae.id as id,ae.enquiry_no as enquiry_no,
                    ae.admission_standard as admission_standard,
                    COALESCE(ar.mother_name, ae.mother_name) as mother_name,
                    COALESCE(ar.mother_mobile_number, ae.mobile_number_mother) as mother_mobile_number,
                    ar.enquiry_id as registration_enquiry_id, ae.remarks AS enquiry_remark, ae.fees_remark AS enquiry_remark2")
                ->where('ae.id', $id)
                ->where('ae.sub_institute_id', $sub_institute_id)
                ->get()->toArray();
        }

        $data = array_map(function ($value) {
            return (array) $value;
        }, $data);
        $editData = $data;

        if (empty($editData)) {
            return response()->json([
                'status_code' => '0',
                'message' => 'Admission registration record not found.',
                'editData' => null,
                'data' => null,
            ], 404);
        }

        $editRecord = $editData[0];
        $checkStudent = tblstudentModel::where(['admission_id' => $id])->where('sub_institute_id', $sub_institute_id)->get()->toArray();

        $dataCustomFields = tblcustomfieldsModel::where(['status' => '1', 'table_name' => 'admission_registration'])
            ->whereRaw('(sub_institute_id = '.$sub_institute_id.' OR common_to_all = 1)  and user_type="" ')
            ->orderBy('sort_order', 'ASC')
            ->get();

        $fieldsData = tblfields_dataModel::get()->toArray();
        $i = 0;
        $finalfieldsData = array();
        foreach ($fieldsData as $key => $value) {
            $finalfieldsData[$value['field_id']][$i]['display_text'] = $value['display_text'];
            $finalfieldsData[$value['field_id']][$i]['display_value'] = $value['display_value'];
            $i++;
        }

        if (count($checkStudent) > 0) {
            $res['display_save_student'] = '0';
        } else {
            $res['display_save_student'] = '1';
        }

        $category = studentQuotaModel::where(['sub_institute_id' => $sub_institute_id])->get()->toArray();

        if (empty($editRecord['register_number'])) {
            $res['next_register_number'] = (int) admissionRegistrationModel::where('sub_institute_id', $sub_institute_id)->count() + 1;
        } else {
            $res['next_register_number'] = $editRecord['register_number'];
        }

        if (isset($editRecord['enrollment_no']) && $editRecord['enrollment_no'] != '') {
            $res['new_enrollment_no'] = $editRecord['enrollment_no'];
        } else {
            $enroll = $this->max_enrollment_no($sub_institute_id, $editRecord['admission_standard'], $syear);
            $res['new_enrollment_no'] = isset($enroll) ? $enroll : 0;
        }

        $array = [201, 202, 203, 204];

        $checkGrnoExist = tblstudentModel::where('enrollment_no', $res['new_enrollment_no'])
            ->where('sub_institute_id', $sub_institute_id)
            ->when(in_array($sub_institute_id, $array), function ($query) use ($syear) {
                $query->where('admission_year', $syear);
            })
            ->get()
            ->toArray();

        if (!empty($checkGrnoExist) && $res['display_save_student'] == 1) {
            $res['new_enrollment_no'] = $this->max_enrollment_no($sub_institute_id, $editRecord['admission_standard'], $syear);
            $res['display_save_student'] = 0;
        }

        $standard = standardModel::where(['sub_institute_id' => $sub_institute_id])->get()->toArray();
        $bloodgroupData = bloodgroupModel::select()->get();

        $getDiv = DB::table('std_div_map as sdm')
            ->join('standard as s', function ($join) use ($marking_period_id) {
                $join->whereRaw('s.id =sdm.standard_id AND s.sub_institute_id = sdm.sub_institute_id');
            })->join('division as d', function ($join) {
                $join->whereRaw('d.id = sdm.division_id AND d.sub_institute_id = sdm.sub_institute_id');
            })->selectRaw('d.id,d.name,sdm.standard_id')
            ->where('sdm.sub_institute_id', $sub_institute_id)
            ->where('sdm.standard_id', $editRecord['admission_standard'])
            ->get()->toArray();

        $getDiv = array_map(function ($value) {
            return (array) $value;
        }, $getDiv);

        $res['status_code'] = '1';
        $res['message'] = 'Successfully';
        $res['editData'] = $editRecord;
        $res['standard'] = $standard;
        $res['bloodgroup_data'] = $bloodgroupData;
        $res['custom_fields'] = $dataCustomFields;
        $res['religion_data'] = religionModel::select()->get();
        $res['caste_data'] = casteModel::select()->get();

        if (count($getDiv) > 0) {
            $res['division'] = $getDiv;
        }
        if (count($finalfieldsData) > 0) {
            $res['data_fields'] = $finalfieldsData;
        }
        if (count($category) > 0) {
            $res['category'] = $category;
        }

        return is_mobile($type, 'admission/registration/edit_admission_registration', $res, 'view');
    }

    /**
     * Corrected variant of index() for the Admission Without Confirmation Report.
     *
     * Added as a new method (rather than editing index()) per project rule: existing
     * controller functions must not be modified. Fixes vs index():
     *  - `join('admission_form as af', ...)` was a mandatory INNER JOIN; admission_form
     *    is never populated by the current admission_enquiry -> admission_registration
     *    flow, so it silently excluded every row. Changed to a LEFT JOIN.
     *  - `where('ae.status','!=','cancel')` silently drops rows where status is NULL
     *    (SQL: `NULL != 'cancel'` is NULL, not true), which is nearly every enquiry
     *    until it's explicitly cancelled. Now NULL-safe.
     *  - `groupBy(['ae.first_name','ae.middle_name','ae.last_name'])` collapsed
     *    distinct enquiries that happen to share a name into a single row. Now
     *    grouped by `ae.id` instead.
     *
     * @return Response
     */
    public function indexWithoutConfirmationReport(Request $request)
    {
        $type = $request->input('type', 'API');
        $sub_institute_id = $request->input('sub_institute_id');
        $syear = $request->input('syear');

        $data = DB::table('admission_enquiry as ae')
            ->leftJoin('admission_form as af', function ($join) {
                $join->on('ae.id', '=', 'af.enquiry_id');
            })->leftJoin('tblstudent as ts', function ($join) {
                $join->on('ts.admission_id', '=', 'ae.id')->on('ts.admission_year', '=', 'ae.syear')->on('ts.sub_institute_id', '=', 'ae.sub_institute_id');
            })->leftJoin('standard as s', function ($join) {
                $join->on('s.id', '=', 'ae.admission_standard')->on('ts.sub_institute_id', '=', 'ae.sub_institute_id');
            })
            ->leftJoin('admission_registration_v1 as ar', function ($join) use($sub_institute_id) {
                $join->whereRaw('ar.enquiry_id = af.enquiry_id')->where('ar.sub_institute_id', $sub_institute_id);
            })
            ->selectRaw("ae.*,COUNT(ts.id) AS total_student_count,ae.remarks AS enquiry_remark,s.name AS std_name,ar.transport_fees")
            ->where('ae.sub_institute_id', $sub_institute_id)
            ->where('ae.syear', $syear)
            ->where(function ($q) {
                $q->whereNull('ae.status')->orWhere('ae.status', '!=', 'cancel');
            })
            ->groupBy('ae.id')
            ->get()->toArray();

        $data = array_map(function ($value) {
            return (array) $value;
        }, $data);

        $customFields = tblcustomfieldsModel::where(['status' => "1", 'table_name' => "admission_registration"])
        ->whereRaw('(sub_institute_id = '.$sub_institute_id.' OR common_to_all = 1) and user_type="" ')
        ->orderBy('sort_order', 'ASC')
        ->get();

        $res['custom_fields']=$customFields;

        $res['status_code'] = 1;
        $res['message'] = "Success";
        $res['data'] = $data;

        return is_mobile($type, 'admission/registration/show_admission_registration', $res, 'view');
    }

    /**
     * Corrected variant of edit() for the Admission Without Confirmation Report.
     *
     * Added as a new method (rather than editing edit()) per project rule: existing
     * controller functions must not be modified. edit() selects bare `ae.*,af.*,ar.*`;
     * since admission_enquiry/admission_form/admission_registration all have a
     * `created_on` column, and admission_form/admission_registration are NULL for an
     * enquiry that isn't registered yet, `created_on` in the result silently became
     * NULL for exactly the unregistered enquiries this report needs to list -
     * breaking its date-range filter for every genuine candidate. This adds an
     * unambiguous `enquiry_created_on` alias (`ae.created_on`) alongside the
     * existing fields so it can no longer be clobbered. Kept lean (no enrollment
     * number/division/bloodgroup lookups from edit()) since this method only feeds
     * the report's list+detail read path, not the registration edit form.
     *
     * @param  int  $id
     * @return Response
     */
    public function editWithoutConfirmationReport(Request $request, $id)
    {
        $type = $request->input('type', 'API');
        $sub_institute_id = $request->input('sub_institute_id');
        $syear = $request->input('syear');

        if ($sub_institute_id == 198) // For Mahaeshvari school
        {
            $data = DB::table('admission_enquiry as ae')
                ->leftJoin('admission_form as af', function ($join) {
                    $join->on('ae.id', '=', 'af.enquiry_id');
                })
                ->leftJoin('admission_registration as ar', function ($join) {
                    $join->on('ae.id', '=', 'ar.enquiry_id');
                })
                ->selectRaw("ae.*,af.*,ar.*,ae.id as id,ae.enquiry_no as enquiry_no,ae.admission_standard as admission_standard,CONCAT_WS(',',ae.house_no,
                    ae.`building_name_appratment_name_society_name`,ae.district_name,ae.pin_code,ae.state) AS address,
                    ae.previous_standard,ae.mother_name,ae.mobile_number_mother ,ae.place_of_birth,ar.enquiry_id as registration_enquiry_id, ae.remarks AS enquiry_remark, ae.fees_remark AS enquiry_remark2, ae.created_on as enquiry_created_on, ar.status as registration_status")
                ->where('ae.id', $id)
                ->where('ae.sub_institute_id', $sub_institute_id)
                ->get()->toArray();
        } else {
            $data = DB::table('admission_enquiry as ae')
                ->leftJoin('admission_form as af', function ($join) use ($sub_institute_id) {
                    $join->on('ae.id', '=', 'af.enquiry_id')->where('af.sub_institute_id', $sub_institute_id);
                })->leftJoin('admission_registration as ar', function ($join) use ($sub_institute_id) {
                    $join->on('ae.id', '=', 'ar.enquiry_id')->where('ar.sub_institute_id', $sub_institute_id);
                })
                ->selectRaw("ae.*,af.*,ar.*,ae.id as id,ae.enquiry_no as enquiry_no,
                    ae.admission_standard as admission_standard,
                    COALESCE(ar.mother_name, ae.mother_name) as mother_name,
                    COALESCE(ar.mother_mobile_number, ae.mobile_number_mother) as mother_mobile_number,
                    ar.enquiry_id as registration_enquiry_id, ae.remarks AS enquiry_remark, ae.fees_remark AS enquiry_remark2, ae.created_on as enquiry_created_on, ar.status as registration_status")
                ->where('ae.id', $id)
                ->where('ae.sub_institute_id', $sub_institute_id)
                ->get()->toArray();
        }

        $data = array_map(function ($value) {
            return (array) $value;
        }, $data);
        $editData = $data;

        if (empty($editData)) {
            $res = [
                'status_code' => '0',
                'message' => 'Admission registration record not found.',
                'editData' => null,
                'data' => null,
            ];

            return is_mobile($type, 'admission/registration/edit_admission_registration', $res, 'view');
        }

        $res = [
            'status_code' => '1',
            'message' => 'Successfully',
            'editData' => $editData[0],
        ];

        return is_mobile($type, 'admission/registration/edit_admission_registration', $res, 'view');
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
        $type = $request->input('type', 'API');
        $sub_institute_id = $request->input('sub_institute_id');
        $syear = $request->input('syear');   
        $user_id = $request->input('user_id');
        $editdata['first_name'] = $request->input("first_name");
        $editdata['middle_name'] = $request->input("middle_name");
        $editdata['last_name'] = $request->input("last_name");
        $editdata['mobile'] = $request->input("mobile");
        $editdata['email'] = $request->input("email");
        $editdata['date_of_birth'] = $request->input("date_of_birth");
        $editdata['age'] = $request->input("age");
        $editdata['address'] = $request->input("address");
        $editdata['previous_school_name'] = $request->input("previous_school_name");
        $editdata['source_of_enquiry'] = $request->input("source_of_enquiry");
        $editdata['remarks'] = $request->input("remarks");
        $editdata['fees_remark'] = $request->input("fees_remark");
        
        admissionEnquiryModel::where(['id' => $id, 'sub_institute_id' => $sub_institute_id])->update($editdata);

        $data = $request->except([
            '_method', '_token','token','syear','sub_institute_id','user_id', 'submit', 'type', 'first_name', 'middle_name', 'last_name', 'mobile', 'email',
            'date_of_birth', 'age', 'address', 'previous_school_name', 'previous_standard', 'source_of_enquiry','gender',
            'admission_standard', 'remarks', 'fees_remark'
        ]); //,'followup_date'

        $checkForm = admissionRegistrationModel::where(['enquiry_id' => $id])->where('sub_institute_id',$sub_institute_id)->get()->toArray();
        if (count($checkForm) > 0) {
            $data['enquiry_id'] = $id;
            $data['created_by'] = $user_id;
            $data['created_on'] = date('Y-m-d H:i:s');
            $data['sub_institute_id'] = $sub_institute_id;

            admissionRegistrationModel::where(['enquiry_id' => $id])->where('sub_institute_id',$sub_institute_id)->update($data);
        } else {
            $data['enquiry_id'] = $id;
            $data['created_by'] = $user_id;
            $data['created_on'] = date('Y-m-d H:i:s');
            $data['sub_institute_id'] = $sub_institute_id;

            admissionRegistrationModel::insert($data);
        }

        $res['status_code'] = "1";
        $res['message'] = "Added successfully";

        return is_mobile($type, "admission_confirmation.index", $res);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return Response
     */
    public function destroy($id)
    {
        //
    }

    public function saveStudent(Request $request)
    {
        $type = $request->input('type', 'API');
        $sub_institute_id = $request->input('sub_institute_id');
        $term_id = $request->input('term_id');
        $syear = $request->input('syear');
        $id = $request->input("id");

        $user_profile_result = DB::table('tbluserprofilemaster')->select('id')
            ->where('name', 'Student')
            ->where('sub_institute_id', $sub_institute_id)->get()->toArray();
        $user_profile_id = $user_profile_result[0]->id;

        $data = DB::table('admission_enquiry as ae')
            // Left join: admission_form is a legacy step that the API-only admission flow
            // (admission_enquiry -> admission_registration) never populates, so requiring it
            // here blocked every API-created admission from ever reaching saveStudent().
            ->leftJoin('admission_form as af', function ($join) use ($sub_institute_id) {
                $join->whereRaw('ae.id = af.enquiry_id')->where('af.sub_institute_id', $sub_institute_id);
            })->join('admission_registration as ar', function ($join) use($sub_institute_id){
                $join->whereRaw('ae.id = ar.enquiry_id')->where('ar.sub_institute_id',$sub_institute_id); // 2024-08-27 add sub_institute_id
            })            ->selectRaw("ae.*,af.*,ae.id as id,ar.*,ar.religion as con_religion,ar.cast as con_cast, ae.remarks AS enquiry_remark, ae.fees_remark AS enquiry_remark2,
                    COALESCE(af.admission_standard, ae.admission_standard) as admission_standard,
                    COALESCE(af.annual_income, ae.annual_income) as annual_income")
            ->where('ae.id', $id)->get()->toArray();

        $data = array_map(function ($value) {
            return (array) $value;
        }, $data);
        // echo "<pre>";print_r($data);exit;
        if (count($data) == 0) {
            $res['status_code'] = 0;
            $res['message'] = "Please complete admission enquiry process";

            return is_mobile($type, "admission_registration.index", $res);
        }

        $data = $data['0'];
        $standardDetails = standardModel::where(['id' => $data['admission_standard']])->get()->toArray();

        if (count($standardDetails) == 0) {
            $res['status_code'] = 0;
            $res['message'] = "Please select an admission standard/grade in the registration details before adding the student.";

            return is_mobile($type, "admission_registration.index", $res);
        }

        if (empty($data['student_quota'])) {
            $res['status_code'] = 0;
            $res['message'] = "Please select a student quota in the registration details before adding the student.";

            return is_mobile($type, "admission_registration.index", $res);
        }

        $grade_id = $standardDetails['0']['grade_id'];

        $studentArray = array();
        $studentEnrollmentArray = array();

        $studentArray['admission_id'] = $id;
        $studentArray['first_name'] = $data['first_name'];
        $studentArray['middle_name'] = $data['middle_name'];
        $studentArray['last_name'] = $data['last_name'];
        $studentArray['gender'] = $data['gender'];
        $studentArray['mobile'] = $data['mobile'];
        $studentArray['email'] = $data['email'];
        $studentArray['address'] = $data['address'];
        $studentArray['username'] = $data['enrollment_no'];
        $studentArray['user_profile_id'] = $user_profile_id;
        $studentArray['admission_year'] = $syear;//date('Y');
        $studentArray['since_when'] = $syear;//date('Y');
        $studentArray['admission_date'] = $data['admission_date'];//date('Y-m-d');
        $studentArray['sub_institute_id'] = $sub_institute_id;
        $studentArray['status'] = "1";
        $studentArray['place_of_birth'] = $data['place_of_birth'];
        $studentArray['adharnumber'] = $data['aadhar_number'];
        $studentArray['mother_name'] = $data['mother_name'];
        $studentArray['mother_mobile'] = $data['mother_mobile_number'];
        $studentArray['father_name'] = $data['father_name'];
        $studentArray['dob'] = $data['date_of_birth'];
        $studentArray['anuualincome'] = $data['annual_income'];
        $studentArray['bloodgroup'] = $data['blood_group'];
        $studentArray['admission_docket_no'] = $data['admission_docket_no'];
        $studentArray['registration_no'] = $data['registration_no'];
        // 2024-08-27 add
        $studentArray['religion'] = $data['con_religion'];
        $studentArray['cast'] = $data['con_cast'];
        // end 2024-08-27
         // 2025-02-18 added fathre mobile numer in student mobile
         $studentArray['student_mobile'] = isset($data['mobile_number_father']) ? $data['mobile_number_father'] : null;
         // end 2025-02-18
         // Add enquiry remarks
         $studentArray['remark1'] = isset($data['enquiry_remark']) ? $data['enquiry_remark'] : (isset($data['remarks']) ? $data['remarks'] : '');
         $studentArray['remark2'] = isset($data['enquiry_remark2']) ? $data['enquiry_remark2'] : ''; 
        DB::transaction(function () use ($data, $studentArray, $syear, $grade_id, $term_id, $sub_institute_id) {
        $i=0;

        // Duplicate prevention: if this admission already has a student record (from an
        // earlier Add Student click), do not create another one.
        $checkStudent = DB::table('tblstudent')
            ->where('sub_institute_id', $studentArray['sub_institute_id'])
            ->where('admission_id', $studentArray['admission_id'])
            ->first();

        if (empty($checkStudent)) {
            // Enrollment Number is generated here, at Add Student time, as
            // last enrollment number + 1 - never reused from a value entered earlier
            // during Registration/Confirm (admission_registration.enrollment_no is ignored).
            $enrollment_no_sql_new = $this->max_enrollment_no_new($sub_institute_id, $data['admission_standard']);

            // Guard against a duplicate enrollment number (e.g. a stale MAX() read under
            // concurrent Add Student clicks) - ensure it isn't already assigned to another student.
            $attempts = 0;
            while (
                $attempts < 20 &&
                DB::table('tblstudent')
                    ->where('sub_institute_id', $sub_institute_id)
                    ->where('enrollment_no', $enrollment_no_sql_new)
                    ->exists()
            ) {
                $enrollment_no_sql_new = is_numeric($enrollment_no_sql_new)
                    ? $enrollment_no_sql_new + 1
                    : $this->max_enrollment_no_new($sub_institute_id, $data['admission_standard']);
                $attempts++;
            }

            DB::table('tblstudent')
                ->insert([
                    'admission_id'        => $studentArray['admission_id'],
                    'first_name'          => $studentArray['first_name'],
                    'middle_name'         => $studentArray['middle_name'],
                    'last_name'           => $studentArray['last_name'],
                    'gender'              => $studentArray['gender'],
                    'mobile'              => $studentArray['mobile'],
                    'email'               => $studentArray['email'],
                    'address'             => $studentArray['address'],
                    'username'            => $studentArray['username'],
                    'user_profile_id'     => $studentArray['user_profile_id'],
                    'admission_year'      => $studentArray['admission_year'],
                    'since_when'          => $studentArray['since_when'],
                    'admission_date'      => $studentArray['admission_date'],
                    'sub_institute_id'    => $studentArray['sub_institute_id'],
                    'status'              => $studentArray['status'],
                    'place_of_birth'      => $studentArray['place_of_birth'],
                    'adharnumber'         => $studentArray['adharnumber'],
                    'mother_name'         => $studentArray['mother_name'],
                    'mother_mobile'       => $studentArray['mother_mobile'],
                    'father_name'         => $studentArray['father_name'],
                    'dob'                 => $studentArray['dob'],
                    'anuualincome'        => $studentArray['anuualincome'],
                    'bloodgroup'          => $studentArray['bloodgroup'],
                    'admission_docket_no' => $studentArray['admission_docket_no'],
                    'registration_no'     => $studentArray['registration_no'],
                    'enrollment_no'       => $enrollment_no_sql_new,
                    // 2024-08-27 add
                    'religion'            => $studentArray['religion'],
                    'cast'                => $studentArray['cast'],
                    // end 2024-08-27
                    // 2025-02-18 added fathre mobile numer in student mobile
                    'student_mobile'                => $studentArray['student_mobile'],
                    // end 2025-02-18
                    'remark1'                        => $studentArray['remark1'],
                    'remark2'                       => $studentArray['remark2'],
                ]);

            $student_id = DB::getPdo()->lastInsertId();
            $i=1;
        }
        
        if($i==1){
            $studentEnrollmentArray['syear'] = $syear;
            $studentEnrollmentArray['student_id'] = $student_id;
            $studentEnrollmentArray['grade_id'] = $grade_id;
            $studentEnrollmentArray['standard_id'] = $data['admission_standard'];
            $studentEnrollmentArray['section_id'] = $data['admission_division'];
            $studentEnrollmentArray['student_quota'] = $data['student_quota'];
            $studentEnrollmentArray['start_date'] = date('Y-m-d');
            $studentEnrollmentArray['enrollment_code'] = "1";
            $studentEnrollmentArray['term_id'] = $term_id;
            $studentEnrollmentArray['admission_fees'] = $data['amount'];
            $studentEnrollmentArray['sub_institute_id'] = $sub_institute_id;
    
            tblstudentEnrollmentModel::insert($studentEnrollmentArray);
        }
        });

        $res['status_code'] = 1;
        $res['message'] = "Student added successfully";//with Enrollment Number - ".$studentArray['enrollment_no'];

        return is_mobile($type, "admission_confirmation.index", $res);
    }

    public function max_enrollment_no($sub_institute_id, $admission_standard_id, $syear = null)
    {
        $array = [201,202,203,204];

        if ($sub_institute_id == 47)//Generate Enrollment No for MMISERP
        {
            // $get_prefix = "SELECT * FROM enrollment_prefix_master
			// 		   WHERE sub_institute_id = '" . $sub_institute_id . "'
			// 		   AND FIND_IN_SET ('" . $admission_standard_id . "',standards) ";

            // $get_prefix_result = DB::select($get_prefix);

            $get_prefix_result = DB::table('enrollment_prefix_master')
                ->select('enrollment_prefix_master.*')
                ->whereRaw("sub_institute_id = '" . $sub_institute_id . "' AND FIND_IN_SET ('" . $admission_standard_id . "',standards) ")
                ->get()->toArray();

            $prefix = $get_prefix_result[0]->prefix;

            if ($prefix != '' && $prefix!=null) {
                $enrollment_result = DB::table('tblstudent')
                    ->selectRaw('*,MAX(enrollment_no) as new_enrollment_no')
                    ->where('sub_institute_id', $sub_institute_id)
                    ->whereRaw("enrollment_no LIKE '%" . $prefix . "%'")->get()->toArray();
                $get_enrollment_no = substr($enrollment_result[0]->new_enrollment_no, 2, 6);
                $new_enrollment_number = $get_enrollment_no + 1;
                $new_enrollment_no = $prefix.$new_enrollment_number;
            } else {
                $get_prefix_null_result = DB::table('enrollment_prefix_master')
                    ->selectRaw('GROUP_CONCAT(prefix) as all_prefix')
                    ->where('sub_institute_id', $sub_institute_id)
                    ->get()->toArray();
                $get_prefix_null_result = $get_prefix_null_result[0];
                $prefix_expload = explode(',', $get_prefix_null_result->all_prefix);

                $enrollment_result = DB::table('tblstudent')
                    ->selectRaw('*,MAX(enrollment_no) as new_enrollment_no')
                    ->where('sub_institute_id', $sub_institute_id)
                    ->when(! empty($prefix_expload), function ($q) use ($prefix_expload) {
                        foreach ($prefix_expload as $key => $value) {
                            $q->whereRaw("enrollment_no NOT LIKE '%".$value."%'");
                        }
                    })->get()->toArray();
                $get_enrollment_no = $enrollment_result[0]->new_enrollment_no;
                $new_enrollment_no = $get_enrollment_no + 1;
            }
        } else if (in_array($sub_institute_id, $array))//Generate Enrollment No for hills_rustampura
        {
            $get_prefix_result = DB::table('enrollment_prefix_master')
                ->select('enrollment_prefix_master.*')
                ->whereRaw("sub_institute_id = '" . $sub_institute_id . "' AND FIND_IN_SET ('" . $admission_standard_id . "', standards)")
                ->get()->toArray();

            $prefix = !empty($get_prefix_result) ? $get_prefix_result[0]->prefix : null;
        
            if ($prefix != '' && $prefix != null) {
        
                $syear = $syear ?? date('Y');
                $syearShort = substr($syear, -2);
                $finalPrefix = $prefix . "-" . $syearShort . "-";
        
				$enrollment_result = DB::table('tblstudent as s')
				    ->join('tblstudent_enrollment as se', function ($join) use ($syear) {
				        $join->on('se.student_id', '=', 's.id')
				             ->on('se.sub_institute_id', '=', 's.sub_institute_id')
				             ->whereNull('se.end_date')
				             ->where('se.syear', $syear);
				    })
				    ->selectRaw("MAX(CAST(SUBSTRING_INDEX(s.enrollment_no, '-', -1) AS UNSIGNED)) AS new_enrollment_no")
				    ->where('s.sub_institute_id', $sub_institute_id)
				    ->where('s.enrollment_no', 'LIKE', $finalPrefix . '%')
				    ->first();

                if ($enrollment_result->new_enrollment_no) {
                    $full = $enrollment_result->new_enrollment_no;
                    //$lastPart = substr($full, strrpos($full, '-') + 1);
                    //echo $enrollment_result->new_enrollment_no."=".$lastPart;die();
                    $new_enrollment_number = (int)$full + 1;
                } else {
                    $new_enrollment_number = 1;
                }
        
                $new_enrollment_no = $finalPrefix . $new_enrollment_number;
        
            } else {

                $get_prefix_null_result = DB::table('enrollment_prefix_master')
                    ->selectRaw('GROUP_CONCAT(prefix) as all_prefix')
                    ->where('sub_institute_id', $sub_institute_id)
                    ->first();
        
                $prefix_expload = explode(',', $get_prefix_null_result->all_prefix);
        
				$syear = $syear ?? date('Y');
				$enrollment_result = DB::table('tblstudent as s')
				    ->join('tblstudent_enrollment as se', function ($join) use ($syear) {
				        $join->on('se.student_id', '=', 's.id')
				             ->on('se.sub_institute_id', '=', 's.sub_institute_id')
				             ->whereNull('se.end_date')
				             ->where('se.syear', $syear);
				    })
				    ->selectRaw('MAX(CAST(s.enrollment_no AS UNSIGNED)) AS new_enrollment_no')
				    ->where('s.sub_institute_id', $sub_institute_id)
				    ->when(!empty($prefix_expload), function ($q) use ($prefix_expload) {
				        foreach ($prefix_expload as $value) {
				            $q->where('s.enrollment_no', 'NOT LIKE', "%{$value}%");
				        }
				    })
				    ->first();

                if ($enrollment_result->new_enrollment_no) {
                    $new_enrollment_no = $enrollment_result->new_enrollment_no + 1;
                } else {
                    $new_enrollment_no = 1001;
                }
            }
        } else {
            $maxEnrollment = DB::table('tblstudent')
                ->selectRaw('(MAX(CAST(enrollment_no AS INT)) + 1) AS new_enrollment_no')
                ->where('sub_institute_id', $sub_institute_id)
                ->orderBy('id', "DESC")->limit(1)->get()->toArray();

            $maxEnrollment = array_map(function ($value) {
                return (array) $value;
            }, $maxEnrollment);

            $new_enrollment_no = $maxEnrollment['0']['new_enrollment_no'];

        }

        return $new_enrollment_no;
    }

    // This function return max enrollment query
    public function max_enrollment_no_new($sub_institute_id, $admission_standard_id)
    {

        if ($sub_institute_id == 47)//Generate Enrollment No for MMISERP
        {
            $get_prefix_result = DB::table('enrollment_prefix_master')
                ->select('enrollment_prefix_master.*')
                ->whereRaw("sub_institute_id = '" . $sub_institute_id . "' AND FIND_IN_SET ('" . $admission_standard_id . "',standards) ")
                ->get()->toArray();
            $prefix = $get_prefix_result[0]->prefix;

            if ($prefix != '') {
                $enrollment_no_sql = "SELECT concat_Ws('','" . $prefix . "',substr(MAX(enrollment_no),3) + 1) as new_enrollment_no
				FROM tblstudent as s
				WHERE sub_institute_id = '".$sub_institute_id."' AND enrollment_no LIKE '%".$prefix."%'";
            } else {
                $get_prefix_null_result = DB::table('enrollment_prefix_master')
                    ->selectRaw('GROUP_CONCAT(prefix) as all_prefix')
                    ->where('sub_institute_id', $sub_institute_id)->get()->toArray();
                $get_prefix_null_result = $get_prefix_null_result[0];
                $prefix_expload = explode(',', $get_prefix_null_result->all_prefix);

                $extra_query = "";
                foreach ($prefix_expload as $key => $value) {
                    $extra_query .= " AND enrollment_no NOT LIKE '%".$value."%'";
                }

                $enrollment_no_sql = "SELECT (MAX(enrollment_no) + 1) as new_enrollment_no
				FROM tblstudent as s
				WHERE sub_institute_id = '".$sub_institute_id."' $extra_query ";
            }
        } else {
            $enrollment_no_sql = "SELECT MAX(CAST(enrollment_no as int) + 1) as new_enrollment_no FROM tblstudent as s
                WHERE sub_institute_id = '" . $sub_institute_id . "'";
        }

        $result = DB::select($enrollment_no_sql);
        return $result[0]->new_enrollment_no ?? 1;
    }

    public function ajax_getDivision(Request $request)
    {
        $standard_id = $request->input("standard_id");
        $sub_institute_id = $request->input('sub_institute_id');
        $marking_period_id = $request->input('term_id');
        return DB::table('std_div_map as sdm')
            ->join('standard as s', function ($join) use($marking_period_id) {
                $join->whereRaw('s.id =sdm.standard_id AND s.sub_institute_id = sdm.sub_institute_id');
                // ->when($marking_period_id,function($query) use ($marking_period_id){
                //     $query->where('s.marking_period_id',$marking_period_id);
                // });
            })->join('division as d', function ($join) {
                $join->whereRaw('d.id = sdm.division_id AND d.sub_institute_id = sdm.sub_institute_id');
            })->selectRaw("d.id,d.name,sdm.standard_id")
            ->where('sdm.sub_institute_id', $sub_institute_id)
            ->where('sdm.standard_id', $standard_id)->get()->toArray();
    }
}


