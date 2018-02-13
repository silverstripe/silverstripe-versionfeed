<?php

namespace SilverStripe\VersionFeed;

use SilverStripe\CMS\Model\SiteTreeExtension;
use SilverStripe\Core\Config\Config;
use SilverStripe\Forms\CheckboxField;
use SilverStripe\Forms\FieldGroup;
use SilverStripe\Forms\FieldList;
use SilverStripe\ORM\ArrayList;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\FieldType\DBField;
use SilverStripe\SiteConfig\SiteConfig;
use SilverStripe\View\Parsers\Diff;
use SilverStripe\CMS\Model\SiteTree;

class VersionFeed extends SiteTreeExtension
{
    
    private static $db = array(
        'PublicHistory' => 'Boolean(true)'
    );

    private static $defaults = array(
        'PublicHistory' => true
    );

    public function updateFieldLabels(&$labels)
    {
        $labels['PublicHistory'] = _t(__CLASS__ . '.LABEL', 'Make history public');
    }

    /**
     * Enable the allchanges feed
     *
     * @config
     * @var bool
     */
    private static $allchanges_enabled = true;

    /**
     * Allchanges feed limit of items.
     *
     * @config
     * @var int
     */
    private static $allchanges_limit = 20;

    /**
     * Enables RSS feed for page-specific changes
     *
     * @config
     * @var bool
     */
    private static $changes_enabled = true;

    /**
     * Changes feed limit of items.
     *
     * @config
     * @var int
     */
    private static $changes_limit = 100;

    /**
     * Compile a list of changes to the current page, excluding non-published and explicitly secured versions.
     *
     * @param int $highestVersion Top version number to consider.
     * @param int $limit Limit to the amount of items returned.
     *
     * @returns ArrayList List of cleaned records.
     */
    public function getDiffList($highestVersion = null, $limit = 100)
    {
        // This can leak secured content if it was protected via inherited setting.
        // For now the users will need to be aware about this shortcoming.
        $offset = $highestVersion ? "AND \"SiteTree_Versions\".\"Version\"<='".(int)$highestVersion."'" : '';
        // Get just enough elements for diffing. We need one more than desired to have something to compare to.
        $qLimit = (int)$limit + 1;
        $versions = $this->owner->allVersions(
            "\"WasPublished\"='1' AND \"CanViewType\" IN ('Anyone', 'Inherit') $offset",
            "\"SiteTree\".\"LastEdited\" DESC, \"SiteTree\".\"ID\" DESC",
            $qLimit
        );

        // Process the list to add the comparisons.
        $changeList = new ArrayList();
        $previous = null;
        $count = 0;
        foreach ($versions as $version) {
            $changed = false;

            // Check if we have something to compare with.
            if (isset($previous)) {
                // Produce the diff fields for use in the template.
                if ($version->Title != $previous->Title) {
                    $diffTitle = Diff::compareHTML($version->Title, $previous->Title);

                    $version->DiffTitle = DBField::create_field('HTMLText', null);
                    $version->DiffTitle->setValue(
                        sprintf(
                            '<div><em>%s</em> ' . $diffTitle . '</div>',
                            _t(__CLASS__ . '.TITLECHANGED', 'Title has changed:')
                        )
                    );
                    $changed = true;
                }

                if ($version->Content != $previous->Content) {
                    $diffContent = Diff::compareHTML($version->Content, $previous->Content);

                    $version->DiffContent = DBField::create_field('HTMLText', null);
                    $version->DiffContent->setValue('<div>'.$diffContent.'</div>');
                    $changed = true;
                }

                // Copy the link so it can be cached.
                $oldPage = $version->getField('object');
                if (!$oldPage instanceof SiteTree) {
                    // We only need enough info to generate the link...
                    $oldPage = SiteTree::create([
                        'ID' => $oldPage->ID,
                        'URLSegment' => $oldPage->URLSegment,
                        'ParentID' => $oldPage->ParentID
                    ]);
                }
                $version->GeneratedLink = $oldPage->AbsoluteLink();
            }

            // Omit the versions that haven't been visibly changed (only takes the above fields into consideration).
            if ($changed) {
                $changeList->push($version);
                $count++;
            }

            // Store the last version for comparison.
            $previous = $version;
        }

        // Make sure enough diff items have been generated to satisfy the $limit. If we ran out, add the final,
        // non-diffed item (the initial version). This will also work for a single-diff request: if we are requesting
        // a diff on the initial version we will just get that version, verbatim.
        if ($previous && $versions->count()<$qLimit) {
            $first = clone($previous);
            $first->DiffContent = DBField::create_field('HTMLText', null);
            $first->DiffContent->setValue('<div>' . $first->Content . '</div>');
            // Copy the link so it can be cached.
            $first->GeneratedLink = $first->AbsoluteLink();
            $changeList->push($first);
        }

        return $changeList;
    }

    /**
     * Return a single diff representing this version.
     * Returns the initial version if there is nothing to compare to.
     *
     * @return DataObject|null Object with relevant fields diffed.
     */
    public function getDiff()
    {
        $changes = $this->getDiffList($this->owner->Version, 1);
        if ($changes && $changes->Count()) {
            return $changes->First();
        }

        return null;
    }

    /**
     * Compile a list of changes to the current page, excluding non-published and explicitly secured versions.
     *
     * @deprecated 2.0.0 Use VersionFeed::getDiffList instead
     *
     * @param int $highestVersion Top version number to consider.
     * @param boolean $fullHistory Set to true to get the full change history, set to false for a single diff.
     * @param int $limit Limit to the amount of items returned.
     *
     * @returns ArrayList List of cleaned records.
     */
    public function getDiffedChanges($highestVersion = null, $fullHistory = true, $limit = 100)
    {
        return $this->getDiffList(
            $highestVersion,
            $fullHistory ? $limit : 1
        );
    }

    public function updateSettingsFields(FieldList $fields)
    {
        if (!$this->owner->config()->get('changes_enabled')) {
            return;
        }
        
        // Add public history field.
        $fields->addFieldToTab(
            'Root.Settings',
            $publicHistory = FieldGroup::create(
                CheckboxField::create('PublicHistory', $this->owner->fieldLabel('PublicHistory'))
            )
                ->setDescription(_t(
                    __CLASS__ . '.Warning',
                    "Publicising the history will also disclose the changes that have at the "
                    . "time been protected from the public view."
                ))
        );

        if ($this->owner->CanViewType != 'Anyone') {
            $canViewType = $fields->fieldByName('Root.Settings.CanViewType');
            if ($canViewType) {
                $canViewType->setDescription(_t(
                    __CLASS__ . '.Warning2',
                    "Changing access settings in such a way that this page or pages under it become publicly<br>"
                    . "accessible may result in publicising all historical changes on these pages too. Please review"
                    . "<br> this section's \"Public history\" settings to ascertain only intended information is "
                    . "disclosed."
                ));
            }
        }
    }

    public function getSiteRSSLink()
    {
        // TODO: This link should be from the homepage, not this page.
        if (Config::inst()->get(get_class(), 'allchanges_enabled')
            && SiteConfig::current_site_config()->AllChangesEnabled
        ) {
            return $this->owner->Link('allchanges');
        }
    }

    public function getDefaultRSSLink()
    {
        if (Config::inst()->get(get_class(), 'changes_enabled') && $this->owner->PublicHistory) {
            return $this->owner->Link('changes');
        }
    }
}
