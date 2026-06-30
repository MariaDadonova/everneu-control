<?php

namespace EVN;

//use Google\Service\PubsubLite\Resource\Admin;

/**
 * Class EverneuControlPlugin
 *
 * @package Settings
 */

class EverneuControlPlugin
{

    protected $github_updater;

    protected function __construct() {
        $this->register_components();

        // Add custom cron intervals
        add_filter('cron_schedules', ['\EVN\Helpers\CronInterval', 'add_custom_schedules']);

        require_once __DIR__ . '/Admin/Settings/GTM/GTMTagPriority.php';
        new \EVN\Admin\Settings\GTM\GTMTagPriority();

        // enqueue global styles/scripts here?
    }

    /** @var static */
    protected static $instance;

    /** @return static */
    public static function get_instance() {
        if ( null === static::$instance ) {
            static::$instance = new static;
        }
        return static::$instance;
    }


    public function register_components() {

        // Include helpers
        require_once EVN_DIR . 'includes/class/Helpers/Environment.php';
        require_once EVN_DIR . 'includes/class/Helpers/CronInterval.php';
        require_once EVN_DIR . 'includes/class/Helpers/Encryption.php';

        /* Include GitHub updater */
        require_once EVN_DIR . 'includes/class/Helpers/plugin-data-parser.php';
        require_once EVN_DIR . 'includes/class/Helpers/GitHubUpdater.php';

        add_action('plugins_loaded', function() {
            $this->github_updater = new \EVN\Helpers\GitHubUpdater(
                WP_PLUGIN_DIR . '/'. EVN_BASENAME,
                'MariaDadonova',
                'everneu-control',
                'master',
                ''
            );
        });
        /* End of this part */

        require_once EVN_DIR . 'includes/class/Cron/BackupCronHandler.php';
        new \EVN\Cron\BackupCronHandler();

        require_once EVN_DIR . 'includes/class/Admin/Settings/Settings.php';
        new Admin\Settings\Settings;

        $backups_file = EVN_DIR . 'includes/class/Admin/Backups/Backups.php';
        if (file_exists($backups_file)) {
            require_once $backups_file;
            new Admin\Backups\Backups;
        } else {
            error_log('EVN: Backups.php missing, skipping init');
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error"><p>Everneu Control: the plugin installation is damaged, reinstall manually.</p></div>';
            });
        }

    }
}