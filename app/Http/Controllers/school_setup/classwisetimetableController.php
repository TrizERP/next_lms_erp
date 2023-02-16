<?php

namespace App\Http\Controllers\school_setup;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\school_setup\timetableModel;
use function App\Helpers\is_mobile;
use App\Models\school_setup\periodModel;
use Illuminate\Support\Facades\DB;
use GenTux\Jwt\JwtToken;
use GenTux\Jwt\GetsJwtToken;
use function App\Helpers\aut_token;


class classwisetimetableController extends Controller
{
	use GetsJwtToken;
	
    public function index(Request $request){        
        $type = $request->input('type');        
        $res['status_code'] = 1;
        $res['message'] = "SUCCESS";
        $res['academic_section_id'] = '';        
        $res['standard_id'] = '';        
        $res['division_id'] = '';         
        return is_mobile($type,'school_setup/show_classwisetimetable',$res,"view");
    }

    public function getClasswiseTimetable(Request $request)
    {
        $academic_section_id = $request->input("grade"); 
        $standard_id = $request->input("standard");
        $division_id = $request->input("division");
        $sub_institute_id = $request->session()->get('sub_institute_id'); 
        $type = $request->input('type');        
        $res = $this->getTimetable_data($request,$academic_section_id,$standard_id,$division_id,$sub_institute_id);
        return is_mobile($type,'school_setup/show_classwisetimetable',$res,"view");                                                                                                                          
    }

    public function getTimetable_data(Request $request,$academic_section_id,$standard_id,$division_id,$sub_institute_id )
    {        
        $syear = $request->session()->get('syear');

        $get_name_data = DB::select("SELECT ac.title AS academic_name,s.name AS std_name,d.name AS div_name
                                    FROM academic_section ac
                                    INNER JOIN standard s ON s.grade_id = ac.id AND ac.sub_institute_id = s.sub_institute_id
                                    INNER JOIN std_div_map sd ON sd.standard_id = s.id AND sd.sub_institute_id = s.sub_institute_id
                                    INNER JOIN division d ON d.id = sd.division_id
                                    WHERE ac.sub_institute_id = '".$sub_institute_id."' AND ac.id = '".$academic_section_id."' AND s.id = '".$standard_id."' AND d.id = '".$division_id."' ");

        $html = ""; 
        $old_timetable_data = array();
        $timetable_data = timetableModel::select('timetable.*',DB::raw('concat(first_name," ",last_name) as teacher_name'),
        'subject.subject_name','subject.subject_code','batch.title as batch_name')
        ->join('academic_section','academic_section.id' ,"=", 'timetable.academic_section_id')        
        ->join('subject','subject.id' ,"=", 'timetable.subject_id')
        ->join('tbluser','tbluser.id' ,"=", 'timetable.teacher_id')  
        ->leftJoin('batch','batch.id' ,"=", 'timetable.batch_id')  
        ->where([
        'timetable.sub_institute_id' => $sub_institute_id,
        'timetable.academic_section_id' => $academic_section_id,
        'timetable.standard_id' => $standard_id,
        'timetable.division_id' => $division_id,
        'timetable.syear' => $syear
        ])->get()->toArray(); //'concat(first_name," ",middle_name," ",last_name) as teacher_name'


        $school_sql = "SELECT *,GROUP_CONCAT(fees_head_id) heads
            FROM fees_receipt_book_master
            WHERE syear = '" . session()->get('syear') . "'
            AND sub_institute_id = '" . session()->get('sub_institute_id') . "'
            GROUP BY receipt_line_1,receipt_line_2,receipt_line_3,
            receipt_line_4,receipt_prefix,receipt_logo,last_receipt_number";
        $school_sql = preg_replace('/\n+/', '', $school_sql);
        $result = DB::select($school_sql);

        $receipt_book_arr = array();
        foreach ($result as $temp_id => $receipt_detail) 
        {
            $receipt_book_arr = $receipt_detail;
        }

        $image_path = "http://" . $_SERVER['HTTP_HOST']."/storage/fees/" . $receipt_book_arr->receipt_logo;

        // dd($timetable_data);
        foreach($timetable_data as $k => $p){
            $old_timetable_data[$p['week_day']][$p['period_id']]['SUBJECT'][] = $p['subject_name'];
            $old_timetable_data[$p['week_day']][$p['period_id']]['SUBJECT_CODE'][] = $p['subject_code'];
            $old_timetable_data[$p['week_day']][$p['period_id']]['TEACHER'][] = $p['teacher_name'];
            if( isset($p['batch_name']) )
            {
                $old_timetable_data[$p['week_day']][$p['period_id']]['BATCH'][] = $p['batch_name'];
            }
        } 
         // echo 'jhjj';
         // print_r($old_timetable_data);
         // die;       
        $period_data =  periodModel::select('period.*',DB::raw('date_format(period.start_time,"%H:%i") as s_time,date_format(period.end_time,"%H:%i") as e_time'))
        ->where(['sub_institute_id'=>$sub_institute_id])//, 'academic_section_id' => $academic_section_id
        ->orderby('sort_order')
        ->get()
        ->toArray();
        
        $week_data = $this->getweeks();

        // dd($period_data);

        $html = '<table style="margin:0 auto;" width="80%">
                        <tbody>
                            <tr>
                                <td style=" width: 165px;text-align: center;" align="left">';

        // $html .= '    <img style="width: 100px;height: 90px;margin: 0;" src="' . $image_path . '" alt="SCHOOL LOGO">';
        $html .= '</td>';
        $html .= '<td colspan="3" style="text-align:center !important;" align="center"> ';
        if ($receipt_book_arr->receipt_line_1 != '') {
            $html .= '<span style=" font-size: 26px;font-weight: 700;font-family: Arial, Helvetica, sans-serif !important;">' . $receipt_book_arr->receipt_line_1 . '</span><br>';
        }
        if ($receipt_book_arr->receipt_line_2 != '') {
            $html .= '<span style=" font-size: 18px;font-weight: 700;font-family: Arial, Helvetica, sans-serif !important">' . $receipt_book_arr->receipt_line_2 . '</span><br>';
        }
        if ($receipt_book_arr->receipt_line_3 != '') {
            $html .= '<span style=" font-size: 14px;font-weight: 600;font-family: Arial, Helvetica, sans-serif !important">' . $receipt_book_arr->receipt_line_3 . '</span><br>';
        }
        if ($receipt_book_arr->receipt_line_4 != '') {
            $html .= '<span style=" font-size: 14px;font-weight: 600;font-family: Arial, Helvetica, sans-serif !important;">' . $receipt_book_arr->receipt_line_4 . '</span><br>';
        }
        $html .= '</td>';
        $html .= '</tr>';
        $html .= '<tr>
                    <td>&nbsp;</td>
                  </tr>';
        $html .= '<tr>
                    <td colspan="3" style="text-align:center !important;" align="center">
                        <span style=" font-size: 18px;font-weight: 700;font-family: Arial, Helvetica, sans-serif !important;margin-left: 15% !important;">Academic Section : ' .$get_name_data[0]->academic_name. ' | </span>
                    
                    
                        <span style=" font-size: 18px;font-weight: 700;font-family: Arial, Helvetica, sans-serif !important">Standard : '.$get_name_data[0]->std_name.' | </span>
                    
                    
                        <span style=" font-size: 18px;font-weight: 700;font-family: Arial, Helvetica, sans-serif !important">Division : '.$get_name_data[0]->div_name.'</span>
                    </td>
                </tr>';
        $html .= '</tbody>';
        $html .= '</table>';
        $html .= '<br>';
        $html .="<table class='table table-bordered table-center' border=1>";
        if( count($old_timetable_data) > 0 )
        {
            $html .="<tr>                                                   
                <td style='display: table-cell;'><span class='label label-info'>Days - Lectures</span></td>";
        foreach($period_data as $pkey => $pval)                                                        
        {
            $html .="<td style='display: table-cell;'><span class='label label-info'>".$pval['title'];
            $html .="<br>";
            $html .="( ".$pval['s_time']."-".$pval['e_time']." )"."</span></td>";                                                            
        }
        $html .="</tr>";
        foreach($week_data as $wkey => $wval)    
        {
            $html .="<tr>";                                                                
            $html .="<td style='display: table-cell;'><span class='label label-warning'>".$wkey."</span></td>";
            foreach($period_data as $pkey => $pval)                                                        
            {
                $html .="<td align='center' style='font-size:10px;color: black;'>";  
                if(isset($old_timetable_data[$wval][$pval['id']]['SUBJECT']))
                {
                    
                    if( count($old_timetable_data[$wval][$pval['id']]['SUBJECT']) > 0)
                    {
                        foreach($old_timetable_data[$wval][$pval['id']]['SUBJECT'] as $k => $v){
                            $subject_name = $old_timetable_data[$wval][$pval['id']]['SUBJECT'][$k];
                            $subject_code = $old_timetable_data[$wval][$pval['id']]['SUBJECT_CODE'][$k];
                            $batch_name = isset($old_timetable_data[$wval][$pval['id']]['BATCH'][$k])? " / ".$old_timetable_data[$wval][$pval['id']]['BATCH'][$k] : "";

                            $html .= $subject_name.$batch_name;                               
                            // $html .= $subject_name.'-'.$subject_code.$batch_name;                                
                            if(isset($old_timetable_data[$wval][$pval['id']]['TEACHER'][$k])){
                                $html .= "<br>".$old_timetable_data[$wval][$pval['id']]['TEACHER'][$k];
                            }
                            if($k != (count($old_timetable_data[$wval][$pval['id']]['SUBJECT'])-1) )
                            {
                                $html .= "<br><hr>";
                            }
                        }    
                    }
                    else
                    {
                        $html .= $old_timetable_data[$wval][$pval['id']]['SUBJECT'][0];
                        // $html .= $old_timetable_data[$wval][$pval['id']]['SUBJECT'][0].'-'.$old_timetable_data[$wval][$pval['id']]['SUBJECT_CODE'][0];
                        if(isset($old_timetable_data[$wval][$pval['id']]['TEACHER'][0])){
                            $html .= "<br>".$old_timetable_data[$wval][$pval['id']]['TEACHER'][0];
                        }
                    }
                }
                else
                {
                    $html .= "<font color='red' style='font-size:10px;'>--No Period--</font>";
                }
                $html .="</td>";  
            }
            $html .="</tr>";
        }
        }
        else{
            $html .= "<tr><td align='center' style='text-align: center;'>No Records Found!</td></tr>";
        }
        $html .="</table>";

        $type = $request->input('type');        
        $res['status_code'] = 1;
        $res['message'] = "SUCCESS";
        $res['HTML'] = $html;
        $res['academic_section_id'] = $academic_section_id;        
        $res['standard_id'] = $standard_id;        
        $res['division_id'] = $division_id;             
        return $res;
    }
    public function getweeks()
    {
        $week_days = array("Monday" => "M","Tuesday" => "T","Wednesday" => "W","Thursday" => "H","Friday" => "F","Saturday" => "S");        
        return $week_days;
    }
	
	public function studentTimetableAPI(Request $request) {
	
		try {
            if (!$this->jwtToken()->validate()) {
                $response = array('status' => '2', 'message' => 'Token Auth Failed', 'data' => array());
                return response()->json($response, 401);
            }
        } catch (\Exception $e) {
            $response = array('status' => '2', 'message' => $e->getMessage(), 'data' => array());
            return response()->json($response, 401);
        }
		
		$type = $request->input("type");
		$student_id = $request->input("student_id");
		$sub_institute_id = $request->input("sub_institute_id");
		$syear = $request->input("syear");

		if($student_id != "" && $sub_institute_id != "" && $syear != "")
		{					
			/*$data = DB::select("SELECT * FROM (
            SELECT CASE
                    WHEN week_day = 'M' THEN 'Mon'
                    WHEN week_day = 'T' THEN 'Tue'
                    WHEN week_day = 'W' THEN 'Wed'
                    WHEN week_day = 'H' THEN 'Thu'
                    WHEN week_day = 'F' THEN 'Fri'
                    WHEN week_day = 'S' THEN 'Sat'
                END AS week_day,s.name as standard_name,sub.subject_name,
            CONCAT_WS(' ',u.first_name,u.middle_name,u.last_name) as teacher_name,p.title as period_name,p.start_time,p.end_time,
            ts.studentbatch,b.title as batch_name,t.batch_id,p.id as period_id
            FROM timetable t
            INNER JOIN tblstudent_enrollment se ON se.standard_id = t.standard_id AND se.section_id = t.division_id AND se.sub_institute_id = t.sub_institute_id AND se.syear = t.syear             
            INNER JOIN tblstudent ts ON ts.id = se.student_id AND ts.sub_institute_id = se.sub_institute_id
            INNER JOIN standard s ON s.id = t.standard_id AND s.sub_institute_id = t.sub_institute_id
            INNER JOIN subject sub ON sub.id = t.subject_id AND sub.sub_institute_id = t.sub_institute_id
            INNER JOIN tbluser u ON u.id = t.teacher_id AND u.sub_institute_id = t.sub_institute_id
            LEFT JOIN period p ON p.id = t.period_id AND p.sub_institute_id = t.sub_institute_id
            LEFT JOIN batch b ON b.id = t.batch_id AND b.sub_institute_id = t.sub_institute_id
            WHERE se.student_id = '".$student_id."' AND t.sub_institute_id = '".$sub_institute_id."' AND t.syear = '".$syear."'
            ) AS A WHERE batch_id IS NULL OR batch_id = studentbatch
            ORDER BY week_day,period_id
            ");*/
			
            $data = DB::select("SELECT CASE 
                WHEN week_day = 'M' THEN 'Mon' 
                WHEN week_day = 'T' THEN 'Tue' 
                WHEN week_day = 'W' THEN 'Wed' 
                WHEN week_day = 'H' THEN 'Thu' 
                WHEN week_day = 'F' THEN 'Fri' 
                WHEN week_day = 'S' THEN 'Sat' 
                END AS week_day,s.name AS standard_name,GROUP_CONCAT(sub.subject_name) subject_name, GROUP_CONCAT(CONCAT_WS(' ',u.first_name,u.middle_name,u.last_name)) AS teacher_name,p.title AS period_name,p.start_time,p.end_time,ts.studentbatch,b.title AS batch_name,t.batch_id,p.id AS period_id
                FROM timetable t
                INNER JOIN tblstudent_enrollment se ON se.standard_id = t.standard_id AND se.section_id = t.division_id AND se.sub_institute_id = t.sub_institute_id AND se.syear = t.syear
                INNER JOIN tblstudent ts ON ts.id = se.student_id AND ts.sub_institute_id = se.sub_institute_id
                INNER JOIN standard s ON s.id = t.standard_id AND s.sub_institute_id = t.sub_institute_id
                INNER JOIN subject sub ON sub.id = t.subject_id AND sub.sub_institute_id = t.sub_institute_id
                INNER JOIN tbluser u ON u.id = t.teacher_id AND u.sub_institute_id = t.sub_institute_id
                LEFT JOIN period p ON p.id = t.period_id AND p.sub_institute_id = t.sub_institute_id
                LEFT JOIN batch b ON b.id = t.batch_id AND b.sub_institute_id = t.sub_institute_id
            WHERE se.student_id = '".$student_id."' AND t.sub_institute_id = '".$sub_institute_id."' AND t.syear = '".$syear."'
            GROUP BY period_id,week_day
            ORDER BY week_day,period_id
            ");

			$res['status'] = 1;
			$res['message'] = "Success";
			$res['data'] = $data;				
		}else{
			$res['status'] = 0;
			$res['message'] = "Parameter Missing";
		}		
		return json_encode($res);					
	}
}
