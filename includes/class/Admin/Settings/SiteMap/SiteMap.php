<?php
require  'vendor/autoload.php';
use Google\Spreadsheet\DefaultServiceRequest;
use Google\Spreadsheet\ServiceRequestFactory;

use EVN\Helpers\Encryption;

$path = get_option('ev_google_service_key');
//decrypt url to json file
if (!empty($path) && is_string($path)) {
    $path = json_decode($path, true);
}
$ecp_url = Encryption::decrypt($path['EVN_GOOGLE_SERVICE_KEY']);
putenv('GOOGLE_APPLICATION_CREDENTIALS=' . $ecp_url);

/**Method for create a google sheets with links to folder with backups*/
function sendtoGoogleUrls($site, $url) {
    $client = new Google_Client();
    $client->useApplicationDefaultCredentials();
    $client->addScope('https://www.googleapis.com/auth/spreadsheets');

    $service = new Google_Service_Sheets($client);
    $spreadsheetId = '1HzlLxrFIQuR3UScYfe1aNgWd_x2YPF_v_XRFUIYayT4';
    $sheetName = 'Installs';

    //Checking and adding headers
    $headerRange = $sheetName . '!A1:B1';
    $response = $service->spreadsheets_values->get($spreadsheetId, $headerRange);
    $headerValues = $response->getValues();

    $expectedHeaders = ['Site', 'URL'];
    if (empty($headerValues) || $headerValues[0] !== $expectedHeaders) {
        $headerRangeObject = new Google_Service_Sheets_ValueRange([
            'values' => [$expectedHeaders]
        ]);
        $service->spreadsheets_values->update(
            $spreadsheetId,
            $headerRange,
            $headerRangeObject,
            ['valueInputOption' => 'RAW']
        );
    }

    //Getting all the rows (to check for duplicates)
    $dataRange = $sheetName . '!A2:B'; //A2 — skip the title
    $existingResponse = $service->spreadsheets_values->get($spreadsheetId, $dataRange);
    $existingRows = $existingResponse->getValues();

    foreach ($existingRows as $row) {
        if (isset($row[0], $row[1]) && $row[0] === $site && $row[1] === $url) {
            //There is already such an entry — do not add it
            return;
        }
    }

    //Adding a new line
    $valueRange = new Google_Service_Sheets_ValueRange([
        'values' => [[$site, $url]]
    ]);

    $service->spreadsheets_values->append(
        $spreadsheetId,
        $sheetName . '!A:B',
        $valueRange,
        [
            'valueInputOption' => 'USER_ENTERED',
            'insertDataOption' => 'INSERT_ROWS'
        ]
    );
}


/**Method for create a google sheets*/
function sendtoGoogle() {
    $arr = array();
    $i=1;
    $pages = get_pages();
    foreach( $pages as $page ){
        $arr[$i]['url'] = get_page_link($page->ID);
        $arr[$i]['title'] = $page->post_title;
        $arr[$i]['description'] = get_post_meta($page->ID, 'seo_description', true );
        $arr[$i]['haveform'] = 'No'; //by default
        $i++;
    }

    $client = new Google_Client();
    $client->useApplicationDefaultCredentials();

    $client->addScope('https://www.googleapis.com/auth/spreadsheets');

    $service = new Google_Service_Sheets($client);

    //Tables ID
    $spreadsheetId = '1HzlLxrFIQuR3UScYfe1aNgWd_x2YPF_v_XRFUIYayT4';

    $values = [
        ["URL", "Title", "Description", "Have form"],
    ];

    for ($i = 1; $i <= count($arr); $i++) {
        $values[$i] = [$arr[$i]['url'], $arr[$i]['title'], $arr[$i]['description'], $arr[$i]['haveform']];
    }

    $ValueRange = new Google_Service_Sheets_ValueRange();
    $ValueRange->setValues($values);
    $options = ['valueInputOption' => 'USER_ENTERED'];
    $service->spreadsheets_values->update($spreadsheetId, 'Installs', $ValueRange, $options);

}

/**Frontend part for generate sitemap table*/
function sitemaptable() {

echo '<table class="tg">
	    <tbody>
		   <tr>
			<th>URL</th>
			<th>Title</th>
			<th>Description</th>
			<th>Have form</th>
		   </tr>';
    $pages = get_pages();
    foreach( $pages as $page ){
        echo '<tr>
			<td><a href="'.get_page_link($page->ID).'">'.get_page_link($page->ID).'</a></td>
			<td>'.$page->post_title.'</td>
			<td>'.get_post_meta($page->ID, 'seo_description', true ).'</td>
			<td>No (still in developing)</td>
		</tr>';
    }

    echo '</tbody>
</table>';

}

/**Frontend part for generate sitemap tree*/
function sitemaptree() {
    echo "<ul class=\"treeCSS\">";
    //Array of pages
    echo "<li>";
    echo wp_list_pages();
    echo "</li>";
    //Array of posts
    //Displays a list of post types with a page in the front
    $post_types = get_post_types( [ 'publicly_queryable'=>1 ], 'objects');
    unset( $post_types['attachment'] ); // deletes attachment

    foreach ( $post_types as $post_type ) {
        echo '<li>'. $post_type->labels->name;
            outputPostsByType($post_type->name);
        echo "</li>";
    }
    echo "</ul>";

}

/**Shows all post with input type*/
function outputPostsByType($post_type) {
    global $post;

    $myposts = get_posts( [
        'post_type' => $post_type,
    ] );
    echo '<ul class="treeCSS">';
    foreach( $myposts as $post ){
        setup_postdata( $post );
        echo "<li>";
        ?>
            <a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
        <?php
        echo "</li>";
    }
    echo "</ul>";
    wp_reset_postdata();
}



/**
 * For recursive scanning of a folder.
 * Displays the hierarchy of child elements.
 */
function recursive($dir)
{
    // Open root directory
    $odir = opendir($dir);

    // Start recursive cicle
    echo "<ul class=\"treeCSS\">";
    while (($file = readdir($odir)) !== FALSE) {

        // Ignore "." and ".." files
        if ($file == '.' || $file == '..') {
            continue;
        }
        else {
            // If it's a file, include file into li tag
            echo "<li>";
            //it may be link for file
            //echo "<a href='".$dir.DIRECTORY_SEPARATOR.$file."'>".$file."</a>";
            echo $file;
        }

        // If currently - is directory, call function again
        if (is_dir($dir.DIRECTORY_SEPARATOR.$file)) {
                recursive($dir.DIRECTORY_SEPARATOR.$file);
        }
        echo "</li>";
    }
    echo "</ul>";
    closedir($odir);
}


/**Backend part for generate xml sitemap*/
function sitemap_generator($name, $urls) {

    $xmlString = '<?xml version="1.0" encoding="UTF-8"?>
    <urlset
      xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
      xmlns:image="http://www.google.com/schemas/sitemap-image/1.1"
      xsi:schemaLocation="http://www.sitemaps.org/schemas/sitemap/0.9 http://www.sitemaps.org/schemas/sitemap/0.9/sitemap.xsd"
      xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';

    foreach ($urls as $key => $url) {
        $xmlString .=  '<url><loc>'.$url.'</loc></url>';
    }

    $xmlString .= '</urlset>';

    $dom = new DOMDocument;
    $dom->preserveWhiteSpace = false;
    $dom->loadXML($xmlString);
    $dom->save("../$name.xml");

    return "$name.xml";
}

function main_sitemap_generator($sitemaps) {

    $xmlString = '<?xml version="1.0" encoding="UTF-8"?>
    <sitemapindex
      xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"
      xmlns:mobile="http://www.google.com/schemas/sitemap-mobile/1.0"
      xmlns:xhtml="http://www.w3.org/1999/xhtml"
      xmlns:video="http://www.google.com/schemas/sitemap-video/1.1"
      xmlns:news="http://www.google.com/schemas/sitemap-news/0.9">';

    foreach ($sitemaps as $key => $sitemap) {
        $xmlString .=  '<sitemap><loc>'.$sitemap.'</loc><lastmod>'.date( 'c').'</lastmod></sitemap>';
    }

    $xmlString .= '</sitemapindex>';


    $dom = new DOMDocument;
    $dom->preserveWhiteSpace = false;
    $dom->loadXML($xmlString);
    $dom->save("../sitemap.xml");
}


?>