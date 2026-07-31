# Using explayouts_content_browser_core

## Constructor

```php
<?php
$backend = new expLayoutsContentBrowserCoreBackend(
    array( 'article', 'folder' ),  // allowed/selectable content class identifiers
    array( 'folder' )              // class identifiers treated as locations (reserved, currently unused)
);
?>
```

Both arguments are optional. With an empty allowed list, every content class is selectable and no class filter is applied.

## API

### getSubItems( $locationNodeId, $offset = 0, $limit = 25 )

Returns direct child nodes (Depth 1) of a location as `expLayoutsContentBrowserItem[]`, sorted by name, filtered by the allowed content classes.

```php
<?php
$items = $backend->getSubItems( 43, 0, 25 );
foreach ( $items as $item )
{
    echo $item->name . ' (' . $item->classIdentifier . ")\n";
}
?>
```

### getSubItemsCount( $locationNodeId )

Returns the number of direct child nodes, honouring the class filter.

### searchItems( $searchText, $locationNodeId = 0, $offset = 0, $limit = 25 )

Searches the subtree under `$locationNodeId` (up to depth 10, at most 1000 fetched nodes). Every whitespace-separated term must match the node name, class identifier or class name (case-insensitive substring). Falls back to the tree root when no location is given.

```php
<?php
$items = $backend->searchItems( 'annual report', 43, 0, 20 );
?>
```

### searchItemsCount( $searchText, $locationNodeId = 0 )

Returns the number of matching search results (re-runs the search without a page limit).

### loadItem( $nodeId )

Loads a single node as `expLayoutsContentBrowserItem` if it exists and passes the selectable-class check; returns `false` otherwise.

## Scenario: paged location browser in a module view

```php
<?php
$http = eZHTTPTool::instance();
$locationNodeId = isset( $Params['LocationNodeID'] ) ? (int)$Params['LocationNodeID'] : 2;
$offset = (int)$http->getVariable( 'offset', 0 );
$limit = 25;

$backend = new expLayoutsContentBrowserCoreBackend(
    array( 'article', 'product', 'image' ),
    array( 'folder' )
);

$items = $backend->getSubItems( $locationNodeId, $offset, $limit );
$total = $backend->getSubItemsCount( $locationNodeId );

$tpl = eZTemplate::factory();
$tpl->setVariable( 'items', $items );
$tpl->setVariable( 'total', $total );
$tpl->setVariable( 'offset', $offset );
$tpl->setVariable( 'limit', $limit );

$Result = array();
$Result['content'] = $tpl->fetch( 'design:content_browser/location.tpl' );
return $Result;
?>
```

## Scenario: JSON search endpoint

```php
<?php
$http = eZHTTPTool::instance();
$search = trim( $http->getVariable( 'q', '' ) );
$locationNodeId = (int)$http->getVariable( 'location', 0 );
$offset = (int)$http->getVariable( 'offset', 0 );
$limit = (int)$http->getVariable( 'limit', 25 );

$backend = new expLayoutsContentBrowserCoreBackend( array( 'article', 'product' ) );
$items = $backend->searchItems( $search, $locationNodeId, $offset, $limit );
$total = $backend->searchItemsCount( $search, $locationNodeId );

$out = array(
    'total' => $total,
    'items' => array_map( function( $item ) { return $item->toArray(); }, $items ),
);

header( 'Content-Type: application/json' );
echo json_encode( $out );
eZExecution::cleanExit();
?>
```

## Scenario: resolving a picked item in a layout block handler

```php
<?php
class myFeaturedBlockHandler
{
    public function getValues( $block )
    {
        $params = is_array( $block ) && isset( $block['parameters'] ) ? $block['parameters'] : array();
        $nodeId = isset( $params['node_id'] ) ? (int)$params['node_id'] : 0;

        $backend = new expLayoutsContentBrowserCoreBackend();
        $item = $nodeId > 0 ? $backend->loadItem( $nodeId ) : false;

        return array(
            'item' => $item ? $item->toArray() : false,
        );
    }
}
?>
```

## Scenario: CLI usage

```bash
php bin/php/ezexec.php ai/bin/tmp/test_content_browser_core.php --allow-root-user
```

Any script running under the bootstrap can construct the backend directly; only autoload generation is required beforehand.

## Customization

### Settings layer (INI)

This extension ships no INI settings and reads none — allowed classes are passed to the constructor by each caller. When configuration lands (see [TODO.md](TODO.md)), it will follow this stack's cascade, lowest to highest priority:

1. `settings/*.ini` — kernel defaults
2. `extension/<ext>/settings/*.ini.append.php` — extension defaults
3. `settings/siteaccess/<siteaccess>/*.ini.append.php` — siteaccess overrides
4. `extension/<ext>/settings/siteaccess/<siteaccess>/*.ini.append.php` — extension siteaccess overrides
5. `settings/override/*.ini.append.php` — global overrides (always win)

### Template layer (design overrides)

No templates are shipped; the backend returns value objects only. Rendering (and therefore template overriding) belongs to the consumer, for example `explayouts_content_browser_ui`.

### PHP layer (extension points)

`expLayoutsContentBrowserCoreBackend` keeps its behaviour in `protected` methods precisely so integrators can subclass it:

- `buildItem( eZContentObjectTreeNode $node )` — return a richer/custom item object for every result.
- `nodeMatchesSearch( $node, $searchText )` — change the matching rules (e.g. include attribute values).
- `isSelectable( $node )` — add permission or state checks to `loadItem()`.
- `applyAllowedContentTypes( &$params )` — change how the class filter is translated to fetch parameters.

Constructor arguments (`allowedContentTypes`, `locationContentTypes`) are the supported way to vary behaviour without subclassing.
