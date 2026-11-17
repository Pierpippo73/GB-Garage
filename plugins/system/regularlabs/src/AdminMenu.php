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

namespace RegularLabs\Plugin\System\RegularLabs;

defined('_JEXEC') or die;

use RegularLabs\Library\Document as RL_Document;
use RegularLabs\Library\RegEx as RL_RegEx;

class AdminMenu
{
    public static function combine(): void
    {
        $params = Params::get();

        if ( ! $params->combine_admin_menu)
        {
            return;
        }

        $html = RL_Document::getBody();

        if (is_null($html) || ! self::combineHtml($html))
        {
            return;
        }

        RL_Document::setBody($html);
    }

    public static function combineHtml(string &$html): bool
    {
        $params = Params::get();

        if ( ! $params->combine_admin_menu)
        {
            return false;
        }

        if (
            ! str_contains($html, '<nav class="main-nav-container"')
            || ! str_contains($html, '">Regular Labs - ')
        )
        {
            return false;
        }

        if ( ! RL_RegEx::matchAll(
            '<li class="item item-level-2"><a class="no-dropdown"[^>]*"Regular Labs - .*?</a></li>',
            $html,
            $matches,
            null,
            PREG_PATTERN_ORDER
        )
        )
        {
            return false;
        }

        $menu_items = $matches[0];

        if (count($menu_items) < 2)
        {
            return false;
        }

        $manager = null;

        foreach ($menu_items as $i => &$menu_item)
        {
            $menu_item = str_replace('item-level-2', 'item-level-3', $menu_item);
            $menu_item = str_replace('Regular Labs - ', '', $menu_item);

            if (str_contains($menu_item, 'index.php?option=com_regularlabsmanager'))
            {
                $manager = $menu_item;
                unset($menu_items[$i]);
            }
        }

        $main_link = '#';

        if ( ! is_null($manager))
        {
            array_unshift($menu_items, $manager);
            $main_link = 'href="index.php?option=com_regularlabsmanager"';
        }

        $new_menu_item =
            '<li class="item parent item-level-2">'
            . '<a class="has-arrow" href=" ' . $main_link . '" aria-label="Regular Labs"><span class="sidebar-item-title">Regular Labs</span></a>'
            . "\n" . '<ul id="menu-regularlabs" class="mm-collapse collapse-level-2">'
            . "\n" . implode("\n", $menu_items)
            . "\n" . '</ul>'
            . '</li>';

        $first = array_shift($matches[0]);

        $html = str_replace($first, $new_menu_item, $html);
        $html = str_replace($matches[0], '', $html);

        return true;
    }
}
