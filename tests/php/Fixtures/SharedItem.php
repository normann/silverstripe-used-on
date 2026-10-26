<?php

namespace Normann\UsedOn\Tests\Fixtures;

use SilverStripe\Assets\File;
use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;

/**
 * A record selected by blocks (ie. on several pages), rather than having a parent
 */
class SharedItem extends DataObject implements TestOnly
{
    private static string $table_name = 'UsedOnTest_SharedItem';

    private static array|string $used_on_used_by = 'Blocks';

    private static array $db = [
        'Title' => 'Varchar',
    ];

    private static array $has_one = [
        'File' => File::class,
    ];

    private static array $has_many = [
        'Blocks' => TestBlock::class . '.Shared',
    ];
}
