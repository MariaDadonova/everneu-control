<?php

namespace EVN\Admin\Settings\SVG;

/**
 * Allow uploading SVG and WebP file types
 *
 * @version 1.2
 */

class AllowSVGUpload
{
    /**
     * Constructor: hook into WordPress 'init' to initialize filters
     */
    function __construct() {
        add_action('init', [$this, 'init_filters'], 1);
    }

    /**
     * Display the SVG settings UI in the admin panel
     */
    public function display_svg_ui() {
        ?>
        <h3>Include SVG</h3>
        <?php
        // Save settings when the form is submitted
        if (isset($_POST['ec_plugin_submit'])) {
            $svg_option_value = isset($_POST['svg_option']) ? $_POST['svg_option'] : 'off';
            $updated = update_option('svg_option', $svg_option_value);
            if ($updated) {
                echo '<div id="message" class="updated"><p>Settings saved successfully!</p></div>';
            } else {
                echo '<div id="message" class="updated"><p>Failed to save SVG Option</p></div>';
            }
        }

        // Get the current option value
        $svg_current_value = get_option('svg_option', '');
        echo '<form method="post" action="">';
        echo '<div class="ev-form-row">';

        // If option is turned on, checkbox is checked
        $checked = (esc_attr($svg_current_value) == 'on') ? 'checked' : '';
        echo '<input type="hidden" id="svg_option" name="svg_option" value="off">';
        echo '<input type="checkbox" id="svg_option" name="svg_option" value="on" ' . $checked . '>';
        echo '<label id="lb" for="svg_option"> Allow uploading SVG files</label>';
        echo '<br><br><input type="submit" name="ec_plugin_submit" class="button button-primary" value="Save">';
        echo '</div>';
        echo '</form>';
    }

    /**
     * Initialize filters to handle SVG uploads and display
     */
    public function init_filters() {
        // Get current plugin option
        $svg_option = get_option('svg_option', 'off');

        if ($svg_option === 'on') {
            // Enable SVG upload and validation filters
            add_filter('upload_mimes', [$this, 'allow_svg_upload'], PHP_INT_MAX);
            add_filter('wp_check_filetype_and_ext', [$this, 'svg_check_filetype_and_ext'], 10, 4);
            add_filter('wp_handle_upload_prefilter', [$this, 'check_svg_for_malicious_code']);

            // Apply fixes for displaying SVG correctly in WP
            $this->add_svg_display_fixes();
        } else {
            error_log('SVG Option is off. Filters not added.');
        }
    }

    /**
     * Allow SVG MIME type for uploads
     *
     * @param array $mimes Existing allowed MIME types
     * @return array Modified MIME types including SVG
     */
    public function allow_svg_upload($mimes) {
        $mimes['svg'] = 'image/svg+xml';
        return $mimes;
    }

    /**
     * Ensure correct file type and extension for SVG
     *
     * @param array $data File type data
     * @param string $file File path
     * @param string $filename File name
     * @param array $mimes Allowed MIME types
     * @return array Modified file type data
     */
    public function svg_check_filetype_and_ext($data, $file, $filename, $mimes) {
        $ext = pathinfo($filename, PATHINFO_EXTENSION);
        if ($ext === 'svg') {
            $data['ext'] = 'svg';
            $data['type'] = 'image/svg+xml';
            $data['proper_filename'] = $filename;
        }
        return $data;
    }

    /**
     * Remove potentially malicious code from SVG
     *
     * @param array $file Uploaded file data
     * @return array Sanitized file data
     */
    public static function check_svg_for_malicious_code($file) {
        if (strtolower($file['type']) === 'image/svg+xml') {
            $content = file_get_contents($file['tmp_name']);
            // Remove XML declarations
            $content = preg_replace('/<\?xml.*?\?>/s', '', $content);
            // Remove <script> tags
            $content = preg_replace('/<script.*?>.*?<\/script>/is', '', $content);
            // Remove <style> tags
            $content = preg_replace('/<style.*?>.*?<\/style>/is', '', $content);
            file_put_contents($file['tmp_name'], $content);
        }
        return $file;
    }

    /**
     * Fix displaying SVG in WordPress
     * - Corrects src and removes fake width/height
     * - Disables srcset for SVG
     * - Removes fake sizes in admin media modal
     */
    private function add_svg_display_fixes() {
        // Fix the src URL and dimensions for SVG attachments
        add_filter('wp_get_attachment_image_src', function($image, $attachment_id) {
            if (get_post_mime_type($attachment_id) === 'image/svg+xml') {
                $src = wp_get_attachment_url($attachment_id);
                $image = [$src, null, null, false]; // Only URL, no width/height
            }
            return $image;
        }, 10, 2);

        // Disable srcset for SVG images
        add_filter('wp_calculate_image_srcset', function($sources, $size_array, $image_src, $image_meta, $attachment_id) {
            if (get_post_mime_type($attachment_id) === 'image/svg+xml') {
                return false;
            }
            return $sources;
        }, 10, 5);

        // Remove fake sizes in admin media modal for SVG
        add_filter('wp_prepare_attachment_for_js', function($response, $attachment, $meta) {
            if ($response['mime'] === 'image/svg+xml' && empty($response['sizes'])) {
                $response['sizes'] = [];
            }
            return $response;
        }, 10, 3);
    }
}