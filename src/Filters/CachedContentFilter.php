<?php

namespace SilverStripe\VersionFeed\Filters;

use SilverStripe\Core\Config\Config;

/**
 * Caches results of a callback
 */
class CachedContentFilter extends ContentFilter
{
    
    /**
     * Enable caching
     *
     * @config
     * @var boolean
     */
    private static $cache_enabled = true;
    
    public function getContent($key, $callback)
    {
        $cache = $this->getCache();
        
        // Return cached value if available
        $cacheEnabled = Config::inst()->get(get_class(), 'cache_enabled');
        $result = (isset($_GET['flush']) || !$cacheEnabled)
            ? null
            : $cache->get($key);
        if ($result) {
            return $result;
        }
        
        // Fallback to generate result
        $result = parent::getContent($key, $callback);
        $lifetime = Config::inst()->get(ContentFilter::class, 'cache_lifetime') ?: null;
        $cache->set($key, $result, $lifetime);
        return $result;
    }
}
