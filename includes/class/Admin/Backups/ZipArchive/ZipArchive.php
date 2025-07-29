<?php

function Zip($source, $destination){
    if (!extension_loaded('zip')) {
        error_log("ZipArchive extension is not loaded");
        return false;
    }
    if (!file_exists($source)) {
        error_log("Source path does not exist: $source");
        return false;
    }

    // Удаляем архив, если он уже есть
    if (file_exists($destination)) {
        error_log("File $destination already exists, deleting it before creating new archive");
        if (!unlink($destination)) {
            error_log("Failed to delete existing archive file: $destination");
            return false;
        }
    }

    $zip = new ZipArchive();
    $res = $zip->open($destination, ZIPARCHIVE::CREATE);
    if ($res !== true) {
        error_log("ZipArchive open failed with code: $res");
        return false;
    }

    $source = str_replace('\\', '/', realpath($source));
    error_log("Zip Archive - source: " . $source);

    if (is_dir($source) === true){
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source), RecursiveIteratorIterator::SELF_FIRST);

        foreach ($files as $file){
            $file = str_replace('\\', '/', $file);

            // Игнорируем "." и ".."
            if(in_array(substr($file, strrpos($file, '/')+1), ['.', '..']))
                continue;

            $file = realpath($file);
            $file = str_replace('\\', '/', $file);

            // Исключаем ненужные файлы и папки
            if (
                (stripos($file, 'backups') !== false) ||
                (stripos($file, '.git') !== false) ||
                (stripos($file, '.idea') !== false) ||
                preg_match('/\.(zip|log|tmp|gz)$/i', $file)
            ) {
                continue;
            }

            $localPath = ltrim(str_replace($source . '/', '', $file), '/');

            if (is_dir($file)) {
                $zip->addEmptyDir($localPath);
                error_log("Zip Archive - add dir: $localPath");
            } elseif (is_file($file)) {
                if (stripos($file, '.sql') !== false) {
                    $zip->addFile($file, $localPath);
                    error_log("Zip Archive - add file: $localPath");
                } else {
                    $zip->addFromString($localPath, file_get_contents($file));
                    error_log("Zip Archive - add from string: $localPath");
                }
            }
        }
    } elseif (is_file($source) === true) {
        $zip->addFromString(basename($source), file_get_contents($source));
        error_log("Zip Archive - add from string single file");
    }

    $result = $zip->close();
    error_log("ZipArchive close result: " . ($result ? 'success' : 'fail'));

    return $result;
}