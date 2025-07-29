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

    if (strpos($destination, $source) === 0) {
        error_log("PclZip: Destination is inside source, skipping to avoid recursive archive.");
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
            if (!$realPath) continue;

            if (
                stripos($realPath, '/backups/') !== false ||
                stripos($realPath, '.git') !== false ||
                stripos($realPath, '.idea') !== false ||
                preg_match('/\.(zip|log|tmp)$/i', $realPath)
            ) {
                continue;
            }

            $localName = ltrim(str_replace(ABSPATH, '', $realPath), '/');

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
                file_put_contents($tmp_path, file_get_contents($realPath));
                $file_list[] = [
                    PCLZIP_ATT_FILE_NAME => $tmp_path,
                    PCLZIP_ATT_FILE_NEW_SHORT_NAME => $localName
                ];
                error_log("PclZip - Added: $localName");
            }
        }
    } elseif (is_file($source)) {
        $localName = ltrim(str_replace(ABSPATH, '', $source), '/');

        if (preg_match('/\.(zip|log|tmp)$/i', $source)) {
            error_log("PclZip - Skipped excluded file: $source");
            return false;
        }

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
    if ($result == 0) {
        error_log("PclZip error: " . $archive->errorInfo(true));
        return false;
    }

    error_log("PclZip - Archive create result: success");
    return true;
}