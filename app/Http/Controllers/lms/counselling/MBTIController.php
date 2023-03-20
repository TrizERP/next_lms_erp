<?php

namespace App\Http\Controllers\lms\counselling;

use App\Http\Controllers\Controller;
use App\Models\lms\counselling\counsellingOnlineExamModel;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use function App\Helpers\is_mobile;

class MBTIController extends Controller
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
        $res['MBTI_data'] = $data['MBTI_data'];
        $res['breadcrum_data'] = $data['breadcrum_data'];

        return is_mobile($type, 'lms/counselling/show_MBTIPaper', $res, "view");
    }

    public function getData($request)
    {
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $course_id = $request->get('course_id');

        $data['MBTI_data'] = DB::table('MBTI_paper')->get()->toArray();

        $data['MBTI_data'] = $data['MBTI_data'][0]->html;

        $data['breadcrum_data'] = $this->getBreadcrum($sub_institute_id, $request->get('course_id'));

        return $data;
    }

    public function getBreadcrum($sub_institute_id, $course_id)
    {
        $breadcrum_data = DB::table('counselling_course as c')
            ->selectRaw('title as course_title,id as course_id')
            ->where('c.sub_institute_id', $sub_institute_id)
            ->where('c.id', $course_id)->get()->toArray();

        return $breadcrum_data[0];
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return Response
     */

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
        $answer = $request->get('first').$request->get('second').$request->get('third').$request->get('fourth');

        //START Insert into lms_online_exam table
        $MBTI_exam = [
            'user_id'          => $user_id,
            'sub_institute_id' => $sub_institute_id,
            'course_id'        => $request->get('course_id'),
            'total_right'      => 0,
            'total_wrong'      => 0,
            'obtain_marks'     => $answer,
        ];
        counsellingOnlineExamModel::insert($MBTI_exam);
        //END Insert into lms_online_exam table

        $answer_data = DB::table('MBTI_answer')->where('ans_key', $answer)->get()->toArray();

        $res['answer_data'] = $answer_data[0]->answer_html;
        $type = $request->input('type');

        return is_mobile($type, 'lms/counselling/show_MBTIResult', $res, "view");
    }
}
