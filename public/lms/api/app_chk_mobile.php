<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

include('app_config.php');

$send_data = array(
	"status" => 0,
	"message" => "Not Found"
);

// get posted data
$data = json_decode(file_get_contents("php://input"));
$data = (array) $data;

if (
	isset($data['mobile']) && $data['mobile'] != ''
) {

	$sql = " select id,username as enrollment_no,firstname as first_name,middlename as middle_name,lastname as last_name,idnumber as sub_institute_id,CONCAT_WS(',',phone1,phone2) as mobile 
			from mdl_user 
			where username='" . $data['mobile'] . "' ";
	$res = mysqli_query($conn, $sql);

	if (mysqli_num_rows($res) > 0) {
		$send_data = array(
			"status" => 1,
			"message" => "Found"
		);
	}

} else {
	http_response_code(401);

	$send_data = array(
		"status" => 0,
		"message" => "Bad Request."
	);
		
}

echo json_encode($send_data);
exit;



$json = array();
error_reporting(0);

$mobile = $_REQUEST["mobile"];

$sql = " select id,username as enrollment_no,firstname as first_name,middlename as middle_name,lastname as last_name,idnumber as sub_institute_id,CONCAT_WS(',',phone1,phone2) as mobile 
from mdl_user where username='" . $mobile . "' ";
$res = mysqli_query($conn, $sql);

$response["result"] = array();
$stu = array();

//echo "R=".mysqli_num_rows($res);
if (mysqli_num_rows($res) > 0) {
	$stu["message"] = "Y";
	array_push($response["result"], $stu);
} else {
	$stu["message"] = "N";
	array_push($response["result"], $stu);
}
echo str_replace('\/', '/', json_encode($response));
