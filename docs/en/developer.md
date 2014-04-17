# Version Feed

## Development

### Feed actions

Creating functions called `changes` or `allchanges` on any of your page types or controllers will cause confusion with
the extensions defined on the extension.

### Default RSS action

Templates can offer a "Subscribe" link with a link to the most relevant RSS feed. This will default to the changes feed
for the current page. You can override this behaviour by defining the `getDefaultRSSLink` function in your page type
and returning the URL of your desired RSS feed:

	:::php
	class MyPage extends Page {
		function getDefaultRSSLink() {
			return $this->Link('myrssfeed');
		}
	}

This can be used in templates as `$DefaultRSSLink`.

### Rate limiting and caching

By default all content is filtered based on the rules specified in `versionfeed/_config/versionfeed.yml`.
Two filters are applied on top of one another:

 * `CachedContentFilter` provides caching of versions based on an identifier built up of the record ID and the 
   most recently saved version number. There is no configuration required for this class.
 * `RateLimitFilter` provides rate limiting to ensure that requests to uncached data does not overload the 
   server. This filter will only be applied if the `CachedContentFilter` does not have any cached record
  for a request.

Either one of these can be replaced, added to, or removed, by adjusting the `VersionFeed_Controller.dependencies`
config to point to a replacement (or no) filter.

For large servers with a high level of traffic (more than 1 visits every 10 seconds) the default settings should
be sufficient.

For smaller servers where it's reasonable to apply a more strict approach to rate limiting, consider setting the
`RateLimitFilter.lock_bypage` config to false, meaning that a single limit will be applied to all URLs.
If left on true, then each URL will have its own rate limit, and on smaller servers with lots of
concurrent requests this can still overwhelm capacity. This will also leave smaller servers vulnerable to DDoS
attacks which target many URLs simultaneously. This config will have no effect on the `allchanges` method.

`RateLimitFilter.lock_byuserip` can also be set to true in order to prevent requests from different users
interfering with one another. However, this can provide an ineffective safeguard against malicious DDoS attacks
which use multiple IP addresses.

Another important variable is the `RateLimitFilter.lock_timeout` config, which is set to 10 seconds by default.
This should be increased on sites which may be slow to generate page versions, whether due to lower
server capacity or volume of content (number of page versions). Requests to this page after the timeout
will not trigger any rate limit safeguard, so you should be sure that this is set to an appropriate level.
