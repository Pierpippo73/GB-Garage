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
class DocumentHeadCleaner
{
    public static function removeScriptTag(string &$string, string $folder, string $name): void
    {
        $string = self::stripScriptTag($string, $folder, $name);
    }
    public static function removeScriptsOptions(string &$string, string $name, string $alias = ''): void
    {
        $string = self::stripScriptsOptions($string, $name, $alias);
    }
    public static function removeScriptsStyles(string &$string, string $name, string $alias = ''): void
    {
        $string = self::stripScriptsStyles($string, $name, $alias);
    }
    public static function removeStyleTag(string &$string, string $folder, string $name): void
    {
        $string = self::stripStyleTag($string, $folder, $name);
    }
    public static function stripScriptTag(string $string, string $folder, string $name): string
    {
        $regex_name = \RegularLabs\Library\RegEx::quote($name);
        $regex_name = str_replace('\*', '[^"]*', $regex_name);
        return \RegularLabs\Library\RegEx::replace('\s*<script [^>]*href="[^"]*(' . $folder . '/js|js/' . $folder . ')/' . $regex_name . '\.[^>]*( /)?>', '', $string);
    }
    public static function stripScriptsOptions(string $string, string $name, string $alias = ''): string
    {
        \RegularLabs\Library\RegEx::match('(<script type="application/json" class="joomla-script-options new">)(.*?)(</script>)', $string, $match);
        if (empty($match)) {
            return $string;
        }
        $alias = $alias ?: \RegularLabs\Library\Extension::getAliasByName($name);
        $scripts = \RegularLabs\Library\Json::toObject($match[2], (object) []);
        if (!isset($scripts->{'rl_' . $alias})) {
            return $string;
        }
        unset($scripts->{'rl_' . $alias});
        return str_replace($match[0], $match[1] . json_encode($scripts) . $match[3], $string);
    }
    public static function stripScriptsStyles(string $string, string $name, string $alias = ''): string
    {
        [$start, $end] = \RegularLabs\Library\Protect::getInlineCommentTags($name, null, \true);
        $alias = $alias ?: \RegularLabs\Library\Extension::getAliasByName($name);
        $string = \RegularLabs\Library\RegEx::replace('((?:;\s*)?)(;?)' . $start . '.*?' . $end . '\s*', '\1', $string);
        $string = \RegularLabs\Library\RegEx::replace('\s*<link [^>]*href="[^"]*/(' . $alias . '/css|css/' . $alias . ')/[^"]*\.css[^"]*"[^>]*( /)?>', '', $string);
        $string = \RegularLabs\Library\RegEx::replace('\s*<script [^>]*src="[^"]*/(' . $alias . '/js|js/' . $alias . ')/[^"]*\.js[^"]*"[^>]*></script>', '', $string);
        return \RegularLabs\Library\RegEx::replace('\s*<script></script>', '', $string);
    }
    public static function stripStyleTag(string $string, string $folder, string $name): string
    {
        $name = \RegularLabs\Library\RegEx::quote($name);
        $name = str_replace('\*', '[^"]*', $name);
        return \RegularLabs\Library\RegEx::replace('\s*<link [^>]*href="[^"]*(' . $folder . '/css|css/' . $folder . ')/' . $name . '\.[^>]*( /)?>', '', $string);
    }
}
