<?php

namespace EVN\Admin\Settings\Updater;

use EVN\Helpers\Encryption;

/**
 *
 *  Form to keep EVN_GITHUB_TOKEN to uploading updates for plugin
 *
 */

class UpdaterForm {

    function __construct() {
    }


    public function display_svg_ui() {
        ?>

        <h3>API GitHub token to upload updates from repo</h3>

        <?php
        // save settings
        if (isset($_POST['ec_github_token_submit'])) {
            $token = sanitize_text_field($_POST['github_option']);
            $encrypted = Encryption::encrypt($token);

            $github_token_value = array(
                'EVN_GITHUB_TOKEN' => $encrypted,
            );

            if (!get_option('ev_github_token')) {
                 add_option('ev_github_token', $github_token_value);
                 echo '<div id="message" class="updated"><p>Token saved successfully!</p></div>';
                 //error_log("GitHub token saved successfully");
            } else {
                 update_option('ev_github_token', $github_token_value);
                 echo '<div id="message" class="updated"><p>Token updated successfully!</p></div>';
                 //error_log("GitHub token updated successfully");
            }
        }

        $github_token_wpo = get_option('ev_github_token');
        if (!empty($github_token_wpo) && is_string($github_token_wpo)) {
            $github_token_wpo = json_decode($github_token_wpo, true);
        }

        $ecp_token = $github_token_wpo['EVN_GITHUB_TOKEN'];

        echo '<form method="post" class="ev-submit-form">
         <div class="ev-form">
           <div class="">
             <label id="apikey" for="ApiKey">API token</label>
           </div>
           <div class="">
              <input class="style-field" id="github_option" name="github_option" type="text" value="'.$ecp_token.'">
              <i>*Token encrypts after saving</i>
           </div>
         </div>
         <br><input type="submit" name="ec_github_token_submit" class="button button-primary" value="Save">
       </form>';
    }



}
