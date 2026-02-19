<?php

namespace EVN\Cron;

use EVN\Helpers\Environment;
use EVN\Admin\Backups\AutoBackupMaster;

class BackupCronHandler {

    public function __construct() {
        add_action('schedule_backup_by_month',    [$this, 'run']);
        add_action('backup_step_dump_db',         [$this, 'step_dump_db']);
        add_action('backup_step_archive_db',      [$this, 'step_archive_db']);
        add_action('backup_step_archive_folders', [$this, 'step_archive_folders']);
        add_action('backup_step_upload_dropbox',  [$this, 'step_upload_dropbox']);
        add_action('backup_step_cleanup',         [$this, 'step_cleanup']);
    }

    public function run() {
        if (Environment::isProduction()) {
            require_once EVN_DIR . 'includes/class/Admin/Backups/AutoBackupMaster.php';
            $backup = new AutoBackupMaster();
            if ($backup->initBackup()) {
                wp_schedule_single_event(time() + 5, 'backup_step_dump_db');
                error_log('Backup initiated successfully.');
            } else {
                error_log('Failed to initiate backup.');
            }
        }
    }

    public function step_dump_db() {
        require_once EVN_DIR . 'includes/class/Admin/Backups/AutoBackupMaster.php';
        $backup = new AutoBackupMaster();
        if ($backup->stepDumpDb()) {
            wp_schedule_single_event(time() + 5, 'backup_step_archive_db');
        }
    }

    public function step_archive_db() {
        require_once EVN_DIR . 'includes/class/Admin/Backups/AutoBackupMaster.php';
        $backup = new AutoBackupMaster();
        if ($backup->stepArchiveDb()) {
            wp_schedule_single_event(time() + 5, 'backup_step_archive_folders');
        }
    }

    public function step_archive_folders() {
        require_once EVN_DIR . 'includes/class/Admin/Backups/AutoBackupMaster.php';
        $backup = new AutoBackupMaster();
        if ($backup->stepArchiveFolders()) {
            wp_schedule_single_event(time() + 5, 'backup_step_upload_dropbox');
        }
    }

    public function step_upload_dropbox() {
        require_once EVN_DIR . 'includes/class/Admin/Backups/AutoBackupMaster.php';
        $backup = new AutoBackupMaster();
        if ($backup->stepUploadDropbox()) {
            wp_schedule_single_event(time() + 5, 'backup_step_cleanup');
        }
    }

    public function step_cleanup() {
        require_once EVN_DIR . 'includes/class/Admin/Backups/AutoBackupMaster.php';
        $backup = new AutoBackupMaster();
        $backup->stepCleanup();
        error_log('Backup cleanup done.');
    }
}