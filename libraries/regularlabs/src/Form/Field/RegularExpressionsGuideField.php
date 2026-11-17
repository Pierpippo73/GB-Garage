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
use SimpleXMLElement;
class RegularExpressionsGuideField extends \RegularLabs\Library\Form\Field\NoteField
{
    public function setup(SimpleXMLElement $element, $value, $group = null)
    {
        $element['class'] = 'rl-alert alert alert-info rl-alert-light';
        $element['text'] = 'RL_HELP_ON_REGULAR_EXPRESSIONS,' . '<a href="index.php?rl_qp=1&amp;class=Library.RegularExpressionsGuide&amp;tmpl=component"' . ' target="_blank" rel="noopener noreferrer">,</a>';
        return parent::setup($element, $value, $group);
    }
}
