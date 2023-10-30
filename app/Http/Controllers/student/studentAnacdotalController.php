<?php

namespace App\Http\Controllers\student;

use App\Http\Controllers\Controller;
use App\Models\student\tblstudentParentFeedbackModel;
use App\Models\student\Anacdotal;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use function App\Helpers\is_mobile;

class studentAnacdotalController extends Controller
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
        $observer_name = $request->session()->get('user_profile_name');
        $type = $request->input('type');
        $place = $request['place'];
        $date = $request['date'];
        $time = $request['time'];
        $student_id = $request['student_id'];
        $observation = $request['observation'];
        $life_skills = $request['life_skills'];
        $life_values = $request['life_values'];

        // dd($observation, $life_skills, $life_values, $sub_institute_id, $syear, $place, $date, $time, $student_id);

        Anacdotal::where(["student_id" => $student_id, "sub_institute_id" => $sub_institute_id,
        ])->delete();
        $request->request->remove('place');
        $request->request->remove('date');
        $request->request->remove('time');
        $request->request->remove('observation');
        $request->request->remove('life_skills');
        $request->request->remove('life_values');

        foreach ($place as $key => $value) 
        {
            if ($value == '') {
                break;
            }
            $request->request->set('place', $value);
            $request->request->set('date', $date[$key]);
            $request->request->set('time', $time[$key]);
            $request->request->set('observation', $observation[$key]);
            $request->request->set('life_skills', $life_skills[$key]);
            $request->request->set('life_values', $life_values[$key]);

            $data = $this->saveData($request);
        }

        $res['status_code'] = 1;
        $res['message'] = "Student Anacdotal Successfully Updated.";
        $res['data'] = $data;

        return is_mobile($type, "search_student.index", $res);
    }

    public function saveData(Request $request)
    {
        $newRequest = $request->post();
  
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $syear = $request->session()->get('syear');
        $student_id = $request['student_id'];
        $observer_name = $request->session()->get('user_profile_name');

        $finalArray['sub_institute_id'] = $sub_institute_id;
        $finalArray['syear'] = $syear;
        $finalArray['student_id'] = $student_id;
        $finalArray['observer_name'] = $observer_name;

        foreach($newRequest as $key => $value){
            if($key != '_method' && $key != '_token' && $key != 'submit'){
                if(is_array($value)){
                    $value = implode(",",$value);
                }
                $finalArray[$key] = $value;
            }
        }
        
        Anacdotal::insert($finalArray);

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
