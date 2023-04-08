<?php
session_start();
error_reporting(1);
include('db.php');

$sub_institute_id = '';
if(isset($_REQUEST['sub_institute_id']) && $_REQUEST['sub_institute_id'] != '')
{
    $sub_institute_id = $_REQUEST['sub_institute_id'];
}

if (isset($_REQUEST['submit'])) 
{

    $token_no = $_REQUEST['token_no'];
    $_SESSION['token_no'] = $token_no;

    $get_Sql = 'SELECT * from new_admission_inquiry_registration WHERE token = "' . $token_no . '" AND eligible_status = "Yes" ';
    $get_result = mysqli_query($cn,$get_Sql);

    while ($row = mysqli_fetch_array($get_result, MYSQLI_ASSOC))
    {   
        $std = $row["admission_std"];
        $dob = date('d-m-Y', strtotime($row["date_of_birth"]));
        $mobile = $row["mobile"];
        $address = $row["address"];
        $child_name = $row["child_name"];
        $father_name = $row["father_name"];
        $mail = $row["mail"];
        $father_adhar = $row["father_adhar"];
        $mother_adhar = $row["mother_adhar"];
        $sibling_details = $row["sibling_details"];
        $rnum = $row["token"];
    }

}

if (isset($_REQUEST['other_submit']) && $_REQUEST['other_submit'] == 'Save & Next') 
{

    $token_no = $_SESSION['token_no'];

    $birth_place = $_REQUEST['birth_place'];
    $town = $_REQUEST['town'];
    $district = $_REQUEST['district'];
    $state = $_REQUEST['state'];
    $citizenship = $_REQUEST['citizenship'];
    $age = $_REQUEST['age'];
    $gender = $_REQUEST['gender'];
    $cast = $_REQUEST['cast'];
    $sub_cast = $_REQUEST['sub_cast'];
    $religion = $_REQUEST['religion'];
    $mother_tongue = $_REQUEST['mother_tongue'];
    $language_spoken_at_home = $_REQUEST['language_spoken_at_home'];
    $other_language_spoken = $_REQUEST['other_language_spoken'];
    $backward_class = $_REQUEST['backward_class'];

   $update_sql = "UPDATE `new_admission_inquiry_registration` SET  birth_place = '".$birth_place."',
    town = '".$town."',district = '".$district."',state = '".$state."',citizenship = '".$citizenship."',age = '".$age."',gender = '".$gender."',cast = '".$cast."',sub_cast = '".$sub_cast."',religion = '".$religion."',mother_tongue = '".$mother_tongue."',language_spoken_at_home = '".$language_spoken_at_home."',other_language_spoken = '".$other_language_spoken."',backward_class = '".$backward_class."',updated_on = Now() WHERE token = '".$token_no."' ";

    $ret = mysqli_query($cn,$update_sql);
    
    if (mysqli_affected_rows($cn) != 0)
    {
        header('Location: step2.php/?sub_institute_id='.$sub_institute_id);
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
        $birth_place = $row['birth_place'];
        $town = $row['town'];
        $district = $row['district'];
        $state = $row['state'];
        $citizenship = $row['citizenship'];
        $age = $row['age'];
        $gender = $row['gender'];
        $cast = $row['cast'];
        $sub_cast = $row['sub_cast'];
        $religion = $row['religion'];
        $mother_tongue = $row['mother_tongue'];
        $language_spoken_at_home = $row['language_spoken_at_home'];
        $other_language_spoken = $row['other_language_spoken'];
        $backward_class = $row['backward_class'];
    }
}

include('header.php');

?>


                <?php
                if (isset($_REQUEST['other_submit'])) {
                    echo "<br><center><label style='color:red;'>Data Updated Successfully !!</label></center>";
                }
                ?>
                <form class="contact100-form validate-form" action="step1.php/?sub_institute_id=<?php echo $sub_institute_id; ?>">
                    <input id="sub_institute_id" type="hidden" name="sub_institute_id" value="<?php echo $sub_institute_id; ?>">
                    <label>Token No.</label>
                    <div class="wrap-input100 validate-input1">
                        <input id="token_no" class="input100" type="text" required name="token_no" maxlength="15" value="<?php if(isset($_REQUEST['token_no'])) echo $_REQUEST['token_no']; ?>">  
                    </div>

                    <div class="container-contact100-form-btn">
                        <button type="submit" name="submit" id="submit" class="contact100-form-btn">Submit</button>
                    </div>
                </form>

                <?php

                if(isset($_REQUEST['submit']) && $rnum == ''){
                    echo "<br><center><label style='color:red;font-weight: bold;font-size: 20px;padding-bottom: 30px;'>Sorry, You are not eligible for further admission process !!</label></center>";
                    exit;
                }

                if (isset($_REQUEST['submit']) && $rnum != '') 
                {
                ?>

                <center>
                    <div class="contact100-form" style="padding-top: 0px !important;">
                        <table style="border-collapse: collapse;" width="100%" border="1"> 
                            <tbody>
                                <tr>     
                                    <td style="width: 35%;">Full Name of the Student</td>
                                    <td><?php echo $child_name; ?></td> 
                                </tr> 
                                <tr>
                                    <td>Date Of Birth</td>     
                                    <td><?php echo $dob; ?></td> 
                                </tr> 
                                <tr>     
                                    <td>Standard</td>     
                                    <td><?php echo $std; ?></td> 
                                </tr> 
                                <tr>     
                                    <td>Father’s Name</td>     
                                    <td><?php echo $father_name; ?></td> 
                                </tr> 
                                <tr>     
                                    <td>Father’s Aadhar No.</td>     
                                    <td><?php echo $father_adhar; ?></td> 
                                </tr> 
                                <tr>     
                                    <td>Mother’s Aadhar No.</td>     
                                    <td><?php echo $mother_adhar; ?></td> 
                                </tr> 
                                <tr>     
                                    <td>Mobile No.</td>     
                                    <td><?php echo $mobile; ?></td> 
                                </tr> 
                                <tr>     
                                    <td>E-mail Id</td>     
                                    <td><?php echo $mail; ?></td> 
                                </tr> 
                                <tr>     
                                    <td>Residential Address</td>     
                                    <td><?php echo $address; ?></td> 
                                </tr>
                                <tr>     
                                    <td>Sibling’s Details</td>     
                                    <td><?php echo $sibling_details; ?></td> 
                                </tr>
                            </tbody>
                        </table>  
                    </div>
                </center>

            <?php
            } 
            if (isset($_REQUEST['submit'])) 
            {
            ?>

                <form class="contact100-form validate-form" action="step1.php/?sub_institute_id=<?php echo $sub_institute_id; ?>" enctype="multipart/form-data">
                    <input id="sub_institute_id" type="hidden" name="sub_institute_id" value="<?php echo $sub_institute_id; ?>">

                    <div style="width: 100%;">
                        <center>
                            <h3><b>Student Information</b></h3>
                            <h4><b>(Student information to be filled as per - Birth Certificate / Leaving Certificate)</b></h4>
                        </center>
                        <br>
                        <label>Birth Place<font color="red">*</font></label>
                        <div class="wrap-input100 validate-input1">
                            <input id="birth_place" class="input100" type="text" name="birth_place" value="<?php if(isset($birth_place)) echo $birth_place; ?>" required> 
                        </div>
                        <label>Town/Village<font color="red">*</font></label>
                        <div class="wrap-input100 validate-input1">
                            <input id="town" class="input100" type="text" name="town" value="<?php if(isset($town)) echo $town; ?>" required> 
                        </div>
                        <label>District<font color="red">*</font></label>
                        <div class="wrap-input100 validate-input1">
                            <input id="district" class="input100" type="text" name="district" value="<?php if(isset($district)) echo $district; ?>" required> 
                        </div>
                        <label>State<font color="red">*</font></label>
                        <div class="wrap-input100 validate-input1">
                            <input id="state" class="input100" type="text" name="state" value="<?php if(isset($state)) echo $state; ?>" required> 
                        </div>
                        <label>Citizenship<font color="red">*</font></label>
                        <div class="wrap-input100 validate-input1">
                            <input id="citizenship" class="input100" type="text" name="citizenship" value="<?php if(isset($citizenship)) echo $citizenship; ?>" required> 
                        </div>
                        <label>Age</label>
                        <div class="wrap-input100">
                            <input id="age" class="input100" type="text" name="age" value="<?php if(isset($age)) echo $age; ?>" readonly=readonly> 
                        </div>                       
                        <label>Gender<font color="red">*</font></label>
                        <div class="wrap-input100">
                            <select name="gender" id="gender" class="input100" required>
                                <option value='M' <?php if($gender == 'M' ) echo 'selected'; ?> >Male</option>
                                <option value='F' <?php if($gender == 'F' ) echo 'selected'; ?> >Female</option>
                            </select>
                        </div>
                        <label>Caste<font color="red">*</font></label>
                        <div class="wrap-input100 validate-input1">
                            <input id="cast" class="input100" type="text" name="cast" value="<?php if(isset($cast)) echo $cast; ?>" required > 
                        </div>
                        <label>Sub Caste<font color="red">*</font></label>
                        <div class="wrap-input100 validate-input1">
                            <input id="sub_cast" class="input100" type="text" name="sub_cast" value="<?php if(isset($sub_cast)) echo $sub_cast; ?>" required> 
                        </div>
                        <label>Religion<font color="red">*</font></label>
                        <div class="wrap-input100 validate-input1">
                            <input id="religion" class="input100" type="text" name="religion" value="<?php if(isset($religion)) echo $religion; ?>" required> 
                        </div>
                        <label>Mother Tongue<font color="red">*</font></label>
                        <div class="wrap-input100 validate-input1">
                            <input id="mother_tongue" class="input100" type="text" name="mother_tongue" value="<?php if(isset($mother_tongue)) echo $mother_tongue; ?>" required> 
                        </div>
                        <label>Language(s) Spoken At Home<font color="red">*</font></label>
                        <div class="wrap-input100 validate-input1">
                            <input id="language_spoken_at_home" class="input100" type="text" name="language_spoken_at_home" value="<?php if(isset($language_spoken_at_home)) echo $language_spoken_at_home; ?>" required> 
                        </div>
                        <label>Other Language(s) Spoken</label>
                        <div class="wrap-input100">
                            <input id="other_language_spoken" class="input100" type="text" name="other_language_spoken" value="<?php if(isset($other_language_spoken)) echo $other_language_spoken; ?>"> 
                        </div>
                        <label>Whether a Member Of Scheduled caste or Community Classified as Backward  class or tribe by the state govt.<font color="red">*</font></label>
                        <div class="wrap-input100" style="margin-bottom: 0px !important;">
                            <select name="backward_class" id="backward_class" class="input100" required="required">
                                <option value='Yes' <?php if($backward_class == 'Yes' ) echo 'selected'; ?> >Yes</option>
                                <option value='No' <?php if($backward_class == 'No' ) echo 'selected'; ?>>No</option>
                            </select>                           
                        </div>
                        <span>(If the answer is YES then upload necessary proof document in the last page in “Other documents” section)</span>
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
