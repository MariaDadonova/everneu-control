<?php

namespace EVN\Admin\Backups;

use DropboxAPI;
use EVN\Helpers\Encryption;
use MySql;

include_once __DIR__ . '/DropboxAPIClient/DropboxAPI.php';
include_once __DIR__ . '/SqlDump/MySql.php';
require_once ABSPATH . 'wp-admin/includes/class-pclzip.php';

/**
 * Class AutoBackupMaster
 *
 * Handles site backups in sequential steps via WP-Cron to avoid server timeouts.
 * Each step is triggered as a separate cron event, and state is persisted
 * between steps using wp_options.
 *
 * Backup flow:
 *   Step 1 (initBackup)         — Create temp directory
 *   Step 2 (stepDumpDb)         — Generate SQL dump via MySql class
 *   Step 3 (stepArchiveDb)      — Zip SQL dump into db_backup.zip
 *   Step 4 (stepArchiveFolders) — Zip wp-content subdirs and root files directly from WP_CONTENT_DIR
 *   Step 5 (stepUploadDropbox)  — Upload all archives and metadata.json to Dropbox
 *   Step 6 (stepCleanup)        — Delete temp directory and clear saved state
 */
class AutoBackupMaster {

    /** wp_options key used to persist backup state between cron steps */
    const STATE_OPTION = 'ev_backup_state';

    /** @var string Absolute path to the temporary backup directory */
    private $tmp_backup_dir;

    /** @var string Unique backup name, used for folder/archive naming */
    private $backup_name;

    /** @var array List of database archive filenames (e.g. ['db_backup.zip']) */
    private $db_parts = [];

    /** @var array List of site archive filenames (e.g. ['wp-content.zip', 'root_files.zip']) */
    private $file_parts = [];

    private $upload_index = 0;

    /**
     * Constructor — loads persisted state from wp_options.
     * On step 1 (initBackup) state will be empty; on steps 2-6 it will be populated.
     */
    public function __construct() {
        $this->loadState();
    }

    // -------------------------------------------------------------------------
    // State management
    // -------------------------------------------------------------------------

    private function loadState() {
        $state = get_option(self::STATE_OPTION, []);
        if (!empty($state)) {
            $this->tmp_backup_dir = $state['tmp_backup_dir'] ?? '';
            $this->backup_name    = $state['backup_name']    ?? '';
            $this->db_parts       = $state['db_parts']       ?? [];
            $this->file_parts     = $state['file_parts']     ?? [];
            $this->upload_index   = $state['upload_index']   ?? 0;
        }
    }

    private function saveState() {
        update_option(self::STATE_OPTION, [
            'tmp_backup_dir' => $this->tmp_backup_dir,
            'backup_name'    => $this->backup_name,
            'db_parts'       => $this->db_parts,
            'file_parts'     => $this->file_parts,
            'upload_index'   => $this->upload_index,
        ]);
    }

    private function clearState() {
        delete_option(self::STATE_OPTION);
    }

    // -------------------------------------------------------------------------
    // Backup steps
    // -------------------------------------------------------------------------

    /**
     * Step 1: Initialize backup.
     *
     * Removes leftover temp dirs from previous failed backups,
     * creates a unique temporary directory in wp-content/uploads.
     * No file copying — archiving is done directly from WP_CONTENT_DIR in step 4.
     */
    public function initBackup(): bool {
        $site_name = $_SERVER['HTTP_HOST'];
        $site_slug = str_replace(['.', ' ', ':'], ['_', '_', '-'], $site_name);

        $active_state = get_option(self::STATE_OPTION, []);
        $active_dir   = $active_state['tmp_backup_dir'] ?? '';

        if (!empty($active_dir) && is_dir($active_dir)) {
            error_log("Backup initBackup: another backup is in progress, aborting — $active_dir");
            return false;
        }

        foreach (glob(wp_upload_dir()['basedir'] . '/' . $site_slug . '*') ?: [] as $old_dir) {
            if (is_dir($old_dir) && $old_dir !== $active_dir) {
                $this->deleteDir($old_dir);
                error_log("Backup initBackup: removed old temp dir — $old_dir");
            }
        }

        $this->backup_name    = $site_name . date('_Y-m-d_H-i-s');
        $this->tmp_backup_dir = wp_upload_dir()['basedir'] . '/' . str_replace(
                ['.', ' ', ':'], ['_', '_', '-'], $this->backup_name
            );

        if (!mkdir($this->tmp_backup_dir, 0755, true) && !is_dir($this->tmp_backup_dir)) {
            error_log("Backup initBackup: failed to create temp dir: {$this->tmp_backup_dir}");
            return false;
        }

        $this->saveState();
        error_log("Backup step 1 done: tmp dir created at {$this->tmp_backup_dir}");
        return true;
    }

    /**
     * Step 2: Create SQL dump.
     */
    public function stepDumpDb(): bool {
        if (empty($this->tmp_backup_dir)) {
            error_log("Backup stepDumpDb: no state found");
            return false;
        }

        foreach (glob($this->tmp_backup_dir . '/*.sql') ?: [] as $old_sql) {
            @unlink($old_sql);
            error_log("Backup stepDumpDb: removed stale sql — $old_sql");
        }

        $database   = new MySql();
        $all_tables = $database->get_tables();

        global $wpdb;
        $valid_tables = [];
        foreach ($all_tables as $table) {
            $check = $wpdb->get_results("DESCRIBE `$table`");
            if (!empty($check)) {
                $valid_tables[] = $table;
            } else {
                error_log("Backup stepDumpDb: skipping broken table/view — $table");
            }
        }

        if (empty($valid_tables)) {
            error_log("Backup stepDumpDb: no valid tables found");
            return false;
        }

        $result = $database->db_backup($valid_tables, $this->tmp_backup_dir);

        if ($result === false) {
            error_log("Backup stepDumpDb: dump failed");
            return false;
        }

        error_log("Backup step 2 done: SQL dump created — $result");
        return true;
    }

    /**
     * Step 3: Archive the SQL dump into db_backup.zip.
     */
    public function stepArchiveDb(): bool {
        if (empty($this->tmp_backup_dir)) {
            error_log("Backup stepArchiveDb: no state found, cannot proceed");
            return false;
        }

        $db_dir = $this->tmp_backup_dir . '/db';

        if (!mkdir($db_dir, 0755, true) && !is_dir($db_dir)) {
            error_log("Backup stepArchiveDb: cannot create db directory");
            return false;
        }

        $sql_files = glob($this->tmp_backup_dir . '/*.sql') ?: [];

        if (empty($sql_files)) {
            error_log("Backup stepArchiveDb: no SQL files found in {$this->tmp_backup_dir}");
            return false;
        }

        foreach ($sql_files as $sql_file) {
            $dest = $db_dir . '/' . basename($sql_file);
            if (!rename($sql_file, $dest)) {
                error_log("Backup stepArchiveDb: failed to move $sql_file to $dest");
            }
        }

        $sql_in_dir = glob($db_dir . '/*.sql') ?: [];

        if (empty($sql_in_dir)) {
            error_log("Backup stepArchiveDb: no SQL files found after move, skipping archive");
            return false;
        }

        $db_zip       = $this->tmp_backup_dir . '/db_backup.zip';
        $archive      = new \PclZip($db_zip);
        $files_to_add = [];

        foreach ($sql_in_dir as $sql_file) {
            $files_to_add[] = [
                PCLZIP_ATT_FILE_NAME          => $sql_file,
                PCLZIP_ATT_FILE_NEW_FULL_NAME => 'db/' . basename($sql_file),
            ];
        }

        if (!$archive->create($files_to_add)) {
            error_log("Backup stepArchiveDb: PclZip error — " . $archive->errorInfo(true));
            return false;
        }

        if (!in_array('db_backup.zip', $this->db_parts, true)) {
            $this->db_parts[] = 'db_backup.zip';
        }

        $this->saveState();
        error_log("Backup step 3 done: db_backup.zip created at $db_zip");
        return true;
    }

    /**
     * Step 4: Archive wp-content subdirs and root files directly from WP_CONTENT_DIR.
     * Skips our own tmp_backup_dir to avoid recursive archiving.
     */
    public function stepArchiveFolders(): bool {
        if (empty($this->tmp_backup_dir)) {
            error_log("Backup stepArchiveFolders: no state found");
            return false;
        }

        $this->archiveWpContent();

        error_log("Backup step 4: skipping wp-admin/wp-includes (standard WP core files)");

        // Root files of the site (not wp-content subdirs)
        $root_files = array_filter(glob($this->tmp_backup_dir . '/*') ?: [], function ($f) {
            if (!is_file($f)) return false;
            $base = basename($f);
            $ext  = pathinfo($f, PATHINFO_EXTENSION);
            if (in_array($base, ['db_backup.zip', 'metadata.json'], true)) return false;
            if (str_starts_with($base, 'pclzip-')) return false;
            if (in_array($ext, ['zip', 'sql', 'gz', 'tmp'], true)) return false;
            return true;
        });

        if (!empty($root_files)) {
            $zip_name = $this->tmp_backup_dir . '/root_files.zip';
            $zip      = new \ZipArchive();
            if ($zip->open($zip_name, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) === true) {
                foreach ($root_files as $f) {
                    $zip->addFile($f, basename($f));
                }
                $zip->close();
                if (!in_array('root_files.zip', $this->file_parts, true)) {
                    $this->file_parts[] = 'root_files.zip';
                }
                error_log("Backup step 4: archived root files");
            }
        }

        error_log("Backup step 4: writing metadata.json");
        file_put_contents(
            $this->tmp_backup_dir . '/metadata.json',
            json_encode([
                'db'         => $this->db_parts,
                'files'      => $this->file_parts,
                'wp_version' => get_bloginfo('version'),
                'created_at' => date('Y-m-d H:i:s'),
            ], JSON_PRETTY_PRINT)
        );

        $this->saveState();
        error_log("Backup step 4 done: all folders archived");
        return true;
    }

    private function archiveWpContent(): void {
        $wp_content_src = WP_CONTENT_DIR;

        $subdirs = glob($wp_content_src . '/*', GLOB_ONLYDIR) ?: [];

        foreach ($subdirs as $subdir) {
            $subdir_name  = basename($subdir);
            $zip_filename = 'wp-content_' . $subdir_name . '.zip';

            if (in_array($zip_filename, $this->file_parts, true)) {
                error_log("Backup: already archived, skipping — wp-content/$subdir_name");
                continue;
            }

            $files_to_add = [];
            foreach ($this->getFilesRecursive($subdir) as $f) {
                // Skip our own tmp dir (it lives inside uploads)
                if (strpos($f, $this->tmp_backup_dir) === 0) continue;

                $files_to_add[] = [
                    PCLZIP_ATT_FILE_NAME          => $f,
                    PCLZIP_ATT_FILE_NEW_FULL_NAME => 'wp-content/' . $subdir_name . '/' . substr($f, strlen($subdir) + 1),
                ];
            }

            if (empty($files_to_add)) continue;

            $zip_name = $this->tmp_backup_dir . '/' . $zip_filename;
            $archive  = new \PclZip($zip_name);

            if ($archive->create($files_to_add)) {
                $this->file_parts[] = $zip_filename;
                error_log("Backup: archived wp-content/$subdir_name");
            } else {
                error_log("Backup: failed wp-content/$subdir_name — " . $archive->errorInfo(true));
            }
        }

        // Root files of wp-content (non-directories), skip .sql files (WP Engine mysql.sql etc)
        $root_files_in_wpc = array_filter(glob($wp_content_src . '/*') ?: [], function ($f) {
            if (!is_file($f)) return false;
            if (pathinfo($f, PATHINFO_EXTENSION) === 'sql') return false;
            return true;
        });

        if (!empty($root_files_in_wpc) && !in_array('wp-content_root.zip', $this->file_parts, true)) {
            $files_to_add = [];
            foreach ($root_files_in_wpc as $f) {
                $files_to_add[] = [
                    PCLZIP_ATT_FILE_NAME          => $f,
                    PCLZIP_ATT_FILE_NEW_FULL_NAME => 'wp-content/' . basename($f),
                ];
            }
            $zip_name = $this->tmp_backup_dir . '/wp-content_root.zip';
            $archive  = new \PclZip($zip_name);
            if ($archive->create($files_to_add)) {
                $this->file_parts[] = 'wp-content_root.zip';
            }
        }
    }

    /**
     * Step 5: Upload ONE file per cron call.
     * Returns 'done', 'continue', or 'error'.
     */
    public function stepUploadNextFile(): string {
        if (empty($this->tmp_backup_dir)) {
            error_log("Backup stepUploadNextFile: no state found");
            return 'error';
        }

        $site_name = $_SERVER['HTTP_HOST'];

        $dropbox_settings = get_option('ev_dropbox_settings');
        if (empty($dropbox_settings)) {
            error_log("Backup stepUploadNextFile: Dropbox settings not configured");
            return 'error';
        }

        if (is_string($dropbox_settings)) {
            $dropbox_settings = json_decode($dropbox_settings, true);
        }

        if (empty($dropbox_settings['refresh_token'])) {
            error_log("Backup stepUploadNextFile: missing credentials");
            return 'error';
        }

        $refresh_token = Encryption::decrypt($dropbox_settings['refresh_token']);
        $app_key       = Encryption::decrypt($dropbox_settings['app_key']);
        $app_secret    = Encryption::decrypt($dropbox_settings['app_secret']);
        $access_code   = Encryption::decrypt($dropbox_settings['access_code']);

        $drops        = new DropboxAPI($app_key, $app_secret, $access_code);
        $access_token = $drops->curlRefreshToken($refresh_token);

        $full_backup_path = $site_name . '/' . $this->backup_name;

        if (($this->upload_index ?? 0) === 0) {
            if (!$drops->GetListFolder($access_token, $site_name)) {
                $drops->CreateFolder($access_token, $site_name);
            }
            $drops->CreateFolder($access_token, $full_backup_path);
        }

        $all_parts = array_merge($this->db_parts, $this->file_parts, ['metadata.json']);
        $index     = $this->upload_index ?? 0;

        if ($index >= count($all_parts)) {
            error_log("Backup step 5: all files uploaded, getting Dropbox link");

            $dropbox_path = '/Secondary Backups/' . $full_backup_path;
            $linkData     = $drops->getOrCreateSharedLinkForFolder($access_token, $dropbox_path);

            error_log("Backup step 5: linkData = " . var_export($linkData, true));

            if ($linkData !== false) {
                $sitemap_file = EVN_DIR . 'includes/class/Admin/Settings/SiteMap/SiteMap.php';
                if (file_exists($sitemap_file)) {
                    require_once $sitemap_file;
                }

                if (function_exists('sendtoGoogleUrls')) {
                    sendtoGoogleUrls($site_name, $linkData);
                    error_log("Backup step 5: sendtoGoogleUrls called successfully");
                } else {
                    error_log("Backup step 5: sendtoGoogleUrls still not found after require — $sitemap_file");
                }
            } else {
                error_log("Backup step 5: linkData is false — Dropbox link failed");
            }

            error_log("Backup step 5 done: all files uploaded");
            return 'done';
        }

        $part       = $all_parts[$index];
        $local_path = $this->tmp_backup_dir . '/' . $part;

        if (!file_exists($local_path)) {
            error_log("Backup stepUploadNextFile: file not found, skipping — $local_path");
            $this->upload_index = $index + 1;
            $this->saveState();
            return 'continue';
        }

        $size = filesize($local_path);
        $fp   = fopen($local_path, 'rb');

        if (!$fp || $size === false) {
            error_log("Backup stepUploadNextFile: cannot open — $local_path");
            $this->upload_index = $index + 1;
            $this->saveState();
            return 'continue';
        }

        try {
            $drops->SendFile($access_token, $full_backup_path . '/' . $part, $fp, $size);
            error_log("Backup stepUploadNextFile: uploaded $part");
        } catch (\Exception $e) {
            error_log("Backup stepUploadNextFile: error on $part — " . $e->getMessage());
        } finally {
            if (is_resource($fp)) fclose($fp);
        }

        $this->upload_index = $index + 1;
        $this->saveState();
        return 'continue';
    }

    /**
     * Step 6: Clean up temporary files.
     */
    public function stepCleanup(): void {
        if (!empty($this->tmp_backup_dir)) {
            $this->deleteDir($this->tmp_backup_dir);
            error_log("Backup step 6 done: temp directory deleted — {$this->tmp_backup_dir}");
        }

        $this->clearState();
        error_log("Backup step 6 done: state cleared");
    }

    // -------------------------------------------------------------------------
    // Helper methods
    // -------------------------------------------------------------------------

    /**
     * Recursively collect all files in a directory.
     * Skips .sql, .zip, .log and .gz files.
     */
    private function getFilesRecursive(string $dir): array {
        $rii   = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir));
        $files = [];

        $excludeExtensions = ['sql', 'zip', 'log', 'gz'];

        foreach ($rii as $file) {
            if (!$file->isFile()) continue;
            $ext = pathinfo($file->getFilename(), PATHINFO_EXTENSION);
            if (in_array($ext, $excludeExtensions, true)) continue;
            $files[] = $file->getRealPath();
        }

        return $files;
    }

    /**
     * Recursively delete a directory and all its contents.
     */
    private function deleteDir(string $dirPath) {
        if (!is_dir($dirPath)) return;

        $files = array_diff(scandir($dirPath), ['.', '..']);

        foreach ($files as $file) {
            $fullPath = $dirPath . DIRECTORY_SEPARATOR . $file;
            is_dir($fullPath) ? $this->deleteDir($fullPath) : @unlink($fullPath);
        }

        @rmdir($dirPath);
    }
}