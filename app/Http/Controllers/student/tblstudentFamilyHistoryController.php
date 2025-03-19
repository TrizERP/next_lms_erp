<?php

namespace App\Http\Controllers\student;

use App\Http\Controllers\Controller;
use App\Models\student\tblstudentFamilyHistoryModel;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use function App\Helpers\is_mobile;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Session;
use Carbon\Carbon;

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
    public function store(Request $request){
        // echo "<pre>";print_r($request->all());exit;
        $type = $request->input('type');
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $syear = $request->session()->get('syear');
        $user_id = $request->session()->get('user_id');
        $insertNames = $request->insert['name'] ?? [];
        $updateNames = $request->update['name'] ?? [];

        if(in_array($type,['API','JSON'])){

            $sub_institute_id = $request->input('sub_institute_id');
            $syear = $request->input('syear');
            $user_id = $request->input('user_id');

            $validator = Validator::make($request->all(), [
                'sub_institute_id'  => 'required|numeric',
                'syear'   => 'required|numeric',
                'user_id'   => 'required|numeric',
            ]);
            
            if ($validator->fails()) {
                $res['status'] = '0';
                $res['response'] = $validator->messages()->first();
		        return is_mobile($type, "student/edit_student", $res, "view");
            } 
        }
        $i = 0;
        // insert into table
        if(!empty($insertNames)){
            foreach ($insertNames as $key => $name) {
                if($name!=''){
                    $insertrelation = isset($request->insert['relation_with_students'][$key]) ? $request->insert['relation_with_students'][$key] : null;
                    $insertDob = isset($request->insert['dob'][$key]) ? $request->insert['dob'][$key] : null;
                    $insertDob = $insertDob ? Carbon::createFromFormat('d-m-Y', $insertDob)->format('Y-m-d') : null;
                    $insertBloodgroup = isset($request->insert['bloodgroup'][$key]) ? $request->insert['bloodgroup'][$key] : null;
                    $insertInstitute = isset($request->insert['institute_name'][$key]) ? $request->insert['institute_name'][$key] : null;
                    $insertCourse = isset($request->insert['courses'][$key]) ? $request->insert['courses'][$key] : null;
                    $insertPercentage = isset($request->insert['percentage'][$key]) ? $request->insert['percentage'][$key] : null;
                    $insertDesignation = isset($request->insert['designation'][$key]) ? $request->insert['designation'][$key] : null;
                    $insertCompany = isset($request->insert['company_name'][$key]) ? $request->insert['company_name'][$key] : null;
                    $insertOffice = isset($request->insert['office_address'][$key]) ? $request->insert['office_address'][$key] : null;
                    $insertIncome = isset($request->insert['annual_income'][$key]) ? $request->insert['annual_income'][$key] : null;
                    $insertMobile = isset($request->insert['mobile_no'][$key]) ? $request->insert['mobile_no'][$key] : null;
                    $insertGuardian = isset($request->insert['local_guardian'][$key]) ? $request->insert['local_guardian'][$key] : null;
                    $insertYear = isset($request->insert['year']) ? $request->insert['year'] : null;
                    $i++;
                    tblstudentFamilyHistoryModel::insert([
                        'student_id'=>$request->student_id,
                        'name'=>$name,
                        'institute_name'=>$insertInstitute,
                        'course'=>$insertCourse,
                        'year'=>$insertYear,
                        'percentage'=>$insertPercentage,
                        'relation_with_student'=>$insertrelation,
                        'annual_income'=>$insertIncome,
                        'birthdate'=>$insertDob,
                        'blood_group'=>$insertBloodgroup,
                        'designation'=>$insertDesignation, 
                        'company_name'=>$insertCompany,
                        'office_address'=>$insertOffice, 
                        'mobile_no'=>$insertMobile,
                        'guardian_address'=>$insertGuardian, 
                        'sub_institute_id'=>$sub_institute_id, 
                        'created_by'=>$user_id, 
                        'created_on'=>now(), 
                    ]);
        // echo "<pre>";print_r($insertDob);

                }
            }
        }
        // exit;
        // update into table
        if(!empty($updateNames)){
            foreach ($updateNames as $id => $name) {
                if($name!=''){
                    $updaterelation = isset($request->update['relation_with_students'][$id]) ? $request->update['relation_with_students'][$id] : null;
                    $updateDob = isset($request->update['dob'][$id]) ? $request->update['dob'][$id] : null;
                    $updateDob = $updateDob ? Carbon::createFromFormat('d-m-Y', $updateDob)->format('Y-m-d') : null;
                    $updateBloodgroup = isset($request->update['bloodgroup'][$id]) ? $request->update['bloodgroup'][$id] : null;
                    $updateInstitute = isset($request->update['institute_name'][$id]) ? $request->update['institute_name'][$id] : null;
                    $updateCourse = isset($request->update['courses'][$id]) ? $request->update['courses'][$id] : null;
                    $updateYear = isset($request->update['year'][$id]) ? $request->update['year'][$id] : null;
                    $updatePercentage = isset($request->update['percentage'][$id]) ? $request->update['percentage'][$id] : null;
                    $updateDesignation = isset($request->update['designation'][$id]) ? $request->update['designation'][$id] : null;
                    $updateCompany = isset($request->update['company_name'][$id]) ? $request->update['company_name'][$id] : null;
                    $updateOffice = isset($request->update['office_address'][$id]) ? $request->update['office_address'][$id] : null;
                    $updateIncome = isset($request->update['annual_income'][$id]) ? $request->update['annual_income'][$id] : null;
                    $updateMobile = isset($request->update['mobile_no'][$id]) ? $request->update['mobile_no'][$id] : null;
                    $updateGuardian = isset($request->update['local_guardian'][$id]) ? $request->update['local_guardian'][$id] : null;
                    $i++;
                    tblstudentFamilyHistoryModel::where('id',$id)->update([
                        'name'=>$name,
                        'institute_name'=>$updateInstitute,
                        'course'=>$updateCourse,
                        'year'=>$updateYear,
                        'percentage'=>$updatePercentage,
                        'relation_with_student'=>$updaterelation,
                        'annual_income'=>$updateIncome,
                        'birthdate'=>$updateDob,
                        'blood_group'=>$updateBloodgroup,
                        'designation'=>$updateDesignation, 
                        'company_name'=>$updateCompany,
                        'office_address'=>$updateOffice, 
                        'mobile_no'=>$updateMobile,
                        'guardian_address'=>$updateGuardian,
                        'created_by'=>$user_id, 
                        'updated_on'=>now(), 
                    ]);
                }
            }
        }

        if($i==0){
            $res['status'] = 0;
            $res['message'] = 'Failed to add data!!';
            Session::flash('status', 0); 
            Session::flash('message', 'Failed to add data!!'); 
        }
        else{
            $res['status'] = 1;
            $res['message'] = 'Data added successfully!!';
            Session::flash('status', 1); 
            Session::flash('message', 'Data added successfully!!'); 
        }

        if(in_array($type,['API','JSON'])){
            return response()->json($res);
        }
        return redirect()->back();
    }
    // changed function name on 19-03-2025 for adding new fields 
    public function storeOld(Request $request)
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
        $annual_income = $request['annual_income'];

        tblstudentFamilyHistoryModel::where(["student_id"       => $request->input('student_id'),
                                             "sub_institute_id" => $sub_institute_id,
        ])->delete();
        $request->request->remove('courses');
        $request->request->remove('names');
        $request->request->remove('institute_names');
        $request->request->remove('percentages');
        $request->request->remove('years');
        $request->request->remove('relation_with_students');
        $request->request->remove('annual_income');

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
            $request->request->set('annual_income', $annual_income[$key]);
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
