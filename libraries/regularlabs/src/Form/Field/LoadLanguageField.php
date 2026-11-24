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
use RegularLabs\Library\Form\FormField;
use RegularLabs\Library\Language;
class LoadLanguageField extends FormField
{
    protected function getInput()
    {
        $extension = $this->get('extension');
        $admin = (bool) $this->get('admin', 1);
        self::loadLanguage($extension, $admin);
        return '';
    }
    protected function getLabel()
    {
        return '';
    }
    private static function loadLanguage(string $extension, bool $admin = \true): void
    {
        if (!$extension) {
            return;
        }
        Language::load($extension, $admin ? JPATH_ADMINISTRATOR : JPATH_SITE);
    }
}
