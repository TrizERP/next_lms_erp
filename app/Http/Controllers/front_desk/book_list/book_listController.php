<?php

namespace App\Http\Controllers\front_desk\book_list;

use App\Http\Controllers\Controller;
use App\Models\lms\chapterModel;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use function App\Helpers\is_mobile;

class book_listController extends Controller
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

        $school_data['data'] = $this->getData();

        $type = $request->input('type');

        return is_mobile($type, "front_desk/book_list/show", $school_data, "view");
    }

    function getData()
    {

        $sub_institute_id = session()->get('sub_institute_id');
        $syear = session()->get('syear');
        $user_profile_id = session()->get('user_profile_id');
        $user_profile_name = session()->get('user_profile_name');
        $user_id = session()->get('user_id');

        if (strtoupper($user_profile_name) == 'TEACHER') {

            /*$result = DB::table("book_list as c")
                ->leftJoin('chapter_master as cm', function ($join) {
                    $join->whereRaw("cm.id = c.chapter_id AND cm.sub_institute_id = c.sub_institute_id");
                })
                ->leftJoin('topic_master as tm', function ($join) {
                    $join->whereRaw("tm.id = c.topic_id AND tm.chapter_id = c.chapter_id AND tm.sub_institute_id = c.sub_institute_id");
                })
                ->join('standard as s', function ($join) {
                    $join->whereRaw("s.id = c.standard_id");
                })
                ->join('sub_std_map as su', function ($join) {
                    $join->whereRaw("su.subject_id = c.subject_id AND su.standard_id = c.standard_id");
                })
                ->join('timetable as t', function ($join) {
                    $join->whereRaw("t.standard_id = s.id AND t.subject_id = su.subject_id AND t.sub_institute_id = su.sub_institute_id");
                })
                ->selectRaw("c.*,s.name std_name ,
                    if(file_name = '','',concat('http://".$_SERVER['SERVER_NAME']."/storage/book_list/',file_name)) as file_name_path,
                    cm.chapter_name,tm.name AS topic_name,su.display_name AS subject_name")
                ->where("c.syear", "=", $syear)
                ->where("c.sub_institute_id", "=", $sub_institute_id)
                ->where("t.teacher_id", "=", $user_id)
                ->get()->toarray();*/

            $result = DB::table("book_list as c")
                ->leftJoin('chapter_master as cm', function ($join) {
                    $join->on('cm.id', '=', 'c.chapter_id')
                        ->where('cm.sub_institute_id', '=', 'c.sub_institute_id');
                })
                ->leftJoin('topic_master as tm', function ($join) {
                    $join->on('tm.id', '=', 'c.topic_id')
                        ->where('tm.chapter_id', '=', 'c.chapter_id')
                        ->where('tm.sub_institute_id', '=', 'c.sub_institute_id');
                })
                ->join('standard as s', function ($join) {
                    $join->on('s.id', '=', 'c.standard_id');
                })
                ->join('sub_std_map as su', function ($join) {
                    $join->on('su.subject_id', '=', 'c.subject_id')
                        ->on('su.standard_id', '=', 'c.standard_id');
                })
                ->join('timetable as t', function ($join) {
                    $join->on('t.standard_id', '=', 's.id')
                        ->on('t.subject_id', '=', 'su.subject_id')
                        ->on('t.sub_institute_id', '=', 'su.sub_institute_id');
                })
                ->selectRaw("c.*, s.name as std_name, IF(file_name = '', '', CONCAT('http://".$_SERVER['SERVER_NAME']."/storage/book_list/', file_name)) as file_name_path,
                cm.chapter_name, tm.name AS topic_name, su.display_name AS subject_name")
                ->where("c.syear", "=", $syear)
                ->where("c.sub_institute_id", "=", $sub_institute_id)
                ->where("t.teacher_id", "=", $user_id)
                ->get()->toArray();
        } else {
            $result = DB::table("book_list as c")
                ->leftJoin('chapter_master as cm', function ($join) {
                    $join->whereRaw("cm.id = c.chapter_id AND cm.sub_institute_id = c.sub_institute_id");
                })
                ->leftJoin('topic_master as tm', function ($join) {
                    $join->whereRaw("tm.id = c.topic_id AND tm.chapter_id = c.chapter_id AND tm.sub_institute_id = c.sub_institute_id");
                })
                ->join('standard as s', function ($join) {
                    $join->whereRaw("s.id = c.standard_id");
                })
                ->join('sub_std_map as su', function ($join) {
                    $join->whereRaw("su.subject_id = c.subject_id AND su.standard_id = c.standard_id");
                })
                ->selectRaw("c.*,s.name std_name ,
                if(file_name = '','',concat('http://".$_SERVER['SERVER_NAME']."/storage/book_list/',file_name)) as file_name_path,
                cm.chapter_name,tm.name AS topic_name,su.display_name AS subject_name")
                ->where("c.syear", "=", $syear)
                ->where("c.sub_institute_id", "=", $sub_institute_id)
                ->get()->toarray();
        }


        return $result;
    }


    /**
     * Show the form for creating a new resource.
     *
     * @return void
     */
    public function create()
    {
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

        $file_name = "";
        if ($request->hasFile('attechment')) {
            $file = $request->file('attechment');
            $originalname = $file->getClientOriginalName();
            $name = $request->get('attechment').date('YmdHis');
            $ext = File::extension($originalname);
            $file_name = "attechment_".$name.'.'.$ext;
            $path = $file->storeAs('public/book_list/', $file_name);
        }
        $values = [
            'syear'            => session()->get('syear'),
            'standard_id'      => $_REQUEST['standard'],
            'title'            => $_REQUEST['title'],
            'message'          => $_REQUEST['message'],
            'file_name'        => $file_name,
            'link'             => $_REQUEST['link'],
            'date_'            => $_REQUEST['date_'],
            'sub_institute_id' => session()->get('sub_institute_id'),
            'created_at'       => now(),
            'updated_at'       => now(),
            'subject_id'       => $_REQUEST['subject'],
            'chapter_id'       => $_REQUEST['chapter'],
            'topic_id'         => $_REQUEST['topic'],
        ];
        DB::table('book_list')->insert($values);

        $res = [
            "status_code" => 1,
            "message"     => "Book List Added Successfully",
        ];
        $type = $request->input('type');

        return is_mobile($type, "book_list.index", $res, "redirect");
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

    public function destroy(Request $request, $id)
    {
        $type = $request->input('type');
        DB::table('book_list')->where(["Id" => $id])->delete();

        $res = [
            "status_code" => 1,
            "message"     => "Data Deleted",
        ];

        return is_mobile($type, "book_list.index", $res, "redirect");
    }

    public function ajax_LMS_SubjectwiseChapterForBooklist(Request $request)
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

}
