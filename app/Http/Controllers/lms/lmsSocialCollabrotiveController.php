<?php

namespace App\Http\Controllers\lms;

use App\Http\Controllers\Controller;
use App\Models\lms\doubtModel;
use App\Models\student\tblstudentEnrollmentModel;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use function App\Helpers\is_mobile;

class lmsSocialCollabrotiveController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index(Request $request)
    {
        $type = $request->input('type');
        $res['status_code'] = 1;
        $res['message'] = "SUCCESS";
        $data = $this->getData($request);
        $res['doubt_data'] = $data['doubt_data'];
        $res['doubt_conversation_data'] = $data['doubt_conversation_data'];

        return is_mobile($type, 'lms/show_lmsSocialCollabrotivenew', $res, "view");
    }

    public function getData($request)
    {

        $sub_institute_id = $request->session()->get('sub_institute_id');
        $syear = $request->session()->get('syear');
        $user_id = $request->session()->get('user_id');
        $user_profile_id = $request->session()->get('user_profile_id');

        $data['doubt_data'] = $data['doubt_conversation_data'] = array();
        if (strtoupper(session()->get('user_profile_name')) == "STUDENT") {
            $student_data = tblstudentEnrollmentModel::select('*')
                ->where(['student_id' => $user_id, 'syear' => $syear])->get()->toArray();

            if (count($student_data) > 0) {
                //START to Get Doubt
                $standard_id = $student_data['0']['standard_id'];

                $data['doubt_data'] = doubtModel::select('lms_doubt.*', db::raw('
                    CONCAT_WS(" ",s.first_name,s.middle_name,s.last_name) as student_name,
                    CONCAT_WS("/","student",IFNULL(s.image,"no-image.jpg")) AS image,
                    DATEDIFF(now(),lms_doubt.created_at) as totaldays,
                    CONCAT_WS("/",st.name,d.name) as standard_division,
                    DATE_FORMAT(lms_doubt.created_at,"%M %d, %Y") as doubt_date'))
                    ->leftjoin("tblstudent_enrollment as se", function ($join) {
                        $join->on('se.student_id', '=', 'lms_doubt.user_id')
                            ->on('se.syear', '=', 'lms_doubt.syear');
                    })
                    ->join('tblstudent as s', 's.id', 'se.student_id')
                    ->join('standard as st', 'st.id', 'se.standard_id')
                    ->join('division as d', 'd.id', 'se.section_id')
                    ->where(['lms_doubt.sub_institute_id' => $sub_institute_id, 'lms_doubt.syear' => $syear])
                    ->where(['lms_doubt.visibility' => 'public'])
                    ->orWhere(['se.standard_id' => $standard_id])
                    ->get()->toArray();
                //END to Get Doubt

                foreach ($data['doubt_data'] as $key => $val) {
                    //START to Get Doubt Conversation
                    // $arr = doubtConversationModel::select('*',db::raw('
                    //     CONCAT_WS(" ",s.first_name,s.middle_name,s.last_name) as student_name,
                    //     CONCAT_WS("/","student",IFNULL(s.image,"no-image.jpg")) AS image,
                    //     DATE_FORMAT(lms_doubt_conversation.created_at,"%M %d, %Y") as comment_date,
                    //     CONCAT("(",CONCAT_WS("/",st.name,d.name),")") as standard_division'))
                    // ->leftjoin('tblstudent_enrollment as se','se.student_id','lms_doubt_conversation.user_id')
                    // ->join('tblstudent as s','s.id','se.student_id')
                    // ->join('standard as st','st.id','se.standard_id')
                    // ->join('division as d','d.id','se.section_id')
                    // ->where(['lms_doubt_conversation.sub_institute_id'=>$sub_institute_id,'lms_doubt_conversation.doubt_id'=>$val['id']])
                    // ->get()->toArray();
                    // $data['doubt_conversation_data'][$val['id']] = $arr;

                    /*$arr = db::select('SELECT l.*, CONCAT_WS(" ",s.first_name,s.middle_name,s.last_name) AS student_name,
                    CONCAT_WS("/","student",IFNULL(s.image,"no-image.jpg")) AS image,
                    DATE_FORMAT(l.created_at,"%M %d, %Y") AS comment_date,
                    CONCAT("(",CONCAT_WS("/",st.name,d.name),")") as standard_division
                    FROM lms_doubt_conversation as l
                    LEFT JOIN tblstudent_enrollment AS se ON se.student_id = l.user_id and se.syear = l.syear
                    INNER JOIN tblstudent AS s ON s.id = se.student_id
                    INNER JOIN standard AS st ON st.id = se.standard_id
                    INNER JOIN division AS d ON d.id = se.section_id
                    WHERE l.sub_institute_id = "'.$sub_institute_id.'" AND l.doubt_id = "'.$val['id'].'"

                    UNION

                    SELECT l.*,CONCAT_WS(" ",u.first_name,u.middle_name,u.last_name) AS student_name,
                    CONCAT_WS("/","user",IFNULL(u.image,"no-image.jpg")) AS image,
                    DATE_FORMAT(l.created_at,"%M %d, %Y") AS comment_date,
                    " " as standard_division
                    FROM lms_doubt_conversation as l
                    INNER JOIN tbluser as u on u.id = l.user_id
                    WHERE l.sub_institute_id = "'.$sub_institute_id.'" AND l.doubt_id = "'.$val['id'].'"
                    ');*/

                    $arr = DB::table('lms_doubt_conversation as l')
                        ->select(
                            'l.*',
                            DB::raw('CONCAT_WS(" ", s.first_name, s.middle_name, s.last_name) AS student_name'),
                            DB::raw('CONCAT_WS("/", "student", IFNULL(s.image, "no-image.jpg")) AS image'),
                            DB::raw('DATE_FORMAT(l.created_at, "%M %d, %Y") AS comment_date'),
                            DB::raw('CONCAT("(", CONCAT_WS("/", st.name, d.name), ")") as standard_division')
                        )
                        ->leftJoin('tblstudent_enrollment as se', function ($join) use ($val, $sub_institute_id) {
                            $join->on('se.student_id', '=', 'l.user_id')
                                ->on('se.syear', '=', 'l.syear')
                                ->where('se.standard_id', '=', 'l.standard_id')
                                ->where('se.sub_institute_id', '=', $sub_institute_id);
                        })
                        ->join('tblstudent as s', 's.id', '=', 'se.student_id')
                        ->join('standard as st', 'st.id', '=', 'se.standard_id')
                        ->join('division as d', 'd.id', '=', 'se.section_id')
                        ->where('l.sub_institute_id', '=', $sub_institute_id)
                        ->where('l.doubt_id', '=', $val['id'])
                        ->unionAll(
                            DB::table('lms_doubt_conversation as l')
                                ->select(
                                    'l.*',
                                    DB::raw('CONCAT_WS(" ", u.first_name, u.middle_name, u.last_name) AS student_name'),
                                    DB::raw('CONCAT_WS("/", "user", IFNULL(u.image, "no-image.jpg")) AS image'),
                                    DB::raw('DATE_FORMAT(l.created_at, "%M %d, %Y") AS comment_date'),
                                    DB::raw('" " as standard_division')
                                )
                                ->join('tbluser as u', 'u.id', '=', 'l.user_id')
                                ->where('l.sub_institute_id', '=', $sub_institute_id)
                                ->where('l.doubt_id', '=', $val['id'])
                        )
                        ->get();


                    $arr = json_decode(json_encode($arr), true);
                    $data['doubt_conversation_data'][$val['id']] = $arr;
                    //END to Get Doubt Conversation
                }
            }
        } else {
            //START to Get Doubt
            $data['doubt_data'] = doubtModel::select('lms_doubt.*', db::raw('
                CONCAT_WS(" ",s.first_name,s.middle_name,s.last_name) as student_name,
                CONCAT_WS("/","student",IFNULL(s.image,"no-image.jpg")) as image,
                DATEDIFF(now(),lms_doubt.created_at) as totaldays,
                CONCAT("(",CONCAT_WS("/",st.name,d.name),")") as standard_division,
                DATE_FORMAT(lms_doubt.created_at,"%M %d, %Y") as doubt_date'))
                ->leftjoin("tblstudent_enrollment as se", function ($join) {
                    $join->on('se.student_id', '=', 'lms_doubt.user_id')
                        ->on('se.syear', '=', 'lms_doubt.syear');
                })
                ->join('tblstudent as s', 's.id', 'se.student_id')
                ->join('standard as st', 'st.id', 'se.standard_id')
                ->join('division as d', 'd.id', 'se.section_id')
                ->where(['lms_doubt.sub_institute_id' => $sub_institute_id, 'lms_doubt.syear' => $syear])
                ->get()->toArray();
            //END to Get Doubt

            foreach ($data['doubt_data'] as $key => $val) {
                //START to Get Doubt Conversation
               /* $arr = db::select('SELECT l.*, CONCAT_WS(" ",s.first_name,s.middle_name,s.last_name) AS student_name,
                    CONCAT_WS("/","student",IFNULL(s.image,"no-image.jpg")) AS image,
                    DATE_FORMAT(l.created_at,"%M %d, %Y") AS comment_date,
                    CONCAT("(",CONCAT_WS("/",st.name,d.name),")") as standard_division
                    FROM lms_doubt_conversation as l
                    LEFT JOIN tblstudent_enrollment AS se ON se.student_id = l.user_id and se.syear = l.syear
                    INNER JOIN tblstudent AS s ON s.id = se.student_id
                    INNER JOIN standard AS st ON st.id = se.standard_id
                    INNER JOIN division AS d ON d.id = se.section_id
                    WHERE l.sub_institute_id = "'.$sub_institute_id.'" AND l.doubt_id = "'.$val['id'].'"

                    UNION

                    SELECT l.*,CONCAT_WS(" ",u.first_name,u.middle_name,u.last_name) AS student_name,
                    CONCAT_WS("/","user",IFNULL(u.image,"no-image.jpg")) AS image,
                    DATE_FORMAT(l.created_at,"%M %d, %Y") AS comment_date,
                    " " as standard_division
                    FROM lms_doubt_conversation as l
                    INNER JOIN tbluser as u on u.id = l.user_id
                    WHERE l.sub_institute_id = "'.$sub_institute_id.'" AND l.doubt_id = "'.$val['id'].'"
                    ');*/
                $arr = DB::table('lms_doubt_conversation as l')
                    ->select(
                        'l.*',
                        DB::raw('CONCAT_WS(" ", s.first_name, s.middle_name, s.last_name) AS student_name'),
                        DB::raw('CONCAT_WS("/", "student", IFNULL(s.image, "no-image.jpg")) AS image'),
                        DB::raw('DATE_FORMAT(l.created_at, "%M %d, %Y") AS comment_date'),
                        DB::raw('CONCAT("(", CONCAT_WS("/", st.name, d.name), ")") as standard_division')
                    )
                    ->leftJoin('tblstudent_enrollment as se', function ($join) use ($val, $sub_institute_id) {
                        $join->on('se.student_id', '=', 'l.user_id')
                            ->where('se.syear', '=', 'l.syear')
                            ->where('se.standard_id', '=', 'l.standard_id')
                            ->where('se.sub_institute_id', '=', $sub_institute_id);
                    })
                    ->join('tblstudent as s', 's.id', '=', 'se.student_id')
                    ->join('standard as st', 'st.id', '=', 'se.standard_id')
                    ->join('division as d', 'd.id', '=', 'se.section_id')
                    ->where('l.sub_institute_id', '=', $sub_institute_id)
                    ->where('l.doubt_id', '=', $val['id'])
                    ->unionAll(
                        DB::table('lms_doubt_conversation as l')
                            ->select(
                                'l.*',
                                DB::raw('CONCAT_WS(" ", u.first_name, u.middle_name, u.last_name) AS student_name'),
                                DB::raw('CONCAT_WS("/", "user", IFNULL(u.image, "no-image.jpg")) AS image'),
                                DB::raw('DATE_FORMAT(l.created_at, "%M %d, %Y") AS comment_date'),
                                DB::raw('" " as standard_division')
                            )
                            ->join('tbluser as u', 'u.id', '=', 'l.user_id')
                            ->where('l.sub_institute_id', '=', $sub_institute_id)
                            ->where('l.doubt_id', '=', $val['id'])
                    )
                    ->get();

                $arr = json_decode(json_encode($arr), true);

                $data['doubt_conversation_data'][$val['id']] = $arr;
                //END to Get Doubt Conversation
            }
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
