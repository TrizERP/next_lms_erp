<?php

namespace App\Http\Controllers\front_desk\syllabus;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use function App\Helpers\is_mobile;

class syllabusController extends Controller
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

        return is_mobile($type, "front_desk/syllabus/show", $school_data, "view");
    }

    function getData()
    {
        $sub_institute_id = session()->get('sub_institute_id');
        $syear = session()->get('syear');
        $user_profile_id = session()->get('user_profile_id');
        $user_profile_name = session()->get('user_profile_name');
        $user_id = session()->get('user_id');
        $marking_period_id = session()->get('term_id');

        if (strtoupper($user_profile_name) == 'TEACHER') {
            $result = DB::table("syllabus as c")
                ->join('standard as s', function ($join) use ($marking_period_id) {
                    $join->whereRaw("s.id = c.standard_id");
                    // ->when($marking_period_id,function($query) use($marking_period_id){
                    //     $query->where('s.marking_period_id',$marking_period_id);
                    // });
                })
                ->join('sub_std_map as su', function ($join) {
                    $join->whereRaw("su.subject_id = c.subject_id AND su.standard_id = c.standard_id");
                })
                ->join('timetable as t', function ($join) {
                    $join->whereRaw("t.standard_id = s.id AND t.subject_id = su.subject_id AND t.sub_institute_id = su.sub_institute_id");
                })
                ->selectRaw('c.*,s.name std_name ,su.display_name')
                ->where("c.syear", "=", $syear)
                ->where("c.sub_institute_id", "=", $sub_institute_id)
                ->where("t.teacher_id", "=", $user_id)
                ->get()->toArray();
        } else {
            $result = DB::table("syllabus as c")
                ->join('standard as s', function ($join) use ($marking_period_id) {
                    $join->whereRaw("s.id = c.standard_id");
                    // ->when($marking_period_id, function ($query) use ($marking_period_id) {
                    //     $query->where('s.marking_period_id', $marking_period_id);
                    // });
                })
                ->join('sub_std_map as su', function ($join) {
                    $join->whereRaw("su.subject_id = c.subject_id AND su.standard_id = c.standard_id");
                })
                ->selectRaw('c.*,s.name std_name ,su.display_name')
                ->where("c.syear", "=", $syear)
                ->where("c.sub_institute_id", "=", $sub_institute_id)
                ->get()->toArray();
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
        if ($request->hasFile('attachment')) {
            $file = $request->file('attachment');
            $originalname = $file->getClientOriginalName();
            $name = $request->get('attachment').date('YmdHis');
            $ext = File::extension($originalname);
            $file_name = "attachment_".$name.'.'.$ext;
            $path = $file->storeAs('public/syllabus/', $file_name);
        }
        $values = [
            'syear'            => session()->get('syear'),
            'standard_id'      => $_REQUEST['standard'],
            'title'            => $_REQUEST['title'],
            'message'          => $_REQUEST['message'],
            'file_name'        => $file_name,
            'date_'            => $_REQUEST['date_'],
            'sub_institute_id' => session()->get('sub_institute_id'),
            'created_at'       => now(),
            'updated_at'       => now(),
            'subject_id'       => $_REQUEST['subject'],
        ];
        DB::table('syllabus')->insert($values);

        $res = [
            "status_code" => 1,
            "message"     => "Done",
        ];

        $type = $request->input('type');

        return is_mobile($type, "syllabus.index", $res, "redirect");
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
        DB::table('syllabus')->where(["Id" => $id])->delete();

        $res = [
            "status_code" => 1,
            "message"     => "Data Deleted",
        ];

        return is_mobile($type, "syllabus.index", $res, "redirect");
    }

}
