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
namespace RegularLabs\Library;

defined('_JEXEC') or die;
use DOMDocument;
use Joomla\CMS\Component\ComponentHelper as JComponentHelper;
use Joomla\CMS\Plugin\PluginHelper as JPluginHelper;
class Parameters
{
    /**
     * Get a usable parameter object for the component
     */
    public static function getComponent(string $name, object|array|string|null $params = null, bool $use_cache = \true): object
    {
        $name = 'com_' . \RegularLabs\Library\RegEx::replaceAtStart('com_', '', $name);
        $cache = new \RegularLabs\Library\Cache(['parameters.get_component', $name, $params]);
        if ($use_cache && $cache->exists()) {
            return $cache->getAsObject();
        }
        if (empty($params) && JComponentHelper::isInstalled($name)) {
            $params = JComponentHelper::getParams($name);
        }
        return $cache->set(self::getObjectFromData($params, JPATH_ADMINISTRATOR . '/components/' . $name . '/config.xml'));
    }
    /**
     * Returns an array based on the data in a given xml file
     */
    public static function getDataFromXmlPath(string $path, bool $use_cache = \true, bool $full_info = \false, string $default_name = 'default'): object
    {
        $file_exists = $path && file_exists($path);
        $modified_time = $file_exists ? filemtime($path) : 0;
        $cache = new \RegularLabs\Library\Cache(['parameters.get_data_from_xml_path', $path, $modified_time, $full_info, $default_name]);
        if ($use_cache) {
            $cache->useFiles();
        }
        if ($use_cache && $cache->exists()) {
            return $cache->getAsObject();
        }
        if (!$file_exists) {
            return $cache->set((object) []);
        }
        $doc = new DOMDocument();
        if (!@$doc->load($path)) {
            return $cache->set((object) []);
        }
        $result = [];
        foreach ($doc->getElementsByTagName('field') as $field) {
            if (!$field->hasAttribute('name')) {
                continue;
            }
            $name = $field->getAttribute('name');
            $type = strtolower($field->getAttribute('type'));
            if (str_starts_with($name, '@') || $type === 'subform' || $field->parentNode?->nodeName === 'form') {
                continue;
            }
            $value = $field->getAttribute('default');
            if ($default_name !== 'default' && $field->hasAttribute($default_name)) {
                $value = $field->getAttribute($default_name ?: 'default');
            }
            if ($type === 'textarea') {
                $value = str_replace('<br>', "\n", $value);
            }
            if (!$full_info) {
                $result[$name] = $value;
                continue;
            }
            $attrs = [];
            foreach ($field->attributes as $attr) {
                $attrs[strtolower($attr->name)] = $attr->value;
            }
            $attrs['multiple'] ??= 'false';
            $attrs['value'] = $value;
            $result[$name] = (object) $attrs;
        }
        return $cache->set((object) $result);
    }
    /**
     * Get a usable parameter object for the module
     */
    public static function getModule(string $name, bool $admin = \true, object|array|string|null $params = null, bool $use_cache = \true): object
    {
        $name = 'mod_' . \RegularLabs\Library\RegEx::replaceAtStart('mod_', '', $name);
        $cache = new \RegularLabs\Library\Cache(['parameters.get_module', $name, $admin, $params]);
        if ($use_cache && $cache->exists()) {
            return $cache->getAsObject();
        }
        if (empty($params)) {
            $params = null;
        }
        return $cache->set(self::getObjectFromData($params, ($admin ? JPATH_ADMINISTRATOR : JPATH_SITE) . '/modules/' . $name . '/' . $name . '.xml'));
    }
    public static function getObjectFromData(object|array|string|null $params, string $path = '', bool $use_cache = \true, string $default_name = ''): object
    {
        $cache = new \RegularLabs\Library\Cache(['parameters.get_object_from_data', $path, $default_name, $params]);
        if ($use_cache && $cache->exists()) {
            return $cache->getAsObject();
        }
        $data = self::getDataFromXmlPath($path, $use_cache, \false, $default_name);
        if (empty($params)) {
            return $cache->set($data);
        }
        if (is_string($params)) {
            $params = \RegularLabs\Library\Json::toObject($params);
        }
        if (is_array($params)) {
            $params = (object) $params;
        }
        if (is_object($params) && method_exists($params, 'toObject')) {
            $params = $params->toObject();
        }
        if (!$params) {
            return $cache->set($data);
        }
        if (empty($data)) {
            return $cache->set($params);
        }
        foreach ($data as $key => $val) {
            if (isset($params->{$key}) && $params->{$key} != '') {
                continue;
            }
            $params->{$key} = $val;
        }
        return $cache->set($params);
    }
    /**
     * @deprecated: use getObjectFromData
     */
    public static function getObjectFromRegistry(object|array|string|null $params, string $path = '', string $default_name = '', bool $use_cache = \true): object
    {
        return self::getObjectFromData($params, $path, $use_cache, $default_name);
    }
    /**
     * Get a usable parameter object for the plugin
     */
    public static function getPlugin(string $name, string $type = 'system', object|array|string|null $params = null, bool $use_cache = \true): object
    {
        $cache = new \RegularLabs\Library\Cache(['parameters.get_plugin', $type, $name, $params]);
        if ($use_cache && $cache->exists()) {
            return $cache->getAsObject();
        }
        if (empty($params)) {
            $plugin = JPluginHelper::getPlugin($type, $name);
            $params = is_object($plugin) && isset($plugin->params) ? $plugin->params : null;
        }
        return $cache->set(self::getObjectFromData($params, JPATH_PLUGINS . '/' . $type . '/' . $name . '/' . $name . '.xml'));
    }
    /**
     * @deprecated: use getDataFromXmlPath
     */
    public static function loadXML(string $path, ?string $default_name = '', bool $use_cache = \true, bool $full_info = \false): array
    {
        return (array) self::getDataFromXmlPath($path, $use_cache, $full_info, $default_name);
    }
    public static function overrideFromObject(object $params, ?object $object = null): object
    {
        if (empty($object)) {
            return $params;
        }
        foreach ($params as $key => $value) {
            if (!isset($object->{$key})) {
                continue;
            }
            $params->{$key} = $object->{$key};
        }
        return $params;
    }
}
