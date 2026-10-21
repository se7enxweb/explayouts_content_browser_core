<?php
class expLayoutsContentBrowserCoreBackend
{
    protected $allowedContentTypes;
    protected $locationContentTypes;
    /** see classes() */
    protected $classes = null;

    public function __construct( array $allowedContentTypes = array(), array $locationContentTypes = array() )
    {
        $this->allowedContentTypes = array_filter( $allowedContentTypes );
        $this->locationContentTypes = array_filter( $locationContentTypes );
    }

    public function getSubItems( $locationNodeId, $offset = 0, $limit = 25 )
    {
        $params = array(
            'Depth' => 1,
            'Limit' => (int)$limit,
            'Offset' => (int)$offset,
            'SortBy' => array( 'name', true ),
        );

        $this->applyAllowedContentTypes( $params );

        $nodes = eZContentObjectTreeNode::subTreeByNodeID( $params, (int)$locationNodeId );
        if ( !is_array( $nodes ) )
            return array();

        return $this->buildItems( $nodes );
    }

    public function getSubItemsCount( $locationNodeId )
    {
        $params = array(
            'Depth' => 1,
        );
        $this->applyAllowedContentTypes( $params );

        return (int)eZContentObjectTreeNode::subTreeCountByNodeID( $params, (int)$locationNodeId );
    }

    /**
     * Content below the location (the whole tree for 0) where every word of $searchText is in the name, the class
     * identifier or the class name, the user's read policies applied. The filter is part of the fetch (the extended
     * attribute filter expLayoutsContentBrowserCoreSearchFilter): the first 1000 nodes by name were fetched and then
     * filtered in PHP, so in a tree of more nodes most content could not be found.
     */
    public function searchItems( $searchText, $locationNodeId = 0, $offset = 0, $limit = 25 )
    {
        $params = $this->searchParams( $searchText );
        $params['Limit'] = (int)$limit;
        $params['Offset'] = (int)$offset;
        $params['SortBy'] = array( 'name', true );

        $nodes = eZContentObjectTreeNode::subTreeByNodeID( $params, $this->searchRoot( $locationNodeId ) );
        if ( !is_array( $nodes ) )
            return array();

        return $this->buildItems( $nodes );
    }

    public function searchItemsCount( $searchText, $locationNodeId = 0 )
    {
        return (int)eZContentObjectTreeNode::subTreeCountByNodeID( $this->searchParams( $searchText ), $this->searchRoot( $locationNodeId ) );
    }

    /**
     * A node the current user may read and whose class is allowed, or false.
     */
    public function loadItem( $nodeId )
    {
        $node = eZContentObjectTreeNode::fetch( (int)$nodeId );
        if ( !$node instanceof eZContentObjectTreeNode )
            return false;

        // fetch() asks no policy: without this any node id handed out its name, class and path
        if ( !$node->canRead() )
            return false;

        if ( !$this->isSelectable( $node ) )
            return false;

        return $this->buildItem( $node );
    }

    protected function searchRoot( $locationNodeId )
    {
        return (int)$locationNodeId > 0 ? (int)$locationNodeId : 1;
    }

    /**
     * The fetch parameters of a search: each word in the name (LIKE, case as the collation has it) or naming a class
     * by identifier or name, the allowed classes, ten levels deep.
     */
    protected function searchParams( $searchText )
    {
        $params = array( 'Depth' => 10 );
        $this->applyAllowedContentTypes( $params );

        $words = array();
        foreach ( preg_split( '/\s+/', trim( (string)$searchText ) ) as $term )
        {
            if ( $term !== '' )
                $words[] = array( 'term' => $term, 'class_ids' => $this->classIdsMatching( $term ) );
        }
        if ( !$words )
            return $params;

        // The kernel ignores an extended attribute filter it has no settings for and fetches everything, so the
        // filter is only used when its settings are there; otherwise the words filter the name alone.
        if ( self::searchFilterAvailable() )
        {
            $params['ExtendedAttributeFilter'] = array( 'id' => 'explayouts_content_browser_search', 'params' => $words );
            return $params;
        }
        $filter = array( 'and' );
        foreach ( $words as $word )
        {
            // * is the wildcard of the attribute filter and \ its escape; % is escaped by the kernel, _ is not
            $term = str_replace( array( '\\', '*', '_' ), array( '\\\\', '\\*', '\\_' ), $word['term'] );
            $filter[] = array( 'name', 'like', '*' . $term . '*' );
        }
        $params['AttributeFilter'] = $filter;
        return $params;
    }

    /**
     * Whether extendedattributefilter.ini has the group of the search filter (settings of this extension).
     *
     * @return bool
     */
    protected static function searchFilterAvailable()
    {
        return eZINI::instance( 'extendedattributefilter.ini' )->hasGroup( 'explayouts_content_browser_search' );
    }

    /**
     * The ids of the classes whose identifier or name (in the current language) contains $term, ignoring case.
     *
     * @param string $term
     * @return int[]
     */
    protected function classIdsMatching( $term )
    {
        $ids = array();
        foreach ( $this->classes() as $id => $texts )
        {
            foreach ( $texts as $text )
            {
                if ( $text !== '' && mb_stripos( $text, $term ) !== false )
                {
                    $ids[] = (int)$id;
                    break;
                }
            }
        }
        return $ids;
    }

    /** class id => array( identifier, name ), read once */
    protected function classes()
    {
        if ( $this->classes === null )
        {
            $this->classes = array();
            foreach ( (array)eZContentClass::fetchAllClasses( true, false ) as $class )
            {
                if ( $class instanceof eZContentClass )
                    $this->classes[(int)$class->attribute( 'id' )] = array( (string)$class->attribute( 'identifier' ), (string)$class->attribute( 'name' ) );
            }
        }
        return $this->classes;
    }

    protected function applyAllowedContentTypes( &$params )
    {
        if ( !empty( $this->allowedContentTypes ) )
        {
            $params['ClassFilterType'] = 'include';
            $params['ClassFilterArray'] = $this->allowedContentTypes;
        }
    }

    protected function buildItems( array $nodes )
    {
        $items = array();
        foreach ( $nodes as $node )
        {
            if ( !$node instanceof eZContentObjectTreeNode )
                continue;

            $items[] = $this->buildItem( $node );
        }
        return $items;
    }

    protected function buildItem( eZContentObjectTreeNode $node )
    {
        return new expLayoutsContentBrowserItem( $node );
    }

    protected function isSelectable( eZContentObjectTreeNode $node )
    {
        if ( empty( $this->allowedContentTypes ) )
            return true;

        $object = $node->attribute( 'object' );
        if ( !$object instanceof eZContentObject )
            return false;

        return in_array( $object->attribute( 'class_identifier' ), $this->allowedContentTypes, true );
    }
}
