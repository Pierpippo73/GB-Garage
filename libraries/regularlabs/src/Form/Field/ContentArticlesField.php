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
use RegularLabs\Library\DB;
use RegularLabs\Library\Form\FormField;
class ContentArticlesField extends FormField
{
    public bool $is_select_list = \true;
    public bool $use_ajax = \true;
    public function getNamesByIds(array $values, array $attributes): array
    {
        $query = $this->db->getQuery(\true)->from('#__content AS i')->select('i.id, i.title as name, i.language, c.title as category, i.state as published')->join('LEFT', '#__categories AS c ON c.id = i.catid')->where(DB::is('i.id', $values))->order('i.title, i.ordering, i.id');
        return $this->getNamesByQuery($query, ['language', 'category', 'id', 'unpublished']);
    }
    protected function getOptions()
    {
        if ($this->max_list_count) {
            $query = $this->db->getQuery(\true)->select('COUNT(*)')->from('#__content AS i')->where('i.access > -1')->where('i.state > -1');
            $this->db->setQuery($query);
            $total = $this->db->loadResult();
            if ($total > $this->max_list_count) {
                return -1;
            }
        }
        $id = 'i.id';
        $extras = ['language', 'category', 'id', 'unpublished'];
        if ($this->get('id_alias_name_as_value', 0)) {
            $id = 'CONCAT(i.id, "::", i.alias, "::", i.title) AS id';
            $extras = ['language', 'category', 'id_number', 'unpublished'];
        }
        $query->clear('select')->select($id . ', i.id AS id_number, i.title AS name, i.language, c.title AS category, i.state AS published')->join('LEFT', '#__categories AS c ON c.id = i.catid')->order('i.title, i.ordering, i.id');
        $options = $this->getOptionsByQuery($query, $extras);
        if ($this->get('showselect')) {
            array_unshift($options, JHtml::_('select.option', '-', '&nbsp;', 'value', 'text', \true));
            array_unshift($options, JHtml::_('select.option', '-', '- ' . JText::_('Select Item') . ' -'));
        }
        return $options;
    }
}
