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
use RegularLabs\Library\RegEx;
use RegularLabs\Library\StringHelper;
class GeneratedSecretPreviewField extends \RegularLabs\Library\Form\Field\NoteField
{
    protected function getNote()
    {
        $note = parent::getNote();
        $parameter = (string) $this->get('parameter');
        $preview = (string) $this->get('preview');
        if ($note === '' || $parameter === '' || $preview === '') {
            return $note;
        }
        $parts = RegEx::split('(<[^>]+>)', $note, '', -1, \PREG_SPLIT_DELIM_CAPTURE);
        $pendingValue = \false;
        $attribute = ' data-rl-generated-secret-preview="' . StringHelper::escape($preview) . '"';
        foreach ($parts as $index => $part) {
            if ($part === '' || str_starts_with($part, '<')) {
                continue;
            }
            if ($pendingValue) {
                $parts[$index] = '<span' . $attribute . '>' . $part . '</span>';
                $pendingValue = \false;
                continue;
            }
            $urlPosition = strpos($part, 'http');
            $parameterPosition = strpos($part, $parameter);
            if ($urlPosition === \false || $parameterPosition === \false || $parameterPosition < $urlPosition) {
                continue;
            }
            $valuePosition = $parameterPosition + strlen($parameter);
            $value = substr($part, $valuePosition);
            if ($value === '') {
                $pendingValue = \true;
                continue;
            }
            $parts[$index] = substr($part, 0, $valuePosition) . '<span' . $attribute . '>' . $value . '</span>';
        }
        return implode('', $parts);
    }
}
