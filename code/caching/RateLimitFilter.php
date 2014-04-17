<?php

/**
 * Provides rate limiting of execution of a callback
 */
class RateLimitFilter extends ContentFilter {
	
	/**
	 * Time duration (in second) to allow for generation of cached results. Requests to 
	 * pages that within this time period that do not hit the cache (and would otherwise trigger
	 * a version query) will be presented with a 429 (rate limit) HTTP error
	 *
	 * @config
	 * @var int
	 */
	private static $lock_timeout = 10;
	
	/**
	 * Determine if the cache generation should be locked on a per-page basis. If true, concurrent page versions
	 * may be generated without rate interference.
	 * 
	 * Suggested to turn this to false on small sites that will not have many concurrent views of page versions
	 *
	 * @config
	 * @var bool
	 */
	private static $lock_bypage = true;
	
	/**
	 * Determine if rate limiting should be applied independently to each IP address. This method is not
	 * reliable, as most DDoS attacks use multiple IP addresses.
	 *
	 * @config
	 * @var bool
	 */
	private static $lock_byuserip = false;
	
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
	protected function getCacheKey($itemkey) {
		$key = self::CACHE_PREFIX;
		
		// Add global identifier
		if(Config::inst()->get(get_class(), 'lock_bypage'))  {
			$key .= '_' . md5($itemkey);
		}
		
		// Add user-specific identifier
		if(Config::inst()->get(get_class(), 'lock_byuserip') && Controller::has_curr()) {
			$ip = Controller::curr()->getRequest()->getIP();
			$key .= '_' . md5($ip);
		}
		
		return $key;
	}


	public function getContent($key, $callback) {
		// Bypass rate limiting if flushing, or timeout isn't set
		$timeout = Config::inst()->get(get_class(), 'lock_timeout');
		if(isset($_GET['flush']) || !$timeout) {
			return parent::getContent($key, $callback);
		}
		
		// Generate result with rate limiting enabled
		$limitKey = $this->getCacheKey($key);
		$cache = $this->getCache();
		if($cacheBegin = $cache->load($limitKey)) {
			if(time() - $cacheBegin < $timeout) {
				// Politely inform visitor of limit
				$response = new SS_HTTPResponse_Exception('Too Many Requests.', 429);
				$response->getResponse()->addHeader('Retry-After', 1 + time() - $cacheBegin);
				throw $response;
			}
		}
		
		// Generate result with rate limit locked
		$cache->save(time(), $limitKey);
		$result = parent::getContent($key, $callback);
		$cache->remove($limitKey);
		return $result;
	}
}
