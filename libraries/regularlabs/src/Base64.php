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
class Base64
{
    public static function decode(?string $value, ?string $default = '', bool $strict = \true): ?string
    {
        if (is_null($value)) {
            return $default;
        }
        $decoded = base64_decode($value, $strict);
        return $decoded === \false ? $default : $decoded;
    }
    public static function encode(string $value): string
    {
        return base64_encode($value);
    }
}
