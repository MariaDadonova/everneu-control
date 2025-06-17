<?php

namespace EVN\Admin\Backups;

use DropboxAPI;
use EVN\Helpers\Encryption;
use MySql;
use ZipArchive;

include_once __DIR__ . '/ZipArchive/ZipArchive.php';
include_once __DIR__ . '/DropboxAPIClient/DropboxAPI.php';
include_once __DIR__ . '/SqlDump/MySql.php';

/**
 * General purpose:
 *
 * Combine class calls to create a database dump, .zip for files, and send .zip to DropBox.
 *
 * @version 1.1
 */


class AutoBackupMaster {

    public function __construct() {
        $instal =  $_SERVER['HTTP_HOST'];

        $upload = wp_upload_dir();
        error_log("wp_upload_dir: " . print_r($upload));

        $upload_dir = $upload['basedir'];
        error_log("Upload dir: " . $upload_dir);

        $upload_dir = $upload_dir . '/backups/';
        error_log("Upload dir 2: " . $upload_dir);

        $src_dir = $_SERVER['DOCUMENT_ROOT'];
        error_log("Src dir: " . $src_dir);

        $name = str_replace('.', '_', $instal).date('Y-m-d_h-i-s').".zip";
        error_log("Name: " . $name);

        //Create a SQL dump
        $this->createSQLdump();

        //Create a backup
        $this->createBackup($upload_dir, $src_dir, $name);

        //Send file to DropBox
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
        $archive_dir = $upload_dir;
        error_log("createBackup 69 - Archive dir: " . $archive_dir);

        $fileName = $archive_dir.$name;
        error_log("createBackup 72 - fileName: " . $fileName);

        Zip($src_dir, $fileName);
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
    }

}