<?php
//
// Description
// ===========
//
// Arguments
// ---------
//
// Returns
// -------
//
function qruqsp_weather_stationUpdate(&$ciniki) {
    //
    // Find all the required and optional arguments
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'prepareArgs');
    $rc = ciniki_core_prepareArgs($ciniki, 'no', array(
        'tnid'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Tenant'),
        'station_id'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Station'),
        'name'=>array('required'=>'no', 'blank'=>'no', 'name'=>'Name'),
        'flags'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Options'),
        'latitude'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Latitude'),
        'longitude'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Longitude'),
        'altitude'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Altitude'),
        'aprs_celsius_sensor_id'=>array('required'=>'no', 'blank'=>'no', 'name'=>'APRS Celsius Sensor'),
        'aprs_humidity_sensor_id'=>array('required'=>'no', 'blank'=>'no', 'name'=>'APRS Humidity Sensor'),
        'aprs_millibars_sensor_id'=>array('required'=>'no', 'blank'=>'no', 'name'=>'APRS Millibars Sensor'),
        'aprs_wind_kph_sensor_id'=>array('required'=>'no', 'blank'=>'no', 'name'=>'APRS Wind Speed Sensor'),
        'aprs_wind_deg_sensor_id'=>array('required'=>'no', 'blank'=>'no', 'name'=>'APRS Wind Direction Sensor'),
        'aprs_rain_mm_sensor_id'=>array('required'=>'no', 'blank'=>'no', 'name'=>'APRS Rainfall Sensor'),
        'aprs_frequency'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'APRS Frequency'),
        'wu_id'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Weather Underground Station ID'),
        'wu_key'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Weather Underground Station Key'),
        'wu_celsius_sensor_id'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Weather Underground Celsius Sensor'),
        'wu_humidity_sensor_id'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Weather Underground Humidity Sensor'),
        'wu_millibars_sensor_id'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Weather Underground Barometer Sensor'),
        'wu_wind_kph_sensor_id'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Weather Underground Wind Speed Sensor'),
        'wu_wind_deg_sensor_id'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Weather Underground Wind Direction Sensor'),
        'wu_rain_mm_sensor_id'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Weather Underground Rainfall Sensor'),
        'wu_frequency'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Weather Underground Submission Frequency'),
        ));
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    $args = $rc['args'];

    //
    // Make sure this module is activated, and
    // check permission to run this function for this tenant
    //
    ciniki_core_loadMethod($ciniki, 'qruqsp', 'weather', 'private', 'checkAccess');
    $rc = qruqsp_weather_checkAccess($ciniki, $args['tnid'], 'qruqsp.weather.stationUpdate');
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }

    //
    // Start transaction
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbTransactionStart');
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbTransactionRollback');
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbTransactionCommit');
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbAddModuleHistory');
    $rc = ciniki_core_dbTransactionStart($ciniki, 'qruqsp.weather');
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }

    //
    // Update the Station in the database
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'objectUpdate');
    $rc = ciniki_core_objectUpdate($ciniki, $args['tnid'], 'qruqsp.weather.station', $args['station_id'], $args, 0x04);
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

    //
    // Update the web index if enabled
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'hookExec');
    ciniki_core_hookExec($ciniki, $args['tnid'], 'ciniki', 'web', 'indexObject', array('object'=>'qruqsp.weather.station', 'object_id'=>$args['station_id']));

    //
    // Update cron to make sure check is running
    //
    ciniki_core_loadMethod($ciniki, 'qruqsp', 'weather', 'private', 'cronCheck');
    $rc = qruqsp_weather_cronCheck($ciniki, $args['tnid']);
    if( $rc['stat'] != 'ok' ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'qruqsp.weather.40', 'msg'=>'Unable to start beacon', 'err'=>$rc['err']));
    }

    return array('stat'=>'ok');
}
?>
