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
use Joomla\CMS\Http\HttpFactory as JHttpFactory;
use Joomla\Registry\Registry;
use RuntimeException;
use Throwable;
class Http
{
    private const MAX_RESPONSE_BYTES = 5242880;
    /**
     * Fetch the contents of the given external url from the Regular Labs server without response side effects
     */
    public static function fetchRegularLabsServerUrl(string $url, int $timeout = 20, string $default = '', array $post_data = []): string
    {
        if (!str_starts_with($url, 'http')) {
            $url = 'http://' . $url;
        }
        if (!self::isAllowedRegularLabsServerUrl($url)) {
            return $default;
        }
        $cache = self::getRegularLabsServerCache($url, $timeout, $default, $post_data);
        if ($cache->exists()) {
            return $cache->get();
        }
        $content = self::requestRegularLabsServerUrl($url, $timeout, $post_data);
        if ($content === '') {
            return $default;
        }
        return $cache->set($content);
    }
    /**
     * Get the contents of the given internal url
     */
    public static function get(string $url, int $timeout = 20, string $default = ''): string
    {
        if (\RegularLabs\Library\Uri::isExternal($url)) {
            return $default;
        }
        $content = @file_get_contents($url, \false, stream_context_create(['http' => ['timeout' => $timeout]]));
        if ($content !== \false) {
            return $content;
        }
        return self::getInternalContents($url, $timeout, $default);
    }
    /**
     * Get the contents of the given external url from the Regular Labs server
     */
    public static function getFromServer(string $url, int $timeout = 20, string $default = ''): string
    {
        if (!str_starts_with($url, 'http')) {
            $url = 'http://' . $url;
        }
        self::assertRegularLabsServerAccess($url);
        $content = self::fetchRegularLabsServerUrl($url, $timeout, $default);
        self::sendDownloadHeaders($url);
        return $content;
    }
    /**
     * Get the contents of the given url
     */
    public static function getFromUrl(string $url, int $timeout = 20, string $default = ''): string
    {
        $cache_ttl = \RegularLabs\Library\Input::getInt('cache', 0);
        $cache = new \RegularLabs\Library\Cache(['http.get_from_url', $url, $timeout, $default, $cache_ttl]);
        if ($cache_ttl) {
            $cache->useFiles($cache_ttl > 1 ? $cache_ttl : 0);
        }
        if ($cache->exists()) {
            return $cache->get();
        }
        $content = self::getContents($url, $timeout);
        if ($content == '') {
            return $default;
        }
        return $cache->set($content ?: $default);
    }
    public static function requestPublicUrl(string $url, int $timeout = 20, int $maxBytes = 1048576): ?object
    {
        return (new \RegularLabs\Library\PublicHttpClient())->request($url, $timeout, $maxBytes);
    }
    private static function assertRegularLabsServerAccess(string $url): void
    {
        if (!\RegularLabs\Library\Document::isAdministrator() || \RegularLabs\Library\User::isGuest()) {
            die;
        }
        if (!self::isAllowedRegularLabsServerUrl($url)) {
            die;
        }
    }
    /**
     * Load the contents of the given url
     */
    private static function getContents(string $url, int $timeout = 20, string $default = ''): string
    {
        $response = self::requestPublicUrl($url, $timeout, self::MAX_RESPONSE_BYTES);
        if (($response->code ?? 0) < 200 || ($response->code ?? 0) >= 300) {
            return $default;
        }
        $content = $response->body ?? $default;
        // Remove prefix and postfix stuff added by SocketTransport
        return \RegularLabs\Library\RegEx::replaceEntireString('\s*1c\s*(\{.*\})\s*0\s*', '$1', $content, 's');
    }
    private static function getInternalContents(string $url, int $timeout = 20, string $default = ''): string
    {
        try {
            // Adding a valid user agent string, otherwise some feed-servers returning an error
            $options = new Registry(['userAgent' => 'Mozilla/5.0 (Windows NT 6.1; WOW64; rv:41.0) Gecko/20100101 Firefox/41.0']);
            $response = JHttpFactory::getHttp($options)->get($url, [], $timeout);
            $content = $response->body ?? $default;
        } catch (RuntimeException $e) {
            return $default;
        }
        return \RegularLabs\Library\RegEx::replaceEntireString('\s*1c\s*(\{.*\})\s*0\s*', '$1', $content, 's');
    }
    private static function getRegularLabsServerCache(string $url, int $timeout, string $default, array $post_data = []): \RegularLabs\Library\Cache
    {
        $cache_ttl = \RegularLabs\Library\Input::getInt('cache', 0);
        $post_data_key = $post_data === [] ? '' : hash('sha256', http_build_query($post_data, '', '&', \PHP_QUERY_RFC3986));
        $cache = new \RegularLabs\Library\Cache(['http.get_from_server', $url, $timeout, $default, $cache_ttl, $post_data_key]);
        if ($cache_ttl) {
            $cache->useFiles($cache_ttl > 1 ? $cache_ttl : 0);
        }
        return $cache;
    }
    private static function requestRegularLabsServerUrl(string $url, int $timeout, array $post_data): string
    {
        try {
            $options = new Registry(['follow_location' => \false, 'userAgent' => 'Regular Labs HTTP Client']);
            $http = JHttpFactory::getHttp($options);
            $response = $post_data === [] ? $http->get($url, [], $timeout) : $http->post($url, $post_data, [], $timeout);
        } catch (Throwable) {
            return '';
        }
        $code = method_exists($response, 'getStatusCode') ? (int) $response->getStatusCode() : (int) ($response->code ?? 0);
        if ($code < 200 || $code >= 300) {
            return '';
        }
        $content = method_exists($response, 'getBody') ? (string) $response->getBody() : (string) ($response->body ?? '');
        if (strlen($content) > self::MAX_RESPONSE_BYTES) {
            return '';
        }
        return \RegularLabs\Library\RegEx::replaceEntireString('\s*1c\s*(\{.*\})\s*0\s*', '$1', $content, 's');
    }
    private static function isAllowedRegularLabsServerUrl(string $url): bool
    {
        $parts = parse_url($url);
        if (($parts['host'] ?? '') !== 'download.regularlabs.com') {
            return \false;
        }
        $path = $parts['path'] ?? '';
        return in_array($path, ['/extensions.php', '/extensions.json', '/extensions.xml', '/check_key.php'], \true);
    }
    private static function sendDownloadHeaders(string $url): void
    {
        $content_type = str_contains($url, '.json') || str_contains($url, 'format=json') ? 'application/json' : 'text/xml';
        header('Pragma: public');
        header('Expires: 0');
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Cache-Control: public');
        header('Content-type: ' . $content_type);
    }
}
