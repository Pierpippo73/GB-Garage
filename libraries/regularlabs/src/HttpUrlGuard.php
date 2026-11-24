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
use Closure;
class HttpUrlGuard
{
    private const RESERVED_IP_RANGES = ['0.0.0.0/8', '10.0.0.0/8', '100.64.0.0/10', '127.0.0.0/8', '169.254.0.0/16', '172.16.0.0/12', '192.0.0.0/24', '192.0.2.0/24', '192.31.196.0/24', '192.52.193.0/24', '192.88.99.0/24', '192.168.0.0/16', '192.175.48.0/24', '198.18.0.0/15', '198.51.100.0/24', '203.0.113.0/24', '224.0.0.0/4', '240.0.0.0/4', '::/128', '::1/128', '::ffff:0:0/96', '64:ff9b::/96', '64:ff9b:1::/48', '100::/64', '2001::/23', '2001:db8::/32', '2002::/16', '2620:4f:8000::/48', '3fff::/20', '5f00::/16', 'fc00::/7', 'fe80::/10', 'ff00::/8'];
    private readonly Closure $resolver;
    public function __construct(?Closure $resolver = null, ?Closure $dnsLookup = null)
    {
        $this->resolver = $resolver ?? static fn(string $host): array => self::resolveHost($host, $dnsLookup);
    }
    public static function isPublicIp(string $ip): bool
    {
        if (filter_var($ip, \FILTER_VALIDATE_IP) === \false) {
            return \false;
        }
        foreach (self::RESERVED_IP_RANGES as $range) {
            if (self::isInRange($ip, $range)) {
                return \false;
            }
        }
        return \true;
    }
    public function getApprovedEndpoint(string $url): ?object
    {
        if (\RegularLabs\Library\RegEx::match('[\x00-\x20\x7f]', $url, $match, '')) {
            return null;
        }
        $parts = parse_url($url);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = trim(strtolower((string) ($parts['host'] ?? '')), '[]');
        if (!in_array($scheme, ['http', 'https'], \true) || $host === '' || isset($parts['user']) || isset($parts['pass'])) {
            return null;
        }
        $port = (int) ($parts['port'] ?? ($scheme === 'https' ? 443 : 80));
        if ($port < 1 || $port > 65535) {
            return null;
        }
        $isIpLiteral = filter_var($host, \FILTER_VALIDATE_IP) !== \false;
        $addresses = $isIpLiteral ? [$host] : ($this->resolver)(rtrim($host, '.'));
        $addresses = array_values(array_unique(array_filter($addresses, 'is_string')));
        if (empty($addresses)) {
            return null;
        }
        foreach ($addresses as $address) {
            if (!self::isPublicIp($address)) {
                return null;
            }
        }
        return (object) ['host' => $host, 'port' => $port, 'addresses' => $addresses, 'isIpLiteral' => $isIpLiteral];
    }
    private static function isInRange(string $ip, string $range): bool
    {
        [$network, $prefixLength] = explode('/', $range, 2);
        $ipBytes = inet_pton($ip);
        $networkBytes = inet_pton($network);
        if ($ipBytes === \false || $networkBytes === \false || strlen($ipBytes) !== strlen($networkBytes)) {
            return \false;
        }
        $prefixLength = (int) $prefixLength;
        $fullBytes = intdiv($prefixLength, 8);
        $remainingBits = $prefixLength % 8;
        if ($fullBytes > 0 && substr($ipBytes, 0, $fullBytes) !== substr($networkBytes, 0, $fullBytes)) {
            return \false;
        }
        if ($remainingBits === 0) {
            return \true;
        }
        $mask = 0xff << 8 - $remainingBits & 0xff;
        return (ord($ipBytes[$fullBytes]) & $mask) === (ord($networkBytes[$fullBytes]) & $mask);
    }
    private static function resolveHost(string $host, ?Closure $dnsLookup): array
    {
        return self::resolveHostRecords($host, [], 0, $dnsLookup);
    }
    private static function resolveHostRecords(string $host, array $visited, int $depth, ?Closure $dnsLookup): array
    {
        $host = strtolower(rtrim($host, '.'));
        if ($host === '' || isset($visited[$host]) || $depth > 8) {
            return [];
        }
        $visited[$host] = \true;
        $records = $dnsLookup ? $dnsLookup($host) : (@dns_get_record($host, \DNS_A | \DNS_AAAA | \DNS_CNAME) ?: []);
        $addresses = [];
        $targets = [];
        foreach ($records as $record) {
            if (isset($record['ip'])) {
                $addresses[] = $record['ip'];
            }
            if (isset($record['ipv6'])) {
                $addresses[] = $record['ipv6'];
            }
            if (isset($record['target'])) {
                $targets[] = (string) $record['target'];
            }
        }
        if (empty($addresses)) {
            foreach (array_unique($targets) as $target) {
                $addresses = [...$addresses, ...self::resolveHostRecords($target, $visited, $depth + 1, $dnsLookup)];
            }
        }
        if (empty($addresses)) {
            $addresses = @gethostbynamel($host) ?: [];
        }
        return array_values(array_unique($addresses));
    }
}
