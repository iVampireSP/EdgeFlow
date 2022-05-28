<?php

require_once '../database.php';

use Applications\Models\Setting;
use Illuminate\Database\Capsule\Manager;

Manager::schema()->create('settings', function ($table) {
    $table->string('key')->index()->unique()->primary();
    $table->string('value')->index()->nullable();

    $table->timestamps();
});

Setting::set('health', 'ok');

echo 'test:' . Setting::get('health')->value . PHP_EOL;