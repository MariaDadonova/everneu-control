<?php

namespace EVN\Admin\Settings\SVG;

/**
 * Allow uploading svg webp file types
 *
 * @version 1.1
 */


class AllowSVGUpload
{
    function __construct() {
        add_action('init', [$this, 'init_filters'], 1);
    }


    public function display_svg_ui() {
        ?>

        <h3>Include SVG</h3>

        <?php
        // save settings
        if (isset($_POST['ec_plugin_submit'])) {
            $svg_option_value = isset($_POST['svg_option']) ? $_POST['svg_option'] : 'off';
            $updated = update_option('svg_option', $svg_option_value);
            if ($updated) {
                //error_log("SVG Option saved successfully");
                echo '<div id="message" class="updated"><p>Settings saved successfully!</p></div>';
            } else {
                //error_log("Failed to save SVG Option");
                echo '<div id="message" class="updated"><p>Failed to save SVG Option</p></div>';
            }
        }

        // get a current option
        $svg_current_value = get_option('svg_option', '');
        echo '<form method="post" action="">';
        echo '<div class="ev-form-row">';

        // if option turn on, checkbox is checked
        $checked = (esc_attr($svg_current_value) == 'on') ? 'checked' : '';
        echo '<input type="hidden" id="svg_option" name="svg_option" value="off">';
        echo '<input type="checkbox" id="svg_option" name="svg_option" value="on" ' . $checked . '>';
        echo '<label id="lb" for="svg_option"> Allow uploading SVG files</label>';
        echo '<br><br><input type="submit" name="ec_plugin_submit" class="button button-primary" value="Save">';
        echo '</div>';
        echo '</form>';
    }

    public function init_filters() {
        // Get a current option
        $svg_option = get_option('svg_option', 'off');

        if ($svg_option === 'on') {
            add_filter('upload_mimes', [$this, 'allow_svg_upload'], PHP_INT_MAX);
            add_filter('wp_check_filetype_and_ext', [$this, 'svg_check_filetype_and_ext'], 10, 4);
            add_filter('wp_handle_upload_prefilter', [$this, 'check_svg_for_malicious_code']);
        } else {
            error_log('SVG Option is off. Filters not added.');
        }
    }

    public function allow_svg_upload($mimes) {
        $mimes['svg'] = 'image/svg+xml';
        return $mimes;
    }

    public function svg_check_filetype_and_ext($data, $file, $filename, $mimes) {
        $ext = pathinfo($filename, PATHINFO_EXTENSION);
        if ($ext === 'svg') {
            $data['ext'] = 'svg';
            $data['type'] = 'image/svg+xml';
            $data['proper_filename'] = $filename;
        }
        return $data;
    }


    public static function check_svg_for_malicious_code($file) {
        if (strtolower($file['type']) === 'image/svg+xml') {
            $content = file_get_contents($file['tmp_name']);

            $content = preg_replace('/<\?xml.*?\?>/s', '', $content);
            $content = preg_replace('/<script.*?>.*?<\/script>/is', '', $content);
            $content = preg_replace('/<style.*?>.*?<\/style>/is', '', $content);
            file_put_contents($file['tmp_name'], $content);
        }
        return $file;
    }

}
