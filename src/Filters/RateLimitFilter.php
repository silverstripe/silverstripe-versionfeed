<?php

namespace SilverStripe\VersionFeed\Filters;

use SilverStripe\Core\Config\Config;
use SilverStripe\Control\Controller;
use SilverStripe\Control\HTTPResponse_Exception;
use SilverStripe\Versioned\Versioned;

/**
 * Provides rate limiting of execution of a callback
 */
class RateLimitFilter extends ContentFilter
{

    /**
     * Time duration (in second) to allow for generation of cached results. Requests to
     * pages that within this time period that do not hit the cache (and would otherwise trigger
     * a version query) will be presented with a 429 (rate limit) HTTP error
     *
     * @config
     * @var int
     */
    private static $lock_timeout = 5;

    /**
     * Determine if the cache generation should be locked on a per-page basis. If true, concurrent page versions
     * may be generated without rate interference.
     *
     * @config
     * @var bool
     */
    private static $lock_bypage = false;

    /**
     * Determine if rate limiting should be applied independently to each IP address. This method is not
     * reliable, as most DDoS attacks use multiple IP addresses.
     *
     * @config
     * @var bool
     */
    private static $lock_byuserip = false;

    /**
     * Time duration (in sections) to deny further search requests after a successful search.
     * Search requests within this time period while another query is in progress will be
     * presented with a 429 (rate limit)
     *
     * @config
     * @var int
     */
    private static $lock_cooldown = 2;

    /**
     * Cache key prefix
     */
    const CACHE_PREFIX = 'RateLimitBegin';

    /**
     * Determines the key to use for saving the current rate
     *
     * @param string $itemkey Input key
     * @return string Result key
     */
    protected function getCacheKey($itemkey)
    {
        $key = self::CACHE_PREFIX;

        // Add global identifier
        if ($this->config()->get('lock_bypage')) {
            $key .= '_' . md5($itemkey);
        }

        // Add user-specific identifier
        if ($this->config()->get('lock_byuserip') && Controller::has_curr()) {
            $ip = Controller::curr()->getRequest()->getIP();
            $key .= '_' . md5($ip);
        }

        return $key;
    }


    public function getContent($key, $callback)
    {
        // Bypass rate limiting if flushing, or timeout isn't set
        $timeout = $this->config()->get('lock_timeout');
        if (isset($_GET['flush']) || !$timeout) {
            return parent::getContent($key, $callback);
        }

        // Generate result with rate limiting enabled
        $limitKey = $this->getCacheKey($key);
        $cache = $this->getCache();
        if ($lockedUntil = $cache->get($limitKey)) {
            if (time() < $lockedUntil) {
                // Politely inform visitor of limit
                $response = new HTTPResponse_Exception('Too Many Requests.', 429);
                $response->getResponse()->addHeader('Retry-After', 1 + $lockedUntil - time());
                throw $response;
            }
        }

        $lifetime = Config::inst()->get(ContentFilter::class, 'cache_lifetime') ?: null;

        // Apply rate limit
        $cache->set($limitKey, time() + $timeout, $lifetime);

        // Generate results
        $result = parent::getContent($key, $callback);

        // Reset rate limit with optional cooldown
        if ($cooldown = $this->config()->get('lock_cooldown')) {
            // Set cooldown on successful query execution
            $cache->set($limitKey, time() + $cooldown, $lifetime);
        } else {
            // Without cooldown simply disable lock
            $cache->delete($limitKey);
        }
        return $result;
    }
}
