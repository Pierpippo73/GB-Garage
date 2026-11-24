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

defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;

echo match ($displayData['action'])
{
    'start'  => HTMLHelper::_('uitab.startTabSet', $displayData['set'], ['active' => $displayData['tab']]),
    'tab'    => HTMLHelper::_('uitab.addTab', $displayData['set'], $displayData['tab'], Text::_($displayData['label'])),
    'endtab' => HTMLHelper::_('uitab.endTab'),
    'end'    => HTMLHelper::_('uitab.endTabSet'),
    default  => '',
};
