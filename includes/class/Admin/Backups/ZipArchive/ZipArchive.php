<?php

function Zip($source, $destination) {
    // Принудительно укажем sys_temp_dir, если не задан или не существует
    if (!ini_get('sys_temp_dir') || !is_dir(ini_get('sys_temp_dir'))) {
        $customTmp = ABSPATH . 'wp-content/tmp';
        if (!is_dir($customTmp)) {
            mkdir($customTmp, 0775, true); // создаём если нет
        }
        ini_set('sys_temp_dir', $customTmp);
        error_log("Zip: sys_temp_dir set to $customTmp");
    }

    $source = realpath(ABSPATH . 'wp-content/wp_everneusandbox_wp_.sql');
    $destination = ABSPATH . 'wp-content/backups/test.zip';

    if (!extension_loaded('zip') || !file_exists($source)) {
        error_log("Zip: extension not loaded or source not found: $source");
        return false;
    }

    $zip = new ZipArchive();
    if (!$zip->open($destination, ZipArchive::CREATE | ZipArchive::OVERWRITE)) {
        error_log("Zip: failed to open destination: $destination");
        return false;
    }

    $basename = basename($source);

    if ($zip->addFile($source, $basename)) {
        error_log("Zip: added $basename");
    } else {
        error_log("Zip: failed to add $basename");
    }

    $closed = $zip->close();
    error_log("Zip: closed with result: " . ($closed ? 'success' : 'fail'));

    return $closed;
}