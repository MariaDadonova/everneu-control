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


/**
 * General purpose:
 *
 * Combine class calls to create a database dump, .zip for files, and send .zip to DropBox.
 *
 * @version 1.1
 */


class AutoBackupMaster {

    public function __construct() {
        $instal = $_SERVER['HTTP_HOST'];

        $upload = wp_upload_dir();
        error_log("wp_upload_dir: " . print_r($upload, true));

        $upload_dir = $upload['basedir'];
        error_log("Upload dir: " . $upload_dir);

        $backup_dir = $upload_dir . '/backups/';
        error_log("Upload dir 2: " . $backup_dir);

        if (!file_exists($backup_dir)) {
            mkdir($backup_dir, 0755, true);
        }

        $src_dir = realpath($_SERVER['DOCUMENT_ROOT']);
        error_log("Src dir: " . $src_dir);

        $name = str_replace('.', '_', $instal) . date('Y-m-d_H-i-s') . ".zip";
        error_log("Name: " . $name);

        $this->createSQLdump();

        clearstatcache(true, $backup_dir);
        error_log('Is backups dir writable? ' . (is_writable($backup_dir) ? 'yes' : 'no'));

        $perms = fileperms($backup_dir);
        error_log('backups dir perms: ' . substr(sprintf('%o', $perms), -4));

        if (function_exists('posix_getpwuid')) {
            $owner = posix_getpwuid(fileowner($backup_dir));
            error_log('backups dir owner: ' . print_r($owner, true));
        } else {
            error_log('posix_getpwuid not available');
        }

        //Creating a backup
        $this->createBackup($backup_dir, $src_dir, $name);

        //Send file to Dropbox
        $this->sendFileToDropbox($instal, $name);
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