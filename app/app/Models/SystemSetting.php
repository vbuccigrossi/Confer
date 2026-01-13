<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Cache;

class SystemSetting extends Model
{
    protected $fillable = [
        'group',
        'key',
        'value',
        'is_encrypted',
    ];

    protected $casts = [
        'is_encrypted' => 'boolean',
    ];

    /**
     * Get a setting value by group and key.
     */
    public static function get(string $group, string $key, mixed $default = null): mixed
    {
        $cacheKey = "system_setting.{$group}.{$key}";

        return Cache::remember($cacheKey, 3600, function () use ($group, $key, $default) {
            $setting = static::where('group', $group)->where('key', $key)->first();

            if (!$setting) {
                return $default;
            }

            if ($setting->is_encrypted && $setting->value) {
                try {
                    return Crypt::decryptString($setting->value);
                } catch (\Exception $e) {
                    return $default;
                }
            }

            return $setting->value ?? $default;
        });
    }

    /**
     * Set a setting value.
     */
    public static function set(string $group, string $key, mixed $value, bool $encrypted = false): void
    {
        $storedValue = $value;

        if ($encrypted && $value) {
            $storedValue = Crypt::encryptString($value);
        }

        static::updateOrCreate(
            ['group' => $group, 'key' => $key],
            ['value' => $storedValue, 'is_encrypted' => $encrypted]
        );

        // Clear cache
        Cache::forget("system_setting.{$group}.{$key}");
        Cache::forget("system_settings.{$group}");
    }

    /**
     * Get all settings for a group.
     */
    public static function getGroup(string $group): array
    {
        $cacheKey = "system_settings.{$group}";

        return Cache::remember($cacheKey, 3600, function () use ($group) {
            $settings = static::where('group', $group)->get();
            $result = [];

            foreach ($settings as $setting) {
                $value = $setting->value;

                if ($setting->is_encrypted && $value) {
                    try {
                        $value = Crypt::decryptString($value);
                    } catch (\Exception $e) {
                        $value = null;
                    }
                }

                $result[$setting->key] = $value;
            }

            return $result;
        });
    }

    /**
     * Set multiple settings at once.
     */
    public static function setMany(string $group, array $settings, array $encryptedKeys = []): void
    {
        foreach ($settings as $key => $value) {
            $encrypted = in_array($key, $encryptedKeys);
            static::set($group, $key, $value, $encrypted);
        }
    }

    /**
     * Clear all cached settings for a group.
     */
    public static function clearGroupCache(string $group): void
    {
        $settings = static::where('group', $group)->get();

        foreach ($settings as $setting) {
            Cache::forget("system_setting.{$group}.{$setting->key}");
        }

        Cache::forget("system_settings.{$group}");
    }
}
