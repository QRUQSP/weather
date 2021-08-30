<?php
//
// Description
// -----------
// This function returns the list of objects for the module.
//
// Arguments
// ---------
//
// Returns
// -------
//
function qruqsp_weather_objects(&$ciniki) {
    //
    // Build the objects
    //
    $objects = array();

    $objects['station'] = array(
        'name' => 'Station',
        'sync' => 'yes',
        'o_name' => 'station',
        'o_container' => 'stations',
        'table' => 'qruqsp_weather_stations',
        'fields' => array(
            'name' => array('name'=>'Name'),
            'flags' => array('name'=>'Options', 'default'=>'0'),
            'latitude' => array('name'=>'Latitude', 'default'=>''),
            'longitude' => array('name'=>'Longitude', 'default'=>''),
            'altitude' => array('name'=>'Altitude', 'default'=>''),
            'aprs_celsius_sensor_id' => array('name'=>'APRS Celsius Sensor', 'ref'=>'qruqsp.weather.sensor', 'default'=>'0'),
            'aprs_humidity_sensor_id' => array('name'=>'APRS Humidity Sensor', 'ref'=>'qruqsp.weather.sensor', 'default'=>'0'),
            'aprs_millibars_sensor_id' => array('name'=>'APRS Barometer Sensor', 'ref'=>'qruqsp.weather.sensor', 'default'=>'0'),
            'aprs_wind_kph_sensor_id' => array('name'=>'APRS Wind Speed Sensor', 'ref'=>'qruqsp.weather.sensor', 'default'=>'0'),
            'aprs_wind_deg_sensor_id' => array('name'=>'APRS Wind Direction Sensor', 'ref'=>'qruqsp.weather.sensor', 'default'=>'0'),
            'aprs_rain_mm_sensor_id' => array('name'=>'APRS Rainfall Sensor', 'ref'=>'qruqsp.weather.sensor', 'default'=>'0'),
            'aprs_last_beacon' => array('name'=>'APRS Last Beacon', 'default'=>''),
            'aprs_frequency' => array('name'=>'APRS Beacon Frequency', 'default'=>'30'),
            'wu_id' => array('name'=>'Weather Underground ID', 'default'=>''),
            'wu_key' => array('name'=>'Weather Underground Key', 'default'=>''),
            'wu_celsius_sensor_id' => array('name'=>'Weather Underground Celsius Sensor', 'ref'=>'qruqsp.weather.sensor', 'default'=>'0'),
            'wu_humidity_sensor_id' => array('name'=>'Weather Underground Humidity Sensor', 'ref'=>'qruqsp.weather.sensor', 'default'=>'0'),
            'wu_millibars_sensor_id' => array('name'=>'Weather Underground Barometer Sensor', 'ref'=>'qruqsp.weather.sensor', 'default'=>'0'),
            'wu_wind_kph_sensor_id' => array('name'=>'Weather Underground Wind Speed Sensor', 'ref'=>'qruqsp.weather.sensor', 'default'=>'0'),
            'wu_wind_deg_sensor_id' => array('name'=>'Weather Underground Wind Direction Sensor', 'ref'=>'qruqsp.weather.sensor', 'default'=>'0'),
            'wu_rain_mm_sensor_id' => array('name'=>'Weather Underground Rainfall Sensor', 'ref'=>'qruqsp.weather.sensor', 'default'=>'0'),
            'wu_last_submit' => array('name'=>'Weather Underground Last Submission Date', 'default'=>'0'),
            'wu_frequency' => array('name'=>'Weather Underground Submission Frequency', 'default'=>'0'),
            ),
        'history_table' => 'qruqsp_weather_history',
        );
    $objects['sensor'] = array(
        'name' => 'Sensor',
        'sync' => 'yes',
        'o_name' => 'sensor',
        'o_container' => 'sensors',
        'table' => 'qruqsp_weather_sensors',
        'fields' => array(
            'station_id' => array('name'=>'Station', 'ref'=>'qruqsp.weather.station'),
            'object' => array('name'=>'Sensor Object'),
            'object_id' => array('name'=>'Sensor Object ID'),
            'name' => array('name'=>'Name'), 
            'sequence' => array('name'=>'Order', 'default'=>'1'),
            'flags' => array('name'=>'Options', 'default'=>'0'),
            'fields' => array('name'=>'Fields', 'default'=>'0'),
            'rain_mm_offset' => array('name'=>'Rain Offset', 'default'=>'0'),
            'rain_mm_last' => array('name'=>'Rain mm Last', 'default'=>'0'),
            'last_sample_date' => array('name'=>'Last Sample Date', 'default'=>''),
            ),
        'history_table' => 'qruqsp_weather_history',
        );
    //
    return array('stat'=>'ok', 'objects'=>$objects);
}
?>
