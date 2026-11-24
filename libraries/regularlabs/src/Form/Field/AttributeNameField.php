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
use Joomla\CMS\Form\Field\TextField as JTextField;
use Joomla\CMS\Language\Text as JText;
use RegularLabs\Library\RegEx;
use Joomla\Registry\Registry;
use RegularLabs\Library\Application;
use RegularLabs\Library\Document;
use RegularLabs\Library\StringHelper;
use SimpleXMLElement;
use UnexpectedValueException;
class AttributeNameField extends JTextField
{
    public static function getInvalidMessage(): string
    {
        return JText::_('RL_ATTRIBUTE_NAME_INVALID');
    }
    public static function getReservedMessage(array $reservedValues): string
    {
        return JText::sprintf('RL_ATTRIBUTE_NAME_RESERVED', self::formatReservedValues($reservedValues));
    }
    public static function isReserved(string $value, array $reservedValues): bool
    {
        $value = StringHelper::toLowerCase($value);
        foreach ($reservedValues as $reservedValue) {
            if ($value === StringHelper::toLowerCase((string) $reservedValue)) {
                return \true;
            }
        }
        return \false;
    }
    public static function sanitize(string $value): string
    {
        return RegEx::replace('[^\p{L}\p{M}\p{N}_-]+', '', $value, 'u');
    }
    public static function sanitizeAndValidate(string $value, array $reservedValues = []): string
    {
        $value = self::sanitize($value);
        if (!RegEx::match('[\p{L}\p{N}]', $value)) {
            throw new UnexpectedValueException(self::getInvalidMessage());
        }
        if (self::isReserved($value, $reservedValues)) {
            throw new UnexpectedValueException(self::getReservedMessage($reservedValues));
        }
        return $value;
    }
    public function filter($value, $group = null, ?Registry $input = null)
    {
        return self::sanitize((string) parent::filter($value, $group, $input));
    }
    public function setup(SimpleXMLElement $element, $value, $group = null)
    {
        $result = parent::setup($element, $value, $group);
        if (!$result) {
            return \false;
        }
        if (!Application::isCli() && Document::isHtml()) {
            Document::script('regularlabs.attribute-name', ['defer' => \true], ['form.validate']);
        }
        $this->class = trim($this->class . ' validate-rlattributename');
        $reservedValues = $this->getReservedValues();
        $this->dataAttributes['data-rl-attribute-name-empty-validation-text'] = self::getInvalidMessage();
        $this->dataAttributes['data-rl-attribute-name-reserved'] = implode(',', $reservedValues);
        $this->dataAttributes['data-validation-text'] = self::getReservedMessage($reservedValues);
        return \true;
    }
    public function validate($value, $group = null, ?Registry $input = null)
    {
        $value = self::sanitize((string) $value);
        $valid = parent::validate($value, $group, $input);
        if ($valid !== \true || $value === '') {
            return $valid;
        }
        try {
            self::sanitizeAndValidate($value, $this->getReservedValues());
        } catch (UnexpectedValueException $exception) {
            return $exception;
        }
        return \true;
    }
    private static function formatReservedValues(array $reservedValues): string
    {
        $reservedValues = array_values(array_map('strval', $reservedValues));
        if (count($reservedValues) < 2) {
            return $reservedValues[0] ?? '';
        }
        $lastValue = array_pop($reservedValues);
        if (count($reservedValues) === 1) {
            return $reservedValues[0] . ' ' . JText::_('RL_OR') . ' ' . $lastValue;
        }
        return implode(', ', $reservedValues) . ', ' . JText::_('RL_OR') . ' ' . $lastValue;
    }
    private function getReservedValues(): array
    {
        $reserved = trim((string) $this->element['reserved']);
        if ($reserved === '') {
            return [];
        }
        $values = RegEx::split('[\s,]+', $reserved, '', -1, \PREG_SPLIT_NO_EMPTY);
        return $values ?: [];
    }
}
