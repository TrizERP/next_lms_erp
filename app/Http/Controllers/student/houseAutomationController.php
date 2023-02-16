<?php

namespace App\Http\Controllers\student;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\school_setup\standardModel;
use App\Models\student\houseModel;
use App\Models\student\tblstudentEnrollmentModel;
use App\Models\school_setup\std_div_mappingModel;
use function App\Helpers\is_mobile;
use Illuminate\Support\Facades\DB;


class houseAutomationController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request){        
        // $data = $this->getData($request);               
        $type = $request->input('type');
        $res['status_code'] = 1;
        $res['message'] = "Success";
        // $res['data'] = $data;        
        $res['standard_data'] =  $this->getStandards($request);
        return is_mobile($type,'student/show_house_automation',$res,"view");  
    }

    public function getStandards(Request $request)
    {
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $standard_data = standardModel::where(['sub_institute_id'=>$sub_institute_id])->get();
        return $standard_data;
    }  

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create(Request $request)
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
        $type = $request->input('type');
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $syear = $request->session()->get('syear');
        $standard_id = $request->get('standard_id');

        $div_data = std_div_mappingModel::select('division.*')
                    ->join("division",function($join){
                        $join->on("division.id","=","std_div_map.division_id")
                            ->on("division.sub_institute_id","=","std_div_map.sub_institute_id");
                        })
                    ->where(['std_div_map.standard_id' => $standard_id,'std_div_map.sub_institute_id' => $sub_institute_id])
                    ->get()->toArray();

        $house_data = houseModel::where(['sub_institute_id'=>$sub_institute_id])->get();            
        $house_data = json_decode(json_encode($house_data),true);

        if(count($house_data) > 0)
        {
            for($i=0;$i<=count($house_data);$i++)
            {   
                if(isset($house_data[$i]['id']) && $house_data[$i]['id'] != '')
                {
                    $house_id = $house_data[$i]['id'];
                }else{
                    $house_id = '';
                }

                // FOR Male 
                $student_boys =DB::SELECT("SELECT s.id as student_id,se.syear,se.standard_id,se.section_id,s.gender 
                                            FROM tblstudent s 
                                            INNER JOIN tblstudent_enrollment se ON se.student_id = s.id 
                                            AND se.sub_institute_id = s.sub_institute_id 
                                            WHERE s.sub_institute_id = '".$sub_institute_id."' 
                                            AND se.standard_id = '".$standard_id."' AND se.house_id = '".$house_id."' 
                                            AND s.gender = 'M' AND syear = '".$syear."' 
                                            AND end_date is NULL");
                // dd($student_boys);
                $student_boys = json_decode(json_encode($student_boys),true);
                $boys_student_count_per_house = count($student_boys);
                // dd($boys_student_count_per_house);
                $counter = 0;
                for($s=0;$s<=$boys_student_count_per_house;$s++)
                {
                    if($counter == count($div_data))
                    {
                       $counter = 0;
                    } 

                    $section_id = $div_data[$counter];
                    
                    $data = array(
                        'section_id' => $section_id['id'],
                        'house_id' => $house_id
                    );

                    if(isset($student_boys[$s]['student_id']) && $student_boys[$s]['student_id'] != '')
                    {
                        tblstudentEnrollmentModel::where(["syear" => $syear,"sub_institute_id" => $sub_institute_id,"student_id" => $student_boys[$s]['student_id']])->update($data);
                        $counter++;
                    }

                }

                // FOR Female 
                $student_girls =DB::SELECT("SELECT s.id as student_id,se.syear,se.standard_id,se.section_id,s.gender 
                                            FROM tblstudent s 
                                            INNER JOIN tblstudent_enrollment se ON se.student_id = s.id 
                                            AND se.sub_institute_id = s.sub_institute_id 
                                            WHERE s.sub_institute_id = '".$sub_institute_id."' 
                                            AND se.standard_id = '".$standard_id."' AND se.house_id = '".$house_id."' 
                                            AND s.gender = 'F' AND syear = '".$syear."' 
                                            AND end_date is NULL");

                $student_girls = json_decode(json_encode($student_girls),true);
                $girls_student_count_per_house = count($student_girls);

                $counter = 0;
                for($s=0;$s<=$girls_student_count_per_house;$s++)
                {
                    if($counter == count($div_data))
                    {
                       $counter = 0;
                    } 

                    $section_id = $div_data[$counter];
                    
                    $data = array(
                        'section_id' => $section_id['id'],
                        'house_id' => $house_id
                    );
                    if(isset($student_girls[$s]['student_id']) && $student_girls[$s]['student_id'] != '')
                    {
                        tblstudentEnrollmentModel::where(["syear" => $syear,"sub_institute_id" => $sub_institute_id,"student_id" => $student_girls[$s]['student_id']])->update($data);
                        $counter++;
                    }    
                }

            }
            $res['status_code'] = "1";
            $res['message'] = "Student House Allocation Successfully";
            $res['class'] = "alert-success";

            return is_mobile($type, "house_automation.index", $res);
        }
        else{
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
    public function edit(Request $request,$id)
    {
        //
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
    public function destroy(Request $request,$id)
    {
        // 
    }
}
