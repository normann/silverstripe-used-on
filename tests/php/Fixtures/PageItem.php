<?php

namespace Normann\UsedOn\Tests\Fixtures;

use SilverStripe\Assets\File;
use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;

/**
 * An item on a page, with its parent relation named other than "Parent"
 */
class PageItem extends DataObject implements TestOnly
{
    private static string $table_name = 'UsedOnTest_PageItem';

    private static string $used_on_parent_relation = 'Page';

    private static array $db = [
        'Title' => 'Varchar',
    ];

    private static array $has_one = [
        'Page' => TestPage::class,
        'File' => File::class,
    ];
}
