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

        <h3 style="margin-top: 40px; margin-bottom: 30px;">Set GTM in head</h3>

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
       </form>'; ?>

        <h3 style="margin-top: 40px; margin-bottom: 30px;">Set GTM in body</h3>
        <table class="tg" style="margin-bottom: 40px;">
            <thead>
            <tr>
                <th>Hook</th>
                <th>Priority</th>
            </tr>
            </thead>
            <tbody>
            <tr>
                <td>wp_body_open</td>
                <td>Immediately after body tag</td>
            </tr>
            <tr>
                <td>the_content</td>
                <td>In the middle of the content</td>
            </tr>
            <tr>
                <td>wp_footer</td>
                <td>Before /body tag</td>
            </tr>
            </tbody>
        </table>

        <?php
        // save settings
        if (isset($_POST['gtm_body_plugin_submit'])) {
            $gtm_body_prior = sanitize_text_field($_POST['gtm_body_priority']);
            $gtm_code_body_priority = sanitize_text_field($_POST['gtm_code_body_priority']);
            $gtm_body_scr = wp_unslash($_POST['gtm_body_script']);

            $gtm_body_script_value = array(
                'priority' => $gtm_body_prior,
                'code-priority' => $gtm_code_body_priority,
                'script'   => $gtm_body_scr
            );

            if (!get_option('ev_gtm_body_settings')) {
                add_option('ev_gtm_body_settings', $gtm_body_script_value);
                echo '<div id="message" class="updated"><p>GTM in body saved successfully!</p></div>';
                error_log("GTM in body saved successfully");
            } else {
                update_option('ev_gtm_body_settings', $gtm_body_script_value);
                echo '<div id="message" class="updated"><p>GTM in body updated successfully!</p></div>';
                error_log("GTM in body updated successfully");
            }
        }

        // get a current option
        $gtm_body_settings = get_option('ev_gtm_body_settings');
        if (!empty($gtm_body_settings) && is_array($gtm_body_settings)) {
            $gtm_body_prior         = $gtm_body_settings['priority'];
            $gtm_code_body_priority = $gtm_body_settings['code-priority'];
            $gtm_body_scr           = $gtm_body_settings['script'];
        } else {
            $gtm_body_prior = $gtm_code_body_priority = $gtm_body_scr = '';
        }


        echo '<form method="post" class="ev-body-submit-form">
                    <div class="ev-form">
                        <div class="">
                           <label id="gtm_body_script_label_priority" for="gtm_body_script_priority">Priority for hook</label>
                        </div>
                        <div class="">
                           <input class="style-field" type="number" id="gtm_body_priority" name="gtm_body_priority" value="'. $gtm_body_prior .'">
                        </div>
                    </div>
                    <div class="ev-form">
                        <div class="">
                           <label id="gtm_code_body_script_label_priority" for="gtm_code_body_script_priority">Priority in code</label>
                        </div>
                        <div class="">
                           <select name="gtm_code_body_priority" id="gtm_code_body_priority">
                                     <option value="body-open"'.selected($gtm_code_body_priority, 'body-open', false).'>wp_body_open</option>
                                     <option value="body-middle"'.selected($gtm_code_body_priority, 'body-middle', false).'>the_content</option>
                                     <option value="body-close"'.selected($gtm_code_body_priority, 'body-close', false).'>wp_footer</option>
                           </select>
                        </div>
                    </div>
                    <div class="ev-form">
                        <div class="">
                           <label id="gtm_body_script_label" for="gtm_body_script">GTM script</label>
                        </div>
                        <div class="">
                           <textarea class="style-field" name="gtm_body_script" id="gtm_body_script">'. $gtm_body_scr .'</textarea>
                        </div>
                    </div>
                    <br><input type="submit" name="gtm_body_plugin_submit" class="button button-primary" value="Save">
                 </form>';

    }

    public function register_gtm_hook() {
        if (!Environment::isProduction()) {
            return;
        }

        global $wp_filter;

        $gtm_settings = get_option('ev_gtm_settings');
        if (!empty($gtm_settings['script'])) {
            $priority = !empty($gtm_settings['priority'])
                ? (int) $gtm_settings['priority']
                : 10;
            $hook = isset($wp_filter['fl_head_open'])
                ? 'fl_head_open'
                : 'wp_head';
            add_action($hook, [$this, 'output_gtm'], $priority);
        }

        $gtm_body_settings = get_option('ev_gtm_body_settings');
        if (!empty($gtm_body_settings['script'])) {
            $priority_body = !empty($gtm_body_settings['priority'])
                ? (int) $gtm_body_settings['priority']
                : 10;

            $code_priority = $gtm_body_settings['code-priority'] ?? 'body-open';

            if ($code_priority === 'body-middle') {
                add_filter('the_content', function($content) {
                    $gtm = get_option('ev_gtm_body_settings');
                    if (!empty($gtm['script'])) {
                        $content .= $gtm['script'];
                    }
                    return $content;
                }, $priority_body);
            } else {
                $hook = $code_priority === 'body-close' ? 'wp_footer' : 'wp_body_open';

                if (isset($wp_filter['fl_body_open']) && $hook === 'wp_body_open') {
                    $hook = 'fl_body_open';
                }

                add_action($hook, [$this, 'output_gtm_body'], $priority_body);
            }
        }
    }

    public function output_gtm() {

        $gtm_settings = get_option('ev_gtm_settings');

        if (!empty($gtm_settings['script'])) {
            echo $gtm_settings['script'];
        }
    }

    public function output_gtm_body() {

        $gtm_settings = get_option('ev_gtm_body_settings');

        if (!empty($gtm_settings['script'])) {
            echo $gtm_settings['script'];
        }
    }


}