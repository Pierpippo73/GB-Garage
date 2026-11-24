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
use Joomla\Input\Input as JoomlaInput;
/**
 * string   getAlphaNumeric($name, $default = null)     Get an alphanumeric string.
 * string   getBase64($name, $default = null)           Get a base64 encoded string.
 * boolean  getBool($name, $default = null)             Get a boolean value.
 * string   getCmd($name, $default = null)              Get a CMD filtered string.
 * float    getFloat($name, $default = null)            Get a floating-point number.
 * string   getHtml($name, $default = null)             Get a HTML string.
 * integer  getInt($name, $default = null)              Get a signed integer.
 * string   getPath($name, $default = null)             Get a file path.
 * string   getPostString($name, $default = null)       Get a string from POST data.
 * mixed    getRaw($name, $default = null)              Get an unfiltered value.
 * string   getString($name, $default = null)           Get a string.
 * integer  getUint($name, $default = null)             Get an unsigned integer.
 * string   getUsername($name, $default = null)         Get a username.
 * string   getWord($name, $default = null)             Get a word.
 */
class Input
{
    public static function get(string $name, mixed $default = null, string $filter = 'none'): mixed
    {
        return self::getInput()->get($name, $default, $filter);
    }
    public static function getAlphaNumeric(string $name, mixed $default = null): string
    {
        return (string) (self::convertFromArray($name) ?? self::getInput()->getAlnum($name, $default));
    }
    public static function getArray(array $vars = [], mixed $datasource = null): array
    {
        return self::getInput()->getArray($vars, $datasource);
    }
    public static function getAsArray(string $name, ?array $default = []): array
    {
        return (array) self::getInput()->get($name, $default ?? [], 'array');
    }
    public static function getBase64(string $name, mixed $default = null): string
    {
        return (string) (self::convertFromArray($name) ?? self::getInput()->getBase64($name, $default));
    }
    public static function getBool(string $name, ?bool $default = \false): bool
    {
        return (bool) (self::convertFromArray($name) ?? self::getInput()->getBool($name, $default));
    }
    public static function getCmd(string $name, mixed $default = null): string
    {
        return (string) (self::convertFromArray($name) ?? self::getInput()->getCmd($name, $default));
    }
    public static function getFloat(string $name, mixed $default = null): float
    {
        return (float) (self::convertFromArray($name) ?? self::getInput()->getFloat($name, $default));
    }
    public static function getHtml(string $name, mixed $default = null): string
    {
        return (string) (self::convertFromArray($name) ?? self::getInput()->getHtml($name, $default));
    }
    public static function getInt(string $name, mixed $default = null): int
    {
        return (int) (self::convertFromArray($name) ?? self::getInput()->getInt($name, $default));
    }
    public static function getPath(string $name, mixed $default = null): string
    {
        return (string) (self::convertFromArray($name) ?? self::getInput()->getPath($name, $default));
    }
    public static function getPostString(string $name, mixed $default = null): string
    {
        return (string) self::getInput()->post->getString($name, $default);
    }
    public static function getRaw(string $name, mixed $default = null): mixed
    {
        return self::getInput()->getRaw($name, $default);
    }
    public static function getRequestContext(): \RegularLabs\Library\RequestContext
    {
        return \RegularLabs\Library\RequestContext::fromInput();
    }
    public static function getString(string $name, mixed $default = null): string
    {
        return (string) (self::convertFromArray($name) ?? self::getInput()->getString($name, $default));
    }
    public static function getUint(string $name, mixed $default = null): int
    {
        return (int) (self::convertFromArray($name) ?? self::getInput()->getUint($name, $default));
    }
    public static function getUsername(string $name, mixed $default = null): string
    {
        return (string) (self::convertFromArray($name) ?? self::getInput()->getUsername($name, $default));
    }
    public static function getWord(string $name, mixed $default = null): string
    {
        return (string) (self::convertFromArray($name) ?? self::getInput()->getWord($name, $default));
    }
    public static function isFinderIndexer(): bool
    {
        return \RegularLabs\Library\Indexer::isActive();
    }
    public static function set(string $name, mixed $value): void
    {
        self::getInput()->set($name, $value);
    }
    public static function setCookie(string $name, mixed $value, array $options = []): void
    {
        self::getInput()->cookie->set($name, $value, $options);
    }
    private static function convertFromArray(string $name): mixed
    {
        $value = self::getInput()->get($name, null);
        if (is_array($value)) {
            return $value[0] ?? null;
        }
        return null;
    }
    private static function getInput(): JoomlaInput
    {
        return \RegularLabs\Library\Application::getCurrent()->getInput();
    }
}
