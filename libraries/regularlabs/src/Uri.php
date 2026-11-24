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
use Joomla\CMS\Router\Route as JRoute;
use Joomla\CMS\Uri\Uri as JUri;
class Uri
{
    /**
     * Adds the given url parameter (key + value) to the url or replaces it already exists
     */
    public static function addParameter(string $url, string $key, string $value = '', bool $replace = \true): string
    {
        if ($key == '') {
            return $url;
        }
        $uri = (array) self::parse($url);
        $query = self::parseQuery($uri['query'] ?? '');
        if (!$replace && isset($query[$key])) {
            return $url;
        }
        $query[$key] = $value;
        $uri['query'] = self::buildQuery($query);
        return self::createUrlFromArray($uri);
    }
    /**
     * Appends the given hash to the url or replaces it if there is already one
     */
    public static function appendHash(string $url = '', string $hash = ''): string
    {
        if ($hash == '') {
            return $url;
        }
        $uri = (array) self::parse($url);
        $uri['fragment'] = $hash;
        return self::createUrlFromArray($uri);
    }
    public static function buildQuery(array $query): string
    {
        return http_build_query($query);
    }
    /**
     * Converts an array of url parts (like made by parse_url) to a string
     */
    public static function createUrlFromArray(array $uri): string
    {
        $user = $uri['user'] ?? '';
        $pass = !empty($uri['pass']) ? ':' . $uri['pass'] : '';
        return (!empty($uri['scheme']) ? $uri['scheme'] . '://' : '') . ($user || $pass ? $user . $pass . '@' : '') . (!empty($uri['host']) ? $uri['host'] : '') . (!empty($uri['port']) ? ':' . $uri['port'] : '') . (!empty($uri['path']) ? $uri['path'] : '') . (!empty($uri['query']) ? '?' . $uri['query'] : '') . (!empty($uri['fragment']) ? '#' . $uri['fragment'] : '');
    }
    public static function decode(string $string, bool $urldecode = \true): string
    {
        return \RegularLabs\Library\EncodedPayload::decodeCompressed($string, '', $urldecode);
    }
    public static function decodeCompressed(string $string, string $default = '', bool $urlDecode = \true): string
    {
        return \RegularLabs\Library\EncodedPayload::decodeCompressed($string, $default, $urlDecode);
    }
    public static function encode(string $string, bool $urlencode = \true): string
    {
        return \RegularLabs\Library\EncodedPayload::encodeCompressed($string, $urlencode);
    }
    /**
     * Returns the full uri and optionally adds/replaces the hash
     */
    public static function get(string $hash = ''): string
    {
        $url = JUri::getInstance()->toString();
        if ($hash == '') {
            return $url;
        }
        return self::appendHash($url, $hash);
    }
    public static function getCompressedAttributes(): string
    {
        return \RegularLabs\Library\EncodedRequestPayload::decode('rlatt', 'rlatt_');
    }
    public static function getCurrentHost(): string
    {
        // hostname: give preference to SERVER_NAME, because this includes subdomains
        $host = $_SERVER['SERVER_NAME'] ?? '';
        $host = $host !== '' ? $host : $_SERVER['HTTP_HOST'] ?? '';
        return self::normalizeHost($host);
    }
    public static function getHost(string $url): string
    {
        $host = parse_url($url, \PHP_URL_HOST);
        if (!is_string($host) || $host === '') {
            return '';
        }
        return self::normalizeHost($host);
    }
    /**
     * Get the value of a given url parameter from the url
     */
    public static function getParameter(string $url, string $key): mixed
    {
        if (empty($key)) {
            return '';
        }
        $uri = self::parse($url);
        if (!isset($uri->query)) {
            return '';
        }
        $query = self::parseQuery($uri->query);
        return $query[$key] ?? '';
    }
    /**
     * Get all url parameters from the url
     */
    public static function getParameters(string $url): object
    {
        $uri = self::parse($url);
        if (!isset($uri->query)) {
            return (object) [];
        }
        $query = self::parseQuery($uri->query);
        return (object) $query;
    }
    public static function getSiteRoute(string $url, bool $xhtml = \true, int $tls = JRoute::TLS_IGNORE, bool $absolute = \false): string
    {
        if (\RegularLabs\Library\Document::isCli()) {
            return '';
        }
        return JRoute::link('site', $url, $xhtml, $tls, $absolute);
    }
    /**
     * Checks URL schemes after HTML-entity and browser whitespace normalization.
     * This does not validate destinations, DNS, redirects, or remote content.
     */
    public static function hasAllowedScheme(string $url, array $allowed_schemes = ['http', 'https'], bool $allow_relative = \true): bool
    {
        $url = \RegularLabs\Library\StringHelper::decodeHtmlEntities($url);
        $url = \RegularLabs\Library\RegEx::replace('[\x00-\x20\x7f]+', '', $url);
        if ($url === '') {
            return \false;
        }
        if (!\RegularLabs\Library\RegEx::startsWith('([a-z][a-z0-9+.-]*):', $url, $match)) {
            return $allow_relative;
        }
        return in_array(strtolower($match[1]), array_map('strtolower', $allowed_schemes), \true);
    }
    public static function isExternal(string $url): bool
    {
        return self::isExternalHost(self::getHost($url));
    }
    public static function isExternalHost(string $host): bool
    {
        $host = self::normalizeHost($host);
        $currentHost = self::getCurrentHost();
        if ($host === '' || $currentHost === '') {
            return \false;
        }
        return strcasecmp($currentHost, $host) !== 0;
    }
    public static function isPublicHttpUrl(string $url): bool
    {
        if (!self::isHttpUrl($url)) {
            return \false;
        }
        $host = self::getHost($url);
        if ($host === '' || !self::isExternalHost($host)) {
            return \false;
        }
        return !self::isLocalHost($host);
    }
    public static function parse(string $url): object
    {
        return (object) (parse_url($url) ?: []);
    }
    /**
     * Parse a query string into an associative array.
     */
    public static function parseQuery(string $string): array
    {
        $result = [];
        if ($string === '') {
            return $result;
        }
        foreach (explode('&', $string) as $kvp) {
            $parts = explode('=', $kvp, 2);
            $key = self::decodeQueryPart($parts[0]);
            $value = isset($parts[1]) ? self::decodeQueryPart($parts[1]) : null;
            if (!isset($result[$key])) {
                $result[$key] = $value;
                continue;
            }
            if (!is_array($result[$key])) {
                $result[$key] = [$result[$key]];
            }
            $result[$key][] = $value;
        }
        return $result;
    }
    /**
     * removes the given url parameter from the url
     */
    public static function removeParameter(string $url, string $key): string
    {
        if (empty($key)) {
            return $url;
        }
        $uri = (array) self::parse($url);
        if (!isset($uri['query'])) {
            return $url;
        }
        $query = self::parseQuery($uri['query']);
        unset($query[$key]);
        $uri['query'] = self::buildQuery($query);
        return self::createUrlFromArray($uri);
    }
    public static function route(string $url): string
    {
        return JRoute::_(JUri::root(\true) . '/' . $url);
    }
    private static function decodeQueryPart(string $value): string
    {
        return rawurldecode(str_replace('+', ' ', $value));
    }
    private static function isHttpUrl(string $url): bool
    {
        $scheme = strtolower((string) parse_url($url, \PHP_URL_SCHEME));
        return in_array($scheme, ['http', 'https'], \true);
    }
    private static function isLocalHost(string $host): bool
    {
        $host = self::normalizeHost($host);
        if (in_array($host, ['localhost', 'localhost.localdomain'], \true)) {
            return \true;
        }
        if (!filter_var($host, \FILTER_VALIDATE_IP)) {
            return \false;
        }
        return filter_var($host, \FILTER_VALIDATE_IP, \FILTER_FLAG_NO_PRIV_RANGE | \FILTER_FLAG_NO_RES_RANGE) === \false;
    }
    private static function normalizeHost(string $host): string
    {
        $host = trim($host);
        if ($host === '') {
            return '';
        }
        $parsed = parse_url('//' . $host, \PHP_URL_HOST);
        if (is_string($parsed) && $parsed !== '') {
            return trim($parsed, '[]');
        }
        $host = \RegularLabs\Library\RegEx::replaceAtEnd(':\d+', '', $host, '');
        return trim($host, '[]');
    }
}
