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

use RegularLabs\Library\Config as RL_Config;
use RegularLabs\Library\DB as RL_DB;
use RegularLabs\Library\RegEx as RL_RegEx;
use RegularLabs\Library\StringHelper as RL_String;

final class Modules
{
    private const MODULE_MARKER_PREFIX = '___SRC_TRUSTED_MODULE_';

    private static array  $modules        = [];
    private static string $request_secret = '';

    public static function clean(string &$string): void
    {
        $string = RL_RegEx::replace(
            '___SRC_TRUSTED_(?:MODULE|PLACEHOLDER)_[A-Z0-9_]+___',
            '',
            $string,
            'i'
        );
    }

    public static function maskForContentPreparation(string &$content): array
    {
        $blocks = [];

        foreach (self::$modules as $module_blocks)
        {
            foreach ($module_blocks as $block)
            {
                $blocks[$block['placeholder']] = $block;
            }
        }

        return self::extractBlocks($content, array_values($blocks), true);
    }

    public static function protectCustomContentFromEvent(object $event): void
    {
        [$context, $article] = self::getContentFromEvent($event);

        if ($context !== 'mod_custom.content'
            || ! is_object($article)
            || ! isset($article->text)
            || ! is_string($article->text))
        {
            return;
        }

        self::protectCustomModuleContent($article->text);
    }

    public static function protectCustomModuleContent(string &$content): void
    {
        if ( ! self::moduleTypeIsTrusted('mod_custom'))
        {
            return;
        }

        $matches = self::getProtectableMatches($content);

        if (empty($matches))
        {
            return;
        }

        $db    = RL_DB::get();
        $query = $db->getQuery(true)
            ->select($db->quoteName(['id', 'content']))
            ->from($db->quoteName('#__modules'))
            ->where($db->quoteName('client_id') . ' = 0')
            ->where($db->quoteName('module') . ' = ' . $db->quote('mod_custom'))
            ->where($db->quoteName('content') . ' = ' . $db->quote($content));
        $db->setQuery($query);

        $module_ids = [];

        foreach ($db->loadObjectList() as $module)
        {
            if (($module->content ?? null) !== $content || (int) ($module->id ?? 0) < 1)
            {
                continue;
            }

            $module_ids[] = (int) $module->id;
        }

        self::protectMatches($content, $module_ids, $matches);
    }

    public static function protectFromListEvent(array|object $event): void
    {
        foreach (self::getModulesFromEvent($event) as $module)
        {
            if (is_object($module))
            {
                self::protectModule($module);
            }
        }
    }

    public static function protectModule(object $module): void
    {
        $module_id = (int) ($module->id ?? 0);

        if ($module_id < 1
            || ($module->module ?? '') !== 'mod_custom'
            || ! isset($module->content)
            || ! is_string($module->content)
            || ! self::moduleTypeIsTrusted('mod_custom'))
        {
            return;
        }

        $matches = self::getProtectableMatches($module->content);

        if (empty($matches))
        {
            return;
        }

        self::protectMatches($module->content, [$module_id], $matches);
    }

    public static function replaceModule(object $module): void
    {
        $module_type = (string) ($module->module ?? '');

        if ($module_type !== ''
            && $module_type !== 'mod_custom'
            && self::moduleTypeIsTrusted($module_type))
        {
            Replace::replace(
                $module->content,
                'module',
                security: Security::getForRenderedModule($module_type),
                output_stage: 'Module'
            );
            self::clean($module->content);

            return;
        }

        $module_id = (int) ($module->id ?? 0);

        if (empty(self::$modules[$module_id]))
        {
            self::protectRenderedModule($module);
        }

        $blocks  = self::$modules[$module_id] ?? [];
        $trusted = self::extractBlocks($module->content, $blocks);

        if (empty($trusted) && ! empty($blocks))
        {
            self::protectRenderedModule($module);
            $trusted = self::extractBlocks(
                $module->content,
                self::$modules[$module_id] ?? []
            );
        }

        Replace::replace($module->content, 'module', output_stage: 'Module');

        foreach ($trusted as $placeholder => $source)
        {
            Replace::replace(
                $source,
                'module',
                security: Security::getForStoredCustomModule(),
                output_stage: 'Module'
            );
            $module->content = RL_String::replaceOnce($placeholder, $source, $module->content);
        }

        self::clean($module->content);
    }

    public static function restoreAfterContentPreparation(string &$content, array $blocks): void
    {
        foreach ($blocks as $placeholder => $source)
        {
            $content = RL_String::replaceOnce($placeholder, $source, $content);
        }
    }

    private static function extractBlocks(string &$content, array $blocks, bool $include_markers = false): array
    {
        $extracted = [];

        foreach ($blocks as $block)
        {
            $open_position = strpos($content, $block['open']);

            if ($open_position === false)
            {
                continue;
            }

            $source_position = $open_position + strlen($block['open']);
            $close_position  = strpos($content, $block['close'], $source_position);

            if ($close_position === false)
            {
                continue;
            }

            $segment = substr(
                $content,
                $open_position,
                $close_position + strlen($block['close']) - $open_position
            );
            $source  = substr($content, $source_position, $close_position - $source_position);

            $extracted[$block['placeholder']] = $include_markers ? $segment : $source;
            $content                          = RL_String::replaceOnce(
                $segment,
                $block['placeholder'],
                $content
            );
        }

        return $extracted;
    }

    private static function getContentFromEvent(object $event): array
    {
        if (method_exists($event, 'getContext')
            && method_exists($event, 'getItem'))
        {
            return [$event->getContext(), $event->getItem()];
        }

        if (method_exists($event, 'getArguments'))
        {
            $arguments = $event->getArguments();

            return [
                $arguments['context'] ?? $arguments[0] ?? '',
                $arguments['subject'] ?? $arguments[1] ?? null,
            ];
        }

        return ['', null];
    }

    private static function getModuleSignature(string $source_id, int $index, string $source): string
    {
        $payload = $source_id . ':' . $index . ':' . hash('sha256', $source);
        $secret  = (string) RL_Config::get('secret', '');

        if (self::$request_secret === '')
        {
            self::$request_secret = bin2hex(random_bytes(32));
        }

        $secret .= ':' . self::$request_secret;

        return substr(
            hash_hmac('sha256', $payload, $secret),
            0,
            32
        );
    }

    private static function getModulesFromEvent(array|object $event): array
    {
        if (is_object($event) && method_exists($event, 'getModules'))
        {
            return (array) $event->getModules();
        }

        if (is_object($event) && method_exists($event, 'getArguments'))
        {
            $arguments = $event->getArguments();

            return (array) ($arguments['modules'] ?? $arguments[0] ?? []);
        }

        return is_array($event) ? $event : [];
    }

    private static function getProtectableMatches(string $content): array
    {
        if (str_contains($content, self::MODULE_MARKER_PREFIX)
            || ! RL_RegEx::matchAll(Params::getRegex(), $content, $matches))
        {
            return [];
        }

        return $matches;
    }

    private static function moduleTypeIsTrusted(string $module_type): bool
    {
        foreach (['tags', 'css', 'js', 'php'] as $feature)
        {
            if (Security::sourceIsTrusted($feature, 'modules', $module_type))
            {
                return true;
            }
        }

        return false;
    }

    private static function protectMatches(string &$content, array $module_ids, array $matches): void
    {
        $valid_module_ids = [];

        foreach ($module_ids as $module_id)
        {
            $module_id = (int) $module_id;

            if ($module_id > 0)
            {
                $valid_module_ids[$module_id] = $module_id;
            }
        }

        $module_ids = array_values($valid_module_ids);

        if (empty($module_ids) || empty($matches) || str_contains($content, self::MODULE_MARKER_PREFIX))
        {
            return;
        }

        sort($module_ids, SORT_NUMERIC);

        $blocks    = [];
        $source_id = count($module_ids) === 1
            ? (string) $module_ids[0]
            : strtoupper(substr(hash('sha256', implode(':', $module_ids)), 0, 16));

        foreach ($matches as $index => $match)
        {
            $signature = self::getModuleSignature($source_id, $index, $match[0]);
            $open      = self::MODULE_MARKER_PREFIX . $source_id . '_' . $index . '_' . $signature . '_OPEN___';
            $close     = self::MODULE_MARKER_PREFIX . $source_id . '_' . $index . '_' . $signature . '_CLOSE___';

            $replacement = $open . $match[0] . $close;

            $content  = RL_String::replaceOnce($match[0], $replacement, $content);
            $blocks[] = [
                'open'        => $open,
                'close'       => $close,
                'placeholder' => '___SRC_TRUSTED_PLACEHOLDER_' . $signature . '___',
            ];
        }

        foreach ($module_ids as $module_id)
        {
            self::$modules[$module_id] = $blocks;
        }
    }

    private static function protectRenderedModule(object $module): void
    {
        $module_id = (int) ($module->id ?? 0);

        if ($module_id < 1
            || ($module->module ?? '') !== 'mod_custom'
            || ! self::moduleTypeIsTrusted('mod_custom'))
        {
            return;
        }

        $db    = RL_DB::get();
        $query = $db->getQuery(true)
            ->select($db->quoteName(['id', 'module', 'content']))
            ->from($db->quoteName('#__modules'))
            ->where($db->quoteName('id') . ' = ' . $module_id)
            ->where($db->quoteName('client_id') . ' = 0');
        $db->setQuery($query);

        $stored_module = $db->loadObject();

        if ( ! $stored_module
            || (int) ($stored_module->id ?? 0) !== $module_id
            || ($stored_module->module ?? '') !== 'mod_custom'
            || ! is_string($stored_module->content ?? null))
        {
            return;
        }

        $matches        = self::getProtectableMatches($stored_module->content);
        $stored_matches = [];

        foreach ($matches as $match)
        {
            if ( ! str_contains($module->content, $match[0]))
            {
                continue;
            }

            $stored_matches[] = $match;
        }

        self::protectMatches($module->content, [$module_id], $stored_matches);
    }
}
