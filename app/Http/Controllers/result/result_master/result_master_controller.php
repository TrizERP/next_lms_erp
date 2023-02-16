<?php

namespace App\Http\Controllers\result\result_master;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\result\result_master\result_master_confrigration;
use Symfony\Component\HttpFoundation\File;
use DB;

class result_master_controller extends Controller {

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request) {

        if (session()->has('data')) { // check if it exists
            $data_arr = session('data'); // to retrieve value
            if (isset($data_arr['message'])) {
                $data['message'] = $data_arr['message'];
            }
        }
        $data['data'] = $this->getData();
        $type = $request->input('type');
//        echo "here";
//exit;
        return \App\Helpers\is_mobile($type, "result/result_master/show_result_master", $data, "view");
    }

    public function getData() {
        $data = result_master_confrigration::orderBy('id')->get();
//, 'academic_section.title grade_name', 'standard.name standard_name', 'division.name division_name', 'DATE_FORMAT(result_master_confrigration.result_date,"%d-%m-%Y") result_date', 'DATE_FORMAT(result_master_confrigration.reopen_date,"%d-%m-%Y") reopen_date', 'DATE_FORMAT(result_master_confrigration.vaction_start_date,"%d-%m-%Y") vaction_start_date', 'DATE_FORMAT(result_master_confrigration.vaction_end_date,"%d-%m-%Y") vaction_end_date', 'result_master_confrigration.teacher_sign', 'result_master_confrigration.principal_sign', 'result_master_confrigration.director_signatiure', 'result_master_confrigration.result_remark', 'result_master_confrigration.optional_subject_display', 'result_master_confrigration.remove_fail_per'
        $data = DB::table('result_master_confrigration')
                        ->join('academic_year', ['academic_year.term_id' => 'result_master_confrigration.term_id', 'academic_year.sub_institute_id' => 'result_master_confrigration.sub_institute_id', 'academic_year.syear' => 'result_master_confrigration.syear'])
                        ->join('standard', 'standard.id', '=', 'result_master_confrigration.standard_id')
                        ->join('academic_section', 'academic_section.id', '=', 'standard.grade_id')

//                        ->join('division', 'division.id', '=', 'result_master_confrigration.division_id')
                        ->select('result_master_confrigration.id', 'academic_year.title as term_name', 'academic_section.title as grade_name', 'standard.name as standard_name', DB::raw('DATE_FORMAT(result_master_confrigration.result_date,"%d-%m-%Y") as result_date'), DB::raw('DATE_FORMAT(result_master_confrigration.reopen_date,"%d-%m-%Y") as reopen_date'), DB::raw('DATE_FORMAT(result_master_confrigration.vaction_start_date,"%d-%m-%Y") as vaction_start_date'), DB::raw('DATE_FORMAT(result_master_confrigration.vaction_end_date,"%d-%m-%Y") as vaction_end_date'), 'result_master_confrigration.teacher_sign', 'result_master_confrigration.principal_sign', 'result_master_confrigration.director_signatiure', DB::raw('if(result_master_confrigration.result_remark = "grade_master","Grade Master","Student Wise") as result_remark'), DB::raw('if(result_master_confrigration.optional_subject_display = "y","Yes","No") as optional_subject_display'), DB::raw('if(result_master_confrigration.remove_fail_per = "y","Yes","No") as remove_fail_per'))
                        ->where([
                                'result_master_confrigration.sub_institute_id' => session()->get('sub_institute_id'),
                                'result_master_confrigration.syear' => session()->get('syear')
                            ])
                        ->get()->toArray();
//        echo "<pre>";
//        print_r($data);
//        exit;
        return $data;
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create() {
        return view('result/result_master/add_result_master');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request) {
        //
        $teacher_sign = "";
        $principal_sign = "";
        $director_signatiure = "";
        if ($request->hasFile('teacher_sign')) {
            $file = $request->file('teacher_sign');
            $originalname = $file->getClientOriginalName();
            $name = date('YmdHis');
            $ext = \File::extension($originalname);
            $teacher_sign = $name . '.' . $ext;
            $path = $file->storeAs('public/result/teacher_sign/', $teacher_sign);
        }
        if ($request->hasFile('principal_sign')) {
            $file = $request->file('principal_sign');
            $originalname = $file->getClientOriginalName();
            $name = date('YmdHis');
            $ext = \File::extension($originalname);
            $principal_sign = $name . '.' . $ext;
            $path = $file->storeAs('public/result/principle_sign/', $principal_sign);
        }
        if ($request->hasFile('director_signatiure')) {
            $file = $request->file('director_signatiure');
            $originalname = $file->getClientOriginalName();
            $name = date('YmdHis');
            $ext = \File::extension($originalname);
            $director_signatiure = $name . '.' . $ext;
            $path = $file->storeAs('public/result/director_sign', $director_signatiure);
        }

        $eroor = false;
        foreach ($request->get('standard') as $id => $val) {
            $data = result_master_confrigration::
                            select('result_master_confrigration.id', 'result_master_confrigration.term_id as term', 'result_master_confrigration.standard_id', 'standard.grade_id as grade', DB::raw('DATE_FORMAT(result_master_confrigration.result_date,"%d-%m-%Y") as result_date'), DB::raw('DATE_FORMAT(result_master_confrigration.reopen_date,"%d-%m-%Y") as reopen_date'), DB::raw('DATE_FORMAT(result_master_confrigration.vaction_start_date,"%d-%m-%Y") as vaction_start_date'), DB::raw('DATE_FORMAT(result_master_confrigration.vaction_end_date,"%d-%m-%Y") as vaction_end_date'), 'result_master_confrigration.teacher_sign', 'result_master_confrigration.principal_sign', 'result_master_confrigration.director_signatiure', 'result_master_confrigration.result_remark', 'result_master_confrigration.optional_subject_display', 'result_master_confrigration.remove_fail_per')
                            ->join('standard', 'standard.id', '=', 'result_master_confrigration.standard_id')
                            ->join('academic_section', 'academic_section.id', '=', 'standard.grade_id')
                            ->where([
                                'result_master_confrigration.sub_institute_id' => session()->get('sub_institute_id'),
                                'result_master_confrigration.syear' => session()->get('syear'),
                                'result_master_confrigration.standard_id' => $val
                            ])
                            ->get()->toArray();
            if (count($data)) {
                $eroor = true;
            }
        }
        if ($eroor == false) {
            foreach ($request->get('standard') as $id => $val) {
                $data = new result_master_confrigration([
                    'term_id' => $request->get('term'),
                    'sub_institute_id' => session()->get('sub_institute_id'),
                    'syear' => session()->get('syear'),
                    'standard_id' => $val,
                    'result_date' => date("Y-m-d", strtotime($request->get('result_date'))),
                    'reopen_date' => date("Y-m-d", strtotime($request->get('reopen_date'))),
                    'vaction_start_date' => date("Y-m-d", strtotime($request->get('vaction_start_date'))),
                    'vaction_end_date' => date("Y-m-d", strtotime($request->get('vaction_end_date'))),
                    'teacher_sign' => $teacher_sign,
                    'principal_sign' => $principal_sign,
                    'director_signatiure' => $director_signatiure,
                    'result_remark' => $request->get('result_remark'),
                    'optional_subject_display' => $request->get('optional_subject_display'),
                    'remove_fail_per' => $request->get('remove_fail_per'),
                ]);
                $data->save();
            }
        }
        if ($eroor) {
            $res = array(
                "status_code" => 0,
                "message" => "Given Standard Have Settings.",
            );
        } else {
            $res = array(
                "status_code" => 1,
                "message" => "Data Saved",
            );
        }


        $type = $request->input('type');
        return \App\Helpers\is_mobile($type, "result_master.index", $res, "redirect");
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
    public function edit(Request $request, $id) {

        $type = $request->input('type');
        $data = result_master_confrigration::
                        select('result_master_confrigration.id', 'result_master_confrigration.term_id as term', 'result_master_confrigration.standard_id', 'standard.grade_id as grade', DB::raw('DATE_FORMAT(result_master_confrigration.result_date,"%d-%m-%Y") as result_date'), DB::raw('DATE_FORMAT(result_master_confrigration.reopen_date,"%d-%m-%Y") as reopen_date'), DB::raw('DATE_FORMAT(result_master_confrigration.vaction_start_date,"%d-%m-%Y") as vaction_start_date'), DB::raw('DATE_FORMAT(result_master_confrigration.vaction_end_date,"%d-%m-%Y") as vaction_end_date'), 'result_master_confrigration.teacher_sign', 'result_master_confrigration.principal_sign', 'result_master_confrigration.director_signatiure', 'result_master_confrigration.result_remark', 'result_master_confrigration.optional_subject_display', 'result_master_confrigration.remove_fail_per')
                        ->join('standard', 'standard.id', '=', 'result_master_confrigration.standard_id')
                        ->join('academic_section', 'academic_section.id', '=', 'standard.grade_id')
                        ->where([
                            'result_master_confrigration.sub_institute_id' => session()->get('sub_institute_id'),
                            'result_master_confrigration.syear' => session()->get('syear'),
                            'result_master_confrigration.id' => $id
                            ])
                        ->get()->toArray();
        $data = $data[0];

        return \App\Helpers\is_mobile($type, "result/result_master/edit_result_master", $data, "view");
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id) {

//        echo "<pre>";
//        print_r($_REQUEST);
//        print_r($_FILES);
//        exit;
        $teacher_sign = "";
        $principal_sign = "";
        $director_signatiure = "";
        if ($request->hasFile('teacher_sign')) {
            if ($_FILES['teacher_sign']['error'] == 0) {
                $file = $request->file('teacher_sign');
                $originalname = $file->getClientOriginalName();
                $name = date('YmdHis');
                $ext = \File::extension($originalname);
                $teacher_sign = $name . '.' . $ext;
                $path = $file->storeAs('public/result/teacher_sign/', $teacher_sign);
            }
        }
        if ($request->hasFile('principal_sign')) {
            if ($_FILES['principal_sign']['error'] == 0) {
                $file = $request->file('principal_sign');
                $originalname = $file->getClientOriginalName();
                $name = date('YmdHis');
                $ext = \File::extension($originalname);
                $principal_sign = $name . '.' . $ext;
                $path = $file->storeAs('public/result/principle_sign/', $principal_sign);
            }
        }
        if ($request->hasFile('director_signatiure')) {
            if ($_FILES['director_signatiure']['error'] == 0) {
                $file = $request->file('director_signatiure');
                $originalname = $file->getClientOriginalName();
                $name = date('YmdHis');
                $ext = \File::extension($originalname);
                $director_signatiure = $name . '.' . $ext;
                $path = $file->storeAs('public/result/director_sign', $director_signatiure);
            }
        }

        $data = array([
                'term_id' => $request->get('term'),
                'sub_institute_id' => session()->get('sub_institute_id'),
                'syear' => session()->get('syear'),
                'standard_id' => $request->get('standard'),
                'result_date' => date("Y-m-d", strtotime($request->get('result_date'))),
                'reopen_date' => date("Y-m-d", strtotime($request->get('reopen_date'))),
                'vaction_start_date' => date("Y-m-d", strtotime($request->get('vaction_start_date'))),
                'vaction_end_date' => date("Y-m-d", strtotime($request->get('vaction_end_date'))),
                'result_remark' => $request->get('result_remark'),
                'optional_subject_display' => $request->get('optional_subject_display'),
                'remove_fail_per' => $request->get('remove_fail_per'),
        ]);
        if ($teacher_sign != '') {
            $data[0]['teacher_sign'] = $teacher_sign;
        }
        if ($principal_sign != '') {
            $data[0]['principal_sign'] = $principal_sign;
        }
        if ($director_signatiure != '') {
            $data[0]['director_signatiure'] = $director_signatiure;
        }
        $data = $data[0];

        result_master_confrigration::where(["id" => $id])->update($data);

        $res = array(
            "status_code" => 1,
            "message" => "Data Saved",
        );
        $type = $request->input('type');

        return \App\Helpers\is_mobile($type, "result_master.index", $res, "redirect");
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy(Request $request, $id) {
        $type = $request->input('type');
        result_master_confrigration::where(["id" => $id])->delete();
        $res = array(
            "status_code" => 1,
            "message" => "Data Deleted",
        );

        return \App\Helpers\is_mobile($type, "result_master.index", $res, "redirect");
    }

}
