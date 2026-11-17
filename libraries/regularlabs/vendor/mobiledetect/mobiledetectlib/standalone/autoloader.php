<?php

namespace RegularLabs\Scoped;

$dir = \dirname(__FILE__);
\spl_autoload_register(function ($class) use ($dir) {
    $classMap = [
        // "mobiledetect/mobiledetectlib"
        "RegularLabs\\Scoped\\Detection\\Cache\\Cache" => $dir . "/../src/Cache/Cache.php",
        "RegularLabs\\Scoped\\Detection\\Cache\\CacheException" => $dir . "/../src/Cache/CacheException.php",
        "RegularLabs\\Scoped\\Detection\\Cache\\CacheInvalidArgumentException" => $dir . "/../src/Cache/CacheInvalidArgumentException.php",
        "RegularLabs\\Scoped\\Detection\\Exception\\MobileDetectException" => $dir . "/../src/Exception/MobileDetectException.php",
        "RegularLabs\\Scoped\\Detection\\Exception\\MobileDetectExceptionCode" => $dir . "/../src/Exception/MobileDetectExceptionCode.php",
        "RegularLabs\\Scoped\\Detection\\MobileDetect" => $dir . "/../src/MobileDetect.php",
        // "psr/simple-cache"
        "RegularLabs\\Scoped\\Psr\\SimpleCache\\CacheException" => $dir . "/deps/simple-cache/src/CacheException.php",
        "RegularLabs\\Scoped\\Psr\\SimpleCache\\CacheInterface" => $dir . "/deps/simple-cache/src/CacheInterface.php",
        "RegularLabs\\Scoped\\Psr\\SimpleCache\\InvalidArgumentException" => $dir . "/deps/simple-cache/src/InvalidArgumentException.php",
    ];
    $fileFound = $classMap[$class] ?? \false;
    if ($fileFound) {
        require $fileFound;
        return \true;
    }
    return \false;
});
