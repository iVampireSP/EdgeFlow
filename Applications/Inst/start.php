<?php

use Workerman\Redis\Client;
use GatewayWorker\Lib\Gateway;
use Applications\Models\Setting;
use Workerman\Connection\TcpConnection;
use Illuminate\Database\Capsule\Manager;
use Workerman\Lib\Timer;

$config = json_decode(file_get_contents('config.json'));

$capsule = new Manager;
// 创建链接
$capsule->addConnection([
    'driver' => 'mysql',
    'host' => $config->database->host,
    'database' => $config->database->db_name,
    'username' => $config->database->user,
    'password' => $config->database->password,
    'charset' => 'utf8mb4',
    'port' => $config->database->port ?? 3306,
    'collation' => 'utf8mb4_general_ci',
    'prefix' => '',
]);
// 设置全局静态可访问DB
$capsule->setAsGlobal();
// 启动Eloquent （如果只使用查询构造器，这个可以注释）
$capsule->bootEloquent();

// Gateway::sendToAll('a', 'login_success', '23333333333333333');

Events::$db = $capsule;
Events::$debug = $config->debug ?? false;

try {
    Events::$health = Setting::get('health');
} catch (Exception $e) {
    echo 'Unable get health status, trying install.' . PHP_EOL;
    echo $e->getMessage() . PHP_EOL;
    require_once 'install.php';
}

// $redis = new Client('redis://127.0.0.1:6379');

// $auth
// $redis->auth('password', '');
// Events::$redis = $redis;