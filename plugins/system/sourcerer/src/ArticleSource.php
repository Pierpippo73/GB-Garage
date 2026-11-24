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

use Joomla\Registry\Registry as JRegistry;
use RegularLabs\Library\ArrayHelper as RL_Array;
use RegularLabs\Library\DB as RL_DB;
use RegularLabs\Library\PluginTag as RL_PluginTag;
use RegularLabs\Library\RegEx as RL_RegEx;
use WeakMap;

final class ArticleSource
{
    private const MARKER           = '___SRC_ARTICLE_';
    private const USER_DATA_MARKER = '___SRC_USER_DATA_';
    private const OPEN_SUFFIX      = '_OPEN___';
    private const CLOSE_SUFFIX     = '_CLOSE___';
    private const TOKEN_BYTES      = 16;
    private const TOKEN_PATTERN    = '[a-f0-9]{' . (self::TOKEN_BYTES * 2) . '}';

    private static array    $saved_articles = [];
    private static array    $templates      = [];
    private static array    $user_data      = [];
    private static ?WeakMap $markers        = null;

    public static function clean(string $content): string
    {
        if ( ! str_contains($content, self::MARKER) && ! str_contains($content, self::USER_DATA_MARKER))
        {
            return $content;
        }

        $pattern = RL_RegEx::quote([self::MARKER, self::USER_DATA_MARKER])
            . self::TOKEN_PATTERN
            . RL_RegEx::quote([self::OPEN_SUFFIX, self::CLOSE_SUFFIX]);

        return RL_RegEx::replace($pattern, '', $content);
    }

    public static function consume(string &$content, string $attributes, object $article): bool
    {
        $block = self::readMarkedBlock($content, self::MARKER);

        if ($block === null)
        {
            $content = self::clean($content);

            return false;
        }

        [$token, $body] = $block;
        $markers        = self::$markers[$article] ?? [];

        if (($markers[$token] ?? null) === [(int) ($article->id ?? 0), self::getEditor($article), $attributes])
        {
            unset($markers[$token]);
            self::$markers[$article] = $markers;
        }
        elseif ((self::$templates[$token] ?? null) !== [$attributes, $body])
        {
            $content = self::clean($content);

            return false;
        }

        $content = $body;

        return true;
    }

    public static function consumeUserData(string &$content, string $attributes): ?bool
    {
        if ( ! str_starts_with($content, self::USER_DATA_MARKER))
        {
            return null;
        }

        $block = self::readMarkedBlock($content, self::USER_DATA_MARKER);

        if ($block === null)
        {
            $content = self::clean($content);

            return false;
        }

        [$token, $content]                = $block;
        [$stored_attributes, $body, $id] = self::$user_data[$token] ?? ['', '', 0];

        return $attributes === $stored_attributes && $content === $body && Security::userDataOwnerIsTrusted($id);
    }

    public static function protect(object $article, bool $include_body, bool $include_page_title): void
    {
        $article_id = (int) ($article->id ?? 0);

        if ($article_id < 1 || ! isset($article->created_by))
        {
            return;
        }

        $fields = ['title', 'description'];

        if ($include_body)
        {
            $body_fields = isset($article->text) && is_string($article->text)
                ? ['text']
                : ['introtext', 'fulltext'];
            $fields = [...$fields, ...$body_fields];
        }

        foreach ($fields as $field)
        {
            if (isset($article->{$field}) && is_string($article->{$field}))
            {
                $article->{$field} = self::mark($article->{$field}, $field, $article, $article_id);
            }
        }

        if ( ! $include_page_title || ! (($article->params ?? null) instanceof JRegistry))
        {
            return;
        }

        $title = $article->params->get('article_page_title');

        if (is_string($title))
        {
            $article->params->set(
                'article_page_title',
                self::mark($title, 'article_page_title', $article, $article_id)
            );
        }
    }

    public static function protectTemplate(string $text, int $creator_id, int $modifier_id): string
    {
        if (($modifier_id ?: $creator_id) < 1 || ! RL_PluginTag::containsTags($text, Params::getTags(true)))
        {
            return $text;
        }

        $security = Security::getForAuthoredContent('articles', $creator_id, $modifier_id);

        if ( ! $security->pass || ! $security->pass_php)
        {
            return $text;
        }

        if ( ! RL_RegEx::matchAll(Params::getRegex(), $text, $matches, null, PREG_SET_ORDER | PREG_OFFSET_CAPTURE))
        {
            return $text;
        }

        foreach (array_reverse($matches) as $match)
        {
            [$content, $offset]      = $match['content'];
            $token                   = bin2hex(random_bytes(self::TOKEN_BYTES));
            self::$templates[$token] = [$match['data'][0], $content];
            $text                    = self::insertMarkers($text, $token, $content, $offset);
        }

        return $text;
    }

    public static function protectUserData(string $text, int $user_id, array $values): string
    {
        $pattern = Params::getRegex();

        if ( ! RL_RegEx::matchAll($pattern, $text, $matches, null, PREG_SET_ORDER | PREG_OFFSET_CAPTURE))
        {
            return $text;
        }

        $saved_blocks = [];
        foreach (RL_Array::flatten($values) as $value)
        {
            if ( ! is_string($value) || ! RL_RegEx::matchAll($pattern, $value, $blocks))
            {
                continue;
            }

            foreach ($blocks as $block)
            {
                $saved_blocks[serialize([$block['data'], $block['content']])] = true;
            }
        }

        foreach (array_reverse($matches) as $match)
        {
            [$content, $offset]      = $match['content'];
            $attributes             = $match['data'][0];
            $key                    = serialize([$attributes, $content]);
            $token                  = bin2hex(random_bytes(self::TOKEN_BYTES));
            self::$user_data[$token] = [$attributes, $content, isset($saved_blocks[$key]) ? $user_id : 0];
            $text                   = self::insertMarkers($text, $token, $content, $offset, self::USER_DATA_MARKER);
        }

        return $text;
    }

    private static function getEditor(object $article): int
    {
        return (int) ($article->modified_by ?? 0) ?: (int) ($article->created_by ?? 0);
    }

    private static function getSavedArticle(int $article_id): ?object
    {
        if (array_key_exists($article_id, self::$saved_articles))
        {
            return self::$saved_articles[$article_id];
        }

        $db    = RL_DB::get();
        $query = $db->getQuery(true)
            ->select($db->quoteName(['title', 'introtext', 'fulltext', 'metadesc', 'attribs', 'created_by', 'modified_by']))
            ->from($db->quoteName('#__content'))
            ->where($db->quoteName('id') . ' = ' . $article_id);

        return self::$saved_articles[$article_id] = $db->setQuery($query)->loadObject();
    }

    private static function getSavedText(object $saved, string $field): string
    {
        if ($field === 'article_page_title')
        {
            $params = new JRegistry($saved->attribs);

            return (string) $params->get('article_page_title', '');
        }

        return match ($field) {
            'text'        => $saved->introtext . ' ' . $saved->fulltext,
            'description' => $saved->metadesc . ' ' . $saved->introtext . ' ' . $saved->fulltext,
            default       => (string) $saved->{$field},
        };
    }

    private static function mark(string $text, string $field, object $article, int $article_id): string
    {
        if (str_contains($text, self::MARKER) || ! RL_PluginTag::containsTags($text, Params::getTags(true)))
        {
            return $text;
        }

        $saved = self::getSavedArticle($article_id);

        if ( ! $saved || self::getEditor($saved) !== self::getEditor($article))
        {
            return $text;
        }

        $pattern    = Params::getRegex();
        $saved_text = self::getSavedText($saved, $field);

        if ( ! RL_RegEx::matchAll($pattern, $saved_text, $authored))
        {
            return $text;
        }

        RL_RegEx::matchAll($pattern, $text, $matches, null, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);

        self::$markers ??= new WeakMap;
        $markers = self::$markers[$article] ?? [];
        $saved_block_counts = [];

        foreach ($authored as $source)
        {
            $key = serialize([$source['data'], $source['content']]);
            $saved_block_counts[$key] = ($saved_block_counts[$key] ?? 0) + 1;
        }

        foreach (array_reverse($matches) as $match)
        {
            [$content, $offset] = $match['content'];
            $attributes         = $match['data'][0];
            $key                = serialize([$attributes, $content]);

            if (empty($saved_block_counts[$key]))
            {
                continue;
            }

            $token = bin2hex(random_bytes(self::TOKEN_BYTES));
            $text  = self::insertMarkers($text, $token, $content, $offset);
            $markers[$token] = [$article_id, self::getEditor($article), $attributes];
            $saved_block_counts[$key]--;
        }

        self::$markers[$article] = $markers;

        return $text;
    }

    private static function readMarkedBlock(string $content, string $prefix): ?array
    {
        $opening = RL_RegEx::quote($prefix) . '(?<token>' . self::TOKEN_PATTERN . ')' . RL_RegEx::quote(self::OPEN_SUFFIX);
        RL_RegEx::startsWith($opening, $content, $match);

        $token = $match['token'] ?? '';
        $end   = $prefix . $token . self::CLOSE_SUFFIX;

        if ($token === '' || ! str_ends_with($content, $end))
        {
            return null;
        }

        return [$token, substr($content, strlen($match[0]), -strlen($end))];
    }

    private static function insertMarkers(string $text, string $token, string $content, int $offset, string $prefix = self::MARKER): string
    {
        $marked = $prefix . $token . self::OPEN_SUFFIX . $content . $prefix . $token . self::CLOSE_SUFFIX;

        return substr_replace($text, $marked, $offset, strlen($content));
    }
}
