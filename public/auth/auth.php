<?php
// header("Access-Control-Allow-Origin: *");
// header("Content-Type: application/json; charset=UTF-8");
// header("Access-Control-Allow-Methods: POST");
// header("Access-Control-Max-Age: 3600");
// header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

require 'vendor/autoload.php';
use \Firebase\JWT\JWT;
require_once("keys.php");

// get posted data
$data = $_REQUEST;

$session = array();

// make sure token is not empty
// if (!empty($data->token)) {
if (isset($data['token'])) {

    try {        
        $decoded = JWT::decode($data['token'], $secrateKey, array('HS256'));
        $session = (array) $decoded;
		
		unset($session['exp']);
		unset($session['mobile']);
		$err = 0;
		foreach($session as $key => $val)
		{
			if(isset($_REQUEST[$key]) && $val != $_REQUEST[$key])
			{
				$err++;				
			}
		}
		if($err > 0)
		{
			http_response_code(401);    
			echo json_encode(array(
			"message" => "Bad Request.",
			"status" => 0
			));
			exit;
		}
    } catch (Exception $e) {
    
        http_response_code(200);    
        echo json_encode(array(
            "message" => "Access denied.",
            "status" => 2,
            "error" => $e->getMessage()
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

