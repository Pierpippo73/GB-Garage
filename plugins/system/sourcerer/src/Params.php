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

use RegularLabs\Library\Parameters as RL_Parameters;
use RegularLabs\Library\PluginTag as RL_PluginTag;
use RegularLabs\Library\RegEx as RL_RegEx;

class Params
{
    protected static $areas   = null;
    protected static $params  = null;
    protected static $regexes = null;

    public static function get(string $key = '', mixed $default = ''): mixed
    {
        if ($key != '')
        {
            return self::getByKey($key, $default);
        }

        if ( ! is_null(self::$params))
        {
            return self::$params;
        }

        $params = RL_Parameters::getPlugin('sourcerer');

        $params->tag = RL_PluginTag::clean($params->syntax_word);

        $params->splitter = '<!-- START: SRC_SPLIT -->';

        $include_path         = (string) ($params->include_path ?? '/');
        $params->include_path = str_replace('//', '/', ('/' . trim($include_path, ' /\\') . '/'));

        self::$params = $params;

        return self::$params;
    }

    public static function getArea(?string $type = 'default'): object
    {
        $areas = self::getAreaSettings();

        return $areas->{$type} ?? $areas->default;
    }

    public static function getAreaForTrustedContent(string $type): object
    {
        $type           = self::getAreaType($type);
        $params         = self::get();
        $forbidden_php  = $params->forbidden_php ?? '';
        $forbidden_tags = $params->forbidden_tags ?? '';

        return (object) [
            'enable'         => Security::getMode('tags', $type) !== 0,
            'enable_css'     => Security::getMode('css', $type) !== 0,
            'enable_js'      => Security::getMode('js', $type) !== 0,
            'enable_php'     => Security::getMode('php', $type) !== 0,
            'forbidden_php'  => $forbidden_php,
            'forbidden_tags' => $forbidden_tags,
        ];
    }

    public static function getAreaForUntrustedContent(string $type): object
    {
        $security = Security::getForSource($type);
        $area     = $security->area_params;

        foreach (['css', 'js', 'php'] as $feature)
        {
            $area->{'enable_' . $feature} = $security->pass && $security->{'pass_' . $feature};
        }

        return $area;
    }

    /**
     * @deprecated Use getAreaForUntrustedContent().
     */
    public static function getAreaForUnverifiedContent(string $type): object
    {
        $area                    = self::getAreaForUntrustedContent($type);
        $area->enable_unverified = $area->enable_untrusted;

        return $area;
    }

    /**
     * @deprecated Use getAreaForTrustedContent().
     */
    public static function getAreaForVerifiedContent(string $type): object
    {
        return self::getAreaForTrustedContent($type);
    }

    public static function getAreaSettings(): object
    {
        if ( ! is_null(self::$areas))
        {
            return self::$areas;
        }

        self::$areas = (object) [
            'default'    => self::getAreaForTrustedContent('other'),
            'articles'   => self::getAreaForTrustedContent('articles'),
            'components' => self::getAreaForTrustedContent('components'),
            'other'      => self::getAreaForTrustedContent('other'),
        ];

        return self::$areas;
    }

    public static function getAreaType(string $area): string
    {
        return match ($area)
        {
            'article', 'articles'     => 'articles',
            'component', 'components' => 'components',
            default                   => 'other',
        };
    }

    public static function getRegex($type = 'tag'): string
    {
        $regexes = self::getRegexes();

        return $regexes->{$type} ?? $regexes->tag;
    }

    public static function getTagCharacters(): array
    {
        $params = self::get();

        if ( ! isset($params->tag_character_start))
        {
            self::setTagCharacters();
        }

        return [$params->tag_character_start, $params->tag_character_end];
    }

    public static function getTags(bool $only_start_tags = false): array
    {
        $params = self::get();

        [$tag_start, $tag_end] = self::getTagCharacters();

        $tags = [
            [
                $tag_start . $params->tag,
            ],
            [
                $tag_start . '/' . $params->tag . $tag_end,
            ],
        ];

        return $only_start_tags ? $tags[0] : $tags;
    }

    public static function setTagCharacters(): void
    {
        $params = self::get();

        [self::$params->tag_character_start, self::$params->tag_character_end] = explode('.', $params->tag_characters);
    }

    private static function getByKey(string $key, mixed $default = ''): mixed
    {
        $params = self::get();

        return $params->{$key} ?? $default;
    }

    private static function getRegexes(): object
    {
        if ( ! is_null(self::$regexes))
        {
            return self::$regexes;
        }

        $params = self::get();

        // Tag character start and end
        [$tag_start, $tag_end] = Params::getTagCharacters();
        $tag_start = RL_RegEx::quote($tag_start);
        $tag_end   = RL_RegEx::quote($tag_end);

        $pre  = RL_PluginTag::getRegexSurroundingTagPre();
        $post = RL_PluginTag::getRegexSurroundingTagPost();

        $spaces = RL_PluginTag::getRegexSpaces('*');

        self::$regexes = (object) [];

        self::$regexes->tag = '('
            . '(?<start_pre>' . $pre . ')'
            . $tag_start . RL_RegEx::quote($params->tag) . $spaces . '(?<data>( .*?)?)' . $tag_end
            . '(?<start_post>' . $post . ')'

            . '(?<content>.*?)'

            . '(?<end_pre>' . $pre . ')'
            . $tag_start . '\/' . RL_RegEx::quote($params->tag) . $tag_end
            . '(?<end_post>' . $post . ')'
            . ')';

        return self::$regexes;
    }
}
