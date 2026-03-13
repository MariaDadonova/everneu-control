<?php

namespace EVN\Admin\LTS;

//use EVN\Helpers\CronInterval;
//use EVN\Helpers\Encryption;
//use EVN\Helpers\Environment;
//use DropboxAPI;
//use MySql;
//use ZipArchive;
use function OpenTelemetry\Instrumentation\hook;

/**
 * Lead Tracking System settings subpage
 *
 */

class LeadTrackingSystemSettings
{

    function __construct() {

        // add LTS submenu item
        add_action( 'admin_menu', [$this, 'lts_page'], 25 );

        // register settings
        add_action( 'admin_init', [$this, 'lts_register_settings'] );

        // handle ajax call
        add_action( 'wp_ajax_lts_get_form_notifications', [$this, 'lts_get_form_notifications'] );

        // enqueue scripts
        add_action('admin_enqueue_scripts', [$this, 'lts_enqueue_admin_scripts'] );
    }


    /**
     * LTS Settings Display
     */
    public function lts_page() {

        $page = add_submenu_page('ec_backups', 'Lead Tracking', "Lead Tracking", 8, 'ec_leadtracking', [$this, 'display_leadtracking_ui'] );

    }

    public function display_leadtracking_ui()
    {
        ?>

        <h1>Lead Tracking System</h1>


        <div class="wrap">

            <div id="lts-message-wrap" class="notice">
                <div id="saving-message"></div>
            </div>

            <?php
            $forms = [];

            // confirm Gravity Forms installed
            if ( class_exists( 'GFFormsModel' ) ) {
                $forms = \GFFormsModel::get_forms();

                if ( empty($forms))  {
                    echo 'No forms found. Please create a Form and a Notification to collect Leads.';
                } else { ?>

                    <h2>Gravity Forms</h2>

                    <?php
                    //get option
                    $supported_forms = get_option('lts_supported_forms', []);
                    $selected_form_notifications = get_option('lts_form_notifications', []);
                    $selected_form_fields = get_option('lts_form_fields', []);

    //                echo "<h2>saved forms</h2>";
    //                print_r( $supported_forms );
    //                echo "<h2>saved notifications</h2>";
    //                print_r( $selected_form_notifications );
    //                echo "<h2>save fields</h2>";
    //                print_r( $selected_form_fields );

                    ?>
                    <form method="post" action="options.php" class="f1">
                        <?php settings_fields('lts_settings_group');
                        //                    do_settings_sections('lts_settings_group');
                        ?>
                        <table class="form-table">
                            <tr valign="top">
                                <th scope="row">Select Supported Forms</th>
                                <td>

                                    <select name="lts_supported_forms[]" multiple>
                                        <?php
                                        $form_names = [];
                                        foreach ($forms as $form):
                                            $the_form = \GFAPI::get_form($form->id);
                                            $form_names[ $form->id ] = $the_form['title'];
                                            $form_notifications[ $form->id ] = $the_form['notifications'];
                                            $form_fields[ $form->id ] = $the_form['fields'];
                                            ?>
                                            <option value="<?php echo esc_attr($form->id); ?>" <?php echo in_array( $form->id, $supported_forms ) ? 'selected' : ''; ?>>
                                                <?php echo esc_html($form->title . ' (ID: ' . esc_html($form->id) . ')' ); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                            </tr>
                            <tr valign="top">
                                <th scope="row">Configure Integration</th>
                                <td id="lts-notifications-wrapper">
                                    <?php

                                    //                                            print_r( $form_fields );

                                    foreach( $selected_form_notifications as $formId => $selected_notification ) : ?>
                                        <div class="form-notification-wrap">
                                            <strong><?php echo $form_names[ $formId ]; ?></strong>
                                            <label for="lts_notification_<?php echo $formId;  ?>">Select Notification:</label>
                                            <select name="lts_form_notifications[<?php echo $formId;  ?>]" id="lts_notification_<?php echo $formId;  ?>">';
                                                <?php foreach( $form_notifications[ $formId ] as $notification ) : ?>
                                                    <option value="<?php echo $notification['id'] ?>" <?php echo ( $notification['id'] == $selected_form_notifications[ $formId ] ) ? 'selected' : ''; ?>>
                                                        <?php echo $notification['name']; ?>
                                                    </option>;
                                                <?php endforeach; ?>
                                            </select>
                                            <?php
                                            ?>
                                            <!-- Dynamically generate notification dropdowns based on selected forms -->
                                            <div class="lead-fields">
                                                <?php
                                                $this->build_form_fields_dropdown( $formId, "FullName", "Full Name", $form_fields[ $formId ], $selected_form_fields[ $formId ]['FullName'] );
                                                $this->build_form_fields_dropdown( $formId, "FirstName", "First Name", $form_fields[ $formId ], $selected_form_fields[ $formId ]['FirstName'] );
                                                $this->build_form_fields_dropdown( $formId, "LastName", "Last Name", $form_fields[ $formId ], $selected_form_fields[ $formId ]['LastName'] );
                                                $this->build_form_fields_dropdown( $formId, "Email", "Email", $form_fields[ $formId ], $selected_form_fields[ $formId ]['Email'] );
                                                $this->build_form_fields_dropdown( $formId, "Phone", "Phone", $form_fields[ $formId ], $selected_form_fields[ $formId ]['Phone'] );
                                                $this->build_form_fields_dropdown( $formId, "Company", "Company", $form_fields[ $formId ], $selected_form_fields[ $formId ]['Company'] );
                                                $this->build_form_fields_dropdown( $formId, "OriginalMessage", "Message", $form_fields[ $formId ], $selected_form_fields[ $formId ]['OriginalMessage'] );
                                                ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </td>
                            </tr>
                        </table>
                    <?php submit_button(); ?>

                    </form>
                    <?php
                } // end if forms exist
            } else {
                echo 'Gravity Forms not installed. Please install and create lead forms to proceed.';
            } // end if GF installed
            ?>

        </div>

        <?php
    }


    /**
     * LTS AJAX support
     */
    public function lts_get_form_notifications() {

        // Check nonce for security
        if ( ! check_ajax_referer('lts_nonce', '_ajax_nonce') ) {

            wp_send_json_error("Request expired.");
            wp_die();

        }

        // get selected form notifications to determine selectedness
        $selected_form_notifications = get_option('lts_form_notifications', []);
        $selected_form_fields = get_option('lts_form_fields', []);
        error_log(print_r('selected notifs ', true));
        error_log(print_r($selected_form_notifications, true));


        // get form ID
        $form_id = intval($_POST['form_id']);
//error_log(print_r('getting notifs for form id ' . $form_id, true));
        if ($form_id) {
            // Retrieve the form object
            $form = \GFAPI::get_form($form_id);

            if ($form && isset($form['notifications'])) {
                // Notifications are stored in the form's 'notifications' key
                $notifications = $form['notifications'];
//error_log(print_r($notifications, true));
                // Prepare notifications for the response
                $response_notifications = array();

                // Prepare notifications for the response
                foreach ($notifications as $notification_id => $notification) {
                    $response_notifications[] = array(
                            'id'   => $notification_id,
                            'name' => $notification['name'],
                            'selected' =>  ( $notification_id == $selected_form_notifications[ $form_id ] )
                    );
                }

                // Prepare fields for the response
                $response_fields = array();
                foreach ($form['fields'] as $field) {
                    if (!empty($field->label)) {
                        $response_fields[] = array(
                                'id'    => $field->id,
                                'label' => $field->label,
                        );
                    }
                }
                // Send a JSON response back to the AJAX call
                wp_send_json_success( array (
                                'notifications' => $response_notifications,
                                'fields'        => $response_fields,
                                'selected_fields' => $selected_form_fields,
                                'form_id' => $form_id,
                                'form_name' => $form['title'] )
                );

            } else {
                wp_send_json_error('No notifications found for this form.');
            }
        } else {
            wp_send_json_error('Invalid form ID.');
        }

        wp_die(); // Required to terminate immediately and return a proper response
    }



    /**
     * LTS Settings
     */
    public function lts_register_settings() {
        register_setting('lts_settings_group', 'lts_supported_forms', array(
                'sanitize_callback' => [$this, 'lts_sanitize_supported_forms']
        ));
        register_setting('lts_settings_group', 'lts_form_notifications', array(
                'sanitize_callback' => [$this, 'lts_sanitize_form_notifications']
        ));
        register_setting('lts_settings_group', 'lts_form_fields', array(
                'sanitize_callback' => [$this, 'lts_sanitize_form_fields']
        ));
    }

    public function lts_sanitize_supported_forms($input) {
//        error_log(print_r("sanitizing forms", true));
//        error_log(print_r($input, true));
        // Ensure the input is an array
        if (!is_array($input)) {
            return array();
        }

        // Sanitize each form ID
        $sanitized_forms = array_map('intval', $input);

        return $sanitized_forms;
    }
    public function lts_sanitize_form_notifications($input) {
//        error_log(print_r("sanitizing form notifications", true));
//        error_log(print_r($input, true));
        // Ensure the input is an array
        if (!is_array($input)) {
            return array();
        }

        $sanitized_notifications = array();

        // Sanitize each form's notification ID
        foreach ($input as $form_id => $notification_id) {
            $sanitized_form_id = intval($form_id);
            $sanitized_notification_id = sanitize_text_field($notification_id);

//            error_log(print_r("san form id: $sanitized_form_id - san notif id: $sanitized_notification_id", true));
            // Add to the sanitized array if both form ID and notification ID are valid
            if ($sanitized_form_id && $sanitized_notification_id) {
                $sanitized_notifications[$sanitized_form_id] = $sanitized_notification_id;
            }
        }
        error_log(print_r($sanitized_notifications, true));

        return $sanitized_notifications;
    }
    public function lts_sanitize_form_fields($input) {
        error_log(print_r("sanitizing form fields", true));
        error_log(print_r($input, true));
        // Ensure the input is an array
        if (!is_array($input)) {
            return array();
        }

        $sanitized_fields = [];

        // Sanitize each form's notification ID
        foreach ($input as $form_id => $field_values) {
            $sanitized_form_id = intval( $form_id );
            $sanitized_field_values = [];
            $sanitized_field_values['FullName'] = sanitize_text_field( $field_values['FullName'] );
            $sanitized_field_values['FirstName'] = sanitize_text_field( $field_values['FirstName'] );
            $sanitized_field_values['LastName'] = sanitize_text_field( $field_values['LastName'] );
            $sanitized_field_values['Email'] = sanitize_text_field( $field_values['Email'] );
            $sanitized_field_values['Phone'] = sanitize_text_field( $field_values['Phone'] );
            $sanitized_field_values['Company'] = sanitize_text_field( $field_values['Company'] );
            $sanitized_field_values['OriginalMessage'] = sanitize_text_field( $field_values['OriginalMessage'] );

//            error_log(print_r("san form id: $sanitized_form_id - san notif id: $sanitized_notification_id", true));
            // Add to the sanitized array if both form ID and notification ID are valid
            if ( $sanitized_form_id ) {
                $sanitized_fields[$sanitized_form_id] = $sanitized_field_values;
            }
        }
        error_log(print_r($sanitized_fields, true));

        return $sanitized_fields;
    }


    /**
     * LTS Assets
     */
    public function lts_enqueue_admin_scripts( $hook_suffix )
    {

        // only load on LTS page
        if ($hook_suffix === 'everneu-control_page_ec_leadtracking') {

            wp_enqueue_style( 'lts-style', EVN_URL . 'assets/css/lts.css' );

            wp_enqueue_script('lts-script', EVN_URL . 'assets/js/lts.js', array('jquery'));

            // Localize the script with the nonce and ajaxurl
            wp_localize_script('lts-script', 'lts_ajax_object', array(
                    'nonce' => wp_create_nonce('lts_nonce')
            ));
        }
    }

}