<?php

namespace App\Http\Controllers\lms\counselling;

use App\Http\Controllers\Controller;
use App\Models\lms\counselling\counsellingCourseModel;
use App\Models\lms\counselling\counsellingOnlineExamModel;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use function App\Helpers\is_mobile;

class lmsCounsellingController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index(Request $request)
    {
        $data = $this->getData($request);
        $type = $request->input('type');
        $res['status_code'] = 1;
        $res['message'] = "SUCCESS";
        $res['counselling_course'] = $data['courses'];
        $res['user_data'] = $data['final_user_data'];

        return is_mobile($type, 'lms/counselling/show_lmsCounselling', $res, "view");
    }

    public function getData($request)
    {

        $sub_institute_id = $request->session()->get('sub_institute_id');
        $user_id = $request->session()->get('user_id');

        $data['courses'] = counsellingCourseModel::select("counselling_course.*",
            DB::raw('count(q.`id`) as total_ques'))
            ->leftjoin('counselling_question_master as q', 'q.counselling_course_id', 'counselling_course.id')
            ->where(['counselling_course.sub_institute_id' => $sub_institute_id])
            ->groupby('counselling_course.id')
            ->orderby('counselling_course.sort_order')
            ->get()
            ->toArray();

        $data['final_user_data'] = [];
        $data['user_data'] = counsellingOnlineExamModel::select("counselling_online_exam.*",
            DB::raw('SUM(q.points) as total_points,count(q.id) as total_ques,DATE_FORMAT(created_at,"%Y-%m-%d") AS exam_date'))
            ->leftjoin('counselling_question_master as q', 'q.counselling_course_id',
                'counselling_online_exam.course_id')
            ->where([
                'counselling_online_exam.sub_institute_id' => $sub_institute_id,
                'counselling_online_exam.user_id'          => $user_id,
            ])
            ->groupby('counselling_online_exam.id')
            ->get()
            ->toArray();

        foreach ($data['user_data'] as $key => $val) {
            $data['final_user_data'][$val['course_id']][] = $val;
        }

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
     * @return void
     */
    public function store(Request $request)
    {

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
    public function edit(Request $request, $id)
    {

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

    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return void
     */
    public function destroy(Request $request, $id)
    {

    }

}
