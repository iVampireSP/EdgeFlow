<?php

require_once '../database.php';

use Illuminate\Database\Capsule\Manager;



// add money column to players table
Manager::schema()->table('servers', function ($table) {
    $table->string('alert')->nullable();
});
