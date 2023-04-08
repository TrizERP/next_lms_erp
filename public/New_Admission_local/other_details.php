<?php
session_start();
error_reporting(1);
//require("Warehouse.php");
$host = "150.129.172.214";
$username = "triz_erp";
$password = "Triz@2019$04";
$database = "triz_erp_2";

$cn = mysqli_connect($host, $username, $password) or die("Database Not Connected");
mysqli_select_db($cn, $database) or die("database");


// echo '<pre>';
// print_r($_REQUEST);
// print_r($_SESSION);
// die;


if (isset($_REQUEST['submit'])) 
{

    $token_no = $_REQUEST['token_no'];
    $_SESSION['token_no'] = $token_no;

    $get_Sql = 'SELECT * from new_admission_inquiry_registration WHERE token = "' . $token_no . '"';
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


if (!isset($_REQUEST['other_submit'])) 
{

    $token_no = $_SESSION['token_no'];
    $full_path = 'student_documents/';

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
        $house_no = $row['house_no'];
        $area = $row['area'];
        $city = $row['city'];
        $pin_code = $row['pin_code'];
        $blood_group = $row['blood_group'];
        $height = $row['height'];
        $weight = $row['weight'];
        $vaccination = $row['vaccination'];
        $diabetes = $row['diabetes'];
        $blood_pressure = $row['blood_pressure'];
        $child_admitted = $row['child_admitted'];
        $if_yes_then_reason = $row['if_yes_then_reason'];
        $how_long = $row['how_long'];
        $child_allergies = $row['child_allergies'];
        $habit_of_bed_wetting = $row['habit_of_bed_wetting'];
        $habit_of_thumb_sucking = $row['habit_of_thumb_sucking'];
        $habit_of_anti_acid_activity = $row['habit_of_anti_acid_activity'];
        $habit_of_drug_allergy = $row['habit_of_drug_allergy'];
        $child_dependent = $row['child_dependent'];
        $behavioral_problem = $row['behavioral_problem'];
        $child_taking_milk = $row['child_taking_milk'];
        $child_taking_curd = $row['child_taking_curd'];
        $child_taking_vegetables = $row['child_taking_vegetables'];
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

    }
}

?>


<html lang="en">
    <head>
        <title>Further Admission Registration</title>
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
        <link rel="stylesheet" type="text/css" href="css/util.css">
        <link rel="stylesheet" type="text/css" href="css/main.css">
        <link rel="stylesheet" type="text/css" href="vitalets-bootstrap-datepicker-c7af15b/css/datepicker.css">
    </head>
    <body>

        <div class="container-contact100">
            <div class="newwrap-contact100">
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

                <?php
                if (isset($_REQUEST['other_submit'])) {
                    echo "<br><center><label style='color:red;'>Data Updated Successfully !!</label></center>";
                }
                ?>
                <form class="contact100-form validate-form" action="<?php echo $_SERVER['PHP_SELF']; ?>" method="post">

                    <label>Token No.</label>
                    <div class="wrap-input100 validate-input">
                        <input id="token_no" class="input100" type="text" required name="token_no" maxlength="15" value="<?php if(isset($_REQUEST['token_no'])) echo $_REQUEST['token_no']; ?>">  
                    </div>

                    <div class="container-contact100-form-btn">
                        <button type="submit" name="submit" id="submit" class="contact100-form-btn">Submit</button>
                    </div>
                </form>

                <?php 
                if (isset($_REQUEST['submit'])) 
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

                <form class="contact100-form validate-form" action="<?php echo $_SERVER['PHP_SELF']; ?>" method="post" enctype="multipart/form-data">
                    <div style="width: 100%;">
                        <center>
                            <h3>Student Information</h3>
                            <h4>(Student information to be filled as per - Birth Certificate / Leaving Certificate)</h4>
                        </center>
                        <br>
                        <label>Birth Place<font color="red">*</font></label>
                        <div class="wrap-input100 validate-input">
                            <input id="birth_place" class="input100" type="text" name="birth_place" value="<?php if(isset($birth_place)) echo $birth_place; ?>"> 
                        </div>
                        <label>Town/Village<font color="red">*</font></label>
                        <div class="wrap-input100 validate-input">
                            <input id="town" class="input100" type="text" name="town" value="<?php if(isset($town)) echo $town; ?>" > 
                        </div>
                        <label>District<font color="red">*</font></label>
                        <div class="wrap-input100 validate-input">
                            <input id="district" class="input100" type="text" name="district" value="<?php if(isset($district)) echo $district; ?>" > 
                        </div>
                        <label>State<font color="red">*</font></label>
                        <div class="wrap-input100 validate-input">
                            <input id="state" class="input100" type="text" name="state" value="<?php if(isset($state)) echo $state; ?>" > 
                        </div>
                        <label>Citizenship<font color="red">*</font></label>
                        <div class="wrap-input100 validate-input">
                            <input id="citizenship" class="input100" type="text" name="citizenship" value="<?php if(isset($citizenship)) echo $citizenship; ?>" > 
                        </div>
                        <label>Age</label>
                        <div class="wrap-input100">
                            <input id="age" class="input100" type="text" name="age" value="<?php if(isset($age)) echo $age; ?>" readonly=readonly> 
                        </div>                       
                        <label>Gender<font color="red">*</font></label>
                        <div class="wrap-input100">
                            <select name="gender" id="gender" class="input100">
                                <option value='M' <?php if($gender == 'M' ) echo 'selected'; ?> >Male</option>
                                <option value='F' <?php if($gender == 'F' ) echo 'selected'; ?> >Female</option>
                            </select>
                        </div>
                        <label>Cast<font color="red">*</font></label>
                        <div class="wrap-input100 validate-input">
                            <input id="cast" class="input100" type="text" name="cast" value="<?php if(isset($cast)) echo $cast; ?>" > 
                        </div>
                        <label>Sub Cast<font color="red">*</font></label>
                        <div class="wrap-input100 validate-input">
                            <input id="sub_cast" class="input100" type="text" name="sub_cast" value="<?php if(isset($sub_cast)) echo $sub_cast; ?>" > 
                        </div>
                        <label>Religion<font color="red">*</font></label>
                        <div class="wrap-input100 validate-input">
                            <input id="religion" class="input100" type="text" name="religion" value="<?php if(isset($religion)) echo $religion; ?>"> 
                        </div>
                        <label>Mother Tongue<font color="red">*</font></label>
                        <div class="wrap-input100 validate-input">
                            <input id="mother_tongue" class="input100" type="text" name="mother_tongue" value="<?php if(isset($mother_tongue)) echo $mother_tongue; ?>" > 
                        </div>
                        <label>Language(s) Spoken At Home<font color="red">*</font></label>
                        <div class="wrap-input100 validate-input">
                            <input id="language_spoken_at_home" class="input100" type="text" name="language_spoken_at_home" value="<?php if(isset($language_spoken_at_home)) echo $language_spoken_at_home; ?>" > 
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
                    <div style="width: 100%;">
                        <center>
                            <h3>Residential Address</h3>
                        </center>
                        <br>
                        <label>House No. & Building Name<font color="red">*</font></label>
                        <div class="wrap-input100 validate-input">
                            <input id="house_no" class="input100" type="text" name="house_no" value="<?php if(isset($house_no)) echo $house_no; ?>" > 
                        </div>
                        <label>Area<font color="red">*</font></label>
                        <div class="wrap-input100 validate-input">
                            <input id="area" class="input100" type="text" name="area" value="<?php if(isset($area)) echo $area; ?>" > 
                        </div>
                        <label>City<font color="red">*</font></label>
                        <div class="wrap-input100 validate-input">
                            <input id="city" class="input100" type="text" name="city" value="<?php if(isset($city)) echo $city; ?>" > 
                        </div>
                        <label>Pin Code<font color="red">*</font></label>
                        <div class="wrap-input100 validate-input">
                            <input id="pin_code" class="input100" type="text" name="pin_code" value="<?php if(isset($pin_code)) echo $pin_code; ?>" pattern="\d*" maxlength="6" > 
                        </div>
                    </div>
                    <div style="width: 100%;">
                        <center>
                            <h3>Medical History</h3>
                            <h4>(Upload related documents in the last page)</h4>
                        </center>
                        <br>
                        <label>Blood Group<font color="red">*</font></label>
                        <div class="wrap-input100 validate-input">
                            <select name="blood_group" id="blood_group" class="input100">
                                <option value='A+' <?php if($blood_group == 'A+') echo 'selected'; ?>>A+</option>
                                <option value='A-' <?php if($blood_group == 'A-') echo 'selected'; ?>>A-</option>
                                <option value='B+' <?php if($blood_group == 'B+') echo 'selected'; ?>>B+</option>
                                <option value='B-' <?php if($blood_group == 'B-') echo 'selected'; ?>>B-</option>
                                <option value='O+' <?php if($blood_group == 'O+') echo 'selected'; ?>>O+</option>
                                <option value='O-' <?php if($blood_group == 'O-') echo 'selected'; ?>>O-</option>
                                <option value='AB+' <?php if($blood_group == 'AB+') echo 'selected'; ?>>AB+</option>
                                <option value='AB-' <?php if($blood_group == 'AB-') echo 'selected'; ?>>AB-</option>                           
                            </select>                            
                        </div>
                        <label>Height In Cms<font color="red">*</font></label>
                        <div class="wrap-input100">
                            <input id="height" class="input100" name="height" type="number" pattern="[0-9]+([,\.][0-9]+)?" step="0.01" value="<?php if(isset($height)) echo $height; ?>" > 
                        </div>
                        <label>Weight In Kgs<font color="red">*</font></label>
                        <div class="wrap-input100 validate-input">
                            <input id="weight" class="input100" type="number" pattern="[0-9]+([,\.][0-9]+)?" step="0.01" name="weight" value="<?php if(isset($weight)) echo $weight; ?>" >
                        </div>
                        <label>Has the child given all the vaccination?<font color="red">*</font></label>
                        <div class="wrap-input100 validate-input">
                            <select name="vaccination" id="vaccination" class="input100">
                                <option value='Yes' <?php if($vaccination == 'Yes' ) echo 'selected'; ?>>Yes</option>
                                <option value='No' <?php if($vaccination == 'No' ) echo 'selected'; ?>>No</option>
                            </select>
                        </div>
                        <br>
                        <span style="font-weight: bold;">A) Family History Of illness :</span>
                        <label>Diabetes<font color="red">*</font></label>
                        <div class="wrap-input100 validate-input">
                            <select name="diabetes" id="diabetes" class="input100">
                                <option value='Affected to Father/Mother' <?php if($diabetes == 'Affected to Father/Mother' ) echo 'selected'; ?>>Affected to Father/Mother</option>
                                <option value='Affected to Brother/Sister' <?php if($diabetes == 'Affected to Brother/Sister' ) echo 'selected'; ?>>Affected to Brother/Sister</option>
                                <option value='None of them are affected' <?php if($diabetes == 'None of them are affected' ) echo 'selected'; ?>>None of them are affected</option>
                                <option value='Affected to Both Mother & Father' <?php if($diabetes == 'Affected to Both Mother & Father' ) echo 'selected'; ?>>Affected to Both Mother & Father</option>
                            </select>
                        </div>
                        <label>Blood Pressure<font color="red">*</font></label>
                        <div class="wrap-input100 validate-input">
                            <select name="blood_pressure" id="blood_pressure" class="input100">
                                <option value='Affected to Father/Mother' <?php if($blood_pressure == 'Affected to Father/Mother' ) echo 'selected'; ?>>Affected to Father/Mother</option>
                                <option value='Affected to Brother/Sister' <?php if($blood_pressure == 'Affected to Brother/Sister' ) echo 'selected'; ?>>Affected to Brother/Sister</option>
                                <option value='None of them are affected' <?php if($blood_pressure == 'Affected to Brother/Sister' ) echo 'selected'; ?>>None of them are affected</option>
                                <option value='Affected to Both Mother & Father' <?php if($blood_pressure == 'Affected to Both Mother & Father' ) echo 'selected'; ?>>Affected to Both Mother & Father</option>
                            </select>
                        </div>
                        <br>
                        <span style="font-weight: bold;">B) Past History of Major illness :</span>
                        <label>Was the child admitted to hospital at any time?<font color="red">*</font></label>
                        <div class="wrap-input100 validate-input">
                            <select name="child_admitted" id="child_admitted" class="input100" onchange="showothers(value);">
                                <option value='Yes' <?php if($child_admitted == 'Yes' ) echo 'selected'; ?>>Yes</option>
                                <option value='No' <?php if($child_admitted == 'No' ) echo 'selected'; ?>>No</option>
                            </select>
                        </div>
                        <label style="display: none;" id="labelforYes">If YES then reason</label>
                        <div class="wrap-input100" style="display: none;" id="inputsforYes">
                            <input id="if_yes_then_reason" class="input100" type="text" name="if_yes_then_reason" value="<?php if(isset($if_yes_then_reason)) echo $if_yes_then_reason; ?>" > 
                        </div>
                        <label style="display: none;" id="labelforlong">How long</label>
                        <div class="wrap-input100" style="display: none;" id="inputsforlong">
                            <input id="how_long" class="input100" type="text" name="how_long" value="<?php if(isset($how_long)) echo $how_long; ?>" > 
                        </div>
                        <label>Does the child have identified allergies if so, give details<font color="red">*</font></label>
                        <div class="wrap-input100 validate-input">
                            <input id="child_allergies" class="input100" type="text" name="child_allergies" value="<?php if(isset($child_allergies)) echo $child_allergies; ?>" > 
                        </div>
                        <label>Habit Of Bed Wetting?<font color="red">*</font></label>
                        <div class="wrap-input100 validate-input">
                            <select name="habit_of_bed_wetting" id="habit_of_bed_wetting" class="input100">
                                <option value='Yes' <?php if($habit_of_bed_wetting == 'Yes' ) echo 'selected'; ?>>Yes</option>
                                <option value='No' <?php if($habit_of_bed_wetting == 'No' ) echo 'selected'; ?>>No</option>
                            </select>
                        </div>
                        <label>Habit Of Thumb Sucking?<font color="red">*</font></label>
                        <div class="wrap-input100 validate-input">
                            <select name="habit_of_thumb_sucking" id="habit_of_thumb_sucking" class="input100">
                                <option value='Yes' <?php if($habit_of_thumb_sucking == 'Yes' ) echo 'selected'; ?>>Yes</option>
                                <option value='No' <?php if($habit_of_thumb_sucking == 'No' ) echo 'selected'; ?>>No</option>
                            </select>
                        </div>
                        <label>Habit Of Anti Acid Activity?<font color="red">*</font></label>
                        <div class="wrap-input100 validate-input">
                            <select name="habit_of_anti_acid_activity" id="habit_of_anti_acid_activity" class="input100">
                                <option value='Yes' <?php if($habit_of_anti_acid_activity == 'Yes' ) echo 'selected'; ?>>Yes</option>
                                <option value='No' <?php if($habit_of_anti_acid_activity == 'No' ) echo 'selected'; ?>>No</option>
                            </select>
                        </div>
                        <label>Habit Of Drug Allergy?<font color="red">*</font></label>
                        <div class="wrap-input100 validate-input">
                            <select name="habit_of_drug_allergy" id="habit_of_drug_allergy" class="input100">
                                <option value='Yes' <?php if($habit_of_drug_allergy == 'Yes' ) echo 'selected'; ?>>Yes</option>
                                <option value='No' <?php if($habit_of_drug_allergy == 'No' ) echo 'selected'; ?>>No</option>
                            </select>
                        </div>
                        <br>
                        <span style="font-weight: bold;">C) History Of Psychiatric Problem :</span>
                        <label>Is the child too much dependent on parents?<font color="red">*</font></label>
                        <div class="wrap-input100 validate-input">
                            <select name="child_dependent" id="child_dependent" class="input100">
                                <option value='Yes' <?php if($child_dependent == 'Yes' ) echo 'selected'; ?>>Yes</option>
                                <option value='No' <?php if($child_dependent == 'No' ) echo 'selected'; ?>>No</option>
                            </select>
                        </div>
                        <label>Mention behavioral problems if any<font color="red">*</font></label>
                        <div class="wrap-input100 validate-input">
                            <input id="behavioral_problem" class="input100" type="text" name="behavioral_problem" value="<?php if(isset($behavioral_problem)) echo $behavioral_problem; ?>" > 
                        </div>
                        <br>
                        <span style="font-weight: bold;">D) Food Habits :</span>
                        <label>Is the child taking plain milk regularly?<font color="red">*</font></label>
                        <div class="wrap-input100 validate-input">
                            <select name="child_taking_milk" id="child_taking_milk" class="input100">
                                <option value='Yes' <?php if($child_taking_milk == 'Yes' ) echo 'selected'; ?>>Yes</option>
                                <option value='No' <?php if($child_taking_milk == 'No' ) echo 'selected'; ?>>No</option>
                            </select>
                        </div>
                        <label>Does the child take curd?<font color="red">*</font></label>
                        <div class="wrap-input100 validate-input">
                            <select name="child_taking_curd" id="child_taking_curd" class="input100">
                                <option value='Yes' <?php if($child_taking_curd == 'Yes' ) echo 'selected'; ?>>Yes</option>
                                <option value='No' <?php if($child_taking_curd == 'No' ) echo 'selected'; ?>>No</option>
                            </select>
                        </div>
                        <label>Is the child taking all vegetables?<font color="red">*</font></label>
                        <div class="wrap-input100 validate-input">
                            <select name="child_taking_vegetables" id="child_taking_vegetables" class="input100">
                                <option value='Yes' <?php if($child_taking_vegetables == 'Yes' ) echo 'selected'; ?>>Yes</option>
                                <option value='No' <?php if($child_taking_vegetables == 'No' ) echo 'selected'; ?>>No</option>
                            </select>
                        </div>
                    </div>
                    <div style="width: 100%;">
                        <center>
                            <h3>Family Information</h3>
                            <h4>(To be filled as per ID proof)</h4>
                        </center>
                        <div style="width: 20%;float: left;">
                            <label style="margin-top: 30px;">Name</label>
                            <label style="margin-top: 27px;">Date Of Birth<font color="red">*</font></label>
                            <label style="margin-top: 27px;">Qualification<font color="red">*</font></label>
                            <label style="margin-top: 30px;">Blood Group<font color="red">*</font></label>
                            <label style="margin-top: 27px;">Occupation<font color="red">*</font></label>
                            <label style="margin-top: 31px;">Name of the Organization?<font color="red">*</font></label>
                            <label style="margin-top: 31px;">Designation<font color="red">*</font></label>
                            <label style="margin-top: 27px;">Business Office Address<font color="red">*</font></label>
                            <label style="margin-top: 27px;">Mobile No</label>
                            <label style="margin-top: 27px;">Email</label>
                            <label style="margin-top: 27px;">Gross Annual Income<font color="red">*</font></label>
                        </div>
                        <div style="width: 30%;float: left;">
                             <center><span style="font-weight: bold;">Father</span></center>
                            <div class="wrap-input100">
                                <input id="father_name" class="input100" type="text" name="father_name" value="<?php if(isset($father_name)) echo $father_name; ?>" readonly="readonly"> 
                            </div>
                            <div class="wrap-input100 validate-input">
                                <input id="father_dob" class="datepicker" required type="date" name="father_dob" autocomplete="off" value="<?php if(isset($father_dob)) echo $father_dob; ?>">
                            </div>
                            <div class="wrap-input100 validate-input">
                                <input id="father_qualification" class="input100" type="text" name="father_qualification" value="<?php if(isset($father_qualification)) echo $father_qualification; ?>" > 
                            </div>
                            <div class="wrap-input100 validate-input">
                                <input id="father_blood_group" class="input100" type="text" name="father_blood_group" value="<?php if(isset($father_blood_group)) echo $father_blood_group; ?>" > 
                            </div>
                            <div class="wrap-input100 validate-input">
                                <input id="father_occupation" class="input100" type="text" name="father_occupation" value="<?php if(isset($father_occupation)) echo $father_occupation; ?>" > 
                            </div>
                            <div class="wrap-input100 validate-input">
                                <input id="father_organization_name" class="input100" type="text" name="father_organization_name" value="<?php if(isset($father_organization_name)) echo $father_organization_name; ?>" > 
                            </div>
                            <div class="wrap-input100 validate-input">
                                <input id="father_designation" class="input100" type="text" name="father_designation" value="<?php if(isset($father_designation)) echo $father_designation; ?>" > 
                            </div>
                            <div class="wrap-input100 validate-input">
                                <input id="father_office_address" class="input100" type="text" name="father_office_address" value="<?php if(isset($father_office_address)) echo $father_office_address; ?>" > 
                            </div>
                            <div class="wrap-input100">
                                <input id="mobile" class="input100" type="text" name="mobile" value="<?php if(isset($mobile)) echo $mobile; ?>" readonly="readonly"> 
                            </div>
                            <div class="wrap-input100">
                                <input id="father_email" class="input100" type="text" name="father_email" value="<?php if(isset($mail)) echo $mail; ?>" readonly="readonly"> 
                            </div>
                            <div class="wrap-input100 validate-input">
                                <input id="father_income" class="input100" type="text" name="father_income" value="<?php if(isset($father_income)) echo $father_income; ?>" > 
                            </div>
                        </div>
                        <div style="width: 30%;float: right;">
                            <center><span style="font-weight: bold;">Mother</span></center>
                            <div class="wrap-input100 validate-input">
                                <input id="mother_name" class="input100" type="text" name="mother_name" value="<?php if(isset($mother_name)) echo $mother_name; ?>" > 
                            </div>
                            <div class="wrap-input100 validate-input">
                                <input id="mother_dob" class="datepicker" required type="date" name="mother_dob" autocomplete="off" value="<?php if(isset($mother_dob)) echo $mother_dob; ?>">
                            </div>
                            <div class="wrap-input100 validate-input">
                                <input id="mother_qualification" class="input100" type="text" name="mother_qualification" value="<?php if(isset($mother_qualification)) echo $mother_qualification; ?>" > 
                            </div>
                            <div class="wrap-input100 validate-input">
                                <input id="mother_blood_group" class="input100" type="text" name="mother_blood_group" value="<?php if(isset($mother_blood_group)) echo $mother_blood_group; ?>" > 
                            </div>
                            <div class="wrap-input100 validate-input">
                                <input id="mother_occupation" class="input100" type="text" name="mother_occupation" value="<?php if(isset($mother_occupation)) echo $mother_occupation; ?>" > 
                            </div>
                            <div class="wrap-input100 validate-input">
                                <input id="mother_organization_name" class="input100" type="text" name="mother_organization_name" value="<?php if(isset($mother_organization_name)) echo $mother_organization_name; ?>" > 
                            </div>
                            <div class="wrap-input100 validate-input">
                                <input id="mother_designation" class="input100" type="text" name="mother_designation" value="<?php if(isset($mother_designation)) echo $mother_designation; ?>" > 
                            </div>
                            <div class="wrap-input100 validate-input">
                                <input id="mother_office_address" class="input100" type="text" name="mother_office_address" value="<?php if(isset($mother_office_address)) echo $mother_office_address; ?>" > 
                            </div>
                            <div class="wrap-input100 validate-input">
                                <input id="mother_mobile_no" class="input100" type="text" name="mother_mobile_no" value="<?php if(isset($mother_mobile_no)) echo $mother_mobile_no; ?>" maxlength="10" pattern="\d{10}" > 
                            </div>
                            <div class="wrap-input100 validate-input">
                                <input id="mother_email" class="input100" type="email" name="mother_email" value="<?php if(isset($mother_email)) echo $mother_email; ?>" > 
                            </div>
                            <div class="wrap-input100 validate-input">
                                <input id="mother_income" class="input100" type="text" name="mother_income" value="<?php if(isset($mother_income)) echo $mother_income; ?>" > 
                            </div>
                        </div>
                    </div>
                    <div style="width: 100%;">
                         <center><span style="font-weight: bold;">Local Guardian Address</span></center>   
                         <div style="width: 20%;float: left;">
                            <label>Full Name</label>
                            <label>Address</label>
                            <label>Tel No/Mobile No</label>
                            <label>Email</label>
                            <label>Relationship with the child</label>
                         </div>
                         <div style="width: 40%;float: right;">
                            <div class="wrap-input100">
                                <input id="guardian_name" class="input100" type="text" name="guardian_name" value="<?php if(isset($guardian_name)) echo $guardian_name; ?>" > 
                            </div>
                            <div class="wrap-input100">
                                <input id="guardian_address" class="input100" type="text" name="guardian_address" value="<?php if(isset($guardian_address)) echo $guardian_address; ?>" > 
                            </div>
                            <div class="wrap-input100">
                                <input id="guardian_mobile_no" class="input100" type="text" name="guardian_mobile_no" value="<?php if(isset($guardian_mobile_no)) echo $guardian_mobile_no; ?>" > 
                            </div>
                            <div class="wrap-input100">
                                <input id="guardian_email" class="input100" type="text" name="guardian_email" value="<?php if(isset($guardian_email)) echo $guardian_email; ?>" > 
                            </div>
                            <div class="wrap-input100">
                                <input id="guardian_relation_with_child" class="input100" type="text" name="guardian_relation_with_child" value="<?php if(isset($guardian_relation_with_child)) echo $guardian_relation_with_child; ?>" > 
                            </div>
                         </div>
                    </div>
                    <div style="width: 100%;">
                         <center>
                            <span style="font-weight: bold;">Siblings Information (Brothers & Sisters Information)</span>
                        </center>   
                        <div style="width: 20%;float: left;margin-right: 7%;">
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
                        </div>
                        <div style="width: 20%;float: left;">
                            <label>Date Of Birth</label>
                            <div class="wrap-input100">
                                <input id="sibling1_dob" type="date" name="sibling1_dob" autocomplete="off" value="<?php if(isset($sibling1_dob)) echo $sibling1_dob; ?>">
                            </div>
                            <div class="wrap-input100">
                                <input id="sibling2_dob" type="date" name="sibling2_dob" autocomplete="off" value="<?php if(isset($sibling2_dob)) echo $sibling2_dob; ?>">
                            </div>
                            <div class="wrap-input100">
                                <input id="sibling3_dob" type="date" name="sibling3_dob" autocomplete="off" value="<?php if(isset($sibling3_dob)) echo $sibling3_dob; ?>">
                            </div>
                            <div class="wrap-input100">
                                <input id="sibling4_dob" type="date" name="sibling4_dob" autocomplete="off" value="<?php if(isset($sibling4_dob)) echo $sibling4_dob; ?>">
                            </div>
                        </div>
                        <div style="width: 20%;float: right;">
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
                        </div>
                        <div style="width: 20%;float: right;margin-right: 7%;">
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
                        </div>
                    </div>
                    <div style="width: 100%;">
                        <center>
                            <h3>Upload Documents</h3>
                            <h4>(Upload Properly Scanned Copies only)</h4>
                        </center>
                        <br>
                        <div style="width: 30%;float: left;">
                            <label style="margin-top: 25px;">Birth Certificate<font color="red">*</font></label>
                            <label style="margin-top: 25px;">Student’s Aadhaar Card<font color="red">*</font></label>
                            <label style="margin-top: 25px;">Student’s Cast Certificate (if applicable)</label>
                            <label style="margin-top: 25px;">Father’s Cast Certificate</label>
                            <label style="margin-top: 25px;">Passport Size Photo Of Student<font color="red">*</font></label>
                            <label style="margin-top: 25px;">Post Card Size Family Photograph (Latest)<font color="red">*</font></label>
                            <label style="margin-top: 25px;">Vaccination Record<font color="red">*</font></label>
                            <label style="margin-top: 25px;">Medical Examination Report Certified by your Family Doctor<font color="red">*</font></label>
                            <label style="margin-top: 25px;">Self Attested Copy Of Father Aadhaar Card<font color="red">*</font></label>
                            <label style="margin-top: 25px;">Self Attested Copy Of Mother Aadhaar Card<font color="red">*</font></label>
                            <label style="margin-top: 25px;">Address Proof (Ration Card/Agreement)<font color="red">*</font></label>
                            <label style="margin-top: 25px;">Father Signature<font color="red">*</font></label>
                            <label style="margin-top: 25px;">Mother Signature<font color="red">*</font></label>
                        </div>
                        <div style="width: 40%;float: left;">
                            <div class="wrap-input100">
                                <input id="birth_certificate" class="input100" type="file" name="birth_certificate" value="<?php if(isset($birth_certificate)) echo $birth_certificate; ?>"> 
                            </div>
                            <div class="wrap-input100">
                                <input id="student_adharcard" class="input100" type="file" name="student_adharcard" value="<?php if(isset($student_adharcard)) echo $student_adharcard; ?>" > 
                            </div>
                            <div class="wrap-input100">
                                <input id="student_cast_certificate" class="input100" type="file" name="student_cast_certificate" value="<?php if(isset($student_cast_certificate)) echo $student_cast_certificate; ?>" > 
                            </div>
                            <div class="wrap-input100">
                                <input id="father_cast_certificate" class="input100" type="file" name="father_cast_certificate" value="<?php if(isset($father_cast_certificate)) echo $father_cast_certificate; ?>" > 
                            </div>
                            <div class="wrap-input100">
                                <input id="student_passport_size_photo" class="input100" type="file" name="student_passport_size_photo" value="<?php if(isset($student_passport_size_photo)) echo $student_passport_size_photo; ?>" > 
                            </div>
                            <div class="wrap-input100">
                                <input id="family_photo" class="input100" type="file" name="family_photo" value="<?php if(isset($family_photo)) echo $family_photo; ?>" > 
                            </div>
                            <div class="wrap-input100">
                                <input id="vaccination_record" class="input100" type="file" name="vaccination_record" value="<?php if(isset($vaccination_record)) echo $vaccination_record; ?>" > 
                            </div>
                            <div class="wrap-input100">
                                <input id="medical_examination_report" class="input100" type="file" name="medical_examination_report" value="<?php if(isset($medical_examination_report)) echo $medical_examination_report; ?>" > 
                            </div>
                            <div class="wrap-input100">
                                <input id="father_adharcard" class="input100" type="file" name="father_adharcard" value="<?php if(isset($father_adharcard)) echo $father_adharcard; ?>" > 
                            </div>
                            <div class="wrap-input100">
                                <input id="mother_adharcard" class="input100" type="file" name="mother_adharcard" value="<?php if(isset($mother_adharcard)) echo $mother_adharcard; ?>" > 
                            </div>
                            <div class="wrap-input100">
                                <input id="address_proof" class="input100" type="file" name="address_proof" value="<?php if(isset($address_proof)) echo $address_proof; ?>" > 
                            </div>
                            <div class="wrap-input100">
                                <input id="father_signature" class="input100" type="file" name="father_signature" value="<?php if(isset($father_signature)) echo $father_signature; ?>" > 
                            </div>
                            <div class="wrap-input100">
                                <input id="mother_signature" class="input100" type="file" name="mother_signature" value="<?php if(isset($mother_signature)) echo $mother_signature; ?>" > 
                            </div>
                        </div>
                        <div style="width: 20%;float: right;">
                            <div>
                                <a href="<?php if(isset($birth_certificate)) echo $birth_certificate; ?>" target="_blank" ><img width="50" height="60" style="border: 1px solid black;" src="<?php if(isset($birth_certificate)) echo $birth_certificate; ?>"/></a>
                            </div>
                            <div>
                                <a href="<?php if(isset($student_adharcard)) echo $student_adharcard; ?>" target="_blank" ><img width="50" height="60" style="border: 1px solid black;" src="<?php if(isset($student_adharcard)) echo $student_adharcard; ?>"/></a>
                            </div>
                            <div>
                                <a href="<?php if(isset($student_cast_certificate)) echo $student_cast_certificate; ?>" target="_blank" ><img width="50" height="60" style="border: 1px solid black;" src="<?php if(isset($student_cast_certificate)) echo $student_cast_certificate; ?>"/></a>
                            </div>
                            <div>
                                <a href="<?php if(isset($father_cast_certificate)) echo $father_cast_certificate; ?>" target="_blank" ><img width="50" height="60" style="border: 1px solid black;" src="<?php if(isset($father_cast_certificate)) echo $father_cast_certificate; ?>"/></a>
                            </div>
                            <div>
                                <a href="<?php if(isset($student_passport_size_photo)) echo $student_passport_size_photo; ?>" target="_blank" ><img width="50" height="60" style="border: 1px solid black;" src="<?php if(isset($student_passport_size_photo)) echo $student_passport_size_photo; ?>"/></a>
                            </div>
                            <div>
                                <a href="<?php if(isset($family_photo)) echo $family_photo; ?>" target="_blank" ><img width="50" height="60" style="border: 1px solid black;" src="<?php if(isset($family_photo)) echo $family_photo; ?>"/></a>
                            </div>
                            <div>
                                <a href="<?php if(isset($vaccination_record)) echo $vaccination_record; ?>" target="_blank" ><img width="50" height="60" style="border: 1px solid black;" src="<?php if(isset($vaccination_record)) echo $vaccination_record; ?>"/></a>
                            </div>
                            <div>
                                <a href="<?php if(isset($medical_examination_report)) echo $medical_examination_report; ?>" target="_blank" ><img width="50" height="60" style="border: 1px solid black;" src="<?php if(isset($medical_examination_report)) echo $medical_examination_report; ?>"/></a>
                            </div>
                            <div>
                                <a href="<?php if(isset($father_adharcard)) echo $father_adharcard; ?>" target="_blank" ><img width="50" height="60" style="border: 1px solid black;" src="<?php if(isset($father_adharcard)) echo $father_adharcard; ?>"/></a>
                            </div>
                            <div>
                                <a href="<?php if(isset($mother_adharcard)) echo $mother_adharcard; ?>" target="_blank" ><img width="50" height="60" style="border: 1px solid black;" src="<?php if(isset($mother_adharcard)) echo $mother_adharcard; ?>"/></a>
                            </div>
                            <div>
                                <a href="<?php if(isset($address_proof)) echo $address_proof; ?>" target="_blank" ><img width="50" height="60" style="border: 1px solid black;" src="<?php if(isset($address_proof)) echo $address_proof; ?>"/></a>
                            </div>
                            <div>
                                <a href="<?php if(isset($father_signature)) echo $father_signature; ?>" target="_blank" ><img width="50" height="60" style="border: 1px solid black;" src="<?php if(isset($father_signature)) echo $father_signature; ?>"/></a>
                            </div>
                            <div>
                                <a href="<?php if(isset($mother_signature)) echo $mother_signature; ?>" target="_blank" ><img width="50" height="60" style="border: 1px solid black;" src="<?php if(isset($mother_signature)) echo $mother_signature; ?>"/></a>
                            </div>
                        </div>
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
        <!--<script src="jquery-ui-1.12.1.custom/jquery-ui.min.js"></script>-->
        <!--<script src="vendor/daterangepicker/moment.min.js"></script>-->
        <script src="vitalets-bootstrap-datepicker-c7af15b/js/bootstrap-datepicker.js"></script>
        <!--<script src="vendor/daterangepicker/daterangepicker.js"></script>-->
        <script src="vendor/countdowntime/countdowntime.js"></script>
        <script src="js/main.js"></script>
        <script type="text/javascript">
            function showothers(other)
            {
                if(other === 'Yes')
                {   
                    // alert('yes');
                    document.getElementById("labelforYes").style.display = "block";
                    document.getElementById("inputsforYes").style.display = "block";
                    document.getElementById("labelforlong").style.display = "block";
                    document.getElementById("inputsforlong").style.display = "block";
                }else{
                    // alert('no');
                    document.getElementById("labelforYes").style.display = "none";
                    document.getElementById("inputsforYes").style.display = "none";
                    document.getElementById("labelforlong").style.display = "none";
                    document.getElementById("inputsforlong").style.display = "none";
                }   
            }
        </script>
    </body>
</html>
