<?php

namespace SilverStripe\VersionFeed\Tests;


use Page;
use SilverStripe\VersionFeed\VersionFeed;
use SilverStripe\VersionFeed\VersionFeedController;
use SilverStripe\Dev\SapphireTest;


class VersionFeedTest extends SapphireTest {

	protected $usesDatabase = true;

	protected $requiredExtensions = array(
		'SiteTree' => array(VersionFeed::class),
		'ContentController' => array(VersionFeedController::class),
	);

	protected $illegalExtensions = array(
		'SiteTree' => array('Translatable')
	);

	public function testDiffedChangesExcludesRestrictedItems() {
		$this->markTestIncomplete();
	}

	public function testDiffedChangesIncludesFullHistory() {
		$this->markTestIncomplete();
	}

	public function testDiffedChangesTitle() {
		$page = new Page(array('Title' => 'My Title'));
		$page->write();
		$page->publish('Stage', 'Live');
	
		$page->Title = 'My Changed Title';
		$page->write();
		$page->publish('Stage', 'Live');

		$page->Title = 'My Unpublished Changed Title';
		$page->write();

		// Strip spaces from test output because they're not reliably maintained by the HTML Tidier
		$cleanDiffOutput = function($val) {
			return str_replace(' ','',strip_tags($val));
		};

		$this->assertContains(
			str_replace(' ' ,'',_t('RSSHistory.TITLECHANGED', 'Title has changed:') . 'My Changed Title'),
			array_map($cleanDiffOutput, $page->getDiffList()->column('DiffTitle')),
			'Detects published title changes'
		);

		$this->assertNotContains(
			str_replace(' ' ,'',_t('RSSHistory.TITLECHANGED', 'Title has changed:') . 'My Unpublished Changed Title'),
			array_map($cleanDiffOutput, $page->getDiffList()->column('DiffTitle')),
			'Ignores unpublished title changes'
		);
	}

}
