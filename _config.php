<?php

SiteTree::add_extension('VersionFeed');
ContentController::add_extension('VersionFeed_Controller');

// Set the cache lifetime to 5 mins.
SS_Cache::set_cache_lifetime('VersionFeed_Controller', 5*60);
