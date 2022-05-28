<?php

use GatewayWorker\Lib\Gateway;

require_once 'vendor/autoload.php';

Gateway::$registerAddress = '127.0.0.1:14301';

foreach ($argv as $arg) {
    Gateway::sendToAll(json_encode([
        'event' => 'event',
        'data' => [
            'client_id' => null,
            'server_name' => 'Flow',
            'msg' => $arg
        ]
    ]));
}

echo 'Broadcast event success.';
