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
use Joomla\CMS\Layout\FileLayout;
use RegularLabs\Library\Form\FormField;
class TabsField extends FormField
{
    protected function getInput(): string
    {
        $layout = new FileLayout('regularlabs.form.field.tabs', dirname(__DIR__, 3) . '/layouts');
        return $this->getControlGroupEnd() . $layout->render(['action' => (string) $this->get('action', 'tab'), 'set' => (string) $this->get('tabset'), 'tab' => (string) $this->get('tab'), 'label' => (string) $this->get('label')]) . $this->getControlGroupStart();
    }
    protected function getLabel(): string
    {
        return '';
    }
}
