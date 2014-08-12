<?php

/**
 * Abstract adapter for Stream Media Download.
 * It is intended that all other Adapters will extend this class.
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
 * Abstract class to provide interface for other Stream download Adapters. 
 * Additional helper methods included.
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
 * @todo       Make better HTTP class to use not only curl
 */
abstract class AbstractAdapter
{
    /* search/hack stream url & download song from stream */
    abstract public function downloadSong($url);
    /* song info */
    abstract public function getSongInfo($url);
    /* validates url */
    abstract protected function validateUrl($url);

    protected function curlGet($url, $postFields = false, $form = false)
    {
        $ch = curl_init($url);
        $headers = array(
            "User-Agent: Mozilla/5.0 (X11; Linux x86_64)",
            "Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8",
            "Accept-Language: en-us,en;q=0.5",
            'Accept-Charset: ISO-8859-1,utf-8;q=0.7,*;q=0.7',
            'Pragma: no-cache',
            'Cache-Control: no-cache'
        );

        if (!$form) {
            $headers[] = "Content-Type: application/json; charset=utf-8";
        }
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 120);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

        if ($postFields) {
            curl_setopt($ch, CURLOPT_POST, count($postFields));
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
        }

        $response = curl_exec($ch);
        $response_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $info = curl_getinfo($ch);

        if ($response_code != 200 && $response_code != 302 && $response_code != 304) {
            $response = false;
        }
        return $response;
    }
}
