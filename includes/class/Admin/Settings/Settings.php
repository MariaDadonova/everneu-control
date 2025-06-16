<?php

namespace EVN\Admin\Settings;


/**
 * Settings subpage includes:
 *
 * 1. SiteMap stuff
 *
 * 2. SVG uploading allow
 *
 * @version 1.1
 */

class Settings
{

    function __construct() {

        // add SVG submenu item
        add_action( 'admin_menu', [$this, 'settings_page'], 30 );

        $this->load_svg_class();
        new SVG\AllowSVGUpload();

    }

    public function settings_page() {
        $page = add_submenu_page('ec_backups', 'Settings', "Settings", 8, 'ec_settings', [$this, 'display_settings_ui'] );
    }

    public function load_svg_class() {
        require_once __DIR__ . '/SVG/AllowSVGUpload.php';
    }

    public function display_settings_ui() {

        require_once __DIR__ . '/SVG/AllowSVGUpload.php';
        //require_once __DIR__. '/SiteMap/SiteMapClass.php';
        require_once __DIR__ . '/Updater/UpdaterForm.php';
        wp_enqueue_style( 'evn-client-style', EVN_URL . 'assets/css/settings_tabs_styles.css' );
        ?>

        <h3>Settings</h3>


        <div id="container">
            <div class="tabs">
                <!-- <input id="sitemap" type="radio" name="tabs" checked>
                <label for="sitemap">Site map</label>-->
                <input id="svg" type="radio" name="tabs" checked>
                <label for="svg">SVG</label>
                <input id="github" type="radio" name="tabs">
                <label for="github">Updates</label>
                 <!--<section id="content-sitemap">
                     <p>--><?php /*new SiteMap\SiteMapClass;*/ ?><!--</p>
                </section>-->
                <section id="content-svg">
                    <p><?php
                        $svg_obj = new SVG\AllowSVGUpload;
                        $svg_obj->display_svg_ui();
                    ?></p>
                </section>
                <section id="content-github">
                    <p><?php
                        $github_form = new Updater\UpdaterForm;
                        $github_form->display_svg_ui();
                    ?></p>
                </section>
            </div>
        </div>

        <?php

    }



}