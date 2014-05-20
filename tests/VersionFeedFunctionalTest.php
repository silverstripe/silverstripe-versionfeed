<?php
class VersionFeedFunctionalTest extends FunctionalTest {

	protected $requiredExtensions = array(
		'Page' => array('VersionFeed'),
		'Page_Controller' => array('VersionFeed_Controller'),
	);
	
	protected $userIP;

	public function setUp() {
		parent::setUp();

		$cache = SS_Cache::factory('VersionFeed_Controller');
		$cache->clean(Zend_Cache::CLEANING_MODE_ALL);
		
		$this->userIP = isset($_SERVER['HTTP_CLIENT_IP']) ? $_SERVER['HTTP_CLIENT_IP'] : null;
		
		Config::nest();
		// Disable caching and locking by default
		Config::inst()->update('VersionFeed\Filters\CachedContentFilter', 'cache_enabled', false);
		Config::inst()->update('VersionFeed\Filters\RateLimitFilter', 'lock_timeout', 0);
		Config::inst()->update('VersionFeed\Filters\RateLimitFilter', 'lock_bypage', false);
		Config::inst()->update('VersionFeed\Filters\RateLimitFilter', 'lock_byuserip', false);
		Config::inst()->update('VersionFeed\Filters\RateLimitFilter', 'lock_cooldown', false);
	}
	
	public function tearDown() {
		Config::unnest();
		
		$_SERVER['HTTP_CLIENT_IP'] = $this->userIP;
		
		parent::tearDown();
	}

	public function testPublicHistory() {
		$page = $this->createPageWithChanges(array('PublicHistory' => false));

		$response = $this->get($page->RelativeLink('changes'));
		$this->assertEquals(404, $response->getStatusCode());

		$response = $this->get($page->RelativeLink('allchanges'));
		$this->assertEquals(200, $response->getStatusCode());
		$xml = simplexml_load_string($response->getBody());
		$this->assertFalse((bool)$xml->channel->item);

		$page = $this->createPageWithChanges(array('PublicHistory' => true));
		
		$response = $this->get($page->RelativeLink('changes'));
		$this->assertEquals(200, $response->getStatusCode());
		$xml = simplexml_load_string($response->getBody());
		$this->assertTrue((bool)$xml->channel->item);

		$response = $this->get($page->RelativeLink('allchanges'));
		$this->assertEquals(200, $response->getStatusCode());
		$xml = simplexml_load_string($response->getBody());
		$this->assertTrue((bool)$xml->channel->item);
	}
	
	public function testRateLimiting() {
		// Re-enable locking just for this test
		Config::inst()->update('VersionFeed\Filters\RateLimitFilter', 'lock_timeout', 20);
		Config::inst()->update('VersionFeed\Filters\CachedContentFilter', 'cache_enabled', true);

		$page1 = $this->createPageWithChanges(array('PublicHistory' => true, 'Title' => 'Page1'));
		$page2 = $this->createPageWithChanges(array('PublicHistory' => true, 'Title' => 'Page2'));
		
		// Artifically set cache lock
		Config::inst()->update('VersionFeed\Filters\RateLimitFilter', 'lock_byuserip', false);
		$cache = SS_Cache::factory('VersionFeed_Controller');
		$cache->setOption('automatic_serialization', true);
		$cache->save(time() + 10, \VersionFeed\Filters\RateLimitFilter::CACHE_PREFIX);
		
		// Test normal hit
		$response = $this->get($page1->RelativeLink('changes'));
		$this->assertEquals(429, $response->getStatusCode());
		$this->assertGreaterThan(0, $response->getHeader('Retry-After'));
		$response = $this->get($page2->RelativeLink('changes'));
		$this->assertEquals(429, $response->getStatusCode());
		$this->assertGreaterThan(0, $response->getHeader('Retry-After'));
		
		// Test page specific lock
		Config::inst()->update('VersionFeed\Filters\RateLimitFilter', 'lock_bypage', true);
		$key = implode('_', array(
			'changes',
			$page1->ID,
			Versioned::get_versionnumber_by_stage('SiteTree', 'Live', $page1->ID, false)
		));
		$key = \VersionFeed\Filters\RateLimitFilter::CACHE_PREFIX . '_' . md5($key);
		$cache->save(time() + 10, $key);
		$response = $this->get($page1->RelativeLink('changes'));
		$this->assertEquals(429, $response->getStatusCode());
		$this->assertGreaterThan(0, $response->getHeader('Retry-After'));
		$response = $this->get($page2->RelativeLink('changes'));
		$this->assertEquals(200, $response->getStatusCode());
		Config::inst()->update('VersionFeed\Filters\RateLimitFilter', 'lock_bypage', false);
		
		// Test rate limit hit by IP
		Config::inst()->update('VersionFeed\Filters\RateLimitFilter', 'lock_byuserip', true);
		$_SERVER['HTTP_CLIENT_IP'] = '127.0.0.1';
		$cache->save(time() + 10, \VersionFeed\Filters\RateLimitFilter::CACHE_PREFIX . '_' . md5('127.0.0.1'));
		$response = $this->get($page1->RelativeLink('changes'));
		$this->assertEquals(429, $response->getStatusCode());
		$this->assertGreaterThan(0, $response->getHeader('Retry-After'));
		
		// Test rate limit doesn't hit other IP
		$_SERVER['HTTP_CLIENT_IP'] = '127.0.0.20';
		$cache->save(time() + 10, \VersionFeed\Filters\RateLimitFilter::CACHE_PREFIX . '_' . md5('127.0.0.1'));
		$response = $this->get($page1->RelativeLink('changes'));
		$this->assertEquals(200, $response->getStatusCode());
		
		// Restore setting
		Config::inst()->update('VersionFeed\Filters\RateLimitFilter', 'lock_byuserip', false);
		Config::inst()->update('VersionFeed\Filters\RateLimitFilter', 'lock_timeout', 0);
		Config::inst()->update('VersionFeed\Filters\CachedContentFilter', 'cache_enabled', false);
	}

	public function testContainsChangesForPageOnly() {
		$page1 = $this->createPageWithChanges(array('Title' => 'Page1'));
		$page2 = $this->createPageWithChanges(array('Title' => 'Page2'));

		$response = $this->get($page1->RelativeLink('changes'));
		$xml = simplexml_load_string($response->getBody());
		$titles = array_map(function($item) {return (string)$item->title;}, $xml->xpath('//item'));
		// TODO Unclear if this should contain the original version
		$this->assertContains('Changed: Page1', $titles);
		$this->assertNotContains('Changed: Page2', $titles);

		$response = $this->get($page2->RelativeLink('changes'));
		$xml = simplexml_load_string($response->getBody());
		$titles = array_map(function($item) {return (string)$item->title;}, $xml->xpath('//item'));
		// TODO Unclear if this should contain the original version
		$this->assertNotContains('Changed: Page1', $titles);
		$this->assertContains('Changed: Page2', $titles);
	}

	public function testContainsAllChangesForAllPages() {
		$page1 = $this->createPageWithChanges(array('Title' => 'Page1'));
		$page2 = $this->createPageWithChanges(array('Title' => 'Page2'));

		$response = $this->get($page1->RelativeLink('allchanges'));
		$xml = simplexml_load_string($response->getBody());
		$titles = array_map(function($item) {return (string)$item->title;}, $xml->xpath('//item'));
		$this->assertContains('Page1', $titles);
		$this->assertContains('Page2', $titles);	
	}

	protected function createPageWithChanges($seed = null) {
		$page = new Page();
		
		$seed = array_merge(array(
			'Title' => 'My Title',
			'Content' => 'My Content'
		), $seed);
		$page->update($seed);
		$page->write();
		$page->publish('Stage', 'Live');

		$page->update(array(
			'Title' => 'Changed: ' . $seed['Title'],
			'Content' => 'Changed: ' . $seed['Content'],
		));
		$page->write();
		$page->publish('Stage', 'Live');

		$page->update(array(
			'Title' => 'Changed again: ' . $seed['Title'],
			'Content' => 'Changed again: ' . $seed['Content'],
		));
		$page->write();
		$page->publish('Stage', 'Live');

		$page->update(array(
			'Title' => 'Unpublished: ' . $seed['Title'],
			'Content' => 'Unpublished: ' . $seed['Content'],
		));
		$page->write();

		return $page;
	}

}