<?php
namespace EVN\Helpers;

class CronInterval {

    public static function add_custom_schedules($schedules) {
        if (!isset($schedules['monthly'])) {
            $schedules['monthly'] = [
                'interval' => 30 * 24 * 60 * 60, // 30 days
                'display' => __('Once Monthly', 'everneu-control')
            ];
        }
        return $schedules;
    }

    public static function schedule_monthly_backup_event() {
        if (!wp_next_scheduled('schedule_backup_by_month')) {
            wp_schedule_event(time(), 'monthly', 'schedule_backup_by_month');
        }
    }


    public static function clear_scheduled_backup_event() {
        $timestamp = wp_next_scheduled('schedule_backup_by_month');
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'schedule_backup_by_month');
        }
    }
}