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
namespace RegularLabs\Library\Plugin;

defined('_JEXEC') or die;
use Joomla\CMS\Extension\PluginInterface;
use Joomla\CMS\Plugin\PluginHelper as JPluginHelper;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use Joomla\Event\DispatcherInterface as JDispatcherInterface;
use RegularLabs\Library\Application;
class ServiceProvider
{
    public static function systemPlugin(string $folder, string $class, string $baseClass = \RegularLabs\Library\Plugin\System::class): ?ServiceProviderInterface
    {
        if (!self::canLoadSystemPlugin($baseClass)) {
            return null;
        }
        return new class($folder, $class) implements ServiceProviderInterface
        {
            public function __construct(private readonly string $folder, private readonly string $class)
            {
            }
            public function register(Container $container): void
            {
                $container->set(PluginInterface::class, function (Container $container) {
                    $plugin = new $this->class($container->get(JDispatcherInterface::class), (array) JPluginHelper::getPlugin('system', $this->folder));
                    $plugin->setApplication(Application::get());
                    return $plugin;
                });
            }
        };
    }
    private static function canLoadSystemPlugin(string $baseClass): bool
    {
        if (version_compare(JVERSION, 4, '<') || version_compare(JVERSION, 7, '>=')) {
            return \false;
        }
        if (self::isInstallerActionRequest()) {
            return \false;
        }
        if (!is_file(JPATH_LIBRARIES . '/regularlabs/regularlabs.xml')) {
            return \false;
        }
        return class_exists($baseClass);
    }
    private static function isInstallerActionRequest(): bool
    {
        $input = Application::getCurrent()->input;
        $option = $input->getCmd('option');
        if (!in_array($option, ['com_installer', 'com_regularlabsmanager'], \true)) {
            return \false;
        }
        if ($input->getCmd('action') != '') {
            return \true;
        }
        return in_array($input->getCmd('task'), ['install.install', 'install.ajax_upload', 'process.install'], \true);
    }
}
