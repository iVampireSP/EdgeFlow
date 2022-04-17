<?php

require_once 'vendor/autoload.php';

use Illuminate\Database\Capsule\Manager;

$config = json_decode(file_get_contents('config.json'));



$capsule = new Manager;
// 创建链接
$capsule->addConnection([
    'driver' => 'mysql',
    'host' => 'localhost',
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

Manager::schema()->create('players', function ($table) {
    $table->id();
    $table->string('name')->index();
    $table->string('oauth_name')->index()->nullable();
    $table->unsignedBigInteger('xuid')->index()->nullable()->unique();

    $table->string('email')->index()->nullable();
    $table->text('nbt')->nullable();

    $table->json('data')->nullable();

    $table->timestamps();
});


Manager::schema()->create('servers', function ($table) {
    $table->id();

    $table->string('name')->index()->nullable();
    $table->string('motd')->index()->nullable();
    $table->string('ip_port')->index()->nullable();

    $table->string('version')->index()->nullable();
    $table->string('token')->index()->nullable();

    $table->string('status')->index()->nullable();

    $table->string('client_id')->index()->nullable();


    $table->timestamps();
});

Manager::schema()->create('messages', function ($table) {
    $table->id();

    $table->string('message')->index()->nullable();

    $table->unsignedBigInteger('player_id')->index();

    $table->string('client_id')->index()->nullable();

    $table->timestamps();
});


echo 'Install succeeded!' . PHP_EOL;