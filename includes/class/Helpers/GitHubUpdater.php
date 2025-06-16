<?php
namespace EVN\Helpers;

class GitHubUpdater {
    private $plugin_file;   // full path to plugin /var/www/wp-content/plugins/everneu-control/everneu-control.php
    private $plugin_slug;   // 'everneu-control/everneu-control.php'
    private $github_user;   // 'MariaDadonova'
    private $github_repo;   // 'everneu-plugin-updater'
    private $branch;        // 'main'
    private $subfolder;     // '' is empty, because plugin in the root of repo

    public function __construct($plugin_file, $github_user, $github_repo, $branch = 'main', $subfolder = '') {
        $this->plugin_file = $plugin_file;
        $this->plugin_slug = plugin_basename($plugin_file);
        $this->github_user = $github_user;
        $this->github_repo = $github_repo;
        $this->branch = $branch;
        $this->subfolder = trim($subfolder, '/');

        add_filter('pre_set_site_transient_update_plugins', [$this, 'check_for_update']);

        $current_plugin_data = get_plugin_data($this->plugin_file);
        error_log("GitHubUpdater current_plugin_data: " . print_r($current_plugin_data, true));

        add_filter('plugins_api', [$this, 'plugins_api'], 10, 3);
        add_filter('upgrader_post_install', [$this, 'after_install'], 10, 3);
    }

    private function get_auth_headers() {
        $github_token_wpo = get_option('ev_github_token');
        if (!empty($github_token_wpo) && is_string($github_token_wpo)) {
            $github_token_wpo = json_decode($github_token_wpo, true);
        }

        $ecp_token = $github_token_wpo['EVN_GITHUB_TOKEN'];
        $token = Encryption::decrypt($ecp_token);

        return [
            'User-Agent' => 'WordPress Plugin Updater',
            'Authorization' => 'token ' . $token,
            'Accept' => 'application/vnd.github.v3+json',
        ];
    }

    private function get_api_file_url() {
        // Path to main plugin's file in repo
        $file_path = $this->subfolder ? $this->subfolder . '/' . basename($this->plugin_file) : basename($this->plugin_file);
        return "https://api.github.com/repos/{$this->github_user}/{$this->github_repo}/contents/{$file_path}?ref={$this->branch}";
    }

    private function get_zip_url() {
        // Link to branch's zip archive
        //return "https://api.github.com/repos/{$this->github_user}/{$this->github_repo}/zipball/{$this->branch}";
        return "https://github.com/{$this->github_user}/{$this->github_repo}/archive/refs/heads/{$this->branch}.zip";
    }

    public function check_for_update($transient) {
        error_log('GitHubUpdater check_for_update called');
        error_log("GitHubUpdater plugin_file path: " . $this->plugin_file);

        if (!function_exists('get_plugin_data')) {
            require_once(ABSPATH . 'wp-admin/includes/plugin.php');
        }

        if (empty($transient->checked)) {
            return $transient;
        }

        $response = wp_remote_get($this->get_api_file_url(), [
            'headers' => $this->get_auth_headers(),
            'timeout' => 20,
        ]);

        if (is_wp_error($response)) {
            error_log('GitHubUpdater: wp_remote_get error');
            return $transient;
        }

        $code = wp_remote_retrieve_response_code($response);
        if ($code !== 200) {
            error_log('GitHubUpdater: GitHub API returned code ' . $code);
            return $transient;
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);
        if (empty($data['content'])) {
            error_log('GitHubUpdater: no content in GitHub API response');
            return $transient;
        }

        $file_content = base64_decode($data['content']);
        $plugin_data = $this->get_plugin_data_from_text($file_content);
        $remote_version = $plugin_data['Version'] ?? '';

        $current_plugin_data = get_plugin_data($this->plugin_file);
        $current_version = $current_plugin_data['Version'] ?? '';

        error_log("GitHubUpdater: remote plugin data: " . print_r($plugin_data, true));
        error_log("GitHubUpdater: current version = $current_version, remote version = $remote_version");

        if ($remote_version && version_compare($remote_version, $current_version, '>')) {
            $transient->response[$this->plugin_slug] = (object)[
                'slug' => $this->plugin_slug,
                'plugin' => $this->plugin_slug,
                'new_version' => $remote_version,
                'url' => "https://github.com/{$this->github_user}/{$this->github_repo}",
                'package' => $this->get_zip_url(),
            ];
        }

        return $transient;
    }

    public function plugins_api($result, $action, $args) {
        if ($action !== 'plugin_information' || $args->slug !== $this->plugin_slug) {
            return $result;
        }

        $response = wp_remote_get($this->get_api_file_url(), [
            'headers' => $this->get_auth_headers(),
            'timeout' => 20,
        ]);

        if (is_wp_error($response)) {
            return $result;
        }

        $code = wp_remote_retrieve_response_code($response);
        if ($code !== 200) {
            return $result;
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);
        if (empty($data['content'])) {
            return $result;
        }

        $file_content = base64_decode($data['content']);
        $plugin_data = $this->get_plugin_data_from_text($file_content);

        return (object)[
            'name' => $plugin_data['Name'] ?? 'Plugin',
            'slug' => $this->plugin_slug,
            'version' => $plugin_data['Version'] ?? '',
            'author' => $plugin_data['Author'] ?? '',
            'homepage' => $plugin_data['PluginURI'] ?? '',
            'sections' => ['description' => $plugin_data['Description'] ?? ''],
        ];
    }

    public function after_install($response, $hook_extra, $result) {
        global $wp_filesystem;

        $plugin_dir = WP_PLUGIN_DIR . '/' . dirname($this->plugin_slug);
        $temp_dir = $result['destination'];

        // Folder after unpacking archive
        $unpacked_subfolder = trailingslashit($temp_dir) . $this->github_repo . '-' . $this->branch;

        // Removing old plugin's version
        $wp_filesystem->delete($plugin_dir, true);

        if ($wp_filesystem->is_dir($unpacked_subfolder)) {
            $wp_filesystem->move($unpacked_subfolder, $plugin_dir);
        } else {
            $wp_filesystem->move($temp_dir, $plugin_dir);
        }

        $result['destination'] = $plugin_dir;

        // Activating plugin after updating
        activate_plugin($this->plugin_slug);

        return $result;
    }

    private function get_plugin_data_from_text($text) {
        $headers = [
            'Name' => 'Plugin Name',
            'PluginURI' => 'Plugin URI',
            'Version' => 'Version',
            'Description' => 'Description',
            'Author' => 'Author',
        ];

        $data = [];
        foreach ($headers as $field => $header_name) {
            if (preg_match('/' . preg_quote($header_name, '/') . ':\s*(.*)$/mi', $text, $matches)) {
                $data[$field] = trim($matches[1]);
            } else {
                $data[$field] = '';
            }
        }
        return $data;
    }
}