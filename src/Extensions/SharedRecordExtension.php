<?php

namespace Normann\UsedOn\Extensions;

use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Core\Extension;
use SilverStripe\Core\Validation\ValidationException;
use SilverStripe\Model\List\SS_List;
use SilverStripe\ORM\DataObject;

/**
 * For records that are used by other records, rather than having a parent, ie. an image selected by blocks
 * on several pages. Shows each page the record is on in the "Page URL" column of the "Used on" tab of files.
 *
 * Configuration on the extended class:
 * - used_on_used_by: the relation, or relations, to the records using it (ie. a has_many to the blocks)
 *
 * The records using it can be pages, blocks (dnadesign/silverstripe-elemental), records with
 * ManagedRecordExtension, or other records with this extension.
 *
 * @extends Extension<DataObject>
 */
class SharedRecordExtension extends Extension
{
    private static array|string $used_on_used_by = [];

    /**
     * Records using this record, via the used_on_used_by relations
     *
     * @return DataObject[]
     */
    public function getUsedOnUsers(): array
    {
        $owner = $this->getOwner();
        $users = [];

        foreach ((array) $owner->config()->get('used_on_used_by') as $relation) {
            if (!$owner->hasMethod($relation)) {
                continue;
            }

            $related = $owner->$relation();
            $records = $related instanceof SS_List ? $related : [$related];

            foreach ($records as $record) {
                if ($record instanceof DataObject && $record->exists()) {
                    $users[$record->getUniqueKey()] = $record;
                }
            }
        }

        return array_values($users);
    }

    /**
     * The pages this record is used on, via the records using it, by title
     *
     * @return SiteTree[]
     */
    public function getUsedOnPages(): array
    {
        $pages = [];
        $visited = [$this->getOwner()->getUniqueKey() => true];

        foreach ($this->getUsedOnUsers() as $user) {
            foreach ($this->findPages($user, $visited) as $page) {
                $pages[$page->ID] = $page;
            }
        }

        usort($pages, fn (SiteTree $a, SiteTree $b) => strnatcasecmp((string) $a->Title, (string) $b->Title));

        return $pages;
    }

    /**
     * @return SiteTree[]
     */
    private function findPages(DataObject $record, array &$visited): array
    {
        if (isset($visited[$record->getUniqueKey()])) {
            return [];
        }
        $visited[$record->getUniqueKey()] = true;

        if ($record instanceof SiteTree) {
            return [$record];
        }

        if ($record->hasExtension(SharedRecordExtension::class)) {
            $pages = [];
            foreach ($record->getUsedOnUsers() as $user) {
                array_push($pages, ...$this->findPages($user, $visited));
            }

            return $pages;
        }

        if ($record->hasExtension(ManagedRecordExtension::class)) {
            $parent = $record->getUsedOnParent();

            return $parent ? $this->findPages($parent, $visited) : [];
        }

        // Blocks (dnadesign/silverstripe-elemental) know which page they're on
        if ($record->hasMethod('getPage')) {
            try {
                $page = $record->getPage();
            } catch (ValidationException $e) {
                return [];
            }

            return $page instanceof SiteTree && $page->exists() ? [$page] : [];
        }

        return [];
    }
}
