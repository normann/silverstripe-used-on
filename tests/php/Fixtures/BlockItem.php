<?php

namespace Normann\UsedOn\Tests\Fixtures;

use SilverStripe\Assets\File;
use SilverStripe\Dev\TestOnly;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\LiteralField;
use SilverStripe\Forms\TabSet;
use SilverStripe\ORM\DataObject;

/**
 * An item on a block, with its own items (managed via the scaffolded "SubItems" GridField),
 * and its file on a tab not shown by default, in a tab set nested in the main tab
 */
class BlockItem extends DataObject implements TestOnly
{
    private static string $table_name = 'UsedOnTest_BlockItem';

    private static array $db = [
        'Title' => 'Varchar',
    ];

    private static array $has_one = [
        'Parent' => TestBlock::class,
        'File' => File::class,
    ];

    private static array $has_many = [
        'SubItems' => SubItem::class,
    ];

    public function getCMSFields(): FieldList
    {
        $fields = parent::getCMSFields();
        $file = $fields->dataFieldByName('File');
        $fields->removeByName('File');

        $fields->addFieldToTab('Root.Main', TabSet::create('Files'));
        $fields->addFieldToTab('Root.Main.Files.Details', LiteralField::create('Details', 'Details'));
        $fields->addFieldToTab('Root.Main.Files.Attachment', $file);

        return $fields;
    }
}
