<?php

namespace App\Http\Controllers\student;

use App\Http\Controllers\Controller;
use App\Models\student\tblstudentTcModel;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use function App\Helpers\is_mobile;

class tblstudentTcController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return void
     */
    public function index()
    {
        //
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
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $user_id = $request->session()->get('user_id');
        $syear = $request->session()->get('syear');
        $type = $request->input('type');
        $created_ip = $_SERVER['REMOTE_ADDR'];

        $data = [
            'student_id'                          => $request->get('student_id'),
            'sub_institute_id'                    => $sub_institute_id,
            'syear'                               => $syear,
            'affiliation_no'                      => $request->get('affiliation_no'),
            'school_code'                         => $request->get('school_code'),
            'candidate_belongs_to'                => $request->get('candidate_belongs_to'),
            'date_of_first_admission'             => $request->get('date_of_first_admission'),
            'class_in_which_pupil_last_studied'   => $request->get('class_in_which_pupil_last_studied'),
            'last_school_board'                   => $request->get('last_school_board'),
            'whether_failed'                      => $request->get('whether_failed'),
            'subjects_studied'                    => $request->get('subjects_studied'),
            'whether_qualified'                   => $request->get('whether_qualified'),
            'if_to_which_class'                   => $request->get('if_to_which_class'),
            'month_up_paid_school_dues'           => $request->get('month_up_paid_school_dues'),
            // 'admission_under' => $request->get('admission_under'),                                                         
            'total_working_days'                  => $request->get('total_working_days'),
            'total_working_days_present'          => $request->get('total_working_days_present'),
            'games_played'                        => $request->get('games_played'),
            'general_conduct'                     => $request->get('general_conduct'),
            'date_of_application_for_certificate' => $request->get('date_of_application_for_certificate'),
            'date_of_issue_of_certificate'        => $request->get('date_of_issue_of_certificate'),
            'reason_leaving_school'               => $request->get('reason_leaving_school'),
            'proof_for_dob'                       => $request->get('proof_for_dob'),
            'whether_school_is_under_goverment'   => $request->get('whether_school_is_under_goverment'),
            'date_on_which_pupil_name_was_struck' => $request->get('date_on_which_pupil_name_was_struck'),
            'any_fees_concession'                 => $request->get('any_fees_concession'),
            'whether_ncc_cadet'                   => $request->get('whether_ncc_cadet'),
            'any_other_remarks'                   => $request->get('any_other_remarks'),
            'created_by'                          => $user_id,
            'created_on'                          => now(),
            'created_ip'                          => $created_ip,
        ];

        //CHECK for existing record
        $student_tc_details = tblstudentTcModel::where([
            'sub_institute_id' => $sub_institute_id,
            'student_id'       => $request->get('student_id'),
        ])->get()->toArray();

        if (count($student_tc_details) > 0) // Update
        {
            tblstudentTcModel::where('student_id', $request->get('student_id'))->update($data);
        } else // Insert
        {
            tblstudentTcModel::insert($data);
        }

        $res['status_code'] = 1;
        $res['message'] = "Student TC Details Successfully Updated.";
        $res['data'] = $data;

        return is_mobile($type, "search_student.index", $res);
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
}
