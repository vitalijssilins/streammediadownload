<?php 

// Autoload files using Composer autoload
require_once __DIR__ . '/../vendor/autoload.php';

use StreamMediaDownload\SoundCloudAdapter;

/*
 * For faster performance it is recommended to obtain Soundcloud API keys.
 * Insert your API keys from soundcloud here.
 * You can obtain keys from here: http://soundcloud.com/you/apps/new (you should be registered on soundcloud)
 * if you don`t want to use API, leave parameters empty
 */
$soundcloudClientID        = '';
$soundcloudClientSecretID  = '';

$soundCloudAdapter     = new SoundCloudAdapter($soundcloudClientID, $soundcloudClientSecretID);

/*
 *  Examples of urls for soundcloud
 *  downloadable through API: https://soundcloud.com/flume/seekae-test-recognise-flume-re-work
 *  non-downloadable through API: https://soundcloud.com/mholok/mariposa-groove-footprints
 */

$songUrl  = 'https://soundcloud.com/flume/seekae-test-recognise-flume-re-work';
$fileName = explode('/', $songUrl)[4]. ".mp3";

try {
    $songInfo  = $soundCloudAdapter->getSongInfo($songUrl);
    $songFile  = $soundCloudAdapter->downloadSong($songUrl);
    if (!$songFile) {
        die('It`s not possible to get stream link for this song. Are you sure, that it is the right link: '.$songUrl.' ?');
    }
} catch (Exception $e) {
    die($e->getMessage());
}

header("Content-Type: audio/mpeg");
header("Content-Disposition: attachment; filename=$fileName");
echo $songFile;
