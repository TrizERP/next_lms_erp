<?php

namespace App\Http\Controllers\student;

use App\Http\Controllers\Controller;
use App\Models\school_setup\standardModel;
use App\Models\school_setup\std_div_mappingModel;
use App\Models\student\houseModel;
use App\Models\student\tblstudentEnrollmentModel;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use function App\Helpers\is_mobile;


class houseAutomationController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index(Request $request)
    {
        $type = $request->input('type');
        $res['status_code'] = 1;
        $res['message'] = "Success";
        $res['standard_data'] = $this->getStandards($request);

        return is_mobile($type, 'student/show_house_automation', $res, "view");
    }

    public function getStandards(Request $request)
    {
        $sub_institute_id = $request->session()->get('sub_institute_id');

        return standardModel::where(['sub_institute_id' => $sub_institute_id])->get();
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return void
     */
    public function create(Request $request)
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
        $type = $request->input('type');
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $syear = $request->session()->get('syear');
        $standard_id = $request->get('standard_id');

        $div_data = std_div_mappingModel::select('division.*')
            ->join("division", function ($join) {
                $join->on("division.id", "=", "std_div_map.division_id")
                    ->on("division.sub_institute_id", "=", "std_div_map.sub_institute_id");
            })
            ->where(['std_div_map.standard_id' => $standard_id, 'std_div_map.sub_institute_id' => $sub_institute_id])
            ->get()->toArray();

        $house_data = houseModel::where(['sub_institute_id' => $sub_institute_id])->get();
        $house_data = json_decode(json_encode($house_data), true);

        if (count($house_data) > 0) {
            foreach ($house_data as $i => $iValue) {
                if (isset($iValue['id']) && $iValue['id'] != '') {
                    $house_id = $house_data[$i]['id'];
                } else {
                    $house_id = '';
                }

                // FOR Male 

                $student_boys = DB::table('tblstudent as s')
                    ->join('tblstudent_enrollment as se', function ($join) {
                        $join->whereRaw('se.student_id = s.id AND se.sub_institute_id = s.sub_institute_id');
                    })->selectRaw('s.id as student_id,se.syear,se.standard_id,se.section_id,s.gender')
                    ->where('s.sub_institute_id', $sub_institute_id)
                    ->where('se.standard_id', $standard_id)
                    ->where('se.house_id', $house_id)
                    ->where('s.gender', '=', 'M')
                    ->where('syear', '=', $syear)
                    ->whereNull('end_date')->get()->toArray();

                $student_boys = json_decode(json_encode($student_boys), true);
                $counter = 0;
                foreach ($student_boys as $sValue) {
                    if ($counter == count($div_data)) {
                        $counter = 0;
                    }

                    $section_id = $div_data[$counter];

                    $data = [
                        'section_id' => $section_id['id'],
                        'house_id'   => $house_id,
                    ];

                    if (isset($sValue['student_id']) && $sValue['student_id'] != '') {
                        tblstudentEnrollmentModel::where([
                            "syear"            => $syear,
                            "sub_institute_id" => $sub_institute_id,
                            "student_id"       => $sValue['student_id'],
                        ])->update($data);
                        $counter++;
                    }

                }

                // FOR Female 
                $student_girls = DB::table('tblstudent as s')
                    ->join('tblstudent_enrollment as se', function ($join) {
                        $join->whereRaw('se.student_id = s.id AND se.sub_institute_id = s.sub_institute_id');
                    })->selectRaw('s.id as student_id,se.syear,se.standard_id,se.section_id,s.gender')
                    ->where('s.sub_institute_id', $sub_institute_id)
                    ->where('se.standard_id', $standard_id)
                    ->where('se.house_id', $house_id)
                    ->where('s.gender', '=', 'F')
                    ->where('syear', '=', $syear)
                    ->whereNull('end_date')->get()->toArray();

                $student_girls = json_decode(json_encode($student_girls), true);

                $counter = 0;
                foreach ($student_girls as $sValue) {
                    if ($counter == count($div_data)) {
                        $counter = 0;
                    }

                    $section_id = $div_data[$counter];

                    $data = [
                        'section_id' => $section_id['id'],
                        'house_id'   => $house_id,
                    ];
                    if (isset($sValue['student_id']) && $sValue['student_id'] != '') {
                        tblstudentEnrollmentModel::where([
                            "syear"            => $syear,
                            "sub_institute_id" => $sub_institute_id,
                            "student_id"       => $sValue['student_id'],
                        ])->update($data);
                        $counter++;
                    }
                }

            }
            $res['status_code'] = "1";
            $res['message'] = "Student House Allocation Successfully";
            $res['class'] = "alert-success";

            return is_mobile($type, "house_automation.index", $res);
        } else {
            $res['status_code'] = "0";
            $res['message'] = "Please create house master for house automation.";
            $res['class'] = "alert-danger";

            return is_mobile($type, "house_automation.index", $res);
        }

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
    public function edit(Request $request, $id)
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
    public function destroy(Request $request, $id)
    {
        // 
    }
}
