<?php

namespace Normann\UsedOn\Tests;

use Normann\UsedOn\Tests\Fixtures\BlockItem;
use Normann\UsedOn\Tests\Fixtures\PageItem;
use Normann\UsedOn\Tests\Fixtures\UnmanagedItem;
use SilverStripe\Core\Config\Config;

class ManagedRecordExtensionTest extends UsedOnTestCase
{
    /**
     * Records on blocks link to their edit form, nested within the block's, with the GridField found by model class
     */
    public function testLinkForRecordOnBlock(): void
    {
        $this->assertLinkEndsWith(
            sprintf(
                'admin/pages/edit/EditForm/%s/field/ElementalArea/item/%s/ItemEditForm/field/Downloads/item/%s/edit',
                $this->page->ID,
                $this->block->ID,
                $this->item->ID
            ),
            $this->item->getCMSEditLink()
        );
    }

    /**
     * Records on other managed records link to their edit form, nested within their parent's
     */
    public function testLinkForRecordOnRecord(): void
    {
        $this->assertLinkEndsWith(
            sprintf(
                'item/%s/ItemEditForm/field/Downloads/item/%s/ItemEditForm/field/SubItems/item/%s/edit',
                $this->block->ID,
                $this->item->ID,
                $this->subItem->ID
            ),
            $this->subItem->getCMSEditLink()
        );
    }

    /**
     * Records on pages link to their edit form, nested within the page's, via a custom parent relation
     */
    public function testLinkForRecordOnPage(): void
    {
        $this->assertLinkEndsWith(
            sprintf('admin/pages/edit/EditForm/%s/field/PageItems/item/%s/edit', $this->page->ID, $this->pageItem->ID),
            $this->pageItem->getCMSEditLink()
        );
    }

    public function testConfiguredGridField(): void
    {
        Config::modify()->set(BlockItem::class, 'used_on_grid_field', 'Configured');

        $this->assertLinkEndsWith(
            sprintf('ItemEditForm/field/Configured/item/%s/edit', $this->item->ID),
            $this->item->getCMSEditLink()
        );
    }

    /**
     * Records with no GridField on their parent link to their parent instead
     */
    public function testLinkWithoutGridField(): void
    {
        $item = UnmanagedItem::create(['ParentID' => $this->block->ID]);
        $item->write();

        $this->assertSame($this->block->getCMSEditLink(), $item->getCMSEditLink());
    }

    public function testLinkWithoutParent(): void
    {
        $item = BlockItem::create();
        $item->write();

        $this->assertNull($item->getCMSEditLink());
    }

    public function testParentWithMissingRelation(): void
    {
        Config::modify()->set(PageItem::class, 'used_on_parent_relation', 'Missing');

        $this->assertNull($this->pageItem->getUsedOnParent());
        $this->assertNull($this->pageItem->getCMSEditLink());
    }

    public function testLinkIsReachable(): void
    {
        $response = $this->get($this->subItem->getCMSEditLink());

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('Workbook guide', $response->getBody());
    }

    private function assertLinkEndsWith(string $expected, ?string $link): void
    {
        $this->assertNotNull($link);
        $this->assertStringEndsWith($expected, rtrim($link, '/'));
    }
}
