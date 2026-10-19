<?php

namespace EVN\Admin\Settings\SimpleHistory;

/**
 * Change maximum time range history keeping in Simple History to custom
 * By default: 365 days
 *
 * @version 1.0
 */

class SimpleHistoryExtendTimeRange
{
    function __construct() {
        add_action('simple_history/db_purge_days_interval', [$this, 'filter_purge_days']);
    }

    public function display_simple_history_ui() {
        $defaults = array( 'override' => 'on', 'days' => 365 );
        ?>
        <h3 style="margin-top: 40px; margin-bottom: 30px;">Simple History Log retention</h3>
       <?php
        // Save settings when the form is submitted
        if ( isset( $_POST['ec_simple_history_save_custom_range_submit'] ) && current_user_can( 'manage_options' ) ) {
            check_admin_referer( 'ec_simple_history_save', 'ec_simple_history_nonce' );

            $override = ( isset( $_POST['simple_history_override'] ) && $_POST['simple_history_override'] === 'on' ) ? 'on' : 'off';
            $days     = isset( $_POST['simple_history_days'] ) ? absint( $_POST['simple_history_days'] ) : 0;
            if ( $days < 1 ) {
                $days = $defaults['days'];
            }

            update_option( 'ec_simple_history_retention', array( 'override' => $override, 'days' => $days ) );
            echo '<div class="notice notice-success"><p>Simple History settings saved.</p></div>';
        }

        $settings = wp_parse_args( get_option( 'ec_simple_history_retention', array() ), $defaults );

        echo '<form method="post" action="">';
        wp_nonce_field( 'ec_simple_history_save', 'ec_simple_history_nonce' );

        echo '<div class="ev-form-row">';
        echo '<input type="hidden" name="simple_history_override" value="off">';
        echo '<input type="checkbox" id="simple_history_override" name="simple_history_override" value="on" ' . checked( $settings['override'], 'on', false ) . '>';
        echo '<label id="lb" for="simple_history_override"> Override Simple History log retention</label>';
        echo '</div>';

        echo '<div class="ev-form">';
        echo '<br><br><label id="days" for="simple_history_days"> Keep history for (days)</label>';
        echo '<input type="number" id="simple_history_days" name="simple_history_days" min="1" value="' . esc_attr( $settings['days'] ) . '">';
        echo '<p>Default: 365. Events older than this are deleted automatically. When unchecked, Simple History uses its own default.</p>';
        echo '<br><br><input type="submit" name="ec_simple_history_save_custom_range_submit" class="button button-primary" value="Save">';
        echo '</div>';
        echo '</form>';
    }


    public function filter_purge_days( $days ) {
        $settings = wp_parse_args(
                get_option( 'ec_simple_history_retention', array() ),
                array( 'override' => 'on', 'days' => 365 )
        );

        if ( $settings['override'] !== 'on' ) {
            return $days;
        }

        return max( 1, (int) $settings['days'] );
    }


}