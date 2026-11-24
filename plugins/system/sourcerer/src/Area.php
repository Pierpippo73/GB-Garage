<?php
/**
 * @package         Sourcerer
 * @version         18.0.0
 * 
 * @author          Peter van Westen <info@regularlabs.com>
 * @link            https://regularlabs.com
 * @copyright       Copyright © 2026 Regular Labs All Rights Reserved
 * @license         GNU General Public License version 2 or later
 */

namespace RegularLabs\Plugin\System\Sourcerer;

defined('_JEXEC') or die;

class Area
{
    static $prefix = 'SRC';

    public static function get(string &$string, string $area = ''): array
    {
        if (empty($string) || empty($area))
        {
            return [];
        }

        $start = '<!-- START: ' . self::$prefix . '_' . strtoupper($area) . ' -->';
        $end   = '<!-- END: ' . self::$prefix . '_' . strtoupper($area) . ' -->';

        $matches = explode($start, $string);
        array_shift($matches);

        foreach ($matches as $i => $match)
        {
            [$text] = explode($end, $match, 2);
            $matches[$i] = [
                $start . $text . $end,
                $text,
            ];
        }

        return $matches;
    }

    public static function tag(string &$string, string $area = ''): bool
    {
        if (empty($string) || empty($area))
        {
            return false;
        }

        $string = '<!-- START: ' . self::$prefix . '_' . strtoupper($area) . ' -->'
            . $string
            . '<!-- END: ' . self::$prefix . '_' . strtoupper($area) . ' -->';

        return true;
    }

    public static function tagContent(string $string, string $content, string $area): string
    {
        if ($string === '' || $content === '' || $area === '' || substr_count($string, $content) !== 1)
        {
            return $string;
        }

        $start = '<!-- START: ' . self::$prefix . '_' . strtoupper($area) . ' -->';
        $end   = '<!-- END: ' . self::$prefix . '_' . strtoupper($area) . ' -->';

        $tagged_content = str_replace([$start, $end], '', $content);

        self::tag($tagged_content, $area);

        return str_replace($content, $tagged_content, $string);
    }
}
