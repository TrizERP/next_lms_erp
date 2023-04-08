<?php
session_start();
error_reporting(1);
//require("Warehouse.php");
/*$host = "150.129.172.214";
$user_name = "triz_surat";
$passwd = "Triz@2017$123";
$dbname = "mmiserp_cms";
*/
$host = "150.129.172.214";
$username = "triz_erp";
$password = "Triz@2019$04";
$database = "triz_erp_2";

$cn = mysqli_connect($host, $username, $password) or die("Database Not Connected");
mysqli_select_db($cn, $database) or die("database");

if (!isset($_SESSION['check'])) {
    header("Location: index.php");
}
// echo '<pre>';
// print_r($_REQUEST);
// print_r($_SESSION);
//die;

if(isset($_SESSION['mobile']) && $_SESSION['mobile'] != '' && !isset($_REQUEST['btn_submit']))
{
    // echo 'div ';
    $Sql = "SELECT * FROM new_admission_inquiry_registration WHERE mobile = '" . $_SESSION['mobile'] . "' limit 0,1"; //die;
    $RET = mysqli_query($cn, $Sql);
    while ($row = mysqli_fetch_array($RET, MYSQLI_ASSOC))
    {
       $display_father_name = $row["father_name"];
       $display_address = $row["address"];
       $display_mother_adhar = $row["mother_adhar"];
       $display_student_quota = $row["sibling_quota"];
       $display_sibling = $row["sibling_details"];
       $display_mail = $row["mail"];
       $birth_certificate = $row["birth_certificate"];
    }
}


if (isset($_REQUEST['btn_submit'])) 
{
    $child_name = $_REQUEST['child_name'];
    $father_name = $_REQUEST['father_name'];
    $mail = $_REQUEST['mail'];
    $address = $_REQUEST['address'];
    $mother_adhar = $_REQUEST['mother_adhar'];
    $student_quota = $_REQUEST['student_quota'];
    $sibling_details = $_REQUEST['sibling_details'];

   
    $standard = $_SESSION['standard'];
    $father_adhar = $_SESSION['father_adhar'];
    $admission_for_child_twins = $_SESSION['admission_for_child_twins'];
    $dob = $_SESSION['dob'];
    $age = $_SESSION['age'];
    $otp = $_SESSION['otp_for_store'];
    $syear = $_SESSION['syear'];
    $mobile = $_SESSION['mobile'];


    if ($standard != '') 
    {
        $sql_sql = "select 
                max(SUBSTRING_INDEX(token, '-',-1) * 1) tk
                from new_admission_inquiry_registration
                where syear='".$syear."' and admission_std =  '$standard'";
        $ret_token = mysqli_query($cn,$sql_sql);


        while ($row = mysqli_fetch_array($ret_token, MYSQLI_ASSOC)) {
            $tk = $row["tk"];
        }
        $tk = $tk + 1;

        if ($tk < 10) {
            $tk = "0" . $tk;
        }
        if ($standard == 'CBSE-PH') {
            $before_tk = "PG";
        } else {
            $before_tk = "NR";
        }
        $token = $before_tk ."MMIS".$syear."-".$tk;
        // echo $token;
    }

     $query = "INSERT INTO `new_admission_inquiry_registration`
            (`admission_std`,`syear`,`date_of_birth`,`age`, `mobile`, `otp`, `token`, `created_on`,`father_adhar`,`admission_for_child_twins`,`child_name`,`father_name`,`mail`,`address`,`mother_adhar`,`student_quota`,`sibling_details`) 
        VALUES('" . $standard . "','".$syear."', '" . $dob . "', '" . $age . "', " . $mobile . ", '" . $otp . "', '" . $token . "',Now(),'" . $father_adhar . "','".$admission_for_child_twins."','".$child_name."','".$father_name."','".$mail."','".$address."','".$mother_adhar."','".$student_quota."','".$sibling_details."')";
      
    $sql = mysqli_query($cn,$query);
    
    $_SESSION['token_no'] = $token;
    // $query = "UPDATE `new_admission_inquiry_registration` SET
    //         child_name ='$child_name',father_name ='$father_name',mail ='$mail',address ='$address'
    //         ,mother_adhar ='$mother_adhar',student_quota ='$student_quota',sibling_details ='$sibling_details' WHERE mobile=$_SESSION[mobile] AND token = '$_SESSION[token_no]'";
    // $sql = mysqli_query($cn,$query);

    if (isset($_FILES['birth_certificate']['name']))
    {
        $photoName = $_FILES['birth_certificate'];
        if ($photoName['name'] != '') 
        {
            $random_no = rand(10000, 99999);
            $IMG_FILE_NAME = 'birth_certificate_'.$random_no.'_'.$photoName['name'];
            $target_path = 'student_documents/'. $IMG_FILE_NAME;
            if (file_exists($target_path)) {
                unlink($target_path);
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
                $upQuery = "UPDATE new_admission_inquiry_registration SET birth_certificate = '$IMG_FILE_NAME' WHERE mobile='".$_SESSION[mobile]."' AND token = '".$_SESSION[token_no]."' ";
                mysqli_query($cn,$upQuery);
            }
        }
    }

    $sql_sql = "select token
		from new_admission_inquiry_registration
		where mobile=  '$_SESSION[mobile]'";
    $ret_data = mysqli_query($cn,$sql_sql);

    while ($row = mysqli_fetch_array($ret_data, MYSQLI_ASSOC)) {
        $rnum = $row["token"];
    }
    $token_text = "Dear Parents, Your Admission Token No. is ";
    $token_text = urlencode($token_text);
    $token = $rnum;
    $final_token = $token_text . $token;
    $sms = "http://49.50.67.32/smsapi/httpapi.jsp?username=MMISVR&password=abc@123&from=MMISVR&coding=0&to=$_SESSION[mobile]&text=$final_token";
    $ch = curl_init();

    curl_setopt($ch, CURLOPT_URL, $sms);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);


    $output = curl_exec($ch);
    //echo $output ;
    //exit;
    curl_close($ch);

    header("Location: print.php");
    // if($_REQUEST['standard'] == 'Playgroup'){
    // $token = '';
    // }
//    $query = "INSERT INTO `new_admission_inquiry_registration`(`admission_std`, `date_of_birth`, `age`, `mobile`, `otp`, `address`, `mother_mobile`, `father_aadhar_card`, `token`, `created_on`) VALUES('" . $standard . "', '" . $dob . "', '" . $age . "', " . $mobile . ", '" . $otp . "', '" . $address . "', '" . $mother_mobile . "', '" . $aadhar_card . "','1',Now())";
//    $sql = DBQuery($query); // or die(mysql_error());
}
?>


<html lang="en">
    <head>
        <title>Admission Registration</title>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <link rel="icon" type="image/png" href="images/icons/favicon.ico"/>
        <link rel="stylesheet" type="text/css" href="vendor/bootstrap/css/bootstrap.min.css">
        <link rel="stylesheet" type="text/css" href="fonts/font-awesome-4.7.0/css/font-awesome.min.css">
        <link rel="stylesheet" type="text/css" href="fonts/iconic/css/material-design-iconic-font.min.css">
        <link rel="stylesheet" type="text/css" href="fonts/Linearicons-Free-v1.0.0/icon-font.min.css">
        <link rel="stylesheet" type="text/css" href="vendor/animate/animate.css">
        <link rel="stylesheet" type="text/css" href="vendor/css-hamburgers/hamburgers.min.css">
        <link rel="stylesheet" type="text/css" href="vendor/animsition/css/animsition.min.css">
        <link rel="stylesheet" type="text/css" href="vendor/select2/select2.min.css">
        <link rel="stylesheet" type="text/css" href="vendor/daterangepicker/daterangepicker.css">
        <link rel="stylesheet" type="text/css" href="css/util.css">
        <link rel="stylesheet" type="text/css" href="css/main.css">
    </head>
    <body>

        <div class="container-contact100">
            <div class="wrap-contact100">

                <!--<div class="contact100-form-title" style="background-image: url(images/bg-02.jpg);">
                    <img src='MMIS_Logo.png' style="width: 100px;height: 95px;padding-right: 10px;" /><br>
                    <span>Muljibhai Mehta International School - MMIS<br>
                        Gokul Township, Agashi Road Bolinj, Virar (W)</span>
                </div>-->

                <CENTER>
                    <div class="contact100-form-title" style="background-image: url(images/bg-02.jpg);">
                        <table width="100%">
                            <tr>
                                <td>
                                    <img src='MMIS_Logo.png' style="width: 100px;" /><br>
                                </td>
                                <td>
                            <center><span style="font-size: 20px;font-weight: bold;color:white;">Muljibhai Mehta International School</br></span>
                                <span style="color:white;">Gokul Township, Agashi Road, Bolinj, Virar (W)</br></span>
                                <span style="color:white;">(ONLINE ADMISSION FOR A.Y. 2021-2022)</span></center>
                            </td>
                            </tr>
                        </table>
                    </div>
                </CENTER>

                <form class="contact100-form validate-form" method="post" enctype="multipart/form-data">

                    <label>Full Name of the Student<font color="red">*</font></label>
                    <div class="wrap-input100 validate-input">
                        <input id="child_name" class="input100" type="text" name="child_name" >	
                    </div>
                    <label>Father’s Name<font color="red">*</font></label>
                    <div class="wrap-input100 validate-input">
                        <input id="fathe_name" class="input100" type="text" name="father_name" value="<?php if(isset($display_father_name)) echo $display_father_name; ?>" <?php if(isset($display_father_name)) echo 'readonly="readonly"' ?>>	
                    </div>
                    <label>E-mail Id<font color="red">*</font></label>
                    <div class="wrap-input100 validate-input">
                        <input id="mail" class="input100" type="email" name="mail" value="<?php if(isset($display_mail)) echo $display_mail; ?>" <?php if(isset($display_mail)) echo 'readonly="readonly"' ?>>	
                    </div>
                    <label>Residential Address<font color="red">*</font></label>
                    <div class="wrap-input100">
                        <textarea id="address" class="input100" name="address" <?php if(isset($display_address)) echo 'readonly="readonly"' ?>><?php if(isset($display_address)) echo $display_address; ?></textarea>
                    </div>

                    <label>Mother’s Aadhar No.<font color="red">*</font><span style="font-size:small">&nbsp;&nbsp;&nbsp;(12 digit)</span></label>
                    <div class="wrap-input100 validate-input">
                        <input id="mother_adhar" class="input100" name="mother_adhar" type="text" maxlength="12" pattern="\d{12}" value="<?php if(isset($display_mother_adhar)) echo $display_mother_adhar; ?>" <?php if(isset($display_mother_adhar)) echo 'readonly="readonly"' ?> />	
                    </div>
                    <label>Student Quota<font color="red">*</font></label>
                    <div class="wrap-input100 validate-input">
                        <select name="student_quota" id="student_quota" class="input100" onchange="show(value);">
                            <option value=''>--Select Student Quota--</option>
                            <option value='General Quota' <?php if($display_student_quota == 'General Quota' ) echo 'selected'; ?>>General Quota</option>
                            <option value='Sibling Quota' <?php if($display_student_quota == 'Sibling Quota' ) echo 'selected'; ?>>Sibling Quota</option>
                        </select>
                    </div>
                    <label id="labelsibling" style="display: none;">Sibling's Details studying in MMIS<span style="font-size:small">&nbsp;&nbsp;&nbsp;(Enter GR.No)</span></label>
                    <div class="wrap-input100" id="divsibling" style="display: none;">
                        <input id="sibling_details" class="input100" type="text" name="sibling_details" value="<?php if(isset($display_sibling)) echo $display_sibling; ?>" <?php if(isset($display_sibling)) echo 'readonly="readonly"' ?> > 
                    </div>

                    <label>Birth Certificate<font color="red">*</font></label>
                    <div class="wrap-input100">
                        <input id="birth_certificate" class="input100" type="file" name="birth_certificate" value="<?php if(isset($birth_certificate)) echo $birth_certificate; ?>" required> 
                    </div>
                    <div>
                        <a href="<?php if(isset($birth_certificate)) echo $birth_certificate; ?>" target="_blank" >
                            <img width="50" height="60" style="border: 1px solid black;" src="<?php if(isset($birth_certificate)) echo $birth_certificate; ?>"/>
                        </a>
                    </div>

                    <div class="container-contact100-form-btn">
                        <button type="submit" name="btn_submit" id="btn_submit" class="contact100-form-btn">Save</button>
                    </div>
                </form>
            </div>
        </div>



        <script src="vendor/jquery/jquery-3.2.1.min.js"></script>
        <script src="vendor/animsition/js/animsition.min.js"></script>
        <script src="vendor/bootstrap/js/popper.js"></script>
        <script src="vendor/bootstrap/js/bootstrap.min.js"></script>
        <script src="vendor/select2/select2.min.js"></script>
        <script src="vendor/daterangepicker/moment.min.js"></script>
        <script src="vendor/daterangepicker/daterangepicker.js"></script>
        <script src="vendor/countdowntime/countdowntime.js"></script>
        <script src="js/main.js"></script>
         <script type="text/javascript">
            function show(other)
            {
                if(other === 'Sibling Quota')
                {   
                    // alert('yes');
                    document.getElementById("labelsibling").style.display = "block";
                    document.getElementById("divsibling").style.display = "block";
                }else{
                    // alert('no');
                    document.getElementById("labelsibling").style.display = "none";
                    document.getElementById("divsibling").style.display = "none";
                }   
            }
        </script>
    </body>
</html>
