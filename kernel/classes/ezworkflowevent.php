<?php
//
// Definition of eZWorkflowEvent class
//
// Created on: <16-Apr-2002 11:08:14 amos>
//
// Copyright (C) 1999-2002 eZ systems as. All rights reserved.
//
// This source file is part of the eZ publish (tm) Open Source Content
// Management System.
//
// This file may be distributed and/or modified under the terms of the
// "GNU General Public License" version 2 as published by the Free
// Software Foundation and appearing in the file LICENSE.GPL included in
// the packaging of this file.
//
// Licencees holding valid "eZ publish professional licences" may use this
// file in accordance with the "eZ publish professional licence" Agreement
// provided with the Software.
//
// This file is provided AS IS with NO WARRANTY OF ANY KIND, INCLUDING
// THE WARRANTY OF DESIGN, MERCHANTABILITY AND FITNESS FOR A PARTICULAR
// PURPOSE.
//
// The "eZ publish professional licence" is available at
// http://ez.no/home/licences/professional/. For pricing of this licence
// please contact us via e-mail to licence@ez.no. Further contact
// information is available at http://ez.no/home/contact/.
//
// The "GNU General Public License" (GPL) is available at
// http://www.gnu.org/copyleft/gpl.html.
//
// Contact licence@ez.no if any conditions of this licencing isn't clear to
// you.
//

//!! eZKernel
//! The class eZWorkflowEvent does
/*!

*/

include_once( "lib/ezdb/classes/ezdb.php" );
include_once( "kernel/classes/ezpersistentobject.php" );
include_once( "kernel/classes/ezworkflowtype.php" );

class eZWorkflowEvent extends eZPersistentObject
{
    function eZWorkflowEvent( $row )
    {
        $this->eZPersistentObject( $row );
        $this->Content = null;

    }

    function &definition()
    {
        return array( "fields" => array( "id" => "ID",
                                         "version" => "Version",
                                         "workflow_id" => "WorkflowID",
                                         "workflow_type_string" => "TypeString",
                                         "description" => "Description",
                                         "data_int1" => "DataInt1",
                                         "data_int2" => "DataInt2",
                                         "data_int3" => "DataInt3",
                                         "data_int4" => "DataInt4",
                                         "data_text1" => "DataText1",
                                         "data_text2" => "DataText2",
                                         "data_text3" => "DataText3",
                                         "data_text4" => "DataText4",
                                         "placement" => "Placement" ),
                      "keys" => array( "id", "version" ),
                      "function_attributes" => array( "content" => "content" ),
                      "increment_key" => "id",
                      "sort" => array( "placement" => "asc" ),
                      "class_name" => "eZWorkflowEvent",
                      "name" => "ezworkflow_event" );
    }

    function &create( $workflow_id, $type_string )
    {
        $row = array(
            "id" => null,
            "version" => 1,
            "workflow_id" => $workflow_id,
            "workflow_type_string" => $type_string,
            "description" => "",
            "placement" => eZPersistentObject::newObjectOrder( eZWorkflowEvent::definition(),
                                                               "placement",
                                                               array( "version" => 1,
                                                                      "workflow_id" => $workflow_id ) ) );
        return new eZWorkflowEvent( $row );
    }

    function &fetch( $id, $asObject = true, $version = 0, $field_filters = null )
    {
        return eZPersistentObject::fetchObject( eZWorkflowEvent::definition(),
                                                $field_filters,
                                                array( "id" => $id,
                                                       "version" => $version ),
                                                $asObject );
    }

    function &fetchList( $asObject = true )
    {
        return eZPersistentObject::fetchObjectList( eZWorkflowEvent::definition(),
                                                    null, null, null, null,
                                                    $asObject );
    }

    function &fetchFilteredList( $cond, $asObject = true )
    {
        return eZPersistentObject::fetchObjectList( eZWorkflowEvent::definition(),
                                                    null, $cond, null, null,
                                                    $asObject );
    }

    /*!
     Moves the object down if $down is true, otherwise up.
     If object is at either top or bottom it is wrapped around.
    */
    function &move( $down, $params = null )
    {
        if ( is_array( $params ) )
        {
            $pos = $params["placement"];
            $wid = $params["workflow_id"];
            $version = $params["version"];
        }
        else
        {
            $pos = $this->Placement;
            $wid = $this->WorkflowID;
            $version = $this->Version;
        }
        return eZPersistentObject::reorderObject( eZWorkflowEvent::definition(),
                                                  array( "placement" => $pos ),
                                                  array( "workflow_id" => $wid,
                                                         "version" => $version ),
                                                  $down );
    }

    function attributes()
    {
        $eventType =& $this->eventType();
        return array_merge( eZPersistentObject::attributes(), array( "workflow_type" ), $eventType->typeFunctionalAttributes() );
    }

    function hasAttribute( $attr )
    {
        $eventType =& $this->eventType();
        return $attr == "workflow_type" or
            $attr == 'content' or
            eZPersistentObject::hasAttribute( $attr ) or
            in_array( $attr, $eventType->typeFunctionalAttributes() );
    }

    function &attribute( $attr )
    {
        $eventType =& $this->eventType();
        if ( $attr == "workflow_type" )
            return $this->eventType();
        else if ( $attr == "content" )
            return $this->content( );
        else if ( in_array( $attr, $eventType->typeFunctionalAttributes( ) ) )
        {
            return $eventType->attributeDecoder( $this, $attr );
        }else
            return eZPersistentObject::attribute( $attr );
    }

    function &eventType()
    {
        if ( ! isset (  $this->EventType ) )
        {
            $this->EventType =& eZWorkflowType::createType( $this->TypeString );
        }
        return $this->EventType;
    }

    /*!
     Returns the content for this event.

    */
    function content()
    {
        if ( $this->Content === null )
        {
            $eventType =& $this->eventType();
            $this->Content =& $eventType->workflowEventContent( $this );
        }

        return $this->Content;
    }

    /*!
     Sets the content for the current event
    */

    function setContent( $content )
    {
        $this->Content =& $content;
    }


    /*!
     Executes the custom HTTP action
    */
    function customHTTPAction( &$http, $action )
    {
        $eventType =& $this->eventType();
        $eventType->customWorkflowEventHTTPAction( $http, $action, $this );
    }

    function store()
    {
        $stored = eZPersistentObject::store();

        $eventType =& $this->eventType();
        $eventType->storeEventData( $this, $this->attribute( 'version' ) );

        return $stored;
    }

    /// \privatesection
    var $ID;
    var $Version;
    var $WorkflowID;
    var $TypeString;
    var $Description;
    var $Placement;
    var $DataInt1;
    var $DataInt2;
    var $DataInt3;
    var $DataInt3;
    var $DataText1;
    var $DataText2;
    var $DataText3;
    var $DataText4;
    var $Content;
}

?>
