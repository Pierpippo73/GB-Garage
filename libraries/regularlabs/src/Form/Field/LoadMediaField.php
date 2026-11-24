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
use RegularLabs\Library\Document;
use RegularLabs\Library\Form\FormField;
class LoadMediaField extends FormField
{
    protected function getInput()
    {
        return '';
    }
    protected function getLabel()
    {
        $filetype = $this->get('filetype');
        $file = $this->get('file');
        switch ($filetype) {
            case 'style':
                Document::style($file);
                break;
            case 'script':
                Document::script($file);
                break;
            default:
                break;
        }
        return '';
    }
}
