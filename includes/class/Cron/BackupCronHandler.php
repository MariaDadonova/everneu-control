<?php

namespace EVN\Cron;

use EVN\Helpers\Environment;
use EVN\Admin\Backups\AutoBackupMaster;

class BackupCronHandler {

    public function __construct() {
        add_action('schedule_backup_by_month',    [$this, 'run']);
        add_action('backup_step_dump_db',         [$this, 'step_dump_db']);
        add_action('backup_step_archive_db',      [$this, 'step_archive_db']);
        add_action('backup_step_archive_folder',  [$this, 'step_archive_folder']);
        add_action('backup_step_upload_dropbox',  [$this, 'step_upload_dropbox']);
        add_action('backup_step_cleanup',         [$this, 'step_cleanup']);
    }

    public function run() {
        if (Environment::isProduction()) {
            require_once EVN_DIR . 'includes/class/Admin/Backups/AutoBackupMaster.php';
            $backup = new AutoBackupMaster();
            if ($backup->initBackup()) {
                wp_clear_scheduled_hook('backup_step_dump_db');
                wp_schedule_single_event(time() + 5, 'backup_step_dump_db');
                spawn_cron();
                error_log('Backup initiated successfully.');
            } else {
                error_log('Failed to initiate backup.');
            }
        }
    }

    public function step_dump_db() {
        error_log('Backup step_dump_db CALLED');
        require_once EVN_DIR . 'includes/class/Admin/Backups/AutoBackupMaster.php';
        $backup = new AutoBackupMaster();
        if ($backup->stepDumpDb()) {
            wp_clear_scheduled_hook('backup_step_archive_db');
            wp_schedule_single_event(time() + 5, 'backup_step_archive_db');
            spawn_cron();
        } else {
            error_log('Backup step_dump_db FAILED — scheduling cleanup');
            wp_schedule_single_event(time() + 5, 'backup_step_cleanup');
            spawn_cron();
        }
    }

    public function step_archive_db() {
        error_log('Backup step_archive_db CALLED');
        require_once EVN_DIR . 'includes/class/Admin/Backups/AutoBackupMaster.php';
        $backup = new AutoBackupMaster();
        if ($backup->stepArchiveDb()) {
            $backup->initFoldersQueue();
            wp_clear_scheduled_hook('backup_step_archive_folder');
            wp_schedule_single_event(time() + 5, 'backup_step_archive_folder');
            spawn_cron();
        } else {
            error_log('Backup step_archive_db FAILED — scheduling cleanup');
            wp_schedule_single_event(time() + 5, 'backup_step_cleanup');
            spawn_cron();
        }
    }

    public function step_archive_folder() {
        error_log('Backup step_archive_folder CALLED');
        require_once EVN_DIR . 'includes/class/Admin/Backups/AutoBackupMaster.php';
        $backup = new AutoBackupMaster();
        $result = $backup->stepArchiveNextFolder();

        if ($result === 'continue') {
            // There are still folders - call ourselves again
            wp_clear_scheduled_hook('backup_step_archive_folder');
            wp_schedule_single_event(time() + 5, 'backup_step_archive_folder');
            spawn_cron();
        } elseif ($result === 'done') {
            wp_clear_scheduled_hook('backup_step_archive_folder');
            // All folders are ready - go to download
            wp_clear_scheduled_hook('backup_step_upload_dropbox');
            wp_schedule_single_event(time() + 5, 'backup_step_upload_dropbox');
            spawn_cron();
        } else {
            error_log('Backup step_archive_folder FAILED — scheduling cleanup');
            wp_schedule_single_event(time() + 5, 'backup_step_cleanup');
            spawn_cron();
        }
    }

    public function step_upload_dropbox() {
        require_once EVN_DIR . 'includes/class/Admin/Backups/AutoBackupMaster.php';
        $backup = new AutoBackupMaster();

        error_log('step_upload_dropbox CALLED');
        $result = $backup->stepUploadNextFile();

        if ($result === 'done') {
            wp_clear_scheduled_hook('backup_step_cleanup');
            wp_schedule_single_event(time() + 5, 'backup_step_cleanup');
            spawn_cron();
        } elseif ($result === 'continue') {
            wp_clear_scheduled_hook('backup_step_upload_dropbox');
            wp_schedule_single_event(time() + 5, 'backup_step_upload_dropbox');
            spawn_cron();
        } else {
            error_log('Backup stepUploadDropbox: fatal error, stopping');
        }
    }

    public function step_cleanup() {
        require_once EVN_DIR . 'includes/class/Admin/Backups/AutoBackupMaster.php';
        $backup = new AutoBackupMaster();
        $backup->stepCleanup();
        error_log('Backup cleanup done.');
    }
}