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
use RegularLabs\Library\Extension;
use RegularLabs\Library\RequestContext;
trait EditorButtonChecks
{
    private function isInstalled(): bool
    {
        $extensions = !is_null($this->check_installed) ? $this->check_installed : [$this->main_type];
        return Extension::areInstalled($this->_name, $extensions);
    }
    private function passChecks(): bool
    {
        if (!is_null($this->_pass)) {
            return $this->_pass;
        }
        $this->_pass = \false;
        if (!Extension::isFrameworkEnabled()) {
            return \false;
        }
        if (!Extension::isAuthorised($this->require_core_auth)) {
            return \false;
        }
        if (!$this->isInstalled()) {
            return \false;
        }
        if (!$this->enable_on_acymailing && RequestContext::fromInput()->isOption('com_acymailing')) {
            return \false;
        }
        $params = $this->getParams();
        if (!Extension::isEnabledInComponent($params)) {
            return \false;
        }
        if (!Extension::isEnabledInArea($params)) {
            return \false;
        }
        if (!$this->extraChecks($params)) {
            return \false;
        }
        $this->_pass = \true;
        return \true;
    }
}
