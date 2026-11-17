<?php

/**
 * @package         Regular Labs Library
 * @version         26.10.25669
 * 
 * @author          Peter van Westen <info@regularlabs.com>
 * @link            https://regularlabs.com
 * @copyright       Copyright © 2026 Regular Labs All Rights Reserved
 * @license         GNU General Public License version 2 or later
 */
namespace RegularLabs\Library;

defined('_JEXEC') or die;
use SimpleXMLElement;
use Throwable;
class Xml
{
    /**
     * Get an object filled with data from an xml file
     */
    public static function toObject(string $url, string $root = ''): object
    {
        $cache = new \RegularLabs\Library\Cache(['xml.to_object', md5($url), $root]);
        if ($cache->exists()) {
            return $cache->get();
        }
        try {
            if (!str_contains($url, '<') && file_exists($url)) {
                $xml = @new SimpleXMLElement($url, \LIBXML_NONET | \LIBXML_NOCDATA, 1);
            } else {
                $xml = @simplexml_load_string($url, "SimpleXMLElement", \LIBXML_NONET | \LIBXML_NOCDATA);
            }
        } catch (Throwable) {
            return $cache->set((object) []);
        }
        if (!$xml instanceof SimpleXMLElement || count($xml) === 0) {
            return $cache->set((object) []);
        }
        if ($root) {
            if (!isset($xml->{$root})) {
                return $cache->set((object) []);
            }
            $xml = $xml->{$root};
        }
        $json = json_encode($xml);
        $xml = \RegularLabs\Library\Json::toObject($json, (object) []);
        if ($root && isset($xml->{$root})) {
            $xml = $xml->{$root};
        }
        return $cache->set($xml);
    }
}
