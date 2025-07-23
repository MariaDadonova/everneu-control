<?php

namespace EVN\Admin\Settings\GoogleAPIKey;

use EVN\Helpers\Encryption;

/**
 *
 *  Form to upload service_key.json file
 *  and keep EVN_GOOGLE_SERVICE_KEY_JSON_URL
 *  to connect plugin with Google Spreadsheet
 *
 */


class KeyForm
{
    function __construct() {
    }


    public function display_google_form_ui() {
        ?>

        <h3>API Google service key to put DropBox links to Google SpreadSheet file</h3>

        <?php
        // save settings
        if (isset($_FILES['google_json_key'])) {

            //Take an uploading file
            $file = $_FILES['google_json_key'];

            if ($file['type'] !== 'application/json') {
                add_settings_error('google_json_key', 'invalid_type', 'File should be a JSON.');
                return;
            }

            $upload_dir = wp_upload_dir();
            $target_dir = trailingslashit($upload_dir['basedir']) . 'google-service-key/';
            wp_mkdir_p($target_dir);

            //Path to saved file
            $target_path = $target_dir . 'service_key.json';
            //Encrypt path to save in database
            $en_target_path = Encryption::encrypt($target_path);

            //Move and check JSON
            if (move_uploaded_file($file['tmp_name'], $target_path)) {
                //Check if JSON is valid
                $content = file_get_contents($target_path);
                $json = json_decode($content, true);
                if (!isset($json['type'], $json['private_key'], $json['client_email'])) {
                    unlink($target_path);
                    add_settings_error('google_json_key', 'invalid_json', 'Неверный формат JSON-ключа.');
                    return;
                }

                //Add to wp_options
                update_option('ev_google_service_key', $en_target_path);
                add_settings_error('google_json_key', 'success', 'Файл успешно загружен.', 'updated');
            } else {
                add_settings_error('google_json_key', 'upload_error', 'Не удалось сохранить файл.');
            }


            $google_service_key_url = array(
                'EVN_GOOGLE_SERVICE_KEY' => $en_target_path,
            );

            if (!get_option('ev_google_service_key')) {
                add_option('ev_google_service_key', $google_service_key_url);
                echo '<div id="message" class="updated"><p>Google service key saved successfully!</p></div>';
                //error_log("Google service key saved successfully");
            } else {
                update_option('ev_google_service_key', $google_service_key_url);
                echo '<div id="message" class="updated"><p>Google service key updated successfully!</p></div>';
                //error_log("Google service key updated successfully");
            }
        }

        $google_service_key_url_check = get_option('ev_google_service_key');
        if (!empty($google_service_key_url_check) && is_string($google_service_key_url_check)) {
            $google_service_key_url_check = json_decode($google_service_key_url_check, true);
        }

        if (!empty($google_service_key_url_check['EVN_GOOGLE_SERVICE_KEY'])) {
            echo '<h4 style="display: flex; gap: 10px;">json file uploaded: <p style="color: green"> yes</p></h4>';
        } else {
            echo '<h4 style="display: flex; gap: 10px;">json file uploaded: <p style="color: red"> no</p></h4>';
        }

        echo '<h4>Instructions: how to connect Google Sheets to the plugin</h4>
              <ol>
                <li>Go to <a href="https://console.cloud.google.com" target="_blank">console.cloud.google.com</a></li>
                <li>Select or create a project</li>
                <li>Go to <i style="color:#0A246A">APIs & Services > Credentials</i></li>
                <li>Click <i style="color:#0A246A">Create Credentials > Service account</i></li>
                <li>Name your account and click <i style="color:#0A246A">Continue</i></li>
                <li>In the access step, select the Editor or Sheets API User role</li>
                <li>After creation, click on the <i style="color:#0A246A">account > Keys tab</i></li>
                <li>Click <i style="color:#0A246A">Add Key > Create New Key > JSON</i></li>
                <li>Download the .json file</li>
                <li>Upload .json file to this form</li>
                <li>(Optional: if need connection to new account) Give the service account access to the required Google Spreadsheet (via its "Share" — add email xxxx@project.iam.gserviceaccount.com as an Editor)</li>
              </ol>
             ';

        echo '<form method="post" class="ev-submit-form" enctype="multipart/form-data">
         <div class="ev-form">
           <div class="">
             <label id="apikey" for="ApiKey">API service key</label>
           </div>
           <div class="">
              <input class="style-field" type="file" id="google_json_key" name="google_json_key" accept=".json" required>
           </div>
         </div>
         <br><input type="submit" name="ec_google_service_key_submit" class="button button-primary" value="Save">
       </form>';

    }

}