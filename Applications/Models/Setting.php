<?php

namespace Applications\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    public $fillable = [
        'key', 'value'
    ];

    public static function set($key, $value)
    {
        return Setting::create(['key' => $key, 'value' => $value]);
    }

    public static function get($key)
    {
        return Setting::where('key', $key)->first()->value;
    }
}
