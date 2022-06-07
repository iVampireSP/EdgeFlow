<?php

require_once '../database.php';

use Applications\Models\Setting;
use Illuminate\Database\Capsule\Manager;

Manager::schema()->table('servers', function ($table) {
    $table->string('group')->index()->nullable();
});