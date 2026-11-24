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
use ReflectionClass;
use RegularLabs\Library\Input;
class EditorButtonPopupTemplate
{
    public static function getPath(object $popup): string
    {
        $layout = Input::getCmd('layout', '');
        $file = 'popup' . ($layout ? '.' . $layout : '') . '.php';
        return dirname(self::getDirectory($popup)) . '/tmpl/' . $file;
    }
    public static function render(object $popup): string
    {
        if (!is_callable([$popup, 'renderTemplate'])) {
            return self::renderLegacyTemplate($popup);
        }
        return $popup->renderTemplate();
    }
    private static function getDirectory(object $popup): string
    {
        $reflection = new ReflectionClass($popup);
        return dirname($reflection->getFileName());
    }
    private static function renderLegacyTemplate(object $popup): string
    {
        ob_start();
        include self::getPath($popup);
        $html = ob_get_contents();
        ob_end_clean();
        return $html;
    }
}
