<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

require 'auth/vendor/autoload.php';
require_once("auth/keys.php");
require_once('app_config.php');


use \Firebase\JWT\JWT;


// get posted data
$data = json_decode(file_get_contents("php://input"));
$data = (array) $data;

$send_data = array(
	"message" => "Successful Login.",
	"status" => 1
);


if(
	isset($data['mobile']) && $data['mobile'] != '' &&
	isset($data['password']) && $data['password'] != ''
) {

	$mobile = $data["mobile"];
	$pass = $data["password"];

	$sql = "SELECT id,username as enrollment_no,firstname as first_name,
IF(middlename IS NULL or middlename = '', '-', middlename) as middle_name,lastname as last_name,
IF(idnumber IS NULL or idnumber = '', '-', idnumber) as sub_institute_id,IF(phone1 IS NULL or phone1 = '', '-', phone1) as mobile,
IF(icq IS NULL or icq = '', '-', icq) as roll_no,
UPPER(LEFT(lastname, 1)) as std_name,UPPER(RIGHT(lastname, 1)) as division,
IF(department IS NULL or department = '', '-', department) as section,
IF(alternatename IS NULL or alternatename = '', '-', alternatename) as dob,
IF(address IS NULL or address = '', '-', address) as address,
IF(lastnamephonetic IS NULL or lastnamephonetic = '', '-', lastnamephonetic) as father_name,
IF(firstnamephonetic IS NULL or firstnamephonetic = '', '-', firstnamephonetic) as mother_name,picture as image
FROM mdl_user
WHERE username='" . $mobile . "'";
			
	$res = mysqli_query($conn, $sql);

	if (mysqli_num_rows($res) > 0) {

		while ($row1 = mysqli_fetch_array($res)) {
			$stu = array();
			$stu["id"] = $row1['id'];
			$stu["U_ID"] = $row1['enrollment_no'];
			$stu["first_name"] = $row1['first_name'];
			$stu["middle_name"] = $row1['middle_name'];
			$stu["last_name"] = $row1['last_name'];
			$stu["roll_no"] = $row1['roll_no'];
			$stu["section"] = $row1['section'];
			$stu["std_name"] = $row1['std_name'];
			$stu["division"] = $row1['division'];
			$stu["dob"] = $row1['dob'];
			$stu["address"] = $row1['address'];
			$stu["father_name"] = $row1['father_name'];
			$stu["mother_name"] = $row1['mother_name'];
			$stu["image"] = $row1['image'];
			$stu["mobile"] = $row1['mobile'];
			$stu["sub_institute_id"] = $row1['sub_institute_id'];
			$stu["lms_user_id"] = $row1['id'];
			$stu["userid"] = $row1['id'];

			$jwt_arr = array(
				"id" => $row1['id'],
				"U_ID" => $row1['enrollment_no'],
				"first_name" => $row1['first_name'],
				"last_name" => $row1['last_name'],
				"roll_no" => $row1['roll_no'],
				"std_name" => $row1['std_name'],
				"division" => $row1['division'],
				"userid" => $row1['id']
			);

			$jwt = JWT::encode($jwt_arr, $secrateKey);

			$send_data["data"] = $stu;
			$send_data["token"] = $jwt;

			echo json_encode($send_data);
			
		}
	

	 } else {
		echo json_encode(array(
			"message" => "Invalid User Name/Password.",
			"status" => 0
		));
		exit;
	}
} else {
	http_response_code(401);

	echo json_encode(array(
		"message" => "Bad Request.",
		"status" => 0
	));
	exit;
}