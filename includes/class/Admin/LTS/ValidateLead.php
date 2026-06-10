<?php
namespace EVN\Admin\LTS;

/**
 * Validate Lead
 * Handles validating and rejecting leads, and Client Ratings.
 */
class ValidateLead {


	/**
	 * Initialize ValidateLead
	 *
	 * @since    1.0.0
	 */
	public function __construct() {

        add_action( 'admin_post_validate_lead', [$this, 'handle_validate_lead'] );
        add_action( 'admin_post_nopriv_validate_lead', [$this, 'handle_validate_lead'] );

        add_action( 'admin_post_confirm_validate_lead', [$this, 'handle_confirm_validate_lead'] );
        add_action( 'admin_post_nopriv_confirm_validate_lead', [$this, 'handle_confirm_validate_lead_nopriv'] );

        // client evaluation
        add_action( 'admin_post_nopriv_eval_lead', [$this, 'handle_eval_lead'] );
	}


    public function handle_validate_lead() {

        try {

            $this->printLTSHeader();

            // get input
            $valid = sanitize_text_field( $_REQUEST['valid'] ?? '' );
            $id    = intval( $_REQUEST['id'] ?? 0 );
            if( ! $id ) {
                echo "<div> > Invalid Lead ID.</div>";
                die();
            }
            $token         = sanitize_text_field( $_GET['nonce'] ?? '' );
            $stored_token  = gform_get_meta( $id, 'lts_token' );
            if ( empty( $token ) || ! hash_equals( $stored_token, $token ) ) {
                echo "<div> > Invalid or expired link.</div>";
                die();
            }


            // Consume the token — one-time use
//        gform_delete_meta( $id, 'spam_review_token' );


            // confirm button
            $btn_txt = "";
            $confirmation_msg = "";
            $button_styles = "display:inline-block;padding:10px 20px;border:none;border-radius:4px;cursor:pointer;font-size:15px;font-weight:500;text-decoration:none;margin-right:8px;";
            if( $valid ) {
                $confirm_url = admin_url( 'admin-post.php' );
                $confirmation_msg = "<div> > Click Confirm below to VALIDATE this Lead.</div>";
                $btn_txt = "Confirm Validate Lead";
                $button_styles .= "background:#00a32a;color:#fff;";
            } else {
                $confirm_url = admin_url( 'admin-post.php' );
                $confirmation_msg = "<div> > Click Confirm below to REJECT this Lead.</div>";
                $btn_txt = "Confirm Reject Lead";
                $button_styles .= "background:#f0f0f0;color:#333;border:solid 1px #999";
            }
            echo $confirmation_msg;


            // form details
            $entry = \GFAPI::get_entry( $id );
            $form_id = rgar( $entry, 'form_id' );
            $form = \GFAPI::get_form( $form_id );
            echo $this->get_entry_fields_html( $form, $entry );


            // confirmation form
            echo "<form method='POST' action='{$confirm_url}'>";
            echo "<input type='hidden' name='action' value='confirm_validate_lead' />";
            echo "<input type='hidden' name='id' value='{$id}' />";
            echo "<input type='hidden' name='valid' value='{$valid}' />";
//            echo "<input type='hidden' name='token' value='{$token}' />";
            wp_nonce_field( 'gf_review_lead_' . $id );
            echo "<button type='submit' style='{$button_styles}'>{$btn_txt}</button>";
            echo "</form>";



        } catch (\Exception $exception) {
            echo $exception->getMessage();
            die();
        }

    }
//    public function handle_validate_lead_nopriv() {
//
//        try {
//
//            $this->printLTSHeader();
//
//            // get input
//            $valid = '';
//            $id = 0;
//            $valid = sanitize_text_field( $_REQUEST['valid'] ?? '' );
//            $id    = intval( $_REQUEST['id'] ?? 0 );
//            if( ! $id ) {
//                echo "<div> > Invalid Lead ID.</div>";
//                die();
//            }
//            $token         = sanitize_text_field( $_GET['nonce'] ?? '' );
//            $stored_token  = gform_get_meta( $id, 'lts_token' );
//            if ( empty( $token ) || ! hash_equals( $stored_token, $token ) ) {
//                echo "<div> > Invalid or expired link.</div>";
//                die();
//            }
//
//
//            // Consume the token — one-time use
////        gform_delete_meta( $id, 'spam_review_token' );
//
//
//            // confirm button
//            $btn_txt = "";
//            $confirmation_msg = "";
//            $button_styles = "display:inline-block;padding:10px 20px;border:none;border-radius:4px;cursor:pointer;font-size:15px;font-weight:500;text-decoration:none;margin-right:8px;";
//            $confirm_url = admin_url( 'admin-post.php' );
//            if( $valid ) {
//                $confirmation_msg = "<div> > Click Confirm below to VALIDATE this Lead.</div>";
//                $btn_txt = "Confirm Validate Lead";
//                $button_styles .= "background:#00a32a;color:#fff;";
//            } else {
//                $confirmation_msg = "<div> > Click Confirm below to REJECT this Lead.</div>";
//                $btn_txt = "Confirm Reject Lead";
//                $button_styles .= "background:#f0f0f0;color:#333;border:solid 1px #999";
//            }
//            echo $confirmation_msg;
//
//
//            // form details
//            $entry = \GFAPI::get_entry( $id );
//            $form_id = rgar( $entry, 'form_id' );
//            $form = \GFAPI::get_form( $form_id );
//            echo $this->get_entry_fields_html( $form, $entry );
//
//
//            // Option A — wp_nonce_field() echoes the hidden input directly
//            echo "<form method='POST' action='{$confirm_url}'>";
//            echo "<input type='hidden' name='action' value='confirm_validate_lead' />";
//            echo "<input type='hidden' name='id' value='{$id}' />";
//            echo "<input type='hidden' name='valid' value='{$valid}' />";
////            echo "<input type='hidden' name='token' value='{$token}' />";
//            wp_nonce_field( 'gf_review_lead_' . $id );
//            echo "<button type='submit' style='{$button_styles}'>{$btn_txt}</button>";
//            echo "</form>";
//            die();
//
//
//        } catch (Exception $exception) {
//            echo $exception->getMessage();
//            die();
//        }
//
//    }


	public function handle_confirm_validate_lead() {

        try {

            // get input
            $valid = sanitize_text_field( $_REQUEST['valid'] ?? '' );
            $id    = intval( $_REQUEST['id'] ?? 0 );
            if( ! $id ) {
                $this->printLTSHeader(); ?>
                <div> > Invalid Lead ID.</div>
                <?php
                die();
            }

            // confirm nonce
            if ( ! check_admin_referer( 'gf_review_lead_' . $id ) ) {
                $this->printLTSHeader(); ?>
                <div> > Invalid or expired link.</div>
                <?php
                die();
            }


            // Consume the token — one-time use
    //        gform_delete_meta( $id, 'spam_review_token' );



            // get user
            $current_user = wp_get_current_user();
            gform_update_meta( $id, 'lts_reviewed_by', $current_user->display_name );


            // record note
            $confirmation_msg = "";
            if( $valid ) {
                $confirmation_msg = 'Lead APPROVED by ' . $current_user->display_name . '.';
            } else {
                $confirmation_msg = 'Lead REJECTED by ' . $current_user->display_name . '. No further action taken.';
            }
            \GFFormsModel::add_note(
                $id,
                $current_user->ID,              // user ID — 0 for system/programmatic
                $current_user->display_name,    // user name label
                $confirmation_msg,
                'lead-tracking'                 // note type
            );


            // validate / reject lead
            $this->validate_lead( $id, $valid );


            //redirect to Entry backend page
            $entry = \GFAPI::get_entry( $id );
            $form_id = rgar( $entry, 'form_id' );
            $entry_url = admin_url( "admin.php?page=gf_entries&view=entry&id={$form_id}&lid={$id}" );
            wp_redirect( $entry_url );
            die();

        } catch (Exception $exception) {
            echo $exception->getMessage();
            die();
        }

    }

	public function handle_confirm_validate_lead_nopriv() {

        $this->printLTSHeader();

        // get input
        $valid = sanitize_text_field( $_REQUEST['valid'] ?? '' );
        $id    = intval( $_REQUEST['id'] ?? 0 );
        if( ! $id ) { ?>
            <div> > Invalid Lead ID.</div>
            <?php
            die();
        }

        // confirm nonce
        if ( ! check_admin_referer( 'gf_review_lead_' . $id ) ) {
            $this->printLTSHeader(); ?>
            <div> > Invalid or expired link.</div>
            <?php
            die();
        }

        // record note
        $confirmation_msg = "";
        if( $valid ) {
            $confirmation_msg = 'Lead APPROVED via secure, anonymous link.';
        } else {
            $confirmation_msg = 'Lead REJECTED via secure, anonymous link. No further action taken.';
        }
        \GFFormsModel::add_note(
            $id,
            0,                          // user ID — 0 for system/programmatic
            'Lead Tracking System',     // user name label
            $confirmation_msg,
            'lead-tracking'             // note type
        );

        // prepare output ?>
        <div> > <?php echo $confirmation_msg; ?></div>
        <?php

        // validate / reject lead
        $this->validate_lead( $id, $valid );

        // set anonymous user
        gform_update_meta( $id, 'lts_reviewed_by', 'Anonymous Admin' );

        // close simple acknowledgement text
        ?>
        </div>
        <?php

        die();
	}

    private function validate_lead( $id, $valid ) {

        //validate input
        // id must exist
        $entry = \GFAPI::get_entry( $id );
        if ( is_wp_error( $entry ) ) {
            error_log(print_r('LTS Error: attempt to update status of GF Entry ' . $id . ' does not exist', true));
        }


//error_log(print_r( 'checking valid for id ' . $id, true));
//        error_log(print_r( $valid ? "lead $id is valid" : "lead $id is NOT valid", true));

        //update status
        if( $valid ) {
            gform_update_meta( $id, 'lts_status', 'Validated' );

            // send email
            $this->send_client_notification( $id );

            // check initial spam status, notify Akismet if previously marked as spam
            $initial_spam = gform_get_meta( $id, 'lts_initial_spam' );
            if ( $initial_spam ) {
                // notify Akismet missed spam
                $form_id = rgar( $entry, 'form_id' );
                $form = \GFAPI::get_form( $form_id );
                NotifyAkismet::report_ham( $entry, $form );
            }

        } else {
            gform_update_meta( $id, 'lts_status', 'Rejected' );

            // a rejection means a legit lead that is not a match, right?
            // so we should actually inform Akismet not spam... right??
            $initial_spam = gform_get_meta( $id, 'lts_initial_spam' );
            if ( $initial_spam ) {
                // notify Akismet missed spam
                $form_id = rgar( $entry, 'form_id' );
                $form = \GFAPI::get_form( $form_id );
                NotifyAkismet::report_ham( $entry, $form );
            }
        }
        gform_update_meta( $id, 'lts_reviewed_at', time() );
    }

	private function send_client_notification( $entry_id ) {

        // get client emails
        $entry = \GFAPI::get_entry( $entry_id );
        $form_id = rgar( $entry, 'form_id' );
        $form = \GFAPI::get_form( $form_id );
        $form_title = rgar( $form, 'title' );
        $client_emails = get_option('lts_client_emails', []);
        $to = $client_emails[ $form_id ];
        $message = $this->get_client_message_html( $form, $entry );
        $subject = "New submission from {$form_title}";

        add_filter( 'wp_mail_content_type', fn() => 'text/html' );
        wp_mail( $to, $subject, $message );
        remove_filter( 'wp_mail_content_type', fn() => 'text/html' );


        \GFFormsModel::add_note(
            $entry_id,
            0,                          // user ID — 0 for system/programmatic
            'Lead Tracking System',     // user name label
            'Sent Lead to client at ' . $to . '.',
            'lead-tracking'             // note type
        );

        // Display unauth user simple acknowledgement
        ?>
            <div> > <?php echo 'Sent Lead to client at ' . $to . '.'?></div>
        <?php

    }

    private function get_entry_fields_html( $form, $entry ) {

        $rows = "";
        foreach ( $form['fields'] as $field ) {
            // Skip fields that shouldn't appear in notifications
            if ( $field->displayOnly || $field->type === 'html' || $field->type === 'page' || $field->type === 'section' ) {
                continue;
            }

            $value = $field->get_value_export( $entry );
            $label = esc_html( $field->label );
            $display = \GFCommon::get_lead_field_display( $field, $value, $entry );

            $rows .= '<tr bgcolor="#EAF2FA">
    <td colspan="2" align="left">
        <font style="font-family:sans-serif;font-size:12px"><strong>' . $label . '</strong></font>
    </td>
</tr>
<tr bgcolor="#FFFFFF">
    <td width="20">&nbsp;</td>
    <td align="left">
        <font style="font-family:sans-serif;font-size:12px">' . $display . '</font>
    </td>
</tr>';
        }

        // build table
        $html  = '<div style="max-width: 800px; margin: 20px 0;"><table width="99%" border="0" cellpadding="1" cellspacing="0" bgcolor="#CCCCCC"><tr><td>';
        $html .= '<table width="100%" border="0" cellpadding="5" cellspacing="0" bgcolor="#FFFFFF">';
        $html .= '<tbody>' . $rows . '</tbody>';
        $html .= '</table></td></tr></table></div>';

        return $html;
    }
    private function get_client_message_html( $form, $entry ) {

        // create action buttons
        $token  = gform_get_meta( $entry['id'], 'lts_token' );
        $rate1_url = add_query_arg([
                'action'   => 'eval_lead',
                'rating'   => '1',
                'id'       => $entry['id'],
                'nonce'    => $token,
        ], admin_url( 'admin-post.php' ) );
        $rate2_url = add_query_arg([
                'action'   => 'eval_lead',
                'rating'   => '2',
                'id'       => $entry['id'],
                'nonce'    => $token,
        ], admin_url( 'admin-post.php' ) );
        $rate3_url = add_query_arg([
                'action'   => 'eval_lead',
                'rating'   => '3',
                'id'       => $entry['id'],
                'nonce'    => $token,
        ], admin_url( 'admin-post.php' ) );
        $rate4_url = add_query_arg([
                'action'   => 'eval_lead',
                'rating'   => '4',
                'id'       => $entry['id'],
                'nonce'    => $token,
        ], admin_url( 'admin-post.php' ) );
        $rate5_url = add_query_arg([
                'action'   => 'eval_lead',
                'rating'   => '5',
                'id'       => $entry['id'],
                'nonce'    => $token,
        ], admin_url( 'admin-post.php' ) );


        $stars = [
                1 => [ 'label' => '★ 1', 'bg' => '#c0392b', 'border' => '#a93226' ],
                2 => [ 'label' => '★★ 2', 'bg' => '#e67e22', 'border' => '#ca6f1e' ],
                3 => [ 'label' => '★★★ 3', 'bg' => '#f1c40f', 'border' => '#d4ac0d', 'color' => '#333' ],
                4 => [ 'label' => '★★★★ 4', 'bg' => '#27ae60', 'border' => '#1e8449' ],
                5 => [ 'label' => '★★★★★ 5', 'bg' => '#2980b9', 'border' => '#2471a3' ],
        ];

        $html = '<div style="text-align: center; margin-bottom: 40px; max-width: 800px;">';
        $html .= '    <p style="margin-bottom: 20px">Please rate this Lead.</p>';
        foreach ( $stars as $rating => $star ) {
            $color  = $star['color'] ?? '#fff';
            $url    = esc_url( add_query_arg([
                    'action'   => 'eval_lead',
                    'rating'   => $rating,
                    'id'       => $entry['id'],
                    'nonce'    => $token,
            ], admin_url( 'admin-post.php' ) ) );
            $html .=  "<a style=\"display:inline-block;color:{$color};background-color:{$star['bg']};border:1px solid {$star['border']};font-weight:400;text-align:center;vertical-align:middle;text-decoration:none;font-family:Arial,sans-serif;padding:10px 16px;border-radius:5px;margin-right:6px;\" href=\"{$url}\">{$star['label']}</a>";
        }
        $html .=  "</div>";


        $rows = "";
        foreach ( $form['fields'] as $field ) {
            // Skip fields that shouldn't appear in notifications
            if ( $field->displayOnly || $field->type === 'html' || $field->type === 'page' || $field->type === 'section' ) {
                continue;
            }

            $value = $field->get_value_export( $entry );
            $label = esc_html( $field->label );
            $display = \GFCommon::get_lead_field_display( $field, $value, $entry );

            $rows .= '<tr bgcolor="#EAF2FA">
        <td colspan="2" align="left">
            <font style="font-family:sans-serif;font-size:12px"><strong>' . $label . '</strong></font>
        </td>
    </tr>
    <tr bgcolor="#FFFFFF">
        <td width="20">&nbsp;</td>
        <td align="left">
            <font style="font-family:sans-serif;font-size:12px">' . $display . '</font>
        </td>
    </tr>';
        }

        // build table
        $html .= '<div style="max-width: 800px; margin: 20px 0;"><table width="99%" border="0" cellpadding="1" cellspacing="0" bgcolor="#CCCCCC"><tr><td>';
        $html .= '<table width="100%" border="0" cellpadding="5" cellspacing="0" bgcolor="#FFFFFF">';
        $html .= '<tbody>' . $rows . '</tbody>';
        $html .= '</table></td></tr></table></div>';

        return $html;
    }


    public function handle_eval_lead() {

        try {

//            $this->printLTSHeader(); // don't show Lead Tracking header to client

            // get input
            $rating    = intval( $_REQUEST['rating'] ?? 0 );
            $id    = intval( $_REQUEST['id'] ?? 0 );
            if( ! $id ) {
                echo "<div> > Invalid link. Please contact an Everneu administrator.</div>";
                die();
            }
            $token         = sanitize_text_field( $_REQUEST['nonce'] ?? '' );
            $stored_token  = gform_get_meta( $id, 'lts_token' );
            if ( empty( $token ) || ! hash_equals( $stored_token, $token ) ) {
                echo "<div> > Invalid or expired link. Please contact an Everneu administrator.</div>";
                die();
            }


            // Consume the token — one-time use.... this would need a different token as well
//        gform_delete_meta( $id, 'spam_review_token' );

            gform_update_meta( $id, 'lts_client_rating', $rating );
            gform_update_meta( $id, 'lts_client_rated_at', time() );


            // add note
            \GFFormsModel::add_note(
                    $id,
                    0,              // user ID — 0 for system/programmatic
                    'Client',    // user name label
                    "Client rated Lead {$rating} out of 5.",
                    'lead-tracking'                 // note type
            );

            // notify admin
            $this->send_admin_client_rating_notification( $id, $rating );

            // confirmation msg
            echo "<br /><div style='font-family: ui-monospace, Menlo, Consolas, \"Liberation Mono\", monospace;font-size: 22px'> > Rating received. Thank you!</div>";


        } catch (\Exception $exception) {
            echo $exception->getMessage();
            die();
        }

    }


    private function send_admin_client_rating_notification( $entry_id, $rating ) {

        // get emails from LTS Active GF Notification
        $entry = \GFAPI::get_entry( $entry_id );
        $form_id = rgar( $entry, 'form_id' );
        $form = \GFAPI::get_form( $form_id );
        $selected_form_notifications = get_option('lts_form_notifications', []);
        $notification_id = $selected_form_notifications[ $form_id ];
        $notification = $form['notifications'][ $notification_id ];
        $from = \GFCommon::replace_variables( rgar( $notification, 'from' ), $form, $entry );
        $to  = $this->filter_to_everneu_emails( \GFCommon::replace_variables( rgar( $notification, 'to' ),  $form, $entry ) );
        $cc  = $this->filter_to_everneu_emails( \GFCommon::replace_variables( rgar( $notification, 'cc' ),  $form, $entry ) );
        $bcc = $this->filter_to_everneu_emails( \GFCommon::replace_variables( rgar( $notification, 'bcc' ), $form, $entry ) );
        $admin_emails = array_filter(
                array_merge(
                        array_map( 'trim', explode( ',', $to ) ),
                        array_map( 'trim', explode( ',', $cc ) ),
                        array_map( 'trim', explode( ',', $bcc ) )
                )
        );

        // build message
        $form_title      = rgar( $form, 'title' );
        $entry_url       = admin_url( "admin.php?page=gf_entries&view=entry&id={$form['id']}&lid={$entry['id']}" );
        $fields_html     = $this->get_entry_fields_html( $form, $entry );

        $subject = "New Client Rating from {$form_title}";

        $message = '';
        $message .= '<div style="text-align: center; margin:5px auto 40px; max-width: 800px;">';
        $message .= '    <h2>Lead Tracking</h2>';
        $message .= '    <p style="margin-bottom: 0">The client has rated this Lead.</p>';
        $message .= '    <p style="margin: 20px; font-size: 32px">' . $rating . ' <span style="font-size: 16px;width: 20px;display: inline-block;margin-right: -20px;">/ 5</span></p>';
        $message .= $fields_html;
        $message .= "<p style='margin: 20px;'><a href='{$entry_url}'>View Lead (Gravity Form Entry) - Login Required</a></p>";
        $message .= '</div>';

        add_filter( 'wp_mail_content_type', fn() => 'text/html' );
        add_filter( 'wp_mail_from', fn() => $from );
        wp_mail( $admin_emails, $subject, $message );
        remove_filter( 'wp_mail_content_type', fn() => 'text/html' );
        remove_filter( 'wp_mail_from', fn() => $from );

    }
    private function filter_to_everneu_emails( $email_string ) {
        if ( empty( $email_string ) ) {
            return '';
        }

        $emails   = array_map( 'trim', explode( ',', $email_string ) );
        $filtered = array_filter( $emails, fn( $email ) => str_ends_with( strtolower( $email ), '@everneu.com' ) );

        return implode( ', ', $filtered );
    }

    private function printLTSHeader()
    {
        ?>
        <div style='font-family: ui-monospace, Menlo, Consolas, "Liberation Mono", monospace;font-size: 22px;'>
        <h1>Lead Tracking System</h1>
        <?php
    }


}