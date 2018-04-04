title: Content change RSS
summary: Adds page or site wide RSS feeds that display content changes

# Content change RSS

## In this section:

* Accessing RSS feeds
* Enabling and disabling via the CMS

## Before we begin

Make sure that your SilverStripe installation has the [versionfeed](http://addons.silverstripe.org/add-ons/silverstripe/versionfeed) module installed.

## Accessing RSS feeds

There are two feeds that are automatically created for each page:

* Page changes: This feed will display all published versions of the page, highlighting any additions or deletions with underscores or strikethroughs respectively. It is accessible with the `changes` action - so `http://mysite.com/mypage/changes`
* Site changes: This will aggregate all the per-page change feeds into one feed and display the most recent 20. It is accessible from any page with the `allchanges` action - so `http://mysite.com/home/allchanges`

## Enabling / disabling

You can enable or disable the feed on a per-page basis by checking or unchecking the *Make history public* checkbox (if available) in the Settings tab of each page. If a page has the Make history public option unchecked, it will not appear in the allchanges feed.

The allchanges feed can also be disabled by unchecking the "All page changes" checkbox in the "Settings" section in the cms.

### Privacy

A page's history will be completely visible when it has public history enabled, even if some updates were made when it was restricted to only being viewed by authenticated users. So if a page has ever had confidential data on it, it is best to not enable this feature unless the data has entered the public domain.

There is a warning explaining this fact next to the *Make history public* checkbox.
