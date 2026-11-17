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
class ClientIp
{
    private const FORWARDED_HEADERS = ['HTTP_CF_CONNECTING_IP', 'HTTP_TRUE_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP'];
    public static function get(?array $server = null, array|string|null $trusted_proxy_ranges = null): string
    {
        $server ??= $_SERVER;
        $direct_peer = self::normalize((string) ($server['REMOTE_ADDR'] ?? ''));
        if ($direct_peer === '') {
            return '';
        }
        $trusted_proxy_ranges ??= self::getConfiguredTrustedProxyRanges();
        if (!self::isTrustedProxy($direct_peer, $trusted_proxy_ranges)) {
            return $direct_peer;
        }
        foreach (self::FORWARDED_HEADERS as $header) {
            $value = (string) ($server[$header] ?? '');
            if ($value === '') {
                continue;
            }
            $client_ip = $header === 'HTTP_X_FORWARDED_FOR' ? self::getFromForwardedChain($value, $trusted_proxy_ranges) : self::getFirstValid($value);
            if ($client_ip !== '') {
                return $client_ip;
            }
        }
        return $direct_peer;
    }
    private static function getConfiguredTrustedProxyRanges(): string
    {
        $params = \RegularLabs\Library\Parameters::getPlugin('regularlabs');
        return (string) ($params->trusted_proxy_ranges ?? '');
    }
    private static function getFirstValid(string $value): string
    {
        foreach (explode(',', $value) as $ip) {
            $ip = self::normalize($ip);
            if ($ip !== '') {
                return $ip;
            }
        }
        return '';
    }
    private static function getFromForwardedChain(string $value, array|string $trusted_proxy_ranges): string
    {
        $addresses = [];
        foreach (explode(',', $value) as $ip) {
            $ip = self::normalize($ip);
            if ($ip !== '') {
                $addresses[] = $ip;
            }
        }
        foreach (array_reverse($addresses) as $ip) {
            if (self::isTrustedProxy($ip, $trusted_proxy_ranges)) {
                continue;
            }
            return $ip;
        }
        return '';
    }
    private static function isInRange(string $ip, string $range): bool
    {
        [$network, $prefix] = array_pad(explode('/', $range, 2), 2, null);
        $network = self::normalize($network);
        if ($network === '') {
            return \false;
        }
        $ip_binary = inet_pton($ip);
        $network_binary = inet_pton($network);
        if ($ip_binary === \false || $network_binary === \false || strlen($ip_binary) !== strlen($network_binary)) {
            return \false;
        }
        $maximum_prefix = strlen($ip_binary) * 8;
        if (!is_null($prefix) && !ctype_digit($prefix)) {
            return \false;
        }
        $prefix = is_null($prefix) ? $maximum_prefix : (int) $prefix;
        if ($prefix < 0 || $prefix > $maximum_prefix) {
            return \false;
        }
        $full_bytes = intdiv($prefix, 8);
        if (substr($ip_binary, 0, $full_bytes) !== substr($network_binary, 0, $full_bytes)) {
            return \false;
        }
        $remaining_bits = $prefix % 8;
        if ($remaining_bits === 0) {
            return \true;
        }
        $mask = 0xff << 8 - $remaining_bits & 0xff;
        return (ord($ip_binary[$full_bytes]) & $mask) === (ord($network_binary[$full_bytes]) & $mask);
    }
    private static function isTrustedProxy(string $ip, array|string $trusted_proxy_ranges): bool
    {
        if (is_string($trusted_proxy_ranges)) {
            $trusted_proxy_ranges = trim($trusted_proxy_ranges);
            $trusted_proxy_ranges = $trusted_proxy_ranges === '' ? [] : \RegularLabs\Library\RegEx::split('[\s,]+', $trusted_proxy_ranges, '', -1, 0);
        }
        foreach ($trusted_proxy_ranges as $range) {
            $range = trim((string) $range);
            if ($range !== '' && self::isInRange($ip, $range)) {
                return \true;
            }
        }
        return \false;
    }
    private static function normalize(string $ip): string
    {
        $ip = trim($ip);
        if (str_starts_with(strtolower($ip), '::ffff:')) {
            $mapped_ip = substr($ip, 7);
            if (filter_var($mapped_ip, \FILTER_VALIDATE_IP, \FILTER_FLAG_IPV4)) {
                return $mapped_ip;
            }
        }
        return filter_var($ip, \FILTER_VALIDATE_IP) ? $ip : '';
    }
}
