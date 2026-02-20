<?php

namespace EVN\Admin\Settings\GTM;

use EVN\Helpers\Environment;

/**
 * Change GTM priority in head
 *
 * @version 1.1
 */


class GTMTagPriority
{
    function __construct() {
        add_action('wp', [$this, 'register_gtm_hook']);
    }

    public function display_gtm_ui() {
        ?>

        <h3 style="margin-top: 40px; margin-bottom: 30px;">Set GTM</h3>

        <table class="tg" style="margin-bottom: 40px;">
            <thead>
            <tr>
                <th>Priority</th>
                <th>Effect / Placement in HTML</th>
                <th>Use Case / Notes</th>
            </tr>
            </thead>
            <tbody>
            <tr>
                <td>Negative numbers (-10, -1)</td>
                <td>Before everything else</td>
                <td>Rarely used; only if you must output something before any theme/plugin code</td>
            </tr>
            <tr>
                <td>0</td>
                <td>Very early in &lt;head&gt;; runs before almost everything else</td>
                <td>Use for scripts that must initialize first (e.g., GTM)</td>
            </tr>
            <tr>
                <td>1-5</td>
                <td>Early in &lt;head&gt;; before most theme and plugin scripts</td>
                <td>Good for critical initialization scripts or CSS variables</td>
            </tr>
            <tr>
                <td>10</td>
                <td>Default WordPress priority; standard placement</td>
                <td>Most theme and plugin scripts use this; safe default</td>
            </tr>
            <tr>
                <td>20-50</td>
                <td>Later in &lt;head&gt;; after most scripts and styles</td>
                <td>Useful for analytics scripts that depend on other scripts being loaded first</td>
            </tr>
            <tr>
                <td>100+</td>
                <td>Very late in &lt;head&gt;; almost last</td>
                <td>Use for optional scripts or tracking pixels that can load after everything else</td>
            </tr>
            </tbody>
        </table>

        <?php
        // save settings
        if (isset($_POST['gtm_plugin_submit'])) {
            $gtm_prior = sanitize_text_field($_POST['gtm_priority']);
            $gtm_scr = wp_unslash($_POST['gtm_script']);

            $gtm_script_value = array(
                'priority' => $gtm_prior,
                'script'   => $gtm_scr
            );

            if (!get_option('ev_gtm_settings')) {
                add_option('ev_gtm_settings', $gtm_script_value);
                echo '<div id="message" class="updated"><p>GTM saved successfully!</p></div>';
                error_log("GTM saved successfully");
            } else {
                update_option('ev_gtm_settings', $gtm_script_value);
                echo '<div id="message" class="updated"><p>GTM updated successfully!</p></div>';
                error_log("GTM updated successfully");
            }
        }

        // get a current option
        $gtm_settings = get_option('ev_gtm_settings');
        if (!empty($gtm_settings) && is_array($gtm_settings)) {
            $gtm_prior = $gtm_settings['priority'];
            $gtm_scr   = $gtm_settings['script'];
        } else {
            $gtm_prior = $gtm_scr = '';
        }


        if (Environment::isProduction()) {
            echo '<span style="color: green">The current environment: Production. GTM included.</span>';
        } else {
            echo '<span style="color: red">The current environment: Not production. GTM turned off.</span>';
        }

        echo '<form method="post" class="ev-submit-form">
         <div class="ev-form">
           <div class="">
             <label id="gtm_script_label_priority" for="gtm_script_priority">GTM priority</label>
           </div>
           <div class="">
              <input class="style-field" type="number" id="gtm_priority" name="gtm_priority" value="'. $gtm_prior .'">
           </div>
         </div>
         <div class="ev-form">
           <div class="">
             <label id="gtm_script_label" for="gtm_script">GTM script</label>
           </div>
           <div class="">
              <textarea class="style-field" name="gtm_script" id="gtm_script">'. $gtm_scr .'</textarea>
           </div>
         </div>
         <br><input type="submit" name="gtm_plugin_submit" class="button button-primary" value="Save">
       </form>';
    }

    public function register_gtm_hook() {

        $gtm_settings = get_option('ev_gtm_settings');

        if (empty($gtm_settings['script'])) {
            return;
        }

        $priority = !empty($gtm_settings['priority'])
            ? (int) $gtm_settings['priority']
            : 10;

        if (Environment::isProduction()) {
            global $wp_filter;

            if (isset($wp_filter['fl_head_open'])) {
                // Beaver Builder
                add_action('fl_head_open', [$this, 'output_gtm'], $priority);
                error_log('GTM hooked to fl_head_open');
            } else {
                add_action('wp_head', [$this, 'output_gtm'], $priority);
                error_log('GTM hooked to wp_head');
            }
        }
    }

    public function output_gtm() {

        $gtm_settings = get_option('ev_gtm_settings');

        if (!empty($gtm_settings['script'])) {
            echo $gtm_settings['script'];
        }
    }


}