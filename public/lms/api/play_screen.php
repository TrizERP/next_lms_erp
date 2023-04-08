<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");


$send_data = array(
	"status" => 1,
	"message" => "Success"
);


// $response["play_screen"] = array();
$data = array();

$data["android"]["type"] = "android";
$data["android"]["appVersion"] = "1.0.5";
$data["android"]["isUpdate"] = 0;
$data["android"]["isComplusory"] = 0;
$data["android"]["is_maintenance"] = 0;
$data["android"]["maintenance_message"] = "Application is under maintenance. Please try after some time.";
$data["android"]["message"] = "New version 1.0.5 Available";

$data["ios"]["type"] = "ios";
$data["ios"]["appVersion"] = "1.0.5";
$data["ios"]["isUpdate"] = 0;
$data["ios"]["isComplusory"] = 0;
$data["ios"]["is_maintenance"] = 0;
$data["ios"]["maintenance_message"] = "Application is under maintenance. Please try after some time.";
$data["ios"]["message"] = "New version 1.0.5 Available";

$send_data["data"] = $data;

echo json_encode($send_data);
exit;
/*
$response2["play_screen"] = array();
$json2 = array();

$json2["ios_type"] = "ios"; 
$json2["appVersion"] = "1.0.2";
$json2["isUpdate"] = "0";
$json2["isComplusory"] = "0";
$json2["is_maintenance"] = "0";
$json2["maintenance_message"] = "Application is under maintenance. Please try after some time.";
$json2["message"] = "New version 1.0.2 Available";

array_push($response["play_screen"], $json2);
//echo str_replace('\/','/',json_encode($response2));

array_push($response["play_screen"], $json);
echo str_replace('\/','/',json_encode($response));
*/
/*
[{"ios_type":"android","appVersion":"1.0.1","isUpdate":0,"isComplusory":"0","is_maintenance":"0","maintenance_message":"Application is under maintenance. Please try after some time.","message":"New version 1.0.1 Available"},

{"ios_type":"ios","appVersion":"1.0.1","isUpdate":0,"isComplusory":"0","is_maintenance":"0","maintenance_message":"Application is under maintenance. Please try after some time.","message":"New version 1.0.1 Available"}]
*/


?>