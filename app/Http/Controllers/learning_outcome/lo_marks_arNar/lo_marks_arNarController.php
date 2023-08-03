<?php

namespace App\Http\Controllers\learning_outcome\lo_marks_arNar;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use function App\Helpers\is_mobile;

class lo_marks_arNarController extends Controller
{

    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index(Request $request)
    {
        if (session()->has('data')) { // check if it exists
            $data_arr = session('data'); // to retrieve value
            if (isset($data_arr['message'])) {
                $school_data['message'] = $data_arr['message'];
            }
        }

        $school_data['data'] = $this->get_all_dd();

        $type = $request->input('type');

        return is_mobile($type, 'learning_outcome/lo_marks_arNar/show', $school_data, 'view');
    }

    public function getData()
    {
        $data = DB::table('learning_outcome_indicator')->get();
        $i = 1;
        foreach ($data as $key => $arr) {
            $arr->SrNo = $i;
            $i++;
        }

        return $data;
    }

    public function get_all_dd()
    {
        $result = DB::table("learning_outcome_pdf")
            ->selectRaw('MEDIUM')
            ->groupBy('MEDIUM')
            ->get()->toArray();

        $medium = [];
        foreach ($result as $id => $arr) {
            $medium[$arr->MEDIUM] = $arr->MEDIUM;
        }

        $result = DB::table("learning_outcome_pdf")
            ->selectRaw('STANDARD')
            ->groupBy('STANDARD')
            ->get()->toArray();

        $std = [];
        foreach ($result as $id => $arr) {
            $std[$arr->STANDARD] = $arr->STANDARD;
        }

        $result = DB::table("school_sections")
            ->selectRaw('section_id,section_name as DIVISION')
            ->get()->toArray();

        $div = [];
        foreach ($result as $id => $arr) {
            $div[$arr->section_id] = $arr->DIVISION;
        }

        return [
            'medium' => $medium,
            'std'    => $std,
            'div'    => $div,
        ];
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return Response
     */
    public function create(Request $request)
    {
        $medium = $_REQUEST['medium'];
        $std = $_REQUEST['std'];
        $lo = $_REQUEST['lo'];
        $subject = $_REQUEST['subject'];
        $marking_period_id = session()->get('term_id');

        $result = DB::table("learning_outcome_question_master")
            ->where("MEDIUM", "=", $medium)
            ->where("STANDARD", "=", $std)
            ->where("SUBJECT", "=", $subject)
            ->where("SYEAR", "=", $request->session()->get('syear'));

        if ($lo != '') {
            $result = $result->where('INDICATORE_ID', $lo);
        }

        $result = $result->get()->toArray();


        $id_arr = [];
        $total = 0;
        foreach ($result as $key => $value) {
            $id_arr[] = $value->ID;
            $total += $value->QUESTION_OUT_OF;
        }
        $ids = implode(',', $id_arr);

        $sub_institute_id = $request->session()->get('sub_institute_id');
        $syear = $request->session()->get('syear');

        $students = DB::table("tblstudent as s")
            ->join('tblstudent_enrollment as se', function ($join) {
                $join->whereRaw("se.student_id = s.id");
            })
            ->join('standard as stds', function ($join) use($marking_period_id){
                $join->whereRaw("stds.id = se.standard_id");
                // ->when($marking_period_id,function($query) use($marking_period_id){
                //     $query->where('stds.marking_period_id',$marking_period_id);
                // });
            })
            ->join('division as d', function ($join) {
                $join->whereRaw("d.id = se.section_id");
            })
            ->join('learning_outcome_question_master as li', function ($join) use ($ids, $id_arr) {
                $join->whereIn("li.ID", $id_arr);
            })
            ->join('learning_outcome_indicator as lo', function ($join) {
                $join->whereRaw("lo.ID = li.INDICATORE_ID");
            })
            ->join('learning_outcome_student_marks as lom', function ($join) use ($sub_institute_id) {
                $join->whereRaw("se.student_id = lom.STUDENT_ID AND li.ID = lom.QUESTION_ID AND lom.sub_institute_id = '$sub_institute_id'");
            })
            ->selectRaw("concat_ws(' ',s.first_name,s.middle_name,s.last_name) stu_name,
                stds.name, lo.INDICATOR,
                if(ROUND((sum(lom.MARKS)*100/sum(li.QUESTION_OUT_OF)),2)<50,'NOT ACHIEVED','ACHIEVED') AR,
                sum(li.QUESTION_OUT_OF) out_of,
                sum(lom.MARKS) got_marks,
                ROUND((sum(lom.MARKS)*100/sum(li.QUESTION_OUT_OF)),2) as per")
            ->groupBy('lo.ID', 's.id')
            ->get()->toArray();

        $type = $request->input('type');

        $dataStore['stud'] = $students;

        return is_mobile($type, 'learning_outcome/lo_marks_arNar/add', $dataStore, 'view');
    }


    /**
     * Store a newly created resource in storage.
     *
     * @param  Request  $request
     *
     * @return Response
     */
    public function store(Request $request)
    {
        foreach ($_REQUEST['result'] as $student_id => $value) {
            foreach ($value as $question_id => $mark) {
                if ($mark != '') {
                    $sub_institute_id = $request->session()->get('sub_institute_id');

                    $result = DB::table("learning_outcome_student_marks")
                        ->where("STUDENT_ID", "=", $student_id)
                        ->where("SUB_INSTITUTE_ID", "=", $sub_institute_id)
                        ->where("MEDIUM", "=", $request->get('medium'))
                        ->where("STANDARD", "=", $request->get('std'))
                        ->where("SUBJECT", "=", $request->get('subject'))
                        ->where("QUESTION_ID", "=", $question_id)
                        ->where("DATE", "=", $request->get('examdate'))
                        ->get()->toArray();

                    if (count($result) == 0) {
                        $data = [
                            'SUB_INSTITUTE_ID'         => $request->session()->get('sub_institute_id'),
                            'STUDENT_ID'               => $student_id,
                            'MEDIUM'                   => $request->get('medium'),
                            'STANDARD'                 => $request->get('std'),
                            'SUBJECT'                  => $request->get('subject'),
                            'QUESTION_ID'              => $question_id,
                            'DATE'                     => $request->get('examdate'),
                            'CREATED_BY'               => $request->session()->get('user_id'),
                            'CREATED_ON'               => now(),
                            'CREATED_BY_USER_GROUP_ID' => $request->session()->get('user_group_id'),
                            'SYEAR'                    => $request->session()->get('syear'),
                            'MARKS'                    => $mark,
                        ];

                        DB::table('learning_outcome_student_marks')->insert(
                            $data
                        );
                    } else {
                        $where = [
                            "STUDENT_ID"       => $student_id,
                            "SUB_INSTITUTE_ID" => $sub_institute_id,
                            "MEDIUM"           => $request->get('medium'),
                            "STANDARD"         => $request->get('std'),
                            "SUBJECT"          => $request->get('subject'),
                            "QUESTION_ID"      => $question_id,
                            "DATE"             => $request->get('examdate'),
                        ];

                        $data = [
                            'SUB_INSTITUTE_ID'         => $request->session()->get('sub_institute_id'),
                            'STUDENT_ID'               => $student_id,
                            'MEDIUM'                   => $request->get('medium'),
                            'STANDARD'                 => $request->get('std'),
                            'SUBJECT'                  => $request->get('subject'),
                            'QUESTION_ID'              => $question_id,
                            'DATE'                     => $request->get('examdate'),
                            'CREATED_BY'               => $request->session()->get('user_id'),
                            'CREATED_ON'               => now(),
                            'CREATED_BY_USER_GROUP_ID' => $request->session()->get('user_group_id'),
                            'SYEAR'                    => $request->session()->get('syear'),
                            'MARKS'                    => $mark,
                        ];

                        DB::table('learning_outcome_student_marks')
                            ->where($where)
                            ->update($data);
                    }
                }
            }
        }

        $res = [
            'status_code' => 1,
            'message'     => 'Data Saved',
        ];

        $type = $request->input('type');

        return is_mobile($type, 'lo_marks_arNar.index', $res, 'redirect');
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     *
     * @return void
     */
    public function show($id)
    {

    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     *
     * @return Response
     */
    public function edit(Request $request, $id)
    {
        $all_dd = $this->get_all_dd();

        $allData = DB::table("learning_outcome_indicator")
            ->where("ID", "=", $id)
            ->get()->toArray();

        $standard = $allData[0]->STANDARD;
        $medium = $allData[0]->MEDIUM;

        $where = [
            'learning_outcome_pdf.standard' => $standard,
            'learning_outcome_pdf.medium'   => $medium,
        ];

        $std_sub_map = DB::table('learning_outcome_pdf')
            ->where($where)
            ->pluck('learning_outcome_pdf.DISPLAY_SUBJECT', 'learning_outcome_pdf.SUBJECTS');

        $data = [
            'medium'           => $all_dd['medium'],
            'std'              => $all_dd['std'],
            'selected_medium'  => $allData[0]->MEDIUM,
            'selected_std'     => $allData[0]->STANDARD,
            'selected_subject' => $allData[0]->SUBJECT,
            'learning_outcome' => $allData[0]->INDICATOR,
            'subject'          => $std_sub_map,
            'id'               => $id,
        ];

        $type = $request->input('type');

        return is_mobile($type, "learning_outcome/lo_marks_arNar/edit", $data, "view");
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  Request  $request
     * @param  int  $id
     *
     * @return Response
     */
    public function update(Request $request, $id)
    {
        $data = [
            'MEDIUM'     => $request->get('medium'),
            'STANDARD'   => $request->get('std'),
            'SUBJECT'    => $request->get('subject'),
            'INDICATOR'  => $request->get('learning_outcome'),
            'UPDATED_AT' => now(),
            'UPDATED_BY' => $request->session()->get('user_id'),
        ];

        DB::table('learning_outcome_indicator')
            ->where(["ID" => $id])
            ->update($data);

        $res = [
            "status_code" => 1,
            "message"     => "Data Saved",
        ];
        $type = $request->input('type');

        return is_mobile($type, "lo_marks_arNar.index", $res, "redirect");
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     *
     * @return Response
     */
    public function destroy(Request $request, $id)
    {
        $type = $request->input('type');

        DB::table('learning_outcome_indicator')
            ->where(["ID" => $id])
            ->delete();

        $res = [
            "status_code" => 1,
            "message"     => "Data Deleted",
        ];

        return is_mobile($type, "lo_marks_arNar.index", $res, "redirect");
    }
}
