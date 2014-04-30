<?php

namespace VersionFeed\Filters;

/**
 * Conditionally executes a given callback, attempting to return the desired results
 * of its execution.
 */
abstract class ContentFilter {
	
	/**
	 * Nested content filter
	 *
	 * @var ContentFilter
	 */
	protected $nestedContentFilter;
	
	public function __construct($nestedContentFilter = null) {
		$this->nestedContentFilter = $nestedContentFilter;
	}
	
	/**
	 * Gets the cache to use
	 * 
	 * @return Zend_Cache_Frontend
	 */
	protected function getCache() {
		$cache = \SS_Cache::factory('VersionFeed_Controller');
		$cache->setOption('automatic_serialization', true);
		return $cache;
	}
	
	/**
	 * Evaluates the result of the given callback
	 * 
	 * @param string $key Unique key for this
	 * @param callable $callback Callback for evaluating the content
	 * @return mixed Result of $callback()
	 */
	public function getContent($key, $callback) {
		if($this->nestedContentFilter) {
			return $this->nestedContentFilter->getContent($key, $callback);
		} else {
			return call_user_func($callback);
		}
	}
}
