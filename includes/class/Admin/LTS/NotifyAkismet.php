<?php

namespace EVN\Admin\LTS;

/**
 * Notify Akismet
 * Reports missed spam or mistaken ham to Akismet.
 */
class NotifyAkismet {


    private static function submit( $entry, $form, string $path ): void {
        if ( ! class_exists( 'Akismet' ) ) {
//            error_log(print_r('akismet http post not available', true));
            return;
        }

        // get API key
        if ( class_exists( 'Akismet' ) ) {
            $api_key = \Akismet::get_api_key();
        } else {
            // Akismet stores the key in options as a fallback
            $api_key = (string) get_option( 'wordpress_api_key' );
//            error_log(print_r('got api key via get_option fallback ' . $api_key, true));
        }
        if ( empty( $api_key ) ) {
//            error_log(print_r('api key not found', true));
            \GFFormsModel::add_note(
                rgar( $entry, 'id' ),
                0,                          // user ID — 0 for system/programmatic
                'Lead Tracking System',     // user name label
                'Akismet API key not found. Contact a developer to configure Akismet.',
                'lead-tracking'             // note type
            );
            return;
        }


        // send feedback
        $fields = [
            'blog'                 => get_option( 'home' ),
            'blog_lang'            => get_locale(),
            'blog_charset'         => get_option( 'blog_charset' ),
            'user_ip'              => rgar( $entry, 'ip' ),
            'user_agent'           => rgar( $entry, 'user_agent' ),
            'referrer'             => rgar( $entry, 'source_url' ),
            'comment_type'         => 'contact-form',
            'comment_author'       => self::get_author( $entry, $form ),
            'comment_author_email' => self::get_author_email( $entry, $form ),
            'comment_content'      => self::get_content( $entry, $form ),
        ];
//        error_log(print_r('sending akismet feedback...', true));
//        error_log(print_r($fields, true));
        $akismet_reply = \Akismet::http_post( \Akismet::build_query( $fields ), $path );
//        error_log(print_r($akismet_reply, true));

        if ( 'Thanks for making the web a better place.' == $akismet_reply[1] ) {
            $msg = ( str_contains( $path, 'spam') ) ? "Successfully notified Akismet missed spam." : "Successfully notified Akismet incorrectly marked as spam.";
        } else {
            $msg = ( str_contains( $path, 'spam') ) ? "Failed to notify Akismet missed spam." : "Failed to notify Akismet incorrectly marked as spam.";
        }

        \GFFormsModel::add_note(
            rgar( $entry, 'id' ),
            0,                          // user ID — 0 for system/programmatic
            'Lead Tracking System',     // user name label
            $msg,
            'lead-tracking'             // note type
        );
    }

    public static function report_spam( $entry, $form ): void {
        self::submit( $entry, $form, 'submit-spam' );
    }

    public static function report_ham( $entry, $form ): void {
        self::submit( $entry, $form, 'submit-ham' );
    }


    private static function get_author( $entry, $form ): string {
        foreach ( $form['fields'] as $field ) {
            if ( $field->type === 'name' ) {
                return trim( rgar( $entry, $field->id . '.3' ) . ' ' . rgar( $entry, $field->id . '.6' ) );
            }
            if ( in_array( $field->type, [ 'text', 'hidden' ], true ) && stripos( $field->label, 'name' ) !== false ) {
                return rgar( $entry, $field->id );
            }
        }
        return '';
    }

    private static function get_author_email( $entry, $form ): string {
        foreach ( $form['fields'] as $field ) {
            if ( $field->type === 'email' ) {
                return rgar( $entry, $field->id );
            }
        }
        return '';
    }

    private static function get_content( $entry, $form ): string {
        $parts = [];
        foreach ( $form['fields'] as $field ) {
            if ( in_array( $field->type, [ 'textarea', 'text' ], true ) ) {
                $value = rgar( $entry, $field->id );
                if ( ! empty( $value ) ) {
                    $parts[] = $field->label . ': ' . $value;
                }
            }
        }
        return implode( "\n", $parts );
    }
}