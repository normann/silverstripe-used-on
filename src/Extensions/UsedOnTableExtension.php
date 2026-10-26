<?php

namespace Normann\UsedOn\Extensions;

use SilverStripe\Admin\Forms\UsedOnTable;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Core\Extension;
use SilverStripe\Core\Validation\ValidationException;
use SilverStripe\LinkField\Models\FileLink;
use SilverStripe\ORM\DataObject;

/**
 * Shows the parents of managed records (see ManagedRecordExtension) on the "Used on" tab of files,
 * up to the page they're on, ie. Page / Block / Download item.
 * Also hides file links (silverstripe/linkfield) whose owner no longer exists, as they aren't used anywhere.
 *
 * @extends Extension<UsedOnTable>
 */
class UsedOnTableExtension extends Extension
{
    /**
     * Hide file links whose owner no longer exists (ie. was deleted without its links), as per elemental hiding
     * blocks that aren't on a page
     */
    protected function updateUsageDataObject(?DataObject &$dataObject): void
    {
        if ($dataObject instanceof FileLink && !$dataObject->Owner()?->exists()) {
            $dataObject = null;
        }
    }

    protected function updateUsageAncestorDataObjects(array &$ancestorDataObjects, DataObject $dataObject): void
    {
        // File links (silverstripe/linkfield) already have their owner added as an ancestor by linkfield
        $record = $dataObject instanceof FileLink ? $dataObject->Owner() : $dataObject;

        if (!$record?->hasExtension(ManagedRecordExtension::class)) {
            return;
        }

        $parent = $record->getUsedOnParent();
        $visited = [];

        while ($parent && !isset($visited[$parent->getUniqueKey()])) {
            $visited[$parent->getUniqueKey()] = true;
            $ancestorDataObjects[] = $parent;

            if ($parent instanceof SiteTree) {
                return;
            }

            if ($parent->hasExtension(ManagedRecordExtension::class)) {
                $parent = $parent->getUsedOnParent();
                continue;
            }

            // Blocks (dnadesign/silverstripe-elemental) know which page they're on
            if ($parent->hasMethod('getPage')) {
                $page = $this->getPage($parent);
                if ($page) {
                    $ancestorDataObjects[] = $page;
                }
            }

            return;
        }
    }

    private function getPage(DataObject $block): ?DataObject
    {
        try {
            $page = $block->getPage();
        } catch (ValidationException $e) {
            return null;
        }

        return $page?->exists() ? $page : null;
    }
}
