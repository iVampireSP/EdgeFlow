<?php

require_once 'database.php';

use Applications\Models\Server;
use Illuminate\Database\Capsule\Manager;


echo 'Please input server address and port like 1.2.3.4:19132' . PHP_EOL;
$ip_port = read();

if (empty($ip_port)) {
    echo 'empty server address' . PHP_EOL;
    exit;
}


// random string
$random_string = bin2hex(random_bytes(16));

$server = new Server();
$server->ip_port = $ip_port;
$server->token = $random_string;
$server->save();


echo 'Token: ' . $server->token . PHP_EOL;
echo 'Server added!' . PHP_EOL;