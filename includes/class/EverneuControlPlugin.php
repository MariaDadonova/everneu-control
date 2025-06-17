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
                WP_PLUGIN_DIR . '/everneu-control/everneu-control.php',
                'MariaDadonova',
                'everneu-control',
                'master',
                ''
            );
            delete_site_transient('update_plugins');
        });
        /* End of this part */

        require_once EVN_DIR . 'includes/class/Cron/BackupCronHandler.php';
        new \EVN\Cron\BackupCronHandler();

        // Registration of activation/deactivation hooks
        register_activation_hook(__FILE__, ['\EVN\Activator', 'activate']);
        register_deactivation_hook(__FILE__, ['\EVN\Activator', 'deactivate']);

        require_once EVN_DIR . 'includes/class/Admin/Settings/Settings.php';    
        require_once EVN_DIR . 'includes/class/Admin/Backups/Backups.php';


        new Admin\Settings\Settings;
        new Admin\Backups\Backups;


    }




}