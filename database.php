<?php

require_once 'vendor/autoload.php';

use Illuminate\Database\Capsule\Manager;

$config = json_decode(file_get_contents(__DIR__ . '/config.json'));

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

$capsule->setAsGlobal();
$capsule->bootEloquent();

function read()
{
    $fp = fopen('php://stdin', 'r');
    $input = fgets($fp, 255);
    fclose($fp);
    $input = trim($input);
    return $input;
}
