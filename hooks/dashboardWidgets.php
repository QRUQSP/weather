<?php
//
// Description
// -----------
// This hooks returns the dashboard widgets available from this module.
// 
// Arguments
// ---------
// ciniki: 
// tnid:            The ID of the current tenant.
// 
// Returns
// ---------
// 
function qruqsp_weather_hooks_dashboardWidgets(&$ciniki, $tnid, $args) {

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
    $pressure_units = 'mbar';
    if( isset($ciniki['session']['user']['settings']['pressure_units']) 
        && $ciniki['session']['user']['settings']['pressure_units'] != '' 
        ) {
        $pressure_units = $ciniki['session']['user']['settings']['pressure_units'];
    }
    $pressure_scale = '1';
    if( isset($ciniki['session']['user']['settings']['pressure_scale']) 
        && $ciniki['session']['user']['settings']['pressure_scale'] != '' 
        ) {
        $pressure_scale = $ciniki['session']['user']['settings']['pressure_scale'];
    }

    //
    // Load the sensors available
    //
    $strsql = "SELECT sensors.id AS value, CONCAT_WS(' - ', stations.name, sensors.name) AS label, sensors.fields "
        . "FROM qruqsp_weather_stations AS stations "
        . "INNER JOIN qruqsp_weather_sensors AS sensors ON ("
            . "stations.id = sensors.station_id "
            . "AND sensors.tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
            . ") "
        . "WHERE stations.tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
        . "ORDER BY stations.name, sensors.name "
        . "";
    $temperature_sensors = array(0=>'None');
    $humidity_sensors = array(0=>'None');
    $pressure_sensors = array(0=>'None');
    $windspeed_sensors = array(0=>'None');
    $winddir_sensors = array(0=>'None');

    $rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'qruqsp.weather', 'sensors');
    if( $rc['stat'] != 'ok' ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'qruqsp.weather.56', 'msg'=>'Unable to load sensors', 'err'=>$rc['err']));
    }
    if( isset($rc['rows']) ) {  
        foreach($rc['rows'] as $sensor) {
            if( ($sensor['fields']&0x01) == 0x01 ) {
                $temperature_sensors[$sensor['value']] = $sensor['label'];
            }
            if( ($sensor['fields']&0x02) == 0x02 ) {
                $humidity_sensors[$sensor['value']] = $sensor['label'];
            }
            if( ($sensor['fields']&0x04) == 0x04 ) {
                $pressure_sensors[$sensor['value']] = $sensor['label'];
            }
            if( ($sensor['fields']&0x10) == 0x10 ) {
                $windspeed_sensors[$sensor['value']] = $sensor['label'];
            }
            if( ($sensor['fields']&0x20) == 0x20 ) {
                $winddir_sensors[$sensor['value']] = $sensor['label'];
            }
        }
    }

    $widgets = array(
        'qruqsp.weather.temp1' => array(
            'name' => 'Temperature',
            'category' => 'Weather',
            'options' => array(
                'name' => array(
                    'label' => 'Label', 
                    'type' => 'text',
                ),
                'units' => array(
                    'label' => 'Units', 
                    'type' => 'toggle',
                    'toggles' => array('celsius'=>'Celsius', 'fahrenheit'=>'Fahrenheit'), 
                    'default' => $temp_units,
                ),
                'tid' => array(
                    'label' => 'Temperature Sensor',
                    'type' => 'select', 
                    'options' => $temperature_sensors,
                ),
            ),
        ),
        'qruqsp.weather.temp2' => array(
            'name' => 'Temperature & Humidity',
            'category' => 'Weather',
            'options' => array(
                'name' => array(
                    'label' => 'Label', 
                    'type' => 'text',
                ),
                'units' => array(
                    'label' => 'Units', 
                    'type' => 'toggle',
                    'toggles' => array('celsius'=>'Celsius', 'fahrenheit'=>'Fahrenheit'), 
                    'default' => $temp_units,
                ),
                'tid' => array(
                    'label' => 'Temperature Sensor',
                    'type' => 'select', 
                    'options' => $temperature_sensors,
                ),
                'hid' => array(
                    'label' => 'Humidity Sensor',
                    'type' => 'select', 
                    'options' => $humidity_sensors,
                ),
            ),
        ),
        'qruqsp.weather.temp3' => array(
            'name' => 'Temperature & Humidity Tails',
            'category' => 'Weather',
            'options' => array(
                'name' => array(
                    'label' => 'Label', 
                    'type' => 'text',
                ),
                'units' => array(
                    'label' => 'Units', 
                    'type' => 'toggle',
                    'toggles' => array('celsius'=>'Celsius', 'fahrenheit'=>'Fahrenheit'), 
                    'default' => $temp_units,
                ),
                'line' => array(
                    'label' => 'Needle', 
                    'type' => 'toggle',
                    'toggles' => array('no'=>'No', 'yes'=>'Yes'), 
                    'default' => 'yes',
                ),
                'tid' => array(
                    'label' => 'Temperature Sensor',
                    'type' => 'select', 
                    'options' => $temperature_sensors,
                ),
                'hid' => array(
                    'label' => 'Humidity Sensor',
                    'type' => 'select', 
                    'options' => $humidity_sensors,
                ),
            ),
        ),
        'qruqsp.weather.baro1' => array(
            'name' => 'Barometer Dial',
            'category' => 'Weather',
            'options' => array(
                'name' => array(
                    'label' => 'Label', 
                    'type' => 'text',
                    'default' => 'Barometer',
                ),
                'units' => array(
                    'label' => 'Units', 
                    'type' => 'toggle',
                    'toggles' => array('mbar'=>'mbar/hPa', 'mmhg'=>'mmHg'), 
                    'default' => $pressure_units,
                ),
                'scale' => array(
                    'label' => 'Scale', 
                    'type' => 'toggle',
                    'toggles' => array('1'=>'Normal', '2'=>'Double'), 
                    'default' => $pressure_scale,
                ),
                'pid' => array(
                    'label' => 'Pressure Sensor',
                    'type' => 'select', 
                    'options' => $pressure_sensors,
                ),
            ),
        ),
        'qruqsp.weather.baro2' => array(
            'name' => 'Barometer Dial with Tail',
            'category' => 'Weather',
            'options' => array(
                'name' => array(
                    'label' => 'Label', 
                    'type' => 'text',
                    'default' => 'Barometer',
                ),
                'units' => array(
                    'label' => 'Units', 
                    'type' => 'toggle',
                    'toggles' => array('mbar'=>'mbar/hPa', 'mmhg'=>'mmHg'), 
                    'default' => $pressure_units,
                ),
                'scale' => array(
                    'label' => 'Scale', 
                    'type' => 'toggle',
                    'toggles' => array('1'=>'Normal', '2'=>'Double'), 
                    'default' => $pressure_scale,
                ),
                'line' => array(
                    'label' => 'Needle', 
                    'type' => 'toggle',
                    'toggles' => array('no'=>'No', 'yes'=>'Yes'), 
                    'default' => 'yes',
                ),
                'pid' => array(
                    'label' => 'Pressure Sensor',
                    'type' => 'select', 
                    'options' => $pressure_sensors,
                ),
            ),
        ),
        'qruqsp.weather.wind1' => array(
            'name' => 'Wind Speed & Direction',
            'category' => 'Weather',
            'options' => array(
                'name' => array(
                    'label' => 'Label', 
                    'type' => 'text',
                    'default' => 'Barometer',
                ),
                'units' => array(
                    'label' => 'Units', 
                    'type' => 'toggle',
                    'toggles' => array('kph'=>'kph', 'mph'=>'mph'), 
                    'default' => $windspeed_units,
                ),
                'sid' => array(
                    'label' => 'Wind Speed Sensor',
                    'type' => 'select', 
                    'options' => $windspeed_sensors,
                ),
                'did' => array(
                    'label' => 'Wind Direction Sensor',
                    'type' => 'select', 
                    'options' => $winddir_sensors,
                ),
            ),
        ),
    );

    return array('stat'=>'ok', 'widgets'=>$widgets);
}
?>
