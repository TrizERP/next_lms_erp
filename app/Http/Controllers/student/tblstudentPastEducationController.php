<?php

namespace App\Http\Controllers\student;

use App\Http\Controllers\Controller;
use App\Models\student\tblstudentPastEducationModel;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use function App\Helpers\is_mobile;

class tblstudentPastEducationController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return void
     */
    public function index()
    {
        //
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
     * @return Response
     */
    public function store(Request $request)
    {
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $term_id = $request->session()->get('term_id');
        $syear = $request->session()->get('syear');
        $type = $request->input('type');
        $courses = $request['courses'];
        $mediums = $request['mediums'];
        $name_of_boards = $request['name_of_boards'];
        $year_of_passings = $request['year_of_passings'];
        $percentages = $request['percentages'];
        $school_names = $request['school_names'];
        $places = $request['places'];
        $trials = $request['trials'];
        tblstudentPastEducationModel::where([
            "student_id"       => $request->input('student_id'),
            "sub_institute_id" => $sub_institute_id,
        ])->delete();
        $request->request->remove('courses');
        $request->request->remove('mediums');
        $request->request->remove('name_of_boards');
        $request->request->remove('year_of_passings');
        $request->request->remove('percentages');
        $request->request->remove('school_names');
        $request->request->remove('places');
        $request->request->remove('trials');
        foreach ($courses as $key => $value) {
            if ($value == '') {
                break;
            }
            $request->request->set('course', $value);
            $request->request->set('medium', $mediums[$key]);
            $request->request->set('name_of_board', $name_of_boards[$key]);
            $request->request->set('year_of_passing', $year_of_passings[$key]);
            $request->request->set('percentage', $percentages[$key]);
            $request->request->set('school_name', $school_names[$key]);
            $request->request->set('place', $places[$key]);
            $request->request->set('trial', $trials[$key]);
            $data = $this->saveData($request);
        }

        $res['status_code'] = 1;
        $res['message'] = "Student Past Education successfully created.";
        $res['data'] = $data;

        return is_mobile($type, "search_student.index", $res);
    }


    public function saveData(Request $request)
    {
        $newRequest = $request->post();
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $finalArray['sub_institute_id'] = $sub_institute_id;

        foreach ($newRequest as $key => $value) {
            if ($key != '_method' && $key != '_token' && $key != 'submit') {
                if (is_array($value)) {
                    $value = implode(",", $value);
                }
                $finalArray[$key] = $value;
            }
        }

        tblstudentPastEducationModel::insert($finalArray);

        return DB::getPdo()->lastInsertId();
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
    public function destroy($id)
    {
        //
    }
}
