<?php

namespace Normann\UsedOn\Tests;

use Normann\UsedOn\Forms\UsedOnTable;
use Normann\UsedOn\Tests\Fixtures\SharedItem;
use SilverStripe\Admin\Forms\UsedOnTable as BaseUsedOnTable;
use SilverStripe\AssetAdmin\Controller\AssetAdmin;
use SilverStripe\Assets\File;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Core\Config\Config;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\Form;
use SilverStripe\LinkField\Models\FileLink;

class UsedOnTableTest extends UsedOnTestCase
{
    public function testReplacesCoreField(): void
    {
        $this->assertInstanceOf(UsedOnTable::class, BaseUsedOnTable::create('UsedOn'));
    }

    /**
     * Managed records show their parents up to the page they're on
     */
    public function testAncestors(): void
    {
        $usage = $this->getUsage($this->subItem->File());

        $this->assertCount(1, $usage);
        $this->assertSame($this->subItem->getCMSEditLink(), $usage[0]['link']);
        $this->assertSame(
            ['Workbook', 'Downloads', 'About us'],
            array_column($usage[0]['ancestors'], 'title')
        );
    }

    public function testAncestorsForRecordOnPage(): void
    {
        $usage = $this->getUsage($this->pageItem->File());

        $this->assertSame(['About us'], array_column($usage[0]['ancestors'], 'title'));
    }

    /**
     * File links (silverstripe/linkfield) owned by managed records show the record's parents too
     */
    public function testAncestorsForFileLink(): void
    {
        $file = $this->createFile('linked.pdf');
        $link = FileLink::create([
            'FileID' => $file->ID,
            'OwnerID' => $this->item->ID,
            'OwnerClass' => $this->item::class,
            'OwnerRelation' => 'Link',
        ]);
        $link->write();

        $usage = $this->getUsage($file);

        $this->assertSame(
            ['Workbook', 'Downloads', 'About us'],
            array_column($usage[0]['ancestors'], 'title')
        );
        $this->assertSame('/about-us/?stage=Stage', $usage[0]['pages'][0]['link']);
    }

    /**
     * Links go to the tab with the field using the file, if it isn't shown by default (see BlockItem)
     */
    public function testLinkToTab(): void
    {
        $this->assertSame(
            $this->item->getCMSEditLink() . '#Files_Attachment',
            $this->getUsage($this->item->File())[0]['link']
        );
    }

    /**
     * Fields on the tab shown by default don't need a link to it (see SubItem)
     */
    public function testLinkToDefaultTab(): void
    {
        $this->assertSame($this->subItem->getCMSEditLink(), $this->getUsage($this->subItem->File())[0]['link']);
    }

    public function testLinkToTabDisabled(): void
    {
        Config::modify()->set(UsedOnTable::class, 'link_to_tab', false);

        $this->assertSame($this->item->getCMSEditLink(), $this->getUsage($this->item->File())[0]['link']);
    }

    public function testPageForDraftPage(): void
    {
        $this->assertSame(
            [['link' => '/about-us/?stage=Stage', 'status' => 'draft', 'statusLabel' => 'Draft']],
            $this->getUsage($this->item->File())[0]['pages']
        );
    }

    public function testPageForPublishedPage(): void
    {
        $this->page->publishRecursive();

        $this->assertSame(
            [['link' => '/about-us/?stage=Stage', 'status' => 'published', 'statusLabel' => 'Published']],
            $this->getUsage($this->item->File())[0]['pages']
        );
    }

    public function testPageForModifiedPage(): void
    {
        $this->page->publishRecursive();
        $this->page->Title = 'Updated title';
        $this->page->write();

        $this->assertSame('modified', $this->getUsage($this->item->File())[0]['pages'][0]['status']);
    }

    /**
     * Pages are modified if their content (ie. blocks) has unpublished changes, not only the page itself
     */
    public function testPageForPageWithModifiedContent(): void
    {
        $this->page->publishRecursive();
        $this->block->Title = 'Updated title';
        $this->block->write();

        $this->assertSame('modified', $this->getUsage($this->item->File())[0]['pages'][0]['status']);
    }

    /**
     * Shared records show each page they're used on, by title
     */
    public function testPagesForSharedRecord(): void
    {
        $this->otherPage->publishRecursive();

        $usage = $this->getUsage($this->sharedItem->File());

        $this->assertCount(1, $usage);
        $this->assertSame(
            [
                ['link' => '/about-us/?stage=Stage', 'status' => 'draft', 'statusLabel' => 'Draft'],
                ['link' => '/case-study/?stage=Stage', 'status' => 'published', 'statusLabel' => 'Published'],
            ],
            $usage[0]['pages']
        );
    }

    /**
     * File links (silverstripe/linkfield) owned by shared records show the shared record's pages
     */
    public function testPagesForFileLinkOnSharedRecord(): void
    {
        $file = $this->createFile('report.pdf');
        FileLink::create([
            'FileID' => $file->ID,
            'OwnerID' => $this->sharedItem->ID,
            'OwnerClass' => $this->sharedItem::class,
            'OwnerRelation' => 'Link',
        ])->write();

        $this->assertSame(
            ['/about-us/?stage=Stage', '/case-study/?stage=Stage'],
            array_column($this->getUsage($file)[0]['pages'], 'link')
        );
    }

    /**
     * File links whose owner no longer exists aren't used anywhere, so they're hidden
     */
    public function testOrphanedFileLinkHidden(): void
    {
        $file = $this->createFile('orphaned.txt');
        $owner = SharedItem::create(['Title' => 'Deleted']);
        $owner->write();
        FileLink::create([
            'FileID' => $file->ID,
            'OwnerID' => $owner->ID,
            'OwnerClass' => $owner::class,
            'OwnerRelation' => 'Link',
        ])->write();
        FileLink::create([
            'FileID' => $file->ID,
            'OwnerID' => $this->sharedItem->ID,
            'OwnerClass' => $this->sharedItem::class,
            'OwnerRelation' => 'Link',
        ])->write();

        $this->assertCount(2, $this->getUsage($file));

        // Deleting the owner (ie. without its links) leaves its file link behind, not used anywhere
        $owner->delete();

        $usage = $this->getUsage($file);
        $this->assertCount(1, $usage);
        $this->assertSame(['Annual report'], array_column($usage[0]['ancestors'], 'title'));
    }

    public function testPagesForUnusedSharedRecord(): void
    {
        $file = $this->createFile('unused.png');
        SharedItem::create(['FileID' => $file->ID])->write();

        $this->assertSame([], $this->getUsage($file)[0]['pages']);
    }

    public function testPageColumnDisabled(): void
    {
        Config::modify()->set(UsedOnTable::class, 'page_column', false);

        $this->assertArrayNotHasKey('pages', $this->getUsage($this->item->File())[0]);

        $this->assertFalse($this->createTable($this->item->File())->getSchemaDataDefaults()['data']['pageColumn']);
    }

    public function testSchemaData(): void
    {
        $this->assertTrue($this->createTable($this->item->File())->getSchemaDataDefaults()['data']['pageColumn']);
    }

    /**
     * The usage endpoint is reachable in the CMS, as usage() is overridden
     */
    public function testUsageEndpoint(): void
    {
        $response = $this->get(sprintf('admin/assets/fileEditForm/%s/field/UsedOnTable/usage', $this->item->FileID));

        $this->assertSame(200, $response->getStatusCode());
        $usage = json_decode($response->getBody(), true)['usage'];
        $this->assertSame($this->item->ID, $usage[0]['id']);
        $this->assertSame('draft', $usage[0]['pages'][0]['status']);
    }

    private function createTable(File $file): UsedOnTable
    {
        $table = UsedOnTable::create('UsedOn');
        $table->setRecord($file);
        // The schema data includes the usage endpoint, which needs a form to link to
        Form::create(AssetAdmin::singleton(), 'Form', FieldList::create($table), FieldList::create());

        return $table;
    }

    private function getUsage(File $file): array
    {
        $response = $this->createTable($file)->usage(new HTTPRequest('GET', '/'));

        return json_decode($response->getBody(), true)['usage'];
    }
}
