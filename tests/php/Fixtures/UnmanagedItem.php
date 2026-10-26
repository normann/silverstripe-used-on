<?php

namespace Normann\UsedOn\Tests\Fixtures;

use SilverStripe\Assets\File;
use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;

/**
 * An item on a block that has no GridField for it
 */
class UnmanagedItem extends DataObject implements TestOnly
{
    private static string $table_name = 'UsedOnTest_UnmanagedItem';

    private static array $db = [
        'Title' => 'Varchar',
    ];

    private static array $has_one = [
        'Parent' => TestBlock::class,
        'File' => File::class,
    ];
}
