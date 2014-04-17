<?php

/**
 * Caches results of a callback
 */
class CachedContentFilter extends ContentFilter {
	
	public function getContent($key, $callback) {
		$cache = $this->getCache();
		
		// Return cached value if available
		$result = isset($_GET['flush'])
			? null
			: $cache->load($key);
		if($result) return $result;
		
		// Fallback to generate result
		$result = parent::getContent($key, $callback);
		$cache->save($result, $key);
		return $result;
	}
}
