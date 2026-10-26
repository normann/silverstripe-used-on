# Changelog

## Unreleased

- Hide file links (silverstripe/linkfield) whose owner no longer exists, as they aren't used anywhere
- Add `SharedRecordExtension`, for records used by other records (ie. an image selected by blocks on several
  pages), listing each page they're used on in the "Page URL" column
- Link to the tab of the CMS edit form with the field using the file, if it isn't shown by default
  (`link_to_tab`)

## 1.0.0

Initial release, for Silverstripe CMS 6.

- Link records managed via a GridField on a parent record (ie. a block, page or another record) to their
  edit form in the "Used on" tab of files, and show their parents up to the page
- Add a "Page URL" column with the page's publishing status, and widen the file editing pane to fit it
- Open the "Used on" tab's links in a new tab
