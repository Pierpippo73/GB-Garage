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
use Joomla\CMS\Event\AbstractEvent as JAbstractEvent;
use Joomla\Event\EventInterface as JEventInterface;
class Event
{
    public static function dispatch(string $event, array|JEventInterface $arguments = []): JEventInterface
    {
        return self::dispatchWithApplication(\RegularLabs\Library\Application::get(), $event, $arguments);
    }
    public static function dispatchCurrent(string $event, array|JEventInterface $arguments = []): JEventInterface
    {
        return self::dispatchWithApplication(\RegularLabs\Library\Application::getCurrent(), $event, $arguments);
    }
    public static function trigger(string $event, array $arguments = []): array
    {
        return \RegularLabs\Library\Application::get()->triggerEvent($event, $arguments);
    }
    public static function triggerCurrent(string $event, array $arguments = []): array
    {
        return \RegularLabs\Library\Application::getCurrent()->triggerEvent($event, $arguments);
    }
    private static function dispatchWithApplication(object $application, string $event, array|JEventInterface $arguments): JEventInterface
    {
        if ($arguments instanceof JEventInterface) {
            return $application->getDispatcher()->dispatch($event, $arguments);
        }
        return $application->getDispatcher()->dispatch($event, JAbstractEvent::create($event, $arguments));
    }
}
