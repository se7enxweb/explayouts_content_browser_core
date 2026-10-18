<?php
/**
 * The extended attribute filter of the content browser's search (extendedattributefilter.ini
 * [explayouts_content_browser_search]): each word must be in the name of the content (LIKE) or name a class whose
 * identifier or name contains it. The classes are worked out beforehand (expLayoutsContentBrowserCoreBackend), so
 * the query compares class ids, not the serialized class names.
 *
 * @license GPL-2.0-or-later
 */
class expLayoutsContentBrowserCoreSearchFilter
{
    /**
     * @param array $params list of array( 'term' => string, 'class_ids' => int[] )
     * @return array the SQL parts eZContentObjectTreeNode expects (tables, joins, columns)
     */
    public function sqlParts( $params )
    {
        $db = eZDB::instance();
        $conditions = array();
        foreach ( is_array( $params ) ? $params : array() as $word )
        {
            $term = isset( $word['term'] ) && is_string( $word['term'] ) ? $word['term'] : '';
            if ( $term === '' )
                continue;
            // ! escapes the wildcards of LIKE, the same way on every database
            $pattern = '%' . str_replace( array( '!', '%', '_' ), array( '!!', '!%', '!_' ), $term ) . '%';
            $condition = "ezcontentobject_name.name LIKE '" . $db->escapeString( $pattern ) . "' ESCAPE '!'";
            $classIds = isset( $word['class_ids'] ) && is_array( $word['class_ids'] ) ? array_filter( array_map( 'intval', $word['class_ids'] ) ) : array();
            if ( $classIds )
                $condition .= ' OR ezcontentobject.contentclass_id IN (' . implode( ',', $classIds ) . ')';
            $conditions[] = '( ' . $condition . ' ) AND ';
        }
        return array( 'tables' => '', 'joins' => implode( '', $conditions ), 'columns' => '' );
    }
}
