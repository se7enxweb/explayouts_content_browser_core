Exponential Layouts Content Browser Core
========================================

General description
-------------------

Exponential Layouts Content Browser Core (`explayouts_content_browser_core`)
provides the content browser core backend services for Exponential Layouts
on Exponential Legacy / Exponential 6. It ships a backend class that lists
direct children of a location, searches a subtree and loads single items,
with optional content class filtering — the layer that the content browser
UI talks to.

This extension is an Exponential Legacy port inspired by
`netgen/content-browser` (the backend layer of the content browser stack).
It depends on `explayouts_content_browser` because it returns
`expLayoutsContentBrowserItem` objects, and it provides the following
capabilities:

- Location child listing - Use this feature to list the direct (depth 1)
  children of any location as normalized items, sorted by name.
- Subtree search - Use this feature to search a subtree with multi-term,
  case-insensitive matching against node names, class identifiers and class
  names.
- Content class filtering - Use this feature to restrict which content
  classes are listed and selectable via a constructor argument.
- Single item loading - Use this feature to load one node as a normalized
  item, guarded by a selectable-class check.

Features
--------

The following features are provided by the Exponential Layouts Content
Browser Core extension:

- A single, focused backend class, `expLayoutsContentBrowserCoreBackend`,
  constructed with two optional arguments:
  - `allowedContentTypes` — content class identifiers that are listed and
    selectable; with an empty list, every content class is selectable and no
    class filter is applied.
  - `locationContentTypes` — class identifiers treated as locations
    (reserved for expandable-location detection in tree UIs; accepted and
    stored, currently unused).
- Real tree-browser stepping: `getSubItems( $locationNodeId, $offset = 0,
  $limit = 25 )` returns the direct child nodes (depth 1) of a location as
  `expLayoutsContentBrowserItem[]`, sorted by name and filtered by the
  allowed content classes, with `getSubItemsCount( $locationNodeId )`
  returning the matching child count.
- Multi-term subtree search: `searchItems( $searchText, $locationNodeId = 0,
  $offset = 0, $limit = 25 )` searches the subtree under a location (up to
  depth 10, at most 1000 fetched nodes; falls back to the tree root when no
  location is given). Every whitespace-separated term must match the node
  name, class identifier or class name as a case-insensitive substring.
- `searchItemsCount( $searchText, $locationNodeId = 0 )` for counting search
  results (re-runs the search without a page limit — cache the value if you
  call it repeatedly in one request).
- Guarded item loading: `loadItem( $nodeId )` loads a single node as an
  `expLayoutsContentBrowserItem` only if it exists and passes the
  selectable-class check, and returns `false` otherwise.
- Designed-for-subclassing internals — the behaviour lives in `protected`
  hook methods so integrators can extend without forking:
  - `buildItem( eZContentObjectTreeNode $node )` — return a richer/custom
    item object for every result.
  - `nodeMatchesSearch( $node, $searchText )` — change the matching rules
    (e.g. include attribute values).
  - `isSelectable( $node )` — add permission or state checks to `loadItem()`.
  - `applyAllowedContentTypes( &$params )` — change how the class filter is
    translated to fetch parameters.
- Works everywhere the bootstrap is available: module views, JSON endpoints,
  layout block handlers and CLI scripts.

Version
-------

- The current version of Exponential Layouts Content Browser Core is 1.0.0
- Last Major update: July 30, 2026

Copyright
---------

- Exponential Layouts Content Browser Core is copyright 1998 - 2026 7x
- See: [LICENSE.md](LICENSE.md) for more information on the terms of the
  copyright and license

License
-------

Exponential Layouts Content Browser Core is licensed under the GNU General
Public License.

The complete license agreement is included in the [LICENSE.md](LICENSE.md)
file.

Exponential Layouts Content Browser Core is free software: you can
redistribute it and/or modify it under the terms of the GNU General Public
License as published by the Free Software Foundation, either version 2 of
the License or at your option a later version.

Exponential Layouts Content Browser Core is distributed in the hope that it
will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty
of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General
Public License for more details.

The GNU GPL gives you the right to use, modify and redistribute
Exponential Layouts Content Browser Core under certain conditions. The GNU
GPL license is distributed with the software, see the file
[LICENSE.md](LICENSE.md).

It is also available at http://www.gnu.org/licenses/gpl.txt

You should have received a copy of the GNU General Public License along with
Exponential Layouts Content Browser Core in [LICENSE.md](LICENSE.md). If
not, see http://www.gnu.org/licenses/.

Using Exponential Layouts Content Browser Core under the terms of the GNU
GPL is free (as in freedom).

For more information or questions please contact info@se7enx.com

Requirements
------------

The following requirements exist for using the Exponential Layouts Content
Browser Core extension:

Exponential version
- Make sure you use Exponential 6 / Exponential Legacy.

PHP version
- Make sure you have PHP 8.1, 8.2, 8.3 or 8.4.

Extension dependencies
- `explayouts_content_browser` — provides the `expLayoutsContentBrowserItem`
  value object returned by the backend. Activate it first.

Installation
------------

Installation is the standard extension procedure: place the extension in
`extension/explayouts_content_browser_core`, activate it after its
dependency via `ActiveExtensions[]` (or `ActiveAccessExtensions[]` for a
single siteaccess), regenerate autoloads with
`php bin/php/ezpgenerateautoloads.php -e` and clear caches with
`php bin/php/ezcache.php --clear-all --purge --allow-root-user`.

See [INSTALL.md](INSTALL.md) for the complete step-by-step installation
instructions.

Usage
-----

The extension ships one key class:

| Class | File | Purpose |
|-------|------|---------|
| `expLayoutsContentBrowserCoreBackend` | `classes/explayoutscontentbrowsercorebackend.php` | Child listing, subtree search and single-item loading with class filters |

A quick example:

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

Searching a subtree and loading a picked item:

```php
<?php
$items = $backend->searchItems( 'annual report', 43, 0, 20 );
$total = $backend->searchItemsCount( 'annual report', 43 );

$item = $backend->loadItem( 123 ); // expLayoutsContentBrowserItem or false
?>
```

Compared to `expLayoutsContentBrowser` in the sibling extension, this
backend adds depth-1 child listing (a real tree browser step), content
class filtering via the constructor, multi-term search against name plus
class identifier and class name, and a selectable-class check in
`loadItem()`.

The full usage guide in [doc/USAGE.md](doc/USAGE.md) covers the complete
API, usage scenarios (paged location browsers in module views, JSON search
endpoints, resolving picked items in layout block handlers, CLI usage) and
the three customization layers of this stack: the settings layer (the INI
configuration cascade), the template layer (design overrides — rendering
belongs to consumers such as `explayouts_content_browser_ui`) and the PHP
layer (the `protected` hook methods listed above).

Documentation
-------------

| Document | Description |
|----------|-------------|
| [INSTALL.md](INSTALL.md) | Requirements, dependencies and step-by-step activation instructions |
| [doc/USAGE.md](doc/USAGE.md) | Full API reference, usage scenarios and the settings/template/PHP customization layers |
| [doc/FAQ.md](doc/FAQ.md) | Frequently asked questions and answers |
| [doc/TODO.md](doc/TODO.md) | Known gaps and planned improvements |
| [doc/SUPPORT.md](doc/SUPPORT.md) | Where and how to get help |
| [LICENSE.md](LICENSE.md) | The complete GNU General Public License agreement |

Troubleshooting
---------------

Read the FAQ
- Some problems are more common than others. The most common ones are listed
  in [doc/FAQ.md](doc/FAQ.md).

Use our support systems
- If you find a bug or defect, please report it to the
  [Exponential Layouts Content Browser Core: Issue Tracker](https://github.com/se7enxweb/explayouts_content_browser_core/issues)
- For commercial support and custom development please visit
  [se7enx.com](https://se7enx.com)
