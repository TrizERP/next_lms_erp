<?php
include('excel_upload/db.php');
session_start();

$sub_institute_id = $_REQUEST['sub_institute_id'];

$records_uploaded = "";
if(isset($sub_institute_id) && $sub_institute_id != "")
{
    
    $adminQuery = "SELECT * FROM tblmenumaster WHERE find_in_set(".$sub_institute_id.",sub_institute_id)  and status = 1";
    $adminresult = mysqli_query($cn, $adminQuery) or die(mysqli_error($cn));                               
    if(mysqli_num_rows($adminresult) > 0)
    {
        foreach($adminresult as $akey => $aval)
        {
            $admin_rights[$aval['name'].'_'.$aval['id']] = $aval['id'];
        }
    }

    $teacher_rights = array(
        "Teachers/Users" => 2,
        "Users" => 105,
        "Attendance" => 265,
        "Student Attendance" => 95,
        "Proxy Management" => 103,
        "Teacher Transfer Utility" => 221,
        "I-card" => 266,
        "User I-card" => 159,
        "Student Discipline" => 100,
        "Circular" => 102,
        "Assign Class Teacher" => 98,
        "Teacher Diary" => 97,
        "Lesson Planning" => 96,
        "Student Academics" => 3,
        "Student" => 259,
        "Search/Edit Student" => 80,
        "Add Student" => 81,
        "Bulk Student Update" => 82,
        "HomeWork" => 260,
        "Student Homework" => 90,
        "Homework Submission" => 218,
        "Certificate" => 262,
        "Student Certificate" => 84,
        "Student Medical" => 263,
        "Student Infirmary" => 86,
        "Student Vaccination" => 87,
        "Student Height Weight" => 88,
        "Student Health" => 89,
        "Student Request Parent" => 264,
        "Student Request" => 85,
        "School" => 1,
        "Mobile Apps" => 18,
        "Create Timetable" => 22,
        "Exam Schedule" => 141,
    );

    $student_rights = array(
        "LMS" => 230,
        "My Courses" => 269,
        "All Courses" => 270,
        "LMS Global Mapping" => 275,
        "Exam" => 276,
        "Question Paper" => 242,
        "Activity Stream" => 277,
        "Communication" => 278,
        "Social & Collabrative" => 279,
        "Virtual Classroom" => 280,
        "Portfolio" => 281,
        "Counselling" => 282,
        "Leader Board" => 290                                
    );

    $lmsteacher_rights = array(
        "LMS" => 230,
        "My Courses" => 269,
        "All Courses" => 270,
        "LMS Global Mapping" => 275,
        "Exam" => 276,
        "Question Paper" => 242,
        "Activity Stream" => 277,
        "Communication" => 278,
        "Social & Collabrative" => 279,
        "Virtual Classroom" => 280,
        "Portfolio" => 281,
        "Counselling" => 282,
        "Leader Board" => 290
    );


      $checkQuery = "SELECT * FROM tbluserprofilemaster WHERE sub_institute_id = '".$sub_institute_id."'";
      $result = mysqli_query($cn, $checkQuery) or die(mysqli_error($cn));                         
              
      if(mysqli_num_rows($result) > 0)
      {
          foreach($result as $profilekey => $profileval)
          {       
              $profileval['name'] = str_replace(' ','',$profileval['name']);       
              $arr_name = strtolower($profileval['name'])."_rights";
              $profile_id = $profileval['id'];
              if(isset($$arr_name))
              {                
                  foreach($$arr_name as $key => $val)
                  {
                      $checkQuery = "SELECT * FROM tblgroupwise_rights WHERE menu_id = '".$val."'
                      AND profile_id = '".$profile_id."' AND sub_institute_id = '".$sub_institute_id."'";
                      $result = mysqli_query($cn, $checkQuery) or die(mysqli_error($cn));                         
                      
                      if(mysqli_num_rows($result) == 0)
                      {   
                        $insertQuery = "INSERT INTO tblgroupwise_rights(menu_id,profile_id,can_view,can_add,can_edit,can_delete,sub_institute_id) 
                        values('".$val."',$profile_id,'1','1','1','1','".$sub_institute_id."')";
                        //echo "<br>";
                        $result = mysqli_query($cn, $insertQuery) or die(mysqli_error($cn));                         
                      }
                  }
                  $records_uploaded .= $profileval['name']." Right Assigned<br><br>";
              }
          }  
      }     
 }   
  
  if($records_uploaded !="")
  {
    echo "<h4 style='color:green'>Records Uploaded <hr><br> ".$records_uploaded."</h4>";
  }

?>
