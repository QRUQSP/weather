<?php
//
// Description
// ===========
// This method will return all the information about an station.
//
// Arguments
// ---------
// api_key:
// auth_token:
// tnid:         The ID of the tenant the station is attached to.
// station_id:          The ID of the station to get the details for.
//
// Returns
// -------
//
function qruqsp_weather_graphData($ciniki) {
    //
    // Find all the required and optional arguments
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'prepareArgs');
    $rc = ciniki_core_prepareArgs($ciniki, 'no', array(
        'tnid'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Tenant'),
        'sensor_ids'=>array('required'=>'yes', 'blank'=>'no', 'type'=>'idlist', 'name'=>'Sensors'),
        'graph'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Graph Type'),
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
    $rc = qruqsp_weather_checkAccess($ciniki, $args['tnid'], 'qruqsp.weather.graphData');
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

    ciniki_core_loadMethod($ciniki, 'ciniki', 'users', 'private', 'datetimeFormat');
    $datetime_format = ciniki_users_datetimeFormat($ciniki, 'php');

    $dt = new DateTime('now', new DateTimezone('UTC'));
    $dt->sub(new DateInterval('P1D'));

    //
    // Load the sensors and current values
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbQuoteIDs');
    $strsql = "SELECT sensors.id, "
        . "sensors.name, "
        . "sensors.fields, "
        . "data.sample_date AS date, ";
    if( $args['graph'] == 'temperature' ) {
        $strsql .= "data.celsius AS value ";
    } elseif( $args['graph'] == 'humidity' ) {
        $strsql .= "data.humidity AS value ";
    } elseif( $args['graph'] == 'pressure' ) {
        $strsql .= "data.millibars AS value ";
    } elseif( $args['graph'] == 'windspeed' ) {
        $strsql .= "data.wind_kph AS value ";
    } elseif( $args['graph'] == 'winddirection' ) {
        $strsql .= "data.wind_deg AS value ";
    } elseif( $args['graph'] == 'rainfall' ) {
        $strsql .= "data.rain_mm AS value ";
    }
    $strsql .= "FROM qruqsp_weather_sensors AS sensors "
        . "LEFT JOIN qruqsp_weather_sensor_data AS data ON ("
            . "sensors.id = data.sensor_id "
            . "AND data.sample_date > '" . ciniki_core_dbQuote($ciniki, $dt->format('Y-m-d H:i:s')) . "' "
            . "AND data.tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
            . ") "
        . "WHERE sensors.id IN (" . ciniki_core_dbQuoteIDs($ciniki, $args['sensor_ids']) . ") "
        . "AND sensors.tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
        . "ORDER BY sensors.id, data.sample_date ASC "
        . "";
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbHashQueryArrayTree');
    $rc = ciniki_core_dbHashQueryArrayTree($ciniki, $strsql, 'qruqsp.weather', array(
        array('container'=>'sensors', 'fname'=>'id', 'fields'=>array('id', 'name')),
        array('container'=>'data', 'fname'=>'date', 'fields'=>array('date', 'value')),
        ));
    if( $rc['stat'] != 'ok' ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'qruqsp.weather.23', 'msg'=>'Unable to load sensors', 'err'=>$rc['err']));
    }
    $sensors = isset($rc['sensors']) ? $rc['sensors'] : array();
    $min = 0;
    $max = 0;
    foreach($sensors as $sid => $sensor) {
        if( !isset($sensor['data']) ) {
            unset($sensors[$sid]);
        } else {
            foreach($sensor['data'] as $d) {
                if( $d['value'] != null && $d['value'] > $max ) {
                    $max = $d['value'];
                }
                if( $d['value'] != null && $d['value'] < $min ) {
                    $min = $d['value'];
                }
            }
        }
    }

    return array('stat'=>'ok', 'sensors'=>$sensors, 'min'=>$min, 'max'=>$max);
}
?>
