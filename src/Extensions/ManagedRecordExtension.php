<?php

namespace Normann\UsedOn\Extensions;

use LogicException;
use SilverStripe\CMS\Controllers\CMSPageEditController;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Control\Controller;
use SilverStripe\Core\Extension;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\ORM\DataObject;

/**
 * For records managed via a GridField on a parent record, ie. a list of downloads on a block, or items on a page.
 *
 * These records usually have no CMS edit link of their own, so the "Used on" tab of files has a broken link
 * (`#`) for them. This extension links them to their edit form, nested within their parent's edit form.
 * Parents can be blocks, pages, records edited in a ModelAdmin, or other managed records.
 *
 * Configuration on the extended class:
 * - used_on_parent_relation: the has_one relation to the parent, "Parent" by default
 * - used_on_grid_field: the name of the GridField that manages the record on its parent,
 *   found from the parent's CMS fields by default
 *
 * @extends Extension<DataObject>
 */
class ManagedRecordExtension extends Extension
{
    private static string $used_on_parent_relation = 'Parent';

    private static ?string $used_on_grid_field = null;

    /**
     * GridField names found on parents, by parent and record class (not config, so not private)
     */
    protected static array $gridFieldNames = [];

    public function getUsedOnParent(): ?DataObject
    {
        $owner = $this->getOwner();
        $relation = $owner->config()->get('used_on_parent_relation');

        if (!$relation || !$owner->getSchema()->hasOneComponent($owner::class, $relation)) {
            return null;
        }

        $parent = $owner->getComponent($relation);

        return $parent->exists() ? $parent : null;
    }

    /**
     * The name of the GridField that manages the record on the given parent
     */
    public function getUsedOnGridFieldName(DataObject $parent): ?string
    {
        $owner = $this->getOwner();
        $configured = $owner->config()->get('used_on_grid_field');

        if ($configured) {
            return $configured;
        }

        $key = $parent::class . '|' . $owner::class;
        if (!array_key_exists($key, self::$gridFieldNames)) {
            self::$gridFieldNames[$key] = $this->findGridFieldName($parent);
        }

        return self::$gridFieldNames[$key];
    }

    protected function updateCMSEditLink(?string &$link): void
    {
        $owner = $this->getOwner();

        if ($link || !$owner->isInDB()) {
            return;
        }

        $parent = $this->getUsedOnParent();
        $parentLink = $parent?->getCMSEditLink();

        if (!$parentLink) {
            return;
        }

        $gridFieldName = $this->getUsedOnGridFieldName($parent);
        $itemPath = $gridFieldName
            ? Controller::join_links('field', $gridFieldName, 'item', $owner->ID, 'edit')
            : null;

        if ($itemPath && preg_match('#/edit/?$#', $parentLink)) {
            // Parents edited via a GridField (ie. blocks) link to their edit form (ending with /edit),
            // replace that with a link to the record's edit form nested within it
            $link = preg_replace('#edit/?$#', Controller::join_links('ItemEditForm', $itemPath), $parentLink);
        } elseif ($itemPath && $parent instanceof SiteTree) {
            $link = Controller::join_links(
                CMSPageEditController::singleton()->Link('EditForm'),
                $parent->ID,
                $itemPath
            );
        } else {
            // Otherwise link to the parent, rather than having a broken link
            $link = $parentLink;
        }
    }

    private function findGridFieldName(DataObject $parent): ?string
    {
        $ownerClass = $this->getOwner()::class;

        foreach ($parent->getCMSFields()->dataFields() as $field) {
            if (!$field instanceof GridField) {
                continue;
            }

            try {
                $modelClass = $field->getModelClass();
            } catch (LogicException $e) {
                // The GridField has no model, ie. no list
                continue;
            }

            if (is_a($ownerClass, $modelClass, true)) {
                return $field->getName();
            }
        }

        return null;
    }
}
