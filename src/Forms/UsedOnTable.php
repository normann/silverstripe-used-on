<?php

namespace Normann\UsedOn\Forms;

use SilverStripe\Admin\Forms\UsedOnTable as BaseUsedOnTable;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Control\Controller;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;
use Normann\UsedOn\Extensions\SharedRecordExtension;
use SilverStripe\Forms\FormField;
use SilverStripe\Forms\Tab;
use SilverStripe\ORM\DataObject;
use SilverStripe\Versioned\Versioned;
use Throwable;

/**
 * Replaces the core UsedOnTable (via Injector) to add the page each record on the "Used on" tab of files is on,
 * with a link to it in the front end and its publishing status, so CMS users can see the file in context.
 * Rendered by client/src/js/used-on-table.js
 */
class UsedOnTable extends BaseUsedOnTable
{
    /**
     * Show the "Page URL" column. The file editing pane is widened to fit it while the "Used on" tab is open.
     */
    private static bool $page_column = true;

    /**
     * Link to the tab of the CMS edit form that has the field using the file (ie. "#ConfigSet_Before"),
     * if it isn't the tab shown by default
     */
    private static bool $link_to_tab = true;

    /**
     * Actions need to be allowed on the class that defines them, so as usage() is overridden it's allowed here too
     */
    private static array $allowed_actions = [
        'usage',
    ];

    public function getSchemaDataDefaults()
    {
        $defaults = parent::getSchemaDataDefaults();
        $defaults['data'] = array_merge($defaults['data'] ?? [], [
            'pageColumn' => (bool) static::config()->get('page_column'),
        ]);

        return $defaults;
    }

    /**
     * As per the parent, with the addition of "pages"
     */
    public function usage(HTTPRequest $request)
    {
        $usage = [];

        $record = $this->getRecord() ?: DataObject::create();
        if ($record->canView()) {
            $excludedClasses = [];
            $this->extend('updateUsageExcludedClasses', $excludedClasses);
            $usage = $record->findAllRelatedData($excludedClasses);
        }

        $pageColumn = static::config()->get('page_column');
        $usageData = [];
        foreach ($usage as $dataObject) {
            $this->extend('updateUsageDataObject', $dataObject);
            if (!$dataObject) {
                continue;
            }

            $ancestorDataObjects = [];
            $this->extend('updateUsageAncestorDataObjects', $ancestorDataObjects, $dataObject);

            $row = [
                'id' => $dataObject->ID,
                'title' => $this->getUsageTitle($dataObject),
                'type' => ucfirst($dataObject->i18n_singular_name() ?? ''),
                'link' => $this->getUsageLink($dataObject, $record),
                'ancestors' => array_map(fn (DataObject $ancestor) => [
                    'title' => $this->getUsageTitle($ancestor),
                    'link' => $ancestor->getCMSEditLink(),
                ], $ancestorDataObjects),
            ];

            if ($pageColumn) {
                $row['pages'] = $this->getUsagePages([$dataObject, ...$ancestorDataObjects]);
            }

            $usageData[] = $row;
        }

        $response = HTTPResponse::create(json_encode(['usage' => $usageData]));
        $response->addHeader('Content-Type', 'application/json');

        return $response;
    }

    /**
     * The pages the given records (ie. the record and its ancestors) are on: the first page in them, or otherwise
     * the pages that any shared records in them are used on (see SharedRecordExtension)
     *
     * @return array<array{link: string, status: string, statusLabel: string}>
     */
    protected function getUsagePages(array $dataObjects): array
    {
        $pages = [];
        foreach ($dataObjects as $dataObject) {
            if ($dataObject instanceof SiteTree) {
                $pages = [$dataObject];
                break;
            }

            if ($dataObject->hasExtension(SharedRecordExtension::class)) {
                foreach ($dataObject->getUsedOnPages() as $page) {
                    $pages[$page->ID] = $page;
                }
            }
        }

        return array_values(array_filter(array_map(
            fn (SiteTree $page) => $page->canView() ? $this->getUsagePage($page) : null,
            $pages
        )));
    }

    /**
     * Front end link (to the draft site) of a page, and its publishing status: "draft" (never published),
     * "modified" (the page or its content, ie. blocks, has unpublished changes) or "published"
     *
     * @return array{link: string, status: string, statusLabel: string}
     */
    protected function getUsagePage(SiteTree $page): array
    {
        if ($page->isOnDraftOnly()) {
            $status = 'draft';
            $statusLabel = _t(Versioned::class . '.FLAG_ADDEDTODRAFT_SHORT', 'Draft');
        } elseif ($page->stagesDifferRecursive()) {
            $status = 'modified';
            $statusLabel = _t(Versioned::class . '.FLAG_MODIFIEDONDRAFT_SHORT', 'Modified');
        } else {
            $status = 'published';
            $statusLabel = _t(__CLASS__ . '.PUBLISHED', 'Published');
        }

        $data = [
            'link' => Controller::join_links($page->Link(), '?stage=Stage'),
            'status' => $status,
            'statusLabel' => $statusLabel,
        ];

        $this->extend('updateUsagePage', $data, $page);

        return $data;
    }

    /**
     * The CMS edit link of a record using the file, to the tab with the field using it (see link_to_tab)
     */
    protected function getUsageLink(DataObject $dataObject, DataObject $record): ?string
    {
        $link = $dataObject->getCMSEditLink();

        if (!$link || str_contains($link, '#') || !static::config()->get('link_to_tab')) {
            return $link;
        }

        $tabID = $this->getUsageTabID($dataObject, $record);

        return $tabID ? $link . '#' . $tabID : $link;
    }

    /**
     * The HTML ID of the tab (as per Tab::ID()) with the field of the has_one relation using the file,
     * if it isn't shown by default. When tabs are nested, this is the innermost tab not shown by default,
     * as the CMS can only open one tab from the URL.
     */
    protected function getUsageTabID(DataObject $dataObject, DataObject $record): ?string
    {
        $relations = $this->getUsageRelations($dataObject, $record);

        if (!$relations) {
            return null;
        }

        try {
            $fields = $dataObject->getCMSFields();
        } catch (Throwable $e) {
            // Don't let a record's CMS fields break the "Used on" tab, it's only for linking to a tab
            return null;
        }

        foreach ($relations as $relation) {
            $field = $fields->dataFieldByName($relation) ?? $fields->dataFieldByName($relation . 'ID');
            $tab = $field ? $this->getHiddenTab($field) : null;

            if ($tab) {
                return $tab->ID();
            }
        }

        return null;
    }

    /**
     * Names of the record's has_one relations to the file
     */
    protected function getUsageRelations(DataObject $dataObject, DataObject $record): array
    {
        $relations = [];

        foreach ($dataObject->hasOne() as $relation => $class) {
            if ((int) $dataObject->getField($relation . 'ID') !== (int) $record->ID) {
                continue;
            }

            // Polymorphic relations store the class too
            if ($class === DataObject::class) {
                $class = $dataObject->getField($relation . 'Class');
            }

            if ($class && is_a($record, $class)) {
                $relations[] = $relation;
            }
        }

        return $relations;
    }

    /**
     * The innermost tab containing the field that isn't the first (default) tab of its tab set
     */
    private function getHiddenTab(FormField $field): ?Tab
    {
        $list = $field->getContainerFieldList();

        while ($list && ($container = $list->getContainerField())) {
            if ($container instanceof Tab && $container->getTabSet()?->Tabs()->first() !== $container) {
                return $container;
            }

            $list = $container->getContainerFieldList();
        }

        return null;
    }

    private function getUsageTitle(DataObject $dataObject): string
    {
        return $dataObject->getTitle() ?: _t(BaseUsedOnTable::class . '.UNTITLED', 'Untitled');
    }
}
