<?php

declare (strict_types=1);
namespace RegularLabs\Scoped\Detection\Cache;

use RegularLabs\Scoped\Psr\SimpleCache\InvalidArgumentException;
class CacheInvalidArgumentException extends CacheException implements InvalidArgumentException
{
}
