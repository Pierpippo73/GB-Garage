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

namespace RegularLabs\Plugin\System\Sourcerer;

defined('_JEXEC') or die;

use RegularLabs\Library\Input as RL_Input;
use RegularLabs\Library\Security as RL_Security;
use RegularLabs\Library\User as RL_User;

class Security
{
    public static function getForArea(string $area): object
    {
        return self::getForUntrustedArea($area);
    }

    public static function getForArticle(object|false|null $article = null): object
    {
        if ( ! is_object($article) || ! isset($article->created_by))
        {
            return self::getForArea('articles');
        }

        $creator_id  = (int) $article->created_by;
        $modifier_id = (int) ($article->modified_by ?? 0);

        return self::getForAuthoredContent('articles', $creator_id, $modifier_id);
    }

    public static function getForAuthoredContent(
        string $area,
        int    $creator_id,
        int    $modifier_id = 0
    ): object
    {
        return self::getForSource($area, 'authors', $creator_id, $modifier_id);
    }

    public static function getForRenderedModule(string $module_type): object
    {
        if (RequestInput::containsSourceTag() && ! self::isCurrentRequestUserTrusted())
        {
            return self::getForUntrustedArea('other');
        }

        return self::getForSource('other', 'module', module_type: $module_type);
    }

    public static function getForSource(
        string $area,
        string $source = 'unknown',
        int    $creator_id = 0,
        int    $modifier_id = 0,
        string $module_type = ''
    ): object
    {
        $area     = Params::getAreaType($area);
        $settings = Params::getAreaForTrustedContent($area);
        $security = (object) ['area_params' => $settings];

        foreach (['tags', 'css', 'js', 'php'] as $feature)
        {
            $trusted = match ($source)
            {
                'authors' => self::articleAuthorIsTrusted(
                    $feature,
                    $creator_id,
                    $modifier_id,
                    $area
                ),
                'module'  => self::sourceIsTrusted($feature, 'modules', $module_type),
                default   => $area === 'components'
                    && self::sourceIsTrusted(
                        $feature,
                        'components',
                        RL_Input::getRequestContext()->option
                    ),
            };

            $mode    = self::getMode($feature, $area);
            $allowed = RL_Security::allowsContentSourcePolicy($mode, $trusted);

            $security->{$feature === 'tags' ? 'pass' : 'pass_' . $feature}     = $allowed;
            $settings->{$feature === 'tags' ? 'enable' : 'enable_' . $feature} = $mode !== 0;
        }

        // Rendered output may retain ordinary HTML while disallowed code types are removed.
        if ($source === 'unknown' || $source === 'module')
        {
            $security->untrusted        = true;
            $settings->enable_untrusted = $security->pass;
        }

        return $security;
    }

    public static function getForStoredCustomModule(): object
    {
        return self::getForSource('other', 'module', module_type: 'mod_custom');
    }

    public static function getForUntrustedArea(string $area): object
    {
        $request_source_tag   = RequestInput::containsSourceTag();
        $request_user_trusted = $request_source_tag && self::isCurrentRequestUserTrusted();

        $security                       = self::getForSource($area);
        $security->request_source_tag   = $request_source_tag;
        $security->request_user_trusted = $request_user_trusted;

        if ($request_source_tag && ! $request_user_trusted)
        {
            $security->area_params->enable_untrusted = false;
        }

        return self::addLegacySecurityAliases($security);
    }

    /**
     * @deprecated Use getForUntrustedArea().
     */
    public static function getForUnverifiedArea(string $area): object
    {
        return self::getForUntrustedArea($area);
    }

    public static function getMode(string $feature, string $area): int
    {
        if ($feature !== 'tags' && (int) (Params::get()->{$feature . '_enable'} ?? 1) === 0)
        {
            return 0;
        }

        $feature = self::getSettingsFeature($feature, $area);
        $default = $feature === 'tags' && $area !== 'articles' ? 1 : 2;

        return RL_Security::getContentSourcePolicyMode(
            Params::get(),
            $feature,
            $area,
            $default
        );
    }

    public static function resolve(string $area, object|null $security = null): object
    {
        if (is_null($security))
        {
            return self::getForUntrustedArea($area);
        }

        $result = clone $security;

        $area_params = isset($result->area_params) && is_object($result->area_params)
            ? clone $result->area_params
            : Params::getAreaForTrustedContent($area);

        $result->area_params = $area_params;

        return self::addLegacySecurityAliases($result);
    }

    public static function sourceIsTrusted(string $feature, string $kind, string $name): bool
    {
        $feature = self::getSettingsFeature($feature, $kind === 'modules' ? 'other' : 'components');

        return RL_Security::sourceIsTrustedBySettings(
            $name,
            Params::get(),
            $feature . '_' . $kind,
            $kind === 'modules' ? RL_Security::DEFAULT_TRUSTED_MODULE_TYPES : []
        );
    }

    private static function addLegacySecurityAliases(object $security): object
    {
        if (isset($security->untrusted))
        {
            $security->unverified = $security->untrusted;
        }

        if (isset($security->area_params->enable_untrusted))
        {
            $security->area_params->enable_unverified = $security->area_params->enable_untrusted;
        }

        return $security;
    }

    private static function articleAuthorIsTrusted(
        string $feature,
        int    $creator_id,
        int    $modifier_id,
        string $area
    ): bool
    {
        $feature = self::getSettingsFeature($feature, $area);
        $default = in_array($feature, ['js', 'php'], true) ? ['Super Users'] : [-1];

        return RL_Security::effectiveAuthorIsTrusted(
            $creator_id,
            $modifier_id,
            Params::get()->{$feature . '_usergroups'} ?? $default
        );
    }

    private static function getSettingsFeature(string $feature, string $area): string
    {
        return $feature !== 'tags' && (int) (Params::get()->{$feature . '_' . $area} ?? 2) === -1
            ? 'tags'
            : $feature;
    }

    private static function isCurrentRequestUserTrusted(): bool
    {
        return RL_Security::userIsInGroups(
            RL_User::getId(),
            Params::get('request_security_level', ['Super Users'])
        );
    }
}
