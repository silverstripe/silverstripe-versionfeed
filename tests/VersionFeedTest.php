<?php
class VersionFeedTest extends SapphireTest {

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
		$feed = new VersionFeed();
		$feed->setOwner($page);

		$page->Title = 'My Changed Title';
		$page->write();
		$page->publish('Stage', 'Live');

		$page->Title = 'My Unpublished Changed Title';
		$page->write();

		$this->assertContains(
			_t('RSSHistory.TITLECHANGED', 'Title has changed:') . 'My Changed Title',
			array_map('strip_tags', $feed->getDiffedChanges()->column('DiffTitle')),
			'Detects published title changes'
		);

		$this->assertNotContains(
			_t('RSSHistory.TITLECHANGED', 'Title has changed:') . 'My Unpublished Changed Title',
			array_map('strip_tags', $feed->getDiffedChanges()->column('DiffTitle')),
			'Ignores unpublished title changes'
		);
	}

}