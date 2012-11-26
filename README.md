# Version Feed

## Overview

The module creates an RSS feed on each page with their change history, as well as one for the entire site.

## Requirements

 * SilverStripe 3.0+

## Installation

Install with composer by running:

	composer require silverstripe/versionfeed:*

in the root of your SilverStripe project.

Or just clone/download the git repository into a subfolder (usually called "versionfeed") of your SilverStripe project.

## Usage

### Accessing RSS feeds

The extensions will automatically add links to the RSS feeds, accessible by the actions 'changes' and 'allchanges'. You will encounter problems if you have functions called the same name on any controller.

### Enabling / disabling

You can enable or disable the feed on a per-page basis by interacting with the checkbox on the Settings tab of each page.