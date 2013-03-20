<?php
namespace lagden;

use sfConfig as sfConfig;
use sfFileCache as sfFileCache;

// Depedencies symfony 1.4 libs

class FileCache
{
    static public $cache = null;
    static public $file_cache_dir = null;

    static public function getInstance($dir=null)
    {
        $dir = ($dir) ? $dir : "myAppCache";
        return (!static::$cache instanceof sfFileCache) ? static::setInstance($dir) : static::$cache;
    }

    static public function setInstance($dir=null)
    {
        $dir = ($dir) ? $dir : "myAppDirCache";
        static::$file_cache_dir = sfConfig::get('sf_cache_dir') . DIRECTORY_SEPARATOR . $dir;
        static::$cache = new sfFileCache(array('cache_dir'=>static::$file_cache_dir));
        return static::$cache;
    }

    static public function setCache($name, $value, $lt=3600000)
    {
        $cache = static::getInstance();
        return $cache->set($name, serialize($value), $lt);
    }

    static public function getCache($name)
    {
        $cache = static::getInstance();
        if ($cache->has($name))
        {
            $cached = $cache->get($name);
            if (!empty($cached))
            {
                return unserialize($cached);
            }
        }
        return false;
    }

    static public function cleanCache($mode = sfCache::ALL)
    {
        $cache = static::getInstance();
        return $cache->clean($mode);
    }
}
