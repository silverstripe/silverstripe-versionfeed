<?php
class VersionFeedFunctionalTest extends FunctionalTest {

	protected $requiredExtensions = array(
		'Page' => array('VersionFeed'),
		'Page_Controller' => array('VersionFeed_Controller'),
	);

	public function setUp() {
		parent::setUp();

		$cache = SS_Cache::factory('VersionFeed_Controller');
		$cache->clean(Zend_Cache::CLEANING_MODE_ALL);
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