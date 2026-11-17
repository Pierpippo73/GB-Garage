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

namespace RegularLabs\Plugin\System\RegularLabs;

defined('_JEXEC') or die;

use RegularLabs\Library\Application as RL_Application;
use RegularLabs\Library\Document as RL_Document;
use RegularLabs\Library\Http as RL_Http;
use RegularLabs\Library\Input as RL_Input;
use RegularLabs\Library\RegEx as RL_RegEx;
use RegularLabs\Library\Template as RL_Template;

class QuickPage
{
    public static function render(): void
    {
        if ( ! RL_Input::getInt('rl_qp', 0))
        {
            return;
        }

        $url = RL_Input::getString('url', '');

        if ($url)
        {
            echo RL_Http::getFromServer($url, RL_Input::getInt('timeout', ''));

            die;
        }

        if ( ! self::passClassCheck())
        {
            die;
        }

        self::setRequestOptionToContent();
        self::setHeaderContentType();
        self::loadTemplateAssets();

        echo self::getHtml();

        die;
    }

    private static function getClassName(): string
    {
        return '\\RegularLabs\\' . str_replace('.', '\\', RL_Input::getString('class', ''));
    }

    private static function getHtml(): string
    {
        $class = self::getClassName();

        ob_start();
        (new $class)->render();
        $html = ob_get_clean() ?: '';

        RL_Document::setComponentBuffer($html);

        $app = new Application;
        $app->render();

        $html = RL_Document::getBody() ?? '';

        $html = RL_RegEx::replace('\s*<link [^>]*href="[^"]*templates/system/[^"]*\.css[^"]*"[^>]*( /)?>', '', $html);
        $html = RL_RegEx::replace('(<body [^>]*class=")', '\1rl-popup ', $html);
        $html = str_replace('<body>', '<body class="rl-popup">', $html);

        return $html;
    }

    private static function loadTemplateAssets(): void
    {
        $asset_manager = RL_Document::getAssetManager();

        if (is_null($asset_manager))
        {
            return;
        }

        $template = RL_Template::get();
        $clientId = RL_Application::getClientId();

        $paramsColorName = $template->params->get('colorName', 'colors_standard');

        if ( ! empty($template->parent))
        {
            $asset_manager->getRegistry()->addTemplateRegistryFile($template->parent, $clientId);
            $asset_manager->registerAndUseStyle('theme.' . $template->parent . '.' . $paramsColorName, 'media/templates/site/' . $template->parent . '/css/global/' . $paramsColorName . '.css');
        }

        $asset_manager->getRegistry()->addTemplateRegistryFile($template->template, $clientId);
        $asset_manager->registerAndUseStyle('theme.' . $template->template . '.' . $paramsColorName, 'media/templates/site/' . $template->template . '/css/global/' . $paramsColorName . '.css');
    }

    private static function passClassCheck(): bool
    {
        $class = RL_Input::getString('class', '');

        if ( ! $class)
        {
            return false;
        }

        $allowed = [
            'Library.RegularExpressionsGuide',
            'Plugin.EditorButton.ArticlesAnywhere.Popup',
            'Plugin.EditorButton.ConditionalContent.Popup',
            'Plugin.EditorButton.ContentTemplater.Data',
            'Plugin.EditorButton.ContentTemplater.Popup',
            'Plugin.EditorButton.DummyContent.Popup',
            'Plugin.EditorButton.Modals.Popup',
            'Plugin.EditorButton.ModulesAnywhere.Popup',
            'Plugin.EditorButton.Sliders.data.php',
            'Plugin.EditorButton.Sliders.Popup',
            'Plugin.EditorButton.Snippets.Popup',
            'Plugin.EditorButton.Sourcerer.Popup',
            'Plugin.EditorButton.TabsAccordions.Popup',
            'Plugin.EditorButton.Tooltips.Popup',
        ];

        return in_array($class, $allowed, true);
    }

    private static function setHeaderContentType(): void
    {
        switch (RL_Input::getCmd('format', 'html'))
        {
            case 'json' :
                $format = 'application/json';
                break;

            default:
            case 'html' :
                $format = 'text/html';
                break;
        }

        header('Content-Type: ' . $format . '; charset=utf-8');
    }

    private static function setRequestOptionToContent(): void
    {
        $_REQUEST['tmpl'] = 'component';
        RL_Input::set('option', 'com_content');
    }
}
