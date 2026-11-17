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
use Joomla\CMS\Router\Route as JRoute;
use Joomla\Component\Content\Site\Helper\RouteHelper as JContentHelperRoute;
class Route
{
    public static function getArticleRoute(array|object $article): string
    {
        $id = self::getArticleSlug($article);
        if (empty($id)) {
            return '';
        }
        $url = JContentHelperRoute::getArticleRoute($id, self::getValue($article, 'catid', 0), self::getValue($article, 'language', '*'));
        $item_id = self::getArticleRouteMenuItemId($article);
        if ($item_id) {
            $url .= '&Itemid=' . $item_id;
        }
        return $url;
    }
    public static function getArticleUrl(array|object $article, bool $xhtml = \true, int $tls = JRoute::TLS_IGNORE, bool $absolute = \false): string
    {
        $route = self::getArticleRoute($article);
        if (empty($route)) {
            return '';
        }
        return \RegularLabs\Library\Uri::getSiteRoute($route, $xhtml, $tls, $absolute);
    }
    public static function getCategoryRoute(array|object|int|string $category, ?string $language = null): string
    {
        $id = self::getCategoryId($category);
        if (empty($id)) {
            return '';
        }
        $language ??= is_array($category) || is_object($category) ? self::getValue($category, 'language', '*') : '*';
        $url = JContentHelperRoute::getCategoryRoute($id, $language);
        $item_id = self::getCategoryRouteMenuItemId($id, $language);
        if ($item_id) {
            $url .= '&Itemid=' . $item_id;
        }
        return $url;
    }
    public static function getCategoryUrl(array|object|int|string $category, ?string $language = null, bool $xhtml = \true, int $tls = JRoute::TLS_IGNORE, bool $absolute = \false): string
    {
        $route = self::getCategoryRoute($category, $language);
        if (empty($route)) {
            return '';
        }
        return \RegularLabs\Library\Uri::getSiteRoute($route, $xhtml, $tls, $absolute);
    }
    private static function getArticleRouteMenuItemId(array|object $article): int
    {
        $article_id = (int) self::getValue($article, 'id', 0);
        if (empty($article_id)) {
            return 0;
        }
        $language = self::getValue($article, 'language', '*');
        $article_link = 'index.php?option=com_content&view=article&id=' . $article_id;
        $item_id = self::getMenuItemIdByLinkAndLanguage($article_link, $language);
        if ($item_id) {
            return $item_id;
        }
        $category_id = (int) self::getValue($article, 'catid', 0);
        if (empty($category_id)) {
            return 0;
        }
        return self::getCategoryRouteMenuItemId($category_id, $language);
    }
    private static function getArticleSlug(array|object $article): string
    {
        $id = self::getValue($article, 'id', '');
        if (empty($id)) {
            return '';
        }
        $alias = self::getValue($article, 'alias', '');
        return $alias ? $id . ':' . $alias : (string) $id;
    }
    private static function getCategoryId(array|object|int|string $category): int|string
    {
        if (is_array($category) || is_object($category)) {
            return self::getValue($category, 'id', '');
        }
        return $category;
    }
    private static function getCategoryRouteMenuItemId(int|string $category_id, string $language = '*'): int
    {
        $category_link = 'index.php?option=com_content&view=category&id=' . (int) $category_id;
        return self::getMenuItemIdByLinkAndLanguage($category_link, $language);
    }
    private static function getMenuItemIdByLinkAndLanguage(string $link, string $language = '*'): int
    {
        $menu = self::getSiteMenu();
        if (empty($menu)) {
            return 0;
        }
        foreach (self::getMenuItemLanguages($language) as $menu_language) {
            $menu_item = $menu->getItems(['link', 'language'], [$link, $menu_language], \true);
            if (!empty($menu_item->id)) {
                return (int) $menu_item->id;
            }
        }
        return 0;
    }
    private static function getMenuItemLanguages(string $language): array
    {
        if (empty($language) || $language === '*') {
            return ['*'];
        }
        return [$language, '*'];
    }
    private static function getSiteMenu(): ?object
    {
        return \RegularLabs\Library\Menu::get();
    }
    private static function getValue(array|object $data, string $key, int|string $default): int|string
    {
        if (is_array($data)) {
            return $data[$key] ?? $default;
        }
        return $data->{$key} ?? $default;
    }
}
