
<noscript><meta http-equiv="refresh" content="1;url=error.html"></noscript>
<?php
session_start();
error_reporting(1);
include('db.php');

$sub_institute_id = $_REQUEST['sub_institute_id'];

$Sql = "SELECT * FROM new_admission_inquiry_registration WHERE mobile = '" . $_SESSION['mobile'] . "' "; //die;
$RET = mysqli_query($cn, $Sql);
while ($row = mysqli_fetch_array($RET, MYSQLI_ASSOC))
{
   $admission_for_child_twins_2nd_time = $row["admission_for_child_twins"];
}

if(isset($_SESSION['mobile']) && $_SESSION['mobile'] != '' && !isset($_REQUEST['submit']) && $admission_for_child_twins_2nd_time == 'Twins')
{
    $Sql = "SELECT * FROM new_admission_inquiry_registration WHERE mobile = '" . $_SESSION['mobile'] . "' "; //die;
    $RET = mysqli_query($cn, $Sql);
    while ($row = mysqli_fetch_array($RET, MYSQLI_ASSOC))
    {
       $display_mobile = $row["mobile"];
       $display_dob = $row["date_of_birth"];
       $display_std = $row["admission_std"];
       $display_age = $row["age"];
       $display_father_adhar = $row["father_adhar"];
       $display_admission_for = $row["admission_for_child_twins"];
    }
}

if (isset($_REQUEST['submit'])) {
//    session_destroy();
 
    $syear = 2021;
    $standard = $_REQUEST['std'];
    $father_adhar = $_REQUEST['father_adhar'];
    $admission_for_child_twins = $_REQUEST['admission_for_child_twins'];
    $dob = $_REQUEST['dob'];
    $dob = date('Y-m-d', strtotime($dob));
    $age = $_REQUEST['age'];
    $mobile = $_REQUEST['mobile'];
    $otp = rand(10000, 99999);
    $otp_text = "Dear Parent, Your OTP is ".$otp." -Shri Muljibhai Mehta School";
    $otp_text = urlencode($otp_text);
    // $final_otp = $otp_text . $otp;

   $sms = "http://49.50.67.32/smsapi/httpapi.jsp?username=MMISVR1&password=abc@123&from=MMISVR&template_id=1207161762431502012&pe_id=1201159351534184675&to=$mobile&text=$otp_text";
   // die;
    $token = "";
    
    $_SESSION['mobile'] = $mobile;
  
    $check_mobile_Sql = "SELECT * FROM new_admission_inquiry_registration WHERE father_adhar = '" . $_REQUEST['father_adhar'] . "' 
	AND date_of_birth = '" . $dob . "'"; //die;
    $mobile_ret = mysqli_query($cn, $check_mobile_Sql);

    $count_result = mysqli_num_rows($mobile_ret);

    if ($count_result != 0 && $admission_for_child_twins_2nd_time != 'Twins') {
        $_SESSION['error'] = "Date of Birth And Father’s Aadhar No. Is Already Register.";
    } else {
       /* if ($standard != '') {

             $sql_sql = "select 
                    max(SUBSTRING_INDEX(token, '-',-1) * 1) tk
                    from new_admission_inquiry_registration_max
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
             (`admission_std`,`syear`,`date_of_birth`,`age`, `mobile`, `otp`, `token`, `created_on`,`father_adhar`,`admission_for_child_twins`) 
		 VALUES('" . $standard . "','".$syear."', '" . $dob . "', '" . $age . "', " . $mobile . ", '" . $otp . "', '" . $token . "',Now(),'" . $father_adhar . "','".$admission_for_child_twins."')";

        $sql = mysqli_query($cn,$query);
        */
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $sms);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $output = curl_exec($ch);

        $_SESSION['token_no'] = $token;
        $_SESSION['standard'] = $standard;
        $_SESSION['father_adhar'] = $father_adhar;
        $_SESSION['admission_for_child_twins'] = $admission_for_child_twins;
        $_SESSION['dob'] = $dob;
        $_SESSION['age'] = $age;
        $_SESSION['otp_for_store'] = $otp;
        $_SESSION['syear'] = $syear;
//         print "<pre>";
// print_r(curl_getinfo($ch));
//         echo '<pre>';
//         print_r($_REQUEST);
//         echo 'out put '.$output ;
//         exit;
        curl_close($ch);
        $_SESSION['otp'] = 1;
        header("Location: otp.php/?sub_institute_id=".$sub_institute_id);
    }
}


if (isset($_SESSION['error'])) {
    echo "<br><center><label style='color:red;'>" . $_SESSION['error'] . "</label></center>";
    UNSET($_SESSION['error']);
}

include('header.php');

?>
<form class="contact100-form validate-form" method="post">
	<div class="w-100">
        <?php 
        if(isset($_SESSION['mobile']) && $_SESSION['mobile'] != '' && !isset($_REQUEST['submit']))
        {

        ?>
        <label>Date Of Birth<font color="red">*</font></label>
        <div class="wrap-input1001 validate-input1">
            <input id="dob" class="datepicker123 input100" required type="text" name="dob" autocomplete="off" onchange="showAge(this.value);" value="<?php if(isset($display_dob)) echo $display_dob; ?>" <?php if(isset($display_dob)) echo 'readonly="readonly"' ?> >
        </div>

        <label>Admission Standard<font color="red">*</font></label>
        <div class="wrap-input1001">
            <select name="standard" id="standard" class="input100" disabled="true" <?php if(isset($display_std)) echo 'readonly'; ?> >
                <option value=''>--Select Standard--</option>
                <option value='CBSE-PH' <?php if($display_std == 'CBSE-PH') echo 'selected'; ?>>CBSE-PH</option>
                <option value='CBSE-NR' <?php if($display_std == 'CBSE-NR') echo 'selected'; ?>>CBSE-NR</option>
            </select>
        </div>
        <input type='hidden' id='std' value="<?php echo $display_std ?>" name="std"> 
        <input type='hidden' id='age' value="<?php echo $display_age ?>" name="age"> 


        <label>Mobile No.<font color="red">*</font><span style="font-size:small">&nbsp;&nbsp;&nbsp;(10 digit)</span></label>
        <div class="wrap-input1001 validate-input1">
            <input id="mobile" class="input100" required name="mobile" type="text" maxlength="10" pattern="\d{10}" value="<?php if(isset($display_mobile)) echo $display_mobile; ?>" <?php if(isset($display_mobile)) echo 'readonly="readonly"' ?> />
          
        </div>

        <label>Father’s Aadhar No.<font color="red">*</font><span style="font-size:small">&nbsp;&nbsp;&nbsp;(12 digit)</span></label>
        <div class="wrap-input1001 validate-input1">
            <input id="father_adhar" class="input100" required name="father_adhar" type="text" maxlength="12" pattern="\d{12}" value="<?php if(isset($display_father_adhar)) echo $display_father_adhar; ?>" <?php if(isset($display_father_adhar)) echo 'readonly="readonly"' ?> />
             
        </div>

        <label>Admission for One Child / Twins<font color="red">*</font></label>
        <div class="wrap-input1001">
            <select name="admission_for_child_twins" id="admission_for_child_twins" class="input100" <?php if(isset($display_admission_for)) echo 'disabled' ?> <?php if(!isset($display_admission_for)) echo 'required' ?>>
                <option value=''>--Select Admission For--</option>
                <option value='One Child'>One Child</option>
                <option value='Twins'>Twins</option>
            </select>
        </div>
        <?php 
        }else{
        ?>

        <label>Date Of Birth<font color="red">*</font></label>
        <div class="wrap-input100 validate-input1">
            <input id="dob" class="datepicker" required type="text" name="dob" onchange="showAge(this.value);" autocomplete="off" >
        </div>

        <label>Admission Standard<font color="red">*</font></label>
        <div class="wrap-input1001">
            <select name="standard" id="standard" class="input100" disabled="true" >
                <option value=''>--Select Standard--</option>
                <option value='CBSE-PH'>CBSE-PH</option>
                <option value='CBSE-NR'>CBSE-NR</option>
            </select>
        </div>
        <input type='hidden' id='std' value="" name="std"> 
        <input type='hidden' id='age' value="" name="age"> 

        <label>Mobile No.<font color="red">*</font><span style="font-size:small">&nbsp;&nbsp;&nbsp;(10 digit)</span></label>
        <div class="wrap-input1001 validate-input1">
            <input id="mobile" class="input100" required name="mobile" type="text" maxlength="10" pattern="\d{10}" />
            <!-- value="<?php if(isset($display_mobile)) echo $display_mobile; ?>" <?php if(isset($display_mobile)) echo 'readonly="readonly"' ?>    -->
        </div>

        <label>Father’s Aadhar No.<font color="red">*</font><span style="font-size:small">&nbsp;&nbsp;&nbsp;(12 digit)</span></label>
        <div class="wrap-input1001 validate-input1">
            <input id="father_adhar" class="input100" required name="father_adhar" type="text" maxlength="12" pattern="\d{12}" />
             <!-- value="<?php if(isset($display_father_adhar)) echo $display_father_adhar; ?>" <?php if(isset($display_father_adhar)) echo 'readonly="readonly"' ?>     -->
        </div>

        <label>Admission for One Child / Twins<font color="red">*</font></label>
        <div class="wrap-input1001">
            <select name="admission_for_child_twins" id="admission_for_child_twins" class="input100">
                <!-- <?php if(isset($display_admission_for)) echo 'disabled' ?> <?php if(!isset($display_admission_for)) echo 'required' ?> -->
                <option value=''>--Select Admission For--</option>
                <option value='One Child'>One Child</option>
                <option value='Twins'>Twins</option>
            </select>
        </div>
        <?php 
        }
        ?>
        <div class="container-contact100-form-btn">
            <a href='re-print.php/?sub_institute_id=<?php echo $sub_institute_id; ?>' class="contact100-form-btn">Re-Print Reciept</a>
            <button type="submit" name="submit" id="submit" class="contact100-form-btn" onclick="return checkform();">Continue</button>
        </div>
    </div>
</form>
<?php include('footer.php'); ?>
