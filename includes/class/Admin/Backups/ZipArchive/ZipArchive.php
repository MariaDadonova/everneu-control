<?php

require_once ABSPATH . 'wp-admin/includes/class-pclzip.php';

function Zip($source, $destination) {
    $source = str_replace('\\', '/', realpath($source));
    $destination = str_replace('\\', '/', $destination);

    error_log("PclZip 14 - source: " . $source);

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
            error_log("PclZip 22 - file: " . $file);

            $basename = basename($file);
            if ($basename === '.' || $basename === '..') continue;

            $realPath = str_replace('\\', '/', realpath($file));

            error_log("PclZip 31 - file: " . $realPath);

            if (stripos($realPath, 'backups') !== false || stripos($realPath, '.git') !== false || stripos($realPath, '.idea') !== false) {
                continue;
            }

            $localName = str_replace($source . '/', '', $realPath);

            if (is_dir($realPath)) {
                // PclZip doesn’t need to explicitly add directories
                continue;
            }

            if (stripos($realPath, '.sql') !== false) {
                $file_list[] = [
                    PCLZIP_ATT_FILE_NAME => $realPath,
                    PCLZIP_ATT_FILE_NEW_SHORT_NAME => $localName
                ];
                error_log("PclZip 43 - added .sql file: $localName");
            } else {
                $tmp_path = tempnam(sys_get_temp_dir(), 'pclzip');
                file_put_contents($tmp_path, file_get_contents($realPath));
                $file_list[] = [
                    PCLZIP_ATT_FILE_NAME => $tmp_path,
                    PCLZIP_ATT_FILE_NEW_SHORT_NAME => $localName
                ];
                error_log("PclZip 45 - added from string: $localName");
            }
        }
    } else if (is_file($source)) {
        $file_list[] = [
            PCLZIP_ATT_FILE_NAME => $source,
            PCLZIP_ATT_FILE_NEW_SHORT_NAME => basename($source)
        ];
        error_log("PclZip 50 - added single file: " . basename($source));
    }

    if (empty($file_list)) {
        error_log("PclZip: No files to archive.");
        return false;
    }

    $result = $archive->create($file_list);
    error_log("PclZip close result: " . ($result ? 'success' : 'fail'));
    return $result !== 0;
}