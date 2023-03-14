<?php

namespace App\Http\Controllers\student;

use App\Http\Controllers\Controller;
use App\Models\student\tblstudentFamilyHistoryModel;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use function App\Helpers\is_mobile;

class tblstudentFamilyHistoryController extends Controller
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
        $names = $request['names'];
        $institute_names = $request['institute_names'];
        $courses = $request['courses'];
        $years = $request['years'];
        $percentages = $request['percentages'];
        $relation_with_students = $request['relation_with_students'];

        tblstudentFamilyHistoryModel::where(["student_id"       => $request->input('student_id'),
                                             "sub_institute_id" => $sub_institute_id,
        ])->delete();
        $request->request->remove('courses');
        $request->request->remove('names');
        $request->request->remove('institute_names');
        $request->request->remove('percentages');
        $request->request->remove('years');
        $request->request->remove('relation_with_students');

        foreach ($names as $key => $value) {
            if ($value == '') {
                break;
            }
            $request->request->set('name', $value);
            $request->request->set('course', $courses[$key]);
            $request->request->set('institute_name', $institute_names[$key]);
            $request->request->set('year', $years[$key]);
            $request->request->set('percentage', $percentages[$key]);
            $request->request->set('relation_with_student', $relation_with_students[$key]);
            $data = $this->saveData($request);
        }
        
        $res['status_code'] = 1;
        $res['message'] = "Student Family History Successfully Updated.";
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

        tblstudentFamilyHistoryModel::insert($finalArray);

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
