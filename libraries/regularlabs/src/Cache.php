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
use Exception;
use Joomla\CMS\Cache\CacheControllerFactoryInterface as JCacheControllerFactoryInterface;
use Joomla\CMS\Cache\Controller\OutputController as JOutputController;
use Joomla\CMS\Factory as JFactory;
use Joomla\CMS\Uri\Uri as JUri;
use JsonSerializable;
use Traversable;
class Cache
{
    static array $cache = [];
    /**
     * @var [JOutputController]
     */
    private array $file_cache_controllers = [];
    private bool $force_caching = \true;
    private string $group;
    private string $id;
    private int $time_to_life_in_minutes = 0;
    private bool $use_files = \false;
    public function __construct(mixed $id = null, string $group = 'regularlabs', int $class_offset = 0)
    {
        $this->id = $this->getId($id, $class_offset);
        $this->group = $group;
    }
    public function exists(): bool
    {
        if (!$this->use_files) {
            return $this->existsMemory();
        }
        return $this->existsMemory() || $this->existsFile();
    }
    public function get(): mixed
    {
        return $this->use_files ? $this->getFile() : $this->getMemory();
    }
    public function getAsArray(): array
    {
        $value = $this->get();
        if (is_array($value)) {
            return $value;
        }
        if (is_object($value)) {
            return (array) $value;
        }
        if (is_string($value)) {
            return [$value];
        }
        if ($this->exists()) {
            $this->reset();
        }
        return [];
    }
    public function getAsObject(): object
    {
        $value = $this->get();
        if (is_object($value)) {
            return $value;
        }
        if (is_array($value)) {
            return (object) $value;
        }
        if ($this->exists()) {
            $this->reset();
        }
        return (object) [];
    }
    public function reset(): void
    {
        unset(static::$cache[$this->id]);
        if ($this->use_files) {
            $this->getFileCache()->remove($this->id);
        }
    }
    public function resetAll(): void
    {
        $force_clear_files = (bool) (func_get_args()[0] ?? \false);
        static::$cache = [];
        if ($this->use_files || $force_clear_files) {
            $this->getFileCache()->clean($this->group);
        }
    }
    public function resetAllFiles(): void
    {
        $this->resetAll(\true);
    }
    public function set(mixed $data): mixed
    {
        return $this->use_files ? $this->setFile($data) : $this->setMemory($data);
    }
    public function setTimeToLife(string|int $time_to_life_in_minutes = 0, bool $force_caching = \true): self
    {
        if (is_string($time_to_life_in_minutes)) {
            $time_to_life_in_minutes = round(strtotime($time_to_life_in_minutes) - time()) / 60;
        }
        $this->use_files = \true;
        $this->time_to_life_in_minutes = $time_to_life_in_minutes;
        $this->force_caching = $force_caching;
        return $this;
    }
    public function useFiles(int $time_to_life_in_minutes = 0, bool $force_caching = \true): self
    {
        $this->setTimeToLife($time_to_life_in_minutes, $force_caching);
        return $this;
    }
    public function usesFiles(): bool
    {
        return $this->use_files;
    }
    private static function getCacheControllerFactory(): JCacheControllerFactoryInterface
    {
        return JFactory::getContainer()->get(JCacheControllerFactoryInterface::class);
    }
    private function existsFile(): bool
    {
        if (\RegularLabs\Library\Document::isDebug()) {
            return \false;
        }
        return $this->getFileCache()->contains($this->id);
    }
    private function existsMemory(): bool
    {
        return array_key_exists($this->id, static::$cache);
    }
    /**
     * @throws Exception
     */
    private function getFile(): mixed
    {
        if ($this->existsMemory()) {
            return $this->getMemory();
        }
        $data = $this->getFileCache()->get($this->id);
        $this->setMemory($data);
        return $data;
    }
    private function getFileCache(): JOutputController
    {
        $options = ['defaultgroup' => $this->group];
        if ($this->time_to_life_in_minutes) {
            $options['lifetime'] = $this->time_to_life_in_minutes;
        }
        if ($this->force_caching) {
            $options['caching'] = \true;
        }
        $id = json_encode($options);
        if (isset($this->file_cache_controllers[$id])) {
            return $this->file_cache_controllers[$id];
        }
        $this->file_cache_controllers[$id] = self::getCacheControllerFactory()->createCacheController('output', $options);
        return $this->file_cache_controllers[$id];
    }
    private function getId(mixed $id = null, int $class_offset = 0): string
    {
        // This method is 2 calls from the calling class
        $class_offset += 2;
        if (is_null($id)) {
            $caller = debug_backtrace(\DEBUG_BACKTRACE_PROVIDE_OBJECT, 1 + $class_offset)[$class_offset];
            $id = [$caller['class'], $caller['function'], $caller['args']];
        }
        if (!is_string($id)) {
            $id = json_encode($this->normalizeIdValue($id));
        }
        $domain = rtrim(JUri::root(), '/');
        return md5($domain . '.' . $id);
    }
    private function getMemory(): mixed
    {
        if (!$this->existsMemory()) {
            return null;
        }
        $data = static::$cache[$this->id];
        return is_object($data) ? clone $data : $data;
    }
    private function normalizeIdValue(mixed $value): mixed
    {
        if (is_null($value) || is_scalar($value)) {
            return $value;
        }
        if (is_array($value)) {
            $value = array_map([$this, 'normalizeIdValue'], $value);
            if (!array_is_list($value)) {
                ksort($value);
            }
            return $value;
        }
        if ($value instanceof JsonSerializable) {
            return $this->normalizeIdValue($value->jsonSerialize());
        }
        if ($value instanceof Traversable) {
            return $this->normalizeIdValue(iterator_to_array($value));
        }
        if (is_object($value)) {
            if (method_exists($value, 'toObject')) {
                return $this->normalizeIdValue($value->toObject());
            }
            return ['__class' => $value::class, '__value' => $this->normalizeIdValue((array) $value)];
        }
        return gettype($value);
    }
    /**
     * @throws Exception
     */
    private function setFile(mixed $data): mixed
    {
        $this->setMemory($data);
        if (\RegularLabs\Library\Document::isDebug()) {
            return $data;
        }
        $this->getFileCache()->store($data, $this->id);
        return $data;
    }
    private function setMemory(mixed $data): mixed
    {
        static::$cache[$this->id] = $data;
        return $data;
    }
}
