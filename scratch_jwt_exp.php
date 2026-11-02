<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$jwt = app(GenTux\Jwt\JwtToken::class);
$token = (string) $jwt->createToken([
    'id' => 1,
    'sub_institute_id' => '1',
    'is_admin' => 2,
    'client_id' => 1,
    'user_profile_id' => 1,
    'is_student' => false,
    'iat' => time(),
    'exp' => time() + 86400 * 30,
]);
echo "TOKEN: " . $token . PHP_EOL;

$jwt2 = app(GenTux\Jwt\JwtToken::class);
$jwt2->setToken($token);
echo "VALID: " . ($jwt2->validate() ? 'YES' : 'NO') . PHP_EOL;

