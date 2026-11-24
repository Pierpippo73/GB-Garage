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
use Joomla\CMS\Menu\AbstractMenu as JAbstractMenu;
use Joomla\CMS\Menu\MenuItem as JMenuItem;
use Throwable;
class Menu
{
    public static function get(): ?JAbstractMenu
    {
        if (!\RegularLabs\Library\Document::isSite()) {
            return null;
        }
        $application = \RegularLabs\Library\Application::get();
        if (!method_exists($application, 'getMenu')) {
            return null;
        }
        try {
            return $application->getMenu('site');
        } catch (Throwable) {
            return null;
        }
    }
    public static function getActive(): ?JMenuItem
    {
        return self::get()?->getActive();
    }
    public static function getDefault(?string $language = null): ?JMenuItem
    {
        return self::get()?->getDefault($language ?? \RegularLabs\Library\Language::getTag());
    }
    public static function getItem(int $id): ?JMenuItem
    {
        if (!$id) {
            return null;
        }
        return self::get()?->getItem($id);
    }
}
