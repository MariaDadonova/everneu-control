<?php

namespace EVN\Helpers;

class Environment {

    public static function detect(): string {

        if (!empty($_ENV['PANTHEON_ENVIRONMENT'])) {
            switch (strtolower($_ENV['PANTHEON_ENVIRONMENT'])) {
                case 'live':
                    return 'production';
                case 'test':
                    return 'staging';
                case 'dev':
                    return 'development';
            }
        }

        if (!empty($_ENV['WP_ENV'])) {
            switch (strtolower($_ENV['WP_ENV'])) {
                case 'production':
                case 'prod':
                    return 'production';
                case 'staging':
                    return 'staging';
                case 'development':
                case 'dev':
                    return 'development';
            }
        }

        $url = get_home_url();

        if (stripos($url, 'wpenginepowered.com') === false && stripos($url, 'wpengine.com') === false && stripos($url, 'pantheonsite.io') === false) {
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

    public static function isUnknown(): bool {
        return self::detect() == 'unknown';
    }
}