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
use Joomla\CMS\HTML\HTMLHelper as JHtml;
use Joomla\CMS\Language\Text as JText;
use RegularLabs\Library\Form\FormField;
use RegularLabs\Library\Language;
use RegularLabs\Library\RegEx;
class ModuleTypesField extends FormField
{
    private static array|null $module_types = null;
    public bool $is_select_list = \true;
    public bool $use_ajax = \true;
    public function getNamesByIds(array $values, array $attributes): array
    {
        $module_types = [];
        foreach ($this->getModuleTypes() as $module_type) {
            if (in_array($module_type->element, $values, \true)) {
                $module_types[] = $module_type;
            }
        }
        return array_map(fn(object $module_type): string => $this->getModuleTypeName($module_type), $module_types);
    }
    protected function getListOptions(array $attributes): array
    {
        $options = [];
        foreach ($this->getModuleTypes() as $module_type) {
            $options[] = JHtml::_('select.option', $module_type->element, $this->getModuleTypeName($module_type));
        }
        usort($options, static fn(object $first, object $second): int => strnatcasecmp($first->text, $second->text));
        return $options;
    }
    private function getModuleTypeName(object $module_type): string
    {
        $name = (string) $module_type->name;
        if (str_contains($name, ' ')) {
            return $name;
        }
        $element = (string) $module_type->element;
        $language = Language::get();
        $language->load($element . '.sys', JPATH_SITE, null, \false, \false) || $language->load($element . '.sys', JPATH_SITE . '/modules/' . $element, null, \false, \false) || $language->load($element . '.sys', JPATH_SITE, $language->getDefault(), \false, \false) || $language->load($element . '.sys', JPATH_SITE . '/modules/' . $element, $language->getDefault(), \false, \false);
        $translated = JText::_(strtoupper($name));
        if ($translated !== strtoupper($name)) {
            return $translated;
        }
        return ucwords(RegEx::replace('[_-]+', ' ', RegEx::replaceAtStart('mod_', '', $element)));
    }
    private function getModuleTypes(): array
    {
        if (!is_null(self::$module_types)) {
            return self::$module_types;
        }
        $query = $this->db->getQuery(\true)->select($this->db->quoteName(['e.name', 'e.element']))->from($this->db->quoteName('#__extensions', 'e'))->where($this->db->quoteName('e.type') . ' = ' . $this->db->quote('module'))->where($this->db->quoteName('e.client_id') . ' = 0')->where($this->db->quoteName('e.name') . ' != ' . $this->db->quote(''))->where($this->db->quoteName('e.element') . ' != ' . $this->db->quote(''))->group($this->db->quoteName(['e.name', 'e.element']))->order($this->db->quoteName('e.element'));
        $this->db->setQuery($query);
        self::$module_types = $this->db->loadObjectList();
        return self::$module_types;
    }
}
