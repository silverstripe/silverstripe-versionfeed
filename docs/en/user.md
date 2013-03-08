# Version Feed

## Usage

### Accessing RSS feeds

There are two feeds that are automatically created for each page:

 - Page changes: This feed will display all published versions of the page, highlighting any additions or deletions
 with underscores or strikethroughs. It is accessible with the `changes` action - so `http://mysite.com/mypage/changes`
 - Site changes: This will aggregate all the per-page change feeds into one feed and display the most recent 20. It is
 accessible from any page with the `allchanges` action - so `http://mysite.com/home/allchanges`

### Enabling / disabling

You can enable or disable the feed on a per-page basis by checking or unchecking the *Public History* checkbox in the
Settings tab of each page. If a page has the Public History option, unchecked, it will not appear in the allchanges
feed.

#### Privacy

A page's history will be completely visible when it has public history enabled, even if some updates were made when it
was restricted to only being viewed by authenticated users. So if a page has ever had confidential data on it, it is
best to not enable this feature unless the data has entered the public domain.

There is a warning explaining this fact next to the *Public History* checkbox.
