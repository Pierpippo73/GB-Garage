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
class Json
{
    public static function decode(?string $json, mixed $default = null, bool $associative = \false, bool $throw = \false, int $flags = 0): mixed
    {
        if (is_null($json) || $json === '') {
            return $default;
        }
        $decoded = json_decode($json, $associative, 512, $throw ? $flags | \JSON_THROW_ON_ERROR : $flags);
        if (!$throw && json_last_error() !== \JSON_ERROR_NONE) {
            return $default;
        }
        return $decoded;
    }
    public static function encodeForScript(mixed $value): string
    {
        if (is_array($value)) {
            $values = [];
            foreach ($value as $item) {
                $values[] = self::encodeScriptValue($item);
            }
            return implode(', ', $values);
        }
        return self::encodeScriptValue($value);
    }
    public static function toArray(?string $json, array $default = [], bool $throw = \false, int $flags = 0): array
    {
        $decoded = self::decode($json, $default, \true, $throw, $flags);
        return is_array($decoded) ? $decoded : $default;
    }
    public static function toObject(?string $json, ?object $default = null, bool $throw = \false, int $flags = 0): ?object
    {
        $decoded = self::decode($json, $default, \false, $throw, $flags);
        return is_object($decoded) ? $decoded : $default;
    }
    private static function encodeScriptValue(mixed $value): string
    {
        return json_encode($value, \JSON_HEX_TAG | \JSON_HEX_APOS | \JSON_HEX_QUOT | \JSON_HEX_AMP);
    }
}
