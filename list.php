<?php

require_once 'database.php';

use Applications\Models\Server;

$servers  = Server::all();

echo "ID \t Name \t Addr \t Status";

foreach ($servers as $server) {
    echo "#{$server->id} \t {$server->name} \t {$server->ip_port} \t {$server->status}" . PHP_EOL;
}

