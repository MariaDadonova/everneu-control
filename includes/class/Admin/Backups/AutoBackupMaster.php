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
        $upload_dir = $upload['basedir'];
        $upload_dir = $upload_dir . '/backups/';
        $src_dir = $_SERVER['DOCUMENT_ROOT'];
        $name = str_replace('.', '_', $instal).date('Y-m-d_h-i-s').".zip";

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
    }

    public function createBackup($upload_dir, $src_dir, $name)
    {
        $archive_dir = $upload_dir;

        $zip = new ZipArchive();
        $fileName = $archive_dir.$name;

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
        $path = $upload_dir['basedir'] . '/backups/'.$name;
        $fp = fopen($path, 'rb');
        $size = filesize($path);
        $path_in_db = $instal.'/'.$name;

        if (!$drops->GetListFolder($access_token, $instal)) {
            $drops->CreateFolder($access_token, $instal);
        }

        //Send file to dropbox
        $drops->SendFile($access_token, $path_in_db, $fp, $size);
    }

}