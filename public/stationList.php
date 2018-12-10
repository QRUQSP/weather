<?php
//
// Description
// -----------
// This method will return the list of Stations for a tenant.
//
// Arguments
// ---------
// api_key:
// auth_token:
// tnid:        The ID of the tenant to get Station for.
//
// Returns
// -------
//
function qruqsp_weather_stationList($ciniki) {
    //
    // Find all the required and optional arguments
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'prepareArgs');
    $rc = ciniki_core_prepareArgs($ciniki, 'no', array(
        'tnid'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Tenant'),
        ));
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    $args = $rc['args'];

    //
    // Check access to tnid as owner, or sys admin.
    //
    ciniki_core_loadMethod($ciniki, 'qruqsp', 'weather', 'private', 'checkAccess');
    $rc = qruqsp_weather_checkAccess($ciniki, $args['tnid'], 'qruqsp.weather.stationList');
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }

    //
    // Get the list of stations
    //
    $strsql = "SELECT qruqsp_weather_stations.id, "
        . "qruqsp_weather_stations.name, "
        . "qruqsp_weather_stations.flags, "
        . "qruqsp_weather_stations.latitude, "
        . "qruqsp_weather_stations.longitude, "
        . "qruqsp_weather_stations.altitude, "
        . "qruqsp_weather_stations.aprs_celsius_sensor_id, "
        . "qruqsp_weather_stations.aprs_humidity_sensor_id, "
        . "qruqsp_weather_stations.aprs_millibars_sensor_id, "
        . "qruqsp_weather_stations.aprs_wind_kph_sensor_id, "
        . "qruqsp_weather_stations.aprs_wind_degrees_sensor_id, "
        . "qruqsp_weather_stations.aprs_rain_mm_sensor_id "
        . "FROM qruqsp_weather_stations "
        . "WHERE qruqsp_weather_stations.tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
        . "";
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbHashQueryArrayTree');
    $rc = ciniki_core_dbHashQueryArrayTree($ciniki, $strsql, 'qruqsp.weather', array(
        array('container'=>'stations', 'fname'=>'id', 
            'fields'=>array('id', 'name', 'flags', 'latitude', 'longitude', 'altitude', 'aprs_celsius_sensor_id', 'aprs_humidity_sensor_id', 'aprs_millibars_sensor_id', 'aprs_wind_kph_sensor_id', 'aprs_wind_degrees_sensor_id', 'aprs_rain_mm_sensor_id')),
        ));
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    if( isset($rc['stations']) ) {
        $stations = $rc['stations'];
        $station_ids = array();
        foreach($stations as $iid => $station) {
            $station_ids[] = $station['id'];
        }
    } else {
        $stations = array();
        $station_ids = array();
    }

    return array('stat'=>'ok', 'stations'=>$stations, 'nplist'=>$station_ids);
}
?>
