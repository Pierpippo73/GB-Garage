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
use Joomla\CMS\Language\Text as JText;
use RegularLabs\Library\Extension;
use RegularLabs\Library\Form\FormField;
class OnlyProField extends FormField
{
    protected function getExtensionName()
    {
        $element = $this->form->getValue('element');
        if ($element) {
            return $element;
        }
        $form_name = $this->form->getName();
        $manifest = $form_name ? Extension::getXML($form_name) : \false;
        if ($manifest && str_starts_with((string) ($manifest['author'] ?? ''), 'Regular Labs')) {
            return $form_name;
        }
        $component = $this->app->input->get('component', '');
        if ($component) {
            return str_replace('com_', '', $component);
        }
        $folder = $this->app->input->get('folder', '');
        if ($folder) {
            $extension = explode('.', $folder);
            return array_pop($extension);
        }
        $option = $this->app->input->get('option', '');
        if ($option) {
            return str_replace('com_', '', $option);
        }
        return \false;
    }
    protected function getInput()
    {
        $label = $this->prepareText($this->get('label'));
        $description = $this->prepareText($this->get('description'));
        if (!$label && !$description) {
            return '';
        }
        return $this->getText();
    }
    protected function getLabel()
    {
        $label = $this->prepareText($this->get('label'));
        $description = $this->prepareText($this->get('description'));
        if (!$label && !$description) {
            return '</div><div>' . $this->getText();
        }
        return parent::getLabel();
    }
    protected function getText()
    {
        $text = JText::_('RL_ONLY_AVAILABLE_IN_PRO');
        $text = '<em>' . $text . '</em>';
        $extension = $this->getExtensionName();
        if (!$extension) {
            return $text;
        }
        $alias = Extension::getAliasByName($extension);
        if (!$alias) {
            return $text;
        }
        return '<a href="https://regularlabs.com/' . $alias . '/features" target="_blank">' . $text . '</a>';
    }
}
