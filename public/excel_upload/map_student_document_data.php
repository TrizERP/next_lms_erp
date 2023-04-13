<?php
include('db.php');
require_once('PHPExcel.php');
session_start();

$sub_institute_id = $_REQUEST['sub_institute_id'];
$syear = $_REQUEST['syear'];
$user_id = $_REQUEST['user_id'];

mysqli_set_charset($cn, "utf8");

$checkQuery = "SELECT s.first_name,s.id AS student_id,n.birth_certificate,n.student_adharcard,
n.student_cast_certificate,n.father_cast_certificate,n.student_passport_size_photo,n.family_photo,n.vaccination_record,
n.medical_examination_report,n.father_adharcard,n.mother_adharcard,n.address_proof,
n.father_signature,n.mother_signature,n.any_other_doc,n.other_doc
FROM tblstudent s
INNER JOIN new_admission_inquiry_registration n ON n.token = s.admission_token_no
WHERE s.sub_institute_id = '47' AND s.admission_token_no IS NOT NULL";

$result = mysqli_query($cn, $checkQuery) or die(mysqli_error($cn));

if (mysqli_num_rows($result) > 0) {
    $return_array = array();
    while ($value = mysqli_fetch_assoc($result)) {
        if ($value['birth_certificate'] != "") {
            $return_array[$value['student_id']][] = insert_document($value['student_id'], 'Birth Certificate', $value['birth_certificate'], '3');
        }
        if ($value['student_adharcard'] != "") {
            $return_array[$value['student_id']][] = insert_document($value['student_id'], 'Student Adharcard', $value['student_adharcard'], '2');
        }
        if ($value['student_cast_certificate'] != "") {
            $return_array[$value['student_id']][] = insert_document($value['student_id'], 'Student Cast Certificate', $value['student_cast_certificate'], '11');
        }
        if ($value['father_cast_certificate'] != "") {
            $return_array[$value['student_id']][] = insert_document($value['student_id'], 'Father Cast Certificate', $value['father_cast_certificate'], '30');
        }
        if ($value['student_passport_size_photo'] != "") {
            $return_array[$value['student_id']][] = insert_document($value['student_id'], 'Student Passport Size Photo', $value['student_passport_size_photo'], '43');
        }
        if ($value['family_photo'] != "") {
            $return_array[$value['student_id']][] = insert_document($value['student_id'], 'Family Photo', $value['family_photo'], '8');
        }
        if ($value['vaccination_record'] != "") {
            $return_array[$value['student_id']][] = insert_document($value['student_id'], 'Vaccination Record', $value['vaccination_record'], '7');
        }
        if ($value['medical_examination_report'] != "") {
            $return_array[$value['student_id']][] = insert_document($value['student_id'], 'Medical Examination Report', $value['medical_examination_report'], '6');
        }
        if ($value['father_adharcard'] != "") {
            $return_array[$value['student_id']][] = insert_document($value['student_id'], 'Father Adharcard', $value['father_adharcard'], '25');
        }
        if ($value['mother_adharcard'] != "") {
            $return_array[$value['student_id']][] = insert_document($value['student_id'], 'Mother Adharcard', $value['mother_adharcard'], '26');
        }
        if ($value['address_proof'] != "") {
            $return_array[$value['student_id']][] = insert_document($value['student_id'], 'Address Proof', $value['address_proof'], '10');
        }
        if ($value['father_signature'] != "") {
            $return_array[$value['student_id']][] = insert_document($value['student_id'], 'Father Signature', $value['father_signature'], '44');
        }
        if ($value['mother_signature'] != "") {
            $return_array[$value['student_id']][] = insert_document($value['student_id'], 'Mother Signature', $value['mother_signature'], '45');
        }
        if ($value['any_other_doc'] != "") {
            $return_array[$value['student_id']][] = insert_document($value['student_id'], 'Any Other Doc', $value['any_other_doc'], '46');
        }
        if ($value['other_doc'] != "") {
            $return_array[$value['student_id']][] = insert_document($value['student_id'], 'Other Doc', $value['other_doc'], '47');
        }

    }
}
echo '<pre>';
print_r($return_array);

function insert_document($student_id, $document_title, $file_name, $document_type_id)
{
    global $cn;

    $records_uploaded = $records_not_uploaded = 0;
    $problematic_reocords = "";
    $return_array = array();

    $checkQuery = "SELECT * FROM tblstudent_document WHERE student_id = '" . $student_id . "' AND document_type_id = '" . $document_type_id . "'";
    $checkresult = mysqli_query($cn, $checkQuery) or die(mysqli_error($cn));

    if (mysqli_num_rows($checkresult) == 0) {
        $insertQuery = "INSERT INTO tblstudent_document(student_id,document_type_id,document_title,file_name,sub_institute_id,created_on)
        values('" . $student_id . "','" . $document_type_id . "','" . $document_title . "','" . $file_name . "',47,now())";

        $result = mysqli_query($cn, $insertQuery) or die(mysqli_error($cn));
        if ($result) {
            $records_uploaded++;
        } else {
            $records_not_uploaded++;
            $problematic_reocords .= "Student ID -  " . $studnet_id . " Document Title -  " . $document_title . "File Name -  " . $file_name . "<br><br>";
        }
        $return_array['PROBLEMATIC_RECORDS'] = $problematic_reocords;
    }


    return $return_array;
}

?>
