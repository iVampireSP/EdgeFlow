<?php

namespace Applications\Models;

use Illuminate\Database\Eloquent\Model;

class Server extends Model {
    public $fillable = [
        'name', 'motd', 'ip_port', 'version', 'token', 'status', 'client_id',
    ];
    public static function current($token) {
        return self::where('token', $token);
    }
}