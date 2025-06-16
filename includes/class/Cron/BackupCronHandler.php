<?php

namespace EVN\Cron;

use EVN\Helpers\Environment;
use EVN\Admin\Backups\AutoBackupMaster;

class BackupCronHandler {

    public function __construct() {
        add_action('schedule_backup_by_month', [$this, 'run']);
    }

    public function run() {
        if (Environment::isProduction()) {
            require_once EVN_DIR . 'includes/class/Admin/Backups/AutoBackupMaster.php';

            $backup = new AutoBackupMaster();

            if ($backup) {
                error_log('Backup successfully uploaded to Dropbox.');
            } else {
                error_log('Failed to create backup.');
            }

        }

        //for debugging cron task
        //$log = time();
        //file_put_contents(__DIR__ . '/log.txt', date('[Y-m-d H:i:s]').'log time '. print_r($log, true) . PHP_EOL, FILE_APPEND | LOCK_EX);

    }
}