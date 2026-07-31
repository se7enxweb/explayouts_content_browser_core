# FAQ — explayouts_content_browser_core

## How is this different from `expLayoutsContentBrowser` in `explayouts_content_browser`?

`expLayoutsContentBrowser` lists a flat subtree page and filters by node name only. `expLayoutsContentBrowserCoreBackend` adds depth-1 child listing (a real tree browser step), content class filtering via the constructor, multi-term search against name plus class identifier and class name, and a selectable-class check in `loadItem()`.

## How does search work exactly?

`searchItems()` fetches up to 1000 nodes to depth 10 under the given location (tree root when none is given), then requires every whitespace-separated term of the search text to appear as a case-insensitive substring in the node name, class identifier or class name. Results beyond the first 1000 fetched nodes are never seen.

## What is the second constructor argument (`locationContentTypes`) for?

It is accepted and stored but not used yet. It is reserved for deciding which content classes are treated as expandable locations in a tree UI.

## Why does `loadItem()` return `false` for an existing node?

Either the node ID does not resolve to a tree node, or the node's content class is not in the `allowedContentTypes` list you passed to the constructor. With an empty allowed list, every class passes.

## Is `searchItemsCount()` expensive?

Yes — it re-runs the full search and counts the results, so a searched, paged listing performs the subtree scan twice. Cache the count if you call it repeatedly in one request.
