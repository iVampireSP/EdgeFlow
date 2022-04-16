<?php

namespace Applications\Models;

use Illuminate\Database\Eloquent\Model;

class Server extends Model {
    public static function current($token) {
        return self::where('token', $token);
    }
}