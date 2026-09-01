<?php

namespace EVN\Admin\Settings\FormsReport;

class GravityFormsReport
{
    function __construct() {
    }

    public function gravity_forms_report_ui() {
        ?>

        <h3 style="margin-top: 40px; margin-bottom: 30px;">Forms report</h3>

        <?php
        if ($this->check_gf_plugin_state() === true) {
            ?>
            <span style="color: green">GravityForms plugin is active.</span>
            <table class="tg" style="margin-bottom: 40px;">
                <thead>
                <tr>
                    <th style="text-align: left;">ID</th>
                    <th style="text-align: left;">Form Name</th>
                    <th style="text-align: left;">Form Status</th>
                    <th style="text-align: left;">Honeypot status</th>
                    <th style="text-align: left;">Notification details</th>
                    <th style="text-align: left;">Confirmation details</th>
                    <th style="text-align: left;">Used on the pages</th>
                </tr>
                </thead>
                <tbody>
                <?php
                    $forms = self::get_gf_forms();
                    foreach ($forms as $form) {
                        echo '<tr>';
                        foreach ($form as $field) {
                            echo '<td style="text-align: left;">' . $field . '</td>';
                        }
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

    private function check_gf_plugin_state()
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
            $forms[$result->id]['on_page'] = self::get_pages_with_form($result->id);
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


    private static function get_pages_with_form(int $form_id) {
        global $wpdb;
        $found = [];

        $form_id_str = (string) $form_id;
        $form_id_len = strlen($form_id_str);

        // Beaver Builder: "form_id";s:N:"ID" in _fl_builder_data and _fl_builder_draft
        $bb_pattern = '%"form_id";s:' . $form_id_len . ':"' . $form_id_str . '"%';

        foreach (['_fl_builder_data', '_fl_builder_draft'] as $meta_key) {
            $results = $wpdb->get_results(
                    $wpdb->prepare(
                            "SELECT DISTINCT p.ID, p.post_title, p.post_type
                             FROM {$wpdb->posts} p
                             INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID
                             WHERE p.post_status = 'publish'
                             AND pm.meta_key = %s
                             AND pm.meta_value LIKE %s",
                             $meta_key,
                             $bb_pattern
                    )
            );
            foreach ($results ?? [] as $post) {
                $found[$post->ID] = $post;
            }
        }

        // Gutenberg block: {"formId":"2"}
        $gutenberg_pattern = '%"formId":"' . $form_id_str . '"%';
        $results = $wpdb->get_results(
                $wpdb->prepare(
                        "SELECT DISTINCT ID, post_title, post_type
                         FROM {$wpdb->posts}
                         WHERE post_status = 'publish'
                         AND post_content LIKE %s",
                         $gutenberg_pattern
                )
        );
        foreach ($results ?? [] as $post) {
            $found[$post->ID] = $post;
        }

        // Add permalink to each result
        foreach ($found as $post_id => $post) {
            $found[$post_id]->url = get_permalink($post->ID);
        }

        $pages = '';
        if (!empty($found)) {
            foreach ($found as $post) {
                $pages .= '<a href="' . esc_url($post->url) . '" target="_blank">'
                        . esc_html($post->post_title) . ' (' . esc_html($post->post_type) . ')'
                        . '</a><br>';
            }
        } else {
            $pages .= 'Not found';
        }


        return $pages;
    }

}