<?php

namespace Normann\UsedOn\Tests\Fixtures;

use DNADesign\Elemental\Models\BaseElement;
use SilverStripe\Dev\TestOnly;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldConfig_RecordEditor;

/**
 * A block with items managed via a GridField, named differently to the relation, and a shared item selected
 */
class TestBlock extends BaseElement implements TestOnly
{
    private static string $table_name = 'UsedOnTest_Block';

    private static bool $inline_editable = false;

    private static array $has_one = [
        'Shared' => SharedItem::class,
    ];

    private static array $has_many = [
        'Items' => BlockItem::class,
    ];

    public function getCMSFields(): FieldList
    {
        $fields = parent::getCMSFields();
        $fields->removeByName('Items');
        $fields->addFieldToTab(
            'Root.Main',
            GridField::create('Downloads', 'Downloads', $this->Items(), GridFieldConfig_RecordEditor::create())
        );

        return $fields;
    }
}
