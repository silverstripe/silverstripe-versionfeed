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

If the [Policy Filter Module](http://github.com/silverstripe/silverstripe-policyfilter) has been installed then 
additional rate limiting and caching functionality will be applied automatically. For more information on
how this modules works see the policy filter documentation.

By default all content is filtered based on the rules specified in `versionfeed/_config/versionfeed.yml`.

For large servers with a high level of traffic (more than 1 visits every 10 seconds) the default settings should
be sufficient.

For smaller servers where it's reasonable to apply a more strict approach to rate limiting, consider setting the
`ContentController.filter_policy.rate_lock_byitem` config to false, meaning that a single limit will be
applied to all URLs. If left on true, then each URL will have its own rate limit, and on smaller servers with lots of
concurrent requests this can still overwhelm capacity. This will also leave smaller servers vulnerable to DDoS
attacks which target many URLs simultaneously. This config will have no effect on the `allchanges` method.

`ContentController.filter_policy.rate_lock_byuserip` can also be set to true in order to prevent requests
from different users interfering with one another. However, this can provide an ineffective safeguard
against malicious DDoS attacks which use multiple IP addresses.

Another important variable is the `ContentController.filter_policy.rate_lock_timeout` config, which is set to 10
seconds by default. This should be increased on sites which may be slow to generate page versions, whether
due to lower server capacity or volume of content (number of page versions). Requests to this page after
the timeout will not trigger any rate limit safeguard, so you should be sure that this is set to an appropriate level.

The default cache of 1 hour can also be adjusted by setting the `ContentController.filter_policy.cache_lifetime`
to any time period in seconds.
