<?php

namespace EVN\Helpers;

/**
 *
 *  Class for encryption/decryption strings
 *
 */

class Encryption
{
    private static $method = 'AES-256-CBC';
    private static $secret_key;
    private static $secret_iv;

    public static function encrypt($string) {
        self::$secret_key = bin2hex(random_bytes(16));
        self::$secret_iv = bin2hex(random_bytes(16));

        $key = hash('sha256', self::$secret_key);
        $iv = substr(hash('sha256', self::$secret_iv), 0, 16);
        return base64_encode(openssl_encrypt($string, self::$method, $key, 0, $iv)).'salt'.self::$secret_key.'bolt'.self::$secret_iv;
    }

    public static function decrypt($encrypted) {
        preg_match('/salt(.*?)bolt/', $encrypted, $matchBetween);
        self::$secret_key = $matchBetween[1] ?? '';

        self::$secret_iv = '';
        if (preg_match('/bolt(.*)$/', $encrypted, $matchAfter)) {
            self::$secret_iv = $matchAfter[1];
        }

        preg_match('/^(.*?)salt/', $encrypted, $matchBefore);
        $encrypted_key = $matchBefore[1] ?? '';

        $key = hash('sha256', self::$secret_key);
        $iv = substr(hash('sha256', self::$secret_iv), 0, 16);
        return openssl_decrypt(base64_decode($encrypted_key), self::$method, $key, 0, $iv);
    }
}