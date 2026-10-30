<?php

namespace EVN\Admin\Deactivation;

/**
 *
 *  Adds "Deactivate and delete credentials" link on the Plugins page.
 *  Deletes all plugin options and the uploaded Google service key file,
 *  then deactivates the plugin.
 *
 *  Regular deactivation keeps all data untouched.
 *
 *  @version 1.0
 */


class DeactivateAndClean
{
    const ACTION = 'env_deactivate_clean';

    const OPTIONS = [
        'everneu_control',
        'ev_google_service_key',
        'ev_github_token',
        'ev_dropbox_settings',
        'ev_gtm_settings',
        'ev_gtm_body_settings',
    ];

    const GOOGLE_KEY_DIR = 'google-service-key';

    public function __construct()
    {
        add_filter('plugin_action_links_' . EVN_BASENAME, [$this, 'add_action_link']);
        add_action('admin_post_'. self::ACTION, [$this, 'handle']);
    }


    public function add_action_link(array $links): array {
        if (!current_user_can('activate_plugins')) {
            return $links;
        }

        $url = wp_nonce_url(
            admin_url('admin-post.php?action=' . self::ACTION),
            self::ACTION
        );

        $confirm = esc_js('This will deactivate Everneu Control and permanently delete all its settings, tokens and the Google service key file. Continue?');

        $links['evn_deactivate_clean'] = sprintf(
            '<a href="%s" style="color:#b32d2e" onclick="return confirm(\'%s\');">%s</a>',
            esc_url($url),
            $confirm,
            esc_html('Deactivate and delete credentials')
        );

        return $links;
    }


    public function handle(): void {
        if (!current_user_can('activate_plugins')) {
            wp_die('You do not have permission to do this.', 403);
        }

        check_admin_referer(self::ACTION);

        $this->delete_options();
        $this->delete_google_key_dir();

        deactivate_plugins(EVN_BASENAME);

        wp_safe_redirect(admin_url('plugins.php?deactivate=true'));
        exit;
    }


    private function delete_options(): void {
        foreach (self::OPTIONS as $option) {
            delete_option($option);
        }
    }


    /**
     * Deletes uploads/google-service-key/ with everything inside.
     * Path is built from a known location instead of the encrypted value
     * stored in the database, so cleanup works even if the option
     * is broken or already deleted.
     */
    private function delete_google_key_dir(): void {
        // false = do not create the uploads dir if it doesn't exist
        $upload_dir = wp_upload_dir(null, false);

        if (!empty($upload_dir['error'])) {
            return;
        }

        $dir = trailingslashit($upload_dir['basedir']) . self::GOOGLE_KEY_DIR . '/';

        if (!is_dir($dir)) {
            return;
        }

        $files = glob($dir . '{,.}*', GLOB_BRACE) ?: [];

        foreach ($files as $file) {
            if (is_file($file)) {
                wp_delete_file($file);
            }
        }

        if (!@rmdir($dir)) {
            error_log('EVN: could not remove ' . $dir);
        }
    }

}