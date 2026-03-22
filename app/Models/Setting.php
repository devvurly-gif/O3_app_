<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $fillable = ['st_domain', 'st_key', 'st_value'];

    // Get a setting value by domain + key
    public static function get(string $domain, string $key, mixed $default = null): mixed
    {
        return static::where('st_domain', $domain)
            ->where('st_key', $key)
            ->value('st_value') ?? $default;
    }

    // Set a setting value
    public static function set(string $domain, string $key, mixed $value): void
    {
        static::updateOrCreate(
            ['st_domain' => $domain, 'st_key' => $key],
            ['st_value'  => $value]
        );
    }
}
