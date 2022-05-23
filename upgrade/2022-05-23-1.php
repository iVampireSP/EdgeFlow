<?php

require_once '../database.php';

use Illuminate\Database\Capsule\Manager;



// add money column to players table
Manager::schema()->table('players', function ($table) {
    $table->float('money')->unsigned(false)->change();
});