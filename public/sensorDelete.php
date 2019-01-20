<?php
//
// Description
// -----------
// This method will delete an sensor.
//
// Arguments
// ---------
// api_key:
// auth_token:
// tnid:            The ID of the tenant the sensor is attached to.
// sensor_id:            The ID of the sensor to be removed.
//
// Returns
// -------
//
function qruqsp_weather_sensorDelete(&$ciniki) {
    //
    // Find all the required and optional arguments
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'prepareArgs');
    $rc = ciniki_core_prepareArgs($ciniki, 'no', array(
        'tnid'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Tenant'),
        'sensor_id'=>array('required'=>'yes', 'blank'=>'yes', 'name'=>'Sensor'),
        ));
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    $args = $rc['args'];

    //
    // Check access to tnid as owner
    //
    ciniki_core_loadMethod($ciniki, 'qruqsp', 'weather', 'private', 'checkAccess');
    $rc = qruqsp_weather_checkAccess($ciniki, $args['tnid'], 'ciniki.weather.sensorDelete');
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }

    //
    // Get the current settings for the sensor
    //
    $strsql = "SELECT id, uuid "
        . "FROM qruqsp_weather_sensors "
        . "WHERE tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
        . "AND id = '" . ciniki_core_dbQuote($ciniki, $args['sensor_id']) . "' "
        . "";
    $rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'ciniki.weather', 'sensor');
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    if( !isset($rc['sensor']) ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'qruqsp.weather.24', 'msg'=>'Sensor does not exist.'));
    }
    $sensor = $rc['sensor'];

    //
    // Check for any dependencies before deleting
    //

    //
    // Check if any modules are currently using this object
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'objectCheckUsed');
    $rc = ciniki_core_objectCheckUsed($ciniki, $args['tnid'], 'qruqsp.weather.sensor', $args['sensor_id']);
    if( $rc['stat'] != 'ok' ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'qruqsp.weather.25', 'msg'=>'Unable to check if the sensor is still being used.', 'err'=>$rc['err']));
    }
    if( $rc['used'] != 'no' ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'qruqsp.weather.26', 'msg'=>'The sensor is still in use. ' . $rc['msg']));
    }

    //
    // Start transaction
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbTransactionStart');
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbTransactionRollback');
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbTransactionCommit');
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbDelete');
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'objectDelete');
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbAddModuleHistory');
    $rc = ciniki_core_dbTransactionStart($ciniki, 'qruqsp.weather');
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }

    //
    // Remove the sensor data
    //
    $strsql = "DELETE FROM qruqsp_weather_sensor_data "
        . "WHERE sensor_id = '" . ciniki_core_dbQuote($ciniki, $args['sensor_id']) . "' "
        . "AND tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
        . "";
    $rc = ciniki_core_dbDelete($ciniki, $strsql, 'qruqsp.weather');
    if( $rc['stat'] != 'ok' ) {
        ciniki_core_dbTransactionRollback($ciniki, 'qruqsp.weather');
        return $rc;
    }

    //
    // Remove the sensor
    //
    $rc = ciniki_core_objectDelete($ciniki, $args['tnid'], 'qruqsp.weather.sensor', $args['sensor_id'], $sensor['uuid'], 0x04);
    if( $rc['stat'] != 'ok' ) {
        ciniki_core_dbTransactionRollback($ciniki, 'qruqsp.weather');
        return $rc;
    }

    //
    // Commit the transaction
    //
    $rc = ciniki_core_dbTransactionCommit($ciniki, 'qruqsp.weather');
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }

    //
    // Update the last_change date in the tenant modules
    // Ignore the result, as we don't want to stop user updates if this fails.
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'tenants', 'private', 'updateModuleChangeDate');
    ciniki_tenants_updateModuleChangeDate($ciniki, $args['tnid'], 'qruqsp', 'weather');

    return array('stat'=>'ok');
}
?>
