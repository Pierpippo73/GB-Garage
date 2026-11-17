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
use JsonException;
use Joomla\CMS\Layout\FileLayout as JFileLayout;
class DownloadKey
{
    private const EXTENSION_COMPONENTS = ['advancedmodulemanager' => 'com_advancedmodules', 'all' => 'com_regularlabsmanager'];
    public static function canManageCurrentSettings(): bool
    {
        $option = \RegularLabs\Library\Input::getCmd('option');
        if ($option === 'com_plugins') {
            return self::canManagePluginSettings();
        }
        if ($option === 'com_config') {
            $option = \RegularLabs\Library\Input::getCmd('component');
        }
        return self::canManageComponentSettings($option);
    }
    public static function canManageExtension(string $extension): bool
    {
        if (self::canManagePluginSettings()) {
            return \true;
        }
        $extension = strtolower($extension);
        $component = self::EXTENSION_COMPONENTS[$extension] ?? 'com_' . $extension;
        return self::canManageComponentSettings($component);
    }
    public static function get(bool $update = \true): string
    {
        $db = \RegularLabs\Library\DB::get();
        $query = \RegularLabs\Library\DB::getQuery()->select('extra_query')->from('#__update_sites')->where(\RegularLabs\Library\DB::like(\RegularLabs\Library\DB::quoteName('extra_query'), 'k=%'))->where(\RegularLabs\Library\DB::like(\RegularLabs\Library\DB::quoteName('location'), '%download.regularlabs.com%'));
        $db->setQuery($query);
        $key = $db->loadResult();
        if (!$key) {
            return '';
        }
        \RegularLabs\Library\RegEx::match('#k=([a-zA-Z0-9]{8}[A-Z0-9]{8})#', $key, $match);
        if (!$match[1]) {
            return '';
        }
        $key = $match[1];
        if ($update) {
            self::store($key);
        }
        return $key;
    }
    public static function getOutputForComponent(
        string $extension = 'all',
        bool $use_modal = \true,
        // Retained for older installed extensions; the field is always inline.
        bool $hidden = \true,
        string $callback = ''
    ): string
    {
        if (!self::canManageCurrentSettings()) {
            return '';
        }
        $id = 'downloadkey_' . strtolower($extension);
        \RegularLabs\Library\Document::script('regularlabs.script');
        \RegularLabs\Library\Document::script('regularlabs.downloadkey');
        return (new JFileLayout('regularlabs.form.field.downloadkey', JPATH_SITE . '/libraries/regularlabs/layouts'))->render(['id' => $id, 'extension' => strtolower($extension), 'hidden' => $hidden, 'callback' => $callback, 'show_label' => \true]);
    }
    public static function isValid(string $key, string $extension = 'all'): string
    {
        $key = trim($key);
        if (!self::isValidFormat($key)) {
            return json_encode(['valid' => \false, 'active' => \false]);
        }
        $cache = new \RegularLabs\Library\Cache();
        $cache->useFiles(1);
        if ($cache->exists()) {
            return $cache->get();
        }
        $result = \RegularLabs\Library\Http::fetchRegularLabsServerUrl('https://download.regularlabs.com/check_key.php?e=' . rawurlencode($extension), 20, '', ['k' => $key]);
        $result = self::normalizeValidationResult($result);
        if ($result === self::getExternalErrorResponse()) {
            return $result;
        }
        return $cache->set($result);
    }
    public static function isValidFormat(string $key): bool
    {
        $key = trim($key);
        if ($key === '') {
            return \true;
        }
        if (strlen($key) != 16) {
            return \false;
        }
        return \RegularLabs\Library\RegEx::matchesEntireString('[a-zA-Z0-9]{8}[A-Z0-9]{8}', $key, $match, 's');
    }
    public static function store(string $key): bool
    {
        if (!self::isValidFormat($key)) {
            return \false;
        }
        $query = \RegularLabs\Library\DB::getQuery()->update('#__update_sites')->set(\RegularLabs\Library\DB::is('extra_query', ''))->where(\RegularLabs\Library\DB::like(\RegularLabs\Library\DB::quoteName('location'), '%download.regularlabs.com%'));
        \RegularLabs\Library\DB::get()->setQuery($query)->execute();
        $extra_query = $key ? 'k=' . $key : '';
        $query = \RegularLabs\Library\DB::getQuery()->update('#__update_sites')->set(\RegularLabs\Library\DB::is('extra_query', $extra_query))->where(\RegularLabs\Library\DB::like(\RegularLabs\Library\DB::quoteName('location'), '%download.regularlabs.com%'))->where(\RegularLabs\Library\DB::combine([\RegularLabs\Library\DB::like(\RegularLabs\Library\DB::quoteName('location'), '%&pro=%'), \RegularLabs\Library\DB::like(\RegularLabs\Library\DB::quoteName('location'), '%e=extensionmanager%')], 'OR'));
        $result = \RegularLabs\Library\DB::get()->setQuery($query)->execute();
        \RegularLabs\Library\Extension::cleanCache('_system');
        return $result;
    }
    private static function canManageComponentSettings(string $component): bool
    {
        if (!\RegularLabs\Library\Document::isAdministrator() || !str_starts_with($component, 'com_')) {
            return \false;
        }
        return \RegularLabs\Library\User::authorise('core.admin', $component) || \RegularLabs\Library\User::authorise('core.options', $component);
    }
    private static function canManagePluginSettings(): bool
    {
        return \RegularLabs\Library\Document::isAdministrator() && \RegularLabs\Library\User::authorise('core.edit', 'com_plugins');
    }
    private static function getExternalErrorResponse(): string
    {
        return json_encode(['valid' => \false, 'active' => \false, 'error' => 'external']);
    }
    private static function normalizeValidationResult(string $result): string
    {
        try {
            $data = \RegularLabs\Library\Json::toArray($result, [], \true);
        } catch (JsonException) {
            return self::getExternalErrorResponse();
        }
        if (!is_array($data) || !is_bool($data['valid'] ?? null) || !is_bool($data['active'] ?? null)) {
            return self::getExternalErrorResponse();
        }
        return json_encode(['valid' => $data['valid'], 'active' => $data['active']]);
    }
}
