<?php

namespace EVN\Cron;

use EVN\Helpers\Environment;
use EVN\Admin\Backups\AutoBackupMaster;

class BackupCronHandler {

    /**
     * Single-event steps of the backup chain.
     * They are stored in the database, so they can be copied
     * from production to another environment mid-backup.
     */
    public const STEP_HOOKS = [
        'backup_step_dump_db',
        'backup_step_archive_db',
        'backup_step_archive_folder',
        'backup_step_upload_dropbox',
        'backup_step_cleanup',
    ];

    public function __construct() {
        add_action('schedule_backup_by_month',    [$this, 'run']);
        add_action('backup_step_dump_db',         [$this, 'step_dump_db']);
        add_action('backup_step_archive_db',      [$this, 'step_archive_db']);
        add_action('backup_step_archive_folder',  [$this, 'step_archive_folder']);
        add_action('backup_step_upload_dropbox',  [$this, 'step_upload_dropbox']);
        add_action('backup_step_cleanup',         [$this, 'step_cleanup']);
    }

    /**
     * Every step checks the environment, not only run():
     * steps copied from production must not continue on another environment
     * and upload to the production Dropbox.
     */
    private function isAllowed(string $step): bool {
        if (Environment::isConfirmedProduction()) {
            return true;
        }

        error_log('EVN Backup: ' . $step . ' skipped, non-production environment ('
            . Environment::detect() . ', ' . Environment::getCurrentHost() . ')');

        self::clearSteps();

        return false;
    }

    public static function clearSteps(): void {
        foreach (self::STEP_HOOKS as $hook) {
            wp_clear_scheduled_hook($hook);
        }
    }

    public function run() {
        if (!$this->isAllowed(__FUNCTION__)) {
            return;
        }

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

    public function step_dump_db() {
        if (!$this->isAllowed(__FUNCTION__)) {
            return;
        }

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
        if (!$this->isAllowed(__FUNCTION__)) {
            return;
        }

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
        if (!$this->isAllowed(__FUNCTION__)) {
            return;
        }

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
        if (!$this->isAllowed(__FUNCTION__)) {
            return;
        }

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