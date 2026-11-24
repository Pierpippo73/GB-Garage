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

defined('_JEXEC') or die;

use RegularLabs\Library\Plugin\ServiceProvider as RL_ServiceProvider;
use RegularLabs\Plugin\System\Sourcerer\Extension\Sourcerer;

if ( ! class_exists(RL_ServiceProvider::class))
{
    return;
}

return RL_ServiceProvider::systemPlugin('sourcerer', Sourcerer::class);
