<?php

require_once ABSPATH . 'wp-admin/includes/class-pclzip.php';

function Zip($source, $destination) {
    $source = str_replace('\\', '/', realpath($source));
    $destination = str_replace('\\', '/', $destination);

    error_log("PclZip - Source: $source");
    error_log("PclZip - Destination: $destination");

    if (!file_exists($source)) {
        error_log("PclZip: Source does not exist: $source");
        return false;
    }

    $archive = new PclZip($destination);
    $file_list = [];

    if (is_dir($source)) {
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($source),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($files as $file) {
            $file = str_replace('\\', '/', $file);
            $basename = basename($file);
            if ($basename === '.' || $basename === '..') continue;

            $realPath = str_replace('\\', '/', realpath($file));
            if (!$realPath || is_dir($realPath)) continue;

            if (stripos($realPath, 'backups') !== false || stripos($realPath, '.git') !== false || stripos($realPath, '.idea') !== false) {
                continue;
            }

            $localName = ltrim(str_replace(ABSPATH, '', $realPath), '/');

            $file_list[] = [
                PCLZIP_ATT_FILE_NAME => $realPath,
                PCLZIP_ATT_FILE_NEW_SHORT_NAME => $localName
            ];

            error_log("PclZip - Added: $localName");
        }
    } elseif (is_file($source)) {
        $localName = ltrim(str_replace(ABSPATH, '', $source), '/');
        $file_list[] = [
            PCLZIP_ATT_FILE_NAME => $source,
            PCLZIP_ATT_FILE_NEW_SHORT_NAME => $localName
        ];
        error_log("PclZip - Added single file: $localName");
    }

    if (empty($file_list)) {
        error_log("PclZip: No files to archive.");
        return false;
    }

    $result = $archive->create($file_list);
    error_log("PclZip - Archive create result: " . ($result ? 'success' : 'fail'));
    return $result !== 0;
}