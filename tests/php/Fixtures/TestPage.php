<?php

namespace Normann\UsedOn\Tests\Fixtures;

use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Dev\TestOnly;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldConfig_RecordEditor;

/**
 * A page with items managed via a GridField
 */
class TestPage extends SiteTree implements TestOnly
{
    private static string $table_name = 'UsedOnTest_Page';

    private static array $has_many = [
        'PageItems' => PageItem::class,
    ];

    public function getCMSFields(): FieldList
    {
        $fields = parent::getCMSFields();
        $fields->addFieldToTab(
            'Root.Items',
            GridField::create('PageItems', 'Items', $this->PageItems(), GridFieldConfig_RecordEditor::create())
        );

        return $fields;
    }
}
