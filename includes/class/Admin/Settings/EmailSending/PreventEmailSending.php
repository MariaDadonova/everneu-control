<?php

namespace EVN\Admin\Settings\EmailSending;

use EVN\Helpers\Environment;

class PreventEmailSending
{
    function __construct() {
        add_action('init', [$this, 'register_stop_email_sending_hook'], 1);
    }

    public function display_prevent_email_sending_ui() {
        ?>

        <h3 style="margin-top: 40px; margin-bottom: 30px;">Prevent emails sending</h3>

        <?php

        if (Environment::isProduction()) {
            echo '<span style="color: green">The current environment: Production. Emails will send.</span>';
        } else {
            echo '<span style="color: red">The current environment: Not production. Emails sending turned off.</span>';
        }
    }

    public function register_stop_email_sending_hook() {
        if (Environment::isProduction()) {
            return;
        }

        global $wp_version;

        if (version_compare($wp_version, '5.7', '>=')) {
            add_filter('pre_wp_mail', function($return, $atts) {
                error_log('EVN PreventEmailSending: blocked email to "'
                        . (is_array($atts['to']) ? implode(', ', $atts['to']) : $atts['to'])
                        . '" subject "' . $atts['subject'] . '"');
                return false;
            }, 1, 2);
        } else {
            // Fallback for WP < 5.7 - override PHPMailer to prevent actual sending
            add_action('phpmailer_init', function ($phpmailer) {
                $phpmailer->ClearAllRecipients();
                error_log('EVN PreventEmailSending: cleared all recipients via phpmailer_init (WP < 5.7)');
            });
        }

    }

}