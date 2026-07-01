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
 *   Step 1 (initBackup)         - Create temp directory
 *   Step 2 (stepDumpDb)         - Generate SQL dump via MySql class
 *   Step 3 (stepArchiveDb)      - Zip SQL dump into db_backup.zip
 *   Step 4 (stepArchiveFolders) - Zip wp-content subdirs and root files directly from WP_CONTENT_DIR
 *   Step 5 (stepUploadDropbox)  - Upload all archives and metadata.json to Dropbox
 *   Step 6 (stepCleanup)        - Delete temp directory and clear saved state
 */
class AutoBackupMaster {

    /** wp_options key used to persist backup state between cron steps */
    const STATE_OPTION = 'ev_backup_state';
    const LARGE_FILE_THRESHOLD = 20 * 1024 * 1024;   // 20 MB - chunked upload above this
    const CHUNK_SIZE_LIMIT     = 200 * 1024 * 1024;  // 200 MB per archive chunk

    /** @var string Absolute path to the temporary backup directory */
    private $tmp_backup_dir;

    /** @var string Unique backup name, used for folder/archive naming */
    private $backup_name;

    /** @var array List of database archive filenames (e.g. ['db_backup.zip']) */
    private $db_parts = [];

    /** @var array List of site archive filenames (e.g. ['wp-content.zip', 'root_files.zip']) */
    private $file_parts = [];

    private $upload_index = 0;

    /** @var array|null Queue of folders and files for archiving */
    private $folders_queue = null;

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
            $this->folders_queue  = $state['folders_queue']  ?? null;
        }
    }

    private function saveState() {
        // Preserve started_at from existing state - set once on first save, never overwritten
        $current    = get_option(self::STATE_OPTION, []);
        $started_at = $current['started_at'] ?? time();

        update_option(self::STATE_OPTION, [
            'tmp_backup_dir' => $this->tmp_backup_dir,
            'backup_name'    => $this->backup_name,
            'db_parts'       => $this->db_parts,
            'file_parts'     => $this->file_parts,
            'upload_index'   => $this->upload_index,
            'folders_queue'  => $this->folders_queue,
            'started_at'     => $started_at,
        ]);
    }

    private function clearState() {
        delete_option(self::STATE_OPTION);
    }

    // -------------------------------------------------------------------------
    // Backup steps
    // -------------------------------------------------------------------------

    /**
     * Initializes the queue: collects all subfolders of wp-content
     * and adds a special 'wp-content-root' element for root files.
     * Called once after stepArchiveDb.
     */
    public function initFoldersQueue(): void {
        $wp_content_src = WP_CONTENT_DIR;
        $queue = [];

        $subdirs = glob($wp_content_src . '/*', GLOB_ONLYDIR) ?: [];
        foreach ($subdirs as $subdir) {
            $subdir_name = basename($subdir);

            $all_files = array_filter(
                $this->getFilesRecursive($subdir),
                fn($f) => strpos($f, $this->tmp_backup_dir) !== 0
            );

            if (empty($all_files)) {
                continue;
            }

            $chunks      = $this->splitFilesIntoChunks($all_files);
            $total_parts = count($chunks);

            foreach ($chunks as $i => $chunk_files) {
                $queue[] = [
                    'type'        => 'subdir-chunk',
                    'subdir'      => $subdir,
                    'subdir_name' => $subdir_name,
                    'files'       => $chunk_files,
                    'part'        => $i + 1,
                    'total_parts' => $total_parts,
                ];
            }
        }

        $queue[] = ['type' => 'wpcontent-root'];
        $queue[] = ['type' => 'site-root'];

        $this->folders_queue = $queue;
        $this->saveState();
        error_log('Backup initFoldersQueue: queue initialized with ' . count($queue) . ' items');
    }

    /**
     * Splits a list of file paths into chunks respecting both a size limit
     * and a dynamic file-count limit.
     *
     * The file-count limit is adaptive: directories with many small files
     * (plugins, themes — mostly PHP < 100 KB average) get a higher per-chunk
     * file limit (3000) so they don't explode into dozens of tiny archives.
     * Directories with large files (uploads - images/video) get a tighter
     * limit (500) to keep each cron step well within execution time limits.
     */
    private function splitFilesIntoChunks(array $files): array {
        $chunks        = [];
        $current_chunk = [];
        $current_size  = 0;

        $total_size = array_sum(array_map(fn($f) => @filesize($f) ?: 0, $files));
        $avg_size   = count($files) > 0 ? $total_size / count($files) : 0;
        $file_limit = $avg_size < 100 * 1024 ? 3000 : 500;

        foreach ($files as $f) {
            $fsize = @filesize($f) ?: 0;

            $would_exceed_size  = !empty($current_chunk) && ($current_size + $fsize > self::CHUNK_SIZE_LIMIT);
            $would_exceed_count = count($current_chunk) >= $file_limit;

            if ($would_exceed_size || $would_exceed_count) {
                $chunks[]      = $current_chunk;
                $current_chunk = [];
                $current_size  = 0;
            }

            $current_chunk[] = $f;
            $current_size   += $fsize;
        }

        if (!empty($current_chunk)) {
            $chunks[] = $current_chunk;
        }

        return $chunks;
    }

    /**
     * Processes ONE item from the queue per call.
     * Returns 'continue' if there are still elements,
     * 'done' if the queue is empty (writes metadata.json),
     * 'error' if state is not found.
     */
    public function stepArchiveNextFolder(): string {
        if (empty($this->tmp_backup_dir)) {
            error_log("Backup stepArchiveNextFolder: no state found");
            return 'error';
        }

        if ($this->folders_queue === null) {
            error_log("Backup stepArchiveNextFolder: queue not initialized");
            return 'error';
        }

        if (empty($this->folders_queue)) {
            if (!file_exists($this->tmp_backup_dir . '/metadata.json')) {
                $this->writeMetadata();
            }
            error_log("Backup stepArchiveNextFolder: queue empty, all folders done");
            return 'done';
        }

        $item = array_shift($this->folders_queue);

        if ($item['type'] === 'subdir-chunk') {
            $this->archiveSubdirChunk($item);
        } elseif ($item['type'] === 'wpcontent-root') {
            $this->archiveWpContentRootFiles();
        } elseif ($item['type'] === 'site-root') {
            $this->archiveSiteRootFiles();
        }

        $this->saveState();

        if (empty($this->folders_queue)) {
            $this->writeMetadata();
            error_log("Backup stepArchiveNextFolder: last item processed, all done");
            return 'done';
        }

        return 'continue';
    }

    /**
     * Archives exactly ONE pre-computed chunk of files from a wp-content
     * subdirectory. Called once per cron step.
     */
    private function archiveSubdirChunk(array $item): void {
        $subdir      = $item['subdir'];
        $subdir_name = $item['subdir_name'];
        $files       = $item['files'];
        $part        = $item['part'];
        $total_parts = $item['total_parts'];

        $zip_filename = $total_parts > 1
            ? 'wp-content_' . $subdir_name . '_part' . $part . '.zip'
            : 'wp-content_' . $subdir_name . '.zip';

        if (in_array($zip_filename, $this->file_parts, true)) {
            error_log("Backup: already archived, skipping - $zip_filename");
            return;
        }

        $files_to_add = [];
        foreach ($files as $f) {
            if (!file_exists($f)) continue;
            $files_to_add[] = [
                PCLZIP_ATT_FILE_NAME          => $f,
                PCLZIP_ATT_FILE_NEW_FULL_NAME => 'wp-content/' . $subdir_name . '/' . substr($f, strlen($subdir) + 1),
            ];
        }

        if (empty($files_to_add)) {
            error_log("Backup: chunk $zip_filename had no existing files, skipping");
            return;
        }

        $archive = new \PclZip($this->tmp_backup_dir . '/' . $zip_filename);
        if ($archive->create($files_to_add)) {
            $this->file_parts[] = $zip_filename;
            error_log("Backup: archived chunk $zip_filename (" . count($files_to_add) . " files)");
        } else {
            error_log("Backup: failed chunk $zip_filename - " . $archive->errorInfo(true));
        }
    }

    /**
     * Archives wp-content root files (not folders, not .sql).
     */
    private function archiveWpContentRootFiles(): void {
        if (in_array('wp-content_root.zip', $this->file_parts, true)) return;

        $root_files = array_filter(glob(WP_CONTENT_DIR . '/*') ?: [], function ($f) {
            if (!is_file($f)) return false;
            if (pathinfo($f, PATHINFO_EXTENSION) === 'sql') return false;
            return true;
        });

        if (empty($root_files)) return;

        $files_to_add = [];
        foreach ($root_files as $f) {
            $files_to_add[] = [
                PCLZIP_ATT_FILE_NAME          => $f,
                PCLZIP_ATT_FILE_NEW_FULL_NAME => 'wp-content/' . basename($f),
            ];
        }

        $archive = new \PclZip($this->tmp_backup_dir . '/wp-content_root.zip');
        if ($archive->create($files_to_add)) {
            $this->file_parts[] = 'wp-content_root.zip';
            error_log("Backup: archived wp-content root files");
        }
    }

    /**
     * Archives the root files of the site (not wp-content, not service files).
     */
    private function archiveSiteRootFiles(): void {
        if (in_array('root_files.zip', $this->file_parts, true)) return;

        $site_root = rtrim(ABSPATH, '/\\');

        $root_files = array_filter(glob($site_root . '/*') ?: [], function ($f) {
            if (!is_file($f)) return false;
            $ext = pathinfo($f, PATHINFO_EXTENSION);
            if (in_array($ext, ['sql', 'log'], true)) return false;
            return true;
        });

        if (empty($root_files)) return;

        $zip = new \ZipArchive();
        if ($zip->open($this->tmp_backup_dir . '/root_files.zip', \ZipArchive::CREATE | \ZipArchive::OVERWRITE) === true) {
            foreach ($root_files as $f) {
                $zip->addFile($f, basename($f));
            }
            $zip->close();
            $this->file_parts[] = 'root_files.zip';
            error_log("Backup: archived site root files");
        }
    }

    /**
     * Writes metadata.json with the final archive list.
     */
    private function writeMetadata(): void {
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
        error_log("Backup: metadata.json written");
    }


    /**
     * Step 1: Initialize backup.
     *
     * Removes leftover temp dirs from previous failed backups,
     * creates a unique temporary directory in wp-content/uploads.
     */
    public function initBackup(): bool {
        $site_name = $_SERVER['HTTP_HOST'];
        $site_slug = str_replace(['.', ' ', ':'], ['_', '_', '-'], $site_name);

        $active_state = get_option(self::STATE_OPTION, []);
        $active_dir   = $active_state['tmp_backup_dir'] ?? '';
        $started_at   = $active_state['started_at'] ?? 0;

        $is_stale = $started_at && (time() - $started_at > 6 * HOUR_IN_SECONDS);

        if (!empty($active_dir) && is_dir($active_dir) && !$is_stale) {
            error_log("Backup initBackup: another backup is in progress, aborting - $active_dir");
            return false;
        }

        if ($is_stale) {
            error_log("Backup initBackup: previous backup stuck for too long, force-cleaning - $active_dir");
            $this->deleteDir($active_dir);
            $this->clearState();
        }

        foreach (glob(wp_upload_dir()['basedir'] . '/' . $site_slug . '*') ?: [] as $old_dir) {
            if (is_dir($old_dir) && $old_dir !== $active_dir) {
                $this->deleteDir($old_dir);
                error_log("Backup initBackup: removed old temp dir - $old_dir");
            }
        }

        $this->backup_name    = $site_name . date('_Y-m-d_H-i-s');
        $this->tmp_backup_dir = wp_upload_dir()['basedir'] . '/' . str_replace(
                ['.', ' ', ':'], ['_', '_', '-'], $this->backup_name
            );

        // Use 0777 to ensure cron-triggered PHP processes (which may run under
        // a different system user than the web request) can write to this dir.
        if (!mkdir($this->tmp_backup_dir, 0777, true) && !is_dir($this->tmp_backup_dir)) {
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

        // On distributed filesystems (e.g. WP Engine NAS) the directory created
        // in step 1 may not yet be visible on the node handling this cron request.
        // Wait up to 5 seconds for it to appear.
        $attempts = 0;
        while (!is_dir($this->tmp_backup_dir) && $attempts < 5) {
            sleep(1);
            $attempts++;
            error_log("Backup stepDumpDb: waiting for tmp dir, attempt $attempts - {$this->tmp_backup_dir}");
        }

        if (!is_dir($this->tmp_backup_dir)) {
            error_log("Backup stepDumpDb: tmp dir not found after waiting - {$this->tmp_backup_dir}");
            return false;
        }

        if (!is_writable($this->tmp_backup_dir)) {
            error_log("Backup stepDumpDb: tmp dir not writable, trying chmod - {$this->tmp_backup_dir}");
            chmod($this->tmp_backup_dir, 0777);
            if (!is_writable($this->tmp_backup_dir)) {
                error_log("Backup stepDumpDb: chmod failed, giving up - {$this->tmp_backup_dir}");
                return false;
            }
            error_log("Backup stepDumpDb: chmod to 0777 succeeded");
        }

        foreach (glob($this->tmp_backup_dir . '/*.sql') ?: [] as $old_sql) {
            @unlink($old_sql);
            error_log("Backup stepDumpDb: removed stale sql - $old_sql");
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
                error_log("Backup stepDumpDb: skipping broken table/view - $table");
            }
        }

        if (empty($valid_tables)) {
            error_log("Backup stepDumpDb: no valid tables found");
            return false;
        }

        $result = $database->db_backup($valid_tables, $this->tmp_backup_dir);

        if ($result === false) {
            error_log("Backup stepDumpDb: dump failed"
                . " — dir=" . $this->tmp_backup_dir
                . " writable=" . (is_writable($this->tmp_backup_dir) ? 'yes' : 'no')
                . " errors=" . print_r($database->errors, true));
            return false;
        }

        error_log("Backup step 2 done: SQL dump created - $result");
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

        if (!mkdir($db_dir, 0777, true) && !is_dir($db_dir)) {
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
            error_log("Backup stepArchiveDb: PclZip error - " . $archive->errorInfo(true));
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
     * Step 5: Upload ONE file per cron call.
     * Returns 'done', 'continue', or 'error'.
     */
    public function stepUploadNextFile(): string {
        if (empty($this->tmp_backup_dir)) {
            error_log("Backup stepUploadNextFile: no state found");
            return 'error';
        }

        $site_name = $_SERVER['HTTP_HOST'];
        $site_url  = get_home_url();

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

        if (!$access_token) {
            error_log("Backup stepUploadNextFile: failed to get Dropbox access token");
            return 'error';
        }

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

            $dropbox_path = '/Secondary Backups/' . $site_name;
            $linkData     = $drops->getOrCreateSharedLinkForFolder($access_token, $dropbox_path);

            error_log("Backup step 5: linkData = " . var_export($linkData, true));

            if ($linkData !== false) {
                $sitemap_file = EVN_DIR . 'includes/class/Admin/Settings/SiteMap/SiteMap.php';
                if (file_exists($sitemap_file)) {
                    require_once $sitemap_file;
                }

                if (function_exists('sendtoGoogleUrls')) {
                    sendtoGoogleUrls($site_url, $linkData);
                    error_log("Backup step 5: sendtoGoogleUrls called successfully");
                } else {
                    error_log("Backup step 5: sendtoGoogleUrls still not found after require - $sitemap_file");
                }
            } else {
                error_log("Backup step 5: linkData is false - Dropbox link failed");
            }

            error_log("Backup step 5 done: all files uploaded");
            return 'done';
        }

        $part       = $all_parts[$index];
        $local_path = $this->tmp_backup_dir . '/' . $part;

        if (!file_exists($local_path)) {
            error_log("Backup stepUploadNextFile: file not found, skipping - $local_path");
            $this->upload_index = $index + 1;
            $this->saveState();
            return 'continue';
        }

        $size = filesize($local_path);
        $fp   = fopen($local_path, 'rb');

        if (!$fp || $size === false) {
            error_log("Backup stepUploadNextFile: cannot open - $local_path");
            $this->upload_index = $index + 1;
            $this->saveState();
            return 'continue';
        }

        try {
            if ($size > self::LARGE_FILE_THRESHOLD) {
                error_log("Backup stepUploadNextFile: large file ({$size} bytes), using chunked upload - $part");
                $response = $drops->SendLargeFile($access_token, $full_backup_path . '/' . $part, $fp, $size);
            } else {
                $response = $drops->SendFile($access_token, $full_backup_path . '/' . $part, $fp, $size);
            }

            $decoded = json_decode($response, true);
            if (empty($decoded['id']) && empty($decoded['name'])) {
                error_log("Backup stepUploadNextFile: upload not confirmed for $part - response: " . $response);
            } else {
                error_log("Backup stepUploadNextFile: uploaded $part");
            }
        } catch (\Throwable $e) {
            error_log("Backup stepUploadNextFile: error on $part - " . $e->getMessage());
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
            error_log("Backup step 6 done: temp directory deleted - {$this->tmp_backup_dir}");
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
