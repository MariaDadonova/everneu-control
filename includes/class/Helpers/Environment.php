<?php

namespace EVN\Helpers;

class Environment {

    public static function detect(): string {
        $url = get_home_url();

        if (stripos($url, 'wpenginepowered.com') === false && stripos($url, 'wpengine.com') === false) {
            return 'production';
        } elseif (stripos($url, 'stg') !== false) {
            return 'staging';
        } elseif (stripos($url, 'dev') !== false) {
            return 'development';
        }

        return 'unknown';
    }

    public static function isProduction(): bool {
        return self::detect() === 'production';
    }

    public static function isStaging(): bool {
        return self::detect() === 'staging';
    }

    public static function isDevelopment(): bool {
        return self::detect() === 'development';
    }
}