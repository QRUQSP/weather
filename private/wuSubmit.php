<?php
//
// Description
// -----------
// Send a submission to weather underground.
//
// Note: Jun 14, 2020: The weather underground system can have a lag time between when the information
// is submitted and it shows on the weatherunderground.com/dashboard/pws/IWASAG2
// The https submit to their php script returns immediately with successful response
// but the website will show data from 3-5 minutes ago.
// On June 12, 2020 their input script had issues and the connection was timing out after 60 seconds.
// This required the change to make this code run from a separate script, scripts/beacon.php
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
function qruqsp_weather_wuSubmit(&$ciniki, $tnid, $station_id) {

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
        . "stations.wu_id, "
        . "stations.wu_key, "
        . "stations.wu_celsius_sensor_id, "
        . "stations.wu_humidity_sensor_id, "
        . "stations.wu_millibars_sensor_id, "
        . "stations.wu_wind_kph_sensor_id, "
        . "stations.wu_wind_deg_sensor_id, "
        . "stations.wu_rain_mm_sensor_id, "
        . "IFNULL(stations.wu_last_submit, '') AS wu_last_submit, "
        . "IFNULL(stations.wu_frequency, '') AS wu_frequency, "
        . "sensors.id AS sensor_id, "
        . "sensors.name AS sensor_name, "
        . "sensors.fields AS sensor_fields, "
        . "IFNULL(data.sample_date, '') as sample_date, "
        . "IFNULL(TIMESTAMPDIFF(SECOND, data.sample_date, UTC_TIMESTAMP()), 999) AS sample_date_age, "
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
                . "stations.wu_celsius_sensor_id = sensors.id "
                . "OR stations.wu_humidity_sensor_id = sensors.id "
                . "OR stations.wu_millibars_sensor_id = sensors.id "
                . "OR stations.wu_wind_kph_sensor_id = sensors.id "
                . "OR stations.wu_wind_deg_sensor_id = sensors.id "
                . "OR stations.wu_rain_mm_sensor_id = sensors.id "
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
            'fields'=>array('id', 'name', 'flags', 'latitude', 'longitude', 'altitude', 'wu_id', 'wu_key',
                'wu_celsius_sensor_id', 'wu_humidity_sensor_id', 'wu_millibars_sensor_id', 'wu_wind_kph_sensor_id', 
                'wu_wind_deg_sensor_id', 'wu_rain_mm_sensor_id', 'wu_last_submit', 'wu_frequency')),
        array('container'=>'sensors', 'fname'=>'sensor_id',
            'fields'=>array('id'=>'sensor_id', 'name'=>'sensor_name', 'fields'=>'sensor_fields', 
                'sample_date', 'sample_date_age', 'celsius', 'humidity', 'millibars', 'wind_kph', 'wind_deg', 'rain_mm'),
            ),
        ));
    if( $rc['stat'] != 'ok' ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'qruqsp.weather.47', 'msg'=>'Unable to load stations', 'err'=>$rc['err']));
    }
    if( !isset($rc['stations'][0]) ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'qruqsp.weather.43', 'msg'=>'Unable to find requested station'));
    }
    $station = $rc['stations'][0];

    //
    // Check the date of last beacon
    //
    $last_dt = new DateTime($station['wu_last_submit'], new DateTimezone('UTC'));
    $last_dt->add(new DateInterval('PT' . $station['wu_frequency'] . 'M'));
    if( $last_dt > $dt ) {
        error_log('submitted within last ' . $station['wu_frequency'] . ' minutes');
    }

    //
    // Check if station is setup for weather underground submissions
    //
    if( ($station['flags']&0x04) == 0 ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'qruqsp.weather.44', 'msg'=>'Station not enabled for Weather Underground Beacon'));
    }

    //
    // Check if station is setup for weather underground with id and key
    //
    if( trim($station['wu_id']) == '' || trim($station['wu_key']) == '' ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'qruqsp.weather.21', 'msg'=>'No Weather Underground ID or Key, unable to submit'));
    }

    //
    // Check for sensors configured
    //
    if( !isset($station['sensors']) || count($station['sensors']) < 1 ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'qruqsp.weather.39', 'msg'=>'No Weather Underground sensors found'));
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
    // Build the URL to submit weather
    // Instructions used: https://feedback.weather.com/customer/en/portal/articles/2924682-pws-upload-protocol?b_id=17298
    // and: https://projects.raspberrypi.org/en/projects/uploading-weather-data-to-weather-underground/4
    //
    $submit_url = "https://weatherstation.wunderground.com/weatherstation/updateweatherstation.php?"
        . "ID=" . $station['wu_id'] 
        . "&PASSWORD=" . $station['wu_key']
        . "&dateutc=now"
        . "&softwaretype=qruqsp.org"
        . "&action=updateraw"
        . "";
    
    //
    // Add the sensors settings
    //
    $sensor_args = '';
    foreach($station['sensors'] as $sensor) {
        //
        // Skip sensors that have old data (older than 3 minutes)
        //
        if( $sensor['sample_date_age'] > 180 ) {
            continue;
        }
        if( $sensor['id'] == $station['wu_celsius_sensor_id'] ) {
            $sensor_args .= sprintf("&tempf=%.1f", (($sensor['celsius'] * (9/5)) + 32));
        }
        if( $sensor['id'] == $station['wu_humidity_sensor_id'] && $sensor['humidity'] > 0 ) {
            if( $sensor['humidity'] >= 100 ) {
                $sensor_args .= "&humidity=100";
            } else {
                $sensor_args .= sprintf("&humidity=%d", $sensor['humidity']);
            }
        }
        if( $sensor['id'] == $station['wu_millibars_sensor_id'] ) {
            $sensor_args .= sprintf("&baromin=%.02f", ($sensor['millibars'] * 0.029530));
        }
        if( $sensor['id'] == $station['wu_wind_kph_sensor_id'] ) {
            // Convert to mph
            $sensor_args .= sprintf("&windspeedmph=%.1f", ($sensor['wind_kph']/1.60934));
        } 
        if( $sensor['id'] == $station['wu_wind_deg_sensor_id'] ) {
            //
            // APRS north is 360, 0 means no value
            //
            $sensor_args .= sprintf("&winddir=%d", $sensor['wind_deg']);
        }
        if( $sensor['id'] == $station['wu_rain_mm_sensor_id'] ) {
//FIXME:            $sensor_args .= sprintf("&%03d", $sensor['rain_mm']);
        }
    }

    if( $sensor_args == '' ) {
        return array('stat'=>'ok');
    }

    $submit_url .= $sensor_args;

    //
    // Submit to weather underground
    //
    $wu_response = file_get_contents($submit_url);

    //
    // Check if request should be logged
    //
    if( isset($ciniki['config']['qruqsp.weather']['wu.logging']) 
        && $ciniki['config']['qruqsp.weather']['wu.logging'] == 'yes' 
        && isset($ciniki['config']['qruqsp.core']['log_dir'])
        && $ciniki['config']['qruqsp.core']['log_dir'] != '' 
        ) {
        $log_dir = $ciniki['config']['qruqsp.core']['log_dir'] . '/qruqsp.weather';
        if( !file_exists($log_dir) ) {
            mkdir($log_dir);
        }

        $dt = new DateTime('now', new DateTimezone('UTC'));
        file_put_contents($log_dir . '/wu.' . $dt->format('Y-m') . '.log',  
            '[' . $dt->format('d/M/Y:H:i:s O') . '] ' . trim($wu_response) . ' ' . $submit_url . "\n",
            FILE_APPEND);
    }

    //
    // Update the database with wu_last_submit time
    //
    if( strncmp($wu_response, 'success', 7) == 0 ) {
        ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'objectUpdate');
        $rc = ciniki_core_objectUpdate($ciniki, $tnid, 'qruqsp.weather.station', $station['id'], array(
            'wu_last_submit'=>$dt->format('Y-m-d H:i:s')), 0x04);
        if( $rc['stat'] != 'ok' ) {
            return array('stat'=>'fail', 'err'=>array('code'=>'qruqsp.weather.33', 'msg'=>'Unable to update the station'));
        }
    } else {
        return array('stat'=>'fail', 'err'=>array('code'=>'qruqsp.weather.50', 'msg'=>'Unable to submit to weather underground: ' . $wu_response));
    }
    
    return array('stat'=>'ok');
}
?>
