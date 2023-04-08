<?php

$sub_institute_id = '';
if(isset($_REQUEST['sub_institute_id']) && $_REQUEST['sub_institute_id'] != '')
{
	$sub_institute_id = $_REQUEST['sub_institute_id'];
}
// else{
// 	echo "<center><h2><font color=red>Please contact to your administrator for Online Admission.</font></h2></center>";
// 	exit();
// }

$school_sql = "SELECT gd.*,s.Logo 
			   FROM school_setup s 
			   INNER JOIN general_data gd ON gd.sub_institute_id = s.Id
			   WHERE s.Id = '".$sub_institute_id."' AND gd.fieldname like '%admission%'";

$SCHOOL_RET = mysqli_query($cn, $school_sql);
while ($row = mysqli_fetch_all($SCHOOL_RET, MYSQLI_ASSOC))
{
   $school_logo = "/admin_dep/images/".$row[0]['Logo'];
   $school_name = $row[1]['fieldvalue'];
   $school_address = $row[2]['fieldvalue'];
   $admission_year = $row[3]['fieldvalue'];
}

?>

<html lang="en">
    <head>
        <title>Online Admission</title>
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
            <div class="wrap-contact100">
                <CENTER>
                    <div class="contact100-form-title" style="background-image: url(images/bg-02.jpg);">
                        <table width="100%">
                            <tr>
                                <td>
                                    <img src="<?php echo $school_logo; ?>" style="width: 100px;" /><br>
                                </td>
                                <td>
                            <center><span style="font-size: 20px;font-weight: bold;color:white;"><?php echo $school_name; ?></br></span>
                                <span style="color:white;"><?php echo $school_address; ?></br></span>
                                <span style="color:white;">(<?php echo $admission_year; ?>)</span></center>
                            </td>
                            </tr>
                        </table>
                    </div>
                </CENTER>