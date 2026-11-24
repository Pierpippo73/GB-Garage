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

namespace RegularLabs\Plugin\System\RegularLabs;

defined('_JEXEC') or die;

use Joomla\CMS\Plugin\PluginHelper as JPluginHelper;
use RegularLabs\Library\Application as RL_Application;
use RegularLabs\Library\Config as RL_Config;
use RegularLabs\Library\Document as RL_Document;
use RegularLabs\Library\Event as RL_Event;
use RegularLabs\Library\Input as RL_Input;
use RegularLabs\Library\Template as RL_Template;
use RegularLabs\Library\User as RL_User;

class Application
{
    static function getThemesDirectory(): string
    {
        $themes_base = RL_Config::get('themes.base');

        if ($themes_base)
        {
            return $themes_base;
        }

        if (defined('JPATH_THEMES'))
        {
            return JPATH_THEMES;
        }

        if (defined('JPATH_BASE'))
        {
            return JPATH_BASE . '/themes';
        }

        return __DIR__ . '/themes';
    }

    public function render(): void
    {
        $app      = RL_Application::get();
        $document = RL_Document::get();

        if (is_null($document))
        {
            return;
        }

        $asset_manager = RL_Document::getAssetManager();

        if (is_null($asset_manager))
        {
            return;
        }

        $user     = RL_User::get();
        $template = RL_Template::get();
        $clientId = RL_Application::getClientId();

        $asset_manager->getRegistry()->addTemplateRegistryFile($template->template, $clientId);

        $app->loadDocument($document);

        $template_file = RL_Input::getCmd('tmpl', 'index');
        $params        = [
            'template'         => RL_Config::get('theme', $template->template),
            'file'             => RL_Config::get('themeFile', $template_file . '.php'),
            'params'           => $template->params,
            'csp_nonce'        => RL_Config::get('csp_nonce'),
            'templateInherits' => RL_Config::get('themeInherits'),
            'directory'        => self::getThemesDirectory(),
        ];

        // Parse the document.
        $document->parse($params);

        // Trigger the onBeforeRender event.
        JPluginHelper::importPlugin('system');
        RL_Event::dispatch('onBeforeRender', ['subject' => $app]);

        $caching = false;

        if (RL_Application::isSite() && RL_Config::get('caching') && RL_Config::get('caching', 2) == 2 && ! $user->get('id'))
        {
            $caching = true;
        }

        // Render the document.
        $data = $document->render($caching, $params);

        // Set the application output data.
        RL_Document::setBody($data);

        // Trigger the onAfterRender event.
        RL_Event::dispatch('onAfterRender', ['subject' => $app]);

        // Mark afterRender in the profiler.
        // Causes issues, so commented out.
        // JDEBUG ? $app->profiler->mark('afterRender') : null;
    }
}
