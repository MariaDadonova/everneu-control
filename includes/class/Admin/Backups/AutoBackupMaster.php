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
 *   Step 1 (initBackup)         — Create temp directory, copy site files
 *   Step 2 (stepDumpDb)         — Generate SQL dump via MySql class
 *   Step 3 (stepArchiveDb)      — Move SQL file and zip it
 *   Step 4 (stepArchiveFolders) — Zip wp-admin, wp-content, wp-includes and root files
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

    /**
     * Load backup state from wp_options into class properties.
     */
    private function loadState() {
        $state = get_option(self::STATE_OPTION, []);
        if (!empty($state)) {
            $this->tmp_backup_dir = $state['tmp_backup_dir'] ?? '';
            $this->backup_name    = $state['backup_name']    ?? '';
            $this->db_parts       = $state['db_parts']       ?? [];
            $this->file_parts     = $state['file_parts']     ?? [];
        }
    }

    /**
     * Persist current backup state to wp_options so the next cron step can use it.
     */
    private function saveState() {
        update_option(self::STATE_OPTION, [
            'tmp_backup_dir' => $this->tmp_backup_dir,
            'backup_name'    => $this->backup_name,
            'db_parts'       => $this->db_parts,
            'file_parts'     => $this->file_parts,
        ]);
    }

    /**
     * Remove backup state from wp_options after the process is complete.
     */
    private function clearState() {
        delete_option(self::STATE_OPTION);
    }

    // -------------------------------------------------------------------------
    // Backup steps
    // -------------------------------------------------------------------------

    /**
     * Step 1: Initialize backup.
     *
     * Creates a unique temporary directory and copies all site files into it,
     * excluding directories that are not needed in the backup.
     *
     * @return bool True on success, false if the temp directory could not be created.
     */
    public function initBackup(): bool {
        $site_name = $_SERVER['HTTP_HOST'];

        $this->backup_name    = $site_name . date('_Y-m-d_H-i-s');
        $this->tmp_backup_dir = sys_get_temp_dir() . '/' . str_replace(
                ['.', ' ', ':'], ['_', '_', '-'], $this->backup_name
            );

        if (!mkdir($this->tmp_backup_dir, 0755, true) && !is_dir($this->tmp_backup_dir)) {
            error_log("Backup initBackup: failed to create temp dir: {$this->tmp_backup_dir}");
            return false;
        }

        $src_dir = realpath($_SERVER['DOCUMENT_ROOT']);

        // Copy site files, skipping directories that are irrelevant or too large
        $this->copyDir($src_dir, $this->tmp_backup_dir, [
            'backups', '.git', '.idea', 'node_modules', 'tmp', 'logs', 'vendor', 'db'
        ]);

        $this->saveState();
        error_log("Backup step 1 done: files copied to {$this->tmp_backup_dir}");
        return true;
    }

    /**
     * Step 2: Create SQL dump.
     *
     * Uses the MySql class to dump all database tables into a .sql file.
     * The file is saved to wp-content/ by MySql::db_backup().
     *
     * @return bool True if the dump was created successfully, false otherwise.
     */
    public function stepDumpDb(): bool {
        if (empty($this->tmp_backup_dir)) {
            error_log("Backup stepDumpDb: no state found");
            return false;
        }

        $database   = new MySql();
        $all_tables = $database->get_tables();

        // Filter out broken VIEWs before dumping
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

        $result = $database->db_backup($valid_tables);

        if ($result === false) {
            error_log("Backup stepDumpDb: dump failed");
            return false;
        }

        error_log("Backup step 2 done: SQL dump created — $result");
        return true;
    }

    /**
     * Step 3: Archive the SQL dump.
     *
     * Moves the .sql file from wp-content/ to the temp backup directory,
     * then compresses it into db_backup.zip.
     *
     * @return bool True if the archive was created successfully, false otherwise.
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

        // MySql::db_backup() saves the .sql file into wp-content/
        $wp_content = realpath(WP_CONTENT_DIR);
        $sql_files  = glob($wp_content . '/*.sql') ?: [];

        if (empty($sql_files)) {
            error_log("Backup stepArchiveDb: no SQL files found in $wp_content");
            return false;
        }

        // Move SQL files into the temp db/ subdirectory
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

        $this->db_parts[] = 'db_backup.zip';
        $this->saveState();
        error_log("Backup step 3 done: db_backup.zip created at $db_zip");
        return true;
    }

    /**
     * Step 4: Archive site folders and root files.
     *
     * Creates separate zip archives for wp-admin, wp-content, and wp-includes.
     * Remaining root-level files (excluding .zip and .sql) are packed into root_files.zip.
     * Also writes metadata.json with the list of all archive parts and creation time.
     *
     * @return bool True when archiving is complete (even if some folders were skipped).
     */
    public function stepArchiveFolders(): bool {
        if (empty($this->tmp_backup_dir)) {
            error_log("Backup stepArchiveFolders: no state found, cannot proceed");
            return false;
        }

        $folders = ['wp-admin', 'wp-content', 'wp-includes'];

        foreach ($folders as $folder) {
            $src = $this->tmp_backup_dir . '/' . $folder;

            if (!is_dir($src)) {
                error_log("Backup stepArchiveFolders: folder not found, skipping — $src");
                continue;
            }

            $files_to_add = [];
            foreach ($this->getFilesRecursive($src) as $f) {
                $files_to_add[] = [
                    PCLZIP_ATT_FILE_NAME          => $f,
                    // Preserve relative path inside the archive
                    PCLZIP_ATT_FILE_NEW_FULL_NAME => substr($f, strlen($this->tmp_backup_dir) + 1),
                ];
            }

            // Skip empty folders to avoid PclZip errors
            if (empty($files_to_add)) {
                error_log("Backup stepArchiveFolders: no files found in $folder, skipping");
                continue;
            }

            $zip_name = $this->tmp_backup_dir . '/' . $folder . '.zip';
            $archive  = new \PclZip($zip_name);

            if ($archive->create($files_to_add)) {
                $this->file_parts[] = $folder . '.zip';
                error_log("Backup step 4: archived $folder → $zip_name");
            } else {
                error_log("Backup stepArchiveFolders: failed to archive $folder — " . $archive->errorInfo(true));
            }
        }

        // Collect root-level files, excluding already created archives and SQL files
        $root_files = array_filter(glob($this->tmp_backup_dir . '/*') ?: [], function ($f) {
            if (!is_file($f)) return false;
            if (in_array(basename($f), ['db_backup.zip', 'metadata.json'], true)) return false;
            $ext = pathinfo($f, PATHINFO_EXTENSION);
            return $ext !== 'zip' && $ext !== 'sql';
        });

        if (!empty($root_files)) {
            $files_to_add = [];
            foreach ($root_files as $f) {
                $files_to_add[] = [
                    PCLZIP_ATT_FILE_NAME          => $f,
                    PCLZIP_ATT_FILE_NEW_FULL_NAME => basename($f),
                ];
            }

            $zip_name = $this->tmp_backup_dir . '/root_files.zip';
            $archive  = new \PclZip($zip_name);

            if ($archive->create($files_to_add)) {
                $this->file_parts[] = 'root_files.zip';
                error_log("Backup step 4: archived root files → $zip_name");
            } else {
                error_log("Backup stepArchiveFolders: failed to archive root files — " . $archive->errorInfo(true));
            }
        }

        // Write metadata.json — used during restore to know which parts belong to this backup
        $metadata = [
            'db'         => $this->db_parts,
            'files'      => $this->file_parts,
            'created_at' => date('Y-m-d H:i:s'),
        ];
        file_put_contents(
            $this->tmp_backup_dir . '/metadata.json',
            json_encode($metadata, JSON_PRETTY_PRINT)
        );

        $this->saveState();
        error_log("Backup step 4 done: all folders archived");
        return true;
    }

    /**
     * Step 5: Upload all backup parts to Dropbox.
     *
     * Uploads db_backup.zip, folder archives, and metadata.json into a
     * dedicated folder on Dropbox: <site_name>/<backup_name>/
     * Optionally sends a shared folder link to Google via sendtoGoogleUrls().
     *
     * @return bool True if upload completed, false if Dropbox credentials are missing.
     */
    public function stepUploadDropbox(): bool {
        if (empty($this->tmp_backup_dir)) {
            error_log("Backup stepUploadDropbox: no state found, cannot proceed");
            return false;
        }

        $site_name = $_SERVER['HTTP_HOST'];

        // Load and validate Dropbox settings from wp_options
        $dropbox_settings = get_option('ev_dropbox_settings');

        if (empty($dropbox_settings)) {
            error_log("Backup stepUploadDropbox: Dropbox settings not configured");
            return false;
        }

        if (is_string($dropbox_settings)) {
            $dropbox_settings = json_decode($dropbox_settings, true);
        }

        if (empty($dropbox_settings['refresh_token'])) {
            error_log("Backup stepUploadDropbox: missing Dropbox credentials in settings");
            return false;
        }

        // Decrypt stored credentials
        $refresh_token = Encryption::decrypt($dropbox_settings['refresh_token']);
        $app_key       = Encryption::decrypt($dropbox_settings['app_key']);
        $app_secret    = Encryption::decrypt($dropbox_settings['app_secret']);
        $access_code   = Encryption::decrypt($dropbox_settings['access_code']);

        $drops        = new DropboxAPI($app_key, $app_secret, $access_code);
        $access_token = $drops->curlRefreshToken($refresh_token);

        // Target path in Dropbox: <site_name>/<backup_name>/
        $full_backup_path = $site_name . '/' . $this->backup_name;

        // Create site folder if it doesn't exist yet
        if (!$drops->GetListFolder($access_token, $site_name)) {
            $drops->CreateFolder($access_token, $site_name);
        }

        // Create a new folder for this specific backup
        $drops->CreateFolder($access_token, $full_backup_path);

        // Upload each archive part and metadata.json
        $all_parts = array_merge($this->db_parts, $this->file_parts, ['metadata.json']);

        foreach ($all_parts as $part) {
            $local_path = $this->tmp_backup_dir . '/' . $part;

            if (!file_exists($local_path)) {
                error_log("Backup stepUploadDropbox: file not found, skipping — $local_path");
                continue;
            }

            $size = filesize($local_path);
            if ($size === false) {
                error_log("Backup stepUploadDropbox: cannot determine file size — $local_path");
                continue;
            }

            $fp = fopen($local_path, 'rb');
            if (!$fp) {
                error_log("Backup stepUploadDropbox: cannot open file — $local_path");
                continue;
            }

            try {
                $drops->SendFile($access_token, $full_backup_path . '/' . $part, $fp, $size);
                error_log("Backup stepUploadDropbox: uploaded $part");
            } catch (\Exception $e) {
                error_log("Backup stepUploadDropbox: error uploading $part — " . $e->getMessage());
            } finally {
                // Always close the file handle, even if an exception was thrown
                if (is_resource($fp)) {
                    fclose($fp);
                }
            }
        }

        // Get or create a shared link for the backup folder and notify Google
        $linkData = $drops->getOrCreateSharedLinkForFolder(
            $access_token,
            '/Secondary Backups/' . $full_backup_path
        );

        if ($linkData !== false && function_exists('sendtoGoogleUrls')) {
            sendtoGoogleUrls($site_name, $linkData);
        }

        error_log("Backup step 5 done: all files uploaded to Dropbox");
        return true;
    }

    /**
     * Step 6: Clean up temporary files.
     *
     * Deletes the temporary backup directory and removes the saved state
     * from wp_options, leaving no trace of the backup process on the server.
     */
    public function stepCleanup(): void {
        if (!empty($this->tmp_backup_dir)) {
            $this->deleteDir($this->tmp_backup_dir);
            error_log("Backup step 6 done: temp directory deleted — {$this->tmp_backup_dir}");
        }

        // Clear persisted state so a fresh backup can start cleanly next time
        $this->clearState();
        error_log("Backup step 6 done: state cleared");
    }

    // -------------------------------------------------------------------------
    // Helper methods
    // -------------------------------------------------------------------------

    /**
     * Recursively copy a directory, skipping specified subdirectories.
     *
     * @param string $src        Source directory path.
     * @param string $dst        Destination directory path.
     * @param array  $excludeDirs Directory names to skip (e.g. ['.git', 'node_modules']).
     */
    private function copyDir(string $src, string $dst, array $excludeDirs = []) {
        $dir = opendir($src);
        if ($dir === false) {
            error_log("copyDir: cannot open source directory — $src");
            return;
        }

        @mkdir($dst, 0755, true);

        while (false !== ($file = readdir($dir))) {
            if ($file === '.' || $file === '..') continue;
            if (in_array($file, $excludeDirs, true)) continue;

            $srcPath = $src . DIRECTORY_SEPARATOR . $file;
            $dstPath = $dst . DIRECTORY_SEPARATOR . $file;

            is_dir($srcPath)
                ? $this->copyDir($srcPath, $dstPath, $excludeDirs)
                : copy($srcPath, $dstPath);
        }

        closedir($dir);
    }

    /**
     * Recursively collect all files in a directory.
     * Skips .sql and .zip files as they are handled separately.
     *
     * @param  string $dir Directory to scan.
     * @return array  Array of absolute file paths.
     */
    private function getFilesRecursive(string $dir): array {
        $rii   = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir));
        $files = [];

        foreach ($rii as $file) {
            if (!$file->isFile()) continue;
            $ext = pathinfo($file->getFilename(), PATHINFO_EXTENSION);
            if ($ext === 'sql' || $ext === 'zip') continue;
            $files[] = $file->getRealPath();
        }

        return $files;
    }

    /**
     * Recursively delete a directory and all its contents.
     *
     * @param string $dirPath Absolute path to the directory.
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