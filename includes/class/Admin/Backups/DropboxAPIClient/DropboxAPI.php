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



}