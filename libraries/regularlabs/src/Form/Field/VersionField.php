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
use RegularLabs\Library\User;
use RegularLabs\Library\Version;
class VersionField extends FormField
{
    protected function getInput()
    {
        $extension = $this->get('extension');
        $xml = $this->get('xml');
        if (!$xml && $this->form->getValue('element')) {
            if ($this->form->getValue('folder')) {
                $xml = 'plugins/' . $this->form->getValue('folder') . '/' . $this->form->getValue('element') . '/' . $this->form->getValue('element') . '.xml';
            } else {
                $xml = 'administrator/modules/' . $this->form->getValue('element') . '/' . $this->form->getValue('element') . '.xml';
            }
            if (!file_exists(JPATH_SITE . '/' . $xml)) {
                return '';
            }
        }
        if (empty($extension) || empty($xml)) {
            return '';
        }
        $authorise = User::authorise('core.manage', 'com_installer');
        if (!$authorise) {
            return '';
        }
        return Version::getMessage($extension);
    }
    protected function getLabel()
    {
        return '';
    }
}
