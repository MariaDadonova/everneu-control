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
            new RecursiveDirectoryIterator($source, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($files as $file) {
            $file = str_replace('\\', '/', $file);
            $realPath = str_replace('\\', '/', realpath($file));

            if (
                stripos($realPath, '/backups/') !== false ||
                stripos($realPath, '/.git/') !== false ||
                stripos($realPath, '/.idea/') !== false
            ) {
                error_log("PclZip - Skipped: $realPath");
                continue;
            }

            $localName = ltrim(str_replace($source, '', $realPath), '/');

            if (is_dir($realPath)) {
                continue;
            }

            if (stripos($realPath, '.sql') !== false) {
                $file_list[] = [
                    PCLZIP_ATT_FILE_NAME => $realPath,
                    PCLZIP_ATT_FILE_NEW_SHORT_NAME => $localName
                ];
                error_log("PclZip - Added .sql: $localName");
            } else {
                $tmp_path = tempnam(sys_get_temp_dir(), 'pclzip');
                if ($tmp_path && file_put_contents($tmp_path, file_get_contents($realPath)) !== false) {
                    $file_list[] = [
                        PCLZIP_ATT_FILE_NAME => $tmp_path,
                        PCLZIP_ATT_FILE_NEW_SHORT_NAME => $localName
                    ];
                    error_log("PclZip - Added from string: $localName");
                } else {
                    error_log("PclZip - Failed to copy to temp: $realPath");
                }
            }
        }
    } elseif (is_file($source)) {
        $file_list[] = [
            PCLZIP_ATT_FILE_NAME => $source,
            PCLZIP_ATT_FILE_NEW_SHORT_NAME => basename($source)
        ];
        error_log("PclZip - Added single file: " . basename($source));
    }

    if (empty($file_list)) {
        error_log("PclZip: No files to archive.");
        return false;
    }

    $result = $archive->create($file_list);
    error_log("PclZip - Archive create result: " . ($result !== 0 ? 'success' : 'fail'));

    return $result !== 0;
}