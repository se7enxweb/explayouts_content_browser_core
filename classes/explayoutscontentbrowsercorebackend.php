<?php
class expLayoutsContentBrowserCoreBackend
{
    protected $allowedContentTypes;
    protected $locationContentTypes;

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
     * Content below the location (the whole tree for 0) whose name contains every word of $searchText, the user's
     * read policies applied. The filter is part of the fetch: the first 1000 nodes by name were fetched and then
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
     * The fetch parameters of a search: every word of $searchText in the name (LIKE, case as the collation has it),
     * the allowed classes, ten levels deep.
     */
    protected function searchParams( $searchText )
    {
        $params = array( 'Depth' => 10 );
        $this->applyAllowedContentTypes( $params );

        $filter = array();
        foreach ( preg_split( '/\s+/', trim( (string)$searchText ) ) as $term )
        {
            if ( $term === '' )
                continue;
            // * is the wildcard of the attribute filter, \ its escape
            $filter[] = array( 'name', 'like', '*' . str_replace( array( '\\', '*' ), array( '\\\\', '\\*' ), $term ) . '*' );
        }
        if ( $filter )
            $params['AttributeFilter'] = array_merge( array( 'and' ), $filter );

        return $params;
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

    protected function nodeMatchesSearch( eZContentObjectTreeNode $node, $searchText )
    {
        $object = $node->attribute( 'object' );
        $haystack = strtolower( $node->attribute( 'name' ) );
        if ( $object instanceof eZContentObject )
        {
            $haystack .= ' ' . strtolower( $object->attribute( 'class_identifier' ) );
            $haystack .= ' ' . strtolower( $object->attribute( 'class_name' ) );
        }

        $terms = preg_split( '/\s+/', trim( $searchText ) );
        foreach ( $terms as $term )
        {
            $term = trim( $term );
            if ( $term === '' )
                continue;

            if ( stripos( $haystack, $term ) === false )
                return false;
        }

        return true;
    }
}
