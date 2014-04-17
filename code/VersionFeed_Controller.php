<?php

class VersionFeed_Controller extends Extension {

	private static $allowed_actions = array(
		'changes',
		'allchanges'
	);
	
	/**
	 * Evaluates the result of the given callback
	 * 
	 * @param string $key Unique key for this
	 * @param callable $callback Callback for evaluating the content
	 * @return mixed Result of $callback()
	 */
	protected function getContent($key, $callback) {
		$result = $this->owner->extend('filterContent', $key, $callback);
		return reset($result) ?: call_user_func($callback);
	}

	public function onAfterInit() {
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
	public function changes() {
		if(!$this->owner->PublicHistory) throw new SS_HTTPResponse_Exception('Page history not viewable', 404);

		// Cache the diffs to remove DOS possibility.
		$target = $this->owner;
		$key = implode('_', array('changes', $this->owner->ID, $this->owner->Version));
		$entries = $this->getContent($key, function() use ($target) {
			return $target->getDiffedChanges();
		});

		// Generate the output.
		$title = sprintf(_t('RSSHistory.SINGLEPAGEFEEDTITLE', 'Updates to %s page'), $this->owner->Title);		
		$rss = new RSSFeed($entries, $this->owner->request->getURL(), $title, '', 'Title', '', null);
		$rss->setTemplate('Page_changes_rss');
		return $rss->outputToBrowser();
	}

	/**
	 * Get all changes from the site in a RSS feed.
	 */
	public function allchanges() {

		$latestChanges = DB::query('
			SELECT * FROM "SiteTree_versions"
			WHERE "WasPublished" = \'1\'
			AND "CanViewType" IN (\'Anyone\', \'Inherit\')
			AND "ShowInSearch" = 1
			AND ("PublicHistory" IS NULL OR "PublicHistory" = \'1\')
			ORDER BY "LastEdited" DESC LIMIT 20'
		);
		$lastChange = $latestChanges->record();
		$latestChanges->rewind();

		if ($lastChange) {

			// Cache the diffs to remove DOS possibility.
			$key = 'allchanges'
				. preg_replace('#[^a-zA-Z0-9_]#', '', $lastChange['LastEdited'])
				. (Member::currentUserID() ?: 'public');
			$changeList = $this->getContent($key, function() use ($latestChanges) {
				$changeList = new ArrayList();
				$canView = array();
				foreach ($latestChanges as $record) {
					
					// Check if the page should be visible.
					// WARNING: although we are providing historical details, we check the current configuration.
					$id = $record['RecordID'];
					if(!isset($canView[$id])) {
						$page = SiteTree::get()->byID($id);
						$canView[$id] = $page && $page->canView(new Member());
					}
					if (!$canView[$id]) continue;

					// Get the diff to the previous version.
					$version = new Versioned_Version($record);
					$changes = $version->getDiffedChanges($version->Version, false);
					if ($changes && $changes->Count()) $changeList->push($changes->First());
				}

				return $changeList;
			});
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
