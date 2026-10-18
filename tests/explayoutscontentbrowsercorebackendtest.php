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
 *  CB-02 - every word must be in the name; * and \ of the search are literal, not wildcards
 *  CB-03 - loadItem() hands out only nodes the current user may read
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

    /** the stand-in of the attribute filter: 'and' of name LIKE terms, * the wildcard, \* a literal * */
    protected static function matching( array $params )
    {
        $ids = array_keys( self::$names );
        sort( $ids );
        usort( $ids, function( $a, $b ) { return strcmp( self::$names[$a], self::$names[$b] ); } );
        if ( isset( $params['AttributeFilter'] ) )
        {
            $filter = $params['AttributeFilter'];
            array_shift( $filter );
            $ids = array_values( array_filter( $ids, function( $id ) use ( $filter ) {
                foreach ( $filter as $condition )
                {
                    $pattern = preg_replace_callback( '/\\\\\\\\|\\\\\*|\*|[^*\\\\]+/', function( $m ) {
                        if ( $m[0] === '*' ) return '.*';
                        if ( $m[0] === '\\*' ) return '\*';
                        if ( $m[0] === '\\\\' ) return '\\\\';
                        return preg_quote( $m[0], '/' );
                    }, $condition[2] );
                    if ( $condition[0] !== 'name' || $condition[1] !== 'like' || !preg_match( '/^' . $pattern . '$/i', self::$names[$id] ) )
                        return false;
                }
                return true;
            } ) );
        }
        return $ids;
    }

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
class expLayoutsContentBrowserItem
{
    public $node;
    public function __construct( $node ) { $this->node = $node; }
}

require_once dirname( __DIR__ ) . '/classes/explayoutscontentbrowsercorebackend.php';

for ( $i = 1; $i <= 6000; $i++ )
    eZContentObjectTreeNode::$names[$i] = sprintf( 'Article %04d', $i );
eZContentObjectTreeNode::$names[6901] = 'Layout-Demo';
eZContentObjectTreeNode::$names[6902] = 'Star *ed';
eZContentObjectTreeNode::$names[6903] = 'Stared';

$backend = new expLayoutsContentBrowserCoreBackend();
$ids = function( $items ) { return array_map( function( $item ) { return $item->node->id; }, $items ); };

// CB-01
cbCheck( $ids( $backend->searchItems( 'Layout', 1 ) ) === array( 6901 ), 'CB-01 a node past the first 1000 by name is found' );
cbCheck( $backend->searchItemsCount( 'layout', 1 ) === 1, 'CB-01 the count is that of the filtered result (and LIKE ignores case here)' );
cbCheck( isset( eZContentObjectTreeNode::$lastParams['AttributeFilter'] ) && eZContentObjectTreeNode::$lastParams['Limit'] === 25,
         'CB-01 the filter and the limit are part of the fetch' );
cbCheck( count( $backend->searchItems( 'Article', 1, 10, 5 ) ) === 5 && $backend->searchItemsCount( 'Article', 1 ) === 6000, 'CB-01 offset and limit of the filtered result' );

// CB-02
cbCheck( $ids( $backend->searchItems( 'Article 0042', 1 ) ) === array( 42 ), 'CB-02 every word must be in the name' );
cbCheck( $ids( $backend->searchItems( 'Star *ed', 1 ) ) === array( 6902 ), 'CB-02 a * of the search is no wildcard' );

// CB-03
eZContentObjectTreeNode::$readable = array( 2 );
cbCheck( $backend->loadItem( 2 ) instanceof expLayoutsContentBrowserItem, 'CB-03 a readable node is loaded' );
cbCheck( $backend->loadItem( 3 ) === false, 'CB-03 a node the user may not read is not' );

echo $failures ? "$failures failed\n" : "all passed\n";
exit( $failures ? 1 : 0 );
