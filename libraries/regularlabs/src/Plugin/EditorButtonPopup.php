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
use RegularLabs\Library\RegEx;
use Exception;
use Joomla\CMS\HTML\HTMLHelper as JHtml;
use Joomla\CMS\Language\Text as JText;
use Joomla\CMS\Session\Session;
use Joomla\Registry\Registry as JRegistry;
use RegularLabs\Library\DB;
use RegularLabs\Library\Document;
use RegularLabs\Library\Extension;
use RegularLabs\Library\Language;
use RegularLabs\Library\Parameters;
use RegularLabs\Library\Protect;
class EditorButtonPopup
{
    public $editor_name = '';
    public $form;
    public $params;
    protected $extension = '';
    protected $main_type = 'plugin';
    protected $require_core_auth = \true;
    private $_params;
    public function render(): void
    {
        if (!Session::checkToken('request')) {
            throw new Exception(JText::_("ALERTNOTAUTH"));
        }
        if (!Extension::isAuthorised($this->require_core_auth)) {
            throw new Exception(JText::_("ALERTNOTAUTH"));
        }
        $this->params = $this->getParams();
        if (!Extension::isEnabledInArea($this->params)) {
            throw new Exception(JText::_("ALERTNOTAUTH"));
        }
        $this->loadLanguages();
        $doc = Document::get();
        $asset_manager = Document::getAssetManager();
        $template_params = $this->getTemplateParams();
        // Get the hue value
        RegEx::matchesEntireString('hsla?\(([0-9]+)[\D]+([0-9]+)[\D]+([0-9]+)[\D]+([0-9](?:.\d+)?)?\)', $template_params->get('hue', 'hsl(214, 63%, 20%)'), $matches, 'i');
        $asset_manager->useScript('core');
        $asset_manager->addInlineStyle(':root {
                --hue: ' . $matches[1] . ';
                --template-bg-light: ' . $template_params->get('bg-light', '--template-bg-light') . ';
                --template-text-dark: ' . $template_params->get('text-dark', '--template-text-dark') . ';
                --template-text-light: ' . $template_params->get('text-light', '--template-text-light') . ';
                --template-link-color: ' . $template_params->get('link-color', '--template-link-color') . ';
                --template-special-color: ' . $template_params->get('special-color', '--template-special-color') . ';
            }');
        // Browsers support SVG favicons
        $doc->addHeadLink(JHtml::_('image', 'joomla-favicon.svg', '', [], \true, 1), 'icon', 'rel', ['type' => 'image/svg+xml']);
        $doc->addHeadLink(JHtml::_('image', 'favicon.ico', '', [], \true, 1), 'alternate icon', 'rel', ['type' => 'image/vnd.microsoft.icon']);
        $doc->addHeadLink(JHtml::_('image', 'joomla-favicon-pinned.svg', '', [], \true, 1), 'mask-icon', 'rel', ['color' => '#000']);
        Document::loadEditorPopupAssets();
        $this->init();
        $this->loadScripts();
        $this->loadStyles();
        echo $this->renderTemplate();
    }
    public function renderTemplate(): string
    {
        $path = \RegularLabs\Library\Plugin\EditorButtonPopupTemplate::getPath($this);
        ob_start();
        include $path;
        $html = ob_get_contents();
        ob_end_clean();
        return $html;
    }
    protected function getParams(): object
    {
        if (!is_null($this->_params)) {
            return $this->_params;
        }
        switch ($this->main_type) {
            case 'component':
                if (Protect::isComponentInstalled($this->extension)) {
                    // Load component parameters
                    $this->_params = Parameters::getComponent($this->extension);
                }
                break;
            case 'plugin':
            default:
                if (Protect::isSystemPluginInstalled($this->extension)) {
                    // Load plugin parameters
                    $this->_params = Parameters::getPlugin($this->extension);
                }
                break;
        }
        return $this->_params;
    }
    protected function getTemplateParams(): JRegistry
    {
        $db = DB::get();
        $query = DB::getQuery()->select(DB::quoteName('s.params'))->from(DB::quoteName('#__template_styles', 's'))->where(DB::is('s.template', 'atum'))->order(DB::quoteName('s.home'));
        $db->setQuery($query, 0, 1);
        $template = $db->loadObject();
        return new JRegistry($template->params ?? null);
    }
    protected function init(): void
    {
    }
    protected function loadLanguages(): void
    {
        Language::load('joomla', JPATH_ADMINISTRATOR);
        Language::load('plg_system_regularlabs');
        Language::load('plg_editors-xtd_' . $this->extension);
        Language::load('plg_system_' . $this->extension);
    }
    protected function loadScripts(): void
    {
    }
    protected function loadStyles(): void
    {
    }
}
