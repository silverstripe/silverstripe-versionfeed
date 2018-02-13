<?php

namespace SilverStripe\VersionFeed\Filters;

use SilverStripe\VersionFeed\VersionFeedController;
use SilverStripe\Core\Config\Configurable;
use Psr\SimpleCache\CacheInterface;
use SilverStripe\Core\Injector\Injector;

/**
 * Conditionally executes a given callback, attempting to return the desired results
 * of its execution.
 */
abstract class ContentFilter
{

    use Configurable;
    
    /**
     * Nested content filter
     *
     * @var ContentFilter
     */
    protected $nestedContentFilter;

    /**
     * Cache lifetime
     *
     * @config
     * @var int
     */
    private static $cache_lifetime = 300;
    
    public function __construct($nestedContentFilter = null)
    {
        $this->nestedContentFilter = $nestedContentFilter;
    }
    
    /**
     * Gets the cache to use
     *
     * @return CacheInterface
     */
    protected function getCache()
    {
        return Injector::inst()->get(
            CacheInterface::class . '.VersionFeedController'
        );
    }
    
    /**
     * Evaluates the result of the given callback
     *
     * @param string $key Unique key for this
     * @param callable $callback Callback for evaluating the content
     * @return mixed Result of $callback()
     */
    public function getContent($key, $callback)
    {
        if ($this->nestedContentFilter) {
            return $this->nestedContentFilter->getContent($key, $callback);
        } else {
            return call_user_func($callback);
        }
    }
}
