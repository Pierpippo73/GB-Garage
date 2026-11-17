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
use RegularLabs\Library\Extension;
use RegularLabs\Library\Form\FormField;
class IsInstalledField extends FormField
{
    protected $layout = 'joomla.form.field.hidden';
    protected function getLabel()
    {
        $this->value = (int) Extension::isInstalled($this->get('extension'), $this->get('extension_type', 'component'), $this->get('folder', 'system'));
        return $this->getControlGroupEnd() . rtrim($this->getRenderer($this->layout)->render($this->getLayoutData()), \PHP_EOL) . $this->getControlGroupStart();
    }
}
