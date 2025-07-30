<?php

namespace EVN\Admin\Backups;

use DropboxAPI;
use EVN\Helpers\Encryption;
use MySql;
use ZipArchive;

include_once __DIR__ . '/ZipArchive/ZipArchive.php';
include_once __DIR__ . '/DropboxAPIClient/DropboxAPI.php';
include_once __DIR__ . '/SqlDump/MySql.php';
include_once WP_PLUGIN_DIR . '/everneu-control/includes/class/Admin/Settings/SiteMap/SiteMap.php';
require_once ABSPATH . 'wp-admin/includes/class-pclzip.php';



/**
 * General purpose:
 *
 * Combine class calls to create a database dump, .zip for files, and send .zip to DropBox.
 *
 * @version 1.1
 */


class AutoBackupMaster {

    public function __construct() {
        $this->runBackup();
    }

    public function runBackup() {
        $instal = $_SERVER['HTTP_HOST'];
        $upload = wp_upload_dir();
        $backup_dir = $upload['basedir'] . '/backups/';

        if (!file_exists($backup_dir)) {
            mkdir($backup_dir, 0755, true);
        }

        $tmp_backup_dir = sys_get_temp_dir() . '/' . str_replace('.', '_', $instal) . date('_Y-m-d_H-i-s');

        error_log("Tmp backup dir: $tmp_backup_dir");

        if (!mkdir($tmp_backup_dir, 0755, true) && !is_dir($tmp_backup_dir)) {
            error_log("Failed to create temp backup directory: $tmp_backup_dir");
            return;
        }

        $src_dir = realpath($_SERVER['DOCUMENT_ROOT']);
        error_log("Src dir: " . $src_dir);

        $this->copyDir($src_dir, $tmp_backup_dir, ['backups', '.git', '.idea', 'node_modules', 'tmp', 'logs', 'vendor']);

        $this->createSQLdump();

        $zip_name = str_replace('.', '_', $instal) . date('Y-m-d_H-i-s') . ".zip";
        $zip_path = $backup_dir . $zip_name;

        $zip_result = $this->zipDirectory($tmp_backup_dir, $zip_path);

        if ($zip_result) {
            error_log("Backup created: $zip_path");
            $this->sendFileToDropbox($instal, $zip_name);
        } else {
            error_log("Backup failed");
        }

        $this->deleteDir($tmp_backup_dir);
    }

    private function copyDir(string $src, string $dst, array $excludeDirs = []) {
        $dir = opendir($src);
        @mkdir($dst, 0755, true);

        while(false !== ($file = readdir($dir))) {
            if ($file == '.' || $file == '..') continue;
            if (in_array($file, $excludeDirs)) continue;

            $srcPath = $src . DIRECTORY_SEPARATOR . $file;
            $dstPath = $dst . DIRECTORY_SEPARATOR . $file;

            if (is_dir($srcPath)) {
                $this->copyDir($srcPath, $dstPath, $excludeDirs);
            } else {
                copy($srcPath, $dstPath);
            }
        }
        closedir($dir);
    }

    private function zipDirectory(string $source, string $destination) {
        $source = realpath($source);
        if (!$source || !file_exists($source)) {
            error_log("PclZip: Source not found: $source");
            return false;
        }

        $archive = new \PclZip($destination);

        $files = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($source), \RecursiveIteratorIterator::LEAVES_ONLY);
        foreach ($iterator as $file) {
            if (!$file->isFile()) continue;
            $filePath = $file->getRealPath();
            $relativePath = substr($filePath, strlen($source) + 1);

            $files[] = [
                PCLZIP_ATT_FILE_NAME => $filePath,
                PCLZIP_ATT_FILE_NEW_FULL_NAME => $relativePath,
            ];
            error_log("PclZip - adding file: $relativePath");
        }

        if (empty($files)) {
            error_log("PclZip: No files to archive");
            return false;
        }

        $result = $archive->create($files);

        if ($result == 0) {
            error_log("PclZip error: " . $archive->errorInfo(true));
            return false;
        }

        return true;
    }

    private function deleteDir(string $dirPath) {
        if (!is_dir($dirPath)) return;
        $files = array_diff(scandir($dirPath), ['.', '..']);
        foreach ($files as $file) {
            $fullPath = $dirPath . DIRECTORY_SEPARATOR . $file;
            if (is_dir($fullPath)) {
                $this->deleteDir($fullPath);
            } else {
                @unlink($fullPath);
            }
        }
        @rmdir($dirPath);
        error_log("Deleted temp backup directory: $dirPath");
    }

    public function createSQLdump()
    {
        $database = new MySql();
        $all_tables   = $database->get_tables();
        $database->db_backup($all_tables);

        if ($database->db_backup($all_tables)) {
            error_log("Dump created");
        } else {
            error_log("Failed create dump");
        }
    }

    public function createBackup($upload_dir, $src_dir, $name)
    {
        $zip_path = $upload_dir . $name;

        $result = Zip($src_dir, $zip_path);

        if ($result) {
            error_log("Backup created: $zip_path");
        } else {
            error_log("Backup failed");
        }
    }

    public function sendFileToDropbox($instal, $name)
    {
        //Authorization
        $dropbox_settings = get_option('ev_dropbox_settings');
        if (!empty($dropbox_settings) && is_string($dropbox_settings)) {
            $dropbox_settings = json_decode($dropbox_settings, true);
        }

        $refresh_token = Encryption::decrypt($dropbox_settings['refresh_token']);
        $app_key = Encryption::decrypt($dropbox_settings['app_key']);
        $app_secret = Encryption::decrypt($dropbox_settings['app_secret']);
        $access_code = Encryption::decrypt($dropbox_settings['access_code']);

        $drops = new DropboxAPI($app_key, $app_secret, $access_code);

        //Access token
        $access_token = $drops->curlRefreshToken($refresh_token);

        //Create folder in Dropbox
        $upload_dir = wp_upload_dir();
        error_log("sendFileToDropbox 97 - upload dir: " . print_r($upload_dir));

        $path = $upload_dir['basedir'] . '/backups/'.$name;
        error_log("sendFileToDropbox 100 - path: " . $path);

        clearstatcache(true, $path);

        if (!file_exists($path)) {
            error_log("Can't find a file: $path");
            return;
        }

        $fp = fopen($path, 'rb');

        if (!$fp) {
            error_log("Can't open the file: $path");
            return;
        }


        $size = filesize($path);

        if ($size === false) {
            error_log("Can't get a file's size: $path");
            fclose($fp);
            return;
        }

        $path_in_db = $instal.'/'.$name;
        error_log("sendFileToDropbox 126 - path in db: " . $path_in_db);

        if (!$drops->GetListFolder($access_token, $instal)) {
            $drops->CreateFolder($access_token, $instal);
        }

        //Send file to dropbox
        $drops->SendFile($access_token, $path_in_db, $fp, $size);

        $linkData = $drops->getOrCreateSharedLinkForFolder($access_token, '/Secondary Backups/'.$instal);

        if ($linkData !== false) {
            echo 'Link to folder: ' . $linkData;
            sendtoGoogleUrls($instal, $linkData);
        } else {
            echo 'Error creating link.';
        }


    }

}