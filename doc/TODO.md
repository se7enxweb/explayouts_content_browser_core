# TODO — explayouts_content_browser_core

- `locationContentTypes` (second constructor argument) is stored but never used; implement location/expandable detection or remove the argument.
- `searchItems()` contains a dead statement that computes `$parentId` from `eZINI` `ContentSettings/DefaultSection` and `eZContentObjectTreeNode::findMainNodeArray()`; its result is unused and the actual fallback root is node 1. Clean this up and make the search root explicit.
- Search fetches at most 1000 nodes (depth 10) before filtering, so matches deeper in large trees are silently missed. Replace with a server-side filter or index-backed search.
- `searchItemsCount()` re-runs the entire search (`searchItems()` with `PHP_INT_MAX`); add a count-only path or memoize per request.
- No INI configuration; allowed classes must be passed to the constructor by every caller.
