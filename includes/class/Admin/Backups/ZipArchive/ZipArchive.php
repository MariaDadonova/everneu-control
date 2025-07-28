<?php

function Zip($source, $destination){
    if (!extension_loaded('zip') || !file_exists($source)) {
        return false;
    }

    $zip = new ZipArchive();
    if (!$zip->open($destination, ZIPARCHIVE::CREATE)) {
        return false;
    }

    $source = str_replace('\\', '/', realpath($source));

    if (is_dir($source) === true){
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($files as $file){
            $filePath = str_replace('\\', '/', realpath($file));
            if (!$filePath) continue;

            $relativePath = str_replace($source . '/', '', $filePath);

            if (
                stripos($relativePath, 'backups') !== false ||
                stripos($relativePath, '.git') !== false ||
                stripos($relativePath, '.idea') !== false ||
                $relativePath === 'wp-content/mysql.sql'
            ) {
                continue;
            }

            if (is_dir($filePath)) {
                $zip->addEmptyDir($relativePath);
            } elseif (is_file($filePath)) {
                if (substr($filePath, -4) === '.sql') {
                    $zip->addFile($filePath, $relativePath); // efficient for large files
                } else {
                    $zip->addFromString($relativePath, file_get_contents($filePath));
                }
            }
        }

    } elseif (is_file($source) === true){
        $zip->addFromString(basename($source), file_get_contents($source));
    }

    return $zip->close();
}