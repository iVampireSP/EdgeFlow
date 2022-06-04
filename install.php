<?php

require_once 'database.php';

use Applications\Models\Setting;
use Illuminate\Database\Capsule\Manager;


echo 'Install script will drop all tables! You can press ^C to cancel in 5s.' . PHP_EOL;
sleep(5);
echo 'Dropping database...' . PHP_EOL;
Manager::schema()->dropIfExists('players');
Manager::schema()->dropIfExists('servers');
Manager::schema()->dropIfExists('messages');
Manager::schema()->dropIfExists('settings');

try {
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
} catch (Exception $e) {
    echo 'Database init failed!' . PHP_EOL;
    exit;
}


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

Manager::schema()->table('players', function ($table) {
    $table->unsignedDouble('money')->default(0)->index();
});

Manager::schema()->table('players', function ($table) {
    $table->float('money')->unsigned(false)->change();
});

Manager::schema()->table('servers', function ($table) {
    $table->string('alert')->nullable();
});

Manager::schema()->create('settings', function ($table) {
    $table->string('key')->index()->unique()->primary();
    $table->string('value')->index()->nullable();

    $table->timestamps();
});

Setting::set('health', 'ok');

echo 'test:' . Setting::get('health') . PHP_EOL;



echo 'Install succeeded!' . PHP_EOL;