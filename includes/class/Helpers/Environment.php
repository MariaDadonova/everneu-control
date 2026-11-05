<?php

namespace EVN\Helpers;

/**
 *
 *  Environment detection
 *
 *  Production is determined ONLY by the EVERNEU_PRODUCTION_DOMAINS constant
 *  in wp-config.php:
 *
 *      define('EVERNEU_PRODUCTION_DOMAINS', ['example.com']);
 *
 *  - constant defined, current domain listed      -> production
 *  - constant defined, current domain not listed  -> non-production (emails blocked)
 *  - constant missing or empty                    -> production (emails delivered),
 *                                                    admin warning is shown
 *
 *  Delivering production emails is more important than blocking dev emails,
 *  so a missing configuration always falls back to production.
 *
 *  The current domain is taken from the request (HTTP_HOST), not from
 *  the database, so copying production to another environment
 *  doesn't make it look like production. See getCurrentHost().
 *
 *  Pantheon / WP_ENV / URL checks are informational only: they label
 *  non-production environments and never affect isProduction().
 *
 */

class Environment {

    const CONSTANT = 'EVERNEU_PRODUCTION_DOMAINS';

    /**
     * The constant is defined and contains at least one domain.
     * A defined but empty constant is treated as missing, otherwise
     * a typo in wp-config.php would block production emails.
     */
    public static function isConfigured(): bool {
        return !empty(self::getProductionDomains());
    }

    public static function getProductionDomains(): array {
        if (!defined(self::CONSTANT)) {
            return [];
        }

        $value = constant(self::CONSTANT);

        // Allow a comma-separated string as well as an array
        if (is_string($value)) {
            $value = explode(',', $value);
        }

        if (!is_array($value)) {
            return [];
        }

        $domains = array_map([self::class, 'normalizeHost'], $value);

        return array_values(array_unique(array_filter($domains)));
    }

    /**
     * Current domain to compare with the whitelist.
     *
     * Web requests (including WP-Cron, which runs via an HTTP request):
     * the host comes from the request itself, so it can't be affected
     * by a copied database or copied files.
     *
     * WP-CLI and other requests without a host: falls back to home_url()
     * from the database. Known limitation: on a site copied from production
     * before search-replace, WP-CLI would see the production domain.
     */
    public static function getCurrentHost(): string {
        if (!self::isCli() && !empty($_SERVER['HTTP_HOST'])) {
            $host = self::normalizeHost(wp_unslash($_SERVER['HTTP_HOST']));

            if ($host !== '') {
                return $host;
            }
        }

        return self::normalizeHost((string) wp_parse_url(get_home_url(), PHP_URL_HOST));
    }

    private static function isCli(): bool {
        return (defined('WP_CLI') && WP_CLI) || PHP_SAPI === 'cli';
    }

    public static function isProduction(): bool {
        if (!self::isConfigured()) {
            return true;
        }

        return in_array(self::getCurrentHost(), self::getProductionDomains(), true);
    }

    /**
     * Fail-closed check for backups: production only if the current domain
     * is explicitly listed. A missing configuration is NOT production here,
     * so a dev site without the constant doesn't upload to the production Dropbox.
     */
    public static function isConfirmedProduction(): bool {
        return self::isConfigured()
            && in_array(self::getCurrentHost(), self::getProductionDomains(), true);
    }


    /**
     * Informational label for the current environment.
     * Returns: production | staging | development | local | unknown
     */
    public static function detect(): string {

        if (self::isProduction()) {
            return 'production';
        }

        // Pantheon sets this variable itself, it's not copied with the site
        switch (strtolower((string) self::env('PANTHEON_ENVIRONMENT'))) {
            case 'test':
                return 'staging';
            case 'dev':
                return 'development';
            // 'live' is not in the whitelist here: misconfiguration, falls through to 'unknown'
        }

        switch (strtolower((string) self::env('WP_ENV'))) {
            case 'staging':
                return 'staging';
            case 'development':
            case 'dev':
                return 'development';
        }

        $host = self::getCurrentHost();

        // Checked first: "devsite.local" is a local environment, not development
        if (self::isLocalHost($host)) {
            return 'local';
        }


        if (stripos($host, 'stg') !== false || stripos($host, 'staging') !== false) {
            return 'staging';
        }

        if (stripos($host, 'dev') !== false) {
            return 'development';
        }

        return 'unknown';
    }

    const LOCAL_SUFFIXES = ['localhost', 'local', 'loc', 'test'];

    private static function isLocalHost(string $host): bool {
        foreach (self::LOCAL_SUFFIXES as $suffix) {
            if ($host === $suffix || substr($host, -strlen($suffix) - 1) === '.' . $suffix) {
                return true;
            }
        }

        return false;
    }

    public static function isLocal(): bool {
        return self::detect() === 'local';
    }

    public static function isStaging(): bool {
        return self::detect() === 'staging';
    }

    public static function isDevelopment(): bool {
        return self::detect() === 'development';
    }

    public static function isUnknown(): bool {
        return self::detect() === 'unknown';
    }

    /**
     * Lowercase, strip scheme/path/port and leading "www."
     * so "https://www.Example.com/" and "example.com" match.
     */
    private static function normalizeHost($value): string {
        $value = strtolower(trim((string) $value));

        if ($value === '') {
            return '';
        }

        if (strpos($value, '://') === false) {
            $value = 'http://' . $value;
        }

        $host = (string) wp_parse_url($value, PHP_URL_HOST);

        return preg_replace('/^www\./', '', $host);
    }

    /**
     * $_ENV can be empty depending on PHP's variables_order, getenv() is a fallback.
     */
    private static function env(string $name): string {
        if (!empty($_ENV[$name])) {
            return (string) $_ENV[$name];
        }

        $value = getenv($name);

        return $value === false ? '' : (string) $value;
    }


}