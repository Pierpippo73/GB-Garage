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

defined('_JEXEC') or die;

use Joomla\CMS\Cache\CacheControllerFactoryInterface;
use Joomla\CMS\Cache\Controller\CallbackController;
use Joomla\CMS\Factory as JFactory;
use Joomla\Filesystem\File as JFile;

if ( ! class_exists('RegularLabsInstallerScript'))
{
    class RegularLabsInstallerScript
    {
        public function postflight($install_type, $adapter)
        {
            if ( ! in_array($install_type, ['install', 'update']))
            {
                return true;
            }

            self::deleteOldFiles();

            /** @var CallbackController $cache */
            $cache = JFactory::getContainer()->get(CacheControllerFactoryInterface::class)->createCacheController('callback', ['defaultgroup' => '_system']);
            $cache->clean();

            return true;
        }

        private static function delete(array $files = []): void
        {
            foreach ($files as $file)
            {
                if (is_file($file))
                {
                    JFile::delete($file);
                }
            }
        }

        private static function deleteOldFiles(): void
        {
            self::delete(
                [
                    JPATH_SITE . '/libraries/regularlabs/layouts/regularlabs/form/field/downloadkey_modal.php',
                    JPATH_SITE . '/libraries/regularlabs/layouts/regularlabs/form/field/downloadkey_modal_body.php',
                ]
            );
        }
    }
}
