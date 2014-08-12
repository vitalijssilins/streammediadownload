<?php

/**
 * GrooveShark.com adapter for Stream Media Download
 *
 * PHP version 5
 *
 * Stream Media Download is free software: you can redistribute it and/or modify it under the
 * terms of the GNU Lesser General Public License as published by the Free
 * Software Foundation, either version 3 of the License, or (at your option)
 * any later version.
 *
 * Stream Media Download is distributed in the hope that it will be useful, but WITHOUT ANY
 * WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS
 * FOR A PARTICULAR PURPOSE. See the GNU Lesser General Public License for more
 * details.
 *
 * You should have received a copy of the GNU Lesser General Public License
 * along with Stream Media Download. If not, see <http://www.gnu.org/licenses/>.
 *
 * @author Vitalijs Silins <silins@silins.lv>
 */

namespace StreamMediaDownload;

/**
 * Class for working with GrooveShark.com undocumented public API
 *
 *
 * @package    StreamMediaDownload
 * @subpackage StreamMediaAdapter
 * @author     Vitalijs Silins <silins@silins.lv>
 * @copyright  2014 Vitalijs Silins
 * @license    http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 * @version    GIT: $Id$
 * @link       https://github.com/vitalijssilins/streammediadownload
 * @todo       Implement logging interface
 */
class GrooveSharkAdapter extends AbstractAdapter
{

    private $randomizer;
    private $phpsession;
    private $uuid;
    private $country;
    private $communicationToken;
    private $songInfo;
    private $client = 'htmlshark';
    private $clientRevision = '20130520';
    // token for communication from main app.js file
    private $revToken = 'nuggetsOfBaller';
    // another token for communication from audio player flash file
    private $flashToken = 'orderMoreCheezPlz';
    private $sesionDomain = 'http://html5.grooveshark.com';

    public function __construct()
    {
        if (!$this->phpsession) {
            if (!preg_match('/PHPSESSID\=(.*?)\;\ /', get_headers($this->sesionDomain)[6], $phpSessionID)) {
                throw new \Exception('no session id (token)');
            }
            $this->phpsession = $phpSessionID[1];
        }
        // contents of this variable is not important, but it is needed for communication with public API
        $this->country = array(
            'ID'=> 1,
            'CC1'=> 0,
            'CC2'=> 0,
            'CC3'=> 4,
            'CC4'=> 0,
            'DMA'=> 0,
            'iso'=> 'US',
            'region'=> '12',
            'city'=> 'Miami',
            'zip'=> '',
            'IPR'=> 0);
    }

    public function getSongInfo($url)
    {
        return $this->songInfo;
    }

    protected function validateUrl($url)
    {
        $valid_url_pattern = '%\b(?:(?:http|https)://|www\.)grooveshark\.[a-z]{2,6}(?::\d{1,5}+)?(?:/[!$\'()*+,._a-z-]++){0,10}(?:/[!$\'()*+,._a-z-]+)?(?:\?[!$&\'()*+,.=_a-z-]*)?%i';

        if (!preg_match($valid_url_pattern, $url)) {
            throw new \Exception('Invalid url');
        }
        return true;
    }

    public function downloadSong($songUrl)
    {
        $this->validateUrl($songUrl);

        $protocolStartTime = str_replace('.', '', microtime(true));
        $slicedUrl = explode('/', $songUrl);
        $hash = '/'.$slicedUrl[4].'/'.$slicedUrl[5].'/'.substr($slicedUrl[6], 0, 6);
        $songToken = substr($slicedUrl[6], 0, 6);

        // trying to get stream key from preload script - this works only 4-5 times using one IP every ~30 min
        // @todo: make automatic proxy list download and substitute to preload context - as it is the fastest method right now (in terms of operation count)
        $preloadInfo = $this->curlGet('http://grooveshark.com/preload.php?hash='.urlencode($hash).'%3Fsrc%3D5&'.$protocolStartTime);

        // getting all info from preload to get working stream
        if (preg_match('/streamKey\"\:\"([a-z0-9\_]*?)\"\,/', $preloadInfo, $streamKey)) {
            if (preg_match('/ip\"\:\"(.*?)\"\}\,/', $preloadInfo, $ipAddress)) {
                // gathering song stream
                return $this->curlGet('http://'.$ipAddress[1].'/stream.php', array('streamKey'=>$streamKey[1]), true);
            }
        }

        // If we got here, than we have reached IP limit for preload or GrooveShark have changed it`s formatting in preload answer
        // We are going the hard way - through "mimicking" Grooveshark protocol

        /* We need a communication token for other requests */
        $params['secretKey'] = md5($this->phpsession);
        $communicationToken =  json_decode($this->curlGet('https://grooveshark.com/more.php?getCommunicationToken', json_encode($this->preparePayload('getCommunicationToken', $params))));
        $this->communicationToken = $communicationToken->result;

        /* Getting info about song */
        unset($params);
        $params['token'] = $songToken;
        $params['country'] = $this->country;
        $songInfo = json_decode($this->curlGet('http://grooveshark.com/more.php?getSongFromToken', json_encode($this->preparePayload('getSongFromToken', $params))));

        /* It was the first API call, when we needed our "secret" token (revToken) - that is why we are testing if result is correct
         * Error code 256 == "Invalid token"
         * We try to obtain new tokens automatically and send another curl request.
         */
        if (isset($songInfo->fault) && (256 == $songInfo->fault->code)) {
            $this->renewCredentials();
            $songInfo = json_decode($this->curlGet('http://grooveshark.com/more.php?getSongFromToken', json_encode($this->preparePayload('getSongFromToken', $params))));
        }
        $this->songInfo = $songInfo;

        /* Now we are imitating flash based player. :) And using another token. */
        unset($params);
        $params['songID']   = $songInfo->result->SongID;
        $params['mobile']   = 'false';
        $params['prefetch'] = 'false';
        $params['country']  = $this->country;
        $this->client       = 'jsqueue';
        $streamInfo = json_decode($this->curlGet('http://grooveshark.com/more.php?getStreamKeyFromSongIDEx', json_encode($this->preparePayload('getStreamKeyFromSongIDEx', $params))));

        $streamUrl = 'http://'.$streamInfo->result->ip.'/stream.php?streamKey='.$streamInfo->result->streamKey;
        return $this->curlGet($streamUrl);
    }

    private function preparePayload($method, $additionalPayload)
    {
        $payload =
        array('header'=> array('client' => $this->client,
                         'clientRevision' => $this->clientRevision,
                         'privacy' => 0,
                         'country' => $this->country,
                         'session'=> $this->phpsession
                         ),
               'method' => $method,
               'parameters' => array()
        );

        $randString = $this->generateRandom();

        if (($this->communicationToken)) {
            $token = $randString.sha1(
                $method.":".
                $this->communicationToken.":".
                (('getStreamKeyFromSongIDEx' == $method)?($this->flashToken):($this->revToken)).":".
                $randString
            );
            $payload['header']['token'] = $token;
        }

        if (!$this->uuid) {
            $this->uuid = $this->GUID();
        }

        $payload['header']['uuid'] = $this->uuid;
        $payload['parameters'] = $additionalPayload;

        return $payload;
    }

    private function GUID()
    {
        if (function_exists('com_create_guid') === true) {
            return trim(com_create_guid(), '{}');
        }

        return sprintf('%04X%04X-%04X-%04X-%04X-%04X%04X%04X', mt_rand(0, 65535), mt_rand(0, 65535), mt_rand(0, 65535), mt_rand(16384, 20479), mt_rand(32768, 49151), mt_rand(0, 65535), mt_rand(0, 65535), mt_rand(0, 65535));
    }

    private function generateRandom()
    {
        // This is just a random string, but make sure we never send the same token twice in a row.
        $rand = sprintf("%06x", mt_rand(0, 0xffffff));
        if ($rand !== $this->randomizer) {
            $this->randomizer = $rand;
            return $rand;
        } else {
            return $this->generateRandom();
        }
    }

    /**
      * This function is needed if we have to renew credentials for our class - when GrooveShark updates it`s API keys .
      * You will need to install swftools on Linux to get this working well (to get token from Flash Player).
      */
    private function renewCredentials()
    {
        $firstPageData = $this->curlGet('http://grooveshark.com');

        preg_match("/app\_(?<jsVersion>\d+)/", $firstPageData, $matches);
        $jsVersion = $matches['jsVersion'];

        preg_match("/JSQueue\_(?<flashVersion>\d+)/", $firstPageData, $matches);
        $flashVersion = $matches['flashVersion'];

        $filename = "JSQueue.swf";
        $url = "http://grooveshark.com/static/JSQueue_".$flashVersion.".swf";
        $js_file = "http://static-a.gs-cdn.net/static/app_".$jsVersion.".js";

        /* We are adding @ - because it is very possible, that this directory will not be writable
         * and we don`t want to get errors in our resulting MP3
         * If you consider running automatic service - better change file saving location.
         */
        @file_put_contents($filename, $this->curlGet($url));

        $dump = `swfdump -a $filename 2>&1 | grep "findpropstrict <q>\[public\]com.grooveshark.jsQueue::Service$" -B 5 `;
        @unlink($filename);

        if (preg_match('/pushstring "(.*?)"/s', $dump, $matches)) {
            $this->flashToken = end($matches);
        }

        $data = $this->curlGet($js_file);
        preg_match("/z\=\"(?<revToken>\w+).*?nt=\"(?<client>\w+).*?rt=\"(?<clientRevision>\d+)/", $data, $matches);

        $this->client = $matches['client'];
        $this->clientRevision = $matches['clientRevision'];
        $this->revToken = $matches['revToken'];
    }
}
