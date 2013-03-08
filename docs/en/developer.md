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
