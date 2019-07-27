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
function qruqsp_weather_stationGet($ciniki) {
    //
    // Find all the required and optional arguments
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'prepareArgs');
    $rc = ciniki_core_prepareArgs($ciniki, 'no', array(
        'tnid'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Tenant'),
        'station_id'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Station'),
        'sensors'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Sensors'),
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
    $rc = qruqsp_weather_checkAccess($ciniki, $args['tnid'], 'qruqsp.weather.stationGet');
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }

    ciniki_core_loadMethod($ciniki, 'ciniki', 'users', 'private', 'convertTemperature');
    ciniki_core_loadMethod($ciniki, 'ciniki', 'users', 'private', 'convertWindSpeed');

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

    //
    // Grab the users settings
    //
    $temp_units = 'celsius';
    if( isset($ciniki['session']['user']['settings']['temperature_units']) 
        && $ciniki['session']['user']['settings']['temperature_units'] != '' 
        ) {
        $temp_units = $ciniki['session']['user']['settings']['temperature_units'];
    }
    $windspeed_units = 'kph';
    if( isset($ciniki['session']['user']['settings']['windspeed_units']) 
        && $ciniki['session']['user']['settings']['windspeed_units'] != '' 
        ) {
        $windspeed_units = $ciniki['session']['user']['settings']['windspeed_units'];
    }
    
    //
    // Return default for new Station
    //
    if( $args['station_id'] == 0 ) {
        $station = array('id'=>0,
            'name'=>'',
            'flags'=>'0',
            'latitude'=>'',
            'longitude'=>'',
            'altitude'=>'',
            'aprs_celsius_sensor_id'=>'',
            'aprs_humidity_sensor_id'=>'',
            'aprs_millibars_sensor_id'=>'',
            'aprs_wind_kph_sensor_id'=>'',
            'aprs_wind_deg_sensor_id'=>'',
            'aprs_rain_mm_sensor_id'=>'',
            'aprs_frequency'=>'30',
        );
    }

    //
    // Get the details for an existing Station
    //
    else {
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
            . "qruqsp_weather_stations.aprs_wind_deg_sensor_id, "
            . "qruqsp_weather_stations.aprs_rain_mm_sensor_id, "
            . "qruqsp_weather_stations.aprs_last_beacon, "
            . "qruqsp_weather_stations.aprs_frequency, "
            . "qruqsp_weather_stations.wu_id, "
            . "qruqsp_weather_stations.wu_key, "
            . "qruqsp_weather_stations.wu_celsius_sensor_id, " . "qruqsp_weather_stations.wu_humidity_sensor_id, "
            . "qruqsp_weather_stations.wu_millibars_sensor_id, "
            . "qruqsp_weather_stations.wu_wind_kph_sensor_id, "
            . "qruqsp_weather_stations.wu_wind_deg_sensor_id, "
            . "qruqsp_weather_stations.wu_rain_mm_sensor_id, "
            . "qruqsp_weather_stations.wu_last_submit, "
            . "qruqsp_weather_stations.wu_frequency "
            . "FROM qruqsp_weather_stations "
            . "WHERE qruqsp_weather_stations.tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
            . "AND qruqsp_weather_stations.id = '" . ciniki_core_dbQuote($ciniki, $args['station_id']) . "' "
            . "";
        ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbHashQueryArrayTree');
        $rc = ciniki_core_dbHashQueryArrayTree($ciniki, $strsql, 'qruqsp.weather', array(
            array('container'=>'stations', 'fname'=>'id', 
                'fields'=>array('name', 'flags', 'latitude', 'longitude', 'altitude', 
                    'aprs_celsius_sensor_id', 'aprs_humidity_sensor_id', 'aprs_millibars_sensor_id', 
                    'aprs_wind_kph_sensor_id', 'aprs_wind_deg_sensor_id', 'aprs_rain_mm_sensor_id',
                    'aprs_last_beacon', 'aprs_frequency',
                    'wu_id', 'wu_key', 'wu_celsius_sensor_id', 'wu_humidity_sensor_id', 'wu_millibars_sensor_id', 
                    'wu_wind_kph_sensor_id', 'wu_wind_deg_sensor_id', 'wu_rain_mm_sensor_id',
                    'wu_last_submit', 'wu_frequency'),
                ),
            ));
        if( $rc['stat'] != 'ok' ) {
            return array('stat'=>'fail', 'err'=>array('code'=>'qruqsp.weather.7', 'msg'=>'Station not found', 'err'=>$rc['err']));
        }
        if( !isset($rc['stations'][0]) ) {
            return array('stat'=>'fail', 'err'=>array('code'=>'qruqsp.weather.8', 'msg'=>'Unable to find Station'));
        }
        $station = $rc['stations'][0];
        $station['temperature_units'] = $temp_units;
        $station['windspeed_units'] = $windspeed_units;

        $station['details'] = array(
            array('label'=>'Name', 'value'=>$station['name']),
            array('label'=>'Latitude', 'value'=>$station['latitude']),
            array('label'=>'Longitude', 'value'=>$station['longitude']),
            array('label'=>'Altitude', 'value'=>$station['altitude']),
            );

        //
        // Load the sensors and current values
        //
        $strsql = "SELECT sensors.id, "
            . "sensors.name, "
            . "sensors.flags, "
            . "sensors.fields, "
//            . "IFNULL(DATE_FORMAT(data.sample_date, '" . ciniki_core_dbQuote($ciniki, $datetime_format) . "'), '') AS sample_date, "
            . "IFNULL(data.sample_date, '') AS sample_date, "
            . "IFNULL(data.celsius, '') AS temperature, "
            . "IFNULL(data.humidity, '') AS humidity, "
            . "IFNULL(data.millibars, '') AS millibars, "
            . "IFNULL(data.wind_kph, '') AS windspeed, "
            . "IFNULL(data.wind_deg, '') AS wind_deg, "
            . "IFNULL(data.rain_mm, '') AS rain_mm "
            . "FROM qruqsp_weather_sensors AS sensors "
            . "LEFT JOIN qruqsp_weather_sensor_data AS data ON ("
                . "sensors.id = data.sensor_id "
                . "AND sensors.last_sample_date = data.sample_date "
                . "AND data.tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
                . ") "
            . "WHERE sensors.station_id = '" . ciniki_core_dbQuote($ciniki, $args['station_id']) . "' "
            . "AND sensors.tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
/*            . "AND data.sample_date = ("
                . "SELECT MAX(sample_date) "
                . "FROM qruqsp_weather_sensor_data "
                . "WHERE sensor_id = data.sensor_id "
                . ") " */
            . "ORDER BY sensors.name ";
        ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbHashQueryArrayTree');
        $rc = ciniki_core_dbHashQueryArrayTree($ciniki, $strsql, 'qruqsp.weather', array(
            array('container'=>'sensors', 'fname'=>'id', 
                'fields'=>array('id', 'name', 'flags', 'fields', 'sample_date', 
                    'temperature', 'humidity', 'millibars', 'windspeed', 'wind_deg', 'rain_mm'),
                'utctotz'=>array('sample_date'=>array('timezone'=>$intl_timezone, 'format'=>$datetime_format)),
                ),
            ));
        if( $rc['stat'] != 'ok' ) {
            return array('stat'=>'fail', 'err'=>array('code'=>'qruqsp.weather.23', 'msg'=>'Unable to load sensors', 'err'=>$rc['err']));
        }
        $station['sensors'] = isset($rc['sensors']) ? $rc['sensors'] : array();
        //
        // Convert units if required
        //
        foreach($station['sensors'] as $sid => $sensor) {
            //
            // Run through user preferences
            //
            $station['sensors'][$sid]['temperature'] = sprintf("%.02f", ciniki_users_convertTemperature($ciniki, $sensor['temperature']));
            $station['sensors'][$sid]['windspeed'] = sprintf("%.02f", ciniki_users_convertWindSpeed($ciniki, $sensor['windspeed']));
        }
    }
    
    return array('stat'=>'ok', 'station'=>$station);
}
?>
