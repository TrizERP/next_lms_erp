<?php

namespace App\Http\Controllers\front_desk\exam_schedule;

use App\Http\Controllers\Controller;
use GenTux\Jwt\GetsJwtToken;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use function App\Helpers\is_mobile;

class exam_scheduleController extends Controller
{

    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    use GetsJwtToken;

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

        return is_mobile($type, "front_desk/exam_schedule/show", $school_data, "view");
    }

    function getData()
    {
        $marking_period_id=session()->get('term_id');
        return DB::table("exam_schedule as c")
            ->join('standard as s', function ($join) use($marking_period_id){
                $join->whereRaw("s.id = c.standard_id");
                // ->when($marking_period_id, function ($query) use ($marking_period_id) {
                //     $query->where('s.marking_period_id', $marking_period_id);
                // });
            })
            ->join('division as d', function ($join) {
                $join->whereRaw("d.id = c.division_id");
            })
            ->selectRaw('c.*,s.name std_name, d.name division_name')
            ->where("c.syear", "=", session()->get('syear'))
            ->where("c.sub_institute_id", "=", session()->get('sub_institute_id'))
            ->orderby("c.date_", 'desc')
            ->get()->toArray();
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return void
     */
    public function create()
    {
        //
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
        $file_name = $file_size = $ext = "";
        if ($request->hasFile('attechment')) {
            $file = $request->file('attechment');
            $originalname = $file->getClientOriginalName();
            $file_size = $file->getSize();
            $name = $request->get('attechment').date('YmdHis');
            $ext = File::extension($originalname);
            $file_name = "attechment_".$name.'.'.$ext;
            $path = $file->storeAs('public/exam_schedule/', $file_name);
        }

        if (isset($_REQUEST['standard'])) {
            foreach ($_REQUEST['standard'] as $id => $std) {
                foreach ($_REQUEST['division'] as $ids => $div_id) {
                    $values = [
                        'syear'            => session()->get('syear'),
                        'standard_id'      => $std,
                        'title'            => $_REQUEST['title'],
                        'division_id'      => $div_id,
                        'file_name'        => $file_name,
                        'file_size'        => $file_size,
                        'file_type'        => $ext,
                        'date_'            => $_REQUEST['date_'],
                        'sub_institute_id' => session()->get('sub_institute_id'),
                        'created_at'       => now(),
                        'updated_at'       => now(),
                    ];
                    DB::table('exam_schedule')->insert($values);
                }
            }
        }

        $res = [
            "status_code" => 1,
            "message"     => "Done",
        ];

        $type = $request->input('type');

        return is_mobile($type, "exam_schedule.index", $res, "redirect");
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

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return void
     */
    public function destroy(Request $request,$id)
    {
        $type = $request->input('type');

        $delete = DB::table('exam_schedule')->where('id',$id)->delete();
        if($delete){
            $res = [
                "status_code" => 1,
                "message"     => "Deleted Successfully!!",
            ];
        }else{
            $res = [
                "status_code" => 0,
                "message"     => "Failed to Delete!!",
            ];
        }

        return is_mobile($type, "exam_schedule.index", $res, "redirect");
    }

    public function studentExamScheduleAPI(Request $request)
    {
        try {
            if (! $this->jwtToken()->validate()) {
                $response = ['status' => '2', 'message' => 'Token Auth Failed', 'data' => []];

                return response()->json($response, 401);
            }
        } catch (\Exception $e) {
            $response = ['status' => '2', 'message' => $e->getMessage(), 'data' => []];

            return response()->json($response, 401);
        }

        $type = $request->input("type");
        $student_id = $request->input("student_id");
        $sub_institute_id = $request->input("sub_institute_id");
        $syear = $request->input("syear");

        if ($student_id != "" && $sub_institute_id != "" && $syear != "") {
            $data = DB::table('exam_schedule as e')
                ->join('tblstudent_enrollment as s', function ($join) {
                    $join->whereRaw('(s.standard_id = e.standard_id AND s.section_id = e.division_id 
                        AND s.sub_institute_id = e.sub_institute_id AND s.syear = e.syear)');
                })->selectRaw("title,date_, if(file_name = '','',concat('https://".$_SERVER['SERVER_NAME']."/storage/exam_schedule/',
                    file_name)) as file_name")
                ->where('e.syear', $syear)
                ->where('e.sub_institute_id', $sub_institute_id)
                ->where('student_id', $student_id)
                ->orderby("e.date_", 'desc')
                ->get()->toArray();

            $res['status'] = 1;
            $res['message'] = "Success";
            $res['data'] = $data;
        } else {
            $res['status'] = 0;
            $res['message'] = "Parameter Missing";
        }

        return json_encode($res);
    }

}
