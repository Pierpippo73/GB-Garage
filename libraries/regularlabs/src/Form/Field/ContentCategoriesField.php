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
use RegularLabs\Library\ArrayHelper;
use RegularLabs\Library\DB;
use RegularLabs\Library\Form\FormField;
class ContentCategoriesField extends FormField
{
    public bool $is_select_list = \true;
    public bool $use_ajax = \true;
    public bool $use_tree_select = \true;
    public function getNamesByIds(array $values, array $attributes): array
    {
        $query = $this->db->getQuery(\true)->select('c.id, c.title as name, c.published, c.language')->from('#__categories AS c')->where('c.extension = ' . $this->db->quote('com_content'))->where(DB::is('c.id', $values))->order('c.lft');
        return $this->getNamesByQuery($query, ['language', 'unpublished']);
    }
    protected function getOptions()
    {
        $query = $this->db->getQuery(\true)->from('#__categories as c')->where('c.extension = ' . $this->db->quote('com_content'))->where('c.parent_id > 0')->where('c.published > -1');
        if ($this->max_list_count) {
            $query = $this->db->getQuery(\true)->select('COUNT(*)')->from('#__categories as c')->where('c.extension = ' . $this->db->quote('com_content'))->where('c.parent_id > 0')->where('c.published > -1');
            $this->db->setQuery($query);
            $total = $this->db->loadResult();
            if ($total > $this->max_list_count) {
                return -1;
            }
        }
        $this->value = ArrayHelper::toArray($this->value);
        $query->clear('select')->select('c.id, c.title as name, c.published, c.language, c.level')->order('c.lft');
        $this->applyLookupToQuery($query, ['c.title', 'c.id', 'c.language'], 'c.id');
        return $this->getOptionsByQuery($query, ['language', 'unpublished'], -1);
    }
}
