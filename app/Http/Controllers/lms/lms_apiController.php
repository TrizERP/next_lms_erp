<?php

namespace App\Http\Controllers\lms;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use function App\Helpers\is_mobile;
use Illuminate\Support\Facades\DB;
use GenTux\Jwt\JwtToken;
use GenTux\Jwt\GetsJwtToken;
use function App\Helpers\aut_token;
use App\Models\lms\questionpaperModel;
use App\Models\lms\answermasterModel;
use App\Models\lms\lmsQuestionMasterModel;
use App\Models\lms\lmsOnlineExamModel;
use App\Models\lms\lmsOnlineExamAnswerModel;
use App\Models\student\tblstudentEnrollmentModel;
use App\Models\lms\lmsContentCategoryModel;


class lms_apiController extends Controller
{
    use GetsJwtToken;

    public function studentVirtualClassroomAPI(Request $request) {
       try {
            if (!$this->jwtToken()->validate()) {
                $response = array('status' => '2', 'message' => 'Token Auth Failed', 'data' => array());
                return response()->json($response, 401);
            }
        } catch (\Exception $e) {
            $response = array('status' => '2', 'message' => $e->getMessage(), 'data' => array());
            return response()->json($response, 401);
        }
                
        $student_id = $request->input("student_id");
        $type = $request->input("type");
        $sub_institute_id = $request->input("sub_institute_id");
        $syear = $request->input("syear");        

        if($student_id != "" && $sub_institute_id != "" && $syear != "")
        {         
            $data = DB::select("SELECT st.name AS standard_name,sub.subject_name,c.chapter_name,t.name AS topic_name,s.syear,
            s.sub_institute_id,v.room_name,v.description,v.event_date,v.from_time,v.to_time,v.recurring,v.url,v.password,
            CONCAT_WS(' ',u.first_name,u.middle_name,u.last_name) AS teacher_name
            FROM tblstudent_enrollment s
            INNER JOIN lms_virtual_classroom v ON s.standard_id = v.standard_id AND s.sub_institute_id = v.sub_institute_id
            INNER JOIN standard st ON st.id = v.standard_id AND st.sub_institute_id = s.sub_institute_id
            INNER JOIN subject sub ON sub.id = v.subject_id AND sub.sub_institute_id = s.sub_institute_id
            INNER JOIN chapter_master c ON c.id = v.chapter_id AND c.sub_institute_id = v.sub_institute_id
            INNER JOIN topic_master t ON t.id = v.topic_id AND t.sub_institute_id = v.sub_institute_id
            INNER JOIN tbluser u ON u.id = v.created_by
            WHERE s.student_id = '".$student_id."' AND s.syear = '".$syear."' AND s.sub_institute_id = '".$sub_institute_id."'             
            ");
            
            $res['status'] = 1;
            $res['message'] = "Success";
            $res['data'] = $data;   
        }else{
            $res['status'] = 0;
            $res['message'] = "Parameter Missing";
        }
        //return  \App\Helpers\is_mobile($type, "implementation", $res);
        return json_encode($res);       
    }

    public function studentPortfolioAPI(Request $request) {
       try {
            if (!$this->jwtToken()->validate()) {
                $response = array('status' => '2', 'message' => 'Token Auth Failed', 'data' => array());
                return response()->json($response, 401);
            }
        } catch (\Exception $e) {
            $response = array('status' => '2', 'message' => $e->getMessage(), 'data' => array());
            return response()->json($response, 401);
        }
                
        $student_id = $request->input("student_id");
        $type = $request->input("type");
        $sub_institute_id = $request->input("sub_institute_id");
        $syear = $request->input("syear");        

        if($student_id != "" && $sub_institute_id != "" && $syear != "")
        {         
            $data = DB::select("SELECT p.*, CONCAT_WS(' ',u.first_name,u.middle_name,u.last_name) AS teacher_name,
            if(p.file_name = '','',concat('https://".$_SERVER['SERVER_NAME']."/storage/lms_portfolio/',p.file_name)) as file_name, DATE_FORMAT(p.created_at,'%d-%m-%Y') AS created_at
            FROM lms_portfolio p
            LEFT JOIN tbluser u ON p.feedback_by = u.id            
            WHERE p.user_id = '".$student_id."' AND p.syear = '".$syear."' AND p.sub_institute_id = '".$sub_institute_id."'             
            ");
            
            $res['status'] = 1;
            $res['message'] = "Success";
            $res['data'] = $data;   
        }else{
            $res['status'] = 0;
            $res['message'] = "Parameter Missing";
        }
        //return  \App\Helpers\is_mobile($type, "implementation", $res);
        return json_encode($res);       
    }

    public function studentSocialCollabrativeAPI(Request $request) {
       try {
            if (!$this->jwtToken()->validate()) {
                $response = array('status' => '2', 'message' => 'Token Auth Failed', 'data' => array());
                return response()->json($response, 401);
            }
        } catch (\Exception $e) {
            $response = array('status' => '2', 'message' => $e->getMessage(), 'data' => array());
            return response()->json($response, 401);
        }
                
        $student_id = $request->input("student_id");
        $type = $request->input("type");
        $sub_institute_id = $request->input("sub_institute_id");
        $syear = $request->input("syear");        

        if($student_id != "" && $sub_institute_id != "" && $syear != "")
        {         
            $doubtdata = DB::select("SELECT *,
            if(d.file_name = '','',concat('https://".$_SERVER['SERVER_NAME']."/storage/lms_doubts/',d.file_name)) as file_name
            FROM lms_doubt d            
            WHERE d.user_id = '".$student_id."' AND d.syear = '".$syear."' AND d.sub_institute_id = '".$sub_institute_id."'             
            ");
            $doubtdata = json_decode(json_encode($doubtdata),true);
            $finaldata = array();
            if(count($doubtdata) > 0)
            {          
                foreach($doubtdata as $key => $val)
                {                
                    $conversationData = DB::select("SELECT c.*,CONCAT_WS(' ',s.first_name,s.middle_name,s.last_name) as student_name 
                    FROM lms_doubt_conversation c
                    INNER JOIN tblstudent s on c.user_id = s.id and c.sub_institute_id=s.sub_institute_id
                    WHERE c.sub_institute_id = '".$sub_institute_id."'  AND c.doubt_id = '".$val['id']."'                        
                    ");
                    $conversationData = json_decode(json_encode($conversationData),true);  

                    $finaldata[$val['id']] = $val;
                    $finaldata[$val['id']]['ConversationData'] = $conversationData;
                }
            }          
            $res['status'] = 1;
            $res['message'] = "Success";
            $res['data'] = $finaldata;   
        }else{
            $res['status'] = 0;
            $res['message'] = "Parameter Missing";
        }
        //return  \App\Helpers\is_mobile($type, "implementation", $res);
        return json_encode($res);       
    }

    public function studentSubjectAPI(Request $request) {
       try {
            if (!$this->jwtToken()->validate()) {
                $response = array('status' => '2', 'message' => 'Token Auth Failed', 'data' => array());
                return response()->json($response, 401);
            }
        } catch (\Exception $e) {
            $response = array('status' => '2', 'message' => $e->getMessage(), 'data' => array());
            return response()->json($response, 401);
        }
                
        $student_id = $request->input("student_id");
        $type = $request->input("type");
        $sub_institute_id = $request->input("sub_institute_id");
        $syear = $request->input("syear");        

        if($student_id != "" && $sub_institute_id != "" && $syear != "")
        {         
            $subjectdata = DB::select("SELECT sb.standard_id,sb.subject_id,sb.display_name,sb.allow_grades,sb.allow_content,sb.display_image,
            sb.sort_order,sb.elective_subject,sb.subject_category,sb.status,
            if(sb.display_image = '','',concat('https://".$_SERVER['SERVER_NAME']."/storage',sb.display_image)) as display_image
            FROM tblstudent s
            INNER JOIN tblstudent_enrollment se ON s.id = se.student_id
            INNER JOIN sub_std_map sb on sb.sub_institute_id = se.sub_institute_id and sb.standard_id = se.standard_id
            WHERE s.sub_institute_id = '".$sub_institute_id."' AND s.id = '".$student_id."' AND se.syear = '".$syear."' AND sb.allow_content='Yes'                           
            ");                    
                    
            $res['status'] = 1;
            $res['message'] = "Success";
            $res['data'] = $subjectdata;   
        }else{
            $res['status'] = 0;
            $res['message'] = "Parameter Missing";
        }
        //return  \App\Helpers\is_mobile($type, "implementation", $res);
        return json_encode($res);       
    }
    
    public function studentContentAPI(Request $request) {
       try {
            if (!$this->jwtToken()->validate()) {
                $response = array('status' => '2', 'message' => 'Token Auth Failed', 'data' => array());
                return response()->json($response, 401);
            }
        } catch (\Exception $e) {
            $response = array('status' => '2', 'message' => $e->getMessage(), 'data' => array());
            return response()->json($response, 401);
        }
                
        $student_id = $request->input("student_id");
        $type = $request->input("type");
        $sub_institute_id = $request->input("sub_institute_id");
        $syear = $request->input("syear");        
        $subject_id = $request->input("subject_id");        

        if($student_id != "" && $sub_institute_id != "" && $syear != "" && $subject_id != "")
        {         
            $chapterdata = DB::select("SELECT c.id AS chapter_id,c.syear,c.standard_id,c.subject_id,c.chapter_name,c.chapter_desc,c.availability,c.show_hide,c.sort_order
                            FROM tblstudent s
                            INNER JOIN tblstudent_enrollment se ON s.id = se.student_id
                            INNER JOIN chapter_master c ON c.sub_institute_id = se.sub_institute_id AND c.standard_id = se.standard_id           
                            WHERE s.sub_institute_id = '".$sub_institute_id."' AND s.id = '".$student_id."' AND se.syear = '".$syear."' 
                            AND c.subject_id = '".$subject_id."'  AND c.show_hide = '1'
                            ORDER BY c.sort_order
                             ");// AND c.syear = se.syear

            $chapterdata = json_decode(json_encode($chapterdata),true);
            $finaldata = array();
            if(count($chapterdata) > 0)
            {          
                foreach($chapterdata as $key => $val)
                {                
                    $chapter_id = $val['chapter_id'];
                    $topicData = DB::select("SELECT * FROM topic_master 
                                WHERE sub_institute_id = '".$sub_institute_id."' AND chapter_id = '".$chapter_id."' 
                                AND topic_show_hide = '1'
                                ORDER BY topic_sort_order
                                ");//AND syear = '".$syear."' 
                    $topicData = json_decode(json_encode($topicData),true);  

                    $finaldata[$chapter_id] = $val;
                    $finaldata[$chapter_id]['topicData'] = $topicData;

                    if(count($topicData) > 0)
                    {
                        foreach($topicData as $tkey => $tval)
                        {
                            $contentData = DB::select("SELECT *, 
                                        if(filename = '','',
                                            if(file_type = 'link',filename,concat('https://".$_SERVER['SERVER_NAME']."/storage',file_folder,'/',filename))) as full_path 
                                        FROM content_master 
                                        WHERE sub_institute_id = '".$sub_institute_id."' AND chapter_id = '".$chapter_id."'  
                                        AND topic_id = '".$tval['id']."' AND subject_id = '".$subject_id."' AND show_hide = '1'                                 
                                        ");//AND syear = '".$syear."'
                            $contentData = json_decode(json_encode($contentData),true);
                            $finaldata[$chapter_id]['topicData'][$tkey]['contentData'] = $contentData;  
                        }
                    }
                }
            }                     
                    
            $res['status'] = 1;
            $res['message'] = "Success";
            $res['data'] = $finaldata;   
        }else{
            $res['status'] = 0;
            $res['message'] = "Parameter Missing";
        }
        //return  \App\Helpers\is_mobile($type, "implementation", $res);
        return json_encode($res);       
    }

    public function studentQuestionPaperListAPI(Request $request) {
       try {
            if (!$this->jwtToken()->validate()) {
                $response = array('status' => '2', 'message' => 'Token Auth Failed', 'data' => array());
                return response()->json($response, 401);
            }
        } catch (\Exception $e) {
            $response = array('status' => '2', 'message' => $e->getMessage(), 'data' => array());
            return response()->json($response, 401);
        }
                
        $student_id = $request->input("student_id");
        $type = $request->input("type");
        $sub_institute_id = $request->input("sub_institute_id");
        $syear = $request->input("syear");        
        $subject_id = $request->input("subject_id");        

        if($student_id != "" && $sub_institute_id != "" && $syear != "" && $subject_id != "")
        {         

            $quespaperdata = DB::select("SELECT q.*,q.id as question_paper_id
                            FROM tblstudent s
                            INNER JOIN tblstudent_enrollment se ON s.id = se.student_id AND s.sub_institute_id = se.sub_institute_id
                            INNER JOIN question_paper q ON  q.standard_id = se.standard_id AND q.sub_institute_id = se.sub_institute_id AND q.show_hide=1 
                            WHERE s.sub_institute_id = '".$sub_institute_id."' AND s.id = '".$student_id."' AND se.syear = '".$syear."' 
                            AND q.subject_id = '".$subject_id."' AND exam_type='online'
                             ");

            $res['status'] = 1;
            $res['message'] = "Success";
            $res['data'] = $quespaperdata; 
        }else{
            $res['status'] = 0;
            $res['message'] = "Parameter Missing";
        }
        //return  \App\Helpers\is_mobile($type, "implementation", $res);
        return json_encode($res);       
    }


    public function studentQuestionPaperAPI(Request $request) {
       try {
            if (!$this->jwtToken()->validate()) {
                $response = array('status' => '2', 'message' => 'Token Auth Failed', 'data' => array());
                return response()->json($response, 401);
            }
        } catch (\Exception $e) {
            $response = array('status' => '2', 'message' => $e->getMessage(), 'data' => array());
            return response()->json($response, 401);
        }
                
        $student_id = $request->input("student_id");
        $type = $request->input("type");
        $sub_institute_id = $request->input("sub_institute_id");
        $syear = $request->input("syear");        
        $question_paper_id = $request->input("question_paper_id");        

        if($student_id != "" && $sub_institute_id != "" && $syear != "" && $question_paper_id != "")
        {         
            $data['questionpaper_data'] = questionpaperModel::find($question_paper_id)->toArray(); 

            $attempted = DB::SELECT("SELECT count(le.id)+1 as count_attempted
                         FROM lms_online_exam le
                         INNER JOIN question_paper qp ON qp.id = le.question_paper_id AND qp.sub_institute_id = '".$sub_institute_id."' AND qp.syear = '".$syear."'
                         WHERE student_id = '".$student_id."' AND question_paper_id = '".$question_paper_id."'");   

            if($data['questionpaper_data']['open_date'] <= date('Y-m-d h:i:s') && $data['questionpaper_data']['close_date'] >= date('Y-m-d h:i:s') && ($attempted[0]->count_attempted <= $data['questionpaper_data']['attempt_allowed'] || $data['questionpaper_data']['attempt_allowed'] == 'unlimited'))
            {
              
                $question_ids = explode(",",$data['questionpaper_data']['question_ids']);
                
                foreach($question_ids as $key => $val)
                {
                    $question = lmsQuestionMasterModel::where("id",$val)->get()->toArray(); 
                    $finaldata['Question'][$val] = $question[0];

                    $answer_arr = answermasterModel::where(["question_id"=>$val,"sub_institute_id"=>$sub_institute_id])->get()->toArray(); 
                    if(count($answer_arr) > 0)
                    {
                        foreach($answer_arr as $anskey => $ansval)
                        {
                            $finaldata['Question'][$val]['Answer'][] = $ansval;
                        }                       
                    }
                }
//echo "<pre>";
//print_r($finaldata);
//die();
                $res['status'] = 1;
                $res['message'] = "Success";
                $res['data'] = $finaldata; 
            }else{
                $res['status'] = 0;
                $res['message'] = "Not allowed";
            }
        }else{
            $res['status'] = 0;
            $res['message'] = "Parameter Missing";
        }
        //return  \App\Helpers\is_mobile($type, "implementation", $res);
        return json_encode($res);       
    }
    

    public function studentAssessmentAPI(Request $request) {
       try {
            if (!$this->jwtToken()->validate()) {
                $response = array('status' => '2', 'message' => 'Token Auth Failed', 'data' => array());
                return response()->json($response, 401);
            }
        } catch (\Exception $e) {
            $response = array('status' => '2', 'message' => $e->getMessage(), 'data' => array());
            return response()->json($response, 401);
        }
                
        $student_id = $request->input("student_id");
        $type = $request->input("type");
        $sub_institute_id = $request->input("sub_institute_id");
        $syear = $request->input("syear");        
        $question_paper_id = $request->input("question_paper_id");        

        if($student_id != "" && $sub_institute_id != "" && $syear != "" && $question_paper_id != "")
        {         
            // $data['attempted_data'] = lmsOnlineExamModel::where(['student_id'=>$student_id,'question_paper_id'=>$question_paper_id])
            //                             ->orderby('start_time')->get()->toArray();

            $data['attempted_data'] = DB::SELECT("SELECT le.id,le.student_id,le.question_paper_id,le.total_right,le.total_wrong,(le.total_right) as obtain_marks,le.start_time,le.created_at,le.id as online_exam_id,qp.paper_name
                 FROM lms_online_exam le
                 INNER JOIN question_paper qp ON qp.id = le.question_paper_id AND qp.sub_institute_id = '".$sub_institute_id."' AND qp.syear = '".$syear."'
                 WHERE student_id = '".$student_id."' AND question_paper_id = '".$question_paper_id."' ORDER BY start_time");   

            $data['attempted_data'] = json_decode(json_encode($data['attempted_data']), true);                       
                // echo '<pre>';
                // print_r($data['attempted_data']);
                // die;                                     
            foreach($data['attempted_data'] as $key => $val)
            {
                $pdata = DB::select("SELECT *,'100' as total_percentage,
                    round(((a.right_answer*100)/total_question),2) as obtained_percentage from (
                    SELECT lt.parent_id,plt.name as parent_name,lt.id,lt.name,COUNT(mapping_type_id) as total_question,group_concat(e.question_id) as ques_list,
                    sum((case when e.ans_status = 'right' then '1' end)) as right_answer
                    FROM lms_question_mapping l
                    INNER JOIN lms_mapping_type lt ON lt.id = l.mapping_value_id
                    INNER JOIN lms_mapping_type plt ON plt.id = lt.parent_id
                    LEFT JOIN lms_online_exam_answer e on e.question_id = l.questionmaster_id and e.question_paper_id = '".$val['question_paper_id']."' AND 
                    e.student_id = '".$val['student_id']."' and e.online_exam_id = '".$val['id']."'
                    WHERE questionmaster_id IN (
                            SELECT question_id
                            FROM lms_online_exam_answer
                            WHERE question_paper_id = '".$val['question_paper_id']."' AND student_id = '".$val['student_id']."' 
                            AND online_exam_id = '".$val['id']."'
                        ) 
                    GROUP BY mapping_value_id
                    ORDER BY mapping_type_id,mapping_value_id) as a
                ");
                
                $pdata_new =  json_decode(json_encode($pdata), true);                       
                foreach($pdata_new as $pkey => $pval)
                {                   
                    $data['attempted_data'][$key]['PROGRESSBAR_DATA'][$pval['parent_name']][] = $pval;                
                }
            }            
                      
            $res['status'] = 1;
            $res['message'] = "Success";
            $res['data'] = $data; 
        }else{
            $res['status'] = 0;
            $res['message'] = "Parameter Missing";
        }
        //return  \App\Helpers\is_mobile($type, "implementation", $res);
        return json_encode($res);       
    }

    public function studentLeaderBoardAPI(Request $request) {
       try {
            if (!$this->jwtToken()->validate()) {
                $response = array('status' => '2', 'message' => 'Token Auth Failed', 'data' => array());
                return response()->json($response, 401);
            }
        } catch (\Exception $e) {
            $response = array('status' => '2', 'message' => $e->getMessage(), 'data' => array());
            return response()->json($response, 401);
        }
                
        $student_id = $request->input("student_id");
        $type = $request->input("type");
        $sub_institute_id = $request->input("sub_institute_id");
        $syear = $request->input("syear");                    

        if($student_id != "" && $sub_institute_id != "" && $syear != "")
        {         
            $data = $modulewise_points = array();        

            //Get Student Current Standard and Leader board Points
            $studData = DB::select("SELECT l.*,m.icon,se.standard_id,se.section_id
                FROM lb_points AS l
                inner join tblstudent s on l.user_id = s.id and l.sub_institute_id = s.sub_institute_id
                inner join tblstudent_enrollment se on se.student_id = s.id and se.sub_institute_id = s.sub_institute_id
                inner join lb_master m on l.module_name = m.module_name and m.standard_id = se.standard_id
                WHERE l.sub_institute_id = '".$sub_institute_id."' AND l.user_id = '".$student_id."' 
                AND l.syear = '".$syear."'" // AND l.user_profile_id = '".$user_profile_id."' 
                );            

            if(count($studData) > 0)
            {
                $studData = json_decode(json_encode($studData),true);            
               
                $total_points = 0;

                //Make Studen Module wise points array
                foreach($studData as $key => $val)
                {
                    $total_points += $val['points'];
                    $modulewise_points[$val['module_name']]['ICON'] = $val['icon'];
                    $modulewise_points[$val['module_name']]['DATA'][$val['inserted_date']] = $val['points'];
                    $standard_id = $val['standard_id'];
                }

                //Get Class wise Rank and Class data
                //$statement = DB::statement("SET @a=0");
                $classdata = DB::select("
                        SELECT sum(points) as total_points,l.user_id,CONCAT_WS(' ' ,s.first_name,s.middle_name,s.last_name) as student_name
                        FROM lb_points AS l
                        INNER JOIN tblstudent s on l.user_id = s.id and l.sub_institute_id = s.sub_institute_id
                        INNER JOIN tblstudent_enrollment se on se.student_id = s.id and se.sub_institute_id = s.sub_institute_id
                        WHERE l.sub_institute_id = '".$sub_institute_id."' AND se.standard_id = '".$standard_id."' and se.syear = '".$syear."'
                        GROUP BY user_id
                        ORDER BY total_points DESC
                        LIMIT 5"           
                    );  
                $classdata = json_decode(json_encode($classdata),true);        

                $data['1']['type'] = "My Points";
                $data['1']['total_points'] = $total_points;
                $data['1']['icon'] = "mdi:progress-star";
                //$data['modulewise_points'] = $modulewise_points; 
                $data['2']['type'] = "My Leaderboard";
                $data['2']['student_rank'] = "#".(array_search($student_id, array_column($classdata, 'user_id')) + 1)." In Site";
                $data['2']['icon'] = "mdi:office-building-cog";

                $data['3']['type'] = "My Tier";
                $data['3']['my_tier'] = "Bronze";
                $data['3']['icon'] = "mdi:medal";                
                //$data['classdata'] = $classdata;
            }

               
            $res['status'] = 1;
            $res['message'] = "Success";
            $res['data'] = $data; 
        }else{
            $res['status'] = 0;
            $res['message'] = "Parameter Missing";
        }
        //return  \App\Helpers\is_mobile($type, "implementation", $res);
        return json_encode($res);       
    }


    public function studentTransportAPI(Request $request) {
       try {
            if (!$this->jwtToken()->validate()) {
                $response = array('status' => '2', 'message' => 'Token Auth Failed', 'data' => array());
                return response()->json($response, 401);
            }
        } catch (\Exception $e) {
            $response = array('status' => '2', 'message' => $e->getMessage(), 'data' => array());
            return response()->json($response, 401);
        }
                
        $student_id = $request->input("student_id");       
        $sub_institute_id = $request->input("sub_institute_id");
        $syear = $request->input("syear");                    

        if($student_id != "" && $sub_institute_id != "" && $syear != "")
        {                                             
            $data = DB::SELECT("SELECT s.id,s.student_id,
            f_ss.shift_title AS from_shift ,f_v.title AS from_bus ,f_st.stop_name AS from_stop_name,
            t_ss.shift_title AS to_shift ,t_v.title AS to_bus ,t_st.stop_name AS to_stop_name
            FROM transport_map_student s
            INNER JOIN transport_school_shift f_ss on f_ss.id = s.from_shift_id AND f_ss.sub_institute_id = s.sub_institute_id
            INNER JOIN transport_vehicle f_v on f_v.id = s.from_bus_id AND f_v.sub_institute_id = s.sub_institute_id
            INNER JOIN transport_stop f_st on f_st.id = s.from_stop AND f_st.sub_institute_id = s.sub_institute_id
            INNER JOIN transport_school_shift t_ss on t_ss.id = s.to_shift_id AND t_ss.sub_institute_id = s.sub_institute_id
            INNER JOIN transport_vehicle t_v on t_v.id = s.to_bus_id AND t_v.sub_institute_id = s.sub_institute_id
            INNER JOIN transport_stop t_st on t_st.id = s.to_stop AND t_st.sub_institute_id = s.sub_institute_id
            WHERE s.student_id = '".$student_id."' AND s.syear = '".$syear."' AND s.sub_institute_id = '".$sub_institute_id."'");          

            $res['status'] = 1;
            $res['message'] = "Success";
            $res['data'] = $data; 
        }else{
            $res['status'] = 0;
            $res['message'] = "Parameter Missing";
        }
        //return  \App\Helpers\is_mobile($type, "implementation", $res);
        return json_encode($res);       
    }


    public function studentActivityStreamAPI(Request $request) {
       try {
            if (!$this->jwtToken()->validate()) {
                $response = array('status' => '2', 'message' => 'Token Auth Failed', 'data' => array());
                return response()->json($response, 401);
            }
        } catch (\Exception $e) {
            $response = array('status' => '2', 'message' => $e->getMessage(), 'data' => array());
            return response()->json($response, 401);
        }
                
        $student_id = $request->input("student_id");       
        $sub_institute_id = $request->input("sub_institute_id");
        $syear = $request->input("syear");                    

        if($student_id != "" && $sub_institute_id != "" && $syear != "")
        {                                                         
            $stu_data = tblstudentEnrollmentModel::select('standard_id')->where(['student_id'=> $student_id,"syear"=>$syear])->get()->toArray(); 
            
            //START Today's Event Query
            $data['activitystream_today_data'] = DB::select("
                SELECT * FROM (
                SELECT 'Virtual Classroom' AS action,v.id,v.standard_id,room_name AS title,description,
                url,event_date,v.subject_id,s.display_name AS subject_name
                FROM lms_virtual_classroom v
                INNER JOIN sub_std_map s ON s.subject_id = v.subject_id and s.standard_id = v.standard_id
                WHERE event_date = CURRENT_DATE() AND v.sub_institute_id = '".$sub_institute_id."' AND v.standard_id = '".$stu_data[0]['standard_id']."' 

                UNION

                SELECT 'Homework' as action,h.id,h.standard_id,title,description,'',submission_date AS event_date,h.subject_id,s.display_name AS subject_name
                FROM homework h
                INNER JOIN sub_std_map s ON s.subject_id = h.subject_id and s.standard_id = h.standard_id
                WHERE h.sub_institute_id = '".$sub_institute_id."' AND h.standard_id = '".$stu_data[0]['standard_id']."'  AND submission_date = CURRENT_DATE()
                ) AS a
                ORDER BY event_date
                ");
            //END Today's Event Query

            //START Upcoming Event Query
            $data['activitystream_upcoming_data'] = DB::select("
                SELECT * FROM (
                SELECT 'Virtual Classroom' AS action,v.id,v.standard_id,room_name AS title,description,
                url,event_date,v.subject_id,s.display_name AS subject_name
                FROM lms_virtual_classroom v
                INNER JOIN sub_std_map s ON s.subject_id = v.subject_id and s.standard_id = v.standard_id
                WHERE event_date BETWEEN CURRENT_DATE() AND DATE_ADD(CURRENT_DATE(), INTERVAL 7 DAY) AND v.sub_institute_id = '".$sub_institute_id."' AND v.standard_id = '".$stu_data[0]['standard_id']."' 

                UNION

                SELECT 'Homework' as action,h.id,h.standard_id,title,description,'',submission_date AS event_date,h.subject_id,s.display_name as subject_name
                FROM homework h
                INNER JOIN sub_std_map s ON s.subject_id = h.subject_id and s.standard_id = h.standard_id
                WHERE h.sub_institute_id = '".$sub_institute_id."' AND h.standard_id = '".$stu_data[0]['standard_id']."'  
                AND submission_date BETWEEN CURRENT_DATE() AND DATE_ADD(CURRENT_DATE(), INTERVAL 7 DAY)
                ) AS a
                ORDER BY event_date
                ");
            //END Upcoming Event Query

            //START Previous Event Query
            $data['activitystream_previous_data'] = DB::select("
                SELECT * FROM (
                SELECT 'Virtual Classroom' AS action,v.id,v.standard_id,room_name AS title,description,
                url,event_date,v.subject_id,s.display_name AS subject_name
                FROM lms_virtual_classroom v
                INNER JOIN sub_std_map s ON s.subject_id = v.subject_id and s.standard_id = v.standard_id
                WHERE v.sub_institute_id = '".$sub_institute_id."' AND v.standard_id = '".$stu_data[0]['standard_id']."' AND
                event_date BETWEEN DATE_SUB(CURRENT_DATE(), INTERVAL 7 DAY) ANd CURRENT_DATE() 

                UNION

                SELECT 'Homework' as action,h.id,h.standard_id,title,description,'',submission_date AS event_date,h.subject_id,s.display_name as subject_name
                FROM homework h
                INNER JOIN sub_std_map s ON s.subject_id = h.subject_id and s.standard_id = h.standard_id
                WHERE h.sub_institute_id = '".$sub_institute_id."' AND h.standard_id = '".$stu_data[0]['standard_id']."'  
                AND submission_date BETWEEN DATE_SUB(CURRENT_DATE(), INTERVAL 7 DAY) AND CURRENT_DATE()
                ) AS a
                ORDER BY event_date
                ");
            //END Previous Event Query        

            $res['status'] = 1;
            $res['message'] = "Success";
            $res['data'] = $data; 
        }else{
            $res['status'] = 0;
            $res['message'] = "Parameter Missing";
        }
        //return  \App\Helpers\is_mobile($type, "implementation", $res);
        return json_encode($res);       
    }

    public function studentBookListAPI(Request $request) {
       try {
            if (!$this->jwtToken()->validate()) {
                $response = array('status' => '2', 'message' => 'Token Auth Failed', 'data' => array());
                return response()->json($response, 401);
            }
        } catch (\Exception $e) {
            $response = array('status' => '2', 'message' => $e->getMessage(), 'data' => array());
            return response()->json($response, 401);
        }
                
        $student_id = $request->input("student_id");       
        $sub_institute_id = $request->input("sub_institute_id");
        $syear = $request->input("syear");                    

        if($student_id != "" && $sub_institute_id != "" && $syear != "")
        {                                             
            $data = DB::SELECT("SELECT b.id,b.title,b.message,b.date_,
            if(b.file_name = '','',concat('https://".$_SERVER['SERVER_NAME']."/storage/book_list/',b.file_name)) as file_name 
            FROM tblstudent_enrollment se            
            INNER JOIN book_list b on b.standard_id = se.standard_id
            WHERE se.student_id = '".$student_id."' and se.syear = '".$syear."'
            AND se.sub_institute_id = '".$sub_institute_id."'");          

            $res['status'] = 1;
            $res['message'] = "Success";
            $res['data'] = $data; 
        }else{
            $res['status'] = 0;
            $res['message'] = "Parameter Missing";
        }
        //return  \App\Helpers\is_mobile($type, "implementation", $res);
        return json_encode($res);       
    }

    public function studentSyllabusAPI(Request $request) {
       try {
            if (!$this->jwtToken()->validate()) {
                $response = array('status' => '2', 'message' => 'Token Auth Failed', 'data' => array());
                return response()->json($response, 401);
            }
        } catch (\Exception $e) {
            $response = array('status' => '2', 'message' => $e->getMessage(), 'data' => array());
            return response()->json($response, 401);
        }
                
        $student_id = $request->input("student_id");       
        $sub_institute_id = $request->input("sub_institute_id");
        $syear = $request->input("syear");                    

        if($student_id != "" && $sub_institute_id != "" && $syear != "")
        {                                             
            $data = DB::SELECT("SELECT b.id,b.title,b.message,b.date_,
            if(b.file_name = '','',concat('https://".$_SERVER['SERVER_NAME']."/storage/syllabus/',b.file_name)) as file_name 
            FROM tblstudent_enrollment se            
            INNER JOIN syllabus b on b.standard_id = se.standard_id
            WHERE se.student_id = '".$student_id."' and se.syear = '".$syear."'
            AND se.sub_institute_id = '".$sub_institute_id."'");          

            $res['status'] = 1;
            $res['message'] = "Success";
            $res['data'] = $data; 
        }else{
            $res['status'] = 0;
            $res['message'] = "Parameter Missing";
        }
        //return  \App\Helpers\is_mobile($type, "implementation", $res);
        return json_encode($res);       
    }

    public function studentQuestionPaperSaveAPI(Request $request) {
       try {
            if (!$this->jwtToken()->validate()) {
                $response = array('status' => '2', 'message' => 'Token Auth Failed', 'data' => array());
                return response()->json($response, 401);
            }
        } catch (\Exception $e) {
            $response = array('status' => '2', 'message' => $e->getMessage(), 'data' => array());
            return response()->json($response, 401);
        }
        
                
        $student_id = $request->input("student_id");       
        $sub_institute_id = $request->input("sub_institute_id");
        $syear = $request->input("syear");                    
        $question_paper_id = $request->input("question_paper_id");                    
        $question_list = $request->input("question_list");                    
        $given_ans = $request->input("given_ans");                    
        $original_ans = $request->input("original_ans");                    
        $total_marks = $request->input("total_marks");                    

        if($student_id != "" && $sub_institute_id != "" && $syear != "" && $question_paper_id != "" && $question_list != "" && 
           $given_ans != "" && $original_ans != "" && $total_marks != "")
        {       
            $given_ans_array = explode(",",$given_ans);
            $original_ans_array = explode(",",$original_ans);
            $question_list_array = explode(",",$question_list);

            //START Insert into lms_online_exam table
            $correct_ans = $wrong_ans = 0;
            foreach($given_ans_array as $key => $val)
            {
                if($val == $original_ans_array[$key])
                {
                    $correct_ans++;
                }
                else
                {
                    $wrong_ans++;
                }
            }
            $tot_marks = $correct_ans + $wrong_ans;
            $lms_online_data = array(
                "student_id" => $student_id,
                "question_paper_id" => $question_paper_id,
                "total_right" => $correct_ans,
                "total_wrong" => $wrong_ans,
                "obtain_marks" => $tot_marks,//$total_marks 08/06/2022 RAJESH
                "start_time" => now()                
            );   

            lmsOnlineExamModel::insert($lms_online_data);        
            $online_exam_id = DB::getPDO()->lastInsertId();   
            //END Insert into lms_online_exam table


            //START Insert into lms_online_exam_answer table
            foreach($question_list_array as $qkey => $qval)
            {
                $ans_status = "";
                if($given_ans_array[$qkey] == $original_ans_array[$qkey])
                {
                    $ans_status = "right";
                }
                else
                {
                    $ans_status = "wrong";
                }

                $lms_answer_data = array(
                    'question_paper_id' => $question_paper_id,
                    'online_exam_id' => $online_exam_id,                            
                    'student_id' => $student_id,                            
                    'question_id' => $qval,                                                                
                    'answer_id' => $given_ans_array[$qkey],
                    'ans_status' => $ans_status
                );
                lmsOnlineExamAnswerModel::insert($lms_answer_data);      
            }
            //END Insert into lms_online_exam_answer table

            $res['status'] = 1;
            $res['message'] = "Success";
            $res['data'] = null; 
        }else{
            $res['status'] = 0;
            $res['message'] = "Parameter Missing";
        }
        //return  \App\Helpers\is_mobile($type, "implementation", $res);
        return json_encode($res);       
    }
    
    public function studentAssessmentDetailAPI(Request $request) {
       try {
            if (!$this->jwtToken()->validate()) {
                $response = array('status' => '2', 'message' => 'Token Auth Failed', 'data' => array());
                return response()->json($response, 401);
            }
        } catch (\Exception $e) {
            $response = array('status' => '2', 'message' => $e->getMessage(), 'data' => array());
            return response()->json($response, 401);
        }
        
                
        $student_id = $request->input("student_id");       
        $sub_institute_id = $request->input("sub_institute_id");
        $syear = $request->input("syear");                    
        $online_exam_id = $request->input("online_exam_id");                                            

        if($student_id != "" && $sub_institute_id != "" && $syear != "" && $online_exam_id != "")
        {     

            $data['attempted_data'] = DB::SELECT("SELECT le.id,le.student_id,le.question_paper_id,le.total_right,le.total_wrong,(le.total_right+le.total_wrong) as obtain_marks,le.start_time,le.created_at,le.id as online_exam_id,qp.paper_name
                 FROM lms_online_exam le
                 INNER JOIN question_paper qp ON qp.id = le.question_paper_id AND qp.sub_institute_id = '".$sub_institute_id."' AND qp.syear = '".$syear."'
                 WHERE student_id = '".$student_id."' AND le.id = '".$online_exam_id."'");
            $online_answer_data = DB::select("SELECT a.*, GROUP_CONCAT(am.answer) AS actual_answer,q.question_type_id,q.multiple_answer,
                (
                CASE 
                WHEN question_type_id = 2 THEN IF(given_answer is null,'wrong','right') 
                WHEN question_type_id = 1 AND multiple_answer = 0 THEN IF(given_answer=GROUP_CONCAT(am.answer),'right','wrong') 
                WHEN question_type_id = 1 AND multiple_answer = 1 THEN IF(given_answer=GROUP_CONCAT(am.answer),'right','wrong') 
                END
                ) AS right_wrong ,q.question_title
                FROM (
                SELECT loem.question_id,loem.ans_status,IFNULL(loem.narrative_answer, GROUP_CONCAT(lam.answer)) AS given_answer
                FROM lms_online_exam_answer loem
                INNER JOIN answer_master lam ON lam.question_id = loem.question_id AND lam.id = loem.answer_id
                WHERE loem.online_exam_id = '".$online_exam_id."' AND loem.student_id = '".$student_id."'
                GROUP BY loem.question_id) AS a
                INNER JOIN lms_question_master q ON q.id = a.question_id
                LEFT JOIN answer_master am ON a.question_id = am.question_id AND correct_answer = 1
                GROUP BY am.question_id,a.question_id
            ");
            
            foreach($online_answer_data as $key => $val)
            {
                $new = array();
                // $data['online_answer_data'][$val->question_id]['QUESTION_TEXT'] = $val->question_title;
                // $data['online_answer_data'][$val->question_id]['RIGHT_WRONG'] = $val->right_wrong;
                // $data['online_answer_data'][$val->question_id]['ACTUAL_ANSWER'] = $val->actual_answer;
                // $data['online_answer_data'][$val->question_id]['GIVEN_ANSWER'] = $val->given_answer;

                $new[$val->question_id]['QUESTION_TEXT'] = $val->question_title;
                $new[$val->question_id]['RIGHT_WRONG'] = $val->right_wrong;
                $new[$val->question_id]['ACTUAL_ANSWER'] = $val->actual_answer;
                $new[$val->question_id]['GIVEN_ANSWER'] = $val->given_answer;

                $data1[] = (object)$new;        
            }  

            $data['online_answer_data'] = $data1;
            
            $res['status'] = 1;
            $res['message'] = "Success";
            $res['data'] = $data;
        }else{
            $res['status'] = 0;
            $res['message'] = "Parameter Missing";
        }
        //return  \App\Helpers\is_mobile($type, "implementation", $res);
        return json_encode($res);       
    }


    public function lmsCategorywiseSubjectAPI(Request $request) {
       try {
            if (!$this->jwtToken()->validate()) {
                $response = array('status' => '2', 'message' => 'Token Auth Failed', 'data' => array());
                return response()->json($response, 401);
            }
        } catch (\Exception $e) {
            $response = array('status' => '2', 'message' => $e->getMessage(), 'data' => array());
            return response()->json($response, 401);
        }        
                        
        $student_id = $request->input("student_id");        
        $sub_institute_id = $request->input("sub_institute_id");        
        $syear = $request->input("syear");        

        if($sub_institute_id != "" && $student_id != "")
        {                 
            //$content_category = lmsContentCategoryModel::where('status','1')->get()->toArray();       

            $stu_data = tblstudentEnrollmentModel::select('standard_id')
            ->where(['student_id'=> $student_id,'syear'=>$syear,'sub_institute_id'=>$sub_institute_id])->get()->toArray();            

            $extra = " AND 
                find_in_set(
                    s.standard_id,
                    (SELECT concat_ws(',','".$stu_data[0]['standard_id']."',group_concat(id))
                    FROM standard
                    WHERE sub_institute_id = '".$sub_institute_id."' AND grade_id IN (
                    SELECT id
                    FROM academic_section
                    WHERE sub_institute_id = '".$sub_institute_id."' AND title = 'Other'))
                )";

            $arr = DB::select("SELECT STD.name AS standard_name,s.display_name AS subject_name,s.subject_id,STD.id AS standard_id,
                if(s.display_image = '','',concat('https://".$_SERVER['SERVER_NAME']."/storage',s.display_image)) as display_image
                ,
                ifnull(s.subject_category,'My Course') AS content_category
                FROM sub_std_map s
                INNER JOIN standard STD ON STD.id = s.standard_id
                LEFT JOIN chapter_master cp ON cp.subject_id = s.subject_id AND cp.standard_id = s.standard_id
                LEFT JOIN content_master c ON c.subject_id = s.subject_id AND c.standard_id = s.standard_id AND c.sub_institute_id = s.sub_institute_id
                WHERE s.sub_institute_id = '".$sub_institute_id."' AND allow_content = 'Yes'
                 ".$extra."
                GROUP BY s.subject_id,s.standard_id,s.subject_category ORDER BY s.sort_order");
        
            $arr = json_decode(json_encode($arr),true);  
                                                        
            if(count($arr) > 0)
            {       
                foreach($arr as $key => $val)
                {
                    $data[$val['content_category']][] = $val;
                }
            }        
           
            $res['status'] = 1;
            $res['message'] = "Success";
            $res['data'] = $data; 
        }else{
            $res['status'] = 0;
            $res['message'] = "Parameter Missing";
        }
        //return  \App\Helpers\is_mobile($type, "implementation", $res);
        return json_encode($res);       
    }

    public function trizStandardAPI(Request $request) {            
                                                                   
        $data = DB::select("SELECT * FROM standard s 
            INNER JOIN academic_section a ON s.sub_institute_id = a.sub_institute_id AND s.grade_id = a.id
            WHERE s.sub_institute_id = '1' AND a.title != 'OTHERS'
            ");
                
        $res['status'] = 1;
        $res['message'] = "Success";
        $res['data'] = $data; 
                
        return json_encode($res);       
    }

}
