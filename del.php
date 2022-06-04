<?php

require_once 'database.php';

use Applications\Models\Server;

echo 'Please input server ip link 1.2.3.4:19132' . PHP_EOL;
$ip_port = read();

if (empty($ip_port)) {
    echo 'empty server address' . PHP_EOL;
    exit;
}

$server = Server::where('ip_port', $ip_port)->first();

if ($server) {
    echo 'Are you sure delete ' . $server->name . '?' . PHP_EOL;
    echo 'yes/no' . PHP_EOL;
    $input = read();
    if ($input == 'yes') {
        $server->delete();
        echo 'Server deleted!' . PHP_EOL;
    } else {
        echo 'Server not deleted!' . PHP_EOL;
    }
} else {
    echo 'Server not found!' . PHP_EOL;
}