<?php

namespace Applications\Models;

use Illuminate\Database\Eloquent\Model;

class Player extends Model {
    public static function xuid($xuid) {
        return self::where('xuid', $xuid);
    }
}