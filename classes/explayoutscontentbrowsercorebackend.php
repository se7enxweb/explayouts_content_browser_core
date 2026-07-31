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

    public function searchItems( $searchText, $locationNodeId = 0, $offset = 0, $limit = 25 )
    {
        $searchText = trim( $searchText );
        $locationNodeId = (int)$locationNodeId;

        $params = array(
            'Depth' => 10,
            'Limit' => 1000,
            'Offset' => 0,
            'SortBy' => array( 'name', true ),
        );
        $this->applyAllowedContentTypes( $params );

        $parentId = $locationNodeId > 0 ? $locationNodeId : eZContentObjectTreeNode::findMainNodeArray( eZContentObject::fetch( eZINI::instance()->variable( 'ContentSettings', 'DefaultSection' ) ) );
        $nodes = eZContentObjectTreeNode::subTreeByNodeID( $params, $locationNodeId > 0 ? $locationNodeId : 1 );
        if ( !is_array( $nodes ) )
            return array();

        $matches = array();
        foreach ( $nodes as $node )
        {
            if ( !$node instanceof eZContentObjectTreeNode )
                continue;

            if ( $searchText !== '' && !$this->nodeMatchesSearch( $node, $searchText ) )
                continue;

            $matches[] = $this->buildItem( $node );
        }

        return array_slice( $matches, (int)$offset, (int)$limit );
    }

    public function searchItemsCount( $searchText, $locationNodeId = 0 )
    {
        $items = $this->searchItems( $searchText, $locationNodeId, 0, PHP_INT_MAX );
        return count( $items );
    }

    public function loadItem( $nodeId )
    {
        $node = eZContentObjectTreeNode::fetch( (int)$nodeId );
        if ( !$node instanceof eZContentObjectTreeNode )
            return false;

        if ( !$this->isSelectable( $node ) )
            return false;

        return $this->buildItem( $node );
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
