# explayouts_content_browser_core

Content browser core backend services for Exponential Layouts on Exponential Legacy / Exponential 6. It provides a backend class that lists direct children of a location, searches a subtree and loads single items, with optional content class filtering — the layer that the content browser UI talks to.

Exponential Legacy port inspired by `netgen/content-browser` (the backend layer of the content browser stack). It depends on `explayouts_content_browser` because it returns `expLayoutsContentBrowserItem` objects.

## Key classes

| Class | File | Purpose |
|-------|------|---------|
| `expLayoutsContentBrowserCoreBackend` | `classes/explayoutscontentbrowsercorebackend.php` | Child listing, subtree search and single-item loading with class filters |

## Quick example

```php
<?php
$backend = new expLayoutsContentBrowserCoreBackend(
    array( 'article', 'folder' ),  // allowed/selectable content class identifiers
    array( 'folder' )              // class identifiers treated as locations (reserved)
);

$items = $backend->getSubItems( 43, 0, 25 );
$total = $backend->getSubItemsCount( 43 );
?>
```

## Documentation

- [INSTALL.md](INSTALL.md) — activation steps and dependencies
- [doc/USAGE.md](doc/USAGE.md) — full API, usage scenarios and customization
- [doc/FAQ.md](doc/FAQ.md) — frequently asked questions
- [doc/TODO.md](doc/TODO.md) — known gaps and planned work
- [doc/SUPPORT.md](doc/SUPPORT.md) — how to get help
