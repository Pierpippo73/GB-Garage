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
use RegularLabs\Library\RegEx;
use Joomla\CMS\Language\Text as JText;
use RegularLabs\Library\Document;
use RegularLabs\Library\Form\FormField;
use RegularLabs\Library\StringHelper;
class AjaxField extends FormField
{
    protected function getInput()
    {
        $class = $this->get('class', 'btn btn-success');
        if ($this->get('disabled')) {
            return $this->getButton($class . ' disabled', 'disabled');
        }
        Document::loadAdminFormAssets();
        $query = $this->getMappedData('url-query', \true, \false);
        $post_data = $this->getMappedData('post-data');
        $legacy_post_data = $this->getMappedData('url-query', \false, \true, $post_data !== '');
        if ($legacy_post_data) {
            $post_data .= ($post_data ? ' + ' : '') . $legacy_post_data;
        }
        $url = '`' . addslashes($this->get('url')) . '`' . ($query ? ' + ' . $query : '');
        $data = $post_data ?: '``';
        $success_disabled = $this->get('success-disabled') ? 'true' : 'false';
        $error_text = StringHelper::escape(json_encode(JText::_($this->get('error-text', '')), \JSON_THROW_ON_ERROR));
        $attributes = 'onclick="RegularLabs.AdminForm.loadAjaxButton(`' . $this->id . '`, ' . $url . ', ' . $data . ', ' . $success_disabled . ', ' . $error_text . ')"';
        return $this->getButton($class, $attributes);
    }
    private static function isSensitiveUrlQueryField(string $id): bool
    {
        $id = RegEx::replace('(?<!\A)[A-Z]', '_$0', $id, '');
        $id = RegEx::replace('[^a-z0-9]+', '_', $id, 'i');
        $id = strtolower($id);
        return (bool) RegEx::match('(?:\A|_)(?:account_?id|api_?key|authentication_?key|credential|key|licen[cs]e_?key|login|password|passwd|secret|token|username)(?:_|\z)', $id, $match, '');
    }
    private function getButton(string $class = 'btn btn-success', string $attributes_string = ''): string
    {
        $icon = $this->get('icon', '') ? 'icon-' . $this->get('icon', '') : '';
        $attributes_string = $attributes_string ? ' ' . $attributes_string : '';
        return '<button type="button" id="' . $this->id . '" class="' . $class . '"' . ' title="' . JText::_($this->get('description')) . '"' . $attributes_string . '>' . '<span class="' . $icon . '"></span> ' . '<span>' . JText::_($this->get('text', $this->get('label'))) . '</span>' . '</button>' . '<div id="message_' . $this->id . '"></div>';
    }
    private function getFieldValueExpression(string $id): string
    {
        $name_prefix = $this->form->getFormControl() . '\\\\[' . $this->group . '\\\\]';
        $id_prefix = $this->form->getFormControl() . '_' . $this->group . '_';
        $element_name = 'document.querySelector(`input[name=' . $name_prefix . '\\\\[' . $id . '\\\\]]:checked`)';
        $element_id = 'document.querySelector(`#' . $id_prefix . $id . '`)';
        return $element_name . ' ? ' . $element_name . '.value : (' . $element_id . ' ? ' . $element_id . '.value : ``)';
    }
    private function getMappedData(string $attribute, bool $for_url = \false, ?bool $sensitive = null, bool $has_previous = \false): string
    {
        $mapping = $this->get($attribute);
        if (!$mapping) {
            return '';
        }
        $data = [];
        foreach (explode(',', $mapping) as $mapping_part) {
            $parts = explode(':', $mapping_part, 2);
            if (count($parts) !== 2) {
                continue;
            }
            [$key, $id] = $parts;
            $is_sensitive = self::isSensitiveUrlQueryField($id);
            if ($sensitive !== null && $is_sensitive !== $sensitive) {
                continue;
            }
            $prefix = $for_url || $has_previous || $data ? '&' : '';
            $data[] = '`' . $prefix . $key . '=` + encodeURIComponent(' . $this->getFieldValueExpression($id) . ')';
        }
        return implode(' + ', $data);
    }
}
