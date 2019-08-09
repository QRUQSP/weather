<?php
//
// Description
// -----------
// This hooks returns the panels available from this module.
// 
// Arguments
// ---------
// ciniki: 
// tnid:            The ID of the current tenant.
// 
// Returns
// ---------
// 
function qruqsp_weather_hooks_dashboardPanels(&$ciniki, $tnid, $args) {

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

    $dial_types = array(
        'temphum1' => 'Temperature', 
        'wind1' => 'Wind Direction & Speed',
        'baro1' => 'Barometric Pressure',
        );
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

    //
    // Setup the various option arrays
    //
    $optionArrays = array();
    for($i = 1; $i <= 12; $i++) {
        $optionArrays[$i] = array();
    }
    for($i = 1; $i <= 12; $i++) {
        for($j = $i; $j <= 12; $j++) {
            $optionArrays[$j]["o{$i}name"] = array('label'=>"Dial {$i} Name", 'type'=>'text');
            $optionArrays[$j]["o{$i}type"] = array('label'=>"Type", 'type'=>'select', 
                'options'=>$dial_types, 
                'onchange'=>"M.qruqsp_dashboard_main.panel.refreshFields(['o{$i}t','o{$i}h','o{$i}ws','o{$i}wd']);",
                );
            $optionArrays[$j]["o{$i}tu"] = array('label'=>"Units", 'type'=>'toggle', 
                'toggles'=>array('celsius'=>'Celsuis', 'fahrenheit'=>'Fahrenheit'), 'default'=>$temp_units, 
                'vfield'=>"o{$i}type", 'vshow'=>array('temphum1'), 'vdefault'=>'yes',
                );
            $optionArrays[$j]["o{$i}t"] = array('label'=>"Temperature Sensor", 'type'=>'select', 
                'options'=>$temperature_sensors, 'vfield'=>"o{$i}type", 'vshow'=>array('temphum1'), 'vdefault'=>'yes',
                );
            $optionArrays[$j]["o{$i}h"] = array('label'=>"Humidity Sensor", 'type'=>'select', 
                'options'=>$humidity_sensors, 'vfield'=>"o{$i}type", 'vshow'=>array('temphum1'), 'vdefault'=>'yes',
                );
            $optionArrays[$j]["o{$i}wu"] = array('label'=>"Units", 'type'=>'toggle', 
                'toggles'=>array('kph'=>'kph', 'mph'=>'mph'), 'default'=>$windspeed_units, 
                'vfield'=>"o{$i}type", 'vshow'=>array('wind1'), 'vdefault'=>'yes',
                );
            $optionArrays[$j]["o{$i}ws"] = array('label'=>"Wind Speed Sensor", 'type'=>'select', 
                'options'=>$windspeed_sensors, 'vfield'=>"o{$i}type", 'vshow'=>array('wind1'), 'vdefault'=>'no',
                );
            $optionArrays[$j]["o{$i}wd"] = array('label'=>"Wind Direction Sensor", 'type'=>'select', 
                'options'=>$winddir_sensors, 'vfield'=>"o{$i}type", 'vshow'=>array('wind1'), 'vdefault'=>'no',
                );
        }
    }

    //
    // Create the list of available panels
    //
    $panels = array(
        'qruqsp.weather.orbitDials.2' => array(
            'value' => 'qruqsp.weather.orbitDials.2',
            'name' => 'Weather - Twin Orbit Dials',
            'options' => $optionArrays[2],
            ),
        'qruqsp.weather.orbitDials.4' => array(
            'value' => 'qruqsp.weather.orbitDials.4',
            'name' => 'Weather - Quad Orbit Dials',
            'options' => $optionArrays[4],
            ),
        'qruqsp.weather.orbitDials.6' => array(
            'value' => 'qruqsp.weather.orbitDials.6',
            'name' => 'Weather - 6 Orbit Dials',
            'options' => $optionArrays[6],
            ),
        'qruqsp.weather.orbitDials.12' => array(
            'value' => 'qruqsp.weather.orbitDials.12',
            'name' => 'Weather - 12 Orbit Dials',
            'options' => $optionArrays[12],
            ),
        );


    return array('stat'=>'ok', 'panels'=>$panels);
}
?>
