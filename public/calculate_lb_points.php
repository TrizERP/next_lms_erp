<?php
include('excel_upload/db.php');
session_start();
// ini_set('display_errors', 1);
// ini_set('display_startup_errors', 1);
// error_reporting(E_ALL);

$sub_institute_id = $_REQUEST['sub_institute_id'];
$syear = $_REQUEST['syear'];

$records_uploaded = "";
$total_inserted = 0;

$current_date = date('Y-m-d');

if(isset($sub_institute_id) && $sub_institute_id != "" && isset($syear) && $syear != "")
{    
    $studentQuery = "SELECT s.id,CONCAT_WS(' ',s.first_name,s.middle_name,s.last_name) AS student_name,
    s.enrollment_no,s.gender,s.dob,s.user_profile_id,s.sub_institute_id,
    se.syear,se.grade_id,se.standard_id,se.section_id,st.name as standard_name
    FROM tblstudent s 
    INNER JOIN tblstudent_enrollment se ON se.student_id = s.id AND s.sub_institute_id = se.sub_institute_id
    INNER JOIN standard st ON st.id = se.standard_id AND st.sub_institute_id = s.sub_institute_id
    WHERE s.sub_institute_id = '".$sub_institute_id."' AND se.syear = '".$syear."' AND se.end_date is null";

    $studata = mysqli_query($cn, $studentQuery) or die(mysqli_error($cn));                               
    if(mysqli_num_rows($studata) > 0)
    {
        foreach($studata as $key => $student)
        {        
            $all_points = array();
            $all_points = get_all_points($student['standard_id'],$student['id'],$student['user_profile_id']);  
            
            // echo '=============Start Debugging==============';
            // echo '<pre>';
            // echo "Student Id-> ".$student['id']."<br>";
            // echo "Student Name-> ".$student['student_name']."<br>";
            // print_r($all_points);            
            
            $insert_data = array(
                "user_id" => $student['id'],
                "user_profile_id" => $student['user_profile_id'],
                "sub_institute_id" => $sub_institute_id,
                "syear" => $syear,
                "current_date" => $current_date,
                "ip_address" => $_SERVER['REMOTE_ADDR']             
                );
            insert_lb_points($all_points['student_points'],$insert_data);
        }
    }
	echo "<span style='color:red;'>".$total_inserted." Records Inserted</span>";
}
else{
	echo "<span style='color:red;'>Missing Parameters sub_institute_id and syear.</span>";
}





/* START Get all leaderboard Master entries */
function get_all_points($standard_id,$student_id,$user_profile_id)
{    
    global $sub_institute_id,$cn; 

    $total_points = array();
    $LBQuery = "SELECT * FROM lb_master l WHERE l.sub_institute_id = '".$sub_institute_id."' 
    AND l.standard_id = '".$standard_id."' AND l.status = 1";

    $LBdata = mysqli_query($cn, $LBQuery) or die(mysqli_error($cn));                               
    if(mysqli_num_rows($LBdata) > 0)
    {
        foreach($LBdata as $lkey => $lval)
        {        
            //Calculate Login Points
            if($lval['module_name'] == "login")
            {
                $actual_points['login'] = $lval['points'];
                $total_points[$student_id]['login'] = get_login_points($student_id,$user_profile_id,$lval['points']);
            }
            
            //Calculate Homework Points
            if($lval['module_name'] == "homework")
            {
                $actual_points['homework'] = $lval['points'];
                $total_points[$student_id]['homework'] = get_homework_points($student_id,$user_profile_id,$lval['points']);
            }

            //Calculate Exam Passed Points
            if($lval['module_name'] == "exampass")   
            {
                $actual_points['exampass'] = $lval['points'];
                $total_points[$student_id]['exampass'] = get_exampass_points($student_id,$user_profile_id,$lval['points'],$lval['per_value']);   
            } 

            //Calculate Exam Failed Points
            if($lval['module_name'] == "examfail")   
            {
                $actual_points['examfail'] = $lval['points'];
                $total_points[$student_id]['examfail'] = get_examfail_points($student_id,$user_profile_id,$lval['points'],$lval['per_value']);   
            }                 
        }
        $return_arr['student_points'] = $total_points[$student_id];
        $return_arr['actual_points'] = $actual_points;

        return $return_arr;
    }    
}
/* END Get all leaderboard Master entries */



/* START Check for student login for today */
function get_login_points($student_id,$user_profile_id,$points)
{
    global $sub_institute_id,$cn,$current_date;  

    $loginQuery = "SELECT count(*) as tot FROM access_log_route a
    WHERE a.sub_institute_id = '".$sub_institute_id."' 
    AND a.user_id = '".$student_id."' AND a.profile_id = '".$user_profile_id."'
    AND a.created_at like '%".$current_date."%'";

    $logindata = mysqli_query($cn, $loginQuery) or die(mysqli_error($cn));                                   
    if(mysqli_num_rows($logindata) > 0)
    {    
        $logindata = mysqli_fetch_assoc($logindata);       
        if($logindata['tot'] > 0)
        {
            $return_var =  $points;
        }               
        else
        {
            $return_var =  0;
        }
    }
    return $return_var;
}
/* END Check for student login for today */



/* START Check for student homework for today */
function get_homework_points($student_id,$user_profile_id,$points)
{
    global $sub_institute_id,$cn,$current_date;     

    $homeworkQuery = "SELECT count(*) as total_homework
    FROM homework h
    WHERE h.sub_institute_id = '".$sub_institute_id."' AND student_id = '".$student_id."' 
    AND submission_date = '".$current_date."' AND completion_status = 'Y'";

    $homeworkdata = mysqli_query($cn, $homeworkQuery) or die(mysqli_error($cn));                                   
    if(mysqli_num_rows($homeworkdata) > 0)
    {    
        $homeworkdata = mysqli_fetch_assoc($homeworkdata);       
        if($homeworkdata['total_homework'] > 0)
        {
            $return_var =  ($homeworkdata['total_homework'] * $points);
        }               
        else
        {
            $return_var =  0;
        }
    }
    return $return_var;
}
/* END Check for student homework for today */

/* START Check for student exam-passed for today */
function get_exampass_points($student_id,$user_profile_id,$points,$per_value)
{
    global $sub_institute_id,$cn,$current_date; 

    $examPassQuery = "SELECT count(*) as total_pass_exam FROM (
    SELECT l.*,q.total_marks,format(((100*obtain_marks)/total_marks),2) as per 
    FROM lms_online_exam l 
    INNER JOIN question_paper q on l.question_paper_id = q.id    
    WHERE student_id = '".$student_id."' 
    AND start_time like '%".$current_date."%'
    HAVING per >= ".$per_value."
    ) AS p";

    $examPassData = mysqli_query($cn, $examPassQuery) or die(mysqli_error($cn));                                   
    if(mysqli_num_rows($examPassData) > 0)
    {    
        $examPassData = mysqli_fetch_assoc($examPassData);       
        if($examPassData['total_pass_exam'] > 0)
        {
            $return_var =  ($examPassData['total_pass_exam'] * $points);
        }               
        else
        {
            $return_var =  0;
        }
    }
    return $return_var;  
}
/* END Check for student exam-passed for today */

/* START Check for student exam-fail for today */
function get_examfail_points($student_id,$user_profile_id,$points,$per_value)
{
    global $sub_institute_id,$cn,$current_date; 

    $examFailQuery = "SELECT count(*) as total_fail_exam FROM (
    SELECT l.*,q.total_marks,format(((100*obtain_marks)/total_marks),2) as per 
    FROM lms_online_exam l 
    INNER JOIN question_paper q on l.question_paper_id = q.id    
    WHERE student_id = '".$student_id."' 
    AND start_time like '%".$current_date."%'
    HAVING per < ".$per_value."
    ) AS f";

    $examFailData = mysqli_query($cn, $examFailQuery) or die(mysqli_error($cn));                                   
    if(mysqli_num_rows($examFailData) > 0)
    {    
        $examFailData = mysqli_fetch_assoc($examFailData);       
        if($examFailData['total_fail_exam'] > 0)
        {
            $return_var =  ($examFailData['total_fail_exam'] * $points);
        }               
        else
        {
            $return_var =  0;
        }
    }
    return $return_var;  
}
/* END Check for student exam-fail for today */



/* START insert into lb_points table */
function insert_lb_points($all_points,$insert_data)
{
    global $sub_institute_id,$cn,$total_inserted;

    $student_id = $insert_data['user_id'];
    foreach($all_points as $key => $val)
    {
        if($val != 0)
        {
            $checkQuery = "SELECT * FROM lb_points WHERE user_id = '".$insert_data['user_id']."'
            AND user_profile_id = '".$insert_data['user_profile_id']."' AND sub_institute_id = '".$sub_institute_id."'
            AND module_name = '".$key."' AND inserted_date = '".$insert_data['current_date']."' AND points = $val";                    

            $result = mysqli_query($cn, $checkQuery) or die(mysqli_error($cn));                         

            if(mysqli_num_rows($result) == 0)
            {   
                $deleteQuery = "DELETE FROM lb_points WHERE user_id = '".$insert_data['user_id']."'
                AND user_profile_id = '".$insert_data['user_profile_id']."' AND sub_institute_id = '".$sub_institute_id."'
                AND module_name = '".$key."' AND inserted_date = '".$insert_data['current_date']."'";                    

                $deleteresult = mysqli_query($cn, $deleteQuery) or die(mysqli_error($cn));    

                $insertQuery = "INSERT INTO lb_points(user_id,user_profile_id,sub_institute_id,syear,inserted_date,module_name,points,ip_address,created_on) 
                values(
                    '".$student_id."','".$insert_data['user_profile_id']."','".$insert_data['sub_institute_id']."',
                    '".$insert_data['syear']."','".$insert_data['current_date']."','".$key."','".$val."','".$insert_data['ip_address']."',now()
                    )";                

                $finalresult = mysqli_query($cn, $insertQuery) or die(mysqli_error($cn)); 
                if($finalresult)
                {
                    $total_inserted++;
                }                        
            }
        }
    }
}
/* END insert into lb_points table */
    
?>
