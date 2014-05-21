<?php

class VersionFeed extends SiteTreeExtension {
	
	private static $db = array(
		'PublicHistory' => 'Boolean(true)'
	);

	private static $defaults = array(
		'PublicHistory' => true
	);

	public function updateFieldLabels(&$labels) {
		$labels['PublicHistory'] = _t('RSSHistory.LABEL', 'Make history public');
	}
	
	/**
	 * Enable the allchanges feed
	 *
	 * @config
	 * @var bool
	 */
	private static $allchanges_enabled = true;
	
	/**
	 * Enables RSS feed for page-specific changes
	 * 
	 * @config
	 * @var bool
	 */
	private static $changes_enabled = true;

	/**
	 * Compile a list of changes to the current page, excluding non-published and explicitly secured versions.
	 *
	 * @param int $highestVersion Top version number to consider.
	 * @param boolean $fullHistory Whether to get the full change history or just the previous version.
	 *
	 * @returns ArrayList List of cleaned records.
	 */
	public function getDiffedChanges($highestVersion = null, $fullHistory = true) {
		// This can leak secured content if it was protected via inherited setting.
		// For now the users will need to be aware about this shortcoming.
		$offset = $highestVersion ? "AND \"SiteTree_versions\".\"Version\"<='".(int)$highestVersion."'" : '';
		$limit = $fullHistory ? null : 2;
		$versions = $this->owner->allVersions("\"WasPublished\"='1' AND \"CanViewType\" IN ('Anyone', 'Inherit') $offset", "\"LastEdited\" DESC", $limit);

		// Process the list to add the comparisons.
		$changeList = new ArrayList();
		$previous = null;
		$count = 0;
		foreach ($versions as $version) {
			$changed = false;

			if (isset($previous)) {
				// We have something to compare with.
				$diff = $this->owner->compareVersions($version->Version, $previous->Version);

				// Produce the diff fields for use in the template.
				if ($version->Title != $previous->Title) {
					$version->DiffTitle = new HTMLText();
					$version->DiffTitle->setValue(
						sprintf(
							'<div><em>%s</em>' . $diff->Title . '</div>',
							_t('RSSHistory.TITLECHANGED', 'Title has changed:')
						)
					);
					$changed = true;
				}
				if ($version->Content != $previous->Content) {
					$version->DiffContent = new HTMLText();
					$version->DiffContent->setValue('<div>'.$diff->obj('Content')->forTemplate().'</div>');
					$changed = true;
				}

				// Copy the link so it can be cached by SS_Cache.
				$version->GeneratedLink = $version->AbsoluteLink();
			}

			// Omit the versions that haven't been visibly changed (only takes the above fields into consideration).
			if ($changed) {
				$changeList->push($version);
				$count++;
			}

			// Store the last version for comparison.
			$previous = $version;
		}

		// Push the first version on to the list - only if we're looking at the full history or if it's the first
		// version in the version history.
		if ($previous && ($fullHistory || $versions->count() == 1)) {
			$first = clone($previous);
			$first->DiffContent = new HTMLText();
			$first->DiffContent->setValue('<div>' . $first->obj('Content')->forTemplate() . '</div>');
			$changeList->push($first);
		}

		return $changeList;
	}

	public function updateSettingsFields(FieldList $fields) {
		if(!Config::inst()->get(get_class(), 'changes_enabled')) return;
		
		// Add public history field.
		$fields->addFieldToTab('Root.Settings', $publicHistory = new FieldGroup(
			new CheckboxField('PublicHistory', $this->owner->fieldLabel('PublicHistory')
		)));

		$warning = _t(
			'VersionFeed.Warning',
			"Publicising the history will also disclose the changes that have at the time been protected " .
			"from the public view."
		);

		$fields->addFieldToTab('Root.Settings', new LiteralField('PublicHistoryWarning', $warning), 'PublicHistory');

		if ($this->owner->CanViewType!='Anyone') {
			$warning = _t(
				'VersionFeed.Warning2',
				"Changing access settings in such a way that this page or pages under it become publicly<br>" .
				"accessible may result in publicising all historical changes on these pages too. Please review<br>" .
				"this section's \"Public history\" settings to ascertain only intended information is disclosed."
			);

			$fields->addFieldToTab('Root.Settings', new LiteralField('PublicHistoryWarning2', $warning), 'CanViewType');
		}
	}

	public function getSiteRSSLink() {
		// TODO: This link should be from the homepage, not this page.
		if(Config::inst()->get(get_class(), 'allchanges_enabled')
			&& SiteConfig::current_site_config()->AllChangesEnabled
		) {
			return $this->owner->Link('allchanges');
		}
	}

	public function getDefaultRSSLink() {
		if(Config::inst()->get(get_class(), 'changes_enabled') && $this->owner->PublicHistory) {
			return $this->owner->Link('changes');
		}
	}
}
