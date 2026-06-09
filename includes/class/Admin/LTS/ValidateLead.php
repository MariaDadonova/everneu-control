<?php
namespace EVN\Admin\LTS;

class ValidateLead {


	/**
	 * Initialize ValidateLead
	 *
	 * @since    1.0.0
	 */
	public function __construct() {

		add_action( 'admin_post_validate_lead', [$this, 'handle_validate_lead'] );
		add_action( 'admin_post_nopriv_validate_lead', [$this, 'handle_validate_lead_nopriv'] );

	}


	public function handle_validate_lead() {

        // get input
        $valid = '';
        $id = 0;
        if( isset( $_GET['valid'] ) && $_GET['valid'] != '' ) {
            $valid = $_GET['valid'];
            $id = $_GET['id'];
        }
        if( isset( $_POST['valid'] ) && $_POST['valid'] != '' ) {
            $valid = $_POST['valid'];
            $id = $_POST['id'];
        }

        // validate / reject lead
        $this->validate_lead( $id, $valid );

        // get user
        $current_user = wp_get_current_user();
        gform_update_meta( $id, 'lts_reviewed_by', $current_user->display_name );


        // record note
        $confirmation_msg = "";
        if( $valid ) {
            $entry = \GFAPI::get_entry( $id );
            $form_id = rgar( $entry, 'form_id' );
            $client_emails = get_option('lts_client_emails', []);
            $to = $client_emails[ $form_id ];
            $confirmation_msg = 'Lead APPROVED by ' . $current_user->display_name . '. Client has been sent the Lead at  ' . implode( ', ', $to ) . '.';
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


        // AND THEN WHAT ARE WE SHOWING USER
		error_log(print_r( 'redirecting to id ' . $id . ', url '. get_permalink( $id ), true));

		//redirect to Entry backend page
        $entry = \GFAPI::get_entry( $id );
        $form_id = rgar( $entry, 'form_id' );
        $entry_url = admin_url( "admin.php?page=gf_entries&view=entry&id={$form_id}&lid={$id}" );
		wp_redirect( $entry_url );
		die();

	}


	public function handle_validate_lead_nopriv() {

        // get input
        $valid = '';
        $id = 0;
        if( isset( $_GET['valid'] ) && $_GET['valid'] != '' ) {
            $valid = $_GET['valid'];
            $id = $_GET['id'];
        }
        if( isset( $_POST['valid'] ) && $_POST['valid'] != '' ) {
            $valid = $_POST['valid'];
            $id = $_POST['id'];
        }

        // validate / reject lead
        $this->validate_lead( $id, $valid );

        // get user
        gform_update_meta( $id, 'lts_reviewed_by', 'Anonymous Admin' );


        // record note
        $confirmation_msg = "";
        if( $valid ) {
            $entry = \GFAPI::get_entry( $id );
            $form_id = rgar( $entry, 'form_id' );
            $client_emails = get_option('lts_client_emails', []);
            $to = $client_emails[ $form_id ];
            $confirmation_msg = 'Lead APPROVED via email link anonymously. Client was notified at ' . implode( ', ', $to ) . '.';
        } else {
            $confirmation_msg = 'Lead REJECTED via email link anonymously. No further action taken.';
        }
        \GFFormsModel::add_note(
            $id,
            0,                          // user ID — 0 for system/programmatic
            'Lead Tracking System',     // user name label
            $confirmation_msg,
            'lead-tracking'             // note type
        );

        // Display simply acknowledgement
        echo $confirmation_msg;

        die();
	}

    private function validate_lead( $id, $valid ) {

        error_log(print_r('validating lead...', true));


        //validate input
        // id must exist


//error_log(print_r( 'checking valid for id ' . $id, true));
        error_log(print_r( $valid ? "lead $id is valid" : "lead $id is NOT valid", true));

        //update status
        if( $valid ) {
            gform_update_meta( $id, 'lts_status', 'Validated' );

            // send email
            $this->send_client_notification( $id );
        } else {
            gform_update_meta( $id, 'lts_status', 'Rejected' );
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
        $message = $this->get_entry_fields_html( $form, $entry );
        $subject = "New submission from {$form_title}";

        add_filter( 'wp_mail_content_type', fn() => 'text/html' );
        wp_mail( $to, $subject, $message );
        remove_filter( 'wp_mail_content_type', fn() => 'text/html' );
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
        $rows .= '</table>';

        // build table
        $html  = '<table width="99%" border="0" cellpadding="1" cellspacing="0" bgcolor="#CCCCCC"><tr><td>';
        $html .= '<table width="100%" border="0" cellpadding="5" cellspacing="0" bgcolor="#FFFFFF">';
        $html .= '<tbody>' . $rows . '</tbody>';
        $html .= '</table></td></tr></table>';

        return $html;
    }
}