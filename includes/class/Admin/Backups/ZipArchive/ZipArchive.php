<?php

require_once ABSPATH . 'wp-admin/includes/class-pclzip.php';

function Zip($source, $final_destination) {
    $source = realpath($source);
    if (!$source || !file_exists($source)) {
        error_log("PclZip: Source not found: $source");
        return false;
    }

    $tmp_zip_path = tempnam(sys_get_temp_dir(), 'everneu_') . '.zip';
    error_log("PclZip: Temp zip path: $tmp_zip_path");

    $archive = new PclZip($tmp_zip_path);

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

        foreach ($excluded_dirs as $dir) {
            if (strpos($relative, $dir) !== false) continue 2;
        }

        $ext = pathinfo($path, PATHINFO_EXTENSION);
        if (in_array(strtolower($ext), $excluded_extensions)) continue;

        $file_list[] = [
            PCLZIP_ATT_FILE_NAME => $path,
            PCLZIP_ATT_FILE_NEW_SHORT_NAME => $relative
        ];

        error_log("PclZip - added: $relative");
    }

    if (empty($file_list)) {
        error_log("PclZip: no files to archive.");
        return false;
    }

    $result = $archive->create($file_list);
    error_log("PclZip - archive result: " . ($result ? 'success' : 'fail'));

    if (!$result) return false;

    if (!copy($tmp_zip_path, $final_destination)) {
        error_log("PclZip: Failed to move zip to final destination: $final_destination");
        return false;
    }

    @unlink($tmp_zip_path);
    return true;
}