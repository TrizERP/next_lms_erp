<?php

namespace App\Http\Controllers\result\exam_creation;

use App\Http\Controllers\Controller;
use App\Models\result\create_exam\exam_creation;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use function App\Helpers\is_mobile;

class exam_creation_controller extends Controller
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
                $data['message'] = $data_arr['message'];
            }
        }
        $data['data'] = $this->getData();

        $type = $request->input('type');

        return is_mobile($type, "result/exam_creation/show_exam", $data, "view");
    }

    /**
     * Show the form for creating a new resource.
     *
     * @param  Request  $request
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @return Response
     */
    public function create(Request $request)
    {
        $exams = DB::table("result_exam_master")
            ->where("SubInstituteId", session()->get('sub_institute_id'))
            ->pluck("ExamTitle", "Id")->toArray();
        $type = $request->input('type');

        return is_mobile($type, "result/exam_creation/add_exam", $exams, "view");
    }

    public function getData()
    {
        $join_arr = [
            'sub_std_map.standard_id'      => 'standard.id',
            'sub_std_map.subject_id'       => 'result_create_exam.subject_id',
            'sub_std_map.sub_institute_id' => 'result_create_exam.sub_institute_id',
        ];


        return DB::table('result_create_exam')
            ->join('academic_year', [
                'academic_year.term_id'          => 'result_create_exam.term_id',
                'academic_year.sub_institute_id' => 'result_create_exam.sub_institute_id',
            ])
            ->join('standard', 'standard.id', '=', 'result_create_exam.standard_id')
            ->join('sub_std_map', $join_arr)
            ->join('subject', 'sub_std_map.subject_id', '=', 'subject.id')
            ->join('academic_section', 'academic_section.id', '=', 'standard.grade_id')
            ->join('result_exam_master', 'result_exam_master.id', '=', 'result_create_exam.exam_id')
            ->select('result_create_exam.id', 'academic_year.title as term_name', 'result_create_exam.medium',
                'result_exam_master.ExamTitle as exam_type', 'result_create_exam.app_disp_status',
                'standard.name as std_name',
                'subject.subject_name as sub_name', 'result_create_exam.title as exam_name',
                'result_create_exam.points',
                'result_create_exam.marks_type', 'result_create_exam.report_card_status',
                'result_create_exam.sort_order',
                DB::raw('DATE_FORMAT(result_create_exam.exam_date,"%d-%m-%Y") as exam_date')
            )
            ->where([
                'result_create_exam.sub_institute_id' => session()->get('sub_institute_id'),
                'result_create_exam.syear'            => session()->get('syear'),
            ])
            ->groupby('result_create_exam.id')
            ->get()->toArray();
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  Request  $request
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @return Response
     */
    public function store(Request $request)
    {
        $eroor = false;
        $con_points = "";
        $error_reson = "";

        foreach ($request->get('subject') as $sub_id => $sub_val) 
        {
            foreach ($request->get('title') as $key => $value) 
            {
                $data = exam_creation::where([
                    'syear'            => session()->get('syear'),
                    'sub_institute_id' => session()->get('sub_institute_id'),
                    'term_id'          => $request->get('term'),
                    'exam_id'          => $request->get('exam'),
                    'standard_id'      => $request->get('standard'),
                    'subject_id'       => $sub_val,
                    'title'            => $value,
                ])->get()->toArray();

                if (count($data)) {
                    $eroor = true;
                    $error_reson = "Given Standard Have Exams.";
                } 
                else 
                {
                    $data = exam_creation::where([
                        'syear'            => session()->get('syear'),
                        'sub_institute_id' => session()->get('sub_institute_id'),
                        'term_id'          => $request->get('term'),
                        'exam_id'          => $request->get('exam'),
                        'standard_id'      => $request->get('standard'),
                        'subject_id'       => $sub_val,
                    ])->get()->toArray();
                }
            }
        }

        if ($eroor == false) {
            $sort = $request->get('sort_order');
            
            $error_co_point = false;

            foreach ($request->get('subject') as $sub_id => $sub_val) 
            {
                foreach ($request->get('title') as $key => $value) 
                {
                    if ($error_co_point == false) 
                    {
                        $data = new exam_creation([
                            'syear'              => session()->get('syear'),
                            'sub_institute_id'   => session()->get('sub_institute_id'),
                            'term_id'            => $request->get('term'),
                            'medium'             => $request->get('medium'),
                            'exam_id'            => $request->get('exam'),
                            'standard_id'        => $request->get('standard'),
                            'app_disp_status'    => $request->get('app_disp_status'),
                            'subject_id'         => $sub_val,
                            'title'              => $value,
                            'points'             => $request->get('points')[$key] ?? 0,
                            'con_point'          => $request->get('con_point'),
                            'marks_type'         => $request->get('marks_type'),
                            'report_card_status' => $request->get('report_card_status'),
                            'sort_order'         => $sort[$key] ?? 0,
                            'exam_date'          => isset($request->get('exam_date')[$key]) ? date("Y-m-d", strtotime($request->get('exam_date')[$key])) : null,
                        ]);
                        $data->save();
                    }
                }
            }
        }
        if ($eroor || $error_co_point) {
            $res = [
                "status_code" => 0,
                "message"     => $error_reson,
            ];
        } else {
            $res = [
                "status_code" => 1,
                "message"     => "Data Saved",
            ];
        }

        $type = $request->input('type');

        return is_mobile($type, "exam_creation.index", $res, "redirect");
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
     * @param  Request  $request
     * @param  int  $id
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @return Response
     */
    public function edit(Request $request, $id)
    {
        $type = $request->input('type');
        $data = exam_creation:: select('result_create_exam.id', 'result_create_exam.term_id',
            'result_create_exam.medium',
            'result_create_exam.exam_id', 'result_create_exam.app_disp_status', 'standard.grade_id as grade',
            'result_create_exam.standard_id', 'result_create_exam.subject_id', 'result_create_exam.title',
            'result_create_exam.points',
            'result_create_exam.marks_type', 'result_create_exam.report_card_status', 'result_create_exam.sort_order',
            'result_create_exam.exam_date', 'result_create_exam.con_point')
            ->join('standard', 'standard.id', '=', 'result_create_exam.standard_id')
            ->where([
                'result_create_exam.sub_institute_id' => session()->get('sub_institute_id'),
                'result_create_exam.id'               => $id,
            ])
            ->get()->toArray();
        $data = $data[0];

        $exams = DB::table("result_exam_master")
            ->where("SubInstituteId", session()->get('sub_institute_id'))
            ->pluck("ExamTitle", "Id")->toArray();
        $data['exams'] = $exams;

        return is_mobile($type, "result/exam_creation/edit_exam", $data, "view");
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  Request  $request
     * @param  int  $id
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @return Response
     */
    public function update(Request $request, $id)
    {
        $eroor = false;
        $con_points = "";
        $error_reson = "";
        $data = exam_creation::where([
            'syear'            => session()->get('syear'),
            'sub_institute_id' => session()->get('sub_institute_id'),
            'term_id'          => $request->get('term'),
            'exam_id'          => $request->get('exam_id'),
            'standard_id'      => $request->get('standard'),
            'subject_id'       => $request->get('subject'),
            'title'            => $request->get('title'),
        ])->get()->toArray();

        if (count($data) > 1) {
            $eroor = true;
            $error_reson = "Given Standard Have Exams.";
        } else {
            $data = exam_creation::
            where([
                'syear'            => session()->get('syear'),
                'sub_institute_id' => session()->get('sub_institute_id'),
                'term_id'          => $request->get('term'),
                'exam_id'          => $request->get('exam_id'),
                'standard_id'      => $request->get('standard'),
                'subject_id'       => $request->get('subject'),
            ])
                ->get()->toArray();
            // if (count($data)) {
            //     foreach ($data as $id1 => $arr) {
            //         $con_points = $arr['con_point'];
            //     }
            // }
        }
        if ($eroor == false) {
            $error_co_point = false;
            // if ($request->get('con_point') != '') {
            //     if ($con_points != "") {
            //         if ($con_points != $request->get('con_point')) {
            //             $error_reson = "Convert Point Is Not Matching With Other Exam.";
            //             $error_co_point = true;
            //         }
            //     }
            // }
            $data = [
                'syear'              => session()->get('syear'),
                'sub_institute_id'   => session()->get('sub_institute_id'),
                'term_id'            => $request->get('term'),
                'medium'             => $request->get('medium'),
                'exam_id'            => $request->get('exam_id'),
                'standard_id'        => $request->get('standard'),
                'app_disp_status'    => $request->get('app_disp_status'),
                'subject_id'         => $request->get('subject'),
                'title'              => $request->get('title'),
                'points'             => $request->get('points'),
                'con_point'          => $request->get('con_point'),
                'marks_type'         => $request->get('marks_type'),
                'report_card_status' => $request->get('report_card_status'),
                'sort_order'         => $request->get('sort_order'),
                'exam_date'          => date("Y-m-d", strtotime($request->get('exam_date'))),

            ];


            exam_creation::where(["id" => $id])->update($data);
        }
        if ($eroor || $error_co_point) {
            $res = [
                "status_code" => 0,
                "message"     => $error_reson,
            ];
        } else {
            $res = [
                "status_code" => 1,
                "message"     => "Data Saved",
            ];
        }

        $type = $request->input('type');

        return is_mobile($type, "exam_creation.index", $res, "redirect");
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return Response
     */
    public function destroy(Request $request, $id)
    {
        $type = $request->input('type');
        exam_creation::where(["id" => $id])->delete();
        $res = [
            "status_code" => 1,
            "message"     => "Data Deleted",
        ];

        return is_mobile($type, "exam_creation.index", $res, "redirect");
    }

}
