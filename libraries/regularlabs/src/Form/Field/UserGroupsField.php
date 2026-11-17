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
use RegularLabs\Library\RegEx;
use RegularLabs\Library\DB;
use RegularLabs\Library\Form\FormField;
use RegularLabs\Library\Json;
use RegularLabs\Library\User;
use SimpleXMLElement;
class UserGroupsField extends FormField
{
    static $options;
    public bool $is_select_list = \true;
    public bool $use_tree_select = \true;
    //    public bool $use_ajax        = true;
    public function getNamesByIds(array $values, array $attributes): array
    {
        $query = $this->db->getQuery(\true)->select('a.title')->from('#__usergroups AS a')->where(DB::is('a.id', $values))->order('a.lft ASC');
        $this->db->setQuery($query);
        return $this->db->loadColumn();
    }
    public function setup(SimpleXMLElement $element, $value, $group = null)
    {
        $this->use_tree_select = !in_array(strtolower((string) $element['treeselect']), ['0', 'false'], \true);
        $this->use_ajax = !$this->use_tree_select;
        $values = is_string($value) ? Json::decode($value, null, \true) : null;
        $values = is_array($values) ? $values : $value;
        if (self::hasSymbolicValue($values)) {
            $value = User::resolveUserGroupIds($values);
            $multiple = strtolower((string) $element['multiple']);
            if (!in_array($multiple, ['1', 'true', 'multiple'], \true)) {
                $value = $value[0] ?? '';
            }
        }
        return parent::setup($element, $value, $group);
    }
    protected function getOptions()
    {
        if (empty(self::$options)) {
            $query = $this->db->getQuery(\true)->select('a.id as value, a.title as text, a.parent_id AS parent')->from('#__usergroups AS a')->select('COUNT(DISTINCT b.id) AS level')->join('LEFT', '#__usergroups AS b ON a.lft > b.lft AND a.rgt < b.rgt')->group('a.id')->order('a.lft ASC');
            $this->db->setQuery($query);
            self::$options = $this->db->loadObjectList();
        }
        $options = self::$options;
        if (!$this->get('treeselect', \true)) {
            foreach ($options as $key => $option) {
                $options[$key] = clone $option;
                $options[$key]->text = str_repeat('- ', (int) $option->level) . $option->text;
            }
        }
        if (!$this->get('show_all')) {
            return $options;
        }
        array_unshift($options, (object) ['value' => -1, 'text' => JText::_('JALL'), 'parent' => 0, 'level' => 0]);
        return $options;
    }
    private static function hasSymbolicValue(mixed $value): bool
    {
        foreach ((array) $value as $group) {
            if (is_string($group) && !RegEx::matchesEntireString('-?[0-9]+', $group, $match, '')) {
                return \true;
            }
        }
        return \false;
    }
}
