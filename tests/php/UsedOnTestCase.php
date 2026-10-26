<?php

namespace Normann\UsedOn\Tests;

use DNADesign\Elemental\Extensions\ElementalPageExtension;
use Normann\UsedOn\Extensions\ManagedRecordExtension;
use Normann\UsedOn\Extensions\SharedRecordExtension;
use Normann\UsedOn\Tests\Fixtures\BlockItem;
use Normann\UsedOn\Tests\Fixtures\PageItem;
use Normann\UsedOn\Tests\Fixtures\SharedItem;
use Normann\UsedOn\Tests\Fixtures\SubItem;
use Normann\UsedOn\Tests\Fixtures\TestBlock;
use Normann\UsedOn\Tests\Fixtures\TestPage;
use Normann\UsedOn\Tests\Fixtures\UnmanagedItem;
use SilverStripe\Assets\File;
use SilverStripe\Control\Controller;
use SilverStripe\Core\Config\Config;
use SilverStripe\Dev\FunctionalTest;
use SilverStripe\Versioned\Versioned;

/**
 * A page with a block, which has an item, which has a sub item; and an item on the page itself.
 * The block selects a shared item, which a block on another page selects too.
 */
abstract class UsedOnTestCase extends FunctionalTest
{
    protected $usesDatabase = true;

    protected static $extra_dataobjects = [
        TestPage::class,
        TestBlock::class,
        BlockItem::class,
        SubItem::class,
        PageItem::class,
        UnmanagedItem::class,
        SharedItem::class,
    ];

    protected static $required_extensions = [
        TestPage::class => [ElementalPageExtension::class],
        BlockItem::class => [ManagedRecordExtension::class],
        SubItem::class => [ManagedRecordExtension::class],
        PageItem::class => [ManagedRecordExtension::class],
        UnmanagedItem::class => [ManagedRecordExtension::class],
        SharedItem::class => [SharedRecordExtension::class],
    ];

    protected TestPage $page;

    protected TestBlock $block;

    protected BlockItem $item;

    protected SubItem $subItem;

    protected PageItem $pageItem;

    protected TestPage $otherPage;

    protected SharedItem $sharedItem;

    protected function setUp(): void
    {
        parent::setUp();

        $this->logInWithPermission('ADMIN');
        // Links (ie. page links) depend on this, which varies by project
        Config::modify()->set(Controller::class, 'add_trailing_slash', true);
        // Elemental areas are only created for pages when on the draft stage
        Versioned::set_stage(Versioned::DRAFT);

        $this->page = TestPage::create(['Title' => 'About us', 'URLSegment' => 'about-us']);
        $this->page->write();

        $this->sharedItem = SharedItem::create([
            'Title' => 'Annual report',
            'FileID' => $this->createFile('before.png')->ID,
        ]);
        $this->sharedItem->write();

        $this->block = TestBlock::create([
            'Title' => 'Downloads',
            'ParentID' => $this->page->ElementalArea()->ID,
            'SharedID' => $this->sharedItem->ID,
        ]);
        $this->block->write();

        $this->otherPage = TestPage::create(['Title' => 'Case study', 'URLSegment' => 'case-study']);
        $this->otherPage->write();
        TestBlock::create([
            'Title' => 'Slider',
            'ParentID' => $this->otherPage->ElementalArea()->ID,
            'SharedID' => $this->sharedItem->ID,
        ])->write();

        $this->item = BlockItem::create([
            'Title' => 'Workbook',
            'ParentID' => $this->block->ID,
            'FileID' => $this->createFile('workbook.xlsx')->ID,
        ]);
        $this->item->write();

        $this->subItem = SubItem::create([
            'Title' => 'Workbook guide',
            'ParentID' => $this->item->ID,
            'FileID' => $this->createFile('guide.pdf')->ID,
        ]);
        $this->subItem->write();

        $this->pageItem = PageItem::create([
            'Title' => 'Checklist',
            'PageID' => $this->page->ID,
            'FileID' => $this->createFile('checklist.pdf')->ID,
        ]);
        $this->pageItem->write();
    }

    protected function createFile(string $name): File
    {
        $file = File::create();
        $file->setFromString('test', $name);
        $file->write();

        return $file;
    }
}
