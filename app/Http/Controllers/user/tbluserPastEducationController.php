<?php

namespace App\Http\Controllers\user;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\View;
use function App\Helpers\is_mobile;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use App\Models\user\tbluserPastEducationModel;

class tbluserPastEducationController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $type = $request->input('type');

        $res['status_code'] = 1;
        $res['message'] = "Success";

        return is_mobile($type, "user/add_user_past_education", $res, "view");
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit(Request $request, $id)
    {
        $type = $request->input('type');
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $editData = tbluserPastEducationModel::where(['user_id' => $id, 'sub_institute_id' => $sub_institute_id])->get();
        $pastEducationUser = array();
        $fieldPastEducationUser = array();
        $fieldsArray = array('id', 'degree', 'medium', 'university name', 'passing year', 'main subject', 'secondary subject', 'percentage', 'cpi', 'cgpa', 'remarks');
        foreach ($editData as $key => $value) {
                $pastEducationUser[] = array(
                    'id' => $value['id'],
                    'degree' => $value['degree'],
                    'medium' => $value['medium'],
                    'university name' => $value['university_name'],
                    'passing year' => $value['passing_year'],
                    'main subject' => $value['main_subject'],
                    'secondary subject' => $value['secondary_subject'],
                    'percentage' => $value['percentage'],
                    'cpi' => $value['cpi'],
                    'cgpa' => $value['cgpa'],
                    'remarks' => $value['remarks']
                );
            }

        foreach ($fieldsArray as $key => $value) {
            if ($value == "id") {
                $fieldPastEducationUser[] = array(
                    'name' => $value,
                    'type' => 'hidden',
                    'css' => 'hide'
                );
            } else {
                $fieldPastEducationUser[] = array(
                    'name' => $value,
                    'type' => 'text',
                    'width' => 170
                );
            }
        }
        $fieldPastEducationUser[] = array('type' => 'control');

        view()->share('data', $pastEducationUser);
        view()->share('fieldsData', $fieldPastEducationUser);

        return view('user/edit_user_past_education');
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }

    public function addUpdateUserPastEducation(Request $request)
    {
        $data = json_decode($request->input('data'),true);
        echo "<pre>";
        print_r($data);
        echo "</pre>";
    }
}
