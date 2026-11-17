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

use Joomla\CMS\Access\Exception\NotAllowed;
use Joomla\CMS\Language\Text;
defined('_JEXEC') or die;
class Access
{
    public static function check(string $action, ?string $assetname = null, ?int $id = null): void
    {
        if (\RegularLabs\Library\User::authorise($action, $assetname, $id)) {
            return;
        }
        self::deny();
    }
    public static function deny(): void
    {
        throw new NotAllowed(Text::_('JERROR_ALERTNOAUTHOR'), 403);
    }
}
