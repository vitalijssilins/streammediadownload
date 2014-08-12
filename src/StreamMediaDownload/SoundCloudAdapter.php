<?php

/**
 * SoundCloud.com adapter for Stream Media Download
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

use Soundcloud\Service;

/**
 * Class for working with Souncloud.com API and undocumented features to get stream media.
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
class SoundCloudAdapter extends AbstractAdapter
{
    private $soundcloudService;
    private $clientID;
    private $useApi = true;

    public function __construct($clientID = '', $secretID = '')
    {
        if (!empty($clientID) && !empty($secretID)) {
            $this->clientID = $clientID;
            $this->soundcloudService = new Service($clientID, $secretID);
        } else {
            $this->useApi = false;
        }
    }

    /**
     * Soundcloud has oembed - we are using it here to get info about the song
     */
    public function getSongInfo($url)
    {
        $this->validateUrl($url);

        return json_decode($this->curlGet('http://soundcloud.com/oembed?url='.$url.'&format=json'));
    }

    /**
     * Search/hack stream url 
     */
    public function downloadSong($url)
    {
        $this->validateUrl($url);

        if ($this->useApi) {
            try {
                $soundcloudResponse = $this->findTrackIDbyURL($url);

                // Is song file downloadable through API?
                if ($soundcloudResponse->downloadable) {
                    $streamUrl = $this->downloadFromApi($soundcloudResponse->id);
                // Is stream available from API?
                } elseif ($soundcloudResponse->streamable) {
                    $streamUrl = $this->curlGet($soundcloudResponse->stream_url.'?client_id=' . $this->clientID);
                }

                return $streamUrl;
            } catch (Exception $e) {
                // there was some problems with API, let`s try to get from Public stream
                // @todo logging interface
            }
        }

        // Stream of song can be downloaded through frontend (hack way)
        return $this->curlGet($this->getPublicStreamLink($url));
    }

    protected function validateUrl($url)
    {
        $valid_url_pattern = '%\b(?:(?:http|https)://|www\.)soundcloud\.[a-z]{2,6}(?::\d{1,5}+)?(?:/[!$\'()*+,._a-z-]++){0,10}(?:/[!$\'()*+,._a-z-]+)?(?:\?[!$&\'()*+,.=_a-z-]*)?%i';

        if (!preg_match($valid_url_pattern, $url)) {
            throw new \Exception('Invalid url');
        }
        return true;
    }

    private function findTrackIDbyURL($trackUrl)
    {
        $response = json_decode($this->soundcloudService->get('resolve', array('url' => $trackUrl)));
        return $response;
    }

    /**
     * Download track from API
     * Make API request to endpoint: curl "http://api.soundcloud.com/tracks/13158665.json?client_id=YOUR_CLIENT_ID"
     */
    private function downloadFromApi($trackID)
    {
        try {
            $track = $this->soundcloudService->download($trackID);
        } catch (Services_Soundcloud_Invalid_Http_Response_Code_Exception $e) {
            exit($e->getMessage());
        }
        return $track;
    }

    /**
     * Get stream URL from parsed public page
     */
    private function getPublicStreamLink($url)
    {
        $this->validateUrl($url);

        $url = str_replace("https", "http", $url);
        $publicHtml = $this->curlGet($url);

        if ($publicHtml !== false) {
            if (preg_match('/streamUrl\"\:\"(.*?)\"\,/', $publicHtml, $stream)) {
                return $stream[1];
            }
        }
        return false;
    }
}
