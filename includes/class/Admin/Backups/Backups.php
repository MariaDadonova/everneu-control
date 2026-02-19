<?php

namespace EVN\Admin\Backups;

use EVN\Helpers\CronInterval;
use EVN\Helpers\Encryption;
use EVN\Helpers\Environment;
use DropboxAPI;
use MySql;
use ZipArchive;

/**
 * Backups subpage includes:
 *
 * 1. Backups schedule
 *
 * 2. Manual creating backup
 *
 * 3. API keys
 *
 * 4. Logs
 *
 * @version 1.1
 */

class Backups
{

    function __construct() {

        // add backups submenu item
        add_action( 'admin_menu', [$this, 'backups_page'], 25 );

    }

    public function backups_page() {


        $page = add_menu_page('Everneu Control Options', 'Everneu Control', 8, 'ec_backups', [$this, 'display_backups_ui'],'dashicons-archive');
        $page = add_submenu_page('ec_backups', 'Backups', "Backups", 8, 'ec_backups', [$this, 'display_backups_ui'] );

    }

    public function display_backups_ui()
    {
        wp_enqueue_style('evn-client-style', EVN_URL . 'assets/css/backups_tabs_styles.css');
        ?>

        <h3>Backups</h3>

        <div id="container">
            <div class="tabs">
                <input id="schedule-backup" type="radio" name="tabs" checked>
                <label for="schedule-backup">Backups schedule</label>
                <input id="manual-backup" type="radio" name="tabs">
                <label for="manual-backup">Manual backup</label>
                <input id="api-keys" type="radio" name="tabs">
                <label for="api-keys">API keys</label>
                <!--<input id="schedule-backup" type="radio" name="tabs">
                <label for="schedule-backup">Schedule a backup</label>-->
                <input id="logs" type="radio" name="tabs">
                <label for="logs">Logs</label>

                <!--Section for display Backups schedule -->
                <section id="content-schedule-backup">
                    <h3>Backup's schedule</h3>

                    <?php
                    $cron_jobs = _get_cron_array();

                    if (!$cron_jobs) {
                        echo '<div class="notice notice-error"><p>No cron jobs found.</p></div>';
                        return;
                    }

                    echo '<div class="notice notice-success"><p><strong>Cron Jobs:</strong></p><ul>';
                    foreach ($cron_jobs as $timestamp => $cron) {
                        foreach ($cron as $hook => $details) {
                            if (esc_html($hook) == 'schedule_backup_by_month') {
                                echo '<li><strong>' . esc_html($hook) . '</strong> - Next Run: ' . date('Y-m-d H:i:s', $timestamp) . '</li>';
                            }
                        }
                    }
                    echo '</ul></div>';
                    ?>

                    <?php
                    // Doesn't work correctly with WPEngine
                    /*$env = wp_get_environment_type();
                    echo '<h4>The current environment: '.$env.'</h4>';*/

                    $create = '';
                    $delete = '';
                    $api_keys = '';

                    if (Environment::isProduction()) {
                        $env = 'Production';
                        /* If cron task exists, show button, if not - disable */
                        if (wp_next_scheduled('schedule_backup_by_month') && get_option('ev_dropbox_settings')){
                            $cron_ex = 'Yes';
                            $env_status = '<span style="color: green">Backup is created according to a schedule</span>';
                            $create = "disabled";
                            $api_keys = 'Exist';
                        } elseif (!wp_next_scheduled('schedule_backup_by_month') && get_option('ev_dropbox_settings')) {
                            $cron_ex = 'No';
                            $env_status = '<span style="color: red">Backup is not created without a cron task</span>';
                            $delete = "disabled";
                            $api_keys = 'Exist';
                        } elseif (!get_option('ev_dropbox_settings') && wp_next_scheduled('schedule_backup_by_month')) {
                            $cron_ex = 'No';
                            $api_keys = 'None';
                            $env_status = '<span style="color: red">Backup is not send to DropBox without API keys. Please, fill out keys in form of API keys tab</span>';
                            $delete = "disabled";
                            $api_keys = 'Does not exist';
                        }
                    } elseif (Environment::isStaging()) {
                        $env = 'Staging';
                        $env_status = '<span style="color: red">Backup is not created in staging</span>';
                        $delete = "disabled";
                    } elseif (Environment::isDevelopment()) {
                        $env = 'Development';
                        $env_status = '<span style="color: red">Backup is not created in development</span>';
                        $delete = "disabled";
                    } else {
                        $env = 'Unknown';
                        /* If cron task exists, show button, if not - disable */
                        if (wp_next_scheduled('schedule_backup_by_month') && get_option('ev_dropbox_settings')){
                            $cron_ex = 'Yes';
                            $env_status = '<span style="color: green">Backup is created according to a schedule</span>';
                            $create = "disabled";
                            $api_keys = 'Exist';
                        } elseif (!wp_next_scheduled('schedule_backup_by_month') && get_option('ev_dropbox_settings')) {
                            $cron_ex = 'No';
                            $env_status = '<span style="color: red">Backup is not created without a cron task</span>';
                            $delete = "disabled";
                            $api_keys = 'Exist';
                        } elseif (!get_option('ev_dropbox_settings') && wp_next_scheduled('schedule_backup_by_month')) {
                            $cron_ex = 'No';
                            $api_keys = 'None';
                            $env_status = '<span style="color: red">Backup is not send to DropBox without API keys. Please, fill out keys in form of API keys tab</span>';
                            $delete = "disabled";
                            $api_keys = 'Does not exist';
                        }
                    }

                    echo '<table class="tg">
                             <tbody>
                                <tr>
                                  <th></th>
                                  <th></th>
                                  <th>Status</th>
                                </tr>
                                <tr>
                                  <td><strong>The current environment:</strong></td>
                                  <td>'.$env.'</td>
                                  <td rowspan="3">'.$env_status.'</td>
                                </tr>
                                <tr>
                                  <td><strong>The cron task exist:</strong></td>
                                  <td>'.$cron_ex.'</td>
                                </tr>
                                <tr>
                                  <td><strong>API keys:</strong></td>
                                  <td>'.$api_keys.'</td>
                                </tr>
                             </tbody>
                           </table>';

              if (!Environment::isProduction()) {
                  echo '<div style="display: flex; gap: 10px;">
                              <form method="post">
                                <button type="submit" name="set_cron" class="button button-primary" ' . $create . '>Create Cron task</button>
                              </form>
                              <form method="post">
                                <button type="submit" name="delete_cron" class="button button-primary" ' . $delete . '>Delete Cron task</button>
                              </form>
                          </div>';

                  /* Button for creating cron by click */
                  if (isset($_POST['set_cron'])) {
                      if (!wp_next_scheduled('schedule_backup_by_month')) {
                          CronInterval::schedule_monthly_backup_event();
                          echo "Cron task added!";
                      } else {
                          echo "The Cron task already exists!";
                      }
                  }

                  /* Button for removing cron by click */
                  if (isset($_POST['delete_cron'])) {
                      if (wp_next_scheduled('schedule_backup_by_month')) {
                          CronInterval::clear_scheduled_backup_event();
                          echo "Cron task deleted!";
                      } else {
                          echo "The Cron task already deleted!";
                      }
                  }
              }
                    ?>

                </section>

                <!--Section for display Form for creating backup manually -->
                <section id="content-manual-backup">
                    <h3>Manual backup</h3>
                    You can create a backup manually by clicking on the button below
                    <form method="post">
                           <input type="hidden" name="send" id="send" value="1">
                           <?php submit_button('Create a backup'); ?>
                    </form>

                    <?php
                    require_once EVN_DIR . 'includes/class/Admin/Backups/AutoBackupMaster.php';

                    if (isset($_POST["send"])) {
                        $backup = new AutoBackupMaster();

                        if ($backup->initBackup()) {
                            // Run the following steps via cron with a slight delay
                            wp_schedule_single_event(time() + 5,  'backup_step_dump_db');
                            echo '<div class="notice notice-success"><p>Backup started! Steps are running in the background.</p></div>';
                            error_log('Manual backup initiated successfully.');
                        } else {
                            echo '<div class="notice notice-error"><p>Failed to initiate backup.</p></div>';
                            error_log('Failed to initiate manual backup.');
                        }
                    }
                    ?>
                </section>

                <!--Section for display API's keys -->
                <section id="content-api-keys">
                      <?php
                      /* Keys for app with Permission type: Scoped App (App Folder) */
                      if (isset($_POST['ec_dropbox_keys_submit'])) {
                          $app_key = sanitize_text_field($_POST['app_key']);
                          $app_secret = sanitize_text_field($_POST['app_secret']);
                          $access_code = sanitize_text_field($_POST['access_code']);
                          $refresh_token = sanitize_text_field($_POST['refresh_token']);

                          $encrypted_app_key = Encryption::encrypt($app_key);
                          $encrypted_app_secret = Encryption::encrypt($app_secret);
                          $encrypted_access_code = Encryption::encrypt($access_code);
                          $encrypted_refresh_token = Encryption::encrypt($refresh_token);

                          $dropbox_settings = array(
                              'app_key'       => $encrypted_app_key,
                              'app_secret'    => $encrypted_app_secret,
                              'access_code'   => $encrypted_access_code,
                              'refresh_token' => $encrypted_refresh_token
                          );

                          if (!get_option('ev_dropbox_settings')) {
                              add_option('ev_dropbox_settings', $dropbox_settings);
                              echo '<div id="message" class="updated"><p>DropBox keys saved successfully!</p></div>';
                              error_log("DropBox keys saved successfully");
                          } else {
                              update_option('ev_dropbox_settings', $dropbox_settings);
                              echo '<div id="message" class="updated"><p>DropBox keys updated successfully!</p></div>';
                              error_log("DropBox keys updated successfully");
                          }
                      }

                      $dropbox_settings = get_option('ev_dropbox_settings');
                      if (!empty($dropbox_settings) && is_array($dropbox_settings)) {
                          $refresh_token = $dropbox_settings['refresh_token'];
                          $app_key       = $dropbox_settings['app_key'];
                          $app_secret    = $dropbox_settings['app_secret'];
                          $access_code   = $dropbox_settings['access_code'];
                      } else {
                          $refresh_token = $app_key = $app_secret = $access_code = '';
                      }


                      ?>

                <div style="display: flex; gap: 20px; width: 100%;">
                  <div style="width: 100%;">
                    <h3>DropBox keys</h3>
                    <p>Permission type: Scoped App (App Folder)</p>
                    <!-- inert disabled form -->
                    <!--<form method="post" inert="" class="ev-submit-form">-->
                    <form method="post" class="ev-submit-form">
                        <div class="ev-form">
                            <div class="">
                                <label id="appkey" for="AppKey">App Key </label>
                            </div>
                            <div class="">
                                <input id="app_key" name="app_key" class="style-field" type="text" value="<?= $app_key ?>">
                            </div>
                        </div>
                        <div class="ev-form">
                            <div class="">
                                <label id="appsecret" for="AppSecret">App Secret </label>
                            </div>
                            <div class="">
                                <input id="app_secret" name="app_secret" class="style-field" type="text" value="<?= $app_secret ?>">
                            </div>
                        </div>
                        <div class="ev-form">
                            <div class="">
                                <label id="accesscode" for="AccessCode">Access Code </label>
                            </div>
                            <div class="">
                                <input id="access_code" name="access_code" class="style-field" type="text" value="<?= $access_code ?>">
                            </div>
                        </div>
                        <div class="ev-form">
                            <div class="">
                                <label id="refreshtoken" for="RefreshToken">Refresh Token </label>
                            </div>
                            <div class="">
                                <input id="refresh_token" name="refresh_token" class="style-field" type="text" value="<?= $refresh_token ?>">
                            </div>
                        </div>
                        <i>*All keys encrypt after saving</i><br>
                        <br><input type="submit" name="ec_dropbox_keys_submit" class="button button-primary" value="Save">
                    </form>
                  </div>
                </div>
                </section>
               <!-- <section id="content-schedule-backup">
                    <p>

                    <h3>Schedule backup</h3>
                    <form method="post">
                        <table>
                            <tbody>
                            <tr>
                                <th>
                                    <label id="freq">Frequency:</label>
                                </th>
                                <td>
                                    <select name="schedule_type" id="schedule_type">
                                        <option value="daily">Per day (works only this option)</option>
                                        <option value="weekly">Per week</option>
                                        <option value="monthly">Per month</option>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th>
                                    <label id="time">Time</label>
                                </th>
                                <td>
                    <span>
						<input type="number" min="0" max="23" step="1" name="hours" id="hours" value=""> Hours
						<input type="number" min="0" max="59" step="1" name="minutes" id="minutes" value=""> Minutes
					</span>
                                </td>
                            </tr>
                            </tbody>
                        </table>-->
                        <?php /*submit_button('Save');*/ ?>
                   <!-- </form>
                    <div>
                        <h3>List of schedule saves (current tasks)</h3>
                        <p>Just for self-checking.</p>-->
                        <?php
                        /*global $wpdb;
                        $table_name = $wpdb->prefix."crontasks";
                        $sql = "SELECT * FROM ".$table_name;
                        $result = $wpdb->get_results($sql);
                        $rrr = "";
                        $rrr.="<table class=\"tg\"><tr>";
                        foreach($result[0] as $name=>$value){
                            $rrr.="<th>".$name."</th>";
                        }
                        $rrr.="<th>actions</th>";
                        $rrr.="</tr>";
                        foreach($result as $row){
                            $rrr.="<tr>";
                            foreach($row as $name=>$value){
                                $rrr.="<td class=\"tg-0lax\">".$value."</td>";
                            }
                            $rrr.="<td class=\"tg-0lax\">
              <form method=\"GET\">
                         <a href=\"&rec=c\">delete</a>
              </form>
           </td>";
                            $rrr.="</tr>";
                        }
                        $rrr.="</table>";
                        echo $rrr;
                        */
                        //print_r(_get_cron_array());

                        ?>

                  <!--  </div>-->

                    <?php
                    /*if (isset($_POST["schedule_type"])) {
                        $wpdb->insert($table_name, array( 'frequency' => $_POST["schedule_type"], 'month' => '', 'day' => '', 'hours' => $_POST["hours"], 'minutes' => $_POST["minutes"]), array( '%s', '%s', '%s', '%s', '%s' ) );
                    } //else { echo 'The Frequency parameter cannot be empty.'; }*/


                    ?>


                 <!--   </p>
                </section>-->

                <!--Section for display logs -->
                <section id="content-logs">
                    <h3>Backups history</h3>
                    <p>
                        <?php

                        //Authorization
                        $dropbox_settings = get_option('ev_dropbox_settings');
                        if (!empty($dropbox_settings) && is_string($dropbox_settings)) {
                            $dropbox_settings = json_decode($dropbox_settings, true);
                        }

                        $refresh_token = Encryption::decrypt($dropbox_settings['refresh_token']);
                        $app_key = Encryption::decrypt($dropbox_settings['app_key']);
                        $app_secret = Encryption::decrypt($dropbox_settings['app_secret']);
                        $access_code = Encryption::decrypt($dropbox_settings['access_code']);

                        $drops = new DropboxAPI($app_key, $app_secret, $access_code);

                        //Access token
                        $access_token = $drops->curlRefreshToken($refresh_token);

                        $instal =  $_SERVER['HTTP_HOST'];

                        if (!$drops->GetListFolder($access_token, $instal)) {
                            $drops->CreateFolder($access_token, $instal);
                        }

                        $linkData = $drops->getOrCreateSharedLinkForFolder($access_token, '/Secondary Backups/'.$instal);

                        if ($linkData !== false) {
                            echo 'Link to folder with backups: <a href="' . $linkData . '" target="_blank">Click here</a>';
                        } else {
                            echo 'Error creating link.';
                        }


                        // Display zip size in gb, mb, kb depends on size from bytes
                        function formatSizeUnits($bytes)
                        {
                            if ($bytes >= 1073741824) {
                                $bytes = number_format($bytes / 1073741824, 2) . ' GB';
                            } elseif ($bytes >= 1048576) {
                                $bytes = number_format($bytes / 1048576, 2) . ' MB';
                            } elseif ($bytes >= 1024) {
                                $bytes = number_format($bytes / 1024, 2) . ' KB';
                            } elseif ($bytes > 1) {
                                $bytes = $bytes . ' bytes';
                            } elseif ($bytes == 1) {
                                $bytes = $bytes . ' byte';
                            } else {
                                $bytes = '0 bytes';
                            }
                            return $bytes;
                        }




                        global $wpdb;
                        $charset_collate = $wpdb->get_charset_collate();
                        $tablename = $wpdb->prefix. "ev_" . "backup_logs";

                        $logs = $wpdb->get_results(
                            "
	                               SELECT * 
	                               FROM $tablename
	                              "
                        );

                        $rrr="<table class=\"tg\"><tr>";
                        $rrr.="<th>Name</th>";
                        $rrr.="<th>Size</th>";
                        $rrr.="<th>Date</th>";
                        $rrr.="<th>Path</th>";
                        $rrr.="</tr>";

                        if ( $logs ) {
                            foreach ($logs as $log) {
                                $rrr.="<tr>";
                                $rrr.="<td>".$log->name."</td>";
                                $rrr.="<td>".formatSizeUnits($log->size)."</td>";
                                $rrr.="<td>".$log->date."</td>";
                                $link_to_file = $drops->getOrCreateSharedLinkForFile($access_token, $log->path);
                                if ($link_to_file) {
                                    $rrr.="<td><a href='". $link_to_file ."' target='_blank'>Download .zip</a></td>";
                                } else {
                                    $rrr.="<td>No file in folder</td>";
                                }

                                $rrr.="</tr>";
                            }
                        } else {
                            echo '<p>Logs is empty.</p>';
                        }

                        $rrr.="</table>";
                        echo $rrr;

                        ?>


                    </p>
                </section>
            </div>
        </div>

        <?php
    }

}