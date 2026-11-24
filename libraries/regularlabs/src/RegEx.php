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
class RegEx
{
    public static function atEnd(string $pattern): string
    {
        return '(?:' . $pattern . ')\z';
    }
    public static function atStart(string $pattern): string
    {
        return '\A(?:' . $pattern . ')';
    }
    public static function atStartToEnd(string $pattern): string
    {
        return '\A(?:' . $pattern . ')\z';
    }
    /**
     * Perform a regular expression match
     */
    public static function match(string $pattern, string $string, &$match = null, ?string $options = null, int $flags = 0): int
    {
        if ($string == '' || $pattern == '') {
            $match = [];
            return 0;
        }
        $pattern = self::preparePattern($pattern, $options, $string);
        $result = preg_match($pattern, $string, $match, $flags);
        if (!$result) {
            $match = [];
            return 0;
        }
        $no_numeric_values = [];
        foreach ($match as $key => $value) {
            if (is_int($key) && $key !== 0) {
                continue;
            }
            $no_numeric_values[$key] = $value;
        }
        // If the leftover array counts more than 2 (so contains named groups), replace $match
        if (count($no_numeric_values) > 1) {
            $match = $no_numeric_values;
        }
        return $result;
    }
    public static function startsWith(string $pattern, string $string, &$match = null, ?string $options = null, int $flags = 0): int
    {
        $pattern = self::atStart($pattern);
        return self::match($pattern, $string, $match, $options, $flags);
    }
    public static function endsWith(string $pattern, string $string, &$match = null, ?string $options = null, int $flags = 0): int
    {
        $pattern = self::atEnd($pattern);
        return self::match($pattern, $string, $match, $options, $flags);
    }
    public static function matchesEntireString(string $pattern, string $string, &$match = null, ?string $options = null, int $flags = 0): int
    {
        $pattern = self::atStartToEnd($pattern);
        return self::match($pattern, $string, $match, $options, $flags);
    }
    /**
     * Perform a global regular expression match
     */
    public static function matchAll(string $pattern, string $string, &$matches = null, ?string $options = null, int $flags = \PREG_SET_ORDER): int
    {
        if ($string == '' || $pattern == '') {
            $matches = [];
            return 0;
        }
        $pattern = self::preparePattern($pattern, $options, $string);
        $result = preg_match_all($pattern, $string, $matches, $flags);
        if (!$result) {
            $matches = [];
            return 0;
        }
        if ($flags == \PREG_OFFSET_CAPTURE) {
            $no_numeric_values = [];
            foreach ($matches as $key => $value) {
                if (is_int($key) && $key !== 0) {
                    continue;
                }
                $no_numeric_values[$key] = $value;
            }
            // If the leftover array counts less than 2 (so no named groups), don't continue
            if (count($no_numeric_values) < 2) {
                return $result;
            }
            $matches = $no_numeric_values;
            return $result;
        }
        if ($flags != \PREG_SET_ORDER) {
            return $result;
        }
        foreach ($matches as &$match) {
            $no_numeric_values = [];
            foreach ($match as $key => $value) {
                if (is_int($key) && $key !== 0) {
                    continue;
                }
                $no_numeric_values[$key] = $value;
            }
            // If the leftover array counts less than 2 (so no named groups), don't continue
            if (count($no_numeric_values) < 2) {
                break;
            }
            $match = $no_numeric_values;
        }
        return $result;
    }
    /**
     * preg_quote the given string or array of strings
     */
    public static function nameGroup(string $data, string $name = ''): string
    {
        return '(?<' . $name . '>' . $data . ')';
    }
    /**
     * Make a string a valid regular expression pattern
     */
    public static function preparePattern(string|array $pattern, ?string $options = null, string $string = ''): string|array
    {
        $array = \RegularLabs\Library\ArrayHelper::applyMethodToValues([$pattern, $options, $string]);
        if (!is_null($array)) {
            return $array;
        }
        if (!str_starts_with($pattern, '#')) {
            $options = !is_null($options) ? $options : 'si';
            $pattern = '#' . $pattern . '#' . $options;
        }
        if (\RegularLabs\Library\StringHelper::detectUTF8($string)) {
            // use utf-8
            return $pattern . 'u';
        }
        return $pattern;
    }
    /**
     * preg_quote the given string or array of strings
     */
    public static function quote(string|array $data, string $name = '', string $delimiter = '#'): string
    {
        if (is_array($data)) {
            if (count($data) === 1) {
                return self::quote(array_pop($data), $name, $delimiter);
            }
            $array = self::quoteArray($data, $delimiter);
            $prefix = '?:';
            if ($name != '') {
                $prefix = $name ? '?<' . $name . '>' : '';
            }
            return '(' . $prefix . implode('|', $array) . ')';
        }
        if ($name != '') {
            return '(?<' . $name . '>' . preg_quote($data, $delimiter) . ')';
        }
        return preg_quote($data, $delimiter);
    }
    /**
     * preg_quote the given array of strings
     */
    public static function quoteArray(array $array, string $delimiter = '#'): array
    {
        array_walk($array, function (&$part, $key, $delimiter) {
            $part = self::quote($part, '', $delimiter);
        }, $delimiter);
        return $array;
    }
    public static function quoteReplacement(string $string): string
    {
        return str_replace(['\\', '$'], ['\\\\', '\$'], $string);
    }
    /**
     * Perform a regular expression search and replace
     */
    public static function replace(string $pattern, string $replacement, string $string, ?string $options = null, int $limit = -1, ?int &$count = null): string
    {
        if ($string == '' || $pattern == '') {
            $count = 0;
            return $string;
        }
        $pattern = self::preparePattern($pattern, $options, $string);
        $result = preg_replace($pattern, $replacement, $string, $limit, $count);
        if (!is_null($result)) {
            return $result;
        }
        $count = 0;
        return $string;
    }
    public static function replaceAtStart(string $pattern, string $replacement, string $string, ?string $options = null, int $limit = -1, ?int &$count = null): string
    {
        $pattern = self::atStart($pattern);
        return self::replace($pattern, $replacement, $string, $options, $limit, $count);
    }
    public static function replaceAtEnd(string $pattern, string $replacement, string $string, ?string $options = null, int $limit = -1, ?int &$count = null): string
    {
        $pattern = self::atEnd($pattern);
        return self::replace($pattern, $replacement, $string, $options, $limit, $count);
    }
    public static function replaceEntireString(string $pattern, string $replacement, string $string, ?string $options = null, int $limit = -1, ?int &$count = null): string
    {
        $pattern = self::atStartToEnd($pattern);
        return self::replace($pattern, $replacement, $string, $options, $limit, $count);
    }
    /**
     * Perform a regular expression search and replace once
     */
    public static function replaceOnce(string $pattern, string $replacement, string $string, ?string $options = null): string
    {
        return self::replace($pattern, $replacement, $string, $options, 1);
    }
    /**
     * Perform a regular expression split
     */
    public static function split(string $pattern, string $string, ?string $options = null, int $limit = -1, int $flags = \PREG_SPLIT_DELIM_CAPTURE): array
    {
        if ($string == '' || $pattern == '') {
            return [$string];
        }
        $pattern = self::preparePattern($pattern, $options, $string);
        $parts = preg_split($pattern, $string, $limit, $flags);
        return $parts === \false ? [$string] : $parts;
    }
    /**
     * reverse preg_quote the given string
     */
    public static function unquote(string $string, string $delimiter = '#'): string
    {
        return strtr($string, ['\\' . $delimiter => $delimiter, '\.' => '.', '\\\\' => '\\', '\+' => '+', '\*' => '*', '\?' => '?', '\[' => '[', '\^' => '^', '\]' => ']', '\$' => '$', '\(' => '(', '\)' => ')', '\{' => '{', '\}' => '}', '\=' => '=', '\!' => '!', '\<' => '<', '\>' => '>', '\|' => '|', '\:' => ':', '\-' => '-']);
    }
}
