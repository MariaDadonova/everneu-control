<?php

function Zip($source, $destination){
    if (!extension_loaded('zip') || !file_exists($source)) {
        error_log("Zip Archive error: zip extension not loaded or source doesn't exist.");
        return false;
    }

    // Проверка: доступна ли папка назначения
    $destinationDir = dirname($destination);
    if (!is_writable($destinationDir)) {
        error_log("Zip Archive error: destination dir not writable - " . $destinationDir);
        return false;
    }

    $zip = new ZipArchive();

    $openResult = $zip->open($destination, ZIPARCHIVE::CREATE | ZIPARCHIVE::OVERWRITE);
    if ($openResult !== true) {
        error_log("Zip Archive error: failed to open archive. Code: $openResult");
        return false;
    }

    $source = str_replace('\\', '/', realpath($source));
    error_log("Zip Archive - source: " . $source);

    if (is_dir($source)){
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($files as $file){
            $file = str_replace('\\', '/', realpath($file));
            error_log("Zip Archive - processing file: $file");

            if (stripos($file, 'backups') !== false || stripos($file, '.git') !== false || stripos($file, '.idea') !== false || stripos($file, '_wpeprivate') !== false) {
                continue;
            }

            $localName = str_replace($source . '/', '', $file);

            if (is_dir($file)) {
                $zip->addEmptyDir($localName);
                error_log("Zip Archive - added dir: $localName");
            } elseif (is_file($file)) {
                if (stripos($file, '.sql') !== false) {
                    $zip->addFile($file, $localName);
                    error_log("Zip Archive - added file: $localName");
                } else {
                    $zip->addFromString($localName, file_get_contents($file));
                    error_log("Zip Archive - added from string: $localName");
                }
            }
        }
    } elseif (is_file($source)) {
        $zip->addFromString(basename($source), file_get_contents($source));
        error_log("Zip Archive - added single file: " . basename($source));
    }

    $result = $zip->close();
    error_log("Zip Archive - close result: " . ($result ? 'success' : 'fail'));

    // Проверка: создан ли zip-файл
    if (!file_exists($destination)) {
        error_log("Zip Archive error: destination file not found after close.");
        return false;
    }

    return $result;
}