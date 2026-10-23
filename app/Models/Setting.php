<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;

#[Fillable(['key', 'value'])]
class Setting extends Model
{
    private const CACHE_KEY = 'asuh.settings';

    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected function casts(): array
    {
        return [
            'value' => 'json',
        ];
    }

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget(self::CACHE_KEY));
        static::deleted(fn () => Cache::forget(self::CACHE_KEY));
    }

    /**
     * Read a setting, falling back to config('asuh.settings').
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $defaults = config('asuh.settings');

        if (! array_key_exists($key, $defaults)) {
            throw new InvalidArgumentException("Unknown setting [{$key}].");
        }

        $stored = Cache::rememberForever(
            self::CACHE_KEY,
            fn () => static::query()->pluck('value', 'key')->all(),
        );

        return $stored[$key] ?? $defaults[$key] ?? $default;
    }

    public static function set(string $key, mixed $value): void
    {
        if (! array_key_exists($key, config('asuh.settings'))) {
            throw new InvalidArgumentException("Unknown setting [{$key}].");
        }

        static::query()->updateOrCreate(['key' => $key], ['value' => $value]);
    }
}
