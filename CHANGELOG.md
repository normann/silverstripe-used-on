# Changelog

## 1.0.0

Initial release, for Silverstripe CMS 6.

- Link records managed via a GridField on a parent record (ie. a block, page or another record) to their
  edit form in the "Used on" tab of files, and show their parents up to the page (`ManagedRecordExtension`)
- List each page that records used by other records are on (ie. a resource selected by blocks on several
  pages) (`SharedRecordExtension`)
- Add a "Page URL" column with each page's publishing status, and widen the file editing pane to fit it
- Link to the tab of the CMS edit form with the field using the file, if it isn't shown by default
- Open the "Used on" tab's links in a new tab
- Hide file links (silverstripe/linkfield) whose owner no longer exists, as they aren't used anywhere
