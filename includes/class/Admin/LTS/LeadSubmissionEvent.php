<?php

namespace EVN\Admin\LTS;

use GFFormDisplay;

class LeadSubmissionEvent {

    private $admin_notice_msg = '';

    public function __construct() {

        // get selected forms
//        $supported_forms = get_option('lts_supported_forms', []);

//        error_log(print_r("LeadSubmissionEvent construct", true));
//        error_log(print_r($supported_forms, true));
        
        // hook into form submission
//        foreach( $supported_forms as $form_id ) {
//
//            add_action( 'gform_after_submission_' . $form_id, [$this, 'after_lead_submission'], 10, 2 );
//
//        }

        // filter notification email to include Approve/Reject buttons


        add_filter('gform_entry_post_save', [$this, 'lts_handle_akismet_spam'], 10, 2 );
        add_filter('gform_pre_send_email', [$this, 'lts_prepend_html_buttons_to_notification'], 10, 4 );


    }




    public function lts_handle_akismet_spam( $entry, $form ) {

        if ( rgar( $entry, 'status' ) !== 'spam' ) {
            return $entry;
        }

        $supported_forms = get_option('lts_supported_forms', []);
        $selected_form_notifications = get_option('lts_form_notifications', []);


        // confirm active LTS form
        $form_id = $entry['form_id'];
        if( ! in_array( $form_id, $supported_forms ) ) {
            return $entry;
        }
        $notification_id = $selected_form_notifications[ $form_id ];


        // check for honeypot marked spam
        // if honeypot, highly certain spam, do not override
        $notes = \GFFormsModel::get_lead_notes( rgar( $entry, 'id' ) );
        $honeypot_flagged = false;
        foreach ( $notes as $note ) {
            if ( strpos( strtolower( $note->value ), 'honeypot' ) !== false ) {
                $honeypot_flagged = true;
                break;
            }
        }
        if ( $honeypot_flagged ) {
            return $entry;
        }

        // if not honeypot, then Akismet marked as spam
        // Akismet has marked legit leads as spam, we don't trust it
        // set as not spam, record note, send for manual review
        \GFAPI::update_entry_property( rgar( $entry, 'id' ), 'status', 'active' );
        $entry['status'] = 'active';
        \GFFormsModel::add_note(
                rgar( $entry, 'id' ),
                0,                          // user ID — 0 for system/programmatic
                'System',                   // user name label
                'Entry was moved out of spam by the Lead Tracking System due to Akismet only flagging.',
                'spam'                      // note type
        );

        // manually send notification
        $entry_id        = rgar( $entry, 'id' );
        $form_title      = rgar( $form, 'title' );
        $entry_url       = admin_url( "admin.php?page=gf_entries&view=entry&id={$form['id']}&lid={$entry_id}" );
        $fields_html     = $this->get_entry_fields_html( $form, $entry );

        // use To, CC and BCC from form notifications, filtering for only @everneu.com addresses
        $form         = \GFAPI::get_form( $form_id );
        $notification = $form['notifications'][ $notification_id ];
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

        // prepare actions
        $approve_url = add_query_arg([
                'action'   => 'validate_lead',
                'valid'    => '1',
                'entry_id' => $entry_id,
                'nonce'    => wp_create_nonce( 'gf_validate_lead_' . $entry_id ),
        ], admin_url( 'admin-post.php' ) );

        $reject_url = add_query_arg([
                'action'   => 'reject_lead',
                'valid'    => '0',
                'entry_id' => $entry_id,
                'nonce'    => wp_create_nonce( 'gf_reject_lead_' . $entry_id ),
        ], admin_url( 'admin-post.php' ) );

        $confirm_spam_url = add_query_arg([
                'action'   => 'spam_lead',
                'entry_id' => $entry_id,
                'nonce'    => wp_create_nonce( 'gf_spam_review_' . $entry_id ),
        ], admin_url( 'admin-post.php' ) );

        $subject = "New submission from {$form_title} - Possible Spam";

        $message = "";
        $message .= '<div style="text-align: center; margin:5px auto 40px; max-width: 800px;">';
        $message .= '    <h2>Lead Tracking</h2>';
        $message .= '    <p style="margin: 10px; text-align: center; font-style: italic">POSSIBLE SPAM</p>';
        $message .= '    <p style="margin-bottom: 10px">Akismet marked this lead as spam. We don\'t fully trust Akismet, please review and confirm.</p>';
        $message .= '    <p style="display: inline-block;margin:15px 0 25px;"><a style="display: inline-block;color: #fff;background-color: #DD6B20;border-color: #DD6B20;font-weight: 400;text-align: center;vertical-align: middle;border: 1px solid transparent;text-decoration: none;font-family: Arial, sans-serif;padding:15px 30px;border-radius: 5px"';
        $message .= '       href="' . $confirm_spam_url . '">CONFIRM SPAM</a></p>';
        $message .= '    <p>If the Lead is legitimate, please Validate or Reject.</p>';
        $message .= '    <p><a style="display: inline-block;color: #fff;background-color: #28a745;border-color: #28a745;font-weight: 400;text-align: center;vertical-align: middle;border: 1px solid transparent;text-decoration: none;font-family: Arial, sans-serif;padding:10px 20px;border-radius: 5px"';
        $message .= '       href="' . $approve_url . '">Validate</a>';
        $message .= '    <a style="display: inline-block;color: #fff;background-color: #bd2130;border-color: #b21f2d;font-weight: 400;text-align: center;vertical-align: middle;border: 1px solid transparent;text-decoration: none;font-family: Arial, sans-serif;padding:10px 20px;border-radius: 5px"';
        $message .= '       href="' . $reject_url . '">Reject</a></p>';
        $message .= '';
        $message .= "";
//        $message .= "<a href='{$approve_url}' style='padding:8px 16px;background:#00a32a;color:#fff;text-decoration:none;border-radius:4px;margin-right:8px;'>Not Spam — Approve</a>";
//        $message .= "<a href='{$confirm_spam_url}' style='padding:8px 16px;background:#d63638;color:#fff;text-decoration:none;border-radius:4px;margin-right:8px;'>Confirm Spam</a>";
        $message .= $fields_html;
        $message .= "<p style='margin: 20px;'><a href='{$entry_url}'>View Lead (Gravity Form Entry) - Login Required</a></p>";
        $message .= '</div>';

        add_filter( 'wp_mail_content_type', fn() => 'text/html' );
        wp_mail( $admin_emails, $subject, $message );
        remove_filter( 'wp_mail_content_type', fn() => 'text/html' );

        return $entry;
    }

    public function lts_prepend_html_buttons_to_notification($email, $message_format, $notification, $entry) {

        error_log(print_r('pre send email here', true));
        try {
            error_log(print_r('the notification, entry in prepend is ', true));
            error_log(print_r($notification, true));
//            error_log(print_r($entry, true));

            //get option
            $supported_forms = get_option('lts_supported_forms', []);
            $selected_form_notifications = get_option('lts_form_notifications', []);
//            $selected_form_fields = get_option('lts_form_fields', []);
            $client_emails = get_option('lts_client_emails', []);


            // confirm current form and notification are LTS active
            $form_id = $entry['form_id'];
            $entry_id = rgar( $entry, 'id' );
            if( ! in_array( $form_id, $supported_forms ) ) {
                return $email;
            }
//            error_log(print_r('form id ' . $form_id . ' is LTS active, entry id: ' . rgar( $entry, 'id' ) . ', client emails, notifications: ' . $selected_form_notifications, true));
//            error_log(print_r( $client_emails , true));
//            error_log(print_r( $selected_form_notifications, true));
//            error_log(print_r('selected notification id '. $notification['id'], true));
            if( $selected_form_notifications[ $form_id ] !== $notification['id'] ) {
                error_log(print_r('this notification is not active, skipping', true));
                return $email;
            }

            error_log(print_r('sending notification', true));

//            $status = rgar( $entry, 'status' );
//            error_log(print_r('presend email on form id ' . $form_id . ' : ' . rgar( $entry, 'id' ) . ' : ' . $status, true));
//            $entry = \GFAPI::get_entry( rgar( $entry, 'id' ) );
//            $status = rgar( $entry, 'status' );
//            error_log(print_r('did status change? ' . $status, true));



//            $spam_source = get_transient( 'gf_spam_source_' . $entry->id );
//            $akismet_spam = ( $spam_source === 'akismet' );

//            // Check if spam; if spam, what marked it as such
//            $is_spam = false;
//            $akismet_only = false;
//            if ( rgar( $entry, 'status' ) === 'spam' ) {
//
//                $entry_id = rgar($entry, 'id');
//                $notes = \GFFormsModel::get_lead_notes($entry_id);
//                error_log(print_r('notes for entry ' . $entry_id, true));
//
//                $akismet_flagged = false;
//                $honeypot_flagged = false;
//
//                foreach ($notes as $note) {
//                    $note_text = strtolower($note->value);
//                    error_log(print_r($note_text, true));
//                    if (strpos($note_text, 'akismet') !== false) {
//                        $akismet_flagged = true;
//                    }
//                    if (strpos($note_text, 'honeypot') !== false || strpos($note_text, 'anti-spam') !== false) {
//                        $honeypot_flagged = true;
//                    }
//                }
//
//                // Akismet only — allow the email through (your "possible false positive" notification)
//                if ($akismet_flagged && $honeypot_flagged) {
//                    error_log(print_r('SPAM as marked by Akismet and Honeypot', true));
//                    $is_spam = true;
//                } elseif ($akismet_flagged) {
//                    error_log(print_r('SPAM as marked by Akismet only', true));
//                    $is_spam = true;
//                    $akismet_only = true;
//                } elseif ($honeypot_flagged) {
//                    error_log(print_r('SPAM as marked by Honeypot only', true));
//                } else {
//                    error_log(print_r('Not spam.', true));
//                }
//
//            }

            // prepare GF Entry meta
//            error_log(print_r("Entry:", true));
//            error_log(print_r($entry, true));

//            $entry_id = $entry->id;
            $token    = wp_generate_password( 32, false, false );
            gform_update_meta( $entry_id, 'lts_status', 'Pending' );
            gform_update_meta( $entry_id, 'lts_token', $token );
//            gform_update_meta( $entry_id, 'lts_client_notification_sent', 0 );


            // create action buttons
            $approve_url = add_query_arg([
                    'action'   => 'validate_lead',
                    'valid'    => '1',
                    'id'       => $entry_id,
                    'nonce'    => wp_create_nonce( 'gf_validate_lead_' . $entry_id ),
            ], admin_url( 'admin-post.php' ) );
            $reject_url = add_query_arg([
                    'action'   => 'validate_lead',
                    'valid'    => '0',
                    'id'       => $entry_id,
                    'nonce'    => wp_create_nonce( 'gf_reject_lead_' . $entry_id ),
            ], admin_url( 'admin-post.php' ) );

            ob_start(); ?>
            <div style="text-align: center; margin-bottom: 40px; max-width: 800px;">
                <h2>Lead Tracking</h2>
                <p style="margin-bottom: 20px">Review Lead details and click below to Validate or Reject.</p>
                <a style="display: inline-block;color: #fff;background-color: #28a745;border-color: #28a745;font-weight: 400;text-align: center;vertical-align: middle;border: 1px solid transparent;text-decoration: none;font-family: Arial, sans-serif;padding:10px 20px;border-radius: 5px"
                   href="<?php echo esc_url( $approve_url ); ?>">Validate</a>
                <a style="display: inline-block;color: #fff;background-color: #bd2130;border-color: #b21f2d;font-weight: 400;text-align: center;vertical-align: middle;border: 1px solid transparent;text-decoration: none;font-family: Arial, sans-serif;padding:10px 20px;border-radius: 5px"
                   href="<?php echo esc_url( $reject_url ); ?>">Reject</a>
            </div>
            <?php
            $buttons_html = ob_get_clean();


            // Modify the email content based on whether it's HTML or plain text
//            if ($message_format === 'html') {
                $email['message'] = $buttons_html . $email['message'];
//            } else {
//                // For plain text emails
//                // todo
//                $buttons_text = "Button 1: https://example.com/button1\nButton 2: https://example.com/button2\n\n";
//                $email['message'] = $buttons_text . $email['message'];
//            }

            // Return the modified email content
            return $email;

        } catch (\Exception $exception) {
            error_log("Error: " . print_r($exception->getMessage(), true));

            // What more should we do?
            // Notify someone?

            return $email;
        }

    }

//    public function after_lead_submission($entry, $form ) {
//
//        error_log(print_r("LTS Detected form submission, ID: " . $form['id'], true));
//
//    }

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
        $rows .= '</table>';

        // build table
        $html  = '<table width="99%" border="0" cellpadding="1" cellspacing="0" bgcolor="#CCCCCC"><tr><td>';
        $html .= '<table width="100%" border="0" cellpadding="5" cellspacing="0" bgcolor="#FFFFFF">';
        $html .= '<tbody>' . $rows . '</tbody>';
        $html .= '</table></td></tr></table>';

        return $html;
    }

    private function filter_to_everneu_emails( $email_string ) {
        if ( empty( $email_string ) ) {
            return '';
        }

        $emails   = array_map( 'trim', explode( ',', $email_string ) );
        $filtered = array_filter( $emails, fn( $email ) => str_ends_with( strtolower( $email ), '@everneu.com' ) );

        return implode( ', ', $filtered );
    }

}