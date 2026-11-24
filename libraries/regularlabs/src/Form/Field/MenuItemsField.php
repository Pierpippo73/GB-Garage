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
use Joomla\CMS\Language\Multilanguage as JMultilanguage;
use Joomla\CMS\Language\Text as JText;
use Joomla\Component\Menus\Administrator\Helper\MenusHelper as JMenusHelper;
use RegularLabs\Library\Form\FormField;
use RegularLabs\Library\Form\SelectListOptions;
use RegularLabs\Library\DB;
use RegularLabs\Library\Language;
use RegularLabs\Library\RegEx;
class MenuItemsField extends FormField
{
    public bool $collapse_children = \true;
    public bool $is_select_list = \true;
    public bool $use_ajax = \true;
    public bool $use_tree_select = \true;
    public function getNamesByIds(array $ids): array
    {
        if (empty($ids)) {
            return [];
        }
        $items = array_fill_keys($ids, '');
        $menu_types = [];
        $menu_ids = [];
        foreach ($ids as $id) {
            if (str_starts_with((string) $id, 'type.')) {
                $menu_types[] = substr($id, 5);
                continue;
            }
            $menu_ids[] = $id;
        }
        $query = $this->db->getQuery(\true)->select('id, title')->from('#__menu')->where('client_id = 0')->where('published != -2')->where(DB::is('id', $menu_ids));
        foreach ($this->db->setQuery($query)->loadObjectList() as $item) {
            $items[$item->id] = $item->title;
        }
        $query = $this->db->getQuery(\true)->select('menutype, title')->from('#__menu_types')->where('client_id = 0')->where(DB::is('menutype', $menu_types));
        foreach ($this->db->setQuery($query)->loadObjectList() as $type) {
            $items['type.' . $type->menutype] = $type->title . ' <span class="small">(' . JText::_('JALL') . ')</span>';
        }
        return $items;
    }
    protected function getOptions()
    {
        Language::load('com_modules', JPATH_ADMINISTRATOR);
        $menuTypes = $this->lookup_active ? $this->getMenuTypesForLookup() : JMenusHelper::getMenuLinks();
        $options = [];
        foreach ($menuTypes as &$type) {
            $option = (object) ['value' => 'type.' . $type->menutype, 'text' => $type->title, 'level' => 0, 'class' => 'hidechildren', 'labelclass' => 'nav-header'];
            $options[] = $option;
            foreach ($type->links as $link) {
                $check1 = RegEx::replace('[^a-z0-9]', '', strtolower($link->text));
                $check2 = RegEx::replace('[^a-z0-9]', '', $link->alias);
                $text = [];
                $text[] = $link->text;
                if ($check1 !== $check2) {
                    $text[] = '<small class="text-muted">[' . $link->alias . ']</small>';
                }
                if (in_array($link->type, ['separator', 'heading', 'alias', 'url'], \true)) {
                    $text[] = '<span class="badge bg-secondary">' . JText::_('COM_MODULES_MENU_ITEM_' . strtoupper($link->type)) . '</span>';
                    // Don't disable, as you need to be able to select the 'Also on Child Items' option
                    // $link->disable = 1;
                }
                if (JMultilanguage::isEnabled() && $link->language != '' && $link->language != '*') {
                    $text[] = $link->language_image ? JHtml::_('image', 'mod_languages/' . $link->language_image . '.gif', $link->language_title, ['title' => $link->language_title], \true) : '<span class="badge bg-secondary" title="' . $link->language_title . '">' . $link->language_sef . '</span>';
                }
                $link->text = implode(' ', $text);
                $options[] = $link;
            }
        }
        return $options;
    }
    private function getMenuTypesForLookup(): array
    {
        $query = $this->db->getQuery(\true)->select('menutype, title')->from('#__menu_types')->where('client_id = 0')->where('menutype != ' . $this->db->quote(''))->order('menutype');
        $types = $this->db->setQuery($query)->loadObjectList('menutype');
        $query = $this->db->getQuery(\true)->select('a.id AS value, a.title AS text, a.alias, a.level, a.menutype, a.type, a.language')->from('#__menu AS a')->where('a.client_id = 0')->where('a.published != -2')->where(DB::is('a.menutype', array_keys($types)))->order('a.lft');
        if (JMultilanguage::isEnabled()) {
            $query->select('l.title AS language_title, l.image AS language_image, l.sef AS language_sef')->join('LEFT', '#__languages AS l ON l.lang_code = a.language');
        }
        $this->applyLookupToQuery($query, ['a.title', 'a.alias', 'a.id'], 'a.id');
        $links = SelectListOptions::load($this, $query, $this->lookup_limit);
        foreach ($types as $type) {
            $type->links = [];
        }
        foreach ($links as $link) {
            $types[$link->menutype]->links[] = $link;
        }
        return array_values($types);
    }
}
