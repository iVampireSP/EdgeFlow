<?php

require_once 'database.php';

use Illuminate\Database\Capsule\Manager;

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