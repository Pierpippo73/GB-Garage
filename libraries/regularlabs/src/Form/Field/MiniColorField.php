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
use RegularLabs\Library\ArrayHelper;
use RegularLabs\Library\Document;
use RegularLabs\Library\Form\FormField;
class MiniColorField extends FormField
{
    public function getInput()
    {
        $class = trim('rl-mini-colors ' . $this->get('class'));
        $table = $this->get('table');
        $item_id = $this->get('item_id');
        $id_column = $this->get('id_column') ?: 'id';
        $disabled = $this->get('disabled') ? ' disabled="disabled"' : '';
        $colors = $this->get('colors', 'none,#c0c6cf,#000000,#dc2a28,#fb6b14,#ffa813,#eac90a,#18a047,#0f9aa4,#115dda,#761bda,#d319a4');
        $colors = str_replace('none', 'transparent', $colors);
        Document::scriptOptions(['swatches' => ArrayHelper::toArray($colors)], 'minicolors');
        Document::script('regularlabs.script');
        Document::script('regularlabs.mini-colors');
        Document::style('regularlabs.mini-colors');
        return '<div class="rl-mini-colors">' . '<input type="text" name="' . $this->name . '" id="' . $this->id . '"' . ' class="' . $class . '" value="' . $this->value . '"' . $disabled . ' data-rl-mini-colors data-table="' . $table . '" data-item_id="' . $item_id . '" data-id_column="' . $id_column . '"' . '>' . '</div>';
    }
}
