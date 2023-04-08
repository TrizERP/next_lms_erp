<?php
require 'auth/vendor/autoload.php';
require_once("auth/keys.php");
require_once('app_config.php');


use \Firebase\JWT\JWT;

$jwt_arr = array(
	// 'exp' => time() + 7200,
	"id" => 123,
	"first_name" => 'keyur',
	"last_name" => 'modi',
	"roll_no" => 12,
);

$jwt = JWT::encode($jwt_arr, $secrateKey);

echo $jwt;
