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
namespace RegularLabs\Library;

defined('_JEXEC') or die;
use Joomla\Registry\Registry as JRegistry;
class Template
{
    public static function get(): object
    {
        if (\RegularLabs\Library\Indexer::isActive() && \RegularLabs\Library\Application::isAdministrator()) {
            return self::getDefaultSiteTemplate();
        }
        $application = \RegularLabs\Library\Application::get();
        if (!method_exists($application, 'getTemplate')) {
            return self::getEmpty();
        }
        $template = $application->getTemplate(\true);
        if (!is_object($template)) {
            return self::getEmpty((string) $template);
        }
        $template->id ??= 0;
        $template->parent ??= '';
        $template->params = self::normalizeParams($template->params ?? null);
        $template->template ??= '';
        return $template;
    }
    public static function getId(): int
    {
        return (int) self::get()->id;
    }
    public static function getName(): string
    {
        return (string) self::get()->template;
    }
    public static function getParams(): JRegistry
    {
        return self::get()->params;
    }
    private static function getEmpty(string $name = ''): object
    {
        return (object) ['id' => 0, 'parent' => '', 'params' => new JRegistry(), 'template' => $name];
    }
    private static function getDefaultSiteTemplate(): object
    {
        $database = \RegularLabs\Library\DB::get();
        $query = $database->getQuery(\true)->select([$database->quoteName('style.id'), $database->quoteName('style.template'), $database->quoteName('style.params'), $database->quoteName('extension.manifest_cache')])->from($database->quoteName('#__template_styles', 'style'))->join('LEFT', $database->quoteName('#__extensions', 'extension') . ' ON ' . $database->quoteName('extension.element') . ' = ' . $database->quoteName('style.template') . ' AND ' . $database->quoteName('extension.type') . ' = ' . $database->quote('template') . ' AND ' . $database->quoteName('extension.client_id') . ' = ' . $database->quoteName('style.client_id'))->where(\RegularLabs\Library\DB::is('style.client_id', 0))->where(\RegularLabs\Library\DB::is('style.home', 1))->where(\RegularLabs\Library\DB::is('extension.enabled', 1));
        $template = $database->setQuery($query, 0, 1)->loadObject();
        if (!$template) {
            return self::getEmpty();
        }
        $manifest = \RegularLabs\Library\Json::toObject($template->manifest_cache ?? null);
        $template->parent = (string) ($manifest->parent ?? '');
        $template->params = self::normalizeParams($template->params ?? null);
        unset($template->manifest_cache);
        return $template;
    }
    private static function normalizeParams(mixed $params): JRegistry
    {
        if ($params instanceof JRegistry) {
            return $params;
        }
        return new JRegistry($params);
    }
}
