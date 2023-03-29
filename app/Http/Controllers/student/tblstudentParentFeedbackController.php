<?php

namespace App\Http\Controllers\student;

use App\Http\Controllers\Controller;
use App\Models\student\tblstudentParentFeedbackModel;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use function App\Helpers\is_mobile;

class tblstudentParentFeedbackController extends Controller
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
        $purposes = $request['purposes'];
        $responses = $request['responses'];
        $person_names = $request['person_names'];
        $commentss = $request['commentss'];
        $dates = $request['dates'];


        tblstudentParentFeedbackModel::where(["student_id"       => $request->input('student_id'),
                                              "sub_institute_id" => $sub_institute_id,
        ])->delete();
        $request->request->remove('purposes');
        $request->request->remove('responses');
        $request->request->remove('person_names');
        $request->request->remove('commentss');
        $request->request->remove('dates');

        foreach ($person_names as $key => $value) {
            if ($value == '') {
                break;
            }
            $request->request->set('person_name', $value);
            $request->request->set('purpose', $purposes[$key]);
            $request->request->set('response', $responses[$key]);
            $request->request->set('comments', $commentss[$key]);
            $request->request->set('date', $dates[$key]);

            $data = $this->saveData($request);
        }
        
        $res['status_code'] = 1;
        $res['message'] = "Student Parent Feedback Successfully Updated.";
        $res['data'] = $data;

        return is_mobile($type, "search_student.index", $res);
    }


    public function saveData(Request $request)
    {
        $newRequest = $request->post();
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $user_id = $request->session()->get('user_id');
        $finalArray['sub_institute_id'] = $sub_institute_id;
        $finalArray['created_by'] = $user_id;
        foreach($newRequest as $key => $value){
            if($key != '_method' && $key != '_token' && $key != 'submit'){
                if(is_array($value)){
                    $value = implode(",",$value);
                }
                $finalArray[$key] = $value;
            }
        }
        
        tblstudentParentFeedbackModel::insert($finalArray);

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
