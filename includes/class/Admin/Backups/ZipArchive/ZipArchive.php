<?php

require_once ABSPATH . 'wp-admin/includes/class-pclzip.php';

function Zip($source, $destination) {
    $source = realpath($source);
    if (!$source || !file_exists($source)) {
        error_log("PclZip: Source not found: $source");
        return false;
    }

    // Чтобы избежать архивации самого архива
    if (strpos($destination, $source) === 0) {
        error_log("PclZip: Destination is inside source — skipping to avoid recursion");
        return false;
    }

    $archive = new PclZip($destination);

    $excluded_extensions = ['zip', 'log', 'tmp', 'gz'];
    $excluded_dirs = ['backups', '.git', '.idea'];

    $file_list = [];

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($source),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($iterator as $file) {
        $path = realpath($file);
        if (!$path || in_array(basename($path), ['.', '..'])) continue;

        $relative = ltrim(str_replace($source, '', $path), '/');

        // Исключения по директориям
        foreach ($excluded_dirs as $dir) {
            if (strpos($relative, $dir) !== false) continue 2;
        }

        // Исключения по расширениям
        $ext = pathinfo($path, PATHINFO_EXTENSION);
        if (in_array(strtolower($ext), $excluded_extensions)) continue;

        $file_list[] = [
            PCLZIP_ATT_FILE_NAME => $path,
            PCLZIP_ATT_FILE_NEW_SHORT_NAME => $relative
        ];

        error_log("PclZip - added: $relative");
    }

    if (empty($file_list)) {
        error_log("PclZip: no files found to archive.");
        return false;
    }

    $result = $archive->create($file_list);
    error_log("PclZip - archive result: " . ($result ? 'success' : 'fail'));

    return $result !== 0;
}
