<?php

use GatewayWorker\Lib\Gateway;

require_once 'vendor/autoload.php';

Gateway::$registerAddress = '127.0.0.1:14301';

Gateway::sendToAll(json_encode([
    'event' => 'upgrade',
]));

echo 'Broadcast upgrade event success.' . PHP_EOL;