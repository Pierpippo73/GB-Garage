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
namespace RegularLabs\Library;

defined('_JEXEC') or die;
use Joomla\CMS\Factory as JFactory;
use Joomla\CMS\Language\Language as JLanguage;
class Language
{
    public static function get(): JLanguage
    {
        return \RegularLabs\Library\Application::get()->getLanguage() ?? \RegularLabs\Library\Application::getCurrent()->getLanguage() ?? JFactory::getLanguage();
    }
    public static function getDefaultAlignment(): string
    {
        return self::get()->isRTL() ? 'right' : 'left';
    }
    public static function getTag(): string
    {
        return self::get()->getTag();
    }
    public static function hasKey(string $key): bool
    {
        return self::get()->hasKey($key);
    }
    public static function isFilterEnabled(): bool
    {
        $application = \RegularLabs\Library\Application::get();
        return method_exists($application, 'getLanguageFilter') && $application->getLanguageFilter();
    }
    /**
     * Load the language of the given extension
     */
    public static function load(string $extension = 'plg_system_regularlabs', string $basePath = '', bool $reload = \false): bool
    {
        if ($basePath && self::get()->load($extension, $basePath, null, $reload)) {
            return \true;
        }
        $basePath = \RegularLabs\Library\Extension::getPath($extension, $basePath, 'language');
        return self::get()->load($extension, $basePath, null, $reload);
    }
}
