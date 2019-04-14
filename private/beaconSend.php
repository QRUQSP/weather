<?php
//
// Description
// -----------
// Send a weather beacon over aprs.
// 
// Arguments
// ---------
// ciniki: 
// tnid:            The ID of the current tenant.
// message_id:      The ID of the message to send.
// 
// Returns
// ---------
// 
function qruqsp_weather_beaconSend(&$ciniki, $tnid, $station_id) {

    $dt = new DateTime('now', new DateTimezone('UTC'));

    //
    // Load the station
    //
    $strsql = "SELECT stations.id, "
        . "stations.name, "
        . "stations.flags, "
        . "stations.latitude, "
        . "stations.longitude, "
        . "stations.altitude, "
        . "stations.aprs_celsius_sensor_id, "
        . "stations.aprs_humidity_sensor_id, "
        . "stations.aprs_millibars_sensor_id, "
        . "stations.aprs_wind_kph_sensor_id, "
        . "stations.aprs_wind_deg_sensor_id, "
        . "stations.aprs_rain_mm_sensor_id, "
        . "IFNULL(stations.aprs_last_beacon, '') AS aprs_last_beacon, "
        . "sensors.id AS sensor_id, "
        . "sensors.name AS sensor_name, "
        . "sensors.fields AS sensor_fields, "
        . "IFNULL(data.sample_date, '') as sample_date, "
        . "IFNULL(data.celsius, '') as celsius, "
        . "IFNULL(data.humidity, '') as humidity, "
        . "IFNULL(data.millibars, '') as millibars, "
        . "IFNULL(data.wind_kph, '') as wind_kph, "
        . "IFNULL(data.wind_deg, '') as wind_deg, "
        . "IFNULL(data.rain_mm, '') as rain_mm "
        . "FROM qruqsp_weather_stations AS stations "
        . "LEFT JOIN qruqsp_weather_sensors AS sensors ON ("
            . "stations.id = sensors.station_id "
            . "AND ("
                . "stations.aprs_celsius_sensor_id = sensors.id "
                . "OR stations.aprs_humidity_sensor_id = sensors.id "
                . "OR stations.aprs_millibars_sensor_id = sensors.id "
                . "OR stations.aprs_wind_kph_sensor_id = sensors.id "
                . "OR stations.aprs_wind_deg_sensor_id = sensors.id "
                . "OR stations.aprs_rain_mm_sensor_id = sensors.id "
                . ") "
            . ") "
        . "LEFT JOIN qruqsp_weather_sensor_data AS data ON ("
            . "sensors.id = data.sensor_id "
            . "AND sensors.last_sample_date = data.sample_date "
            . "AND data.tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
            . ") "
        . "WHERE stations.tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
        . "AND stations.id = '" . ciniki_core_dbQuote($ciniki, $station_id) . "' "
        . "";
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbHashQueryArrayTree');
    $rc = ciniki_core_dbHashQueryArrayTree($ciniki, $strsql, 'qruqsp.weather', array(
        array('container'=>'stations', 'fname'=>'id', 
            'fields'=>array('id', 'name', 'flags', 'latitude', 'longitude', 'altitude', 'aprs_celsius_sensor_id', 
                'aprs_humidity_sensor_id', 'aprs_millibars_sensor_id', 'aprs_wind_kph_sensor_id', 
                'aprs_wind_deg_sensor_id', 'aprs_rain_mm_sensor_id', 'aprs_last_beacon')),
        array('container'=>'sensors', 'fname'=>'sensor_id',
            'fields'=>array('id'=>'sensor_id', 'name'=>'sensor_name', 'fields'=>'sensor_fields', 
                'sample_date', 'celsius', 'humidity', 'millibars', 'wind_kph', 'wind_deg', 'rain_mm'),
            ),
        ));
    if( $rc['stat'] != 'ok' ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'qruqsp.weather.30', 'msg'=>'Unable to load stations', 'err'=>$rc['err']));
    }
    if( !isset($rc['stations'][0]) ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'qruqsp.weather.20', 'msg'=>'Unable to find requested station'));
    }
    $station = $rc['stations'][0];

    //
    // Check the date of last beacon
    //
    $last_dt = new DateTime($station['aprs_last_beacon'], new DateTimezone('UTC'));
    $last_dt->add(new DateInterval('PT5M'));
    if( $last_dt > $dt ) {
        error_log('beaconed within last 5 minutes');
    }

    //
    // Check the station position is specified
    //
    if( $station['latitude'] == '' || $station['latitude'] == 0 || $station['longitude'] == '' || $station['longitude'] == 0 ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'qruqsp.weather.29', 'msg'=>'No GPS position specified for station.'));
    }
   
    //
    // Check if station is setup for beaconing/digipeating
    //
    if( ($station['flags']&0x02) == 0 ) {
//FIXME:        return array('stat'=>'fail', 'err'=>array('code'=>'qruqsp.weather.21', 'msg'=>'Station not enabled for APRS Beacon'));
    }

    //
    // Check for sensors configured
    //
    if( !isset($station['sensors']) || count($station['sensors']) < 1 ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'qruqsp.weather.23', 'msg'=>'No APRS sensors found'));
    }

    //
    // Load tenant information
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'tenants', 'hooks', 'tenantDetails');
    $rc = ciniki_tenants_hooks_tenantDetails($ciniki, $tnid, array());
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    if( isset($rc['details']) && is_array($rc['details']) ) {
        $tenant_details = $rc['details'];
    } else {
        $tenant_details = array();
    }

    //
    // Create the packet
    //
    $packet = array(
        'addrs' => array(
            'APPQRU',
            $tenant_details['name'],
            'WIDE2-1',
            ),
        'control' => 0x03, 
        'protocol' => 0xf0,
        'data' => '',
        );

    //
    // Add the timestamp of the weather data
    //
    $packet['data'] = '@' . $dt->format('His') . 'z';

    //
    // Add the position of the station
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'gpsDDtoDDM');
    $rc = ciniki_core_gpsDDtoDDM($ciniki, $tnid, $station);
    if( $rc['stat'] != 'ok' ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'qruqsp.weather.22', 'msg'=>'', 'err'=>$rc['err']));
    }
    if( isset($rc['latitude']) && isset($rc['longitude']) && $rc['latitude'] != '' && $rc['longitude'] != '' ) {
        $packet['data'] .= $rc['latitude'] . '/' . $rc['longitude'];
    }
    $packet['data'] .= '_';

    //
    // FIXME: Check how old sensor data is before beaconing
    //

    //
    // FIXME: Setup Settings for beacon timeframe interval
    //

    //
    // Add the sensors settings
    //
    $wind_mph = '   ';
    $wind_deg = '000';
    foreach($station['sensors'] as $sensor) {
        if( $sensor['id'] == $station['aprs_celsius_sensor_id'] ) {
            $temp = sprintf("t%03d", (($sensor['celsius'] * (9/5)) + 32));
        }
        if( $sensor['id'] == $station['aprs_humidity_sensor_id'] && $sensor['humidity'] > 0 ) {
            if( $sensor['humidity'] > 99 ) {
                $humidity = 'h99';
            } else {
                $humidity = sprintf("h%02d", $sensor['humidity']);
            }
        }
        if( $sensor['id'] == $station['aprs_millibars_sensor_id'] ) {
            $millibars = sprintf("b%03.02f", ($sensor['millibars']/10));
        }
        if( $sensor['id'] == $station['aprs_wind_kph_sensor_id'] ) {
            $wind_mph = sprintf("%03d", ($sensor['wind_kph']/1.60934));
        } 
        if( $sensor['id'] == $station['aprs_wind_deg_sensor_id'] ) {
            //
            // APRS north is 360, 0 means no value
            //
            if( $sensor['wind_deg'] == 0 ) {
                $sensor['wind_deg'] = 360;
            }
            $wind_deg = sprintf("%03d", $sensor['wind_deg']);
        }
        if( $sensor['id'] == $station['aprs_rain_mm_sensor_id'] ) {
            $rain_mm = sprintf("%03d", $sensor['rain_mm']);
        }
    }

    $packet['data'] .= $wind_mph . '/' . $wind_deg;

    if( isset($temp) ) {
        $packet['data'] .= $temp;
    }
    if( isset($humidity) ) {
        $packet['data'] .= $humidity;
    }
    if( isset($millibars) ) {
        $packet['data'] .= $millibars;
    }
    if( isset($rain_mm) ) {
        $packet['data'] .= $rain_mm;
    }

    //
    // Add QRUSP to end of weather packet to mark as a QRUQSP weather packet
    //
    $packet['data'] .= 'QSP';

    error_log($packet['data']);

    //
    // Send the packet
    //
    ciniki_core_loadMethod($ciniki, 'qruqsp', 'tnc', 'hooks', 'packetSend');
    $rc = qruqsp_tnc_hooks_packetSend($ciniki, $tnid, array('packet'=>$packet));
    if( $rc['stat'] != 'ok' ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'qruqsp.weather.18', 'msg'=>'Error sending message', 'err'=>$rc['err']));
    }

    return array('stat'=>'ok');
}
?>
