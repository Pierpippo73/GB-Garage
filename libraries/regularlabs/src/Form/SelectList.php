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
namespace RegularLabs\Library\Form;

defined('_JEXEC') or die;
use Joomla\CMS\HTML\HTMLHelper as JHtml;
use Joomla\CMS\Language\Text as JText;
use Joomla\CMS\Layout\FileLayout as JFileLayout;
use Joomla\Registry\Registry;
use RegularLabs\Library\Application;
use RegularLabs\Library\ArrayHelper;
use RegularLabs\Library\Cache;
use RegularLabs\Library\Input;
use RegularLabs\Library\Language;
use RegularLabs\Library\Parameters;
use RegularLabs\Library\StringHelper;
use RegularLabs\Library\User;
class SelectList
{
    public static function getSelectedOptions(\RegularLabs\Library\Form\FormField $field, array $attributes, ?array $selected): ?array
    {
        if ($selected === null || !method_exists($field, 'getNamesByIds') || !empty($attributes['text_as_value']) || !empty($attributes['key'])) {
            return null;
        }
        $options = [];
        foreach ($selected as $value) {
            $names = match (\true) {
                (string) $value === '-1' && !empty($attributes['show_all']) => [JText::_('JALL')],
                $value === 'current' && !empty($attributes['show_current']) => [JText::_('RL_CURRENT_USER')],
                $value === 'custom' && !empty($attributes['show_custom']) => [JText::_('RL_CUSTOM')],
                default => $field->getNamesByIds([$value], $attributes),
            };
            $options[] = ['value' => (string) $value, 'label' => self::getOptionLabel(reset($names) ?: (string) $value), 'disabled' => \false, 'group' => ''];
        }
        return $options;
    }
    public static function filterOptions(array $options, string $search, ?array $selected, int $limit): array
    {
        $result = [];
        $selected_values = $selected === null ? null : array_map('strval', $selected);
        foreach (self::normalizeOptions($options) as $option) {
            if ($selected_values !== null && !in_array($option['value'], $selected_values, \true)) {
                continue;
            }
            if ($search !== '' && StringHelper::stristr($option['label'] . ' ' . $option['value'] . ' ' . $option['group'], $search) === \false) {
                continue;
            }
            $result[] = $option;
            if ($limit && count($result) >= $limit) {
                break;
            }
        }
        return $result;
    }
    public static function render(array $data): string
    {
        $field_class = $data['field_class'];
        $field = new $field_class();
        $field->parent_request = $data['parent_request'];
        $attributes = $data['attributes'];
        $values = is_array($data['value']) ? $data['value'] : ArrayHelper::toListValue($data['value']);
        $values = array_values(array_filter($values, static fn($value) => (string) $value !== '' && $value !== '[]'));
        $values = array_values(array_unique($values));
        $selected = $values ? $field->getOptionsForLookup($attributes, '', $values) : [];
        $by_value = [];
        foreach ($selected as $option) {
            $by_value[(string) $option['value']] = $option;
        }
        $options = [];
        if (empty($attributes['multiple'])) {
            $options[] = JHtml::_('select.option', '', '');
        }
        foreach ($values as $value) {
            $option = $by_value[(string) $value] ?? ['label' => (string) $value, 'disabled' => \false];
            $options[] = JHtml::_('select.option', $value, strip_tags($option['label']), 'value', 'text', $option['disabled']);
        }
        $data['type'] = 'lookup';
        $data = \RegularLabs\Library\Form\Form::signAjaxData($data);
        $params = Parameters::getPlugin('regularlabs');
        $renderer = new JFileLayout('regularlabs.form.field.lazyselect', JPATH_SITE . '/libraries/regularlabs/layouts');
        return $renderer->render(['data' => $data, 'mode' => $params->list_search_mode ?? 'auto', 'list_mode' => self::getInitialMode($field, $data), 'options' => $options, 'attributes' => $attributes, 'values' => $values]);
    }
    public static function lookup(\RegularLabs\Library\Form\FormField $field, Registry $data): string
    {
        $params = Parameters::getPlugin('regularlabs');
        $threshold = max(1, (int) ($params->list_search_threshold ?? 100));
        $search = trim(Input::getString('search'));
        $search = StringHelper::substr($search, 0, 200);
        $searching = $search !== '';
        $attributes = (array) $data->get('attributes', []);
        if (!$searching && ($params->list_search_mode ?? 'auto') === 'always') {
            return json_encode(['mode' => 'search', 'options' => [], 'more' => \false], \JSON_THROW_ON_ERROR);
        }
        $limit = $searching ? 100 : $threshold;
        $offset = $searching ? min(max(0, Input::getInt('offset')), \PHP_INT_MAX - $limit - 1) : 0;
        $options = $field->getOptionsForLookup($attributes, $search, null, $offset + $limit + 1);
        $more = count($options) > $offset + $limit;
        if (!$searching && count($options) >= $threshold) {
            return json_encode(['mode' => 'search', 'options' => [], 'more' => \false], \JSON_THROW_ON_ERROR);
        }
        return json_encode(['mode' => $searching ? 'search' : 'list', 'options' => array_slice($options, $offset, $limit), 'more' => $more], \JSON_THROW_ON_ERROR);
    }
    public static function normalizeOptions(array $options, string $group = ''): array
    {
        $result = [];
        foreach ($options as $key => $option) {
            if (is_array($option)) {
                $result = [...$result, ...self::normalizeOptions($option, is_string($key) ? $key : $group)];
                continue;
            }
            if (!isset($option->value) || (string) $option->value === '' || !empty($option->no_checkbox)) {
                continue;
            }
            $result[] = ['value' => (string) $option->value, 'label' => self::getOptionLabel((string) $option->text), 'disabled' => !empty($option->disable) || !empty($option->disabled), 'group' => self::getOptionLabel($group)];
        }
        return $result;
    }
    private static function getInitialMode(\RegularLabs\Library\Form\FormField $field, array $data): string
    {
        $params = Parameters::getPlugin('regularlabs');
        if (($params->list_search_mode ?? 'auto') === 'always') {
            return 'search';
        }
        $threshold = max(1, (int) ($params->list_search_threshold ?? 100));
        $attributes = $data['attributes'];
        // Selected-order HTML varies per row without changing the candidate list.
        unset($attributes['dataAttribute']);
        $cache = new Cache([__METHOD__, get_class($field), $attributes, $data['parent_request'], $threshold, Application::isAdministrator(), User::getId(), Language::getTag()]);
        if ($cache->exists()) {
            return $cache->get();
        }
        $count = \RegularLabs\Library\Form\SelectListOptions::count($field, $attributes, $threshold);
        return $cache->set($count >= $threshold ? 'search' : 'pending');
    }
    private static function getOptionLabel(string $label): string
    {
        return StringHelper::decodeHtmlEntities(strip_tags($label));
    }
}
