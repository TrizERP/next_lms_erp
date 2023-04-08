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

    $father_dob = $_REQUEST['father_dob'];
    $father_qualification = $_REQUEST['father_qualification'];
    $father_blood_group = $_REQUEST['father_blood_group'];
    $father_occupation = $_REQUEST['father_occupation'];
    $father_organization_name = $_REQUEST['father_organization_name'];
    $father_designation = $_REQUEST['father_designation'];
    $father_office_address = $_REQUEST['father_office_address'];
    $father_email = $_REQUEST['father_email'];
    $father_income = $_REQUEST['father_income'];
    $mother_name = $_REQUEST['mother_name'];
    $mother_dob = $_REQUEST['mother_dob'];
    $mother_qualification = $_REQUEST['mother_qualification'];
    $mother_blood_group = $_REQUEST['mother_blood_group'];
    $mother_occupation = $_REQUEST['mother_occupation'];
    $mother_organization_name = $_REQUEST['mother_organization_name'];
    $mother_designation = $_REQUEST['mother_designation'];
    $mother_office_address = $_REQUEST['mother_office_address'];
    $mother_mobile_no = $_REQUEST['mother_mobile_no'];
    $mother_email = $_REQUEST['mother_email'];
    $mother_income = $_REQUEST['mother_income'];
    $guardian_name = $_REQUEST['guardian_name'];
    $guardian_address = $_REQUEST['guardian_address'];
    $guardian_mobile_no = $_REQUEST['guardian_mobile_no'];
    $guardian_email = $_REQUEST['guardian_email'];
    $guardian_relation_with_child = $_REQUEST['guardian_relation_with_child'];
    $sibling1_name = $_REQUEST['sibling1_name'];
    $sibling2_name = $_REQUEST['sibling2_name'];
    $sibling3_name = $_REQUEST['sibling3_name'];
    $sibling4_name = $_REQUEST['sibling4_name'];  
    $sibling1_dob = !empty($_REQUEST['sibling1_dob']) ? "'".$_REQUEST['sibling1_dob']."'" : 'NULL';
    $sibling2_dob = !empty($_REQUEST['sibling2_dob']) ? "'".$_REQUEST['sibling2_dob']."'" : 'NULL';
    $sibling3_dob = !empty($_REQUEST['sibling3_dob']) ? "'".$_REQUEST['sibling3_dob']."'" : 'NULL';
    $sibling4_dob = !empty($_REQUEST['sibling4_dob']) ? "'".$_REQUEST['sibling3_dob']."'" : 'NULL';
    $sibling1_education = $_REQUEST['sibling1_education'];
    $sibling2_education = $_REQUEST['sibling2_education'];
    $sibling3_education = $_REQUEST['sibling3_education'];
    $sibling4_education = $_REQUEST['sibling4_education'];
    $sibling1_college = $_REQUEST['sibling1_college'];
    $sibling2_college = $_REQUEST['sibling2_college'];
    $sibling3_college = $_REQUEST['sibling3_college'];
    $sibling4_college = $_REQUEST['sibling4_college'];

     $update_sql = "UPDATE `new_admission_inquiry_registration` SET father_dob = '".$father_dob."',father_qualification = '".$father_qualification."',father_blood_group = '".$father_blood_group."',father_occupation = '".$father_occupation."',father_organization_name = '".$father_organization_name."',father_designation = '".$father_designation."',father_office_address = '".$father_office_address."',father_email = '".$father_email."',father_income = '".$father_income."',mother_name = '".$mother_name."',mother_dob = '".$mother_dob."',mother_qualification = '".$mother_qualification."',mother_blood_group = '".$mother_blood_group."',mother_occupation = '".$mother_occupation."',mother_organization_name = '".$mother_organization_name."',mother_designation = '".$mother_designation."',mother_office_address = '".$mother_office_address."',mother_mobile_no = '".$mother_mobile_no."',mother_email = '".$mother_email."',mother_income = '".$mother_income."',guardian_name = '".$guardian_name."',guardian_address = '".$guardian_address."',guardian_mobile_no = '".$guardian_mobile_no."',guardian_email = '".$guardian_email."',guardian_relation_with_child = '".$guardian_relation_with_child."',sibling1_name = '".$sibling1_name."',sibling2_name = '".$sibling2_name."',sibling3_name = '".$sibling3_name."',sibling4_name = '".$sibling4_name."',
     sibling1_dob = $sibling1_dob,
     sibling2_dob = $sibling2_dob,sibling4_dob = $sibling4_dob,sibling3_dob = $sibling3_dob,sibling1_education = '".$sibling1_education."',sibling2_education = '".$sibling2_education."',sibling3_education = '".$sibling3_education."',sibling4_education = '".$sibling4_education."',sibling1_college = '".$sibling1_college."',sibling2_college = '".$sibling2_college."',sibling3_college = '".$sibling3_college."',sibling4_college = '".$sibling4_college."',updated_on = Now() WHERE token = '".$token_no."' ";
    // die;
    $ret = mysqli_query($cn,$update_sql);

    if (mysqli_affected_rows($cn) != 0)
    {
        header('Location: step5.php/?sub_institute_id='.$sub_institute_id);
    }else{
        echo "<br><center><label style='color:red;'>Please fill all data in proper format !!</label></center>";
    }

}

if (!isset($_REQUEST['other_submit'])) 
{

    $token_no = $_SESSION['token_no'];

    $Sql = "SELECT * FROM new_admission_inquiry_registration WHERE token = '" . $token_no . "' "; //die;
    $RET = mysqli_query($cn, $Sql);
    while ($row = mysqli_fetch_array($RET, MYSQLI_ASSOC))
    {
        $father_name = $row['father_name'];
        $father_dob = $row['father_dob'];
        $father_qualification = $row['father_qualification'];
        $father_blood_group = $row['father_blood_group'];
        $father_occupation = $row['father_occupation'];
        $father_organization_name = $row['father_organization_name'];
        $father_designation = $row['father_designation'];
        $father_office_address = $row['father_office_address'];
        $father_email = $row['father_email'];
        $mail = $row['mail'];
        $mobile = $row['mobile'];
        $father_income = $row['father_income'];
        $mother_name = $row['mother_name'];
        $mother_dob = $row['mother_dob'];
        $mother_qualification = $row['mother_qualification'];
        $mother_blood_group = $row['mother_blood_group'];
        $mother_occupation = $row['mother_occupation'];
        $mother_organization_name = $row['mother_organization_name'];
        $mother_designation = $row['mother_designation'];
        $mother_office_address = $row['mother_office_address'];
        $mother_mobile_no = $row['mother_mobile_no'];
        $mother_email = $row['mother_email'];
        $mother_income = $row['mother_income'];
        $guardian_name = $row['guardian_name'];
        $guardian_address = $row['guardian_address'];
        $guardian_mobile_no = $row['guardian_mobile_no'];
        $guardian_email = $row['guardian_email'];
        $guardian_relation_with_child = $row['guardian_relation_with_child'];
        $sibling1_name = $row['sibling1_name'];
        $sibling2_name = $row['sibling2_name'];
        $sibling3_name = $row['sibling3_name'];
        $sibling4_name = $row['sibling4_name'];
        $sibling1_dob = $row['sibling1_dob'];
        $sibling2_dob = $row['sibling2_dob'];
        $sibling3_dob = $row['sibling3_dob'];
        $sibling4_dob = $row['sibling4_dob'];
        $sibling1_education = $row['sibling1_education'];
        $sibling2_education = $row['sibling2_education'];
        $sibling3_education = $row['sibling3_education'];
        $sibling4_education = $row['sibling4_education'];
        $sibling1_college = $row['sibling1_college'];
        $sibling2_college = $row['sibling2_college'];
        $sibling3_college = $row['sibling3_college'];
        $sibling4_college = $row['sibling4_college'];
    }
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
                <form class="contact100-form validate-form" action="step4.php/?sub_institute_id=<?php echo $sub_institute_id; ?>">
                    <input id="sub_institute_id" type="hidden" name="sub_institute_id" value="<?php echo $sub_institute_id; ?>">
                    
                    <div class="validate-form-title">
                        <h3><b>Family Information</b></h3>
                        <h4><b>(To be filled as per ID proof)</b></h4>
                    </div>
                    <ul class="validate-form-list">
                        <li>
                            <label>  </label>
                            <div class="wrap-input100" style="font-weight: bold;">Father</div>
                            <div class="wrap-input100" style="font-weight: bold;">Mother</div>
                        </li>
                        <li>
                            <label>Name</label>    
                            <div class="wrap-input100">
                                <input id="father_name" class="input100" type="text" name="father_name" value="<?php if(isset($father_name)) echo $father_name; ?>" readonly="readonly"> 
                            </div>
                            <div class="wrap-input100 validate-input1">
                                <input id="mother_name" class="input100" type="text" name="mother_name" value="<?php if(isset($mother_name)) echo $mother_name; ?>" required> 
                            </div>
                        </li>
                        <li>
                            <label>Date Of Birth<font color="red">*</font></label>
                            <div class="wrap-input100 validate-input1">
                                <input id="father_dob" class="datepicker input100" required type="date" name="father_dob" autocomplete="off" value="<?php if(isset($father_dob)) echo $father_dob; ?>">
                            </div>
                            <div class="wrap-input100 validate-input1">
                                <input id="mother_dob" class="datepicker input100" required type="date" name="mother_dob" autocomplete="off" value="<?php if(isset($mother_dob)) echo $mother_dob; ?>">
                            </div>
                        </li>
                        <li>
                            <label>Qualification<font color="red">*</font></label>
                            <div class="wrap-input100 validate-input1">
                                <input id="father_qualification" class="input100" type="text" name="father_qualification" value="<?php if(isset($father_qualification)) echo $father_qualification; ?>" required> 
                            </div>
                            <div class="wrap-input100 validate-input1">
                                <input id="mother_qualification" class="input100" type="text" name="mother_qualification" value="<?php if(isset($mother_qualification)) echo $mother_qualification; ?>" required> 
                            </div>
                        </li>
                        <li>
                            <label>Blood Group<font color="red">*</font></label>
                            <div class="wrap-input100 validate-input1">
                                <input id="father_blood_group" class="input100" type="text" name="father_blood_group" value="<?php if(isset($father_blood_group)) echo $father_blood_group; ?>" required> 
                            </div>
                            <div class="wrap-input100 validate-input1">
                                <input id="mother_blood_group" class="input100" type="text" name="mother_blood_group" value="<?php if(isset($mother_blood_group)) echo $mother_blood_group; ?>" required> 
                            </div>
                        </li>
                        <li>
                            <label>Occupation<font color="red">*</font></label>
                            <div class="wrap-input100 validate-input1">
                                <input id="father_occupation" class="input100" type="text" name="father_occupation" value="<?php if(isset($father_occupation)) echo $father_occupation; ?>" required> 
                            </div>
                            <div class="wrap-input100 validate-input1">
                                <input id="mother_occupation" class="input100" type="text" name="mother_occupation" value="<?php if(isset($mother_occupation)) echo $mother_occupation; ?>" required> 
                            </div>
                        </li>
                        <li>
                            <label>Name of the Organization?<font color="red">*</font></label>
                            <div class="wrap-input100 validate-input1">
                                <input id="father_organization_name" class="input100" type="text" name="father_organization_name" value="<?php if(isset($father_organization_name)) echo $father_organization_name; ?>" required> 
                            </div>
                            <div class="wrap-input100 validate-input1">
                                <input id="mother_organization_name" class="input100" type="text" name="mother_organization_name" value="<?php if(isset($mother_organization_name)) echo $mother_organization_name; ?>" required> 
                            </div>
                        </li>
                        <li>
                            <label>Designation<font color="red">*</font></label>
                            <div class="wrap-input100 validate-input1">
                                <input id="father_designation" class="input100" type="text" name="father_designation" value="<?php if(isset($father_designation)) echo $father_designation; ?>" required> 
                            </div>
                            <div class="wrap-input100 validate-input1">
                                <input id="mother_designation" class="input100" type="text" name="mother_designation" value="<?php if(isset($mother_designation)) echo $mother_designation; ?>" required> 
                            </div>
                        </li>
                        <li>
                            <label>Business Office Address<font color="red">*</font></label>
                            <div class="wrap-input100 validate-input1">
                                <input id="father_office_address" class="input100" type="text" name="father_office_address" value="<?php if(isset($father_office_address)) echo $father_office_address; ?>" required> 
                            </div>
                            <div class="wrap-input100 validate-input1">
                                <input id="mother_office_address" class="input100" type="text" name="mother_office_address" value="<?php if(isset($mother_office_address)) echo $mother_office_address; ?>" required> 
                            </div>
                        </li>
                        <li>
                            <label>Mobile No</label>
                            <div class="wrap-input100">
                                <input id="mobile" class="input100" type="text" name="mobile" value="<?php if(isset($mobile)) echo $mobile; ?>" readonly="readonly"> 
                            </div>
                            <div class="wrap-input100 validate-input1">
                                <input id="mother_mobile_no" class="input100" type="text" name="mother_mobile_no" value="<?php if(isset($mother_mobile_no)) echo $mother_mobile_no; ?>" maxlength="10" pattern="\d{10}" required> 
                            </div>
                        </li>
                        <li>
                            <label>Email</label>
                            <div class="wrap-input100">
                                <input id="father_email" class="input100" type="text" name="father_email" value="<?php if(isset($mail)) echo $mail; ?>" readonly="readonly"> 
                            </div>
                            <div class="wrap-input100 validate-input1">
                                <input id="mother_email" class="input100"  name="mother_email" value="<?php if(isset($mother_email)) echo $mother_email; ?>" required> 
                            </div>
                        </li>
                        <li>
                            <label>Gross Annual Income<font color="red">*</font></label>
                            <div class="wrap-input100 validate-input1">
                                <input id="father_income" class="input100" type="text" name="father_income" value="<?php if(isset($father_income)) echo $father_income; ?>" required> 
                            </div>
                            <div class="wrap-input100 validate-input1">
                                <input id="mother_income" class="input100" type="text" name="mother_income" value="<?php if(isset($mother_income)) echo $mother_income; ?>" required> 
                            </div>
                        </li>
                    </ul>
                    <div style="width: 100%;">
                        <div style="width: 20%;float: left;">
                            <!-- <label style="margin-top: 30px;">Name</label> -->
                            <!-- <label style="margin-top: 27px;">Date Of Birth<font color="red">*</font></label> -->
                            <!-- <label style="margin-top: 27px;">Qualification<font color="red">*</font></label> -->
                            <!-- <label style="margin-top: 30px;">Blood Group<font color="red">*</font></label> -->
                            <!-- <label style="margin-top: 27px;">Occupation<font color="red">*</font></label> -->
                            <!-- <label style="margin-top: 31px;">Name of the Organization?<font color="red">*</font></label> -->
                            <!-- <label style="margin-top: 31px;">Designation<font color="red">*</font></label> -->
                            <!-- <label style="margin-top: 27px;">Business Office Address<font color="red">*</font></label> -->
                            <!-- <label style="margin-top: 27px;">Mobile No</label> -->
                            <!-- <label style="margin-top: 27px;">Email</label> -->
                            <!-- <label style="margin-top: 27px;">Gross Annual Income<font color="red">*</font></label> -->
                        </div>
                        <div style="width: 30%;float: left;">
                             <!-- <center><span style="font-weight: bold;">Father</span></center> -->
                            <!-- <div class="wrap-input100">
                                <input id="father_name" class="input100" type="text" name="father_name" value="<?php if(isset($father_name)) echo $father_name; ?>" readonly="readonly"> 
                            </div> -->
                            <!-- <div class="wrap-input100 validate-input">
                                <input id="father_dob" class="datepicker" required type="date" name="father_dob" autocomplete="off" value="<?php if(isset($father_dob)) echo $father_dob; ?>">
                            </div> -->
                            <!-- <div class="wrap-input100 validate-input">
                                <input id="father_qualification" class="input100" type="text" name="father_qualification" value="<?php if(isset($father_qualification)) echo $father_qualification; ?>" > 
                            </div> -->
                            <!-- <div class="wrap-input100 validate-input">
                                <input id="father_blood_group" class="input100" type="text" name="father_blood_group" value="<?php if(isset($father_blood_group)) echo $father_blood_group; ?>" > 
                            </div> -->
                            <!-- <div class="wrap-input100 validate-input">
                                <input id="father_occupation" class="input100" type="text" name="father_occupation" value="<?php if(isset($father_occupation)) echo $father_occupation; ?>" > 
                            </div> -->
                            <!-- <div class="wrap-input100 validate-input">
                                <input id="father_organization_name" class="input100" type="text" name="father_organization_name" value="<?php if(isset($father_organization_name)) echo $father_organization_name; ?>" > 
                            </div> -->
                            <!-- <div class="wrap-input100 validate-input">
                                <input id="father_designation" class="input100" type="text" name="father_designation" value="<?php if(isset($father_designation)) echo $father_designation; ?>" > 
                            </div> -->
                            <!-- <div class="wrap-input100 validate-input">
                                <input id="father_office_address" class="input100" type="text" name="father_office_address" value="<?php if(isset($father_office_address)) echo $father_office_address; ?>" > 
                            </div> -->
                            <!-- <div class="wrap-input100">
                                <input id="mobile" class="input100" type="text" name="mobile" value="<?php if(isset($mobile)) echo $mobile; ?>" readonly="readonly"> 
                            </div> -->
                            <!-- <div class="wrap-input100">
                                <input id="father_email" class="input100" type="text" name="father_email" value="<?php if(isset($mail)) echo $mail; ?>" readonly="readonly"> 
                            </div> -->
                            <!-- <div class="wrap-input100 validate-input">
                                <input id="father_income" class="input100" type="text" name="father_income" value="<?php if(isset($father_income)) echo $father_income; ?>" > 
                            </div> -->
                        </div>
                        <div style="width: 30%;float: right;">
                            <!-- <center><span style="font-weight: bold;">Mother</span></center> -->
                            <!-- <div class="wrap-input100 validate-input">
                                <input id="mother_name" class="input100" type="text" name="mother_name" value="<?php if(isset($mother_name)) echo $mother_name; ?>" > 
                            </div> -->
                            <!-- <div class="wrap-input100 validate-input">
                                <input id="mother_dob" class="datepicker" required type="date" name="mother_dob" autocomplete="off" value="<?php if(isset($mother_dob)) echo $mother_dob; ?>">
                            </div> -->
                            <!-- <div class="wrap-input100 validate-input">
                                <input id="mother_qualification" class="input100" type="text" name="mother_qualification" value="<?php if(isset($mother_qualification)) echo $mother_qualification; ?>" > 
                            </div> -->
                            <!-- <div class="wrap-input100 validate-input">
                                <input id="mother_blood_group" class="input100" type="text" name="mother_blood_group" value="<?php if(isset($mother_blood_group)) echo $mother_blood_group; ?>" > 
                            </div> -->
                            <!-- <div class="wrap-input100 validate-input">
                                <input id="mother_occupation" class="input100" type="text" name="mother_occupation" value="<?php if(isset($mother_occupation)) echo $mother_occupation; ?>" > 
                            </div> -->
                            <!-- <div class="wrap-input100 validate-input">
                                <input id="mother_organization_name" class="input100" type="text" name="mother_organization_name" value="<?php if(isset($mother_organization_name)) echo $mother_organization_name; ?>" > 
                            </div> -->
                            <!-- <div class="wrap-input100 validate-input">
                                <input id="mother_designation" class="input100" type="text" name="mother_designation" value="<?php if(isset($mother_designation)) echo $mother_designation; ?>" > 
                            </div> -->
                            <!-- <div class="wrap-input100 validate-input">
                                <input id="mother_office_address" class="input100" type="text" name="mother_office_address" value="<?php if(isset($mother_office_address)) echo $mother_office_address; ?>" > 
                            </div> -->
                            <!-- <div class="wrap-input100 validate-input">
                                <input id="mother_mobile_no" class="input100" type="text" name="mother_mobile_no" value="<?php if(isset($mother_mobile_no)) echo $mother_mobile_no; ?>" maxlength="10" pattern="\d{10}" > 
                            </div> -->
                            <!-- <div class="wrap-input100 validate-input">
                                <input id="mother_email" class="input100"  name="mother_email" value="<?php if(isset($mother_email)) echo $mother_email; ?>" > 
                            </div> -->
                            <!-- <div class="wrap-input100 validate-input">
                                <input id="mother_income" class="input100" type="text" name="mother_income" value="<?php if(isset($mother_income)) echo $mother_income; ?>" > 
                            </div> -->
                        </div>
                    </div>
                    </br>
                    <div style="width: 100%;">
                        <div class="validate-form-title">
                            <h4 style="font-weight: bold;font-size: 20px;">Local Guardian Address</h4>
                        </div>   
                        <ul class="validate-form-list">
                            <li>
                                <label style="margin-top: 20px;">Full Name</label>
                                <div class="wrap-input100" style="width: 75% !important;">
                                    <input id="guardian_name" class="input100" type="text" name="guardian_name" value="<?php if(isset($guardian_name)) echo $guardian_name; ?>" > 
                                </div>
                            </li>
                            <li>
                                <label style="margin-top: 25px;">Address</label>
                                <div class="wrap-input100" style="width: 75% !important;">
                                    <input id="guardian_address" class="input100" type="text" name="guardian_address" value="<?php if(isset($guardian_address)) echo $guardian_address; ?>" > 
                                </div>
                            </li>
                            <li>
                                <label style="margin-top: 25px;">Tel No/Mobile No</label>
                                <div class="wrap-input100" style="width: 75% !important;">
                                    <input id="guardian_mobile_no" class="input100" type="text" name="guardian_mobile_no" value="<?php if(isset($guardian_mobile_no)) echo $guardian_mobile_no; ?>" maxlength="10" pattern="\d{10}" > 
                                </div>
                            </li>
                            <li>
                                <label style="margin-top: 25px;">Email</label>
                                <div class="wrap-input100" style="width: 75% !important;">
                                    <input id="guardian_email" class="input100" type="email" name="guardian_email" value="<?php if(isset($guardian_email)) echo $guardian_email; ?>" > 
                                </div>
                            </li>
                            <li>
                                <label style="margin-top: 25px;">Relationship with the child</label>
                                <div class="wrap-input100" style="width: 75% !important;">
                                    <input id="guardian_relation_with_child" class="input100" type="text" name="guardian_relation_with_child" value="<?php if(isset($guardian_relation_with_child)) echo $guardian_relation_with_child; ?>" > 
                                </div>
                            </li>
                        </ul>
                         <!-- <div style="width: 20%;float: left;">
                            <label style="margin-top: 20px;">Full Name</label>
                            <label style="margin-top: 25px;">Address</label>
                            <label style="margin-top: 25px;">Tel No/Mobile No</label>
                            <label style="margin-top: 25px;">Email</label>
                            <label style="margin-top: 25px;">Relationship with the child</label>
                         </div> -->
                         <div style="width: 40%;float: left;">
                            <!-- <div class="wrap-input100" style="width: 75% !important;">
                                <input id="guardian_name" class="input100" type="text" name="guardian_name" value="<?php if(isset($guardian_name)) echo $guardian_name; ?>" > 
                            </div> -->
                            <!-- <div class="wrap-input100" style="width: 75% !important;">
                                <input id="guardian_address" class="input100" type="text" name="guardian_address" value="<?php if(isset($guardian_address)) echo $guardian_address; ?>" > 
                            </div> -->
                            <!-- <div class="wrap-input100" style="width: 75% !important;">
                                <input id="guardian_mobile_no" class="input100" type="text" name="guardian_mobile_no" value="<?php if(isset($guardian_mobile_no)) echo $guardian_mobile_no; ?>" maxlength="10" pattern="\d{10}" > 
                            </div> -->
                            <!-- <div class="wrap-input100" style="width: 75% !important;">
                                <input id="guardian_email" class="input100" type="email" name="guardian_email" value="<?php if(isset($guardian_email)) echo $guardian_email; ?>" > 
                            </div> -->
                            <!-- <div class="wrap-input100" style="width: 75% !important;">
                                <input id="guardian_relation_with_child" class="input100" type="text" name="guardian_relation_with_child" value="<?php if(isset($guardian_relation_with_child)) echo $guardian_relation_with_child; ?>" > 
                            </div> -->
                         </div>
                    </div>
                    </br> 
                    <div style="width: 100%;">
                        <div class="validate-form-title">
                            <h4 style="font-weight: bold;font-size: 20px;">Siblings Information (Brothers & Sisters Information)</h4>
                        </div>  
                        <ul class="validate-form-list">
                            <li>
                                <label>First Name</label>
                                <div class="wrap-input100">
                                    <input id="sibling1_name" class="input100" type="text" name="sibling1_name" value="<?php if(isset($sibling1_name)) echo $sibling1_name; ?>" > 
                                </div>
                                <div class="wrap-input100">
                                    <input id="sibling2_name" class="input100" type="text" name="sibling2_name" value="<?php if(isset($sibling2_name)) echo $sibling2_name; ?>" > 
                                </div>
                                <div class="wrap-input100">
                                    <input id="sibling3_name" class="input100" type="text" name="sibling3_name" value="<?php if(isset($sibling3_name)) echo $sibling3_name; ?>" > 
                                </div>
                                <div class="wrap-input100">
                                    <input id="sibling4_name" class="input100" type="text" name="sibling4_name" value="<?php if(isset($sibling4_name)) echo $sibling4_name; ?>" > 
                                </div>
                            </li>
                            <li>
                                <label>Date Of Birth</label>
                                <div class="wrap-input100">
                                    <input id="sibling1_dob" class="input100" type="date" name="sibling1_dob" autocomplete="off" value="<?php if(isset($sibling1_dob)) echo $sibling1_dob; ?>">
                                </div>
                                <div class="wrap-input100">
                                    <input id="sibling2_dob" class="input100" type="date" name="sibling2_dob" autocomplete="off" value="<?php if(isset($sibling2_dob)) echo $sibling2_dob; ?>">
                                </div>
                                <div class="wrap-input100">
                                    <input id="sibling3_dob" class="input100" type="date" name="sibling3_dob" autocomplete="off" value="<?php if(isset($sibling3_dob)) echo $sibling3_dob; ?>">
                                </div>
                                <div class="wrap-input100">
                                    <input id="sibling4_dob" class="input100" type="date" name="sibling4_dob" autocomplete="off" value="<?php if(isset($sibling4_dob)) echo $sibling4_dob; ?>">
                                </div>
                            </li>
                            <li>
                                <label>Education</label>
                                <div class="wrap-input100">
                                    <input id="sibling1_education" class="input100" type="text" name="sibling1_education" value="<?php if(isset($sibling1_education)) echo $sibling1_education; ?>" > 
                                </div>
                                <div class="wrap-input100">
                                    <input id="sibling2_education" class="input100" type="text" name="sibling2_education" value="<?php if(isset($sibling2_education)) echo $sibling2_education; ?>" > 
                                </div>
                                <div class="wrap-input100">
                                    <input id="sibling3_education" class="input100" type="text" name="sibling3_education" value="<?php if(isset($sibling3_education)) echo $sibling3_education; ?>" > 
                                </div>  
                                <div class="wrap-input100">
                                    <input id="sibling4_education" class="input100" type="text" name="sibling4_education" value="<?php if(isset($sibling4_education)) echo $sibling4_education; ?>" > 
                                </div>   
                            </li>
                            <li>
                                <label>School/College</label>
                                <div class="wrap-input100">
                                    <input id="sibling1_college" class="input100" type="text" name="sibling1_college" value="<?php if(isset($sibling1_college)) echo $sibling1_college; ?>" > 
                                </div>
                                <div class="wrap-input100">
                                    <input id="sibling2_college" class="input100" type="text" name="sibling2_college" value="<?php if(isset($sibling2_college)) echo $sibling2_college; ?>" > 
                                </div>
                                <div class="wrap-input100">
                                    <input id="sibling3_college" class="input100" type="text" name="sibling3_college" value="<?php if(isset($sibling3_college)) echo $sibling3_college; ?>" > 
                                </div>
                                <div class="wrap-input100">
                                    <input id="sibling4_college" class="input100" type="text" name="sibling4_college" value="<?php if(isset($sibling4_college)) echo $sibling4_college; ?>" > 
                                </div>
                            </li>
                        </ul>
                        <div style="width: 20%;float: left;margin-right: 7%;">
                            <!-- <label>First Name</label>
                            <div class="wrap-input100">
                                <input id="sibling1_name" class="input100" type="text" name="sibling1_name" value="<?php if(isset($sibling1_name)) echo $sibling1_name; ?>" > 
                            </div>
                            <div class="wrap-input100">
                                <input id="sibling2_name" class="input100" type="text" name="sibling2_name" value="<?php if(isset($sibling2_name)) echo $sibling2_name; ?>" > 
                            </div>
                            <div class="wrap-input100">
                                <input id="sibling3_name" class="input100" type="text" name="sibling3_name" value="<?php if(isset($sibling3_name)) echo $sibling3_name; ?>" > 
                            </div>
                            <div class="wrap-input100">
                                <input id="sibling4_name" class="input100" type="text" name="sibling4_name" value="<?php if(isset($sibling4_name)) echo $sibling4_name; ?>" > 
                            </div> -->
                        </div>
                        <div style="width: 20%;float: left;">
                            <!-- <label>Date Of Birth</label>
                            <div class="wrap-input100">
                                <input id="sibling1_dob" type="date" name="sibling1_dob" autocomplete="off" value="<?php if(isset($sibling1_dob)) echo $sibling1_dob; ?>" style="height: 35px;">
                            </div>
                            <div class="wrap-input100">
                                <input id="sibling2_dob" type="date" name="sibling2_dob" autocomplete="off" value="<?php if(isset($sibling2_dob)) echo $sibling2_dob; ?>" style="height: 35px;">
                            </div>
                            <div class="wrap-input100">
                                <input id="sibling3_dob" type="date" name="sibling3_dob" autocomplete="off" value="<?php if(isset($sibling3_dob)) echo $sibling3_dob; ?>" style="height: 35px;">
                            </div>
                            <div class="wrap-input100">
                                <input id="sibling4_dob" type="date" name="sibling4_dob" autocomplete="off" value="<?php if(isset($sibling4_dob)) echo $sibling4_dob; ?>" style="height: 35px;">
                            </div> -->
                        </div>
                        <div style="width: 20%;float: right;">
                            <!-- <label>Education</label>
                            <div class="wrap-input100">
                                <input id="sibling1_education" class="input100" type="text" name="sibling1_education" value="<?php if(isset($sibling1_education)) echo $sibling1_education; ?>" > 
                            </div>
                            <div class="wrap-input100">
                                <input id="sibling2_education" class="input100" type="text" name="sibling2_education" value="<?php if(isset($sibling2_education)) echo $sibling2_education; ?>" > 
                            </div>
                            <div class="wrap-input100">
                                <input id="sibling3_education" class="input100" type="text" name="sibling3_education" value="<?php if(isset($sibling3_education)) echo $sibling3_education; ?>" > 
                            </div>  
                            <div class="wrap-input100">
                                <input id="sibling4_education" class="input100" type="text" name="sibling4_education" value="<?php if(isset($sibling4_education)) echo $sibling4_education; ?>" > 
                            </div>     -->
                        </div>
                        <div style="width: 20%;float: right;margin-right: 7%;">
                            <!-- <label>School/College</label>
                            <div class="wrap-input100">
                                <input id="sibling1_college" class="input100" type="text" name="sibling1_college" value="<?php if(isset($sibling1_college)) echo $sibling1_college; ?>" > 
                            </div>
                            <div class="wrap-input100">
                                <input id="sibling2_college" class="input100" type="text" name="sibling2_college" value="<?php if(isset($sibling2_college)) echo $sibling2_college; ?>" > 
                            </div>
                            <div class="wrap-input100">
                                <input id="sibling3_college" class="input100" type="text" name="sibling3_college" value="<?php if(isset($sibling3_college)) echo $sibling3_college; ?>" > 
                            </div>
                            <div class="wrap-input100">
                                <input id="sibling4_college" class="input100" type="text" name="sibling4_college" value="<?php if(isset($sibling4_college)) echo $sibling4_college; ?>" > 
                            </div> -->
                        </div>
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
