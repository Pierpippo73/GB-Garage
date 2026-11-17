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
use RegularLabs\Library\Form\Form;
use RegularLabs\Library\Form\FormField;
class UsersField extends FormField
{
    static $users;
    static $users_count;
    public $attributes = ['show_current' => \false];
    public bool $is_select_list = \true;
    public bool $use_ajax = \true;
    public function getOptionByListItem(object $item, array $extras = [], int $levelOffset = 0, string $key = 'id'): object
    {
        if ($key !== 'identifiers') {
            return parent::getOptionByListItem($item, $extras, $levelOffset, $key);
        }
        $item = clone $item;
        $item->identifiers = implode('|', [rawurlencode((string) $item->id), rawurlencode((string) $item->username), rawurlencode((string) $item->email)]);
        return parent::getOptionByListItem($item, $extras, $levelOffset, $key);
    }
    public function getNamesByIds(array $values, array $attributes): array
    {
        $value_field = $this->getValueField();
        $query_field = $value_field;
        if ($value_field === 'identifiers') {
            $query_field = 'id';
            $values = $this->getIdsFromIdentifierValues($values);
        }
        $query = $this->db->getQuery(\true)->select('u.name, u.username, u.email, u.id, u.block as disabled')->from('#__users AS u')->where(DB::is('u.' . $query_field, $values))->order('name');
        $this->db->setQuery($query);
        $users = $this->db->loadObjectList();
        if (in_array('current', $values)) {
            array_unshift($users, (object) ['id' => 'current', 'name' => JText::_('RL_CURRENT_USER'), 'add_id' => \false]);
        }
        return Form::getNamesWithExtras($users, ['username', 'id', 'disabled']);
    }
    protected function getListOptions(array $attributes): array|int
    {
        if ($this->max_list_count && $this->getUsersCount() > $this->max_list_count) {
            return -1;
        }
        $users = $this->getUsers();
        $options = $this->getOptionsByList($users, ['username', 'id', 'disabled'], 0, $this->getValueField());
        if (!empty($attributes['show_current'])) {
            array_unshift($options, JHtml::_('select.option', 'current', '- ' . JText::_('RL_CURRENT_USER') . ' -'));
        }
        return $options;
    }
    private function getUsers(): array
    {
        if (!is_null(self::$users)) {
            return self::$users;
        }
        $query = $this->db->getQuery(\true)->select('u.name, u.username, u.email, u.id, u.block as disabled')->from('#__users AS u')->order('name');
        $this->db->setQuery($query);
        self::$users = $this->db->loadObjectList();
        return self::$users;
    }
    private function getUsersCount(): int
    {
        if (!is_null(self::$users_count)) {
            return self::$users_count;
        }
        $query = $this->db->getQuery(\true)->select('COUNT(*)')->from('#__users AS u');
        $this->db->setQuery($query);
        self::$users_count = $this->db->loadResult();
        return self::$users_count;
    }
    private function getIdsFromIdentifierValues(array $values): array
    {
        $ids = [];
        foreach ($values as $value) {
            $parts = explode('|', (string) $value, 2);
            $ids[] = rawurldecode($parts[0]);
        }
        return $ids;
    }
    private function getValueField(): string
    {
        $value_field = $this->get('value_field', $this->get('username_as_value') ? 'username' : 'id');
        return in_array($value_field, ['id', 'username', 'email', 'identifiers'], \true) ? $value_field : 'id';
    }
}
