<?php

use GatewayWorker\Lib\Gateway;
use Workerman\Redis\Client;
use Illuminate\Database\Capsule\Manager;
use Workerman\Connection\TcpConnection;

$capsule = new Manager;
// 创建链接
$capsule->addConnection([
    'driver' => 'mysql',
    'host' => 'localhost',
    'database' => 'flow',
    'username' => 'root',
    'password' => '123456',
    'charset' => 'utf8mb4',
    'port' => 3306,
    'collation' => 'utf8mb4_general_ci',
    'prefix' => '',
]);
// 设置全局静态可访问DB
$capsule->setAsGlobal();
// 启动Eloquent （如果只使用查询构造器，这个可以注释）
$capsule->bootEloquent();

// Gateway::sendToAll('a', 'login_success', '23333333333333333');

Events::$db = $capsule;

// $redis = new Client('redis://127.0.0.1:6379');

// $auth
// $redis->auth('password', '');
// Events::$redis = $redis;