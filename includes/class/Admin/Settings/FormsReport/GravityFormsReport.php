<?php

namespace EVN\Admin\Settings\FormsReport;

class GravityFormsReport
{
    function __construct() {
        add_action('wp_ajax_evn_find_form_pages', [$this, 'ajax_find_form_pages']);
    }

    public static function gravity_forms_report_ui() {
        ?>

        <h3 style="margin-top: 40px; margin-bottom: 30px;">Forms report</h3>

        <?php
        if (self::check_gf_plugin_state() === true) {
            ?>
            <span style="color: green">GravityForms plugin is active.</span>

            <script>
                jQuery(function($) {
                    $(document).on('click', '.evn-gf-check-btn', function() {
                        var btn = $(this);
                        var formId = btn.data('form-id');
                        var cell = btn.closest('td');

                        btn.prop('disabled', true);
                        cell.html('<span class="evn-pages-loading">Searching...</span>');

                        $.post(ajaxurl, {
                            action: 'evn_find_form_pages',
                            form_id: formId,
                            _ajax_nonce: '<?php echo wp_create_nonce('evn_find_form_pages'); ?>'
                        }, function(response) {
                            if (response.success) {
                                cell.html(response.data.html + '<br><small><a href="#" class="evn-pages-reset" data-form-id="' + formId + '">Reset cache</a></small>');
                            } else {
                                cell.html('<span style="color:red">Error: ' + response.data + '</span>');
                            }
                        }).fail(function() {
                            cell.html('<span style="color:red">Request failed.</span>');
                        });
                    });

                    $(document).on('click', '.evn-pages-reset', function(e) {
                        e.preventDefault();
                        var formId = $(this).data('form-id');
                        var cell = $(this).closest('td');
                        cell.html('<button class="button evn-gf-check-btn" data-form-id="' + formId + '">Check pages</button>');

                        $.post(ajaxurl, {
                            action: 'evn_find_form_pages',
                            form_id: formId,
                            reset_cache: 1,
                            _ajax_nonce: '<?php echo wp_create_nonce('evn_find_form_pages'); ?>'
                        });
                    });

                    // Pagination
                    var perPage = 10;
                    var currentPage = 1;
                    var $rows = $('.evn-gf-table tbody tr');
                    var totalPages = Math.ceil($rows.length / perPage);

                    function showPage(page) {
                        currentPage = page;
                        $rows.hide();
                        $rows.slice((page - 1) * perPage, page * perPage).show();
                        $('#evn-page-info').text('Page ' + page + ' of ' + totalPages);
                        $('#evn-prev').prop('disabled', page === 1);
                        $('#evn-next').prop('disabled', page === totalPages);
                    }

                    if (totalPages > 1) {
                        $('.evn-gf-table').after(
                            '<div class="evn-gf-pagination">' +
                            '<button class="button" id="evn-prev">&#8592;</button>' +
                            '<span class="evn-page-info" id="evn-page-info"></span>' +
                            '<button class="button" id="evn-next">&#8594;</button>' +
                            '</div>'
                        );

                        $('#evn-prev').on('click', function() { if (currentPage > 1) showPage(currentPage - 1); });
                        $('#evn-next').on('click', function() { if (currentPage < totalPages) showPage(currentPage + 1); });

                        showPage(1);
                    }
                });
            </script>

            <table class="tg evn-gf-table">
                <thead>
                <tr>
                    <th>ID</th>
                    <th>Form Name</th>
                    <th>Form Status</th>
                    <th>Honeypot status</th>
                    <th>Notification details</th>
                    <th>Confirmation details</th>
                    <th>Used on pages</th>
                </tr>
                </thead>
                <tbody>
                <?php
                $forms = self::get_gf_forms();
                foreach ($forms as $form) {
                    $cache_key = 'evn_form_pages_' . $form['id'];
                    $cached    = get_transient($cache_key);

                    echo '<tr>';
                    echo '<td>' . esc_html($form['id']) . '</td>';
                    echo '<td>' . esc_html($form['title']) . '</td>';
                    echo '<td>' . esc_html($form['is_active']) . '</td>';
                    echo '<td>' . esc_html($form['display_meta']) . '</td>';
                    echo '<td>' . $form['notifications'] . '</td>';
                    echo '<td>' . $form['confirmations'] . '</td>';

                    echo '<td class="evn-pages-cell">';
                    if ($cached !== false) {
                        echo $cached;
                        echo '<br><small><a href="#" class="evn-pages-reset" data-form-id="' . esc_attr($form['id']) . '">Reset cache</a></small>';
                    } else {
                        echo '<button class="button evn-gf-check-btn" data-form-id="' . esc_attr($form['id']) . '">Check pages</button>';
                    }
                    echo '</td>';
                    echo '</tr>';
                }
                ?>
                </tbody>
            </table>

            <?php
        } else {
            echo '<span style="color: red">GravityForms plugin is not active.</span>';
        }
    }


    /**
     * AJAX handler: finds pages for a given form_id, caches result for 24h
     */
    public function ajax_find_form_pages() {
        check_ajax_referer('evn_find_form_pages');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        $form_id = intval($_POST['form_id'] ?? 0);
        if (!$form_id) {
            wp_send_json_error('Invalid form ID');
        }

        $reset     = !empty($_POST['reset_cache']);
        $cache_key = 'evn_form_pages_' . $form_id;

        if ($reset) {
            delete_transient($cache_key);
            wp_send_json_success(['html' => '']);
        }

        $cached = get_transient($cache_key);
        if ($cached !== false) {
            wp_send_json_success(['html' => $cached]);
        }

        $html = self::get_pages_with_form($form_id);
        set_transient($cache_key, $html, DAY_IN_SECONDS);

        wp_send_json_success(['html' => $html]);
    }


    private static function check_gf_plugin_state()
    {
        if (is_plugin_active('gravityforms/gravityforms.php')){
            return true;
        }

        return false;
    }


    private static function get_gf_forms() {
        //SELECT gf.`id`, gf.`title`, gf.`is_active`, gfmeta.`display_meta`, gfmeta.`confirmations`, gfmeta.`notifications`
        //FROM `wp_gf_form` gf JOIN `wp_gf_form_meta` gfmeta ON gf.`id` = gfmeta.`form_id`;

        global $wpdb;

        $query = $wpdb->prepare( "SELECT gf.id, gf.title, gf.is_active, gfmeta.display_meta, gfmeta.confirmations, gfmeta.notifications 
                                  FROM wp_gf_form gf JOIN wp_gf_form_meta gfmeta ON gf.id = gfmeta.form_id");
        $results = $wpdb->get_results( $query );

        $forms = array();

        foreach ( $results as $result ) {
            $forms[$result->id]['id'] = $result->id;
            $forms[$result->id]['title'] = $result->title;
            $forms[$result->id]['is_active'] = $result->is_active == 1 ? "Active" : "Not active";
            $forms[$result->id]['display_meta'] = self::get_honeypot_status($result->display_meta);
            $forms[$result->id]['notifications'] = self::parse_notification_details($result->notifications);
            $forms[$result->id]['confirmations'] = self::parse_confirmation_details($result->confirmations);
        }

        return $forms;
    }

    private static function get_honeypot_status($display_meta) {
        $display_meta_array = json_decode($display_meta);
        if ($display_meta_array->{'enableHoneypot'} === true) {
            return 'Enabled';
        }

        return 'Disabled';
    }

    private static function parse_confirmation_details($confirmations) {
        $confirmations_array = json_decode($confirmations);
        $info = '';
        foreach ($confirmations_array as $confirmation) {
            $info .= '<strong>Confirmation name:</strong> ' . $confirmation->{'name'} . '<br>';
            $info .= '<strong>Confirmation type:</strong> ' . $confirmation->{'type'};
        }

        return $info;
    }

    private static function parse_notification_details($notifications) {
        $notifications_array = json_decode($notifications);
        $info = '';
        foreach ($notifications_array as $notification) {
            $info .= '<strong>Notification name:</strong> ' . $notification->{'name'} . '<br>';
            $info .= '<strong>From:</strong> ' . esc_html($notification->from ?? 'N/A') . '<br>';

            // toType: email, field, routing
            $toType = $notification->{'toType'};
            switch ($toType) {
                case 'email':
                    $info .= '<strong>To type:</strong> ' . esc_html($toType) . '<br>';
                    $info .= '<strong>To:</strong> ' . esc_html($notification->to ?? 'N/A') . '<br>';
                    break;
                case 'field':
                    $info .= '<strong>To type:</strong> ' . esc_html($toType) . '<br>';
                    $info .= '<strong>To field id:</strong> ' . esc_html($notification->to ?? 'N/A') . '<br>';
                    break;
                case 'routing':
                    $info .= '<strong>To type:</strong> ' . esc_html($toType) . '<br>';
                    $info .= '<strong>Routing:</strong><br>';
                    $routing_array = $notification->routing ?? [];
                    $condition_number = 1;
                    foreach ($routing_array as $routing) {
                        $info .= '<strong>Condition ' . $condition_number . ':</strong>';
                        $info .= ' <i>Send to</i> ' . esc_html($routing->email ?? 'N/A');
                        $info .= ' <i>if field value (field id)</i> ' . esc_html($routing->fieldId ?? 'N/A');
                        $info .= ' <i>' . esc_html($routing->operator) . '</i> ' . esc_html($routing->value ?? 'N/A');
                        $info .= '<br>';
                        $condition_number++;
                    }
                    break;
                default:
                    $info .= '<strong>To type:</strong> Undefined<br>';
            }

            $info .= '<strong>BCC:</strong> ' . ($notification->bcc ? $notification->bcc : 'N/A') . '<br>';

            $info .= '<br>';
        }

        return $info;
    }


    private static function get_pages_with_form(int $form_id): string {
        global $wpdb;
        $found = [];

        $id_str = (string) $form_id;
        $id_len = strlen($id_str);

        // Beaver Builder: "form_id";s:N:"ID"
        // Covers GF Widget and native BB GF module
        $bb_serial_pattern = '%"form_id";s:' . $id_len . ':"' . $id_str . '"%';

        foreach (['_fl_builder_data', '_fl_builder_draft'] as $meta_key) {
            $rows = $wpdb->get_results($wpdb->prepare(
                    "SELECT DISTINCT p.ID, p.post_title, p.post_type
                 FROM {$wpdb->posts} p
                 INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID
                 WHERE p.post_status = 'publish'
                   AND pm.meta_key = %s
                   AND pm.meta_value LIKE %s",
                    $meta_key,
                    $bb_serial_pattern
            ));
            foreach ($rows ?? [] as $post) {
                $found[$post->ID] = $post;
            }
        }

        // PowerPack Gravity Forms module: "select_form_field";s:N:"ID"
        // PowerPack stores form ID under a different key
        $pp_pattern = '%"select_form_field";s:' . $id_len . ':"' . $id_str . '"%';

        foreach (['_fl_builder_data', '_fl_builder_draft'] as $meta_key) {
            $rows = $wpdb->get_results($wpdb->prepare(
                    "SELECT DISTINCT p.ID, p.post_title, p.post_type
                 FROM {$wpdb->posts} p
                 INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID
                 WHERE p.post_status = 'publish'
                   AND pm.meta_key = %s
                   AND pm.meta_value LIKE %s",
                    $meta_key,
                    $pp_pattern
            ));
            foreach ($rows ?? [] as $post) {
                $found[$post->ID] = $post;
            }
        }

        // Gutenberg block: {"formId":"ID"}
        $rows = $wpdb->get_results($wpdb->prepare(
                "SELECT DISTINCT ID, post_title, post_type
             FROM {$wpdb->posts}
             WHERE post_status = 'publish'
               AND post_content LIKE %s",
                '%"formId":"' . $id_str . '"%'
        ));
        foreach ($rows ?? [] as $post) {
            $found[$post->ID] = $post;
        }

        // Classic shortcode in post_content
        foreach (['%[gravityform id="' . $id_str . '"%', '%[gravityforms id="' . $id_str . '"%'] as $sc_pattern) {
            $rows = $wpdb->get_results($wpdb->prepare(
                    "SELECT DISTINCT ID, post_title, post_type
                 FROM {$wpdb->posts}
                 WHERE post_status = 'publish'
                   AND post_content LIKE %s",
                    $sc_pattern
            ));
            foreach ($rows ?? [] as $post) {
                $found[$post->ID] = $post;
            }
        }

        // Build HTML output
        if (empty($found)) {
            return 'Not found';
        }

        $html = '';
        foreach ($found as $post) {
            $url   = get_permalink($post->ID);
            $html .= '<a href="' . esc_url($url) . '" target="_blank">'
                    . esc_html($post->post_title)
                    . ' <small>(' . esc_html($post->post_type) . ')</small>'
                    . '</a><br>';
        }
        return $html;
    }

}