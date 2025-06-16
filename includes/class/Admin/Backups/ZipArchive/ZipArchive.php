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

    if (is_dir($source) === true){
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source), RecursiveIteratorIterator::SELF_FIRST);

        foreach ($files as $file){
            $file = str_replace('\\', '/', $file);

            // Ignore "." and ".." folders
            if( in_array(substr($file, strrpos($file, '/')+1), array('.', '..')) )
                continue;

            $file = realpath($file);
            $file = str_replace('\\', '/', $file);

        if((stripos($file, 'backups') === false) && (stripos($file, '.git') === false) && (stripos($file, '.idea') === false) /*&& (stripos($file, '.sql') === false)*/){
                //$log = date('Y-m-d H:i:s') . ' log time';
                //file_put_contents(__DIR__ . '/log.txt', stripos($file, 'uploads').' '.$file.' '.$log . PHP_EOL, FILE_APPEND);

            if (is_dir($file) === true) {
                $zip->addEmptyDir(str_replace($source . '/', '', $file . '/'));
            }elseif (is_file($file) === true){
                $zip->addFromString(str_replace($source . '/', '', $file), file_get_contents($file));
            }
          }


        }
    }else if (is_file($source) === true){
        $zip->addFromString(basename($source), file_get_contents($source));
    }
    return $zip->close();
}