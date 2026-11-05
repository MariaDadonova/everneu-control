<?php

namespace EVN\Admin\Notices;

use EVN\Helpers\Environment;

/**
 *
 *  Shows a red admin notice when EVERNEU_PRODUCTION_DOMAINS is missing
 *  in wp-config.php and the site falls back to production behavior.
 *
 */


class EnvironmentConfigNotice
{
    public function __construct() {
        add_action('admin_notices', [$this, 'render']);
        add_action('network_admin_notices', [$this, 'render']);
    }

    public function render(): void {
        if (Environment::isConfigured() || !current_user_can('manage_options')) {
            return;
        }

        // Placeholder on purpose: on a dev site the current domain must NOT be added
        $example = "define('" . Environment::CONSTANT . "', ['production-domain.com']);";

        printf(
            '<div class="notice notice-error"><p><strong>Everneu Control:</strong> %s</p><p>%s</p><p><code>%s</code></p></div>',
            esc_html(Environment::CONSTANT . ' is not defined in wp-config.php. This site is treated as production: emails are delivered, but scheduled backups are disabled until the constant is added.'),
            esc_html('Add the production domain(s) to wp-config.php on every environment of this site (production, staging and development):'),
            esc_html($example)
        );
    }


}