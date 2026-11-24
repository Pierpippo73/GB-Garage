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
namespace RegularLabs\Library\Plugin;

defined('_JEXEC') or die;
use Joomla\CMS\Plugin\PluginHelper as JPluginHelper;
trait SystemPassState
{
    private function isFrameworkEnabled(): bool
    {
        if (!defined('REGULAR_LABS_LIBRARY_ENABLED')) {
            $this->setIsFrameworkEnabled();
        }
        if (!REGULAR_LABS_LIBRARY_ENABLED) {
            $this->throwError('REGULAR_LABS_LIBRARY_NOT_ENABLED');
        }
        return REGULAR_LABS_LIBRARY_ENABLED;
    }
    private function resetPass(): void
    {
        $this->_pass = null;
    }
    private function setIsFrameworkEnabled(): void
    {
        if (!JPluginHelper::isEnabled('system', 'regularlabs')) {
            $this->throwError('REGULAR_LABS_LIBRARY_NOT_ENABLED');
            define('REGULAR_LABS_LIBRARY_ENABLED', \false);
            return;
        }
        define('REGULAR_LABS_LIBRARY_ENABLED', \true);
    }
    private function setPass(bool $pass): void
    {
        if (!$this->_doc_ready) {
            return;
        }
        $this->_pass = (bool) $pass;
    }
}
