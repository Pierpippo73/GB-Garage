<?php

/**
 * @package         Regular Labs Library
 * @version         26.11.17024
 * 
 * @author          Peter van Westen <info@regularlabs.com>
 * @link            https://regularlabs.com
 * @copyright       Copyright © 2026 Regular Labs All Rights Reserved
 * @license         GNU General Public License version 2 or later
 */
namespace RegularLabs\Library;

defined('_JEXEC') or die;
class EncodedPayload
{
    public static function decodeCompressed(string $string, string $default = '', bool $urlDecode = \true): string
    {
        if ($urlDecode) {
            $string = urldecode($string);
        }
        $string = \RegularLabs\Library\Base64::decode($string, null);
        if (is_null($string)) {
            return $default;
        }
        $deflated = @gzinflate($string);
        return $deflated === \false ? $string : $deflated;
    }
    public static function decodeRequestChunks(string $prefix, int $count = 10): string
    {
        $encoded = '';
        for ($i = 0; $i < $count; $i++) {
            $encoded .= \RegularLabs\Library\Input::getString($prefix . $i, '');
        }
        return \RegularLabs\Library\Base64::decode($encoded, '') ?? '';
    }
    public static function encodeCompressed(string $string, bool $urlEncode = \true): string
    {
        $string = \RegularLabs\Library\Base64::encode(gzdeflate($string));
        if ($urlEncode) {
            $string = urlencode($string);
        }
        return $string;
    }
}
