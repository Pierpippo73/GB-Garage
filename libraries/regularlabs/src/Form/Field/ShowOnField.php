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
namespace RegularLabs\Library\Form\Field;

defined('_JEXEC') or die;
use RegularLabs\Library\Form\FormField;
use RegularLabs\Library\RegEx;
use RegularLabs\Library\ShowOn;
class ShowOnField extends FormField
{
    protected function getInput()
    {
        $value = (string) $this->get('value');
        $class = $this->get('class', '');
        if (!$value) {
            return $this->getControlGroupEnd() . ShowOn::close() . $this->getControlGroupStart();
        }
        $formControl = $this->get('form', $this->formControl);
        $formControl = $formControl == 'root' ? '' : $formControl;
        while (str_starts_with($value, '../')) {
            $value = substr($value, 3);
            if (str_contains($formControl, '[')) {
                $formControl = RegEx::replaceEntireString('(.*)\[.*?\]', '\1', $formControl);
            }
        }
        return $this->getControlGroupEnd() . ShowOn::open($value, $formControl, $this->group, $class) . $this->getControlGroupStart();
    }
    protected function getLabel()
    {
        return '';
    }
}
