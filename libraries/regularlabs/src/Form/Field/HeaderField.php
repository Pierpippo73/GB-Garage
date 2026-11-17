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
namespace RegularLabs\Library\Form\Field;

defined('_JEXEC') or die;
use Joomla\CMS\Installer\Installer as JInstaller;
use Joomla\CMS\Language\Text as JText;
use RegularLabs\Library\Form\FormField;
use RegularLabs\Library\Message;
use RegularLabs\Library\RegEx;
use RegularLabs\Library\StringHelper;
use RegularLabs\Library\Version;
class HeaderField extends FormField
{
    protected function getInput()
    {
        $title = $this->get('label');
        $jversion = Version::getMajorJoomlaVersion();
        if ($jversion != 4) {
            Message::enqueue(JText::sprintf('RL_NOT_COMPATIBLE_WITH_JOOMLA_VERSION', JText::_($title), $jversion), 'error');
            return '';
        }
        $description = $this->get('description');
        $xml = $this->get('xml');
        $url = $this->get('url');
        $this->description = '';
        if ($description) {
            $description = StringHelper::decodeHtmlEntities(trim(JText::_($description)));
        }
        if ($title) {
            $title = JText::_($title);
        }
        if ($description) {
            // Replace inline monospace style with rl_code classname
            $description = str_replace('span style="font-family:monospace;"', 'span class="rl_code"', $description);
            // 'Break' plugin style tags
            $description = str_replace(['{', '['], ['<span>{</span>', '<span>[</span>'], $description);
            // Wrap in paragraph (if not already starting with an html tag)
            if ($description[0] != '<') {
                $description = '<p>' . $description . '</p>';
            }
        }
        if (!$xml && $this->form->getValue('element')) {
            if ($this->form->getValue('folder')) {
                $xml = 'plugins/' . $this->form->getValue('folder') . '/' . $this->form->getValue('element') . '/' . $this->form->getValue('element') . '.xml';
            } else {
                $xml = 'administrator/modules/' . $this->form->getValue('element') . '/' . $this->form->getValue('element') . '.xml';
            }
        }
        if ($xml) {
            $xml = JInstaller::parseXMLInstallFile(JPATH_SITE . '/' . $xml);
            $version = 0;
            if ($xml && isset($xml['version'])) {
                $version = $xml['version'];
            }
            if ($version) {
                if (str_contains($version, 'PRO')) {
                    $version = str_replace('PRO', '', $version);
                    $version .= ' <small style="color:green">[PRO]</small>';
                } elseif (str_contains($version, 'FREE')) {
                    $version = str_replace('FREE', '', $version);
                    $version .= ' <small style="color:green">[FREE]</small>';
                }
                if ($title) {
                    $title .= ' v';
                } else {
                    $title = JText::_('Version') . ' ';
                }
                $title .= $version;
            }
        }
        $html = [];
        if ($title) {
            if ($url) {
                $title = '<a href="' . $url . '" target="_blank" title="' . RegEx::replace('<[^>]*>', '', $title) . '">' . $title . '</a>';
            }
            $html[] = '<h4>' . StringHelper::decodeHtmlEntities($title) . '</h4>';
        }
        if ($description) {
            $html[] = $description;
        }
        if ($url) {
            $html[] = '<p><a href="' . $url . '" class="btn btn-outline-info" target="_blank" title="' . JText::_('RL_MORE_INFO') . '">' . JText::_('RL_MORE_INFO') . ' >></a></p>';
        }
        return $this->getControlGroupEnd() . implode('', $html) . $this->getControlGroupStart();
    }
    protected function getLabel()
    {
        return '';
    }
}
