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
use Joomla\CMS\Uri\Uri as JUri;
use Joomla\Filesystem\File as JFile;
use Joomla\Filesystem\Folder as JFolder;
class ImageFile
{
    private const MAX_REMOTE_IMAGE_BYTES = 10485760;
    public static function cleanPath(string $path): string
    {
        $path = rawurldecode(str_replace('\\', '/', $path));
        $path_site = str_replace('\\', '/', JPATH_SITE) . '/';
        if (str_starts_with($path, $path_site)) {
            $path = substr($path, strlen($path_site));
        }
        $path = ltrim(str_replace(JUri::root(), '', $path), '/');
        $path = strtok($path, '?');
        $path = strtok($path, '#');
        if ($path === '' || str_contains($path, "\x00")) {
            return '';
        }
        // Preserve the Unicode normalization form stored in the path. Generic File
        // helpers normalize strings to NFC, which can point at a different file on
        // filesystems where Joomla stored a decomposed filename.
        $folder = \RegularLabs\Library\File::normalizeSiteFolder(dirname($path));
        $file = ltrim(basename($path), '/');
        if ($folder === '' || $file === '' || in_array($file, ['.', '..'], \true)) {
            return '';
        }
        return $folder === '.' ? $file : $folder . '/' . $file;
    }
    public static function countImagesFromText(string $text): int
    {
        if ($text === '') {
            return 0;
        }
        return count(self::getAllImagesFromText($text));
    }
    public static function downloadExternal(string $file, string $folder = 'images/remote'): string
    {
        if (\RegularLabs\Library\File::isInternal($file)) {
            return '';
        }
        if (!\RegularLabs\Library\Uri::isPublicHttpUrl($file) || !\RegularLabs\Library\File::isImage($file)) {
            return '';
        }
        $destination = self::getDownloadPath($file, $folder);
        if (is_file($destination)) {
            $existingSize = filesize($destination);
            $existingContents = $existingSize !== \false && $existingSize <= self::MAX_REMOTE_IMAGE_BYTES ? file_get_contents($destination) : \false;
            if ($existingContents !== \false && self::isValidDownloadedImage($existingContents, $destination)) {
                return $destination;
            }
            JFile::delete($destination);
        }
        $response = \RegularLabs\Library\Http::requestPublicUrl($file, 20, self::MAX_REMOTE_IMAGE_BYTES);
        if (($response->code ?? 0) !== 200 || empty($response->body)) {
            return '';
        }
        if (!self::isValidDownloadedImage($response->body, $destination) || !self::writeAtomically($destination, $response->body)) {
            return '';
        }
        return $destination;
    }
    public static function getAllImagesFromText(string $text): array
    {
        $cache = new \RegularLabs\Library\Cache([__METHOD__, md5($text)]);
        if ($cache->exists()) {
            return $cache->get();
        }
        \RegularLabs\Library\RegEx::matchAll('<img\s[^>]*src=([\'"]).*?\1[^>]*>', $text, $matches);
        $images = [];
        foreach ($matches as $i => $match) {
            $images[$i + 1] = (object) \RegularLabs\Library\HtmlTag::getAttributes($match[0]);
        }
        return $cache->set($images);
    }
    public static function getDownloadPath(string $file, string $folder = 'images/remote'): string
    {
        $path = \RegularLabs\Library\Uri::parse($file)->path ?? $file;
        $extension = pathinfo($path, \PATHINFO_EXTENSION);
        $file_name = JFile::stripExt($path);
        $host = \RegularLabs\Library\Uri::getHost($file);
        if ($host !== '') {
            $file_name = $host . '/' . ltrim($file_name, '/');
        }
        $file_name = \RegularLabs\Library\RegEx::replace('[^a-zA-Z0-9_\-\/]', '-', $file_name);
        return \RegularLabs\Library\File::getSiteFolderPath($folder, 'images/remote') . '/' . $file_name . '.' . $extension;
    }
    public static function getFolderData(string $folder, array &$cache): array
    {
        if (isset($cache[$folder])) {
            return $cache[$folder];
        }
        $cache[$folder] = [];
        if (!self::exists($folder . '/data.txt')) {
            return $cache[$folder];
        }
        $data = file_get_contents($folder . '/data.txt');
        $data = str_replace("\r", '', $data);
        $data = explode("\n", $data);
        foreach ($data as $data_line) {
            self::parseDataLine($data_line, $cache[$folder]);
        }
        return $cache[$folder];
    }
    public static function getImageDataFromText(string $text, mixed $id): object
    {
        if ($text === '') {
            return (object) [];
        }
        $images = self::getAllImagesFromText($text);
        if (empty($images)) {
            return (object) [];
        }
        if ($id === 'random') {
            $id = rand(1, count($images));
        }
        return isset($images[$id]) ? \RegularLabs\Library\ObjectHelper::clone($images[$id]) : (object) [];
    }
    public static function getInfo(string $file, string $file_path): object
    {
        $file_path = urldecode($file_path);
        $path_info = @pathinfo($file_path);
        $file_name = $path_info['basename'] ?? basename($file_path);
        $file_stem = $path_info['filename'] ?? JFile::stripExt($file_name);
        $file_extension = $path_info['extension'] ?? pathinfo($file_name, \PATHINFO_EXTENSION);
        $size_info = [];
        if (\RegularLabs\Library\File::isInternal($file_path)) {
            $size_info = @getimagesize($file_path) ?: [];
            if (empty($size_info) && strtolower($file_extension) === 'svg') {
                $size_info = self::getSvgDimensions($file_path);
            }
        }
        return (object) ['folder' => \RegularLabs\Library\File::getDirName($file), 'folder_path' => $path_info['dirname'] ?? null, 'file' => $file, 'file_path' => $file_path, 'file_name' => $file_name, 'file_stem' => $file_stem, 'file_extension' => $file_extension, 'mime' => $size_info['mime'] ?? null, 'width' => $size_info[0] ?? null, 'height' => $size_info[1] ?? null];
    }
    private static function getSvgDimensions(string $file_path): array
    {
        $content = @file_get_contents($file_path, \false, null, 0, 65536);
        if ($content === \false || !\RegularLabs\Library\RegEx::match('<svg\b[^>]*>', $content, $match)) {
            return [];
        }
        $attributes = \RegularLabs\Library\HtmlTag::getAttributes($match[0]);
        $width = self::getSvgNumericDimension($attributes['width'] ?? '');
        $height = self::getSvgNumericDimension($attributes['height'] ?? '');
        if ($width > 0 && $height > 0) {
            return [$width, $height, 'mime' => 'image/svg+xml'];
        }
        $viewBox = $attributes['viewbox'] ?? $attributes['viewBox'] ?? '';
        if (!\RegularLabs\Library\RegEx::match('^\s*[-+]?(?:\d+(?:\.\d*)?|\.\d+)[,\s]+[-+]?(?:\d+(?:\.\d*)?|\.\d+)' . '[,\s]+(\d+(?:\.\d*)?|\.\d+)[,\s]+(\d+(?:\.\d*)?|\.\d+)\s*$', $viewBox, $dimensions)) {
            return [];
        }
        return [(int) round((float) $dimensions[1]), (int) round((float) $dimensions[2]), 'mime' => 'image/svg+xml'];
    }
    private static function getSvgNumericDimension(string $value): int
    {
        if (!\RegularLabs\Library\RegEx::match('^\s*(\d+(?:\.\d*)?|\.\d+)(?:px)?\s*$', $value, $match)) {
            return 0;
        }
        return (int) round((float) $match[1]);
    }
    private static function addDataValue(array &$data, string $file, string $type, string $value): void
    {
        if (!isset($data[$file])) {
            $data[$file] = [];
        }
        $data[$file][$type] = $value;
    }
    private static function exists(string $file): bool
    {
        $file = urldecode($file);
        return $file && file_exists($file) && is_file($file);
    }
    private static function isValidDownloadedImage(string $contents, string $destination): bool
    {
        $imageInfo = @getimagesizefromstring($contents);
        if ($imageInfo === \false || empty($imageInfo['mime'])) {
            return \false;
        }
        $extensionsByMime = ['image/avif' => ['avif'], 'image/bmp' => ['bmp'], 'image/gif' => ['gif'], 'image/jpeg' => ['jpg', 'jpeg', 'jpe'], 'image/png' => ['png'], 'image/webp' => ['webp'], 'image/x-ms-bmp' => ['bmp']];
        $extension = strtolower(pathinfo($destination, \PATHINFO_EXTENSION));
        return in_array($extension, $extensionsByMime[$imageInfo['mime']] ?? [], \true);
    }
    private static function parseDataLine(string $data_line, array &$data): void
    {
        if (empty($data_line) || $data_line[0] == '#' || !str_contains($data_line, '=')) {
            return;
        }
        [$key, $value] = explode('=', $data_line, 2);
        if (!\RegularLabs\Library\RegEx::matchesEntireString('(?<file>.*?)\[(?<type>.*)\]', $key, $match)) {
            return;
        }
        self::addDataValue($data, $match['file'], $match['type'], $value);
    }
    private static function writeAtomically(string $destination, string $contents): bool
    {
        $folder = dirname($destination);
        if (!is_dir($folder) && !JFolder::create($folder)) {
            return \false;
        }
        $temporaryFile = tempnam($folder, '.download-');
        if ($temporaryFile === \false) {
            return \false;
        }
        $written = file_put_contents($temporaryFile, $contents, \LOCK_EX);
        if ($written !== strlen($contents) || !rename($temporaryFile, $destination)) {
            @unlink($temporaryFile);
            return \false;
        }
        @chmod($destination, 0644);
        return \true;
    }
}
