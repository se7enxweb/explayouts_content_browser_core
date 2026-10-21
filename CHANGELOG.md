# Changelog

## 1.0.4 (2026-10-20)

- The search is part of the fetch: every word must be in the name, the class identifier or the class name
  (extended attribute filter `explayouts_content_browser_search`; without its settings a filter on the name alone).
  Before, only the first 1000 nodes by name were searched.
- `loadItem()` returns only nodes the current user may read.
- New class `expLayoutsContentBrowserCoreSearchFilter`: run `php bin/php/ezpgenerateautoloads.php -e` and clear the
  INI cache.
