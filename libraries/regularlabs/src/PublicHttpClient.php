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
use Closure;
class PublicHttpClient
{
    private const MAX_REDIRECTS = 5;
    private const MAX_RESPONSE_BYTES = 10485760;
    private const MAX_TIMEOUT = 30;
    private readonly \RegularLabs\Library\HttpUrlGuard $guard;
    private readonly Closure $transport;
    public function __construct(?\RegularLabs\Library\HttpUrlGuard $guard = null, ?Closure $transport = null)
    {
        $this->guard = $guard ?? new \RegularLabs\Library\HttpUrlGuard();
        $this->transport = $transport ?? $this->requestWithCurl(...);
    }
    public function request(string $url, int $timeout = 20, int $maxBytes = 1048576, int $maxRedirects = self::MAX_REDIRECTS): ?object
    {
        $timeout = min(self::MAX_TIMEOUT, max(1, $timeout));
        $maxBytes = min(self::MAX_RESPONSE_BYTES, max(1, $maxBytes));
        $maxRedirects = min(self::MAX_REDIRECTS, max(0, $maxRedirects));
        for ($redirect = 0; $redirect <= $maxRedirects; $redirect++) {
            $endpoint = $this->guard->getApprovedEndpoint($url);
            if ($endpoint === null) {
                return null;
            }
            $response = ($this->transport)($url, $endpoint, $timeout, $maxBytes);
            if (!is_object($response) || !isset($response->code, $response->body, $response->headers) || strlen((string) $response->body) > $maxBytes) {
                return null;
            }
            if (!in_array((int) $response->code, [301, 302, 303, 307, 308], \true)) {
                return $response;
            }
            $location = $response->headers['location'] ?? '';
            if (!is_string($location) || $location === '' || $redirect === $maxRedirects) {
                return null;
            }
            $url = self::resolveRedirectUrl($url, $location);
            if ($url === '') {
                return null;
            }
        }
        return null;
    }
    private static function resolveRedirectUrl(string $url, string $location): string
    {
        $location = trim($location);
        if (\RegularLabs\Library\RegEx::match('[\x00-\x20\x7f]', $location, $match, '')) {
            return '';
        }
        if (\RegularLabs\Library\RegEx::startsWith('https?://', $location, $match, 'i')) {
            return $location;
        }
        $parts = parse_url($url);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = (string) ($parts['host'] ?? '');
        if ($scheme === '' || $host === '') {
            return '';
        }
        if (str_starts_with($location, '//')) {
            return $scheme . ':' . $location;
        }
        $authority = $scheme . '://' . $host;
        $port = $parts['port'] ?? null;
        if ($port !== null) {
            $authority .= ':' . $port;
        }
        if (str_starts_with($location, '/')) {
            return $authority . $location;
        }
        $path = (string) ($parts['path'] ?? '/');
        if (str_starts_with($location, '?')) {
            return $authority . $path . $location;
        }
        if (str_starts_with($location, '#')) {
            $query = isset($parts['query']) ? '?' . $parts['query'] : '';
            return $authority . $path . $query . $location;
        }
        $path = rtrim(str_replace('\\', '/', dirname($path)), '/');
        return $authority . ($path === '' ? '' : $path) . '/' . $location;
    }
    private function requestWithCurl(string $url, object $endpoint, int $timeout, int $maxBytes): ?object
    {
        if (!function_exists('curl_init')) {
            return null;
        }
        $deadline = microtime(\true) + $timeout;
        foreach ($endpoint->addresses as $address) {
            $remainingTimeout = (int) ceil($deadline - microtime(\true));
            if ($remainingTimeout < 1) {
                return null;
            }
            $response = $this->requestAddressWithCurl($url, $endpoint, $address, $remainingTimeout, $maxBytes);
            if ($response !== null) {
                return $response;
            }
        }
        return null;
    }
    private function requestAddressWithCurl(string $url, object $endpoint, string $address, int $timeout, int $maxBytes): ?object
    {
        $body = '';
        $headers = [];
        $tooLarge = \false;
        $curl = curl_init();
        if ($curl === \false) {
            return null;
        }
        $address = str_contains($address, ':') ? '[' . $address . ']' : $address;
        $options = [\CURLOPT_URL => $url, \CURLOPT_FOLLOWLOCATION => \false, \CURLOPT_RETURNTRANSFER => \false, \CURLOPT_PROXY => '', \CURLOPT_CONNECTTIMEOUT => min(5, $timeout), \CURLOPT_TIMEOUT => $timeout, \CURLOPT_NOSIGNAL => \true, \CURLOPT_PROTOCOLS => \CURLPROTO_HTTP | \CURLPROTO_HTTPS, \CURLOPT_USERAGENT => 'Regular Labs HTTP Client', \CURLOPT_HEADERFUNCTION => static function ($curl, string $line) use (&$headers): int {
            $length = strlen($line);
            $parts = explode(':', $line, 2);
            if (count($parts) === 2) {
                $headers[strtolower(trim($parts[0]))] = trim($parts[1]);
            }
            return $length;
        }, \CURLOPT_WRITEFUNCTION => static function ($curl, string $chunk) use (&$body, &$tooLarge, $maxBytes): int {
            if (strlen($body) + strlen($chunk) > $maxBytes) {
                $tooLarge = \true;
                return 0;
            }
            $body .= $chunk;
            return strlen($chunk);
        }];
        if (!$endpoint->isIpLiteral) {
            $options[\CURLOPT_RESOLVE] = [$endpoint->host . ':' . $endpoint->port . ':' . $address];
        }
        curl_setopt_array($curl, $options);
        $success = curl_exec($curl);
        $code = (int) curl_getinfo($curl, \CURLINFO_HTTP_CODE);
        if ($success === \false || $tooLarge) {
            return null;
        }
        return (object) ['code' => $code, 'body' => $body, 'headers' => $headers];
    }
}
