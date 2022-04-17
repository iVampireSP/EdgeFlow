<?php

use GatewayWorker\Lib\Gateway;

require_once 'vendor/autoload.php';


Gateway::sendToAll(json_encode([
    'event' => 'upgrade',
]));

echo 'Broadcast upgrade event success.';