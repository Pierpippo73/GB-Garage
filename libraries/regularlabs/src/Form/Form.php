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
namespace RegularLabs\Library\Form;

defined('_JEXEC') or die;
use Joomla\CMS\HTML\HTMLHelper as JHtml;
use Joomla\CMS\Language\Text as JText;
use Joomla\CMS\Layout\FileLayout as JFileLayout;
use Joomla\CMS\Plugin\PluginHelper as JPluginHelper;
use Joomla\Registry\Registry as JRegistry;
use RegularLabs\Library\ArrayHelper;
use RegularLabs\Library\Config;
use RegularLabs\Library\Document;
use RegularLabs\Library\Parameters;
use RegularLabs\Library\RegEx;
use RegularLabs\Library\RequestContext;
use RegularLabs\Library\StringHelper;
class Form
{
    public static function getLayout(array $options, bool $treeselect = \false): string
    {
        if ($treeselect) {
            return 'regularlabs.form.field.treeselect';
        }
        if (is_array(reset($options))) {
            return 'joomla.form.field.groupedlist-fancy-select';
        }
        return 'joomla.form.field.list-fancy-select';
    }
    /**
     * Return a name with added extras and formatting
     */
    public static function getNameWithExtras(object $item, array $extras = []): string
    {
        $name = trim($item->name);
        foreach ($extras as $extra) {
            if ($extra == 'language' && $item->{$extra} == '*') {
                continue;
            }
            if (in_array($extra, ['id', 'alias'], \true) && $item->{$extra} == $item->name) {
                continue;
            }
            if ($extra == 'unpublished') {
                $name .= isset($item->published) && !$item->published ? ' (' . JText::_('JUNPUBLISHED') . ')' : '';
                continue;
            }
            if ($extra == 'disabled') {
                $name .= isset($item->disabled) && $item->disabled ? ' (' . JText::_('JDISABLED') . ')' : '';
                continue;
            }
            if (empty($item->{$extra})) {
                continue;
            }
            if (isset($item->{'add_' . $extra}) && !$item->{'add_' . $extra}) {
                continue;
            }
            $name .= ' [' . $item->{$extra} . ']';
        }
        return self::prepareSelectItem($name);
    }
    /**
     * Return an array with names with added extras and formatting
     */
    public static function getNamesWithExtras(array $items, array $extras = []): array
    {
        $names = [];
        foreach ($items as $item) {
            $names[] = self::getNameWithExtras($item, $extras);
        }
        return $names;
    }
    public static function hasValidAjaxDataSignature(JRegistry $data): bool
    {
        $signature = (string) $data->get('signature', '');
        if ($signature == '') {
            return \false;
        }
        $payload = $data->toArray();
        unset($payload['signature']);
        return hash_equals(self::getAjaxDataSignature($payload), $signature);
    }
    public static function applyAjaxFieldRuntimeIdentity(JRegistry $data, string $name, string $id): bool
    {
        $template_name = (string) $data->get('name', '');
        $template_id = (string) $data->get('id', '');
        if (!self::matchesAjaxFieldRuntimeIdentity($template_name, $name) || !self::matchesAjaxFieldRuntimeIdentity($template_id, $id)) {
            return \false;
        }
        $data->set('name', $name);
        $data->set('id', $id);
        return \true;
    }
    public static function prepareSelectItem(string $string, int $remove_first = 0): string
    {
        if (empty($string)) {
            return '';
        }
        $string = str_replace(['&nbsp;', '&#160;'], ' ', $string);
        $string = RegEx::replaceAtStart('(- )+', '  ', $string);
        for ($i = 0; $remove_first > $i; $i++) {
            $string = RegEx::replaceAtStart('  ', '', $string, '');
        }
        if (RegEx::matchesEntireString('( *)(.*)', $string, $match, '')) {
            [$string, $pre, $name] = $match;
            $pre = str_replace('  ', ' ·  ', $pre);
            $pre = RegEx::replace('(( ·  )*) ·  ', '\1 »  ', $pre);
            $pre = str_replace('  ', ' &nbsp; ', $pre);
            $string = $pre . $name;
        }
        return $string;
    }
    /**
     * Render a full select list
     */
    public static function selectList(int|array $options, string $name, mixed $value, string $id, array $attributes = [], bool $treeselect = \false, bool $collapse_children = \false, null|int $max_list_count = null): string
    {
        if (empty($options)) {
            return '<fieldset class="radio">' . JText::_('RL_NO_ITEMS_FOUND') . '</fieldset>';
        }
        $params = Parameters::getPlugin('regularlabs');
        $max_list_count = $max_list_count ?? $params->max_list_count;
        $value = ArrayHelper::toListValue($value);
        $count = 0;
        if ($max_list_count && $options != -1) {
            foreach ($options as $option) {
                $count++;
                if (isset($option->links)) {
                    $count += count($option->links);
                }
                if ($count > $params->max_list_count) {
                    break;
                }
            }
        }
        if ($options == -1 || $max_list_count && $count > $max_list_count) {
            if (is_array($value)) {
                $value = implode(',', $value);
            }
            if (!$value) {
                $input = '<textarea name="' . $name . '" id="' . $id . '" cols="40" rows="5">' . $value . '</textarea>';
            } else {
                $input = '<input type="text" name="' . $name . '" id="' . $id . '" value="' . $value . '" size="60">';
            }
            $plugin = JPluginHelper::getPlugin('system', 'regularlabs');
            $url = !empty($plugin->id) ? 'index.php?option=com_plugins&task=plugin.edit&extension_id=' . $plugin->id : 'index.php?option=com_plugins&&filter[folder]=&filter[search]=Regular%20Labs%20Library';
            $label = JText::_('RL_ITEM_IDS');
            $text = JText::_('RL_MAX_LIST_COUNT_INCREASE');
            $tooltip = JText::_('RL_MAX_LIST_COUNT_INCREASE_DESC,' . $max_list_count . ',RL_MAX_LIST_COUNT');
            $link = '<a href="' . $url . '" target="_blank" id="' . $id . '_msg"' . ' class="hasPopover rl-max-list-count-message" title="' . $text . '" data-bs-content="' . htmlentities($tooltip) . '">' . '<span class="icon icon-cog"></span>' . $text . '</a>';
            self::loadMaxListCountPopover();
            return '<fieldset class="radio">' . '<label for="' . $id . '">' . $label . ':</label>' . $input . '<br><small>' . $link . '</small>' . '</fieldset>';
        }
        $layout = self::getLayout($options, $treeselect);
        $path = $treeselect ? JPATH_SITE . '/libraries/regularlabs/layouts' : null;
        $data = [...compact('id', 'name', 'value', 'options'), 'multiple' => \false, 'autofocus' => \false, 'onchange' => '', 'dataAttribute' => '', 'readonly' => \false, 'disabled' => '', 'hint' => \false, 'required' => \false, 'collapse_children' => $collapse_children, 'groups' => $options, ...$attributes];
        $renderer = new JFileLayout($layout, $path);
        return $renderer->render($data);
    }
    /**
     * Render a select list loaded via Ajax
     */
    public static function selectListAjax(string $field_class, string $name, mixed $value, string $id, array $attributes = [], bool $treeselect = \false, bool $collapse_children = \false): string
    {
        Document::loadAdminFormAssets();
        self::loadMaxListCountPopover();
        if ($treeselect) {
            Document::loadTreeSelectAssets();
        } else {
            Document::loadFancySelectAssets();
        }
        $submit_inputs = self::getAjaxSubmitInputs($name, $value, !empty($attributes['multiple']));
        if (is_array($value)) {
            $value = implode(',', $value);
        }
        $ajax_data = self::getAjaxFieldData($field_class, $name, $value, $id, $attributes, $treeselect, $collapse_children);
        return '<div class="rl-ajax-wrapper">' . '<textarea name="' . $name . '" id="' . $id . '" cols="40" rows="1" class="form-control rl-ajax-field"' . ' disabled="disabled"' . ' data-rl-ajax="' . StringHelper::escape(json_encode($ajax_data)) . '">' . $value . '</textarea>' . $submit_inputs . '<div class="rl-spinner"></div>' . '</div>';
    }
    public static function signAjaxData(array $data): array
    {
        unset($data['signature']);
        $data['signature'] = self::getAjaxDataSignature($data);
        return $data;
    }
    private static function getAjaxDataSignature(array $data): string
    {
        return hash_hmac('sha256', json_encode(self::normalizeAjaxDataForSignature($data)), (string) Config::get('secret'));
    }
    private static function loadMaxListCountPopover(): void
    {
        JHtml::_('bootstrap.popover', 'body', ['container' => 'body', 'html' => \true, 'selector' => '.rl-max-list-count-message', 'trigger' => 'hover focus']);
    }
    private static function matchesAjaxFieldRuntimeIdentity(string $template, string $runtime): bool
    {
        if ($template === '' || $runtime === '') {
            return \false;
        }
        if ($template === $runtime) {
            return \true;
        }
        $pattern = RegEx::quote($template);
        $pattern = RegEx::replace('X(?=\\\\\\]|__)', '[0-9]+', $pattern, '');
        return (bool) RegEx::matchesEntireString($pattern, $runtime, $match);
    }
    private static function getAjaxFieldData(string $field_class, string $name, mixed $value, string $id, array $attributes, bool $treeselect, bool $collapse_children): array
    {
        $request = RequestContext::fromInput();
        return self::signAjaxData(['parent_request' => ['option' => $request->option, 'view' => $request->view, 'id' => $request->id], 'field_class' => $field_class, 'name' => $name, 'id' => $id, 'value' => $value, 'attributes' => $attributes, 'treeselect' => $treeselect, 'collapse_children' => $collapse_children]);
    }
    private static function getAjaxSubmitInputs(string $name, mixed $value, bool $multiple): string
    {
        $values = $multiple ? ArrayHelper::toListValue($value) : [is_array($value) ? implode(',', $value) : $value];
        if (empty($values)) {
            $values = [''];
        }
        $inputs = [];
        foreach ($values as $value) {
            $inputs[] = '<input type="hidden" class="rl-ajax-submit-value" name="' . StringHelper::escape($name) . '" value="' . StringHelper::escape((string) $value) . '">';
        }
        return implode('', $inputs);
    }
    private static function normalizeAjaxDataForSignature(mixed $data): mixed
    {
        if (is_object($data)) {
            $data = get_object_vars($data);
        }
        if (!is_array($data)) {
            return $data;
        }
        ksort($data);
        foreach ($data as $key => $value) {
            $data[$key] = self::normalizeAjaxDataForSignature($value);
        }
        return $data;
    }
}
