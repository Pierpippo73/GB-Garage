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
use Joomla\CMS\Application\CMSApplicationInterface as JCMSApplicationInterface;
use Joomla\CMS\Factory as JFactory;
class Application
{
    public static function get(): JCMSApplicationInterface
    {
        if (\RegularLabs\Library\Indexer::isActive()) {
            return self::getForFinder();
        }
        return self::getCurrent();
    }
    public static function getClientId(int $default = 0): int
    {
        $application = self::get();
        if (!method_exists($application, 'getClientId')) {
            return $default;
        }
        return (int) $application->getClientId();
    }
    public static function getCurrent(): JCMSApplicationInterface
    {
        return JFactory::getApplication();
    }
    public static function getSite(): JCMSApplicationInterface
    {
        return JFactory::getContainer()->get('Joomla\CMS\Application\SiteApplication');
    }
    public static function isAdministrator(): bool
    {
        return self::getCurrent()->isClient('administrator');
    }
    public static function isCli(): bool
    {
        return self::getCurrent()->isClient('cli');
    }
    public static function isClient(string $identifier): bool
    {
        $identifier = $identifier == 'admin' ? 'administrator' : $identifier;
        return self::getCurrent()->isClient($identifier);
    }
    public static function isSite(): bool
    {
        return self::getCurrent()->isClient('site');
    }
    private static function getForFinder(): JCMSApplicationInterface
    {
        $application = self::getSite();
        $language = $application->getLanguage();
        if (is_null($language)) {
            $language = self::getCurrent()->getLanguage() ?? JFactory::getLanguage();
            if (method_exists($application, 'setLanguage')) {
                $application->setLanguage($language);
            } elseif (method_exists($application, 'loadLanguage')) {
                (new \ReflectionMethod($application, 'loadLanguage'))->invoke($application, $language);
            }
        }
        if (method_exists($application, 'getDocument') && method_exists($application, 'loadDocument') && is_null($application->getDocument())) {
            $application->loadDocument();
        }
        return $application;
    }
}
