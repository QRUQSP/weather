<?php
//
// Description
// ===========
// This method will return all the information about an sensor.
//
// Arguments
// ---------
// api_key:
// auth_token:
// tnid:         The ID of the tenant the sensor is attached to.
// sensor_id:          The ID of the sensor to get the details for.
//
// Returns
// -------
//
function qruqsp_weather_sensorGet($ciniki) {
    //
    // Find all the required and optional arguments
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'prepareArgs');
    $rc = ciniki_core_prepareArgs($ciniki, 'no', array(
        'tnid'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Tenant'),
        'sensor_id'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Sensor'),
        'stations'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Stations'),
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
    $rc = qruqsp_weather_checkAccess($ciniki, $args['tnid'], 'qruqsp.weather.sensorGet');
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }

    //
    // Load tenant settings
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'tenants', 'private', 'intlSettings');
    $rc = ciniki_tenants_intlSettings($ciniki, $args['tnid']);
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    $intl_timezone = $rc['settings']['intl-default-timezone'];
    $intl_currency_fmt = numfmt_create($rc['settings']['intl-default-locale'], NumberFormatter::CURRENCY);
    $intl_currency = $rc['settings']['intl-default-currency'];

    ciniki_core_loadMethod($ciniki, 'ciniki', 'users', 'private', 'dateFormat');
    $date_format = ciniki_users_dateFormat($ciniki, 'php');

    //
    // Return default for new Sensor
    //
    if( $args['sensor_id'] == 0 ) {
        $sensor = array('id'=>0,
            'station_id'=>'',
            'object'=>'',
            'object_id'=>'',
            'name'=>'',
            'sequence'=>'1',
            'flags'=>'0',
            'fields'=>'0',
            'rain_mm_offset'=>'0',
            'rain_mm_last'=>'0',
        );
    }

    //
    // Get the details for an existing Sensor
    //
    else {
        $strsql = "SELECT qruqsp_weather_sensors.id, "
            . "qruqsp_weather_sensors.station_id, "
            . "qruqsp_weather_sensors.object, "
            . "qruqsp_weather_sensors.object_id, "
            . "qruqsp_weather_sensors.name, "
            . "qruqsp_weather_sensors.sequence, "
            . "qruqsp_weather_sensors.flags, "
            . "qruqsp_weather_sensors.fields, "
            . "qruqsp_weather_sensors.rain_mm_offset, "
            . "qruqsp_weather_sensors.rain_mm_last "
            . "FROM qruqsp_weather_sensors "
            . "WHERE qruqsp_weather_sensors.tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
            . "AND qruqsp_weather_sensors.id = '" . ciniki_core_dbQuote($ciniki, $args['sensor_id']) . "' "
            . "";
        ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbHashQueryArrayTree');
        $rc = ciniki_core_dbHashQueryArrayTree($ciniki, $strsql, 'qruqsp.weather', array(
            array('container'=>'sensors', 'fname'=>'id', 
                'fields'=>array('station_id', 'object', 'object_id', 'name', 'sequence', 'flags', 'fields', 'rain_mm_offset', 'rain_mm_last'),
                ),
            ));
        if( $rc['stat'] != 'ok' ) {
            return array('stat'=>'fail', 'err'=>array('code'=>'qruqsp.weather.27', 'msg'=>'Sensor not found', 'err'=>$rc['err']));
        }
        if( !isset($rc['sensors'][0]) ) {
            return array('stat'=>'fail', 'err'=>array('code'=>'qruqsp.weather.28', 'msg'=>'Unable to find Sensor'));
        }
        $sensor = $rc['sensors'][0];
    }

    $rsp = array('stat'=>'ok', 'sensor'=>$sensor);

    if( isset($args['stations']) && $args['stations'] == 'yes' ) {
        $strsql = "SELECT qruqsp_weather_stations.id, "
            . "qruqsp_weather_stations.name "
            . "FROM qruqsp_weather_stations "
            . "WHERE qruqsp_weather_stations.tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
            . "";
        ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbHashQueryArrayTree');
        $rc = ciniki_core_dbHashQueryArrayTree($ciniki, $strsql, 'qruqsp.weather', array(
            array('container'=>'stations', 'fname'=>'id', 
                'fields'=>array('id', 'name')),
            ));
        if( $rc['stat'] != 'ok' ) {
            return $rc;
        }
        $rsp['stations'] = isset($rc['stations']) ? $rc['stations'] : array();
    }

    return $rsp;
}
?>
