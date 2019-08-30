<?php
//
// Description
// -----------
// This hook returns content for a panel to be added to the dashboard.
// 
// Arguments
// ---------
// ciniki: 
// tnid:            The ID of the current tenant.
// 
// Returns
// ---------
// 
function qruqsp_weather_hooks_updateObjectID(&$ciniki, $tnid, $args) {

    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'objectUpdate');

    //
    // Make sure the module is enabled
    //
    if( !isset($ciniki['tenant']['modules']['qruqsp.weather']) ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'qruqsp.weather.31', 'msg'=>'Weather not enabled', 'err'=>$rc['err']));
    }

    //  
    // Check to make sure object and description have been passed
    //  
    if( !isset($args['object']) ) { 
        return array('stat'=>'fail', 'err'=>array('code'=>'qruqsp.weather.64', 'msg'=>"No object specified."));
    }   
    if( !isset($args['old_object_id']) ) { 
        return array('stat'=>'fail', 'err'=>array('code'=>'qruqsp.weather.65', 'msg'=>"No old object specified."));
    }   
    if( !isset($args['new_object_id']) ) { 
        return array('stat'=>'fail', 'err'=>array('code'=>'qruqsp.weather.34', 'msg'=>"No new object specified."));
    }   

    //
    // Check for any sensors that reference the object
    //
    $strsql = "SELECT id, object, object_id "
        . "FROM qruqsp_weather_sensors "
        . "WHERE object = '" . ciniki_core_dbQuote($ciniki, $args['object']) . "' "
        . "AND object_id = '" . ciniki_core_dbQuote($ciniki, $args['old_object_id']) . "' "
        . "AND tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
        . "";
    $rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'qruqsp.weather', 'item');
    if( $rc['stat'] != 'ok' ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'qruqsp.weather.67', 'msg'=>'Unable to load sensors', 'err'=>$rc['err']));
    }
    if( isset($rc['rows']) ) {
        $sensors = $rc['rows'];
        foreach($sensors as $sensor) {
            //
            //  Update the sensor with new object ID
            //
            $rc = ciniki_core_objectUpdate($ciniki, $tnid, 'qruqsp.weather.sensor', $sensor['id'], array(
                'object_id' => $args['new_object_id'],
                ), 0x04);
            if( $rc['stat'] != 'ok' ) {
                return array('stat'=>'fail', 'err'=>array('code'=>'qruqsp.weather.35', 'msg'=>'Unable to update the sensor'));
            }
        }
    }
    
    return array('stat'=>'ok');
}
?>
