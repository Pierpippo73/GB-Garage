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
use Joomla\CMS\Cache\CacheControllerFactoryInterface as JCacheControllerFactoryInterface;
use Joomla\CMS\Factory as JFactory;
use Joomla\CMS\User\UserFactoryInterface;
use Joomla\DI\Container;
/**
 * @deprecated Use the focused Regular Labs Library wrappers instead.
 */
class Runtime
{
    public static function getApplication(): JCMSApplicationInterface
    {
        return \RegularLabs\Library\Application::getCurrent();
    }
    public static function getCacheControllerFactory(): JCacheControllerFactoryInterface
    {
        return self::getContainer()->get(JCacheControllerFactoryInterface::class);
    }
    public static function getConfig(string $key, mixed $default = null): mixed
    {
        return \RegularLabs\Library\Config::get($key, $default);
    }
    public static function getContainer(): Container
    {
        return JFactory::getContainer();
    }
    public static function getSiteApplication(): JCMSApplicationInterface
    {
        return \RegularLabs\Library\Application::getSite();
    }
    public static function getUserFactory(): UserFactoryInterface
    {
        return self::getContainer()->get(UserFactoryInterface::class);
    }
}
