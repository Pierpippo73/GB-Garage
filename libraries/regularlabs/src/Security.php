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
use DOMElement;
use Joomla\CMS\Filter\InputFilter as JInputFilter;
class Security
{
    public const DEFAULT_TRUSTED_MODULE_TYPES = ['mod_custom', 'mod_footer', 'mod_languages', 'mod_menu', 'mod_stats', 'mod_syndicate', 'mod_wrapper'];
    private const COMPONENT_SOURCE_ID = \PHP_INT_MIN;
    private static array $content_source_transformations = [];
    private const ARTICLE_CONTEXTS = ['com_content.article', 'com_content.category', 'com_content.featured', 'com_finder.indexer'];
    private const ARTICLE_SOURCE_CONTEXTS = ['com_content.article', 'com_content.category', 'com_content.featured'];
    public static function allowsTagsInSourcerer(object $settings): bool
    {
        if (property_exists($settings, 'tags_sourcerer')) {
            return (bool) $settings->tags_sourcerer;
        }
        return !(bool) ($settings->disable_in_sourcerer ?? \false);
    }
    public static function escapeRequestInputForHtml(string $value): string
    {
        if (!\RegularLabs\Library\Uri::hasAllowedScheme($value, ['http', 'https', 'mailto', 'tel', 'ftp'], \true)) {
            return '';
        }
        $value = \RegularLabs\Library\StringHelper::escape($value);
        // Keep visitor input from becoming executable Regular Labs syntax in a later plugin pass.
        return str_replace(['{', '[', '«'], ['&#123;', '&#91;', '&#171;'], $value);
    }
    public static function sanitizeHtmlFragment(string $html): string
    {
        return JInputFilter::getInstance([], [], 1, 1)->clean($html, 'html');
    }
    private static function firstOccurrenceIsInHtmlEventAttribute(string $html, string $needle): bool
    {
        return self::firstOccurrenceIsInHtmlAttribute($html, $needle, 'on[a-z0-9:_-]+');
    }
    public static function firstOccurrenceIsInExecutableHtmlContext(string $html, string $needle): bool
    {
        if (self::firstOccurrenceIsInHtmlEventAttribute($html, $needle) || self::firstOccurrenceIsInHtmlAttribute($html, $needle, 'srcdoc')) {
            return \true;
        }
        $offset = strpos($html, $needle);
        if ($offset === \false) {
            return \false;
        }
        return (bool) \RegularLabs\Library\RegEx::match('<script(?:\s[^>]*)?>(?:(?!</script\b).)*\z', substr($html, 0, $offset));
    }
    private static function firstOccurrenceIsInHtmlAttribute(string $html, string $needle, string $attribute_name_pattern): bool
    {
        $offset = strpos($html, $needle);
        if ($offset === \false) {
            return \false;
        }
        $before = substr($html, 0, $offset);
        $tag_start = strrpos($before, '<');
        if ($tag_start === \false) {
            return \false;
        }
        $tag_end = strrpos($before, '>');
        if ($tag_end !== \false && $tag_end > $tag_start) {
            return \false;
        }
        $tag_prefix = substr($before, $tag_start + 1);
        return (bool) \RegularLabs\Library\RegEx::endsWith('\s+(?:' . $attribute_name_pattern . ')\s*=\s*(?:"[^"]*|\'[^\']*|[^\s"\'=<>`]*)', $tag_prefix);
    }
    /**
     * @deprecated Use getTrustedArticle().
     */
    public static function getAuthorisedArticle(int $id, mixed $allowed_groups): ?object
    {
        return self::getTrustedArticle($id, $allowed_groups);
    }
    /**
     * @deprecated Use getTrustedArticleForSource().
     */
    public static function getAuthorisedArticleForSource(string $source, mixed $article, string $context, mixed $allowed_groups): ?object
    {
        return self::getTrustedArticleForSource($source, $article, $context, $allowed_groups);
    }
    public static function getTrustedArticle(int $id, array|int|string $trusted_groups): ?object
    {
        if ($id < 1) {
            return null;
        }
        $db = \RegularLabs\Library\DB::get();
        $query = $db->getQuery(\true)->select($db->quoteName(['id', 'title', 'created_by_alias', 'introtext', 'fulltext', 'created_by', 'modified_by']))->from($db->quoteName('#__content'))->where($db->quoteName('id') . ' = ' . $id);
        $article = $db->setQuery($query)->loadObject();
        if (!$article || !self::articleAuthorIsTrusted($article, $trusted_groups)) {
            return null;
        }
        return $article;
    }
    public static function getTrustedArticleForSource(string $source, mixed $article, string $context, array|int|string $trusted_groups): ?object
    {
        if (!is_object($article) || empty($article->id) || $source === '' || !in_array($context, self::ARTICLE_CONTEXTS, \true)) {
            return null;
        }
        $stored = self::getTrustedArticle((int) $article->id, $trusted_groups);
        if (!$stored || !self::sourceBelongsToArticle($source, $stored)) {
            return null;
        }
        return $stored;
    }
    public static function allowsContentSource(string $source, mixed $item, string $area, string $context, object $settings, string $settings_prefix): bool
    {
        if (!($settings->{$settings_prefix . '_security'} ?? \true)) {
            return \true;
        }
        return (bool) self::getTrustedContentSource($source, $item, $area, $context, $settings, $settings_prefix);
    }
    public static function allowsContentSourceByPolicy(string $source, mixed $item, string $area, string $context, object $settings, string $settings_prefix, int $default = 1): bool
    {
        $mode = self::getContentSourcePolicyMode($settings, $settings_prefix, $area, $default);
        if ($mode === 2 && in_array($area, ['article', 'articles'], \true)) {
            if (!is_object($item) || empty($item->id) || $source === '' || !in_array($context, self::ARTICLE_CONTEXTS, \true)) {
                return \false;
            }
            $trusted_groups = $settings->{$settings_prefix . '_usergroups'} ?? ['Super Users'];
            return (bool) self::getTrustedArticle((int) $item->id, $trusted_groups);
        }
        $source_is_trusted = $mode === 2 && self::allowsContentSource($source, $item, $area, $context, $settings, $settings_prefix);
        return self::allowsContentSourcePolicy($mode, $source_is_trusted);
    }
    public static function allowsContentSourcePolicy(int $mode, bool $source_is_trusted): bool
    {
        return $mode === 1 || $mode === 2 && $source_is_trusted;
    }
    public static function getContentSourcePolicyMode(object $settings, string $settings_prefix, string $area, int $default = 1): int
    {
        $area = match ($area) {
            'article', 'articles' => 'articles',
            'component', 'components' => 'components',
            default => 'other',
        };
        $mode = (int) ($settings->{$settings_prefix . '_' . $area} ?? $default);
        return in_array($mode, [0, 1, 2], \true) ? $mode : $default;
    }
    public static function getContentSourceArea(mixed $item, string $context): string
    {
        if (is_object($item) && isset($item->created_by)) {
            return 'article';
        }
        $item_context = is_object($item) ? (string) ($item->context ?? '') : '';
        if (in_array($item_context ?: $context, self::ARTICLE_SOURCE_CONTEXTS, \true)) {
            return 'article';
        }
        return 'other';
    }
    public static function getContentSourceTransformations(string $generated_source): array
    {
        if ($generated_source === '') {
            return [];
        }
        return array_filter(self::$content_source_transformations, static fn(object $transformation): bool => str_contains($transformation->output, $generated_source));
    }
    public static function getTrustedContentSource(string $source, mixed $item, string $area, string $context, object $settings, string $settings_prefix): ?object
    {
        if ($source === '') {
            return null;
        }
        if (in_array($area, ['article', 'articles'], \true)) {
            $trusted_groups = $settings->{$settings_prefix . '_usergroups'} ?? ['Super Users'];
            return self::getTrustedArticleForSource($source, $item, $context, $trusted_groups);
        }
        if (in_array($area, ['module', 'modules'], \true)) {
            return self::getTrustedModuleForSource($source, $item, 'module', $context, $settings, $settings_prefix);
        }
        if (!in_array($area, ['component', 'components'], \true)) {
            return null;
        }
        return self::getTrustedComponent($settings, $settings_prefix);
    }
    public static function getTrustedContentSourceById(int $id, object $settings, string $settings_prefix): ?object
    {
        if ($id === self::COMPONENT_SOURCE_ID) {
            return self::getTrustedComponent($settings, $settings_prefix);
        }
        if ($id < 0) {
            return self::getTrustedModule(-$id, $settings, $settings_prefix);
        }
        $trusted_groups = $settings->{$settings_prefix . '_usergroups'} ?? ['Super Users'];
        return self::getTrustedArticle($id, $trusted_groups);
    }
    public static function getTrustedContentSourceId(object $source): int
    {
        return match ((string) ($source->_rl_source_type ?? 'article')) {
            'component' => self::COMPONENT_SOURCE_ID,
            'module' => -(int) ($source->id ?? 0),
            default => (int) ($source->id ?? 0),
        };
    }
    public static function registerContentSourceTransformation(string $template, string $output, array $replacements): void
    {
        if ($template === '' || $template === $output || $replacements === []) {
            return;
        }
        self::$content_source_transformations[] = (object) ['template' => $template, 'output' => $output, 'replacements' => $replacements];
    }
    private static function sourceBelongsToArticle(string $source, object $article): bool
    {
        $introtext = (string) ($article->introtext ?? '');
        $fulltext = (string) ($article->fulltext ?? '');
        foreach (['title', 'created_by_alias', 'introtext', 'fulltext'] as $field) {
            if (str_contains((string) ($article->{$field} ?? ''), $source)) {
                return \true;
            }
        }
        // Article::process temporarily joins intro and full text with one of these private separators.
        return $fulltext !== '' && in_array($source, [$introtext . '͞' . $fulltext, $introtext . 'Ͽ' . $fulltext], \true);
    }
    private static function getTrustedComponent(object $settings, string $settings_prefix): ?object
    {
        $option = \RegularLabs\Library\RequestContext::fromInput()->option;
        if ($option === '' || !self::sourceIsTrustedBySettings($option, $settings, self::getSourceSettingsPrefix($settings_prefix, 'components'))) {
            return null;
        }
        return (object) ['_rl_source_type' => 'component', 'option' => $option];
    }
    private static function getTrustedModule(int $id, object $settings, string $settings_prefix): ?object
    {
        if ($id < 1) {
            return null;
        }
        $db = \RegularLabs\Library\DB::get();
        $query = $db->getQuery(\true)->select($db->quoteName(['id', 'title', 'content', 'module']))->from($db->quoteName('#__modules'))->where($db->quoteName('id') . ' = ' . $id)->where($db->quoteName('client_id') . ' = 0');
        $module = $db->setQuery($query)->loadObject();
        if (!$module || !self::moduleTypeIsTrusted((string) $module->module, $settings, $settings_prefix)) {
            return null;
        }
        $module->_rl_source_type = 'module';
        return $module;
    }
    private static function getTrustedModuleForSource(string $source, mixed $item, string $area, string $context, object $settings, string $settings_prefix): ?object
    {
        if ($source === '' || !is_object($item) || empty($item->id)) {
            return null;
        }
        $module_type = (string) ($item->module ?? '');
        if ($module_type === '' && str_starts_with($context, 'mod_')) {
            $module_type = explode('.', $context, 2)[0];
        }
        if ($module_type === '') {
            return null;
        }
        $module = self::getTrustedModule((int) $item->id, $settings, $settings_prefix);
        if (!$module || (string) $module->module !== $module_type) {
            return null;
        }
        if ($module_type !== 'mod_custom') {
            return $area === 'module' ? $module : null;
        }
        return str_contains((string) ($module->content ?? ''), $source) ? $module : null;
    }
    private static function moduleTypeIsTrusted(string $module_type, object $settings, string $settings_prefix): bool
    {
        return self::sourceIsTrustedBySettings($module_type, $settings, self::getSourceSettingsPrefix($settings_prefix, 'modules'), self::DEFAULT_TRUSTED_MODULE_TYPES);
    }
    private static function getSourceSettingsPrefix(string $feature, string $source_type): string
    {
        return ($feature === '' ? '' : $feature . '_') . $source_type;
    }
    public static function sourceIsTrustedBySettings(int|string $source_id, object $settings, string $settings_prefix, array|int|string $default_whitelist = []): bool
    {
        return self::sourceIsTrusted($source_id, (bool) ($settings->{$settings_prefix . '_trust_default'} ?? \false), $settings->{$settings_prefix . '_whitelist'} ?? $default_whitelist, $settings->{$settings_prefix . '_blacklist'} ?? []);
    }
    private static function sourceIsTrusted(int|string $source_id, bool $trust_default, array|int|string $whitelist, array|int|string $blacklist): bool
    {
        $values = $trust_default ? $blacklist : $whitelist;
        if (is_string($values)) {
            $values = str_replace('|', ',', $values);
        }
        $values = \RegularLabs\Library\ArrayHelper::toArray($values);
        $selected = in_array((string) $source_id, array_map('strval', $values), \true);
        return $trust_default ? !$selected : $selected;
    }
    public static function getHtmlAttributeSignature(string $scope, object $source, DOMElement $element, string $tag_name, array $excluded_attributes = []): string
    {
        $secret = (string) \RegularLabs\Library\Config::get('secret');
        if ($scope === '' || $secret === '') {
            return '';
        }
        $attributes = [];
        foreach ($element->attributes as $attribute) {
            if (in_array($attribute->name, $excluded_attributes, \true)) {
                continue;
            }
            $attributes[$attribute->name] = $attribute->value;
        }
        ksort($attributes);
        return hash_hmac('sha256', $scope . ':' . serialize([$source, strtolower($tag_name), $attributes]), $secret);
    }
    public static function userIsInGroups(int $user_id, array|int|string $allowed_groups): bool
    {
        if ($user_id < 1) {
            return \false;
        }
        $groups = \RegularLabs\Library\User::resolveUserGroupIds($allowed_groups);
        if (in_array(-1, $groups, \true)) {
            return \true;
        }
        return $groups !== [] && array_intersect($groups, \RegularLabs\Library\User::getAuthorisedGroups($user_id)) !== [];
    }
    public static function effectiveAuthorIsTrusted(int $creator_id, int $modifier_id, array|int|string $trusted_groups): bool
    {
        return self::userIsInGroups($modifier_id ?: $creator_id, $trusted_groups);
    }
    private static function articleAuthorIsTrusted(object $article, array|int|string $trusted_groups): bool
    {
        return self::effectiveAuthorIsTrusted((int) ($article->created_by ?? 0), (int) ($article->modified_by ?? 0), $trusted_groups);
    }
}
