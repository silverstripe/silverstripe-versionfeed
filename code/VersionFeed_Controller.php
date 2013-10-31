<?php

class VersionFeed_Controller extends Extension {

	private static $allowed_actions = array(
		'changes',
		'allchanges'
	);

	function onAfterInit() {
		// RSS feed for per-page changes.
		if ($this->owner->PublicHistory) {
			RSSFeed::linkToFeed($this->owner->Link() . 'changes', 
				sprintf(
					_t('RSSHistory.SINGLEPAGEFEEDTITLE', 'Updates to %s page'),
					$this->owner->Title
				)
			);
		}

		$this->linkToAllSiteRSSFeed();

		return $this;
	}

	/**
	 * Get page-specific changes in a RSS feed.
	 */
	function changes() {
		if(!$this->owner->PublicHistory) throw new SS_HTTPResponse_Exception('Page history not viewable', 404);;

		// Cache the diffs to remove DOS possibility.
		$cache = SS_Cache::factory('VersionFeed_Controller');
		$cache->setOption('automatic_serialization', true);
		$key = implode('_', array('changes', $this->owner->ID, $this->owner->Version));
		$entries = $cache->load($key);
		if(!$entries || isset($_GET['flush'])) {
			$entries = $this->owner->getDiffedChanges();
			$cache->save($entries, $key);
		}

		// Generate the output.
		$title = sprintf(_t('RSSHistory.SINGLEPAGEFEEDTITLE', 'Updates to %s page'), $this->owner->Title);		
		$rss = new RSSFeed($entries, $this->owner->request->getURL(), $title, '', 'Title', '', null);
		$rss->setTemplate('Page_changes_rss');
		return $rss->outputToBrowser();
	}

	/**
	 * Get all changes from the site in a RSS feed.
	 */
	function allchanges() {

		$latestChanges = DB::query('SELECT * FROM "SiteTree_versions" WHERE "WasPublished"=\'1\' AND "CanViewType" IN (\'Anyone\', \'Inherit\') AND "ShowInSearch"=1 AND ("PublicHistory" IS NULL OR "PublicHistory" = \'1\') ORDER BY "LastEdited" DESC LIMIT 20');
		$lastChange = $latestChanges->record();
		$latestChanges->rewind();

		if ($lastChange) {

			// Cache the diffs to remove DOS possibility.
			$member = Member::currentUser();
			$cache = SS_Cache::factory('VersionFeed_Controller');
			$cache->setOption('automatic_serialization', true);
			$key = 'allchanges' . preg_replace('#[^a-zA-Z0-9_]#', '', $lastChange['LastEdited']) .
				($member ? $member->ID : 'public');

			$changeList = $cache->load($key);
			if(!$changeList || isset($_GET['flush'])) {

				$changeList = new ArrayList();

				foreach ($latestChanges as $record) {
					// Check if the page should be visible.
					// WARNING: although we are providing historical details, we check the current configuration.
					$page = SiteTree::get()->filter(array('ID'=>$record['RecordID']))->First();
					if (!$page->canView(new Member())) continue;

					// Get the diff to the previous version.
					$version = new Versioned_Version($record);

					$changes = $version->getDiffedChanges($version->Version, false);
					if ($changes && $changes->Count()) $changeList->push($changes->First());
				}

				$cache->save($changeList, $key);
			}

		} else {
			$changeList = new ArrayList();
		}

		// Produce output
		$rss = new RSSFeed($changeList, $this->owner->request->getURL(), $this->linkToAllSitesRSSFeedTitle(), '', 'Title', '', null);
		$rss->setTemplate('Page_allchanges_rss');
		return $rss->outputToBrowser();
	}

	function linkToAllSiteRSSFeed() {
		// RSS feed to all-site changes.
		$title = Convert::raw2xml($this->linkToAllSitesRSSFeedTitle());
		$url = $this->owner->getSiteRSSLink();

		Requirements::insertHeadTags(
			'<link rel="alternate nofollow" type="application/rss+xml" title="' . $title .
			'" href="' . $url . '" />');
	}

	function linkToAllSitesRSSFeedTitle() {
		return sprintf(_t('RSSHistory.SITEFEEDTITLE', 'Updates to %s'), SiteConfig::current_site_config()->Title);
	}
}
