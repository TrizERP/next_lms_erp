<?php
session_start();
error_reporting(1);
include('db.php');

$sub_institute_id = '';
if(isset($_REQUEST['sub_institute_id']) && $_REQUEST['sub_institute_id'] != '')
{
    $sub_institute_id = $_REQUEST['sub_institute_id'];
}

if (isset($_REQUEST['other_submit']) && $_REQUEST['other_submit'] == 'Save & Next' && $_SESSION['token_no'] != '') 
{

    $token_no = $_SESSION['token_no'];

    $parents_declaration = $_REQUEST['parents_declaration'];
    $declare_by_father_mother = $_REQUEST['declare_by_father_mother'];

    $update_sql = "UPDATE `new_admission_inquiry_registration` SET  parents_declaration = '".$parents_declaration."',declare_by_father_mother = '".$declare_by_father_mother."' WHERE token = '".$token_no."' ";
    // die;
    $ret = mysqli_query($cn,$update_sql);
    
    if (isset($_FILES['birth_certificate']['name']))
    {
        $photoName = $_FILES['birth_certificate'];
        if ($photoName['name'] != '') 
        {
            $random_no = rand(10000, 99999);
            $IMG_FILE_NAME = 'birth_certificate_'.$random_no.'_'.$photoName['name'];
            $target_path = '../../storage/app/public/student_document/'. $IMG_FILE_NAME;

            $oldfile_name = get_file_name('birth_certificate',$token_no,$cn);
            if($oldfile_name != '')
            {
                if (file_exists('../../storage/app/public/student_document/'.$oldfile_name)) 
                {
                    unlink('../../storage/app/public/student_document/'.$oldfile_name);
                }
            }

            if ($photoName["error"] > 0) {
                echo "<font color=red><b>Unable to upload. Return Code: " . $photoName["error"] . "</b></font>";
                die;
            } else {
                if ($photoName['type'] == "image/jpeg" || 
                    $photoName['type'] == "image/jpg" || 
                    $photoName['type'] == "image/png" || 
                    $photoName['type'] == "application/pdf") 
                {
                    $tmpFilePath = $photoName["tmp_name"];
                    $destFile = $tmpFilePath;
                } else {
                    $destFile = $photoName["tmp_name"];
                }
                move_uploaded_file($destFile, $target_path);
                $upQuery = "UPDATE new_admission_inquiry_registration SET birth_certificate = '$IMG_FILE_NAME',updated_on = Now() WHERE token = '".$token_no."' ";
                mysqli_query($cn,$upQuery);
            }
        }
    }
    if (isset($_FILES['student_adharcard']['name']))
    {
        $photoName = $_FILES['student_adharcard'];
        if ($photoName['name'] != '') 
        {
            $random_no = rand(10000, 99999);
            $IMG_FILE_NAME = 'student_adharcard_'.$random_no.'_'.$photoName['name'];
            $target_path = '../../storage/app/public/student_document/'. $IMG_FILE_NAME;
            
            $oldfile_name = get_file_name('student_adharcard',$token_no,$cn);
            if($oldfile_name != '')
            {
                if (file_exists('../../storage/app/public/student_document/'.$oldfile_name)) 
                {
                    unlink('../../storage/app/public/student_document/'.$oldfile_name);
                }
            }
            
            if ($photoName["error"] > 0) {
                echo "<font color=red><b>Unable to upload. Return Code: " . $photoName["error"] . "</b></font>";
                die;
            } else {
                if ($photoName['type'] == "image/jpeg" || 
                    $photoName['type'] == "image/jpg" || 
                    $photoName['type'] == "image/png" || 
                    $photoName['type'] == "application/pdf") 
                {
                    $tmpFilePath = $photoName["tmp_name"];
                    $destFile = $tmpFilePath;
                } else {
                    $destFile = $photoName["tmp_name"];
                }
                move_uploaded_file($destFile, $target_path);
                $upQuery = "UPDATE new_admission_inquiry_registration SET student_adharcard = '$IMG_FILE_NAME',updated_on = Now() WHERE token = '".$token_no."' ";
                mysqli_query($cn,$upQuery);
            }
        }
    }
    if (isset($_FILES['student_cast_certificate']['name']))
    {
        $photoName = $_FILES['student_cast_certificate'];
        if ($photoName['name'] != '') 
        {
            $random_no = rand(10000, 99999);
            $IMG_FILE_NAME = 'student_cast_certificate_'.$random_no.'_'.$photoName['name'];
            $target_path = '../../storage/app/public/student_document/'. $IMG_FILE_NAME;
            
            $oldfile_name = get_file_name('student_cast_certificate',$token_no,$cn);
            if($oldfile_name != '')
            {
                if (file_exists('../../storage/app/public/student_document/'.$oldfile_name)) 
                {
                    unlink('../../storage/app/public/student_document/'.$oldfile_name);
                }
            }
            
            if ($photoName["error"] > 0) {
                echo "<font color=red><b>Unable to upload. Return Code: " . $photoName["error"] . "</b></font>";
                die;
            } else {
                if ($photoName['type'] == "image/jpeg" || 
                    $photoName['type'] == "image/jpg" || 
                    $photoName['type'] == "image/png" || 
                    $photoName['type'] == "application/pdf") 
                {
                    $tmpFilePath = $photoName["tmp_name"];
                    $destFile = $tmpFilePath;
                } else {
                    $destFile = $photoName["tmp_name"];
                }
                move_uploaded_file($destFile, $target_path);
                $upQuery = "UPDATE new_admission_inquiry_registration SET student_cast_certificate = '$IMG_FILE_NAME',updated_on = Now() WHERE token = '".$token_no."' ";
                mysqli_query($cn,$upQuery);
            }
        }
    }
    if (isset($_FILES['father_cast_certificate']['name']))
    {
        $photoName = $_FILES['father_cast_certificate'];
        if ($photoName['name'] != '') 
        {
            $random_no = rand(10000, 99999);
            $IMG_FILE_NAME = 'father_cast_certificate_'.$random_no.'_'.$photoName['name'];
            $target_path = '../../storage/app/public/student_document/'. $IMG_FILE_NAME;

            $oldfile_name = get_file_name('father_cast_certificate',$token_no,$cn);
            if($oldfile_name != '')
            {
                if (file_exists('../../storage/app/public/student_document/'.$oldfile_name)) 
                {
                    unlink('../../storage/app/public/student_document/'.$oldfile_name);
                }
            }
            
            if ($photoName["error"] > 0) {
                echo "<font color=red><b>Unable to upload. Return Code: " . $photoName["error"] . "</b></font>";
                die;
            } else {
                if ($photoName['type'] == "image/jpeg" || 
                    $photoName['type'] == "image/jpg" || 
                    $photoName['type'] == "image/png" || 
                    $photoName['type'] == "application/pdf") 
                {
                    $tmpFilePath = $photoName["tmp_name"];
                    $destFile = $tmpFilePath;
                } else {
                    $destFile = $photoName["tmp_name"];
                }
                move_uploaded_file($destFile, $target_path);
                $upQuery = "UPDATE new_admission_inquiry_registration SET father_cast_certificate = '$IMG_FILE_NAME',updated_on = Now() WHERE token = '".$token_no."' ";
                mysqli_query($cn,$upQuery);
            }
        }
    }
    if (isset($_FILES['student_passport_size_photo']['name']))
    {
        $photoName = $_FILES['student_passport_size_photo'];
        if ($photoName['name'] != '') 
        {
            $random_no = rand(10000, 99999);
            $IMG_FILE_NAME = 'student_passport_size_photo_'.$random_no.'_'.$photoName['name'];
            $target_path = '../../storage/app/public/student_document/'. $IMG_FILE_NAME;

            $oldfile_name = get_file_name('student_passport_size_photo',$token_no,$cn);
            if($oldfile_name != '')
            {
                if (file_exists('../../storage/app/public/student_document/'.$oldfile_name)) 
                {
                    unlink('../../storage/app/public/student_document/'.$oldfile_name);
                }
            }

            if ($photoName["error"] > 0) {
                echo "<font color=red><b>Unable to upload. Return Code: " . $photoName["error"] . "</b></font>";
                die;
            } else {
                if ($photoName['type'] == "image/jpeg" || 
                    $photoName['type'] == "image/jpg" || 
                    $photoName['type'] == "image/png" || 
                    $photoName['type'] == "application/pdf") 
                {
                    $tmpFilePath = $photoName["tmp_name"];
                    $destFile = $tmpFilePath;
                } else {
                    $destFile = $photoName["tmp_name"];
                }
                move_uploaded_file($destFile, $target_path);
                $upQuery = "UPDATE new_admission_inquiry_registration SET student_passport_size_photo = '$IMG_FILE_NAME',updated_on = Now() WHERE token = '".$token_no."' ";
                mysqli_query($cn,$upQuery);
            }
        }
    }
    if (isset($_FILES['family_photo']['name']))
    {
        $photoName = $_FILES['family_photo'];

        if ($photoName['name'] != '') 
        {
            $random_no = rand(10000, 99999);
            $IMG_FILE_NAME = 'family_photo_'.$random_no.'_'.$photoName['name'];
            $target_path = '../../storage/app/public/student_document/'. $IMG_FILE_NAME;

            $oldfile_name = get_file_name('family_photo',$token_no,$cn);
            if($oldfile_name != '')
            {
                if (file_exists('../../storage/app/public/student_document/'.$oldfile_name)) 
                {
                    unlink('../../storage/app/public/student_document/'.$oldfile_name);
                }
            }

            if ($photoName["error"] > 0) {
                echo "<font color=red><b>Unable to upload. Return Code: " . $photoName["error"] . "</b></font>";
                die;
            } else {
                if ($photoName['type'] == "image/jpeg" || 
                    $photoName['type'] == "image/jpg" || 
                    $photoName['type'] == "image/png" || 
                    $photoName['type'] == "application/pdf") 
                {
                    $tmpFilePath = $photoName["tmp_name"];
                    $destFile = $tmpFilePath;
                } else {
                    $destFile = $photoName["tmp_name"];
                }
                move_uploaded_file($destFile, $target_path);
                $upQuery = "UPDATE new_admission_inquiry_registration SET family_photo = '$IMG_FILE_NAME',updated_on = Now() WHERE token = '".$token_no."' ";
                mysqli_query($cn,$upQuery);
            }
        }
    }
    if (isset($_FILES['vaccination_record']['name']))
    {
        
        $photoName = $_FILES['vaccination_record'];
        if ($photoName['name'] != '') 
        {
            $random_no = rand(10000, 99999);
            $IMG_FILE_NAME = 'vaccination_record_'.$random_no.'_'.$photoName['name'];
            $target_path = '../../storage/app/public/student_document/'. $IMG_FILE_NAME;

            $oldfile_name = get_file_name('vaccination_record',$token_no,$cn);
            if($oldfile_name != '')
            {
                if (file_exists('../../storage/app/public/student_document/'.$oldfile_name)) 
                {
                    unlink('../../storage/app/public/student_document/'.$oldfile_name);
                }
            }
            
            if ($photoName["error"] > 0) {
                echo "<font color=red><b>Unable to upload. Return Code: " . $photoName["error"] . "</b></font>";
                die;
            } else {
                if ($photoName['type'] == "image/jpeg" || 
                    $photoName['type'] == "image/jpg" || 
                    $photoName['type'] == "image/png" || 
                    $photoName['type'] == "application/pdf") 
                {
                    $tmpFilePath = $photoName["tmp_name"];
                    $destFile = $tmpFilePath;
                } else {
                    $destFile = $photoName["tmp_name"];
                }
                move_uploaded_file($destFile, $target_path);
                // echo '<pre>';
                // print_r($_FILES);
                // print_r($_REQUEST);
                 $upQuery = "UPDATE new_admission_inquiry_registration SET vaccination_record = '$IMG_FILE_NAME',updated_on = Now() WHERE token = '".$token_no."' ";
                // die;
                mysqli_query($cn,$upQuery);
            }
        }
    }
    if (isset($_FILES['medical_examination_report']['name']))
    {
        $photoName = $_FILES['medical_examination_report'];
        if ($photoName['name'] != '') 
        {
            $random_no = rand(10000, 99999);
            $IMG_FILE_NAME = 'medical_examination_report_'.$random_no.'_'.$photoName['name'];
            $target_path = '../../storage/app/public/student_document/'. $IMG_FILE_NAME;

            $oldfile_name = get_file_name('medical_examination_report',$token_no,$cn);
            if($oldfile_name != '')
            {
                if (file_exists('../../storage/app/public/student_document/'.$oldfile_name)) 
                {
                    unlink('../../storage/app/public/student_document/'.$oldfile_name);
                }
            }

            if ($photoName["error"] > 0) {
                echo "<font color=red><b>Unable to upload. Return Code: " . $photoName["error"] . "</b></font>";
                die;
            } else {
                if ($photoName['type'] == "image/jpeg" || 
                    $photoName['type'] == "image/jpg" || 
                    $photoName['type'] == "image/png" || 
                    $photoName['type'] == "application/pdf") 
                {
                    $tmpFilePath = $photoName["tmp_name"];
                    $destFile = $tmpFilePath;
                } else {
                    $destFile = $photoName["tmp_name"];
                }
                move_uploaded_file($destFile, $target_path);
                $upQuery = "UPDATE new_admission_inquiry_registration SET medical_examination_report = '$IMG_FILE_NAME',updated_on = Now() WHERE token = '".$token_no."' ";
                mysqli_query($cn,$upQuery);
            }
        }
    }
    if (isset($_FILES['father_adharcard']['name']))
    {
        $photoName = $_FILES['father_adharcard'];
        if ($photoName['name'] != '') 
        {
            $random_no = rand(10000, 99999);
            $IMG_FILE_NAME = 'father_adharcard_'.$random_no.'_'.$photoName['name'];
            $target_path = '../../storage/app/public/student_document/'. $IMG_FILE_NAME;

            $oldfile_name = get_file_name('father_adharcard',$token_no,$cn);
            if($oldfile_name != '')
            {
                if (file_exists('../../storage/app/public/student_document/'.$oldfile_name)) 
                {
                    unlink('../../storage/app/public/student_document/'.$oldfile_name);
                }
            }

            if ($photoName["error"] > 0) {
                echo "<font color=red><b>Unable to upload. Return Code: " . $photoName["error"] . "</b></font>";
                die;
            } else {
                if ($photoName['type'] == "image/jpeg" || 
                    $photoName['type'] == "image/jpg" || 
                    $photoName['type'] == "image/png" || 
                    $photoName['type'] == "application/pdf") 
                {
                    $tmpFilePath = $photoName["tmp_name"];
                    $destFile = $tmpFilePath;
                } else {
                    $destFile = $photoName["tmp_name"];
                }
                move_uploaded_file($destFile, $target_path);
                $upQuery = "UPDATE new_admission_inquiry_registration SET father_adharcard = '$IMG_FILE_NAME',updated_on = Now() WHERE token = '".$token_no."' ";
                mysqli_query($cn,$upQuery);
            }
        }
    }
    if (isset($_FILES['mother_adharcard']['name']))
    {
        $photoName = $_FILES['mother_adharcard'];
        if ($photoName['name'] != '') 
        {
            $random_no = rand(10000, 99999);
            $IMG_FILE_NAME = 'mother_adharcard_'.$random_no.'_'.$photoName['name'];
            $target_path = '../../storage/app/public/student_document/'. $IMG_FILE_NAME;

            $oldfile_name = get_file_name('mother_adharcard',$token_no,$cn);
            if($oldfile_name != '')
            {
                if (file_exists('../../storage/app/public/student_document/'.$oldfile_name)) 
                {
                    unlink('../../storage/app/public/student_document/'.$oldfile_name);
                }
            }

            if ($photoName["error"] > 0) {
                echo "<font color=red><b>Unable to upload. Return Code: " . $photoName["error"] . "</b></font>";
                die;
            } else {
                if ($photoName['type'] == "image/jpeg" || 
                    $photoName['type'] == "image/jpg" || 
                    $photoName['type'] == "image/png" || 
                    $photoName['type'] == "application/pdf") 
                {
                    $tmpFilePath = $photoName["tmp_name"];
                    $destFile = $tmpFilePath;
                } else {
                    $destFile = $photoName["tmp_name"];
                }
                move_uploaded_file($destFile, $target_path);
                $upQuery = "UPDATE new_admission_inquiry_registration SET mother_adharcard = '$IMG_FILE_NAME',updated_on = Now() WHERE token = '".$token_no."' ";
                mysqli_query($cn,$upQuery);
            }
        }
    }
    if (isset($_FILES['address_proof']['name']))
    {
        $photoName = $_FILES['address_proof'];
        if ($photoName['name'] != '') 
        {
            $random_no = rand(10000, 99999);
            $IMG_FILE_NAME = 'address_proof_'.$random_no.'_'.$photoName['name'];
            $target_path = '../../storage/app/public/student_document/'. $IMG_FILE_NAME;

            $oldfile_name = get_file_name('address_proof',$token_no,$cn);
            if($oldfile_name != '')
            {
                if (file_exists('../../storage/app/public/student_document/'.$oldfile_name)) 
                {
                    unlink('../../storage/app/public/student_document/'.$oldfile_name);
                }
            }

            if ($photoName["error"] > 0) {
                echo "<font color=red><b>Unable to upload. Return Code: " . $photoName["error"] . "</b></font>";
                die;
            } else {
                if ($photoName['type'] == "image/jpeg" || 
                    $photoName['type'] == "image/jpg" || 
                    $photoName['type'] == "image/png" || 
                    $photoName['type'] == "application/pdf") 
                {
                    $tmpFilePath = $photoName["tmp_name"];
                    $destFile = $tmpFilePath;
                } else {
                    $destFile = $photoName["tmp_name"];
                }
                move_uploaded_file($destFile, $target_path);
                $upQuery = "UPDATE new_admission_inquiry_registration SET address_proof = '$IMG_FILE_NAME',updated_on = Now() WHERE token = '".$token_no."' ";
                mysqli_query($cn,$upQuery);
            }
        }
    }
    if (isset($_FILES['father_signature']['name']))
    {
        $photoName = $_FILES['father_signature'];
        if ($photoName['name'] != '') 
        {
            $random_no = rand(10000, 99999);
            $IMG_FILE_NAME = 'father_signature_'.$random_no.'_'.$photoName['name'];
            $target_path = '../../storage/app/public/student_document/'. $IMG_FILE_NAME;

            $oldfile_name = get_file_name('father_signature',$token_no,$cn);
            if($oldfile_name != '')
            {
                if (file_exists('../../storage/app/public/student_document/'.$oldfile_name)) 
                {
                    unlink('../../storage/app/public/student_document/'.$oldfile_name);
                }
            }

            if ($photoName["error"] > 0) {
                echo "<font color=red><b>Unable to upload. Return Code: " . $photoName["error"] . "</b></font>";
                die;
            } else {
                if ($photoName['type'] == "image/jpeg" || 
                    $photoName['type'] == "image/jpg" || 
                    $photoName['type'] == "image/png" || 
                    $photoName['type'] == "application/pdf") 
                {
                    $tmpFilePath = $photoName["tmp_name"];
                    $destFile = $tmpFilePath;
                } else {
                    $destFile = $photoName["tmp_name"];
                }
                move_uploaded_file($destFile, $target_path);
                $upQuery = "UPDATE new_admission_inquiry_registration SET father_signature = '$IMG_FILE_NAME',updated_on = Now() WHERE token = '".$token_no."' ";
                mysqli_query($cn,$upQuery);
            }
        }
    }
    if (isset($_FILES['mother_signature']['name']))
    {
        $photoName = $_FILES['mother_signature'];
        if ($photoName['name'] != '') 
        {
            $random_no = rand(10000, 99999);
            $IMG_FILE_NAME = 'mother_signature_'.$random_no.'_'.$photoName['name'];
            $target_path = '../../storage/app/public/student_document/'. $IMG_FILE_NAME;

            $oldfile_name = get_file_name('mother_signature',$token_no,$cn);
            if($oldfile_name != '')
            {
                if (file_exists('../../storage/app/public/student_document/'.$oldfile_name)) 
                {
                    unlink('../../storage/app/public/student_document/'.$oldfile_name);
                }
            }

            if ($photoName["error"] > 0) {
                echo "<font color=red><b>Unable to upload. Return Code: " . $photoName["error"] . "</b></font>";
                die;
            } else {
                if ($photoName['type'] == "image/jpeg" || 
                    $photoName['type'] == "image/jpg" || 
                    $photoName['type'] == "image/png" || 
                    $photoName['type'] == "application/pdf") 
                {
                    $tmpFilePath = $photoName["tmp_name"];
                    $destFile = $tmpFilePath;
                } else {
                    $destFile = $photoName["tmp_name"];
                }
                move_uploaded_file($destFile, $target_path);
                $upQuery = "UPDATE new_admission_inquiry_registration SET mother_signature = '$IMG_FILE_NAME',updated_on = Now() WHERE token = '".$token_no."' ";
                mysqli_query($cn,$upQuery);
            }
        }
    }
    if (isset($_FILES['any_other_doc']['name']))
    {
        $photoName = $_FILES['any_other_doc'];
        if ($photoName['name'] != '') 
        {
            $random_no = rand(10000, 99999);
            $IMG_FILE_NAME = 'any_other_doc_'.$random_no.'_'.$photoName['name'];
            $target_path = '../../storage/app/public/student_document/'. $IMG_FILE_NAME;

            $oldfile_name = get_file_name('any_other_doc',$token_no,$cn);
            if($oldfile_name != '')
            {
                if (file_exists('../../storage/app/public/student_document/'.$oldfile_name)) 
                {
                    unlink('../../storage/app/public/student_document/'.$oldfile_name);
                }
            }

            if ($photoName["error"] > 0) {
                echo "<font color=red><b>Unable to upload. Return Code: " . $photoName["error"] . "</b></font>";
                die;
            } else {
                if ($photoName['type'] == "image/jpeg" || 
                    $photoName['type'] == "image/jpg" || 
                    $photoName['type'] == "image/png" || 
                    $photoName['type'] == "application/pdf") 
                {
                    $tmpFilePath = $photoName["tmp_name"];
                    $destFile = $tmpFilePath;
                } else {
                    $destFile = $photoName["tmp_name"];
                }
                move_uploaded_file($destFile, $target_path);
                $upQuery = "UPDATE new_admission_inquiry_registration SET any_other_doc = '$IMG_FILE_NAME',updated_on = Now() WHERE token = '".$token_no."' ";
                mysqli_query($cn,$upQuery);
            }
        }
    }
    if (isset($_FILES['other_doc']['name']))
    {
        $photoName = $_FILES['other_doc'];
        if ($photoName['name'] != '') 
        {
            $random_no = rand(10000, 99999);
            $IMG_FILE_NAME = 'other_doc_'.$random_no.'_'.$photoName['name'];
            $target_path = '../../storage/app/public/student_document/'. $IMG_FILE_NAME;

            $oldfile_name = get_file_name('other_doc',$token_no,$cn);
            if($oldfile_name != '')
            {
                if (file_exists('../../storage/app/public/student_document/'.$oldfile_name)) 
                {
                    unlink('../../storage/app/public/student_document/'.$oldfile_name);
                }
            }

            if ($photoName["error"] > 0) {
                echo "<font color=red><b>Unable to upload. Return Code: " . $photoName["error"] . "</b></font>";
                die;
            } else {
                if ($photoName['type'] == "image/jpeg" || 
                    $photoName['type'] == "image/jpg" || 
                    $photoName['type'] == "image/png" || 
                    $photoName['type'] == "application/pdf") 
                {
                    $tmpFilePath = $photoName["tmp_name"];
                    $destFile = $tmpFilePath;
                } else {
                    $destFile = $photoName["tmp_name"];
                }
                move_uploaded_file($destFile, $target_path);
                echo $upQuery = "UPDATE new_admission_inquiry_registration SET other_doc = '$IMG_FILE_NAME',updated_on = Now() WHERE token = '".$token_no."' ";
                mysqli_query($cn,$upQuery);
            }
        }
    }
    // echo 'count '.mysqli_affected_rows($cn);
    // die;
    // if (mysqli_affected_rows($cn) != 0)
    // {
        header('Location: step6.php/?sub_institute_id='.$sub_institute_id);
    // }else{
        // echo "<br><center><label style='color:red;'>Please fill all data in proper format !!</label></center>";
    // }

}

if (!isset($_REQUEST['other_submit'])) 
{

    $token_no = $_SESSION['token_no'];
    $full_path = '../../storage/student_document/';

    $Sql = "SELECT * FROM new_admission_inquiry_registration WHERE token = '" . $token_no . "' "; //die;
    $RET = mysqli_query($cn, $Sql);
    while ($row = mysqli_fetch_array($RET, MYSQLI_ASSOC))
    {
        $parents_declaration = $row['parents_declaration'];
        $declare_by_father_mother = $row['declare_by_father_mother'];

        $birth_certificate = $full_path.$row['birth_certificate'];
        $student_adharcard = $full_path.$row['student_adharcard'];
        $student_cast_certificate = $full_path.$row['student_cast_certificate'];
        $father_cast_certificate = $full_path.$row['father_cast_certificate'];
        $student_passport_size_photo = $full_path.$row['student_passport_size_photo'];
        $family_photo = $full_path.$row['family_photo'];
        $vaccination_record = $full_path.$row['vaccination_record'];
        $medical_examination_report = $full_path.$row['medical_examination_report'];
        $father_adharcard = $full_path.$row['father_adharcard'];
        $mother_adharcard = $full_path.$row['mother_adharcard'];
        $address_proof = $full_path.$row['address_proof'];
        $father_signature = $full_path.$row['father_signature'];
        $mother_signature = $full_path.$row['mother_signature'];
        $any_other_doc = $full_path.$row['any_other_doc'];
        $other_doc = $full_path.$row['other_doc'];

    }
}


function get_file_name($action,$token,$cn)
{
    $sql = 'SELECT '.$action.' from new_admission_inquiry_registration WHERE token = "'.$token.'" ';
    $RET = mysqli_query($cn, $sql); 
    $row = mysqli_fetch_array($RET, MYSQLI_ASSOC);
    return $row[$action];
}

include('header.php');

?>


                <?php
                if ($_SESSION['token_no'] == '') {
                    echo "<br><center><label style='color:red;font-weight: bold;font-size: 20px;padding-bottom: 30px;'>Please follow steps properly !!</label></center>";
                    exit;
                }
                ?>
                <?php 
                if ($_SESSION['token_no'] != '') 
                {
                ?>
                <form class="contact100-form validate-form" action="step5.php/?sub_institute_id=<?php echo $sub_institute_id; ?>" enctype="multipart/form-data">
                    <input id="sub_institute_id" type="hidden" name="sub_institute_id" value="<?php echo $sub_institute_id; ?>">

                    <div class="validate-form-title">
                        <h3><b>Upload Documents</b></h3>
                        <h4><b>(Upload Properly Scanned Copies only)</b></h4>
                    </div>  
                    <ul class="validate-form-list">
                        <li>
                            <label style="margin-top: 25px;">Birth Certificate<font color="red">*</font></label>
                            <div class="wrap-input100">
                                <input id="birth_certificate" class="input100" type="file" name="birth_certificate" value="<?php if(isset($birth_certificate)) echo $birth_certificate; ?>"> 
                            </div>
                            <div>
                                <a href="<?php if(isset($birth_certificate)) echo $birth_certificate; ?>" target="_blank" ><img width="50" height="60" style="border: 1px solid black;" src="<?php if(isset($birth_certificate)) echo $birth_certificate; ?>"/></a>
                            </div>
                        </li>
                        <li>
                            <label style="margin-top: 25px;">Student’s Aadhaar Card<br>(If not available upload Aadhar card application/receipt)<font color="red">*</font></label>
                            <div class="wrap-input100">
                                <input id="student_adharcard" class="input100" type="file" name="student_adharcard" value="<?php if(isset($student_adharcard)) echo $student_adharcard; ?>" > 
                            </div>
                            <div>
                                <a href="<?php if(isset($student_adharcard)) echo $student_adharcard; ?>" target="_blank" ><img width="50" height="60" style="border: 1px solid black;" src="<?php if(isset($student_adharcard)) echo $student_adharcard; ?>"/></a>
                            </div>
                        </li>
                        <li>
                            <label style="margin-top: 25px;">Student’s Caste Certificate (if applicable)</label>
                            <div class="wrap-input100">
                                <input id="student_cast_certificate" class="input100" type="file" name="student_cast_certificate" value="<?php if(isset($student_cast_certificate)) echo $student_cast_certificate; ?>" > 
                            </div>
                            <div>
                                <a href="<?php if(isset($student_cast_certificate)) echo $student_cast_certificate; ?>" target="_blank" ><img width="50" height="60" style="border: 1px solid black;" src="<?php if(isset($student_cast_certificate)) echo $student_cast_certificate; ?>"/></a>
                            </div>
                        </li>
                        <li>    
                            <label style="margin-top: 25px;">Father’s Caste Certificate</label>
                            <div class="wrap-input100">
                                <input id="father_cast_certificate" class="input100" type="file" name="father_cast_certificate" value="<?php if(isset($father_cast_certificate)) echo $father_cast_certificate; ?>" > 
                            </div>
                            <div>
                                <a href="<?php if(isset($father_cast_certificate)) echo $father_cast_certificate; ?>" target="_blank" ><img width="50" height="60" style="border: 1px solid black;" src="<?php if(isset($father_cast_certificate)) echo $father_cast_certificate; ?>"/></a>
                            </div>
                        </li>
                        <li>
                            <label style="margin-top: 25px;">Passport Size Photo Of Student<font color="red">*</font></label>
                            <div class="wrap-input100">
                                <input id="student_passport_size_photo" class="input100" type="file" name="student_passport_size_photo" value="<?php if(isset($student_passport_size_photo)) echo $student_passport_size_photo; ?>" > 
                            </div>
                            <div>
                                <a href="<?php if(isset($student_passport_size_photo)) echo $student_passport_size_photo; ?>" target="_blank" ><img width="50" height="60" style="border: 1px solid black;" src="<?php if(isset($student_passport_size_photo)) echo $student_passport_size_photo; ?>"/></a>
                            </div>
                        </li>
                        <li>
                            <label style="margin-top: 25px;">Post Card Size Family Photograph (Latest)<font color="red">*</font></label>
                            <div class="wrap-input100">
                                <input id="family_photo" class="input100" type="file" name="family_photo" value="<?php if(isset($family_photo)) echo $family_photo; ?>" > 
                            </div>
                            <div>
                                <a href="<?php if(isset($family_photo)) echo $family_photo; ?>" target="_blank" ><img width="50" height="60" style="border: 1px solid black;" src="<?php if(isset($family_photo)) echo $family_photo; ?>"/></a>
                            </div>
                        </li>
                        <li>
                            <label style="margin-top: 25px;">Vaccination Record<font color="red">*</font></label>
                            <div class="wrap-input100">
                                <input id="vaccination_record" class="input100" type="file" name="vaccination_record" value="<?php if(isset($vaccination_record)) echo $vaccination_record; ?>" > 
                            </div>
                            <div>
                                <a href="<?php if(isset($vaccination_record)) echo $vaccination_record; ?>" target="_blank" ><img width="50" height="60" style="border: 1px solid black;" src="<?php if(isset($vaccination_record)) echo $vaccination_record; ?>"/></a>
                            </div>
                        </li>
                        <li>
                            <label style="margin-top: 25px;">Medical Examination Report Certified by your Family Doctor<font color="red">*</font></label>
                            <div class="wrap-input100">
                                <input id="medical_examination_report" class="input100" type="file" name="medical_examination_report" value="<?php if(isset($medical_examination_report)) echo $medical_examination_report; ?>" > 
                            </div>
                            <div>
                                <a href="<?php if(isset($medical_examination_report)) echo $medical_examination_report; ?>" target="_blank" ><img width="50" height="60" style="border: 1px solid black;" src="<?php if(isset($medical_examination_report)) echo $medical_examination_report; ?>"/></a>
                            </div>
                        </li>
                        <li>
                            <label style="margin-top: 25px;">Original Father Aadhaar card<font color="red">*</font></label>
                            <div class="wrap-input100">
                                <input id="father_adharcard" class="input100" type="file" name="father_adharcard" value="<?php if(isset($father_adharcard)) echo $father_adharcard; ?>" > 
                            </div>
                            <div>
                                <a href="<?php if(isset($father_adharcard)) echo $father_adharcard; ?>" target="_blank" ><img width="50" height="60" style="border: 1px solid black;" src="<?php if(isset($father_adharcard)) echo $father_adharcard; ?>"/></a>
                            </div>
                        </li>
                        <li>
                            <label style="margin-top: 25px;">Original Mother Aadhaar cardd<font color="red">*</font></label>
                            <div class="wrap-input100">
                                <input id="mother_adharcard" class="input100" type="file" name="mother_adharcard" value="<?php if(isset($mother_adharcard)) echo $mother_adharcard; ?>" > 
                            </div>
                            <div>
                                <a href="<?php if(isset($mother_adharcard)) echo $mother_adharcard; ?>" target="_blank" ><img width="50" height="60" style="border: 1px solid black;" src="<?php if(isset($mother_adharcard)) echo $mother_adharcard; ?>"/></a>
                            </div>
                        </li>
                        <li>
                            <label style="margin-top: 25px;">Address Proof (Ration Card/Agreement)<font color="red">*</font></label>
                            <div class="wrap-input100">
                                <input id="address_proof" class="input100" type="file" name="address_proof" value="<?php if(isset($address_proof)) echo $address_proof; ?>" > 
                            </div>
                            <div>
                                <a href="<?php if(isset($address_proof)) echo $address_proof; ?>" target="_blank" ><img width="50" height="60" style="border: 1px solid black;" src="<?php if(isset($address_proof)) echo $address_proof; ?>"/></a>
                            </div>
                        </li>
                        <li>
                            <label style="margin-top: 25px;">Father Signature<font color="red">*</font></label>
                            <div class="wrap-input100">
                                <input id="father_signature" class="input100" type="file" name="father_signature" value="<?php if(isset($father_signature)) echo $father_signature; ?>" > 
                            </div>
                            <div>
                                <a href="<?php if(isset($father_signature)) echo $father_signature; ?>" target="_blank" ><img width="50" height="60" style="border: 1px solid black;" src="<?php if(isset($father_signature)) echo $father_signature; ?>"/></a>
                            </div>
                        </li>
                        <li>
                            <label style="margin-top: 25px;">Mother Signature<font color="red">*</font></label>
                            <div class="wrap-input100">
                                <input id="mother_signature" class="input100" type="file" name="mother_signature" value="<?php if(isset($mother_signature)) echo $mother_signature; ?>" > 
                            </div>
                            <div>
                                <a href="<?php if(isset($mother_signature)) echo $mother_signature; ?>" target="_blank" ><img width="50" height="60" style="border: 1px solid black;" src="<?php if(isset($mother_signature)) echo $mother_signature; ?>"/></a>
                            </div>
                        </li>
                        <li>
                            <label style="margin-top: 25px;">Any other document<font></font></label>
                            <div class="wrap-input100">
                                <input id="any_other_doc" class="input100" type="file" name="any_other_doc" value="<?php if(isset($any_other_doc)) echo $any_other_doc; ?>" > 
                            </div>
                            <div>
                                <a href="<?php if(isset($any_other_doc)) echo $any_other_doc; ?>" target="_blank" ><img width="50" height="60" style="border: 1px solid black;" src="<?php if(isset($any_other_doc)) echo $any_other_doc; ?>"/></a>
                            </div>
                        </li>
                        <li>
                            <label style="margin-top: 25px;">Any other document <font></font></label>
                            <div class="wrap-input100">
                                <input id="other_doc" class="input100" type="file" name="other_doc" value="<?php if(isset($other_doc)) echo $other_doc; ?>" > 
                            </div>
                            <div>
                                <a href="<?php if(isset($other_doc)) echo $other_doc; ?>" target="_blank" ><img width="50" height="60" style="border: 1px solid black;" src="<?php if(isset($other_doc)) echo $other_doc; ?>"/></a>
                            </div>
                        </li>
                    </ul>
                    <div style="width: 100%;">
                        <div style="width: 30%;float: left;">
                            <!-- <label style="margin-top: 25px;">Birth Certificate<font color="red">*</font></label>
                            <label style="margin-top: 25px;">Student’s Aadhaar Card<br>(If not available upload Aadhar card application/receipt)<font color="red">*</font></label>
                            <label style="margin-top: 25px;">Student’s Caste Certificate (if applicable)</label>
                            <label style="margin-top: 25px;">Father’s Caste Certificate</label>
                            <label style="margin-top: 25px;">Passport Size Photo Of Student<font color="red">*</font></label>
                            <label style="margin-top: 25px;">Post Card Size Family Photograph (Latest)<font color="red">*</font></label>
                            <label style="margin-top: 25px;">Vaccination Record<font color="red">*</font></label>
                            <label style="margin-top: 25px;">Medical Examination Report Certified by your Family Doctor<font color="red">*</font></label>
                            <label style="margin-top: 25px;">Original Father Aadhaar card<font color="red">*</font></label>
                            <label style="margin-top: 25px;">Original Mother Aadhaar cardd<font color="red">*</font></label>
                            <label style="margin-top: 25px;">Address Proof (Ration Card/Agreement)<font color="red">*</font></label>
                            <label style="margin-top: 25px;">Father Signature<font color="red">*</font></label>
                            <label style="margin-top: 25px;">Mother Signature<font color="red">*</font></label>
                            <label style="margin-top: 25px;">Any other document<font></font></label>
                            <label style="margin-top: 25px;">Any other document <font></font></label> -->
                        </div>
                        <div style="width: 40%;float: left;">
                            <!-- <div class="wrap-input100">
                                <input id="birth_certificate" class="input100" type="file" name="birth_certificate" value="<?php if(isset($birth_certificate)) echo $birth_certificate; ?>"> 
                            </div> -->
                            <!-- <div class="wrap-input100">
                                <input id="student_adharcard" class="input100" type="file" name="student_adharcard" value="<?php if(isset($student_adharcard)) echo $student_adharcard; ?>" > 
                            </div> -->
                            <!-- <div class="wrap-input100">
                                <input id="student_cast_certificate" class="input100" type="file" name="student_cast_certificate" value="<?php if(isset($student_cast_certificate)) echo $student_cast_certificate; ?>" > 
                            </div> -->
                            <!-- <div class="wrap-input100">
                                <input id="father_cast_certificate" class="input100" type="file" name="father_cast_certificate" value="<?php if(isset($father_cast_certificate)) echo $father_cast_certificate; ?>" > 
                            </div> -->
                            <!-- <div class="wrap-input100">
                                <input id="student_passport_size_photo" class="input100" type="file" name="student_passport_size_photo" value="<?php if(isset($student_passport_size_photo)) echo $student_passport_size_photo; ?>" > 
                            </div> -->
                            <!-- <div class="wrap-input100">
                                <input id="family_photo" class="input100" type="file" name="family_photo" value="<?php if(isset($family_photo)) echo $family_photo; ?>" > 
                            </div> -->
                            <!-- <div class="wrap-input100">
                                <input id="vaccination_record" class="input100" type="file" name="vaccination_record" value="<?php if(isset($vaccination_record)) echo $vaccination_record; ?>" > 
                            </div> -->
                            <!-- <div class="wrap-input100">
                                <input id="medical_examination_report" class="input100" type="file" name="medical_examination_report" value="<?php if(isset($medical_examination_report)) echo $medical_examination_report; ?>" > 
                            </div> -->
                            <!-- <div class="wrap-input100">
                                <input id="father_adharcard" class="input100" type="file" name="father_adharcard" value="<?php if(isset($father_adharcard)) echo $father_adharcard; ?>" > 
                            </div> -->
                            <!-- <div class="wrap-input100">
                                <input id="mother_adharcard" class="input100" type="file" name="mother_adharcard" value="<?php if(isset($mother_adharcard)) echo $mother_adharcard; ?>" > 
                            </div> -->
                            <!-- <div class="wrap-input100">
                                <input id="address_proof" class="input100" type="file" name="address_proof" value="<?php if(isset($address_proof)) echo $address_proof; ?>" > 
                            </div> -->
                            <!-- <div class="wrap-input100">
                                <input id="father_signature" class="input100" type="file" name="father_signature" value="<?php if(isset($father_signature)) echo $father_signature; ?>" > 
                            </div> -->
                            <!-- <div class="wrap-input100">
                                <input id="mother_signature" class="input100" type="file" name="mother_signature" value="<?php if(isset($mother_signature)) echo $mother_signature; ?>" > 
                            </div> -->
                            <!-- <div class="wrap-input100">
                                <input id="any_other_doc" class="input100" type="file" name="any_other_doc" value="<?php if(isset($any_other_doc)) echo $any_other_doc; ?>" > 
                            </div> -->
                            <!-- <div class="wrap-input100">
                                <input id="other_doc" class="input100" type="file" name="other_doc" value="<?php if(isset($other_doc)) echo $other_doc; ?>" > 
                            </div> -->
                        </div>
                        <div style="width: 20%;float: right;">
                            <!-- <div>
                                <a href="<?php if(isset($birth_certificate)) echo $birth_certificate; ?>" target="_blank" ><img width="50" height="60" style="border: 1px solid black;" src="<?php if(isset($birth_certificate)) echo $birth_certificate; ?>"/></a>
                            </div> -->
                            <!-- <div>
                                <a href="<?php if(isset($student_adharcard)) echo $student_adharcard; ?>" target="_blank" ><img width="50" height="60" style="border: 1px solid black;" src="<?php if(isset($student_adharcard)) echo $student_adharcard; ?>"/></a>
                            </div> -->
                            <!-- <div>
                                <a href="<?php if(isset($student_cast_certificate)) echo $student_cast_certificate; ?>" target="_blank" ><img width="50" height="60" style="border: 1px solid black;" src="<?php if(isset($student_cast_certificate)) echo $student_cast_certificate; ?>"/></a>
                            </div> -->
                            <!-- <div>
                                <a href="<?php if(isset($father_cast_certificate)) echo $father_cast_certificate; ?>" target="_blank" ><img width="50" height="60" style="border: 1px solid black;" src="<?php if(isset($father_cast_certificate)) echo $father_cast_certificate; ?>"/></a>
                            </div> -->
                            <!-- <div>
                                <a href="<?php if(isset($student_passport_size_photo)) echo $student_passport_size_photo; ?>" target="_blank" ><img width="50" height="60" style="border: 1px solid black;" src="<?php if(isset($student_passport_size_photo)) echo $student_passport_size_photo; ?>"/></a>
                            </div> -->
                            <!-- <div>
                                <a href="<?php if(isset($family_photo)) echo $family_photo; ?>" target="_blank" ><img width="50" height="60" style="border: 1px solid black;" src="<?php if(isset($family_photo)) echo $family_photo; ?>"/></a>
                            </div> -->
                            <!-- <div>
                                <a href="<?php if(isset($vaccination_record)) echo $vaccination_record; ?>" target="_blank" ><img width="50" height="60" style="border: 1px solid black;" src="<?php if(isset($vaccination_record)) echo $vaccination_record; ?>"/></a>
                            </div> -->
                            <!-- <div>
                                <a href="<?php if(isset($medical_examination_report)) echo $medical_examination_report; ?>" target="_blank" ><img width="50" height="60" style="border: 1px solid black;" src="<?php if(isset($medical_examination_report)) echo $medical_examination_report; ?>"/></a>
                            </div> -->
                            <!-- <div>
                                <a href="<?php if(isset($father_adharcard)) echo $father_adharcard; ?>" target="_blank" ><img width="50" height="60" style="border: 1px solid black;" src="<?php if(isset($father_adharcard)) echo $father_adharcard; ?>"/></a>
                            </div> -->
                            <!-- <div>
                                <a href="<?php if(isset($mother_adharcard)) echo $mother_adharcard; ?>" target="_blank" ><img width="50" height="60" style="border: 1px solid black;" src="<?php if(isset($mother_adharcard)) echo $mother_adharcard; ?>"/></a>
                            </div> -->
                            <!-- <div>
                                <a href="<?php if(isset($address_proof)) echo $address_proof; ?>" target="_blank" ><img width="50" height="60" style="border: 1px solid black;" src="<?php if(isset($address_proof)) echo $address_proof; ?>"/></a>
                            </div> -->
                            <!-- <div>
                                <a href="<?php if(isset($father_signature)) echo $father_signature; ?>" target="_blank" ><img width="50" height="60" style="border: 1px solid black;" src="<?php if(isset($father_signature)) echo $father_signature; ?>"/></a>
                            </div> -->
                            <!-- <div>
                                <a href="<?php if(isset($mother_signature)) echo $mother_signature; ?>" target="_blank" ><img width="50" height="60" style="border: 1px solid black;" src="<?php if(isset($mother_signature)) echo $mother_signature; ?>"/></a>
                            </div> -->
                            <!-- <div>
                                <a href="<?php if(isset($any_other_doc)) echo $any_other_doc; ?>" target="_blank" ><img width="50" height="60" style="border: 1px solid black;" src="<?php if(isset($any_other_doc)) echo $any_other_doc; ?>"/></a>
                            </div> -->
                            <!-- <div>
                                <a href="<?php if(isset($other_doc)) echo $other_doc; ?>" target="_blank" ><img width="50" height="60" style="border: 1px solid black;" src="<?php if(isset($other_doc)) echo $other_doc; ?>"/></a>
                            </div> -->
                        </div>
                    </div>   
                    <div style="width: 100%">
                        <!-- <input type="checkbox" id="parents_declaration" name="parents_declaration" <?php if(isset($parents_declaration)) echo 'checked'; ?> required="required" value="Yes">
                        <span style="font-weight: bold;">Parent’s Declaration: I certify that I am the</span>
                        <div class="validate-input">
                            <select name="declare_by_father_mother" id="declare_by_father_mother" >
                                <option value='Father' <?php if($declare_by_father_mother == 'Father' ) echo 'selected'; ?>>Father</option>
                                <option value='Mother' <?php if($declare_by_father_mother == 'Mother' ) echo 'selected'; ?>>Mother</option>
                            </select>
                        </div> -->
                        <div class="validate-input-checkbox">
                            <input type="checkbox" id="parents_declaration" name="parents_declaration" <?php if(isset($parents_declaration)) echo 'checked'; ?> required="required" value="Yes">
                            <label for="parents_declaration" style="font-weight: bold;">Parent’s Declaration: I certify that I am the</label>
                        </div>
                        <div class="validate-input wrap-input100">
                            <select class="input100" name="declare_by_father_mother" id="declare_by_father_mother" >
                                <option value='Father' <?php if($declare_by_father_mother == 'Father' ) echo 'selected'; ?>>Father</option>
                                <option value='Mother' <?php if($declare_by_father_mother == 'Mother' ) echo 'selected'; ?>>Mother</option>
                            </select>
                        </div>
                        <span style="font-weight: bold;">of the child and the information filled and documents that are uploaded are correct to the best of my knowledge. I understand that if at any stage it comes to the school’s notice that any of the information uploaded is incorrect, admission of my child may be cancelled.</span>
                    </div>     
                    <div class="container-contact100-form-btn">
                        <button type="submit" name="other_submit" id="other_submit" value="Save & Next" class="contact100-form-btn">Save & Next</button>
                    </div>
                </form>
                <?php 
            }
                ?>
            </div>
        </div>

        <script src="vendor/jquery/jquery-3.2.1.min.js"></script>
        <script src="vendor/animsition/js/animsition.min.js"></script>
        <script src="vendor/bootstrap/js/popper.js"></script>
        <script src="vendor/bootstrap/js/bootstrap.min.js"></script>
        <script src="vendor/select2/select2.min.js"></script>
        <script src="vitalets-bootstrap-datepicker-c7af15b/js/bootstrap-datepicker.js"></script>
        <script src="vendor/countdowntime/countdowntime.js"></script>
        <script src="js/main.js"></script>
    </body>
</html>
