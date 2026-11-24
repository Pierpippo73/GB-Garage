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

defined('_JEXEC') or die;

use Joomla\CMS\Factory as JFactory;
use Joomla\CMS\Installer\Manifest\PackageManifest as JPackageManifest;
use Joomla\CMS\Language\Text as JText;
use Joomla\Database\DatabaseInterface as JDatabaseInterface;

if ( ! class_exists('pkg_regularlabsInstallerScript'))
{
    class pkg_regularlabsInstallerScript
    {
        static $current_version;
        static $name;
        static $package_name;
        static $previous_version;

        public function postflight($install_type, $adapter)
        {
            if ( ! in_array($install_type, ['install', 'update']))
            {
                return true;
            }

            $source_path = self::getSourcePath($adapter);

            try
            {
                RegularLabsPackageLibraryInstaller::register($source_path, static::$package_name);
                if (static::$package_name === 'regularlabs')
                {
                    require_once $source_path . '/packages/lib_regularlabs/script.install.php';
                    (new RegularLabsInstallerScript)->postflight($install_type, $adapter);
                }

                RegularLabsPackageNamespaceMap::refresh();
                self::displayMessages();
                RegularLabsPackagePluginState::restore(static::$package_name);
                self::publishExtensions();

                return true;
            }
            finally
            {
                try
                {
                    RegularLabsPackagePluginState::restore(static::$package_name);
                }
                finally
                {
                    RegularLabsPackageLibraryLock::release();
                }
            }
        }

        public function preflight($install_type, $adapter)
        {
            if ( ! in_array($install_type, ['install', 'update']))
            {
                return true;
            }

            $source_path = self::getSourcePath($adapter);

            require_once $source_path . '/package.library.lock.php';
            require_once $source_path . '/package.library.installer.php';
            require_once $source_path . '/package.namespace.map.php';
            require_once $source_path . '/package.plugin.state.php';

            if ( ! RegularLabsPackageLibraryLock::acquire())
            {
                return false;
            }

            try
            {
                $manifest = $adapter->getManifest();

                static::$package_name = trim($manifest->packagename);

                RegularLabsPackagePluginState::suspend(static::$package_name);
                RegularLabsPackageLibraryInstaller::install($source_path);
            }
            catch (Throwable $error)
            {
                try
                {
                    RegularLabsPackagePluginState::restore(static::$package_name);
                }
                catch (Throwable)
                {
                    // The shutdown handler retries restoration without hiding the installer error.
                }
                finally
                {
                    RegularLabsPackageLibraryLock::release();
                }

                throw $error;
            }

            static::$name             = trim($manifest->name);
            static::$current_version  = trim($manifest->version);
            static::$previous_version = self::getPreviousVersion();

            return true;
        }

        private static function getSourcePath($adapter)
        {
            $source_path = rtrim((string) $adapter->getParent()->getPath('source'), '/\\');

            if ($source_path === '' || ! is_dir($source_path))
            {
                throw new RuntimeException('Could not find the extracted Regular Labs package.');
            }

            return $source_path;
        }

        private static function displayMessages()
        {
            if (php_sapi_name() == 'cli')
            {
                return;
            }

            $msg = self::getInstallationLanguageString();

            JFactory::getApplication()->enqueueMessage(
                JText::sprintf(
                    $msg,
                    '<strong>' . JText::_(static::$name . '_SHORT') . '</strong>',
                    '<strong>' . static::$current_version . '</strong>'
                ), 'success'
            );
        }

        private static function getInstallationLanguageString()
        {
            if ( ! static::$previous_version)
            {
                return 'PKG_RL_EXTENSION_INSTALLED';
            }

            if (static::$previous_version == static::$current_version)
            {
                return 'PKG_RL_EXTENSION_REINSTALLED';
            }

            return 'PKG_RL_EXTENSION_UPDATED';
        }

        private static function getPreviousVersion()
        {
            $xml_file = self::getXmlFile();

            if ( ! $xml_file)
            {
                return '';
            }

            $manifest = new JPackageManifest($xml_file);

            return isset($manifest->version) ? trim($manifest->version) : '';
        }

        private static function getXmlFile()
        {
            $xml_file = JPATH_MANIFESTS . '/packages/pkg_' . static::$package_name . '.xml';

            if (file_exists($xml_file))
            {
                return $xml_file;
            }

            $xml_file = JPATH_LIBRARIES . '/' . static::$package_name . '.xml';

            if (file_exists($xml_file))
            {
                return $xml_file;
            }

            $xml_file = JPATH_ADMINISTRATOR . '/components/com_' . static::$package_name . '/' . static::$package_name . '.xml';

            if (file_exists($xml_file))
            {
                return $xml_file;
            }

            return '';
        }

        private static function publishExtensions()
        {
            // ignore if this is an update of Conditions
            if (static::$package_name == 'conditions' && static::$previous_version)
            {
                return;
            }

            $db = JFactory::getContainer()->get(JDatabaseInterface::class);

            $query = $db->getQuery(true)
                ->update('#__extensions')
                ->set($db->quoteName('enabled') . ' = 1')
                ->where($db->quoteName('element') . ' IN ('
                    . $db->quote(static::$package_name)
                    . ', ' . $db->quote('com_' . static::$package_name)
                    . ')'
                );

            $db->setQuery($query);
            $db->execute();
        }
    }
}
