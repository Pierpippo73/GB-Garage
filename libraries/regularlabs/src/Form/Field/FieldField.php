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
use RegularLabs\Library\ArrayHelper;
use RegularLabs\Library\Cache;
use RegularLabs\Library\DB;
use RegularLabs\Library\Form\Form;
use RegularLabs\Library\Form\FormField;
class FieldField extends FormField
{
    public bool $is_select_list = \true;
    public function getNameById(string $value, array $attributes): string
    {
        return ArrayHelper::implode($this->getNamesByIds([$value], $attributes));
    }
    public function getNamesByIds(array $values, array $attributes): array
    {
        $db = DB::get();
        $query = DB::getQuery()->select('DISTINCT a.id, a.type, a.title as name')->from('#__fields AS a')->where('a.state = 1')->where(DB::is('a.id', $values))->order('a.title');
        $db->setQuery($query);
        $fields = $db->loadObjectList();
        return Form::getNamesWithExtras($fields, ['type']);
    }
    protected function getOptions()
    {
        $fields = $this->getFields();
        $options = [];
        $options[] = JHtml::_('select.option', '', '- ' . JText::_('RL_SELECT_FIELD') . ' -');
        foreach ($fields as $field) {
            $key = $field->{$this->get('key', 'id')} ?? $field->id;
            $options[] = JHtml::_('select.option', $key, $field->title . ' [' . $field->type . ']');
        }
        if ($this->get('show_custom')) {
            $options[] = JHtml::_('select.option', 'custom', '- ' . JText::_('RL_CUSTOM') . ' -');
        }
        return $options;
    }
    private function getFields(): array
    {
        $context = $this->get('context', 'com_content.article');
        $cache = new Cache([__METHOD__, $context]);
        if ($cache->exists()) {
            return $cache->get();
        }
        $db = DB::get();
        $query = DB::getQuery()->select('DISTINCT a.id, a.type, a.name, a.title')->from('#__fields AS a')->where('a.state = 1')->where('a.only_use_in_subform = 0')->where(DB::isNot('a.type', ['subform', 'repeatable']))->where(DB::is('a.context', $context))->order('a.title');
        $db->setQuery($query);
        $fields = $db->loadObjectList();
        return $cache->set($fields);
    }
}
