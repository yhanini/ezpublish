<?php
/**
 * File containing eZContentOperationDeleteRegression class
 *
 * @copyright Copyright (C) 1999-2011 eZ Systems AS. All rights reserved.
 * @license http://ez.no/licenses/gnu_gpl GNU GPLv2
 * @package tests
 */

/**
 * @backupGlobals disabled
 */
class eZContentOperationDeleteObjectRegression extends ezpDatabaseTestCase
{
    private $folder;

    private $article;

    private $nodeIds = array();

    private $objectIds = array();

    private $db;

    public function __construct( $name = NULL, array $data = array(), $dataName = '' )
    {
        parent::__construct( $name, $data, $dataName );
        $this->setName( "eZContentOperationDeleteObject Regression Tests" );
    }

    public function setUp()
    {
        parent::setUp();

        $this->db = eZDB::instance();

        $adminUser = eZUser::fetchByName( 'admin' );
        eZUser::setCurrentlyLoggedInUser( $adminUser, $adminUser->attribute( 'contentobject_id' ) );
        ezpINIHelper::setINISetting( 'site.ini', 'SearchSettings', 'DelayedIndexing', 'enabled' );

        $this->folder = new ezpObject( 'folder', 2 );
        $this->folder->name = 'Test folder';
        $this->folder->publish();
        $this->nodeIds[] = $this->folder->main_node_id;
        $this->objectIds[] = $this->folder->object->attribute( 'id' );

        $this->article = new ezpObject( 'article', 2 );
        $this->article->title = 'Test article';
        $this->article->publish();
        $this->nodeIds[] = $this->article->main_node_id;
        $this->objectIds[] = $this->article->object->attribute( 'id' );
    }

    public function tearDown()
    {
        $this->folder->remove();
        $this->article->remove();
        $this->db->query( 'DELETE FROM ezpending_actions WHERE action = "index_object"' );
        $this->nodeIds = array();
        $this->objectIds = array();

        $anonymousUser = eZUser::fetchByName( 'anonymous' );
        eZUser::setCurrentlyLoggedInUser( $anonymousUser, $anonymousUser->attribute( 'contentobject_id' ) );
        eZContentLanguage::expireCache();

        parent::tearDown();
    }

    private function countIndexPendingActionsByObjectId( $objectID )
    {
        $count = 0;
        $objectID = (array)$objectID;
        $inStatement = $this->db->generateSQLINStatement( $objectID, 'param' );
        $query = "SELECT COUNT(*) AS count FROM ezpending_actions WHERE action = 'index_object' AND $inStatement";
        $aRes = $this->db->arrayQuery( $query );
        if ( !empty( $aRes ) )
            $count = (int)$aRes[0]['count'];

        return $count;
    }

    /**
     * Regression test for issue #017932: Indexer crashes on trashed objects
     *
     * Test Outline
     * ------------
     * 1. Activate DelayedIndexing
     * 2. Publish an object
     * 3. Remove this object
     *
     * @result Entry in ezpending_action table is not removed
     * @expected Entry in ezpending_action table should be removed
     * @link http://issues.ez.no/17932
     * @group issue_17932
     */
    public function testRemovePendingSearchOnDeleteObject()
    {
        $objectID = $this->folder->object->attribute( 'id' );

        // Now remove it
        eZContentOperationCollection::deleteObject( array( $this->folder->mainNode->node_id ) );
        $pendingCount = $this->countIndexPendingActionsByObjectId( $objectID );
        self::assertEquals( 0, $pendingCount, 'eZContentOperationCollection::deleteObject() must remove pending action for object #'.$objectID );
    }

    /**
     * Same test as {@link self::testRemovePendingSearchOnDeleteObject()}, with several objects
     * @group issue_17932
     */
    public function testRemovePendingSearchOnDeleteSeveralObjects()
    {
        eZContentOperationCollection::deleteObject( $this->nodeIds );
        $pendingCount = $this->countIndexPendingActionsByObjectId( $this->objectIds );
        self::assertEquals( 0, $pendingCount, 'eZContentOperationCollection::deleteObject() must remove pending action for objects #'.implode( ', ', $this->objectIds ) );
    }

    /**
     * Same test as {@link self::testRemovePendingSearchOnDeleteObject()}, with class based delayed indexing
     * @group issue_17932
     */
    public function testRemovePendingSearchWithClassBasedIndexing()
    {
        // Activate delayed indexing for folder content class only
        ezpINIHelper::setINISetting( 'site.ini', 'SearchSettings', 'DelayedIndexing', 'classbased' );
        ezpINIHelper::setINISetting( 'site.ini', 'SearchSettings', 'DelayedIndexingClassList', array( 'folder' ) );

        eZContentOperationCollection::deleteObject( $this->nodeIds );
        $pendingCount = $pendingCount = $this->countIndexPendingActionsByObjectId( $this->folder->object->attribute( 'id' ) );
        self::assertEquals( 0, $pendingCount, 'eZContentOperationCollection::deleteObject() must remove pending action for objects #'.implode( ', ', $this->objectIds ) );
    }

    /**
     * Same test as {@link self::testRemovePendingSearchOnDeleteObject()}, with several nodes for one object
     * Use case
     * --------
     * 1. If all nodes are removed, pending action must also be removed (case tested here)
     * 2. If NOT all nodes are removed (at least one node remaining for object), pending action must NOT be removed
     *
     * @group issue_17932
     */
    public function testRemovePendingSearchSeveralNodesForObject()
    {
        $this->folder->addNode( 43 );
        $folderObjectID = $this->folder->object->attribute( 'id' );

        $aNodeID = array();
        foreach ( $this->folder->nodes as $node )
        {
            $aNodeID[] = $node->attribute( 'node_id' );
        }

        eZContentOperationCollection::deleteObject( $aNodeID );
        $filterConds = array(
            'action'        => 'index_object',
            'param'         => $folderObjectID
        );
        $pendingCount = $this->countIndexPendingActionsByObjectId( $folderObjectID );
        self::assertEquals( 0, $pendingCount, "eZContentOperationCollection::deleteObject() must remove pending action for object #$folderObjectID as all nodes have been removed" );
    }

    /**
     * Same test as {@link self::testRemovePendingSearchSeveralNodesForObject()}, with not all nodes removed
     * Use case
     * --------
     * 1. If all nodes are removed, pending action must also be removed
     * 2. If NOT all nodes are removed (at least one node remaining for object), pending action must NOT be removed (case tested here)
     *
     * @group issue_17932
     */
    public function testRemovePendingSearchNotAllNodesRemoved()
    {
        $this->folder->addNode( 43 );
        $folderObjectID = $this->folder->object->attribute( 'id' );

        $aNodeID = array( $this->folder->nodes[0]->attribute( 'node_id' ) ); // Only delete the first node

        eZContentOperationCollection::deleteObject( $aNodeID );
        $pendingCount = $this->countIndexPendingActionsByObjectId( $folderObjectID );
        self::assertGreaterThan( 0, $pendingCount, "eZContentOperationCollection::deleteObject() must remove pending action for object #$folderObjectID as all nodes have been removed" );
    }
}
?>
