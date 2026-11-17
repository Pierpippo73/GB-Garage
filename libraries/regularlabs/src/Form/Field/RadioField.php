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
namespace RegularLabs\Library\Form\Field;

defined('_JEXEC') or die;
use Joomla\CMS\Form\Field\RadioField as JRadioField;
class RadioField extends JRadioField
{
    protected function getLayoutData()
    {
        $data = parent::getLayoutData();
        if ((string) $this->element['display_only'] !== 'true') {
            return $data;
        }
        $data['name'] = 'rl_display[' . $data['id'] . ']';
        return $data;
    }
}
