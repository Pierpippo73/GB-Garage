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
use RegularLabs\Library\DB;
use RegularLabs\Library\Form\FormField;
use RegularLabs\Library\Form\SelectListOptions;
class AccessLevelsField extends FormField
{
    static $options;
    public bool $is_select_list = \true;
    public function getNamesByIds(array $values, array $attributes): array
    {
        $query = $this->db->getQuery(\true)->select('a.title')->from('#__viewlevels AS a')->where(DB::is('a.id', $values))->order('a.ordering ASC');
        $this->db->setQuery($query);
        return $this->db->loadColumn();
    }
    protected function getOptions()
    {
        if (!$this->lookup_active && !is_null(self::$options)) {
            return self::$options;
        }
        $query = $this->db->getQuery(\true)->select('a.id as value, a.title as text')->from('#__viewlevels AS a')->order('a.ordering ASC');
        $this->applyLookupToQuery($query, ['a.title', 'a.id'], 'a.id');
        if ($this->lookup_active) {
            return SelectListOptions::load($this, $query, $this->lookup_limit);
        }
        self::$options = SelectListOptions::load($this, $query, $this->lookup_limit);
        return self::$options;
    }
}
