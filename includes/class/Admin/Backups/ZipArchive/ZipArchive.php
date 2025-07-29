<?php

function Zip($source, $final_destination){
    if (!extension_loaded('zip') || !file_exists($source)) {
        error_log("Zip: Zip extension not loaded or source doesn't exist");
        return false;
    }

    $source = str_replace('\\', '/', realpath($source));
    $tmp_destination = sys_get_temp_dir() . '/' . basename($final_destination);

    error_log("Zip Archive - source: $source");
    error_log("Zip Archive - tmp destination: $tmp_destination");

    $zip = new ZipArchive();
    if (!$zip->open($tmp_destination, ZipArchive::CREATE | ZipArchive::OVERWRITE)) {
        error_log("Zip: Cannot open temp archive for writing");
        return false;
    }

    if (is_dir($source)) {
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($source),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($files as $file) {
            $file = str_replace('\\', '/', $file);

            if (in_array(substr($file, strrpos($file, '/')+1), ['.', '..'])) continue;

            $realPath = realpath($file);
            if (!$realPath) continue;
            $realPath = str_replace('\\', '/', $realPath);

            if (
                stripos($realPath, '/backups/') !== false ||
                stripos($realPath, '.git') !== false ||
                stripos($realPath, '.idea') !== false ||
                preg_match('/\.(zip|log|tmp|gz)$/i', $realPath)
            ) {
                continue;
            }

            $localName = ltrim(str_replace($source . '/', '', $realPath), '/');

            if (is_dir($realPath)) {
                $zip->addEmptyDir($localName);
                error_log("Zip Archive - add dir: $localName");
            } elseif (is_file($realPath)) {
                $zip->addFile($realPath, $localName);
                error_log("Zip Archive - add file: $localName");
            }
        }

    } elseif (is_file($source)) {
        $zip->addFile($source, basename($source));
        error_log("Zip Archive - add single file: " . basename($source));
    }

    $result = $zip->close();
    error_log("Zip Archive - close result: " . ($result ? 'success' : 'fail'));

    if (!$result) {
        return false;
    }

    if (!@rename($tmp_destination, $final_destination)) {
        error_log("Zip Archive - failed to move archive to final destination");
        return false;
    }

    error_log("Zip Archive - moved to: $final_destination");
    return true;
}