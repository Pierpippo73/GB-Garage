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
class GeneratedSecretField extends FormField
{
    protected $layout = 'regularlabs.form.field.generatedsecret';
    protected function getInput()
    {
        Document::script('regularlabs.generated-secret');
        return $this->getRenderer($this->layout)->render($this->getLayoutData());
    }
    protected function getLayoutData()
    {
        $data = parent::getLayoutData();
        $data = [...$data, 'maxLength' => '', 'pattern' => '', 'inputmode' => '', 'dirname' => '', 'addonBefore' => '', 'addonAfter' => '', 'options' => [], 'class' => trim('font-monospace ' . $this->get('class')), 'charcounter' => \false, 'dataAttribute' => trim(($data['dataAttribute'] ?? '') . ' data-rl-generated-secret-input'), 'format' => $this->getFormat(), 'bytes' => max(8, min(64, (int) $this->get('bytes', 16))), 'word_count' => max(1, min(16, (int) $this->get('word_count', 8))), 'word_length' => max(0, min(64, (int) $this->get('word_length', 0))), 'separator' => substr((string) $this->get('separator', '-'), 0, 3) ?: '-', 'preview' => substr(trim((string) $this->get('preview', '')), 0, 64), 'generate_on_empty' => (bool) $this->get('generate_on_empty', \false), 'button_text' => (string) $this->get('button_text', 'RL_GENERATE_NEW'), 'can_generate' => !$this->disabled && !$this->readonly];
        return $data;
    }
    protected function getLayoutPaths()
    {
        $paths = parent::getLayoutPaths();
        $paths[] = JPATH_LIBRARIES . '/regularlabs/layouts';
        return $paths;
    }
    private function getFormat(): string
    {
        $format = (string) $this->get('format', 'hex');
        return in_array($format, ['hex', 'base64url', 'words'], \true) ? $format : 'hex';
    }
}
