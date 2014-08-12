<?php 

// Autoload files using Composer autoload
require_once __DIR__ . '/../vendor/autoload.php';

use StreamMediaDownload\GrooveSharkAdapter;

$grooveSharkAdapter     = new GrooveSharkAdapter();

/*
 *  Example of url for grooveshark: 
 *  http://grooveshark.com/#!/s/Happy+From+Despicable+Me+2/6VAueI?src=5
 */

$songUrl  = 'http://grooveshark.com/#!/s/Happy/6JlIMI?src=5';
$fileName = explode('/', $songUrl)[5]. ".mp3";

try {
    $songFile  = $grooveSharkAdapter->downloadSong($songUrl);
    $songInfo  = $grooveSharkAdapter->getSongInfo($songUrl);
    if (!$songFile) {
        die('It`s not possible to get stream link for this song. Are you sure, that it is the right link: '.$songUrl.' ?');
    }
} catch (Exception $e) {
    die($e->getMessage());
}

header("Content-Type: audio/mpeg");
header("Content-Disposition: attachment; filename=$fileName");
echo $songFile;
