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
namespace RegularLabs\Library\Plugin;

defined('_JEXEC') or die;
use Joomla\CMS\Language\Text as JText;
use RegularLabs\Library\Language;
class ActionLogItem
{
    public static function getItemData(string $extension, array $item_types = [], array $item_titles = []): object
    {
        if (str_contains($extension, '.')) {
            [$extension, $type] = explode('.', $extension);
        }
        Language::load($extension);
        $type ??= $item_types[$extension] ?? 'item';
        $title = $item_titles[$extension] ?? JText::_($extension) . ' ' . JText::_('RL_ITEM');
        return (object) ['context' => $extension . '.' . $type, 'option' => $extension, 'type' => $type, 'title' => $title];
    }
}
