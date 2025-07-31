<h1>Documentation to Everneu plugin</h1>

```
Files structure
├─── assets
│     └───  css
│             ├─── backups_tabs_styles.css
│             ├─── settings_tabs_styles.css
│             └─── style.css
│
├─── includes
│     └─── class
│             ├─── Admin
│             │        ├─── Backups
│             │        │       ├─── DropboxAPIClient
│             │        │       │       └─── DropboxAPI.php
│             │        │       ├─── SqlDump
│             │        │       │       └─── MySql.php
│             │        │       ├─── AutoBackupMaster.php
│             │        │       └─── Backups.php
│             │        └─── Settings
│             │                ├─── GoogleAPIKey
│             │                │       └─── KeyForm.php
│             │                ├─── SiteMap
│             │                │       ├─── vendor (Library for work with Google API)
│             │                │       ├─── composer.json
│             │                │       ├─── composer.lock
│             │                │       ├─── script.js
│             │                │       └─── SiteMap.php
│             │                ├─── SVG
│             │                │       └─── AllowSVGUpload.php
│             │                ├─── Updater
│             │                │       └─── UpdaterForm.php
│             │                └─── Settings.php
│             ├─── Cron
│             │        └─── BackupCronHandler.php
│             ├─── Helpers
│             │        ├─── CronInterval.php
│             │        ├─── Encryption.php
│             │        ├─── Environment.php
│             │        ├─── GitHubUpdater.php
│             │        └─── plugin-data-parser.php
│             ├─── Activator.php
│             └─── EverneuControlPlugin.php
│   
├─── evernue-control.php
└─── readme.md
```

For developers

<h2>After install plugin</h2>
Before plugin start working as well, you need to do the following steps.

<h3>Connect the plugin to DropBox API</h3>

**1 step:** Create the DropBox application by link [here](https://www.dropbox.com/developers/).

**2 step:** Click on ```App Console```.

**3 step:** Create a new app or use an existing app. Make sure the app has ```files.content.write``` checked in the ```Permissions``` tab.

**4 step:** On the ```Settings``` tab copy App key (DROPBOX_APP_KEY), App secret (DROPBOX_APP_SECRET). 

**5 step:** Next, open a new browser window and put into address line following: https://www.dropbox.com/oauth2/authorize?token_access_type=offline&response_type=code&client_id=<DROPBOX_APP_KEY>
<br>Where <DROPBOX_APP_KEY> is the one from 4th step. 
<br>Next the confirmation you will get a code (alphanumeric sequence) - <DROPBOX_ACCESS_CODE>. Copy that code and save.

**6 step:** Paste all saved keys to request as: 
```    
curl -X POST https://api.dropbox.com/oauth2/token \
  -u "<DROPBOX_APP_KEY>:<DROPBOX_APP_SECRET>" \
  -d "code=<DROPBOX_ACCESS_CODE>" \
  -d "grant_type=authorization_code" \
  -H "Content-Type: application/x-www-form-urlencoded"
```
and to procedures the command in the terminal.
Find the ```refresh_token``` value in the response array and save it.

**7 step:** Open the plugin  ```Backups > API keys``` and fill out the form with keys you got on previous steps.
Save them.


<h3>Connect the plugin to Google API (Google Sheets)</h3>

**1 step:** Go to [console.cloud.google.com](https://console.cloud.google.com/).

**2 step:** Select or create a project.

**3 step:** Go to ```APIs & Services > Credentials```.

**4 step:** Click ```Create Credentials > Service account```.

**5 step:** Name your account and click ```Continue```.

**6 step:** In the access step, select the Editor or Sheets API User role.

**7 step:** After creation, click on the ```account > Keys tab```.

**8 step:** Click ```Add Key > Create New Key > JSON```.

**9 step:** Download the .json file.

**10 step:** Upload .json file to form in the plugin ```Settings > Google API```.

**11 step:** (Optional: if need connection to new account) Give the service account access to the required Google Spreadsheet (via its "Share" — add email xxxx@project.iam.gserviceaccount.com as an Editor).


<h3>Connect the plugin to GitHub repo (get updates)</h3>

**1 step:** Go to your GitHub account.

**2 step:** Open tab ```Settings > ```.

**3 step:** Go to ```Developer settings > Personal access tokens > Fine-grained tokens```.

**4 step:** Click by ```Generate new token```.

**5 step:** Fill out the form and press ```Generate token```.

**6 step:** Copy and saved this token.

**7 step:** Open Everneu plugin settings.

**8 step:** Find an ```Updates``` tab, paste your token and save.

Here you go! Now the plugin is ready to creating backups, saving them into DropBox, taking updates and much more.