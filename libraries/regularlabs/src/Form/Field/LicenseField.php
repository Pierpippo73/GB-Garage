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
use RegularLabs\Library\Form\FormField;
use RegularLabs\Library\License;
class LicenseField extends FormField
{
    protected function getInput()
    {
        $extension = $this->get('extension');
        if (empty($extension)) {
            return '';
        }
        return License::getMessage($extension, \true);
    }
    protected function getLabel()
    {
        return '';
    }
}
