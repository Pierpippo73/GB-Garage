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

use DateTimeZone;
use Joomla\CMS\Factory as JFactory;
use Joomla\CMS\User\User as JUser;
use Joomla\CMS\User\UserFactoryInterface as JUserFactoryInterface;
defined('_JEXEC') or die;
class User
{
    private const PUBLIC_DATA_FIELDS = ['id', 'name', 'username', 'email', 'block', 'registerDate', 'lastvisitDate'];
    private const FILTERABLE_DATA_FIELDS = [...self::PUBLIC_DATA_FIELDS, 'sendEmail', 'lastResetTime', 'resetCount', 'requireReset', 'authProvider'];
    private const SAFE_PARAMETER_FIELDS = ['language', 'editor', 'timezone', 'a11y_mono', 'a11y_contrast', 'a11y_highlight', 'a11y_font'];
    public static function authorise(string $action, ?string $assetname = null, ?int $id = null): bool
    {
        return self::get($id)->authorise($action, $assetname) ?? \false;
    }
    public static function get(?int $id = null): JUser
    {
        $user = $id ? self::getFactory()->loadUserById($id) : \RegularLabs\Library\Application::get()->getIdentity();
        return $user ?? self::getFactory()->loadUserById(0);
    }
    public static function getAuthorisedCategories(string $component, string $action, ?int $id = null): array
    {
        return self::get($id)->getAuthorisedCategories($component, $action);
    }
    public static function getAuthorisedGroups(?int $id = null): array
    {
        return self::get($id)->getAuthorisedGroups();
    }
    public static function getAuthorisedViewLevels(?int $id = null): array
    {
        return self::get($id)->getAuthorisedViewLevels();
    }
    public static function getByEmail(string $email): ?JUser
    {
        return self::getByKey('email', $email);
    }
    public static function getById(?int $id = null): ?JUser
    {
        if (!$id) {
            return null;
        }
        $user = static::get($id);
        if ($user->guest) {
            return null;
        }
        return $user;
    }
    public static function getByKey(string $key, string $value): ?JUser
    {
        $id = self::getIdByKey($key, $value);
        if (!$id) {
            return null;
        }
        return self::getById($id);
    }
    public static function getByUsername(string $username): ?JUser
    {
        return self::getByKey('username', $username);
    }
    public static function getEmail(?int $id = null): string
    {
        return (string) self::getValue('email', $id, '');
    }
    public static function getFilterableDataFields(): array
    {
        return self::FILTERABLE_DATA_FIELDS;
    }
    public static function getGuest(): JUser
    {
        return self::getFactory()->loadUserById(0);
    }
    public static function getId(?int $id = null): int
    {
        return (int) self::getValue('id', $id, 0);
    }
    public static function getName(?int $id = null): string
    {
        return (string) self::getValue('name', $id, '');
    }
    public static function getPublicDataFields(): array
    {
        return self::PUBLIC_DATA_FIELDS;
    }
    public static function getSafeParameterFields(): array
    {
        return self::SAFE_PARAMETER_FIELDS;
    }
    public static function getTimezone(?int $id = null): DateTimeZone
    {
        return self::get($id)->getTimezone();
    }
    public static function getUsername(?int $id = null): string
    {
        return (string) self::getValue('username', $id, '');
    }
    public static function getValue(string $key, ?int $id = null, $default = null): mixed
    {
        $user = self::get($id);
        return $user->{$key} ?? $default;
    }
    public static function hasId(int $id): bool
    {
        return self::getId() === $id;
    }
    public static function isAdministrator(?int $id = null): bool
    {
        return self::get($id)->authorise('core.admin') ?? \false;
    }
    public static function isCurrent(int $id): bool
    {
        return self::hasId($id);
    }
    public static function isFilterableDataField(string $key, string $default_key = ''): bool
    {
        $key = self::normalizeDataField($key, $default_key);
        return in_array($key, self::FILTERABLE_DATA_FIELDS, \true) || in_array(\RegularLabs\Library\StringHelper::toUnderscoreCase($key), self::SAFE_PARAMETER_FIELDS, \true);
    }
    public static function isGuest(?int $id = null): bool
    {
        return (bool) self::getValue('guest', $id, \true);
    }
    public static function isPublicDataField(string $key, string $default_key = ''): bool
    {
        $key = self::normalizeDataField($key, $default_key);
        return in_array($key, self::PUBLIC_DATA_FIELDS, \true) || in_array(\RegularLabs\Library\StringHelper::toUnderscoreCase($key), self::SAFE_PARAMETER_FIELDS, \true);
    }
    public static function resolveUserGroupIds(array|int|string $groups): array
    {
        if (is_string($groups)) {
            $decoded_groups = \RegularLabs\Library\Json::decode($groups, null, \true);
            $groups = is_array($decoded_groups) ? $decoded_groups : $groups;
        }
        $groups = \RegularLabs\Library\ArrayHelper::toListValue($groups);
        $ids = [];
        $titles = [];
        foreach ($groups as $group) {
            if (is_int($group) || is_string($group) && \RegularLabs\Library\RegEx::matchesEntireString('-?[0-9]+', $group, $match, '')) {
                $ids[] = (int) $group;
                continue;
            }
            $title = trim((string) $group);
            if ($title != '') {
                $titles[] = $title;
            }
        }
        if (empty($titles)) {
            return array_values(array_unique($ids));
        }
        $db = \RegularLabs\Library\DB::get();
        $query = $db->getQuery(\true)->select(['id', 'title'])->from('#__usergroups')->where(\RegularLabs\Library\DB::is('title', $titles, ['handle_wildcards' => \false]));
        $db->setQuery($query);
        $ids_by_title = [];
        foreach ($db->loadObjectList() as $group) {
            $title_key = \RegularLabs\Library\StringHelper::strtolower($group->title);
            $ids_by_title[$title_key] ??= [];
            $ids_by_title[$title_key][] = (int) $group->id;
        }
        foreach ($titles as $title) {
            $matching_ids = $ids_by_title[\RegularLabs\Library\StringHelper::strtolower($title)] ?? [];
            if (count($matching_ids) === 1) {
                $ids[] = $matching_ids[0];
            }
        }
        return array_values(array_unique($ids));
    }
    private static function getFactory(): JUserFactoryInterface
    {
        return JFactory::getContainer()->get(JUserFactoryInterface::class);
    }
    private static function getIdByKey(string $key, string $value): int
    {
        $cache = new \RegularLabs\Library\Cache();
        if ($cache->exists()) {
            return $cache->get();
        }
        $db = \RegularLabs\Library\DB::get();
        $query = $db->getQuery(\true)->select($db->quoteName('id'))->from($db->quoteName('#__users'))->where($db->quoteName($key) . ' = :value')->bind(':value', $value)->setLimit(1);
        $db->setQuery($query);
        $id = (int) $db->loadResult();
        return $cache->set($id);
    }
    private static function normalizeDataField(string $key, string $default_key): string
    {
        $key = $key === '' ? $default_key : $key;
        return \RegularLabs\Library\StringHelper::toCamelCase($key);
    }
}
