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
use Joomla\CMS\Layout\FileLayout as JFileLayout;
use RegularLabs\Library\Document;
use RegularLabs\Library\DownloadKey;
use RegularLabs\Library\Form\FormField;
class DownloadKeyField extends FormField
{
    public function renderField($options = [])
    {
        if (!DownloadKey::canManageCurrentSettings()) {
            return '';
        }
        return parent::renderField($options);
    }
    protected function getInput()
    {
        if (!DownloadKey::canManageCurrentSettings()) {
            return '';
        }
        Document::script('regularlabs.script');
        Document::script('regularlabs.downloadkey');
        return (new JFileLayout('regularlabs.form.field.downloadkey', JPATH_SITE . '/libraries/regularlabs/layouts'))->render(['id' => $this->id, 'extension' => strtolower($this->get('extension', 'all'))]);
    }
}
