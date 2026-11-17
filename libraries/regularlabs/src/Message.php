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
class Message
{
    public static function contains(string $message, ?string $type = null): bool
    {
        return self::queueContains($message, $type, \true);
    }
    public static function containsText(string $text, ?string $type = null): bool
    {
        return self::queueContains($text, $type, \false);
    }
    public static function enqueue(string $message, string $type = 'message'): void
    {
        \RegularLabs\Library\Application::getCurrent()->enqueueMessage($message, $type);
    }
    public static function enqueueOnce(string $message, string $type = 'message'): void
    {
        if (self::contains($message, $type)) {
            return;
        }
        self::enqueue($message, $type);
    }
    public static function getQueue(): array
    {
        return \RegularLabs\Library\Application::getCurrent()->getMessageQueue();
    }
    private static function messageMatches(mixed $message, string $needle, bool $exact): bool
    {
        if (!is_string($message)) {
            return \false;
        }
        return $exact ? $message === $needle : str_contains($message, $needle);
    }
    private static function queueContains(string $needle, ?string $type, bool $exact): bool
    {
        foreach (self::getQueue() as $key => $message) {
            if (is_array($message) && isset($message['message'])) {
                if ($type && ($message['type'] ?? null) !== $type) {
                    continue;
                }
                if (self::messageMatches($message['message'], $needle, $exact)) {
                    return \true;
                }
                continue;
            }
            if ($type && $key !== $type) {
                continue;
            }
            foreach ((array) $message as $queued_message) {
                if (self::messageMatches($queued_message, $needle, $exact)) {
                    return \true;
                }
            }
        }
        return \false;
    }
}
