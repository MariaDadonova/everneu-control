<?php

namespace EVN;

use EVN\Helpers\CronInterval;
use EVN\Helpers\Environment;

class Activator
{
    public static function activate() {
        // Adding a custom interval (the filter is triggered globally, without conditions)
        add_filter('cron_schedules', [CronInterval::class, 'add_custom_schedules']);

        // if the environment is production, we register the cron task
        if (Environment::isProduction()) {
            CronInterval::schedule_monthly_backup_event();
        }
    }

    public static function deactivate() {
        // Deleting the cron task
        CronInterval::clear_scheduled_backup_event();
    }
}