<?php

function Zip($source, $destination){
    if (!extension_loaded('zip') || !file_exists($source)) {
        return false;
    }

    $zip = new ZipArchive();
    if (!$zip->open($destination, ZIPARCHIVE::CREATE)) {
        return false;
    }

    $source = str_replace('\\', '/', realpath($source));
    error_log("Zip Archive 14 - source: " . $source);

    if (is_dir($source) === true){
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source), RecursiveIteratorIterator::SELF_FIRST);

        foreach ($files as $file){
            $file = str_replace('\\', '/', $file);

            error_log("Zip Archive 22 - file: " . $file);

            // Ignore "." and ".." folders
            if( in_array(substr($file, strrpos($file, '/')+1), array('.', '..')) )
                continue;

            $file = realpath($file);
            $file = str_replace('\\', '/', $file);

            error_log("Zip Archive 31 - file: " . $file);

            if((stripos($file, 'backups') === false) && (stripos($file, '.git') === false) && (stripos($file, '.idea') === false) /*&& (stripos($file, '.sql') === false)*/){
                //$log = date('Y-m-d H:i:s') . ' log time';
                //file_put_contents(__DIR__ . '/log.txt', stripos($file, 'uploads').' '.$file.' '.$log . PHP_EOL, FILE_APPEND);

                if (is_dir($file) === true) {
                    $zip->addEmptyDir(str_replace($source . '/', '', $file . '/'));
                    error_log("Zip Archive 39 - add dir");
                }elseif (is_file($file) === true){
                    if (stripos($file, '.sql') !== false) {
                        $zip->addFile($file, str_replace($source . '/', '', $file));
                        error_log("Zip Archive 43 - add file");
                    } else {
                        $zip->addFromString(str_replace($source . '/', '', $file), file_get_contents($file));
                        error_log("Zip Archive 45 - add from string");
                    }
                }
            }


        }
    }else if (is_file($source) === true){
        $zip->addFromString(basename($source), file_get_contents($source));
        error_log("Zip Archive 50 - add from string");
    }
    return $zip->close();
}