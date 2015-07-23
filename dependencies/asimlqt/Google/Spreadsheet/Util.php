<?php
/**
 * Copyright 2013 Asim Liaquat
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *    http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */
namespace Google\Spreadsheet;

use SimpleXMLElement;

/**
 * Utility class. Provides several methods which are common to multiple classes.
 *
 * @package    Google
 * @subpackage Spreadsheet
 * @author     Asim Liaquat <asimlqt22@gmail.com>
 */
class Util
{
    /**
     * Extracts the endpoint from a full google spreadsheet url.
     * 
     * @param string $url
     * 
     * @return string
     */
    public static function extractEndpoint($url)
    {
        return parse_url($url, PHP_URL_PATH);
    }

    /**
     * Extracts the href for a specific rel from an xml object.
     * 
     * @param  \SimpleXMLElement $xml
     * @param  string            $rel the value of the rel attribute whose href you want
     * 
     * @return string
     */
    public static function getLinkHref(SimpleXMLElement $xml, $rel)
    {
        foreach($xml->link as $link) {
            $attributes = $link->attributes();
            if($attributes['rel']->__toString() === $rel) {
                return $attributes['href']->__toString();
            }
        }
        throw new Exception('No link found with rel "'.$rel.'"');
    }

    # added by @aravindanve
    # for sheets 3.0

    # feed hrefs have been moved from link array to content array

    # old response
    
    /*
    ["link"]=>
    array(3) {
    [0]=> object(SimpleXMLElement)#19 (1) {
        ["@attributes"]=>
        array(3) {
            ["rel"]=>
            string(58) "http://schemas.google.com/spreadsheets/2006#worksheetsfeed"
            ["type"]=>
            string(20) "application/atom+xml"
            ["href"]=>
            string(106) "https://spreadsheets.google.com/feeds/worksheets/1ryPiOjpvA04YukeTB_-Kx8l4EKzAfQNux3K7jxm0MME/private/full"
        }
    }   */

    # new response

    /*
    ["content"]=>
    object(SimpleXMLElement)#19 (1) {
        ["@attributes"]=>
        array(2) {
            ["type"]=>
            string(30) "application/atom+xml;type=feed"
            ["src"]=>
            string(106) "https://spreadsheets.google.com/feeds/worksheets/1ryPiOjpvA04YukeTB_-Kx8l4EKzAfQNux3K7jxm0MME/private/full"
        }
    }   */

    public static function getContentHref(
        SimpleXMLElement $xml, 
        $type = 'application/atom+xml;type=feed')
    {
        foreach($xml->content as $content) 
        {
            $attributes = $content->attributes();

            if($attributes['type']->__toString() === $type) 
            {
                return $attributes['src']->__toString();
            }
        }
        
        throw new Exception('No content found with type "'
            .$type.'"');
    }

}