<?php

namespace SilverStripe\VersionFeed\Tests;

use Page;

use SilverStripe\VersionFeed\VersionFeed;
use SilverStripe\VersionFeed\Filters\CachedContentFilter;
use SilverStripe\VersionFeed\Filters\RateLimitFilter;
use SilverStripe\VersionFeed\VersionFeedController;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Versioned\Versioned;
use SilverStripe\SiteConfig\SiteConfig;
use SilverStripe\Dev\FunctionalTest;
use SilverStripe\Control\Director;
use Psr\SimpleCache\CacheInterface;

class VersionFeedFunctionalTest extends FunctionalTest
{
    protected $usesDatabase = true;
    
    protected $baseURI = 'http://www.fakesite.test';

    protected static $required_extensions = array(
        'Page' => array(VersionFeed::class),
        'PageController' => array(VersionFeedController::class),
    );

    protected $userIP;

    protected function setUp()
    {
        Director::config()->set('alternate_base_url', $this->baseURI);
        
        parent::setUp();

        $cache = Injector::inst()->get(
            CacheInterface::class . '.VersionFeedController'
        );
        $cache->clear();

        $this->userIP = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : null;

        // Enable history by default
        Config::modify()->set(VersionFeed::class, 'changes_enabled', true);
        Config::modify()->set(VersionFeed::class, 'allchanges_enabled', true);

        // Disable caching and locking by default
        Config::modify()->set(CachedContentFilter::class, 'cache_enabled', false);
        Config::modify()->set(RateLimitFilter::class, 'lock_timeout', 0);
        Config::modify()->set(RateLimitFilter::class, 'lock_bypage', false);
        Config::modify()->set(RateLimitFilter::class, 'lock_byuserip', false);
        Config::modify()->set(RateLimitFilter::class, 'lock_cooldown', false);
    }

    public function tearDown()
    {
        Director::config()->set('alternate_base_url', null);
        
        $_SERVER['REMOTE_ADDR'] = $this->userIP;

        parent::tearDown();
    }

    public function testPublicHistory()
    {
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

    public function testRateLimiting()
    {
        // Re-enable locking just for this test
        Config::modify()->set(RateLimitFilter::class, 'lock_timeout', 20);
        Config::modify()->set(CachedContentFilter::class, 'cache_enabled', true);

        $page1 = $this->createPageWithChanges(array('PublicHistory' => true, 'Title' => 'Page1'));
        $page2 = $this->createPageWithChanges(array('PublicHistory' => true, 'Title' => 'Page2'));

        // Artifically set cache lock
        Config::modify()->set(RateLimitFilter::class, 'lock_byuserip', false);
        $cache = Injector::inst()->get(
            CacheInterface::class . '.VersionFeedController'
        );
        $cache->set(RateLimitFilter::CACHE_PREFIX, time() + 10);

        // Test normal hit
        $response = $this->get($page1->RelativeLink('changes'));
        $this->assertEquals(429, $response->getStatusCode());
        $this->assertGreaterThan(0, $response->getHeader('Retry-After'));
        $response = $this->get($page2->RelativeLink('changes'));
        $this->assertEquals(429, $response->getStatusCode());
        $this->assertGreaterThan(0, $response->getHeader('Retry-After'));

        // Test page specific lock
        Config::modify()->set(RateLimitFilter::class, 'lock_bypage', true);
        $key = implode('_', array(
            'changes',
            $page1->ID,
            Versioned::get_versionnumber_by_stage(SiteTree::class, 'Live', $page1->ID, false)
        ));
        $key = RateLimitFilter::CACHE_PREFIX . '_' . md5($key);
        $cache->set($key, time() + 10);
        $response = $this->get($page1->RelativeLink('changes'));
        $this->assertEquals(429, $response->getStatusCode());
        $this->assertGreaterThan(0, $response->getHeader('Retry-After'));
        $response = $this->get($page2->RelativeLink('changes'));
        $this->assertEquals(200, $response->getStatusCode());
        Config::modify()->set(RateLimitFilter::class, 'lock_bypage', false);

        // Test rate limit hit by IP
        Config::modify()->set(RateLimitFilter::class, 'lock_byuserip', true);
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $cache->set(RateLimitFilter::CACHE_PREFIX . '_' . md5('127.0.0.1'), time() + 10);
        $response = $this->get($page1->RelativeLink('changes'));
        $this->assertEquals(429, $response->getStatusCode());
        $this->assertGreaterThan(0, $response->getHeader('Retry-After'));

        // Test rate limit doesn't hit other IP
        $_SERVER['REMOTE_ADDR'] = '127.0.0.20';
        $cache->set(RateLimitFilter::CACHE_PREFIX . '_' . md5('127.0.0.1'), time() + 10);
        $response = $this->get($page1->RelativeLink('changes'));
        $this->assertEquals(200, $response->getStatusCode());

        // Restore setting
        Config::modify()->set(RateLimitFilter::class, 'lock_byuserip', false);
        Config::modify()->set(RateLimitFilter::class, 'lock_timeout', 0);
        Config::modify()->set(CachedContentFilter::class, 'cache_enabled', false);
    }

    public function testContainsChangesForPageOnly()
    {
        $page1 = $this->createPageWithChanges(array('Title' => 'Page1'));
        $page2 = $this->createPageWithChanges(array('Title' => 'Page2'));

        $response = $this->get($page1->RelativeLink('changes'));
        $xml = simplexml_load_string($response->getBody());
        $titles = array_map(function ($item) {
            return (string)$item->title;
        }, $xml->xpath('//item'));
        // TODO Unclear if this should contain the original version
        $this->assertContains('Changed: Page1', $titles);
        $this->assertNotContains('Changed: Page2', $titles);

        $response = $this->get($page2->RelativeLink('changes'));
        $xml = simplexml_load_string($response->getBody());
        $titles = array_map(function ($item) {
            return (string)$item->title;
        }, $xml->xpath('//item'));
        // TODO Unclear if this should contain the original version
        $this->assertNotContains('Changed: Page1', $titles);
        $this->assertContains('Changed: Page2', $titles);
    }

    public function testContainsAllChangesForAllPages()
    {
        $page1 = $this->createPageWithChanges(array('Title' => 'Page1'));
        $page2 = $this->createPageWithChanges(array('Title' => 'Page2'));

        $response = $this->get($page1->RelativeLink('allchanges'));
        $xml = simplexml_load_string($response->getBody());
        $titles = array_map(function ($item) {
            return (string)$item->title;
        }, $xml->xpath('//item'));
        $this->assertContains('Page1', $titles);
        $this->assertContains('Page2', $titles);
    }

    protected function createPageWithChanges($seed = null)
    {
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

    /**
     * Tests response code for globally disabled feedss
     */
    public function testFeedViewability()
    {

        // Nested loop through each configuration
        foreach (array(true, false) as $publicHistory_Page) {
            $page = $this->createPageWithChanges(array('PublicHistory' => $publicHistory_Page, 'Title' => 'Page'));

            // Test requests to 'changes' action
            foreach (array(true, false) as $publicHistory_Config) {
                Config::modify()->set(VersionFeed::class, 'changes_enabled', $publicHistory_Config);
                $expectedResponse = $publicHistory_Page && $publicHistory_Config ? 200 : 404;
                $response = $this->get($page->RelativeLink('changes'));
                $this->assertEquals($expectedResponse, $response->getStatusCode());
            }

            // Test requests to 'allchanges' action on each page
            foreach (array(true, false) as $allChanges_Config) {
                foreach (array(true, false) as $allChanges_SiteConfig) {
                    Config::modify()->set(VersionFeed::class, 'allchanges_enabled', $allChanges_Config);
                    $siteConfig = SiteConfig::current_site_config();
                    $siteConfig->AllChangesEnabled = $allChanges_SiteConfig;
                    $siteConfig->write();

                    $expectedResponse = $allChanges_Config && $allChanges_SiteConfig ? 200 : 404;
                    $response = $this->get($page->RelativeLink('allchanges'));
                    $this->assertEquals($expectedResponse, $response->getStatusCode());
                }
            }
        }
    }
}
