<?php

namespace App\Http\Controllers\student;

use App\Http\Controllers\Controller;
use App\Models\school_setup\subjectModel;
use App\Models\student\studentHomeworkModel;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use function App\Helpers\is_mobile;

class studentHomeworkSubmissionController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index(Request $request)
    {
        $type = $request->input('type');
        $submit = $request->input('submit');
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $res['status_code'] = 1;
        $res['message'] = "Success";

        $subjects = subjectModel::select('id',
            'subject_name')->where(['sub_institute_id' => $sub_institute_id])->get()->toArray();

        $res['subjects'] = $subjects;

        return is_mobile($type, "student/homework/show_student_homework_submission", $res, "view");
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return Response
     */
    public function create(Request $request)
    {
        $grade = $request->input('grade');
        $standard = $request->input('standard');
        $division = $request->input('division');
        $subject = $request->input('subject');
        $submission_date = $request->input('submission_date');
        $type = $request->input('type');
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $syear = $request->session()->get('syear');
        $extra_query = '';
        $marking_period_id = session()->get('term_id');

        $result = DB::table('homework as ah')
            ->join('tblstudent as s', function ($join) {
                $join->whereRaw('s.id = ah.student_id');
            })->join('tblstudent_enrollment as se', function ($join) {
                $join->whereRaw('(s.id = se.student_id AND se.end_date IS NULL)');
            })->join('standard as cs', function ($join) use ($marking_period_id){
                $join->whereRaw('(cs.id = ah.standard_id)');
                // ->when($marking_period_id,function($query) use($marking_period_id) {
                //     $query->where('cs.marking_period_id');
                // });
            })->join('division as ss', function ($join) {
                $join->whereRaw('(ss.id = ah.division_id)');
            })->selectRaw("ah.id AS CHECKBOX,se.roll_no,s.enrollment_no, CONCAT_WS(' ',s.last_name,s.first_name,s.middle_name) AS
                student_name,cs.name AS standard,ss.name as division,s.email,s.mobile,ah.id, ah.title,ah.description,ah.image,
                DATE_FORMAT(ah.submission_date,'%d-%m-%Y') AS SUBMISSION_DATE,DATE_FORMAT(ah.date,'%d-%m-%Y') AS HOMEWORK_DATE,
                '' REMARKS,submission_remarks")
            ->where('se.syear', $syear)
            ->where('ah.completion_status', '=', 'N')
            ->where('s.sub_institute_id', $sub_institute_id);

        if ($grade != '') {
            $result = $result->where('se.grade_id', $grade);
        }

        if ($standard != '') {
            $result = $result->where('ah.standard_id', $standard);
        }

        if ($division != '') {
            $result = $result->where('ah.division_id', $division);
        }

        if ($subject != '') {
            $result = $result->where('ah.subject_id', $subject);
        }

        if ($submission_date != '') {
            $result = $result->whereRaw("DATE_FORMAT(ah.submission_date,'%Y-%m-%d') = '".$submission_date."'");
        }

        $result = $result->get()->toArray();

        $subjects = subjectModel::select('id',
            'subject_name')->where(['sub_institute_id' => $sub_institute_id])->get()->toArray();


        $res['status_code'] = 1;
        $res['message'] = "Success";
        $res['student_data'] = $result;
        $res['subjects'] = $subjects;
        $res['grade_id'] = $grade;
        $res['standard_id'] = $standard;
        $res['division_id'] = $division;
        $res['submission_date'] = $submission_date;

        $res['subject'] = $subject;

        return is_mobile($type, "student/homework/show_student_homework_submission", $res, "view");
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  Request  $request
     * @return Response
     */
    public function store(Request $request)
    {
        // dd($request);
        $students = $request->get('students');
        $type = $request->get('type');
        $title = $request->get('title');
        $description = $request->get('description');
        $submission_date = $request->get('submission_date');
        $division_id = $request->get('division_id');
        $standard_id = $request->get('standard_id');
        $subject_id = $request->get('subject_id');
        $submission_remarks = $request->input('submission_remarks');

        $sub_institute_id = $request->session()->get('sub_institute_id');
        $syear = $request->session()->get('syear');

        // $file_name = "";


        foreach ($students as $key => $student_id) {
            $file_name = $file_size = $ext = "";
            if ($request->hasFile('image')) {
                $file = $request->file('image')[$student_id];
                $originalname = $file->getClientOriginalName();
                $file_size = $file->getSize();
                $name = "homework-submission-".$request->get('user_name').date('YmdHis').'-'.$student_id;
                $ext = File::extension($originalname);
                $file_name = $name.'.'.$ext;
                $path = $file->storeAs('public/student/', $file_name);
            }

            $homeworksubmissionArray = [];

            $homeworksubmissionArray['submission_remarks'] = $submission_remarks[$student_id];
            $homeworksubmissionArray['completion_status'] = 'Y';
            $homeworksubmissionArray['submission_image'] = $file_name;
            $homeworksubmissionArray['submission_image_size'] = $file_size;
            $homeworksubmissionArray['submission_image_type'] = $ext;

            studentHomeworkModel::where([
                "id"               => $student_id, 'syear' => $syear,
                'sub_institute_id' => $sub_institute_id,
            ])
                ->update($homeworksubmissionArray);
        }

        $res['status_code'] = "1";
        $res['message'] = "Homework Submited successfully";

        return is_mobile($type, "student_homework_submission.index", $res);
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

    public function studentHomeworkSubmissionReportIndex(Request $request)
    {
        $type = $request->input('type');
        $submit = $request->input('submit');
        $sub_institute_id = $request->session()->get('sub_institute_id');

        $subjects = subjectModel::select('id',
            'subject_name')->where(['sub_institute_id' => $sub_institute_id])->get()->toArray();

        $res['status_code'] = 1;
        $res['message'] = "Success";
        $res['subjects'] = $subjects;

        return is_mobile($type, "student/homework/show_student_homework_submission_report", $res, "view");
    }

    public function studentHomeworkSubmissionReport(Request $request)
    {
        $type = $request->input('type');
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $syear = $request->session()->get('syear');
        $subject = $request->input('subject');
        $grade = $request->input('grade');
        $standard = $request->input('standard');
        $division = $request->input('division');
        $from_date = $request->input('from_date');
        $to_date = $request->input('to_date');
        $submission_status = $request->input('status');
        $marking_period_id = session()->get('marking_period_id');

        $subjects = subjectModel::select('id',
            'subject_name')->where(['sub_institute_id' => $sub_institute_id])->get()->toArray();

        $result = DB::table('homework as ah')
            ->join('tblstudent as s', function ($join) {
                $join->whereRaw('s.id = ah.student_id AND s.sub_institute_id = ah.sub_institute_id');
            })->join('tblstudent_enrollment as se', function ($join) {
                $join->whereRaw('(s.id = se.student_id AND se.end_date IS NULL)');
            })->join('standard as cs', function ($join) use($marking_period_id) {
                $join->whereRaw('(cs.id = ah.standard_id)');
                // ->when($marking_period_id,function($query) use($marking_period_id) {
                //     $query->where('cs.marking_period_id',$marking_period_id);
                // });
            })->join('division as ss', function ($join) {
                $join->whereRaw('(ss.id = ah.division_id)');
            })->join('tbluser as tu', function ($join) {
                $join->whereRaw('tu.id = ah.created_by');
            })->selectRaw("ah.*,s.enrollment_no, CONCAT_WS(' ',s.last_name,s.first_name,s.middle_name) AS student_name,
                concat_ws('-',cs.name,ss.name) as std_div,s.mobile,DATE_FORMAT(ah.date,'%d-%m-%Y') AS HOMEWORK_DATE,ah.title,
                ah.description,ah.image,DATE_FORMAT(ah.submission_date,'%d-%m-%Y') AS SUBMISSION_DATE,ah.submission_remarks,
                CONCAT_WS(' ',tu.first_name,tu.last_name) AS submission_taken_by")
            ->where('se.syear', $syear)
            ->where('ah.sub_institute_id', $sub_institute_id)
            ->where('ah.syear', $syear);

        if ($standard != '') {
            $result = $result->where('ah.standard_id', $standard);
        }

        if ($subject != '') {
            $result = $result->where('ah.subject_id', $subject);
        }

        if ($division != '') {
            $result = $result->where('ah.division_id', $division);
        }

        if ($grade != '') {
            $result = $result->where('se.grade_id', $grade);
        }

        if ($submission_status != '' && $submission_status != '--Select Status--') {
            $result = $result->where('ah.completion_status', $submission_status);
        }

        if ($from_date != '' && $to_date != '') {
            $result = $result->whereRaw("DATE_FORMAT(ah.submission_date,'%Y-%m-%d') BETWEEN '".$from_date."' AND '".$to_date."'");
        }

        $result = $result->get()->toArray();

        $result = array_map(function ($value) {
            return (array) $value;
        }, $result);

        $res['status_code'] = 1;
        $res['message'] = "Success";
        $res['report_data'] = $result;
        $res['subjects'] = $subjects;
        $res['grade_id'] = $grade;
        $res['standard_id'] = $standard;
        $res['division_id'] = $division;
        $res['subject'] = $subject;
        $res['from_date'] = $from_date;
        $res['to_date'] = $to_date;
        $res['submission_status'] = $submission_status;

        return is_mobile($type, "student/homework/show_student_homework_submission_report", $res, "view");
    }
}
