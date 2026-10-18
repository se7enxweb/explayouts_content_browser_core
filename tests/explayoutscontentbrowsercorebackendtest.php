<?php
/**
 * The search and the loading of single items of expLayoutsContentBrowserCoreBackend, without the kernel:
 * eZContentObjectTreeNode is a stand-in that records the fetch parameters and holds a tree of 6000 nodes.
 *
 * Run directly with:
 *   php tests/explayoutscontentbrowsercorebackendtest.php
 *
 *  CB-01 - the search filters by name in the fetch itself, so content past the first 1000 nodes by name is found;
 *          offset, limit and the count are those of the filtered result
 *  CB-02 - every word must be in the name or name a class (identifier or name, ignoring case)
 *  CB-03 - loadItem() hands out only nodes the current user may read
 *  CB-04 - the SQL of the filter: LIKE with the wildcards of the search escaped, the class ids as integers
 *  CB-05 - without the settings of the filter (the kernel would ignore it and fetch everything) the words filter the
 *          name alone, with *, \ and _ escaped
 */

$failures = 0;
function cbCheck( $ok, $message )
{
    global $failures;
    echo ( $ok ? 'PASS' : 'FAIL' ), ": $message\n";
    if ( !$ok )
        $failures++;
}

class eZContentObjectTreeNode
{
    /** the tree: node id => name; and the nodes the user may read */
    public static $names = array();
    public static $readable = array();
    public static $lastParams = null;
    public $id;
    public function __construct( $id ) { $this->id = $id; }
    public function canRead() { return in_array( $this->id, self::$readable, true ); }
    public function attribute( $name ) { return $name === 'name' ? self::$names[$this->id] : null; }
    public static function fetch( $id ) { return isset( self::$names[$id] ) ? new self( $id ) : null; }

    /** the stand-in of the search filter: for each word, the name contains it (no wildcards) or the class is listed */
    protected static function matching( array $params )
    {
        $ids = array_keys( self::$names );
        usort( $ids, function( $a, $b ) { return strcmp( self::$names[$a], self::$names[$b] ); } );
        if ( isset( $params['ExtendedAttributeFilter'] ) )
        {
            $words = $params['ExtendedAttributeFilter']['params'];
            $ids = array_values( array_filter( $ids, function( $id ) use ( $words ) {
                foreach ( $words as $word )
                {
                    if ( stripos( self::$names[$id], $word['term'] ) === false && !in_array( self::classOf( $id ), $word['class_ids'], true ) )
                        return false;
                }
                return true;
            } ) );
        }
        return $ids;
    }

    public static function classOf( $id ) { return $id === 6904 ? 7 : 1; }

    public static function subTreeByNodeID( $params, $nodeId )
    {
        self::$lastParams = $params;
        $ids = array_slice( self::matching( $params ), isset( $params['Offset'] ) ? $params['Offset'] : 0, isset( $params['Limit'] ) ? $params['Limit'] : null );
        return array_map( function( $id ) { return new self( $id ); }, $ids );
    }

    public static function subTreeCountByNodeID( $params, $nodeId )
    {
        return count( self::matching( $params ) );
    }
}
class eZContentClass
{
    public $values;
    public function __construct( array $values ) { $this->values = $values; }
    public function attribute( $name ) { return $this->values[$name]; }
    public static function fetchAllClasses( $asObject = true, $includeFilter = true )
    {
        return array( new self( array( 'id' => 1, 'identifier' => 'article', 'name' => 'Article' ) ),
                      new self( array( 'id' => 7, 'identifier' => 'ng_landing_page', 'name' => 'Landing page' ) ) );
    }
}
class eZINI
{
    public static $groups = array( 'explayouts_content_browser_search' );
    public static function instance( $name = 'site.ini' ) { return new self(); }
    public function hasGroup( $group ) { return in_array( $group, self::$groups, true ); }
}
class eZDB
{
    public static function instance() { return new self(); }
    public function escapeString( $value ) { return addslashes( $value ); }
}
class expLayoutsContentBrowserItem
{
    public $node;
    public function __construct( $node ) { $this->node = $node; }
}

require_once dirname( __DIR__ ) . '/classes/explayoutscontentbrowsercorebackend.php';
require_once dirname( __DIR__ ) . '/classes/explayoutscontentbrowsercoresearchfilter.php';

for ( $i = 1; $i <= 6000; $i++ )
    eZContentObjectTreeNode::$names[$i] = sprintf( 'Article %04d', $i );
eZContentObjectTreeNode::$names[6901] = 'Layout-Demo';
eZContentObjectTreeNode::$names[6902] = 'Star *ed';
eZContentObjectTreeNode::$names[6903] = 'Stared';
eZContentObjectTreeNode::$names[6904] = 'Spring sale';

$backend = new expLayoutsContentBrowserCoreBackend();
$ids = function( $items ) { return array_map( function( $item ) { return $item->node->id; }, $items ); };

// CB-01
cbCheck( $ids( $backend->searchItems( 'Layout', 1 ) ) === array( 6901 ), 'CB-01 a node past the first 1000 by name is found' );
cbCheck( $backend->searchItemsCount( 'layout', 1 ) === 1, 'CB-01 the count is that of the filtered result (and LIKE ignores case here)' );
cbCheck( isset( eZContentObjectTreeNode::$lastParams['ExtendedAttributeFilter'] ) && eZContentObjectTreeNode::$lastParams['Limit'] === 25,
         'CB-01 the filter and the limit are part of the fetch' );
$expected = count( array_filter( eZContentObjectTreeNode::$names, function( $name ) { return strpos( $name, '00' ) !== false; } ) );
cbCheck( count( $backend->searchItems( '00', 1, 10, 5 ) ) === 5 && $backend->searchItemsCount( '00', 1 ) === $expected, "CB-01 offset and limit of the filtered result ($expected)" );

// CB-02
cbCheck( $ids( $backend->searchItems( 'Article 0042', 1 ) ) === array( 42 ), 'CB-02 every word must be in the name' );
cbCheck( $ids( $backend->searchItems( 'landing', 1 ) ) === array( 6904 ), 'CB-02 a word of the class name finds content of that class' );
cbCheck( $ids( $backend->searchItems( 'ng_landing spring', 1 ) ) === array( 6904 ), 'CB-02 class identifier and name together' );
cbCheck( $backend->searchItemsCount( 'landing autumn', 1 ) === 0, 'CB-02 every word must match' );

// CB-04
$filter = new expLayoutsContentBrowserCoreSearchFilter();
$sql = $filter->sqlParts( array( array( 'term' => "50%_o'k!", 'class_ids' => array( '7', 'x' ) ), array( 'term' => 'b', 'class_ids' => array() ) ) );
cbCheck( $sql['joins'] === "( ezcontentobject_name.name LIKE '%50!%!_o\\'k!!%' ESCAPE '!' OR ezcontentobject.contentclass_id IN (7) ) AND ( ezcontentobject_name.name LIKE '%b%' ESCAPE '!' ) AND ",
         'CB-04 wildcards escaped, quotes escaped, class ids as integers: ' . $sql['joins'] );

// CB-03
eZContentObjectTreeNode::$readable = array( 2 );
cbCheck( $backend->loadItem( 2 ) instanceof expLayoutsContentBrowserItem, 'CB-03 a readable node is loaded' );
cbCheck( $backend->loadItem( 3 ) === false, 'CB-03 a node the user may not read is not' );

// CB-05
eZINI::$groups = array();
$backend = new expLayoutsContentBrowserCoreBackend();
$backend->searchItems( 'a_b* c\\d', 1 );
$params = eZContentObjectTreeNode::$lastParams;
cbCheck( !isset( $params['ExtendedAttributeFilter'] ) && isset( $params['AttributeFilter'] )
         && $params['AttributeFilter'] === array( 'and', array( 'name', 'like', '*a\\_b\\**' ), array( 'name', 'like', '*c\\\\d*' ) ),
         'CB-05 without its settings a name filter, never the whole tree: ' . json_encode( isset( $params['AttributeFilter'] ) ? $params['AttributeFilter'] : null ) );

echo $failures ? "$failures failed\n" : "all passed\n";
exit( $failures ? 1 : 0 );
