<?php

ini_set('error_reporting', E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);


class DropboxAPI
{
    public $app_key;
    public $app_secret;
    public $code;

    function __construct($app_key, $app_secret, $code) {
        $this->app_key = $app_key;
        $this->app_secret = $app_secret;
        $this->code = $code;
    }

    //Ask refresh token
    public function curlToken() {
        $arr = [];
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://api.dropbox.com/oauth2/token');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, "code=".$this->code."&grant_type=authorization_code");
        curl_setopt($ch, CURLOPT_USERPWD, $this->app_key. ':' . $this->app_secret);
        $headers = array();
        $headers[] = 'Content-Type: application/x-www-form-urlencoded';
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        $result = curl_exec($ch);
        $result_arr = json_decode($result,true);


        if (curl_errno($ch)) {
            $arr = ['status'=>'error','token'=>null];
        }elseif(isset($result_arr['access_token'])){
            $arr = ['status'=>'okay','token'=>$result_arr['access_token']];
        }

        curl_close($ch);

        return $result_arr;
    }

    //Ask access token by refresh
    public function curlRefreshToken($refresh_token) {
        $arr = [];
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://api.dropbox.com/oauth2/token');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, "grant_type=refresh_token&refresh_token=".$refresh_token);
        curl_setopt($ch, CURLOPT_USERPWD, $this->app_key. ':' . $this->app_secret);
        $headers = array();
        $headers[] = 'Content-Type: application/x-www-form-urlencoded';
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        $result = curl_exec($ch);
        $result_arr = json_decode($result,true);


        if (curl_errno($ch)) {
            $arr = ['status'=>'error','token'=>null];
        }elseif(isset($result_arr['access_token'])){
            $arr = ['status'=>'okay','token'=>$result_arr['access_token']];
        }

        curl_close($ch);

        return $result_arr['access_token'];
    }

    //Create folder
    public function CreateFolder($access_token, $install) {
        $ch = curl_init();

        $folder_path = "/Secondary Backups/";

        curl_setopt($ch, CURLOPT_URL, 'https://api.dropboxapi.com/2/files/create_folder_v2');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        //curl_setopt($ch, CURLOPT_POSTFIELDS, "{\"path\": \"/Apps/".$install."\",\"autorename\": false}");
        curl_setopt($ch, CURLOPT_POSTFIELDS, "{\"path\": \"$folder_path".$install."\",\"autorename\": false}");

        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Authorization: Bearer '.$access_token, 'Content-Type: application/json'));

        echo $result = curl_exec($ch);
        if (curl_errno($ch)) {
            echo 'Error:' . curl_error($ch);
        }
        curl_close($ch);
    }

    //Get list folders
    public function GetListFolder($access_token, $install_name) {
        $ch = curl_init();

        // path to shared folder by id
        //$folder_path = "/id:hXUzUes2rSUAAAAAAAAAAQ";
        $folder_path = "/Secondary Backups";

        curl_setopt($ch, CURLOPT_URL, "https://api.dropboxapi.com/2/files/list_folder");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CAINFO, "cacert.pem");
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        //curl_setopt($ch, CURLOPT_POSTFIELDS, "{\"path\":\"/Apps\"}");
        curl_setopt($ch, CURLOPT_POSTFIELDS, "{\"path\":\"$folder_path\"}");
        curl_setopt($ch, CURLOPT_POST, 1);

        $headers = array();
        $headers[] = "Authorization: Bearer ".$access_token;
        $headers[] = "Content-Type: application/json";
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $result = curl_exec($ch);
        if (curl_errno($ch)) {
            echo 'Error:' . curl_error($ch);
        }
        curl_close ($ch);


        $json = json_decode($result, true);
        $bool = false;
        foreach ($json['entries'] as $data) {
            //echo 'File Name: ' . $data['name'];
            if ($data['name'] == $install_name) {
                $bool = true;
            }
        }


        return $bool;

    }

/////////////////////
    public function listDropboxFoldersFromRoot($access_token) {
        $url = 'https://api.dropboxapi.com/2/team/folders/list';
        $headers = [
            'Authorization: Bearer ' . $access_token,
            'Content-Type: application/json'
        ];

        $data = [

        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

        $response = curl_exec($ch);
        curl_close($ch);

        return json_decode($response, true);
    }

    function listInsideUserFolder($access_token) {
        $url = 'https://api.dropboxapi.com/2/files/list_folder';
        $headers = [
            'Authorization: Bearer ' . $access_token,
            'Content-Type: application/json'
        ];
        $post_fields = json_encode([
            'path' => '' // root user folder
        ]);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_fields);

        $response = curl_exec($ch);
        curl_close($ch);

        echo '<pre>';
        print_r($response);
        echo '</pre>';

        return json_decode($response, true);
    }
/////////////////////////////////

    //Send file to dropbox
    //file size <150Mb
    public function SendFile($access_token, $name, $fp, $size) {

        // path to shared folder by ID
        //$folder_path = "/id:hXUzUes2rSUAAAAAAAAAAQ/" . $name;
        $folder_path = "/Secondary Backups/" . $name;

        $cheaders = array('Authorization: Bearer '.$access_token,
            'Content-Type: application/octet-stream',
            /*'Dropbox-API-Arg: {"path":"/Apps/'.$name.'"}');*/
            'Dropbox-API-Arg: {"path":"' . $folder_path . '", "mode":"add", "autorename":true, "mute":false}');


        $ch = curl_init('https://content.dropboxapi.com/2/files/upload');
        curl_setopt($ch, CURLOPT_HTTPHEADER, $cheaders);
        curl_setopt($ch, CURLOPT_PUT, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch, CURLOPT_INFILE, $fp);
        curl_setopt($ch, CURLOPT_INFILESIZE, $size);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);

        curl_close($ch);
        fclose($fp);

        /* Fill in the log table */
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        $tablename = $wpdb->prefix. "ev_" . "backup_logs";

        $wpdb->insert(
            $tablename,
            array(
                'name' => substr($name, strpos($name, '/') + 1 ),
                'size' => $size,
                'date' => date('Y-m-d h:i:s'),
                'path' => $folder_path,
            )
        );


        return $response;
    }

    //Send file to dropbox
    //file size <2Tb
    public function SendLargeFile($access_token, $name, $fp, $size) {
        $chunk_size = 8 * 1024 * 1024; // 8MB
        $upload_session_id = null;
        $offset = 0;

        while (!feof($fp)) {
            $chunk = fread($fp, $chunk_size);
            $is_last = feof($fp);

            if ($upload_session_id === null) {
                // Start session
                $ch = curl_init('https://content.dropboxapi.com/2/files/upload_session/start');
                $headers = [
                    'Authorization: Bearer ' . $access_token,
                    'Content-Type: application/octet-stream',
                    'Dropbox-API-Arg: {"close": false}'
                ];
                curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $chunk);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

                $response = curl_exec($ch);
                curl_close($ch);

                $data = json_decode($response, true);
                if (isset($data['session_id'])) {
                    $upload_session_id = $data['session_id'];
                    $offset += strlen($chunk);
                } else {
                    return $response; // Error starting session
                }
            } else {
                if ($is_last) {
                    // Finish upload session
                    $ch = curl_init('https://content.dropboxapi.com/2/files/upload_session/finish');

                    $arg = json_encode([
                        "cursor" => [
                            "session_id" => $upload_session_id,
                            "offset" => $offset
                        ],
                        "commit" => [
                            "path" => "/Secondary Backups/" . $name,
                            "mode" => "add",
                            "autorename" => true,
                            "mute" => false
                        ]
                    ]);

                    $headers = [
                        'Authorization: Bearer ' . $access_token,
                        'Content-Type: application/octet-stream',
                        'Dropbox-API-Arg: ' . $arg
                    ];
                    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                    curl_setopt($ch, CURLOPT_POST, true);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $chunk);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

                    $response = curl_exec($ch);
                    curl_close($ch);
                    break;
                } else {
                    // Append chunk
                    $ch = curl_init('https://content.dropboxapi.com/2/files/upload_session/append_v2');

                    $arg = json_encode([
                        "cursor" => [
                            "session_id" => $upload_session_id,
                            "offset" => $offset
                        ],
                        "close" => false
                    ]);

                    $headers = [
                        'Authorization: Bearer ' . $access_token,
                        'Content-Type: application/octet-stream',
                        'Dropbox-API-Arg: ' . $arg
                    ];
                    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                    curl_setopt($ch, CURLOPT_POST, true);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $chunk);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

                    curl_exec($ch); // No response on success
                    curl_close($ch);
                    $offset += strlen($chunk);
                }
            }
        }

        fclose($fp);

        /* Fill in the log table */
        global $wpdb;
        $tablename = $wpdb->prefix . "ev_backup_logs";

        $wpdb->insert(
            $tablename,
            [
                'name' => substr($name, strpos($name, '/') + 1),
                'size' => $size,
                'date' => date('Y-m-d h:i:s'),
                'path' => "/Secondary Backups/" . $name,
            ]
        );

        return $response;
    }

    public function moveFile($access_token, $from_path, $to_path) {
        $ch = curl_init("https://api.dropboxapi.com/2/files/move_v2");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);

        $post_fields = json_encode([
            "from_path" => $from_path,
            "to_path" => $to_path,
            "autorename" => true
        ]);

        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_fields);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer " . $access_token,
            "Content-Type: application/json"
        ]);

        $response = curl_exec($ch);
        curl_close($ch);

        return json_decode($response, true);
    }

    public function getOrCreateSharedLinkForFolder($access_token, $folder_path) {
        // Try to get an existing link
        $url = "https://api.dropboxapi.com/2/sharing/list_shared_links";
        $headers = [
            'Authorization: Bearer ' . $access_token,
            'Content-Type: application/json'
        ];

        $data = [
            "path" => $folder_path,
            "direct_only" => true
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        $response = curl_exec($ch);
        curl_close($ch);

        $result = json_decode($response, true);

        if (!empty($result['links']) && isset($result['links'][0]['url'])) {
            return $result['links'][0]['url'];
        }

        // If there is no link, create a new one
        $create_url = "https://api.dropboxapi.com/2/sharing/create_shared_link_with_settings";
        $create_data = [
            "path" => $folder_path,
            "settings" => ["requested_visibility" => "public"]
        ];

        $ch = curl_init($create_url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($create_data));
        $create_response = curl_exec($ch);
        curl_close($ch);

        $create_result = json_decode($create_response, true);

        if (isset($create_result['url'])) {
            return $create_result['url'];
        } else {
            error_log('Dropbox: Error creating a link to a folder: ' . json_encode($create_result));
            return false;
        }
    }


    public function getOrCreateSharedLinkForFile($access_token, $file_path) {
        $url = "https://api.dropboxapi.com/2/sharing/list_shared_links";
        $headers = [
            'Authorization: Bearer ' . $access_token,
            'Content-Type: application/json'
        ];

        $data = [
            "path" => $file_path,
            "direct_only" => true
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        $response = curl_exec($ch);
        curl_close($ch);

        $result = json_decode($response, true);

        if (!empty($result['links']) && isset($result['links'][0]['url'])) {
            return str_replace('?dl=0', '?dl=1', $result['links'][0]['url']);
        }

        $create_url = "https://api.dropboxapi.com/2/sharing/create_shared_link_with_settings";
        $create_data = [
            "path" => $file_path,
            "settings" => ["requested_visibility" => "public"]
        ];

        $ch = curl_init($create_url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($create_data));
        $create_response = curl_exec($ch);
        curl_close($ch);

        $create_result = json_decode($create_response, true);

        return isset($create_result['url']) ? str_replace('?dl=0', '?dl=1', $create_result['url']) : false;
    }



}