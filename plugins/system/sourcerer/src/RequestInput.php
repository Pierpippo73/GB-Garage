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

use RegularLabs\Library\Application as RL_Application;
use RegularLabs\Library\RegEx as RL_RegEx;
use RegularLabs\Library\StringHelper as RL_String;

final class RequestInput
{
    private const SESSION_KEY = 'sourcerer.request_source_tag';
    private const SESSION_TTL = 60;

    private static ?bool $contains_source_tag = null;

    public static function capture(): void
    {
        self::$contains_source_tag = self::detectSourceTag();

        if ( ! RL_Application::isSite())
        {
            return;
        }

        $session    = RL_Application::getCurrent()->getSession();
        $expires_at = (int) $session->get(self::SESSION_KEY, 0);

        if (self::$contains_source_tag)
        {
            $session->set(self::SESSION_KEY, time() + self::SESSION_TTL);

            return;
        }

        if ($expires_at >= time())
        {
            self::$contains_source_tag = true;

            return;
        }

        if ($expires_at > 0)
        {
            $session->remove(self::SESSION_KEY);
        }
    }

    public static function containsSourceTag(): bool
    {
        if ( ! is_null(self::$contains_source_tag))
        {
            return self::$contains_source_tag;
        }

        self::$contains_source_tag = self::detectSourceTag();

        return self::$contains_source_tag;
    }

    private static function detectSourceTag(): bool
    {
        foreach ([$_GET, $_POST, $_COOKIE, $_FILES, self::getServerInput()] as $input)
        {
            if (self::valueContainsSourceTag($input))
            {
                return true;
            }
        }

        if (self::requestBodyContainsSourceTag())
        {
            return true;
        }

        return self::uploadedFilesContainSourceTag($_FILES);
    }

    private static function getMaximumEncodedTagLength(): int
    {
        $maximum = 0;

        foreach (Params::getTags(true) as $tag)
        {
            $maximum = max($maximum, strlen($tag));
        }

        return max(256, $maximum * 12);
    }

    private static function getServerInput(): array
    {
        $input        = [];
        $allowed_keys = [
            'CONTENT_TYPE',
            'ORIG_PATH_INFO',
            'PATH_INFO',
            'PHP_AUTH_PW',
            'PHP_AUTH_USER',
            'QUERY_STRING',
            'REDIRECT_QUERY_STRING',
            'REDIRECT_URL',
            'REQUEST_URI',
        ];

        foreach ($_SERVER as $name => $value)
        {
            if (str_starts_with($name, 'HTTP_') || in_array($name, $allowed_keys, true))
            {
                $input[$name] = $value;
            }
        }

        return $input;
    }

    private static function getStrings(array|string $value): array
    {
        if (is_string($value))
        {
            return $value === '' ? [] : [$value];
        }

        $strings = [];

        foreach ($value as $item)
        {
            $strings = [...$strings, ...self::getStrings($item)];
        }

        return $strings;
    }

    private static function getUploadedFilePaths(array $files): array
    {
        $paths = [];

        foreach ($files as $name => $value)
        {
            if ($name === 'tmp_name')
            {
                $paths = [...$paths, ...self::getStrings($value)];

                continue;
            }

            if (is_array($value))
            {
                $paths = [...$paths, ...self::getUploadedFilePaths($value)];
            }
        }

        return $paths;
    }

    private static function requestBodyContainsSourceTag(): bool
    {
        $handle = fopen('php://input', 'rb');

        if ($handle === false)
        {
            return false;
        }

        try
        {
            return self::streamContainsSourceTag($handle);
        }
        finally
        {
            fclose($handle);
        }
    }

    private static function streamContainsSourceTag($handle): bool
    {
        $carry_size = self::getMaximumEncodedTagLength();
        $carry      = '';

        while ( ! feof($handle))
        {
            $chunk = fread($handle, 8192);

            if ($chunk === false)
            {
                return false;
            }

            $content = $carry . $chunk;

            if (self::valueContainsSourceTag($content))
            {
                return true;
            }

            $carry = substr($content, -$carry_size);
        }

        return false;
    }

    private static function stringContainsSourceTag(string $value): bool
    {
        foreach (Params::getTags(true) as $tag)
        {
            $tag_regex = RL_RegEx::quote($tag);

            if (RL_RegEx::match($tag_regex, $value))
            {
                return true;
            }
        }

        return false;
    }

    private static function uploadedFilesContainSourceTag(array $files): bool
    {
        foreach (self::getUploadedFilePaths($files) as $path)
        {
            if ( ! is_uploaded_file($path))
            {
                continue;
            }

            $handle = fopen($path, 'rb');

            if ($handle === false)
            {
                continue;
            }

            try
            {
                $contains_source_tag = self::streamContainsSourceTag($handle);
            }
            finally
            {
                fclose($handle);
            }

            if ($contains_source_tag)
            {
                return true;
            }
        }

        return false;
    }

    private static function valueContainsSourceTag(mixed $value): bool
    {
        if (is_array($value))
        {
            foreach ($value as $name => $item)
            {
                if (is_string($name) && self::valueContainsSourceTag($name))
                {
                    return true;
                }

                if (self::valueContainsSourceTag($item))
                {
                    return true;
                }
            }

            return false;
        }

        if ( ! is_scalar($value))
        {
            return false;
        }

        $value = (string) $value;

        for ($i = 0; $i < 4; $i++)
        {
            if (self::stringContainsSourceTag($value))
            {
                return true;
            }

            $json = json_decode($value, true);

            if (json_last_error() === JSON_ERROR_NONE
                && is_array($json)
                && self::valueContainsSourceTag($json))
            {
                return true;
            }

            $decoded = rawurldecode($value);
            $decoded = RL_String::html_entity_decoder($decoded);

            if ($decoded === $value)
            {
                return false;
            }

            $value = $decoded;
        }

        return self::stringContainsSourceTag($value);
    }
}
