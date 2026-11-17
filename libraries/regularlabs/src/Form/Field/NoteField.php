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
use Joomla\CMS\HTML\HTMLHelper as JHtml;
use Joomla\CMS\Language\Text as JText;
use RegularLabs\Library\Form\FormField;
class NoteField extends FormField
{
    protected $hiddenDescription = \true;
    protected function getCoreNote()
    {
        $html = [];
        $class = [];
        if (!empty($this->class)) {
            $class[] = $this->class;
        }
        if ($close = (string) $this->element['close']) {
            JHtml::_('bootstrap.alert');
            $close = $close === 'true' ? 'alert' : $close;
            $html[] = '<button type="button" class="btn-close" data-bs-dismiss="' . $close . '"></button>';
            $class[] = 'alert-dismissible show';
        }
        $class = $class ? ' class="' . implode(' ', $class) . '"' : '';
        $title = $this->element['label'] ? (string) $this->element['label'] : ($this->element['title'] ? (string) $this->element['title'] : '');
        $heading = $this->element['heading'] ? (string) $this->element['heading'] : 'h4';
        $description = (string) $this->element['description'];
        $html[] = !empty($title) ? '<' . $heading . '>' . JText::_($title) . '</' . $heading . '>' : '';
        $html[] = !empty($description) ? JText::_($description) : '';
        return '</div><div ' . $class . '>' . implode('', $html);
    }
    protected function getInput()
    {
        return '';
    }
    protected function getLabel()
    {
        $use_core = !empty($this->element['label']) || !empty($this->element['description']);
        $note = $use_core ? $this->getCoreNote() : $this->getNote();
        if (empty($note)) {
            return '';
        }
        return '</div><div>' . $note;
    }
    protected function getNote()
    {
        if (empty($this->element['title']) && empty($this->element['text'])) {
            return '';
        }
        $title = $this->prepareText($this->element['title']);
        $text = $this->prepareText($this->element['text']);
        $heading = $this->element['heading'] ?: 'h4';
        $class = !empty($this->element['class']) ? ' class="' . $this->element['class'] . '"' : '';
        $html = [];
        $html[] = !empty($title) ? '<' . $heading . '>' . $title . '</' . $heading . '>' : '';
        $html[] = !empty($text) ? $text : '';
        return '<div ' . $class . '>' . implode('', $html) . '</div>';
    }
}
