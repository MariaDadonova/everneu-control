<?php

/*
Plugin Name: Everneu Control
Plugin URI: https://github.com/MariaDadonova/evernue
Description: Plugin for control process of development in the company.
Version: 1.0.2
Author: Maria Dadonova
Author URI: http://everneu.wpengine.com
License: A "Slug" license name e.g. GPL2
*/


// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}


// Define globals for Version, Directory, URL, and Basename
define( 'EVN_VERSION', '0.0.1' );
/** Absolute path to plugin directory (with trailing slash). */
define( 'EVN_DIR', trailingslashit( __DIR__ ) );
/** Public URL to plugin directory (with trailing slash). */
define( 'EVN_URL', trailingslashit( plugin_dir_url( __FILE__ ) ) );
define( 'EVN_BASENAME', plugin_basename( __FILE__ ) );


// Run plugin
require_once __DIR__ . '/includes/class/EverneuControlPlugin.php';
//require_once plugin_dir_path( __FILE__ ) . 'includes/class/EverneuControlPlugin.php';
$evn_plugin = \EVN\EverneuControlPlugin::get_instance();


add_option("everneu_control", "1.0");


function ec_install(){

    global $wpdb;

    $charset_collate = $wpdb->get_charset_collate();

    $tablename = $wpdb->prefix. "ev_" . "backup_logs";

    $sql = "CREATE TABLE $tablename  (
  id int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  name varchar(255) NOT NULL,
  size int(11) NOT NULL,
  date timestamp NOT NULL,
  path varchar(255) NOT NULL
  ) $charset_collate;";

/*    $tablename = $wpdb->prefix. "ev_" . "crontasks";

    $sql .= "CREATE TABLE $tablename  (
  id int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  frequency varchar(255) NOT NULL,
  month int(11),
  day int(11),
  hours int(11),
  minutes int(11)
  ) $charset_collate;";*/

    // [add comment for why we require upgrade.php] or remove if no longer needed
    //Connecting upgrade.php , which contains the dbDelta() function – it safely creates/updates tables.
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);

    //Create folder for backups
    $upload = wp_upload_dir();
    $upload_dir = $upload['basedir'];
    $upload_dir = $upload_dir . '/backups';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777);
    }

}

function ec_uninstall(){

    delete_option('ev_dropbox_settings');
    
}


register_activation_hook(__FILE__, 'ec_install');
register_deactivation_hook(__FILE__, 'ec_uninstall');

function ev_plugin_admin_styles() {
    wp_enqueue_style( 'style', plugins_url().'/everneu-control/assets/css/style.css', '', null, '' );
}

