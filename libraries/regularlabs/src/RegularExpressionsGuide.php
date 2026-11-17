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
use Joomla\CMS\Layout\FileLayout as JFileLayout;
class RegularExpressionsGuide
{
    public function render(): void
    {
        $layout = new JFileLayout('regularlabs.regular_expressions_guide', JPATH_LIBRARIES . '/regularlabs/layouts');
        echo $layout->render();
    }
}
