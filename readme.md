Documentation to plugin

```
Files structure
├─── assets
│     └───  css
│             ├─── backups_tabs_styles.css
│             ├─── settings_tabs_styles.css
│             └─── style.cs
│
├─── includes
│     └─── class
│             └─── Admin
│                      ├─── Backups
│                      │       ├─── DropboxAPIClient
│                      │       │       └─── DropboxAPI.php
│                      │       ├─── SqlDump
│                      │       │       └─── MySql.php
│                      │       ├─── ZipArchive
│                      │       │       └─── ZipArchive.php
│                      │       └─── Backups.php
│                      └─── Settings
│                      │       ├─── SiteMap
│                      │       ├─── SVG
│                      │       │       └─── AllowSVGUpload.php
│                      │       └─── Settings.php
│                      └─── EverneuControlPlugin.php
│   
├─── evernue-control.php
└─── readme.md
```

For developers

<h2>Before install plugin</h2>

**1 step:** Create the DropBox application by link [here](https://www.dropbox.com/developers/).

**2 step:** Click on ```App Console```.

**3 step:** Create a new app or use an existing app. Make sure the app has ```files.content.write``` checked in the ```Permissions``` tab.

**4 step:** On the ```Settings``` tab copy App key (DROPBOX_APP_KEY), App secret (DROPBOX_APP_SECRET). 

**5 step:** Next, open a new browser window and put into address line following: https://www.dropbox.com/oauth2/authorize?token_access_type=offline&response_type=code&client_id=(App Key)
<br>Where "(App key)" is the one from 4th step. 
<br>Next the confirmation you will get a code (alphanumeric sequence). Copy that code and save.

**6 step:** Paste all saved keys in ```evernue-control.php``` as: 
```    
    $dropbox_settings = array(
        'app_key'       => '<DROPBOX_APP_KEY>',
        'app_secret'    => '<DROPBOX_APP_SECRET>',
        'access_code'   => '<SAVED_CODE_FROM_STEP_5>'
    );
```

**7 step:** Open the file ```/includes/class/Admin/Backups/Backups.php``` and then uncomment part of code to get <b>“Refresh Token”</b>.

**8 step:** Install the plugin on the site.

**9 step:**  Open the plugin menu
And click by button <b>“Create a backup”</b>. After click, you’ll see json array with <b>“Refresh Token”</b> value. 
Copy this value and save it as:
```    
    $dropbox_settings = array(
        'app_key'       => '<DROPBOX_APP_KEY>',
        'app_secret'    => '<DROPBOX_APP_SECRET>',
        'access_code'   => '<SAVED_CODE_FROM_STEP_5>',
        'refresh_token' => '<DISPLAYED_TOKEN_FROM_CURRENT_STEP>'
    );
```

**10 step:** Open the file ```/includes/class/Admin/Backups/Backups.php``` and then comment the part for getting <b>“Refresh Token”</b>.

**11 step:**  Deactivate and activate the plugin.
