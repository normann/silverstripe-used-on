<?php

namespace Normann\UsedOn\Tests\Fixtures;

use SilverStripe\Assets\File;
use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;

/**
 * An item on an item on a block
 */
class SubItem extends DataObject implements TestOnly
{
    private static string $table_name = 'UsedOnTest_SubItem';

    private static array $db = [
        'Title' => 'Varchar',
    ];

    private static array $has_one = [
        'Parent' => BlockItem::class,
        'File' => File::class,
    ];
}
