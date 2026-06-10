<?php
namespace EVN\Admin\LTS;

class SpamLead {


	/**
	 * Initialize SpamLead
	 *
	 * @since    1.0.0
	 */
	public function __construct() {

        add_action( 'admin_post_spam_lead', [$this, 'handle_spam_lead'] );
        add_action( 'admin_post_nopriv_spam_lead', [$this, 'handle_spam_lead'] );

        add_action( 'admin_post_confirm_spam_lead', [$this, 'handle_confirm_spam_lead'] );
        add_action( 'admin_post_nopriv_confirm_spam_lead', [$this, 'handle_confirm_spam_lead_nopriv'] );

	}


	public function handle_spam_lead() {

        $this->printLTSHeader();

        // get input
        $id    = intval( $_REQUEST['id'] ?? 0 );
        if( ! $id ) {
            echo "<div> > Invalid Lead ID {$id}.</div>";
            die();
        }
        $token         = sanitize_text_field( $_GET['nonce'] ?? '' );
        $stored_token  = gform_get_meta( $id, 'lts_token' );
        if ( empty( $token ) || ! hash_equals( $stored_token, $token ) ) {
            echo "<div> > Invalid or expired link.</div>";
            die();
        }

        // confirm button
        $button_styles = "display:inline-block;padding:10px 20px;background:#d63638;color:#fff;border:none;border-radius:4px;cursor:pointer;font-size:15px;font-weight:500;text-decoration:none;margin-right:8px;";
        $confirm_url = admin_url( 'admin-post.php' );
        $btn_txt = "Confirm Spam";

        echo "<div> > Click Confirm below to mark Lead as SPAM.</div>";


        // form details
        $entry = \GFAPI::get_entry( $id );
        $form_id = rgar( $entry, 'form_id' );
        $form = \GFAPI::get_form( $form_id );
        echo $this->get_entry_fields_html( $form, $entry );


        // Option A — wp_nonce_field() echoes the hidden input directly
        echo "<form method='POST' action='{$confirm_url}'>";
        echo "<input type='hidden' name='action' value='confirm_spam_lead' />";
        echo "<input type='hidden' name='id' value='{$id}' />";
//            echo "<input type='hidden' name='token' value='{$token}' />";
        wp_nonce_field( 'gf_review_spam_' . $id );
        echo "<button type='submit' style='{$button_styles}'>{$btn_txt}</button>";
        echo "</form>";


        die();

	}


    public function handle_confirm_spam_lead() {

        try {

            // get input
            $id    = intval( $_REQUEST['id'] ?? 0 );
            if( ! $id ) {
                $this->printLTSHeader();
                echo "<div> > Invalid Lead ID {$id}.</div>";
                die();
            }
            // confirm nonce
            if ( ! check_admin_referer( 'gf_review_spam_' . $id ) ) {
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
            \GFFormsModel::add_note(
                    $id,
                    $current_user->ID,              // user ID — 0 for system/programmatic
                    $current_user->display_name,    // user name label
                    'Lead marked as SPAM by ' . $current_user->display_name . '.',
                    'lead-tracking'                 // note type
            );


            // process spam lead
            $this->spam_lead( $id );



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
    public function handle_confirm_spam_lead_nopriv() {

        $this->printLTSHeader();

        // get input
        $id    = intval( $_REQUEST['id'] ?? 0 );
        if( ! $id ) { ?>
            <div> > Invalid Lead ID.</div>
            <?php
            die();
        }
        // confirm nonce
        if ( ! check_admin_referer( 'gf_review_spam_' . $id ) ) {
            $this->printLTSHeader(); ?>
            <div> > Invalid or expired link.</div>
            <?php
            die();
        }

        // record note
        $confirmation_msg = 'Lead marked as SPAM via secure, anonymous link.';
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

        // spam / reject lead
        $this->spam_lead( $id );

        // set anonymous user
        gform_update_meta( $id, 'lts_reviewed_by', 'Anonymous Admin' );

        // close simple acknowledgement text
        ?>
        </div>
        <?php

        die();
    }


    private function spam_lead( $id ) {

        // id must exist
        $entry = \GFAPI::get_entry( $id );
        if ( is_wp_error( $entry ) ) {
            throw new Exception( 'LTS Error: entry not found for ID ' . $id );
        }


//error_log(print_r( 'checking valid for id ' . $id, true));
//        error_log(print_r( $valid ? "lead $id is valid" : "lead $id is NOT valid", true));

        // set Gravity Form status to spam
        \GFAPI::update_entry_property( $id, 'status', 'spam' );

        //update status meta
        gform_update_meta( $id, 'lts_status', 'Spam' );
        gform_update_meta( $id, 'lts_reviewed_at', time() );


        // Check for one of the scenarios where we notify Akismet
        // If not originally marked as spam, but set as spam by admin - notify Akismet missed spam
        $initial_spam = gform_get_meta( $id, 'lts_initial_spam' );
        if ( ! $initial_spam ) {

            // notify Akismet missed spam
            $form_id = rgar( $entry, 'form_id' );
            $form = \GFAPI::get_form( $form_id );
            NotifyAkismet::report_spam( $entry, $form );

        }


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

    private function printLTSHeader()
    {
        ?>
        <div style='font-family: ui-monospace, Menlo, Consolas, "Liberation Mono", monospace;font-size: 22px;'>
        <h1>Lead Tracking System</h1>
        <?php
    }
}