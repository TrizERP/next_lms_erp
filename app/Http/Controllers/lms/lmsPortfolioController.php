<?php

namespace App\Http\Controllers\lms;

use App\Http\Controllers\Controller;
use App\Models\lms\chapterModel;
use App\Models\lms\portfolioModel;
use App\Models\lms\topicModel;
use GenTux\Jwt\GetsJwtToken;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use function App\Helpers\is_mobile;


class lmsPortfolioController extends Controller
{
    use GetsJwtToken;

    /**
     * Display a listing of the resource.
     *
     * @param  Request  $request
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @return Response
     */
    public function index(Request $request)
    {
        $type = $request->input('type');
        $data = $this->getData($request);
        $res['status_code'] = 1;
        $res['message'] = "SUCCESS";
        $res['data'] = $data['portfolio_data'];

        if (strtoupper(session()->get('user_profile_name')) == "STUDENT") {
            return is_mobile($type, 'lms/show_student_lmsPortfolio', $res, "view");
        } else {
            if (strtoupper(session()->get('user_profile_name')) == "LMS TEACHER" || strtoupper(session()->get('user_profile_name')) == "TEACHER") {
                return is_mobile($type, 'lms/show_all_lmsPortfolio', $res, "view");
            }
        }

    }

    public function getData($request)
    {

        $sub_institute_id = $request->session()->get('sub_institute_id');
        $syear = $request->session()->get('syear');
        $user_id = $request->session()->get('user_id');
        $user_profile_id = $request->session()->get('user_profile_id');

        $data['portfolio_data'] = [];

        if (strtoupper(session()->get('user_profile_name')) == "STUDENT") {
            $data['portfolio_data'] = portfolioModel::select('lms_portfolio.*', DB::raw('date_format(created_at,"%d-%m-%Y") as created_at,
                CONCAT_WS(" ",u.first_name,u.middle_name,u.last_name) as teacher_name'))
                ->leftjoin("tbluser as u", function ($join) {
                    $join->on("u.id", "=", "lms_portfolio.feedback_by")
                        ->on("u.sub_institute_id", "=", "lms_portfolio.sub_institute_id");
                })
                ->where([
                    'lms_portfolio.sub_institute_id' => $sub_institute_id, 'lms_portfolio.user_id' => $user_id,
                    'lms_portfolio.user_profile_id'  => $user_profile_id, 'lms_portfolio.syear' => $syear,
                ])
                ->get()->toArray();
        } else {
            if (strtoupper(session()->get('user_profile_name')) == "LMS TEACHER" || strtoupper(session()->get('user_profile_name')) == " TEACHER") {
                $data['portfolio_data'] = portfolioModel::select('lms_portfolio.*',
                    DB::raw('date_format(lms_portfolio.created_at,"%d-%m-%Y") AS created_at,
                    CONCAT_WS(" ",s.first_name,s.middle_name,s.last_name) AS student_name,se.standard_id,
                    st.name AS standard_name'))
                    ->join("tblstudent as s", function ($join) {
                        $join->on("s.id", "=", "lms_portfolio.user_id")
                            ->on("s.sub_institute_id", "=", "lms_portfolio.sub_institute_id");
                    })
                    ->join("tblstudent_enrollment as se", function ($join1) {
                        $join1->on("se.student_id", "=", "lms_portfolio.user_id")
                            ->on("se.sub_institute_id", "=", "lms_portfolio.sub_institute_id");
                    })
                    ->join("standard as st", function ($join2) {
                        $join2->on("st.id", "=", "se.standard_id");
                    })
                    ->where(['lms_portfolio.sub_institute_id' => $sub_institute_id, 'se.syear' => $syear])
                    ->get()->toArray();
            }
        }

        return $data;
    }


    /**
     * Show the form for creating a new resource.
     *
     * @return Response
     */
    public function create(Request $request)
    {
        $type = $request->input('type');
        $action = $request->get('action');
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $syear = $request->session()->get('syear');

        if (strtoupper(session()->get('user_profile_name')) == "STUDENT") {
            $student_id = session()->get('user_id');

            /*$res = DB::select("SELECT * FROM sub_std_map s WHERE s.standard_id =
            (
                SELECT standard_id FROM tblstudent_enrollment WHERE student_id = '".$student_id."' AND
                sub_institute_id = '".$sub_institute_id."' AND syear = '".$syear."'
            )
            and sub_institute_id = '".$sub_institute_id."'");*/

            $res = DB::table('sub_std_map as s')
                ->where('s.standard_id', function ($query) use ($student_id, $sub_institute_id, $syear) {
                    $query->select('standard_id')
                        ->from('tblstudent_enrollment')
                        ->where('student_id', $student_id)
                        ->where('sub_institute_id', $sub_institute_id)
                        ->where('syear', $syear);
                })
                ->where('s.sub_institute_id', $sub_institute_id)
                ->get();


            $subject_arr = json_decode(json_encode($res), true);
            $data['subject_arr'] = $subject_arr;
        }

        $data['action'] = $action;

        return is_mobile($type, 'lms/add_portfolio', $data, "view");
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
        $user_profile_id = $request->session()->get('user_profile_id');
        $user_id = $request->session()->get('user_id');

        $newfilename = "";
        if ($request->hasFile('filename')) {
            $img = $request->file('filename');
            $filename = $img->getClientOriginalName();
            $ext = $img->getClientOriginalExtension();
            $size = $img->getSize();
            $newfilename = 'lms_'.date('Y-m-d_h-i-s').'.'.$ext;
            //$img->move(public_path().'/lms_content_file/',$newfilename);
            $img->storeAs('public/lms_portfolio/', $newfilename);
        }

        $content = [
            'title'            => $request->get('title'),
            'description'      => $request->get('description'),
            'type'             => $request->get('type'),
            'file_name'        => $newfilename,
            'user_id'          => $user_id,
            'user_profile_id'  => $user_profile_id,
            'sub_institute_id' => $sub_institute_id,
            'syear'            => $syear,
        ];

        portfolioModel::insert($content);

        $res = array(
            "status_code" => 1,
            "message"     => "Portfolio Added Successfully",
        );
        $type = $request->input('type');

        return is_mobile($type, "lmsPortfolio.index", $res, "redirect");
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return Response
     */
    public function show(Request $request, $id)
    {
        $type = $request->input('type');

        $sub_institute_id = $request->session()->get('sub_institute_id');
        $user_id = $request->session()->get('user_id');
        $user_profile_id = $request->session()->get('user_profile_id');


        $data['portfolio_data'] = portfolioModel::select('*',
            db::raw('date_format(created_at,"%d-%m-%Y") as created_at'))
            ->where([
                'sub_institute_id' => $sub_institute_id, 'user_id' => $user_id, 'user_profile_id' => $user_profile_id,
            ])
            ->take(10)
            ->get()->toArray();

        return is_mobile($type, "lms/view_portfolio", $data, "view");
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return Response
     */
    public function edit(Request $request, $id)
    {
        $type = $request->input('type');
        $sub_institute_id = $request->session()->get('sub_institute_id');

        $data['portfolio_data'] = portfolioModel::find($id)->toArray();
        $data['action'] = $data['portfolio_data']['type'];

        return is_mobile($type, "lms/add_portfolio", $data, "view");
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
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $syear = $request->session()->get('syear');
        $user_id = $request->session()->get('user_id');
        $user_profile_id = $request->session()->get('user_profile_id');

        $image_data = array();
        if ($request->hasFile('filename')) {
            if ($request->has('hid_filename')) {
                unlink('storage'.$request->input('hid_filename'));
            }
            $img = $request->file('filename');
            $filename = $img->getClientOriginalName();
            $ext = $img->getClientOriginalExtension();
            $size = $img->getSize();
            $newfilename = 'lms_'.date('Y-m-d_h-i-s').'.'.$ext;
            //$img->move(public_path().'/lms_content_file/',$newfilename);
            $img->storeAs('public/lms_portfolio/', $newfilename);

            $image_data = [
                'file_name' => $newfilename,
            ];
        }

        $data = [
            'title'            => $request->get('title'),
            'description'      => $request->get('description'),
            'user_id'          => $user_id,
            'user_profile_id'  => $user_profile_id,
            'sub_institute_id' => $sub_institute_id,
            'syear'            => $syear,
        ];

        $data = array_merge($data, $image_data);

        portfolioModel::where(["id" => $id])->update($data);

        $res = [
            "status_code" => 1,
            "message"     => "Portfolio Updated Successfully",
        ];
        $type = $request->input('type');

        return is_mobile($type, "lmsPortfolio.index", $res, "redirect");

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

        portfolioModel::where(["id" => $id])->delete();
        $res['status_code'] = "1";
        $res['message'] = "Portfolio Deleted Successfully";

        return is_mobile($type, "lmsPortfolio.index", $res);

    }

    public function ajax_LMS_SubjectwiseChapter(Request $request)
    {
        $sub_id = $request->input("sub_id");
        $std_id = $request->input("std_id");
        $sub_institute_id = $request->session()->get("sub_institute_id");

        return chapterModel::where([
            'chapter_master.sub_institute_id' => $sub_institute_id,
            'chapter_master.subject_id'       => $sub_id,
            'chapter_master.standard_id'      => $std_id,
        ])->get()->toArray();
    }

    public function ajax_LMS_ChapterwiseTopic(Request $request)
    {
        $chapter_id = $request->input("chapter_id");
        $chapter_ids = explode(",", $chapter_id);
        $sub_institute_id = $request->session()->get("sub_institute_id");

        return topicModel::whereIn("topic_master.chapter_id", $chapter_ids)
            ->where(['topic_master.sub_institute_id' => $sub_institute_id])
            ->get()->toArray();
    }

    public function ajax_lmsPortfolio_feedback(Request $request)
    {
        $user_id = $request->session()->get("user_id");
        foreach ($request->get('feedback') as $key => $val) {
            $data = [
                'feedback'    => $val,
                'feedback_by' => $user_id,
            ];
            portfolioModel::where(["id" => $key])->update($data);
        }

        $res = [
            "status_code" => 1,
            "message"     => "Portfolio Updated Successfully",
        ];
        $type = $request->input('type');

        return is_mobile($type, "lmsPortfolio.index", $res, "redirect");
    }

}
