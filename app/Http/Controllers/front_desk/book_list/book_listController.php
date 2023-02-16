<?php

namespace App\Http\Controllers\front_desk\book_list;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\lms\chapterModel;
use File;
use DB;

class book_listController extends Controller {

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request) {
        if (session()->has('data')) { // check if it exists
            $data_arr = session('data'); // to retrieve value
            if (isset($data_arr['message'])) {
                $school_data['message'] = $data_arr['message'];
            }
        }

        $school_data['data'] = $this->getData();
        // dd($school_data);
//        $school_data['data'] = array();
        $type = $request->input('type');
        return \App\Helpers\is_mobile($type, "front_desk/book_list/show", $school_data, "view");
    }

    function getData()
    {
        $sub_institute_id = session()->get('sub_institute_id');
        $syear = session()->get('syear');
        $user_profile_id = session()->get('user_profile_id');
        $user_profile_name = session()->get('user_profile_name');
        $user_id = session()->get('user_id');

        if(strtoupper($user_profile_name) == 'TEACHER')
        {
            $sql = "SELECT c.*,s.name std_name ,
                if(file_name = '','',concat('http://".$_SERVER['SERVER_NAME']."/storage/book_list/',file_name)) as file_name_path,
                cm.chapter_name,tm.name AS topic_name,su.display_name AS subject_name
                FROM book_list c
                LEFT JOIN chapter_master cm ON cm.id = c.chapter_id AND cm.sub_institute_id = c.sub_institute_id
                LEFT JOIN topic_master tm ON tm.id = c.topic_id AND tm.chapter_id = c.chapter_id AND tm.sub_institute_id = c.sub_institute_id
                INNER JOIN standard s on s.id = c.standard_id
                INNER JOIN sub_std_map su on su.subject_id = c.subject_id AND su.standard_id = c.standard_id
                INNER JOIN timetable t ON t.standard_id = s.id AND t.subject_id = su.subject_id AND t.sub_institute_id = su.sub_institute_id
                WHERE c.syear = '".$syear."' AND c.sub_institute_id = '".$sub_institute_id."' AND t.teacher_id = '".$user_id."' "; 
        }else{
            $sql = "SELECT c.*,s.name std_name ,
                if(file_name = '','',concat('http://".$_SERVER['SERVER_NAME']."/storage/book_list/',file_name)) as file_name_path,
                cm.chapter_name,tm.name AS topic_name,su.display_name AS subject_name
                FROM book_list c
                LEFT JOIN chapter_master cm ON cm.id = c.chapter_id AND cm.sub_institute_id = c.sub_institute_id
                LEFT JOIN topic_master tm ON tm.id = c.topic_id AND tm.chapter_id = c.chapter_id AND tm.sub_institute_id = c.sub_institute_id
                INNER JOIN standard s on s.id = c.standard_id
                INNER JOIN sub_std_map su on su.subject_id = c.subject_id AND su.standard_id = c.standard_id
                WHERE c.syear = '".$syear."' AND c.sub_institute_id = '".$sub_institute_id."' ";    
        }
        
        $sql = preg_replace('/\n+/', '', $sql);

        $result = DB::select($sql);
        return $result;
    }
    

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create() {
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request) {

        $file_name = "";
        if ($request->hasFile('attechment')) {
            $file = $request->file('attechment');
            $originalname = $file->getClientOriginalName();
            $name = $request->get('attechment') . date('YmdHis');
            $ext = \File::extension($originalname);
            $file_name = "attechment_" . $name . '.' . $ext;
            $path = $file->storeAs('public/book_list/', $file_name);
        }
        // if (isset($_REQUEST['standard'])) {
        //     foreach ($_REQUEST['standard'] as $id => $std) {
                $values = array(
                    'syear' => session()->get('syear'),
                    //'standard_id' => $std,
                    'standard_id' => $_REQUEST['standard'],
                    'title' => $_REQUEST['title'],
                    'message' => $_REQUEST['message'],
                    'file_name' => $file_name,
                    'link' => $_REQUEST['link'],
                    'date_' => $_REQUEST['date_'],
                    'sub_institute_id' => session()->get('sub_institute_id'),
                    'created_at' => now(),
                    'updated_at' => now(),
                     'subject_id' => $_REQUEST['subject'],
                     'chapter_id' => $_REQUEST['chapter'],
                     'topic_id' => $_REQUEST['topic'],
                );
                DB::table('book_list')->insert($values);
        //     }
        // }

        $res = array(
            "status_code" => 1,
            "message" => "Book List Added Successfully",
        );
        $type = $request->input('type');
        return \App\Helpers\is_mobile($type, "book_list.index", $res, "redirect");
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
    public function edit($id) {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id) {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
//    public function destroy($id) {
//        //
//    }

    public function destroy(Request $request, $id) {
        $type = $request->input('type');
        DB::table('book_list')->where(["Id" => $id])->delete();
//        ExamMaster::where(["Id" => $id])->delete();
        $res = array(
            "status_code" => 1,
            "message" => "Data Deleted",
        );

        return \App\Helpers\is_mobile($type, "book_list.index", $res, "redirect");
    }

    public function ajax_LMS_SubjectwiseChapterForBooklist(Request $request)
    {
        $sub_id = $request->input("sub_id");        
        $std_id = $request->input("std_id");        
        $sub_institute_id = $request->session()->get("sub_institute_id");
        
        $data = chapterModel::where(
            [
            'chapter_master.sub_institute_id' => $sub_institute_id,            
            'chapter_master.subject_id' => $sub_id,
            'chapter_master.standard_id' => $std_id
            ]
            )          
        ->get()->toArray();       
        
        return $data; 
    }

}
