<?php

namespace App\Http\Controllers\lms\assignment;

use App\Http\Controllers\Controller;
use App\Models\lms\assignment\lms_assignmentModel;
use App\Models\lms\lmsOfflineExamModel;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use function App\Helpers\is_mobile;

class assignmentSubmissionController extends Controller
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
        $data = $this->getData($request);
        $res['assignment_data'] = $data['assignment_data'];

        return is_mobile($type, "lms/assignment/assignment_submission", $res, "view");
    }

    public function getData($request)
    {
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $syear = $request->session()->get('syear');
        $student_id = $request->session()->get('user_id');

        $data['assignment_data'] = array();
        if (strtoupper(session()->get('user_profile_name')) == "STUDENT") {
            $data['assignment_data'] = DB::table('lms_assignment as a')
                ->select('a.*', 'subject_name')
                ->join('subject as s', 's.id', 'a.subject_id')
                ->where(['a.sub_institute_id' => $sub_institute_id, 'a.syear' => $syear, 'student_id' => $student_id])
                ->get()->toArray();
        }
        $data['assignment_data'] = json_decode(json_encode($data['assignment_data']), true);

        return $data;
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return void
     */
    public function create(Request $request)
    {
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
        $syear = $request->session()->get('syear');
        $student_id = $request->session()->get('user_id');

        $assignments = $request->file('image');
        foreach ($assignments as $assignment_id => $image) {
            $file_name = "";
            $file = $request->file('image')[$assignment_id];
            $originalname = $file->getClientOriginalName();
            $name = 'assignment_'.$assignment_id.'-'.date('YmdHis').'-'.$student_id;
            $ext = File::extension($originalname);
            $file_name = $name.'.'.$ext;
            $path = $file->storeAs('public/lms_assignment_submission', $file_name);

            $submissionArray = [];

            $submissionArray['submission_image'] = $file_name;
            $submissionArray['student_submitted_date'] = date('Y-m-d');
            $submissionArray['student_submission_status'] = 'Y';
            $submissionArray['student_submitted_by'] = $student_id;

            lms_assignmentModel::where([
                "id" => $student_id, 'syear' => $syear, 'sub_institute_id' => $sub_institute_id, 'id' => $assignment_id,
            ])
                ->update($submissionArray);
        }
        $type = $request->get('type');

        $res['status_code'] = "1";
        $res['message'] = "Assignment Submited successfully";

        return is_mobile($type, "lmsAssignment_submission.index", $res);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return Response
     */
    public function show(Request $request, $id)
    {

        $sub_institute_id = $request->session()->get('sub_institute_id');
        $syear = $request->session()->get('syear');
        $student_id = $request->get("student_id");
        $questionpaper_id = $request->get("question_paper_id");

        $data['questionpaper_data'] = lmsOfflineExamModel::select('*', 'sub_std_map.display_name as subject_name',
            'lms_offline_exam.id as offline_exam_id')
            ->join('question_paper', 'question_paper.id', 'lms_offline_exam.question_paper_id')
            ->join("sub_std_map", function ($join) {
                $join->on("sub_std_map.subject_id", "=", "question_paper.subject_id")
                    ->on("sub_std_map.standard_id", "=", "question_paper.standard_id");
            })
            ->where([
                'lms_offline_exam.assignment_id'    => $id, 'lms_offline_exam.student_id' => $student_id,
                'lms_offline_exam.sub_institute_id' => $sub_institute_id,
            ])
            ->get()->toArray();

        $data['questionpaper_data'] = $data['questionpaper_data'][0];

        $offline_exam_id = $data['questionpaper_data']['offline_exam_id'];

        $pdata = DB::select("SELECT *,'100' as total_percentage,
            round(((a.right_answer*100)/total_question),2) as obtained_percentage from (
            SELECT lt.id,lt.name,COUNT(mapping_type_id) as total_question,group_concat(e.question_id) as ques_list,
            sum((case when e.ans_status = 'right' then '1' end)) as right_answer
            FROM lms_question_mapping l
            INNER JOIN lms_mapping_type lt ON lt.id = l.mapping_value_id
            LEFT JOIN lms_offline_exam_answer e on e.question_id = l.questionmaster_id and e.question_paper_id = '".$questionpaper_id."' AND
            e.student_id = '".$student_id."' and e.offline_exam_id = '".$offline_exam_id."'
            WHERE questionmaster_id IN (
                    SELECT question_id
                    FROM lms_offline_exam_answer
                    WHERE question_paper_id = '".$questionpaper_id."' AND student_id = '".$student_id."'
                    AND offline_exam_id = '".$offline_exam_id."'
                )
            GROUP BY mapping_value_id
            ORDER BY mapping_type_id,mapping_value_id) as a
        ");

        $progressbar_data = json_decode(json_encode($pdata), true);

        $data['progressbar_data'] = $progressbar_data;

        $type = $request->input('type');

        return is_mobile($type, 'lms/offline_attempted_result', $data, "view");
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
