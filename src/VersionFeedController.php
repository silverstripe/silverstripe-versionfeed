<?php

namespace SilverStripe\VersionFeed;

use SilverStripe\Core\Config\Config;
use SilverStripe\Control\RSS\RSSFeed;
use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Security;
use SilverStripe\SiteConfig\SiteConfig;
use SilverStripe\ORM\DB;
use SilverStripe\Security\Member;
use SilverStripe\ORM\ArrayList;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Core\Convert;
use SilverStripe\View\Requirements;
use SilverStripe\Core\Extension;
use SilverStripe\VersionFeed\Filters\ContentFilter;

class VersionFeedController extends Extension
{
    private static $allowed_actions = array(
        'changes',
        'allchanges'
    );
    
    /**
     * Content handler
     *
     * @var ContentFilter
     */
    protected $contentFilter;
    
    /**
     * Sets the content filter
     *
     * @param ContentFilter $contentFilter
     */
    public function setContentFilter(ContentFilter $contentFilter)
    {
        $this->contentFilter = $contentFilter;
    }
    
    /**
     * Evaluates the result of the given callback
     *
     * @param string $key Unique key for this
     * @param callable $callback Callback for evaluating the content
     * @return mixed Result of $callback()
     */
    protected function filterContent($key, $callback)
    {
        if ($this->contentFilter) {
            return $this->contentFilter->getContent($key, $callback);
        } else {
            return call_user_func($callback);
        }
    }

    public function onAfterInit()
    {
        $this->linkToPageRSSFeed();
        $this->linkToAllSiteRSSFeed();
    }

    /**
     * Get page-specific changes in a RSS feed.
     */
    public function changes()
    {
        // Check viewability of changes
        if (!Config::inst()->get(VersionFeed::class, 'changes_enabled')
            || !$this->owner->PublicHistory
            || $this->owner->Version == ''
        ) {
            return $this->owner->httpError(404, 'Page history not viewable');
        }

        // Cache the diffs to remove DOS possibility.
        $target = $this->owner;
        $key = implode('_', array('changes', $target->ID, $target->Version));
        $entries = $this->filterContent($key, function () use ($target) {
            return $target->getDiffList(null, Config::inst()->get(VersionFeed::class, 'changes_limit'));
        });

        // Generate the output.
        $title = _t(
            'SilverStripe\\VersionFeed\\VersionFeed.SINGLEPAGEFEEDTITLE',
            'Updates to {title} page',
            ['title' => $this->owner->Title]
        );
        $rss = new RSSFeed($entries, $this->owner->request->getURL(), $title, '', 'Title', '', null);
        $rss->setTemplate('Page_changes_rss');
        return $rss->outputToBrowser();
    }

    /**
     * Get all changes from the site in a RSS feed.
     */
    public function allchanges()
    {
        // Check viewability of allchanges
        if (!Config::inst()->get(VersionFeed::class, 'allchanges_enabled')
            || !SiteConfig::current_site_config()->AllChangesEnabled
        ) {
            return $this->owner->httpError(404, 'Global history not viewable');
        }

        $limit = (int)Config::inst()->get(VersionFeed::class, 'allchanges_limit');
        $latestChanges = DB::query('
			SELECT * FROM "SiteTree_Versions"
			WHERE "WasPublished" = \'1\'
			AND "CanViewType" IN (\'Anyone\', \'Inherit\')
			AND "ShowInSearch" = 1
			AND ("PublicHistory" IS NULL OR "PublicHistory" = \'1\')
			ORDER BY "LastEdited" DESC LIMIT ' . $limit);
        $lastChange = $latestChanges->record();
        $latestChanges->rewind();

        if ($lastChange) {
            // Cache the diffs to remove DOS possibility.
            $key = 'allchanges'
                . preg_replace('#[^a-zA-Z0-9_]#', '', $lastChange['LastEdited'])
                . (Security::getCurrentUser() ? Security::getCurrentUser()->ID : 'public');
            $changeList = $this->filterContent($key, function () use ($latestChanges) {
                $changeList = new ArrayList();
                $canView = array();
                foreach ($latestChanges as $record) {
                    // Check if the page should be visible.
                    // WARNING: although we are providing historical details, we check the current configuration.
                    $id = $record['RecordID'];
                    if (!isset($canView[$id])) {
                        $page = DataObject::get_by_id(SiteTree::class, $id);
                        $canView[$id] = $page && $page->canView(Security::getCurrentUser());
                    }
                    if (!$canView[$id]) {
                        continue;
                    }

                    // Get the diff to the previous version.
                    $record['ID'] = $record['RecordID'];
                    $version = SiteTree::create($record);
                    if ($diff = $version->getDiff()) {
                        $changeList->push($diff);
                    }
                }

                return $changeList;
            });
        } else {
            $changeList = new ArrayList();
        }

        // Produce output
        $url = $this->owner->getRequest()->getURL();
        $rss = new RSSFeed(
            $changeList,
            $url,
            $this->linkToAllSitesRSSFeedTitle(),
            '',
            'Title',
            '',
            null
        );
        $rss->setTemplate('Page_allchanges_rss');
        return $rss->outputToBrowser();
    }
    
    /**
     * Generates and embeds the RSS header link for the page-specific version rss feed
     */
    public function linkToPageRSSFeed()
    {
        if (!Config::inst()->get(VersionFeed::class, 'changes_enabled') || !$this->owner->PublicHistory) {
            return;
        }
        
        RSSFeed::linkToFeed(
            $this->owner->Link('changes'),
            _t(
                'SilverStripe\\VersionFeed\\VersionFeed.SINGLEPAGEFEEDTITLE',
                'Updates to {title} page',
                ['title' => $this->owner->Title]
            )
        );
    }

    /**
     * Generates and embeds the RSS header link for the global version rss feed
     */
    public function linkToAllSiteRSSFeed()
    {
        if (!Config::inst()->get(VersionFeed::class, 'allchanges_enabled')
            || !SiteConfig::current_site_config()->AllChangesEnabled
        ) {
            return;
        }
        
        // RSS feed to all-site changes.
        $title = Convert::raw2xml($this->linkToAllSitesRSSFeedTitle());
        $url = $this->owner->getSiteRSSLink();

        Requirements::insertHeadTags(
            '<link rel="alternate" type="application/rss+xml" title="' . $title .
            '" href="' . $url . '" />'
        );
    }

    public function linkToAllSitesRSSFeedTitle()
    {
        return _t(
            'SilverStripe\\VersionFeed\\VersionFeed.SITEFEEDTITLE',
            'Updates to {title}',
            ['title' => SiteConfig::current_site_config()->Title]
        );
    }
}
