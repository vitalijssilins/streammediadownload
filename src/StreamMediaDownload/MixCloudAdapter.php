<?php

/**
 * MixCloud.com adapter for Stream Media Download
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
 * Class for working with MixCloud.com parsed pages to get stream media
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
class MixCloudAdapter extends AbstractAdapter
{
    const FIRST_SERVER = 13, LAST_SERVER = 22;

    private $songInfo;

    /*
     * Mixcloud has oembed - we are using it here to get info about the song
     */
    public function getSongInfo($url)
    {
        $this->validateUrl($url);

        return json_decode($this->curlGet('http://www.mixcloud.com/oembed/?url='.$url.'&format=json'));
    }

    protected function validateUrl($url)
    {
        $valid_url_pattern = '%\b(?:(?:http|https)://|www\.)[\mixcloud]+\.[a-z]{2,6}(?::\d{1,5}+)?(?:/[!$\'()*+,._a-z-]++){0,10}(?:/[!$\'()*+,._a-z-]+)?(?:\?[!$&\'()*+,.=_a-z-]*)?%i';

        if (!preg_match($valid_url_pattern, $url)) {
            throw new \Exception('Invalid url');
        }
        return true;
    }

    public function downloadSong($songUrl)
    {
        $this->validateUrl($songUrl);

        $songHtml = $this->curlGet($songUrl);
        preg_match("/m-preview=\"(?<previewUrl>[^\"]*)\"/", $songHtml, $matches);
        $originalUrl = preg_replace('/stream[0-9][0-9]\.mixcloud\.com\/previews/', 'stream'.MixCloudAdapter::FIRST_SERVER.'.mixcloud.com/c/originals', $matches['previewUrl']);

        // We are testing first server. If song is there, we should get stream. 90% of songs are on this server.
        if (strpos(get_headers($originalUrl)[0], '200') === false) {
            // As we did not got a positive result, we are looping through all possible servers. Stop on first.
            for ($i = MixCloudAdapter::FIRST_SERVER+1; $i < MixCloudAdapter::LAST_SERVER; $i++) {
                $testUrl  = str_replace('stream'. MixCloudAdapter::FIRST_SERVER, 'stream'. $i, $originalUrl);
                $testUrl2 = preg_replace('/stream[0-9]+\.mixcloud\.com\/c\/originals(.*)\.mp3/', 'stream'.$i.'.mixcloud.com/c/m4a/64\1.m4a', $originalUrl);

                // we test url with changed subdomain
                if (strpos(get_headers($testUrl)[0], '200') !== false) {
                    $originalUrl = $testUrl;
                    break;
                } elseif (strpos(get_headers($testUrl2)[0], '200') !== false) { // we test url with changed file format
                    $originalUrl = $testUrl2;
                    break;
                }
            }
        }

        return $originalUrl;
    }
}
