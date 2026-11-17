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
use RegularLabs\Scoped\DeepCopy\DeepCopy;
require_once dirname(__FILE__, 2) . '/vendor/autoload.php';
class ObjectHelper
{
    /**
     * Change the case of object keys
     * $key_format: 'camel', 'dash', 'dot', 'underscore'
     */
    public static function changeKeyCase(object|array|null $object, $format, bool $to_lowercase = \true): object
    {
        return (object) \RegularLabs\Library\ArrayHelper::applyMethodToKeys([$object, $format, $to_lowercase], '\RegularLabs\Library\StringHelper', 'toCase');
    }
    /**
     * Deep clone an object
     */
    public static function clone(object $object): object
    {
        return (new DeepCopy())->copy($object);
    }
    /**
     * Return the value by the object property key
     * A list of keys can be given. The first one that is not empty will get returned
     */
    public static function getValue(object $object, string|array $keys, mixed $default = null): mixed
    {
        $keys = \RegularLabs\Library\ArrayHelper::toArray($keys);
        foreach ($keys as $key) {
            if (empty($object->{$key})) {
                continue;
            }
            return $object->{$key};
        }
        return $default;
    }
    /**
     * Merge 2 objects
     */
    public static function merge(object $object1, object $object2): object
    {
        return (object) [...(array) $object1, ...(array) $object2];
    }
    public static function replaceKey(string|int $key, array $replacements, bool $include_prefixes, string $prefix_delimiter): string|int
    {
        foreach ($replacements as $to => $froms) {
            foreach ($froms as $from) {
                if ($key === $from) {
                    return $to;
                }
                if (!$include_prefixes || !is_string($key)) {
                    continue;
                }
                $prefix = $from . $prefix_delimiter;
                if (str_starts_with($key, $prefix)) {
                    return $to . $prefix_delimiter . substr($key, strlen($prefix));
                }
            }
        }
        return $key;
    }
    /**
     * Replace key names
     */
    public static function replaceKeys(string|object $object, array $replacements, bool $include_prefixes = \false, string $prefix_delimiter = '_'): string|object
    {
        if (is_string($object)) {
            return $object;
        }
        $replacements = self::normalizeReplacements($replacements);
        return \RegularLabs\Library\ArrayHelper::transformKeysByMethod($object, self::class, 'replaceKey', [$replacements, $include_prefixes, $prefix_delimiter]);
    }
    private static function normalizeReplacements(array $replacements): array
    {
        $normalized = [];
        foreach ($replacements as $to => $froms) {
            $normalized[$to] = is_array($froms) ? $froms : [$froms];
        }
        return $normalized;
    }
}
