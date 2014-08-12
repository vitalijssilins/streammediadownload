<?php 

// Autoload files using Composer autoload
require_once __DIR__ . '/../vendor/autoload.php';

use StreamMediaDownload\MixCloudAdapter;

$mixCloudAdapter     = new MixCloudAdapter();

/*
 *  Example of url for mixcloud:
 *  http://www.mixcloud.com/FACTMixArchive/fact-mix-455-airhead/
 */

$songUrl  = 'http://www.mixcloud.com/FACTMixArchive/fact-mix-455-airhead/';

try {
    $songInfo  = $mixCloudAdapter->getSongInfo($songUrl);
    $songFile  = $mixCloudAdapter->downloadSong($songUrl);
    if (!$songFile) {
        die('It`s not possible to get stream link for this song. Are you sure, that it is the right link: '.$songUrl.' ?');
    }
} catch (Exception $e) {
    die($e->getMessage());
}

echo '<a href="'.$songFile.'" target="_blank">Download song from stream</a>';
