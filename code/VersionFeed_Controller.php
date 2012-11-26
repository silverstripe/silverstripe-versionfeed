<?php

class VersionFeed_Controller extends Extension {

	static $allowed_actions = array(
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

		// Generate the output.
		$title = sprintf(_t('RSSHistory.SINGLEPAGEFEEDTITLE', 'Updates to %s page'), $this->owner->Title);		
		$rss = new RSSFeed($this->owner->getDiffedChanges(), $this->owner->request->getURL(), $title, '', 'Title', '', null);
		$rss->setTemplate('Page_changes_rss');
		return $rss->outputToBrowser();
	}

	/**
	 * Get all changes from the site in a RSS feed.
	 */
	function allchanges() {
		// Fetch the latest changes on the entire site.
		$latestChanges = DB::query('SELECT * FROM "SiteTree_versions" WHERE "WasPublished"=\'1\' AND "CanViewType" IN (\'Anyone\', \'Inherit\') AND "ShowInSearch"=1 AND ("PublicHistory" IS NULL OR "PublicHistory" = \'1\') ORDER BY "LastEdited" DESC LIMIT 20');

		$changeList = new ArrayList();
		foreach ($latestChanges as $record) {
			// Get the diff to the previous version.
			$version = new Versioned_Version($record);
			$changes = $version->getDiffedChanges($version->Version, false);
			if ($changes && $changes->Count()) $changeList->push($changes->First());
		}

		// Produce output
		$rss = new RSSFeed($changeList, $this->owner->request->getURL(), $this->linkToAllSitesRSSFeedTitle(), '', 'Title', '', null);
		$rss->setTemplate('Page_allchanges_rss');
		return $rss->outputToBrowser();
	}

	function linkToAllSiteRSSFeed() {
		// RSS feed to all-site changes.
		RSSFeed::linkToFeed($this->owner->getSiteRSSLink(), $this->linkToAllSitesRSSFeedTitle());
	}

	function linkToAllSitesRSSFeedTitle() {
		return sprintf(_t('RSSHistory.SITEFEEDTITLE', 'Updates to %s'), SiteConfig::current_site_config()->Title);
	}
}