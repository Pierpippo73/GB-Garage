<?php
/**
 * @package         Sourcerer
 * @version         17.0.1
 * 
 * @author          Peter van Westen <info@regularlabs.com>
 * @link            https://regularlabs.com
 * @copyright       Copyright © 2026 Regular Labs All Rights Reserved
 * @license         GNU General Public License version 2 or later
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory as JFactory;
use Joomla\Database\DatabaseInterface;
use Joomla\Filesystem\File as JFile;
use Joomla\Filesystem\Folder as JFolder;

class PlgSystemSourcererInstallerScript
{
    private const NEW_FORBIDDEN_PHP_FUNCTIONS = [
        'chgrp',
        'chmod',
        'chown',
        'copy',
        'file_put_contents',
        'fopen',
        'fprintf',
        'fputs',
        'ftruncate',
        'fwrite',
        'link',
        'mkdir',
        'move_uploaded_file',
        'rename',
        'rmdir',
        'stream_copy_to_stream',
        'tempnam',
        'tmpfile',
        'touch',
        'unlink',
    ];

    private string $installed_version = '';

    public function preflight($install_type, $adapter)
    {
        if ($install_type != 'update')
        {
            return true;
        }

        $extension = self::getExtension();

        if ( ! is_object($extension))
        {
            return true;
        }

        $manifest               = json_decode($extension->manifest_cache ?? '');
        $this->installed_version = (string) ($manifest->version ?? '');

        return true;
    }

    public function postflight($install_type, $adapter)
    {
        if ( ! in_array($install_type, ['install', 'update']))
        {
            return true;
        }

        if ($install_type == 'update')
        {
            self::migrateSecurityParams($this->installed_version);
        }

        self::deleteOldFiles();

        return true;
    }

    private static function getExtension(): ?object
    {
        $database = JFactory::getContainer()->get(DatabaseInterface::class);
        $query    = $database->getQuery(true)
            ->select($database->quoteName(['extension_id', 'manifest_cache', 'params']))
            ->from($database->quoteName('#__extensions'))
            ->where($database->quoteName('type') . ' = ' . $database->quote('plugin'))
            ->where($database->quoteName('folder') . ' = ' . $database->quote('system'))
            ->where($database->quoteName('element') . ' = ' . $database->quote('sourcerer'));

        $database->setQuery($query);

        $extension = $database->loadObject();

        return is_object($extension) ? $extension : null;
    }

    private static function getMigratedSecurityParams(
        object $params,
        string $installed_version
    ): object
    {
        $params  = clone $params;
        $version = self::getNumericVersion($installed_version);

        self::migrateForbiddenPhpFunctions($params, $version);

        if ($version != '' && version_compare($version, '14.0.2', '<'))
        {
            $old_global = [];

            foreach (['css', 'js', 'php'] as $code_type)
            {
                $field                  = 'enable_' . $code_type;
                $old_global[$code_type] = (int) ($params->{$field} ?? 1);
                $params->{$field}       = $old_global[$code_type] === 0 ? '0' : '2';
            }

            self::migrateLegacyAreaPolicies($params, $old_global);

            self::removeSplitPolicies($params);
        }

        self::migrateTrustedSourceParams($params);
        self::migrateIndependentPolicies($params);
        self::migrateAreaInheritance($params);
        self::migrateForbiddenLists($params);
        self::migrateRemoveUnprocessedTags($params);

        return $params;
    }

    private static function migrateAreaInheritance(object $params): void
    {
        foreach (['css', 'js', 'php'] as $feature)
        {
            $field = $feature . '_same_as_tags';
            $params->{$feature . '_enable'} ??= '1';

            if ( ! empty($params->{$field}))
            {
                foreach (['articles', 'components', 'other'] as $area)
                {
                    $params->{$feature . '_' . $area} = '-1';
                }
            }

            unset($params->{$field});
        }
    }

    private static function migrateForbiddenLists(object $params): void
    {
        foreach (['forbidden_php', 'forbidden_tags'] as $name)
        {
            $values = [];
            $has_extra = false;

            foreach (['articles', 'components', 'other'] as $area)
            {
                $field = $area . '_' . $name;
                $has_extra = $has_extra || property_exists($params, $field);
                $values[] = (string) ($params->{$field} ?? '');
                unset($params->{$field});
            }

            if ( ! $has_extra)
            {
                continue;
            }

            $default = '';

            if ( ! property_exists($params, $name))
            {
                $manifest = simplexml_load_file(__DIR__ . '/sourcerer.xml');
                $fields = $manifest->xpath('//field[@name="' . $name . '"]');
                $default = (string) $fields[0]['default'];
            }

            array_unshift($values, (string) ($params->{$name} ?? $default));
            $entries = [];

            foreach (explode(',', implode(',', $values)) as $entry)
            {
                $entry = trim($entry);

                if ($entry !== '')
                {
                    $entries[strtolower($entry)] ??= $entry;
                }
            }

            $params->{$name} = implode(', ', $entries);
        }
    }

    private static function migrateIndependentPolicies(object $params): void
    {
        if (isset($params->tags_articles))
        {
            return;
        }

        $groups = $params->articles_security_level ?? [-1];
        $params->tags_usergroups = $groups;

        foreach (['articles', 'components', 'other'] as $area)
        {
            $mode = (int) ($params->{$area . '_enable'} ?? 1);
            $params->{'tags_' . $area} = (string) ($area === 'articles' && $mode !== 0 ? 2 : $mode);

            foreach (['css', 'js', 'php'] as $feature)
            {
                $mode = (int) ($params->{$area . '_enable_' . $feature} ?? -1);
                $mode = $mode === -1 ? (int) ($params->{'enable_' . $feature} ?? 2) : $mode;
                $params->{$feature . '_' . $area} = (string) ($area === 'articles' && $mode !== 0 ? 2 : $mode);
            }
        }

        foreach (['css', 'js', 'php'] as $feature)
        {
            $use_general = (bool) ($params->{'articles_security_level_default_' . $feature} ?? ($feature === 'css'));
            $params->{$feature . '_usergroups'} = $use_general
                ? $groups
                : ($params->{'articles_security_level_' . $feature} ?? ['Super Users']);
        }

        foreach (['tags', 'css', 'js', 'php'] as $feature)
        {
            foreach (['components', 'modules'] as $kind)
            {
                foreach (['trust_default', 'whitelist', 'blacklist'] as $setting)
                {
                    $name = $kind . '_' . $setting;
                    $params->{$feature . '_' . $name} = $setting === 'trust_default'
                        ? ($params->{$name} ?? '0')
                        : self::normalizeList($params->{$name} ?? []);
                }
            }
        }
    }

    private static function getNumericVersion(string $version): string
    {
        if ( ! preg_match('#^\d+(?:\.\d+){1,3}#', $version, $match))
        {
            return '';
        }

        return $match[0];
    }

    private static function migrateForbiddenPhpFunctions(object $params, string $version): void
    {
        if ($version == '' || version_compare($version, '16.0.0', '>='))
        {
            return;
        }

        if ( ! property_exists($params, 'forbidden_php'))
        {
            return;
        }

        $functions = [];

        foreach (explode(',', (string) $params->forbidden_php) as $function)
        {
            $function = trim($function);

            if ($function !== '')
            {
                $functions[] = $function;
            }
        }

        $normalized = array_map('strtolower', $functions);

        foreach (self::NEW_FORBIDDEN_PHP_FUNCTIONS as $function)
        {
            if (in_array($function, $normalized, true))
            {
                continue;
            }

            $functions[]  = $function;
            $normalized[] = $function;
        }

        $params->forbidden_php = implode(', ', $functions);
    }

    private static function migrateLegacyAreaPolicies(object $params, array $old_global): void
    {
        foreach (['components', 'other'] as $area)
        {
            $area_field              = $area . '_enable';
            $legacy_untrusted_field  = $area . '_unverified';
            $area_policy             = (int) ($params->{$area_field} ?? 1);

            if (isset($params->{$legacy_untrusted_field}))
            {
                $params->{$area_field} = $area_policy === 0
                    ? '0'
                    : ((int) $params->{$legacy_untrusted_field} === 1 ? '1' : '2');
            }

            foreach (['css', 'js', 'php'] as $code_type)
            {
                self::migrateLegacyCodePolicy($params, $area, $code_type, $old_global[$code_type]);
            }
        }
    }

    private static function migrateRemoveUnprocessedTags(object $params): void
    {
        if ( ! property_exists($params, 'remove_unprocessed_tags'))
        {
            $params->remove_unprocessed_tags = (int) ($params->disable_components_remove ?? 1) ? '1' : '0';
        }

        unset($params->disable_components_remove);
    }

    private static function migrateLegacyCodePolicy(
        object $params,
        string $area,
        string $code_type,
        int $old_global
    ): void
    {
        $field                    = $area . '_enable_' . $code_type;
        $legacy_untrusted_field   = $area . '_unverified_' . $code_type;
        $trusted_policy           = (int) ($params->{$field} ?? -1);

        if ( ! isset($params->{$legacy_untrusted_field}))
        {
            $params->{$field} = match ($trusted_policy)
            {
                0       => '0',
                -1      => '-1',
                default => '2',
            };

            return;
        }

        $legacy_untrusted_policy = (int) $params->{$legacy_untrusted_field};
        $allowed_untrusted       = $legacy_untrusted_policy === 1
            || ($legacy_untrusted_policy === -1 && $old_global !== 0);

        if ($allowed_untrusted)
        {
            $params->{$field} = '1';

            return;
        }

        if ($trusted_policy === -1)
        {
            $params->{$field} = '-1';

            return;
        }

        $params->{$field} = $trusted_policy === 0 ? '0' : '2';
    }

    private static function migrateTrustedSourceParams(object $params): void
    {
        if ( ! property_exists($params, 'components_trust_default'))
        {
            self::migrateComponentTrustParams($params);
        }

        unset(
            $params->components,
            $params->verified_components,
            $params->verified_component_ids
        );

        if ( ! property_exists($params, 'modules_trust_default'))
        {
            $params->modules_trust_default = '0';
            $params->modules_whitelist     = [
                'mod_custom',
                'mod_footer',
                'mod_languages',
                'mod_menu',
                'mod_stats',
                'mod_syndicate',
                'mod_wrapper',
            ];
            $params->modules_blacklist     = [];
        }

        unset(
            $params->verified_custom_modules,
            $params->verified_custom_module_ids
        );
    }

    private static function migrateComponentTrustParams(object $params): void
    {
        $legacy_components = $params->components ?? null;
        $legacy_components = is_array($legacy_components) || is_string($legacy_components)
            ? self::normalizeList($legacy_components)
            : [];

        $params->components_trust_default = '0';
        $params->components_whitelist     = [];
        $params->components_blacklist     = [];

        if ((int) ($params->components_enable ?? 1) !== 1 || $legacy_components === [])
        {
            return;
        }

        $params->components_enable        = '2';
        $params->components_trust_default = '1';
        $params->components_blacklist     = $legacy_components;

        foreach (['css', 'js', 'php'] as $code_type)
        {
            $field  = 'components_enable_' . $code_type;
            $policy = (int) ($params->{$field} ?? -1);

            if ($policy === -1)
            {
                $policy = (int) ($params->{'enable_' . $code_type} ?? 2);
            }

            if ($policy === 2)
            {
                $params->{$field} = '0';
            }
        }
    }

    private static function normalizeList(array|string|null $values): array
    {
        if (is_string($values))
        {
            $values = preg_split('#[|,]#', $values) ?: [];
        }

        $normalized = [];

        foreach ($values ?? [] as $value)
        {
            $value = trim((string) $value);

            if ($value !== '')
            {
                $normalized[$value] = $value;
            }
        }

        return array_values($normalized);
    }

    private static function migrateSecurityParams(string $installed_version): void
    {
        $extension = self::getExtension();

        if ( ! is_object($extension))
        {
            return;
        }

        $params = json_decode($extension->params ?? '');

        if ( ! is_object($params))
        {
            return;
        }

        $migrated = self::getMigratedSecurityParams($params, $installed_version);
        $json     = json_encode($migrated);

        if ($json === false || $json === ($extension->params ?? ''))
        {
            return;
        }

        $database = JFactory::getContainer()->get(DatabaseInterface::class);
        $update   = (object) [
            'extension_id' => (int) $extension->extension_id,
            'params'       => $json,
        ];

        $database->updateObject('#__extensions', $update, 'extension_id');
    }

    private static function removeSplitPolicies(object $params): void
    {
        foreach (['components', 'other'] as $area)
        {
            unset($params->{$area . '_unverified'});

            foreach (['css', 'js', 'php'] as $code_type)
            {
                unset($params->{$area . '_unverified_' . $code_type});
            }
        }
    }

    private static function delete(array $files = []): void
    {
        foreach ($files as $file)
        {
            if (is_dir($file))
            {
                JFolder::delete($file);
            }

            if (is_file($file))
            {
                JFile::delete($file);
            }
        }
    }

    private static function deleteOldFiles(): void
    {
        self::delete(
            [
                JPATH_SITE . '/media/sourcerer/css',
                JPATH_SITE . '/media/sourcerer/images/tab.png',
                JPATH_SITE . '/media/sourcerer/js/script.js',
                JPATH_SITE . '/media/sourcerer/js/script.min.js',
                JPATH_SITE . '/media/sourcerer/less',
                JPATH_SITE . '/plugins/system/sourcerer/helper.php',
                JPATH_SITE . '/plugins/system/sourcerer/script.install.helper.php',
                JPATH_SITE . '/plugins/system/sourcerer/sourcerer.php',
                JPATH_SITE . '/plugins/system/sourcerer/src/Code.php',
                JPATH_SITE . '/plugins/system/sourcerer/src/Helper.php',
                JPATH_SITE . '/plugins/system/sourcerer/src/Items.php',
                JPATH_SITE . '/plugins/system/sourcerer/src/Plugin.php',
                JPATH_SITE . '/plugins/system/sourcerer/src/TrustedSource.php',
                JPATH_SITE . '/plugins/system/sourcerer/src/VerifiedSource.php',
                JPATH_SITE . '/plugins/system/sourcerer/vendor',
            ]
        );
    }
}
