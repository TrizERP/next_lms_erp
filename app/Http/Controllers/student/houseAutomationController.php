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
        $res['data'] = session('data');
        // echo "<pre>";print_r($res['data']);exit;
        return is_mobile($type, 'student/show_house_automation', $res, "view");
    }

    public function getStandards(Request $request)
    {
        $sub_institute_id = $request->session()->get('sub_institute_id');

        return standardModel::where(['sub_institute_id' => $sub_institute_id])->get();
    }


    public function getStudent($syear,$sub_institute_id,$house_id='',$standard_id,$sectionId='',$gender,$countDiv=''){
        // db::enableQueryLog();
        
        $data = DB::table('tblstudent')
        ->join('tblstudent_enrollment', function ($join) use($gender) {
            $join->on('tblstudent.id', '=', 'tblstudent_enrollment.student_id');
        })
        ->where('tblstudent_enrollment.syear', $syear);
        if($house_id!=''){
            $data->where('tblstudent_enrollment.house_id', $house_id);
        }
        if($sectionId!=''){
            $data->where('tblstudent_enrollment.section_id', $sectionId);
        }
       
        // ->where('tblstudent_enrollment.section_id', $sectionId)
        $data = $data->where('tblstudent_enrollment.standard_id', $standard_id)
        ->where('tblstudent_enrollment.sub_institute_id', $sub_institute_id)
        ->whereNull('tblstudent_enrollment.end_date')
        ->where('tblstudent.gender', '=', $gender)
        ->when($countDiv=='',function($q){
            $q->whereNotIn('tblstudent_enrollment.section_id', [1397]); // Exclude section_id 1397
        })
        ->get();
        //update house

        return $data;
    }
    /**
     * Store a newly created resource in storage.
     *
     * @param  Request  $request
     * @return Response
     */
    public function create(Request $request)
    {
        $type = $request->input('type');
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $syear = $request->session()->get('syear');
        $res['standard_id'] = $standard_id =$request->standard_id;
        $res['opt_sub'] = $opt_sub=$request->opt_sub;
        $std_name = DB::table('standard')->where('id',$standard_id)->where('sub_institute_id',$sub_institute_id)->first();
        $res['std_name'] = $std_name->name;
        // get houses 
        $res['house_data'] = $houseResults = DB::table('house_master')->where('sub_institute_id', $sub_institute_id)->get();
        $res['total_house'] = $totalHouses = $houseResults->count();

        if ($totalHouses > 0) {

            // if optional subject is no then equally divide students in house and division
            if($standard_id!=null && $opt_sub=='No'){

                $studAllM = $this->getStudent($syear,$sub_institute_id,'',$standard_id,'','M','Count');
                $studAllMCount = $studAllM->count();
                $studAllF = $this->getStudent($syear,$sub_institute_id,'',$standard_id,'','F','Count');
                $studAllFCount = $studAllF->count();

            // Fetch all division expect division "T". do not divide student into div "T"
            $sectionArray = DB::table('std_div_map as sdm')->selectRaw('count(sdm.id) as total_div,GROUP_CONCAT(sdm.division_id) as section_id')
            ->where('sdm.standard_id', $standard_id)
            ->where('sdm.sub_institute_id', $sub_institute_id)
            ->whereNotIn('sdm.division_id', [1397]) // Exclude section_id 1397
            ->groupBy('sdm.standard_id')
            ->get();
            // echo "<pre>";print($sectionArray[0]->section_id);exit;
            $sectionArray = explode(",", $sectionArray[0]->section_id);
            $section_array=array_filter($sectionArray);               

            $section_array = array_merge($section_array);
            $res['total_div'] = $totalDiv = count($section_array) ?? 0;
            // echo "<pre>";print_r($section_array);exit;
                // start division according to house 
                foreach ($houseResults as $houseResult) {
                    // get all students including div "T"
                    $studHouseM = $this->getStudent($syear,$sub_institute_id,$houseResult->id,$standard_id,'','M','Count');
                    $studHouseMCount = $studHouseM->count();
                   
                    $counter2=0;
                     for($s=1;$s<=$studHouseMCount;$s++){
                        if($counter2==count($section_array)){
                            $counter2=0;
                         }                
                         $section_id=$section_array[$counter2];
                            $updateQuery = DB::table('tblstudent_enrollment')
                                ->where('syear',$syear)
                                ->where('student_id', $studHouseM[($s-1)]->student_id)
                                ->where('sub_institute_id', $sub_institute_id)                    
                                ->update([
                                    'section_id' => $section_id,
                                    'house_id' => $houseResult->id,
                                    ]);
                                $counter2++;
                        }
                        $studHouseF = $this->getStudent($syear,$sub_institute_id,$houseResult->id,$standard_id,'','F','Count');
                        $studHouseFCount = $studHouseF->count();

                        $counter3=0;
                        for($sf=1;$sf<=$studHouseFCount;$sf++){
                            if($counter3==count($section_array)){
                                $counter3=0;
                             }                
                            $section_id2=$section_array[$counter3];
                            $updateQuery = DB::table('tblstudent_enrollment')
                                ->where('syear',$syear)
                                ->where('student_id', $studHouseF[($sf-1)]->student_id)
                                ->where('sub_institute_id', $sub_institute_id)                    
                                ->update([
                                    'section_id' => $section_id2,
                                    'house_id' => $houseResult->id,
                                    ]);
                                $counter3++;
                        }
                }
                $formatArray = [];
           
            $totalBoys=$totalGirls=0;
            
            foreach ($sectionArray as $sectionId) {
                $sectionResult = DB::table('division')->where('id', $sectionId)
                    ->where('sub_institute_id', $sub_institute_id)
                    ->first();
            
                $formatArrayIncermenter = $formatArrayIncermenter ?? 0;
                $formatArray[$formatArrayIncermenter]["division"] = $sectionResult ? $sectionResult->name : '';
                // Fetching student counts for each house and gender
                foreach ($houseResults as $houseResult) {
                    $house_id=$houseResult->id;
                    // for boys 
                    $studMaleCount = $this->getStudent($syear,$sub_institute_id,$house_id,$standard_id,$sectionId,'M');
                    
                    $studMCount= $studMaleCount->count();
                    //for girls 
                    $studFemaleCount = $this->getStudent($syear,$sub_institute_id,$house_id,$standard_id,$sectionId,'F');
                    $studFCount= $studFemaleCount->count(); 
                  
                    // Populating the format array
                    $formatArray[$formatArrayIncermenter][$houseResult->house_name . "_b"] = $studMCount ?? 0;
                    $formatArray[$formatArrayIncermenter][$houseResult->house_name . "_g"] = $studFCount ?? 0;
            
                    $totalBoys += $studMCount ?? 0;
                    $totalGirls += $studFCount ?? 0;
                }
            
                $formatArrayIncermenter++;
            }
            // Additional data, if needed
            $res['total_boys']=$studAllMCount;
            $res['total_girls']=$studAllFCount;        
            $res['student_house']=$formatArray;
            // echo "<pre>";print_r($res);exit;        
        }else{
            // DB::enableQueryLog();
                $queryResultM = DB::table('tblstudent as s')
                ->select('se.student_id', 'se.house_id', 'se.id', 'se.syear', 'se.standard_id', 'se.section_id', 'sos.subject_id', 's.gender')
                ->join('tblstudent_enrollment as se', function ($join) {
                    $join->on('s.id', '=', 'se.student_id');
                })
                ->join('student_optional_subject as sos', 'sos.student_id', '=', 'se.student_id')
                ->join('sub_std_map as ssm','ssm.subject_id','=','sos.subject_id')
                ->join('subject_elective as sel','sel.subject_id','=','sos.subject_id')
                ->where('se.syear', $syear)
                ->where('s.sub_institute_id', $sub_institute_id)
                ->where('se.standard_id', $standard_id)
                ->whereNull('se.end_date')
                //->whereNotIn('se.section_id', [1397]) // Exclude section_id 1397
                ->where('s.gender', '=', 'M')->distinct()
                ->get();
                // dd(DB::getQueryLog($queryResultM));
            // echo "<pre>";print_r($queryResultM);exit;        

            $studentAllCountM = $queryResultM->count();
           
            $queryResultF = DB::table('tblstudent as s')
                ->select('se.student_id', 'se.house_id', 'se.id', 'se.syear', 'se.standard_id', 'se.section_id', 'sos.subject_id', 's.gender')
                ->join('tblstudent_enrollment as se', function ($join) {
                    $join->on('s.id', '=', 'se.student_id');
                })
                ->join('student_optional_subject as sos', 'sos.student_id', '=', 'se.student_id')
                ->join('sub_std_map as ssm','ssm.subject_id','=','sos.subject_id')                
                ->join('subject_elective as sel','sel.subject_id','=','sos.subject_id')                
                ->where('se.syear', $syear)
                ->where('s.sub_institute_id', $sub_institute_id)
                ->where('se.standard_id', $standard_id)
                ->whereNull('se.end_date') 
                //->whereNotIn('se.section_id', [1397]) // Exclude section_id 1397
                ->where('s.gender', '=', 'F')
                ->distinct()
                ->get();
            
            $studentAllCountF = $queryResultF->count();
            // echo "<pre>";print_r($queryResultM);exit;
           
            $subjectResult = DB::table('subject_elective as se')
                ->select('se.subject_id')
                // ->where('sos.subject_id', $subject_id)
                ->where('se.syear', $syear)                
                ->where('se.standard_id', $standard_id)
                ->where('se.sub_institute_id', $sub_institute_id)
                ->orderBy('se.subject_id')
                ->get();
            // echo "<pre>";print_r($subjectResult);exit;
                
            foreach ($subjectResult as $subject) {
                $subject_id = $subject->subject_id;

                $sectionResult = DB::table('subject_elective as se')
                ->select('division_id','subject_id')
                ->distinct()
                ->where('se.syear', $syear)                                
                ->where('se.standard_id', $standard_id)
                ->where('se.subject_id',$subject_id)
                ->where('se.sub_institute_id', $sub_institute_id)
                ->orderBy('se.division_id')
                ->get();
                // echo "<pre>";print_r($sectionResult);exit;
             
                $sectionArray = $sectionResult->pluck('division_id')->toArray();
                // echo "<pre>";print_r($sectionArray);exit;
               
                $studentResultM = DB::table('student_optional_subject as SJOS')
                ->select('HSS.id as student_id', 'CE.subject_id as elec_sub','SJOS.subject_id', 'SE.section_id','SE.house_id')
                ->distinct()
                ->join('tblstudent as HSS', function ($join) {
                    $join->on('HSS.id', '=', 'SJOS.student_id');
                })
                ->join('tblstudent_enrollment as SE',function($join){
                    $join->on('SJOS.student_id', '=', 'SE.student_id')->on('SJOS.syear', '=', 'SE.syear'); // added syear 12-02-2025
                })
                ->join('subject_elective as CE',function($join) {
                    $join->on('CE.subject_id', '=', 'SJOS.subject_id')->on('SJOS.syear','=','CE.syear');
                })
                ->where('SJOS.syear', '=', $syear) // added syear 12-02-2025
                ->where('SE.standard_id', '=', $standard_id)
                ->where('SJOS.subject_id', '=', $subject_id)
                ->where('SJOS.sub_institute_id',$sub_institute_id)
                ->where('HSS.gender', '=', 'M')
                ->orderBy('SJOS.student_id')
                ->groupBy('SJOS.student_id')
                ->get();
                // echo "male";

                // dd(DB::getQueryLog());
                $studAllMCount = $studentResultM->count();
                
                    $ct=1;
                    $sc=$msub=array();
                    for($j=1;$j<=count($sectionResult);$j++){
                        $section_id = $sectionResult[($j-1)]->division_id;
                        $subject = $sectionResult[($j-1)]->subject_id;
                        //display_array($section_id);
                        $sc[$j] = $section_id;
                        $msub[$j] = $subject;
                    }   
                    
                    //display_array($sc);
                    $s_id=$mDiv=[];
                    for($s=1;$s<=count($studentResultM);$s++){
                         $student_id = $studentResultM[($s-1)]->student_id;
                         $subject_id = $studentResultM[($s-1)]->subject_id;
                         if($ct==count($sc)+1){
                                $ct=1;
                            }                            
                            $section_id=$sc[$ct];
                            $sub_id=$msub[$ct];
                            if($sub_id===$subject_id){ // added condition 12-02-2025
                                // echo 'student_id='.$student_id.' elective_sub='.$sub_id.' student_sub='.$subject_id.' DIv='.$section_id.'<br>';
                                $updateQuery = DB::table('tblstudent_enrollment')
                                ->where('syear',$syear)
                                ->where('student_id', $student_id)
                                ->where('sub_institute_id', $sub_institute_id)                    
                                ->update([
                                    'section_id' => $section_id,
                                    ]);
                                $s_id[] = $student_id;
                                $mDiv[$section_id][$sub_id][] = $student_id;
                                $ct++;
                            }
                        }
                        // echo "male-<pre>";print_r($mDiv);

    
                $studentResultF = DB::table('student_optional_subject as SJOS')
                ->select('HSS.id as student_id', 'CE.subject_id', 'SE.section_id','SE.house_id')
                ->distinct()
                ->join('tblstudent as HSS', function ($join) {
                    $join->on('HSS.id', '=', 'SJOS.student_id');
                })
                ->join('tblstudent_enrollment as SE',function($join){
                    $join->on('SJOS.student_id', '=', 'SE.student_id')->on('SJOS.syear', '=', 'SE.syear'); // added syear 12-02-2025
                })
                ->join('subject_elective as CE',function($join) {
                    $join->on('CE.subject_id', '=', 'SJOS.subject_id')->on('SJOS.syear','=','CE.syear');
                })
                ->where('SJOS.syear', '=', $syear) // added syear 12-02-2025
                ->where('SE.standard_id', '=', $standard_id)
                ->where('SJOS.subject_id', '=', $subject_id)
                ->where('SJOS.sub_institute_id',$sub_institute_id)
                ->where('HSS.gender', '=', 'F')
                ->orderBy('SJOS.student_id')
                ->get();
                // echo "<pre>";print_r($studentResultF);

                $studAllFCount = $studentResultF->count();
                $ct=1;
                    $fsc=$fsub= $fDiv=array();
                    for($j=1;$j<=count($sectionResult);$j++){
                        $section_id = $sectionResult[($j-1)]->division_id;
                        $subject_id = $sectionResult[($j-1)]->subject_id;
                        //display_array($section_id);
                        $fsc[$j] = $section_id;
                        $fsub[$j] = $subject_id;
                            
                    }   
                    //display_array($sc);
                    for($s=1;$s<=count($studentResultF);$s++){
                         $student_id = $studentResultF[($s-1)]->student_id;
                         $subject_id = $studentResultF[($s-1)]->subject_id;
                         if($ct==count($fsc)+1){
                                $ct=1;
                            }                            
                            $section_id=$fsc[$ct];
                            $sub=$fsub[$ct];
                            if($sub===$subject_id){ // added condition 12-02-2025
                                $updateQuery = DB::table('tblstudent_enrollment')
                                ->where('syear',$syear)
                                ->where('student_id', $student_id)
                                ->where('sub_institute_id', $sub_institute_id)                    
                                ->update([
                                    'section_id' => $section_id,
                                    ]);
                                    $fDiv[$section_id][$sub_id][] = $student_id;
                                
                                $ct++;
                            }
                        }
                // echo "female-<pre>";print_r($fDiv);
               
            }
// exit;
            $formatArray = [];
            $formatArrayIncrementer = 0;
            
            $sectionArray = DB::table('std_div_map')->selectRaw('count(id) as total_div,GROUP_CONCAT(division_id) as section_id')
            ->where('standard_id', $standard_id)
            ->where('sub_institute_id', $sub_institute_id)
            ->whereNotIn('division_id', [1397]) // Exclude section_id 1397
            ->groupBy('standard_id')
            ->first();
            $res['total_div'] = $totalDiv =$sectionArray->total_div ?? 0;
        // echo "<pre>";print();exit;
        $sectionArray = array_filter(explode(",", $sectionArray->section_id));
            $houseResults = DB::table('house_master')->where('sub_institute_id', $sub_institute_id)->get();
            $totalBoys = $totalGirls=0;

            foreach ($sectionArray as $sectionId) {
                
                $sectionResult = DB::table('division')->where('id', $sectionId)->where('sub_institute_id', $sub_institute_id)->first();
            
                $formatArray[$formatArrayIncrementer]["division"] = $sectionResult ? $sectionResult->name : '';
            
                foreach ($houseResults as $houseResult) {
                    $houseId = $houseResult->id;
                    // for boys
                    $StudentM = $this->getStudent($syear,$sub_institute_id,$houseId,$standard_id,$sectionId,'M');
                    $studentCountM = $StudentM->count();
                    // for girls
                    $StudentF = $this->getStudent($syear,$sub_institute_id,$houseId,$standard_id,$sectionId,'F');
                    
                    $studentCountF = $StudentF->count();
            
                    $formatArray[$formatArrayIncrementer][$houseResult->house_name . "_b"] = $studentCountM ?? 0;
                    $formatArray[$formatArrayIncrementer][$houseResult->house_name . "_g"] = $studentCountF ?? 0;
                    $totalBoys += $studentCountM ?? 0;
                    $totalGirls += $studentCountF ?? 0;
                }
            
                $formatArrayIncrementer++;
                
            }
            $res['total_boys']=$studentAllCountM;
            $res['total_girls']=$studentAllCountF;     
            $res['student_house'] = $formatArray;            
            }
            $res['status_code'] = "1";
            $res['message'] = "Student House Allocation Successfully";
            $res['class'] = "alert-success";
            $res['standard_data'] = $this->getStandards($request);
        // echo "<pre>";print_r($res);exit;
        return is_mobile($type, 'student/show_house_automation', $res, "view");
            
        } else {
            $res['status_code'] = "0";
            $res['message'] = "Please create house master for house automation.";
            $res['class'] = "alert-danger";

            return is_mobile($type, "house_automation.index", $res);
        }

    }
}
