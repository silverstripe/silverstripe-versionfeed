<?php

namespace SilverStripe\VersionFeed\Tests;

use Page;
use SilverStripe\CMS\Controllers\ContentController;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Versioned\Versioned;
use SilverStripe\VersionFeed\VersionFeed;
use SilverStripe\VersionFeed\VersionFeedController;

class VersionFeedTest extends SapphireTest
{
    protected $usesDatabase = true;

    protected static $required_extensions = [
        SiteTree::class => [VersionFeed::class],
        ContentController::class => [VersionFeedController::class],
    ];

    public function testDiffedChangesExcludesRestrictedItems()
    {
        $this->markTestIncomplete();
    }

    public function testDiffedChangesIncludesFullHistory()
    {
        $this->markTestIncomplete();
    }

    public function testDiffedChangesTitle()
    {
        $page = new Page(['Title' => 'My Title']);
        $page->write();
        $page->copyVersionToStage(Versioned::DRAFT, Versioned::LIVE);
    
        $page->Title = 'My Changed Title';
        $page->write();
        $page->copyVersionToStage(Versioned::DRAFT, Versioned::LIVE);

        $page->Title = 'My Unpublished Changed Title';
        $page->write();

        // Strip spaces from test output because they're not reliably maintained by the HTML Tidier
        $cleanDiffOutput = function ($val) {
            return str_replace(' ', '', strip_tags($val ?? ''));
        };

        $this->assertContains(
            str_replace(' ', '', _t('RSSHistory.TITLECHANGED', 'Title has changed:') . 'My Changed Title'),
            array_map($cleanDiffOutput, $page->getDiffList()->column('DiffTitle') ?? []),
            'Detects published title changes'
        );

        $this->assertNotContains(
            str_replace(' ', '', _t('RSSHistory.TITLECHANGED', 'Title has changed:') . 'My Unpublished Changed Title'),
            array_map($cleanDiffOutput, $page->getDiffList()->column('DiffTitle') ?? []),
            'Ignores unpublished title changes'
        );
    }
}
