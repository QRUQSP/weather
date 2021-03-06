<?php
//
// Description
// -----------
// This function returns the settings for the module and the main menu items and settings menu items
//
// Arguments
// ---------
// ciniki:
// tnid:
// args: The arguments for the hook
//
// Returns
// -------
//
function qruqsp_weather_hooks_weatherDataReceived(&$ciniki, $tnid, $args) {
    
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'objectAdd');
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'objectUpdate');
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbInsert');

    //
    // Make sure required fields are specified
    //
    if( !isset($args['object']) || $args['object'] == '' ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'qruqsp.weather.9', 'msg'=>'No object specified'));
    }
    if( !isset($args['object_id']) || $args['object'] == '' ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'qruqsp.weather.10', 'msg'=>'No object ID specified'));
    }
    if( !isset($args['sample_date']) || $args['sample_date'] == '' ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'qruqsp.weather.17', 'msg'=>'No sample date specified'));
    }
/*
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
 */   
    //
    // Check if request should be logged
    //
    if( isset($ciniki['config']['qruqsp.weather']['received.logging']) 
        && $ciniki['config']['qruqsp.weather']['received.logging'] == 'yes' 
        && isset($ciniki['config']['qruqsp.core']['log_dir'])
        && $ciniki['config']['qruqsp.core']['log_dir'] != '' 
        ) {
        $log_dir = $ciniki['config']['qruqsp.core']['log_dir'] . '/qruqsp.weather';
        if( !file_exists($log_dir) ) {
            mkdir($log_dir);
        }
        $dt = new DateTime('now', new DateTimezone('UTC'));
        file_put_contents($log_dir . '/received.' . $dt->format('Y-m') . '.log',  
            '[' . $dt->format('d/M/Y:H:i:s O') . '] ' . json_encode($args) . "\n",
            FILE_APPEND);
    }

    //
    // Parse the time in UTC and normalize to current minute.
    //
    $dt = new DateTime($args['sample_date'], new DateTimezone('UTC'));
    $dt->setTime($dt->format('H'), $dt->format('i'), 0);

    //
    // Check for which fields are supplied
    //
    $fields = 0;
    $fields_sql1 = '';
    $fields_sql2 = '';
    $update_sql = '';
    if( isset($args['celsius']) ) {
        $fields |= 0x01;
        $fields_sql1 .= ', celsius';
        $fields_sql2 .= ", '" . ciniki_core_dbQuote($ciniki, $args['celsius']) . "' ";
        $update_sql .= ($update_sql != '' ? ', ' : '') . "celsius = '" . ciniki_core_dbQuote($ciniki, $args['celsius']) . "'";
    }
    if( isset($args['humidity']) ) {
        $fields |= 0x02;
        $fields_sql1 .= ', humidity';
        $fields_sql2 .= ", '" . ciniki_core_dbQuote($ciniki, $args['humidity']) . "' ";
        $update_sql .= ($update_sql != '' ? ', ' : '') . "humidity = '" . ciniki_core_dbQuote($ciniki, $args['humidity']) . "'";
    }
    if( isset($args['millibars']) ) {
        $fields |= 0x04;
        $fields_sql1 .= ', millibars';
        $fields_sql2 .= ", '" . ciniki_core_dbQuote($ciniki, $args['millibars']) . "' ";
        $update_sql .= ($update_sql != '' ? ', ' : '') . "millibars = '" . ciniki_core_dbQuote($ciniki, $args['millibars']) . "'";
    }
    if( isset($args['wind_kph']) ) {
        $fields |= 0x10;
        $fields_sql1 .= ', wind_kph';
        $fields_sql2 .= ", '" . ciniki_core_dbQuote($ciniki, $args['wind_kph']) . "' ";
        $update_sql .= ($update_sql != '' ? ', ' : '') . "wind_kph = '" . ciniki_core_dbQuote($ciniki, $args['wind_kph']) . "'";
    }
/*    if( isset($args['wind_avg_km_h']) ) {
        $fields |= 0x10;
        $fields_sql1 .= ', wind_kph';
        $fields_sql2 .= ", '" . ciniki_core_dbQuote($ciniki, $args['wind_avg_km_h']) . "' ";
        $update_sql .= ($update_sql != '' ? ', ' : '') . "wind_kph = '" . ciniki_core_dbQuote($ciniki, $args['wind_avg_km_h']) . "'";
    } */
    if( isset($args['wind_deg']) ) {
        $fields |= 0x20;
        $fields_sql1 .= ', wind_deg';
        $fields_sql2 .= ", '" . ciniki_core_dbQuote($ciniki, $args['wind_deg']) . "' ";
        $update_sql .= ($update_sql != '' ? ', ' : '') . "wind_deg = '" . ciniki_core_dbQuote($ciniki, $args['wind_deg']) . "'";
    }
    if( isset($args['rain_mm']) ) {
        $args['rain_mm'] = round($args['rain_mm'], 2);
        $fields |= 0x40;
        $fields_sql1 .= ', rain_mm';
    }
        
    //
    // Check if the sensor exists in the database
    //
    $strsql = "SELECT id, station_id, name, flags, fields, rain_mm_offset, rain_mm_last, last_sample_date "
        . "FROM qruqsp_weather_sensors "
        . "WHERE object = '" . ciniki_core_dbQuote($ciniki, $args['object']) . "' "
        . "AND object_id = '" . ciniki_core_dbQuote($ciniki, $args['object_id']) . "' "
        . "AND tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
        . "";
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbHashQuery');
    $rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'qruqsp.weather', 'sensor');
    if( $rc['stat'] != 'ok' ) {
        ciniki_core_dbTransactionRollback($ciniki, 'qruqsp.weather');
        return array('stat'=>'fail', 'err'=>array('code'=>'qruqsp.weather.11', 'msg'=>'Unable to load sensor', 'err'=>$rc['err']));
    }
    if( isset($rc['sensor']) ) {
        $sensor = $rc['sensor'];
        //
        // Check if sensor fields need to be updated
        // Acurite 5n1 sends 2 packets, one with half of the sensors, second with other half
        // not all contained in a single packet
        //
        // If the name was changed in the other module, it should also be changed here.
        //
        $update_args = array();
        if( $fields != $sensor['fields'] ) {
            $update_args['fields'] = ($fields | $sensor['fields']);
        }
        if( isset($args['sensor']) && $args['sensor'] != $sensor['name'] ) {
            $update_args['name'] = $args['sensor'];
        }
        if( isset($args['rain_mm']) ) {
            //
            // The rain guage has rolled over, the offset should be updated with last reading
            //
            if( $args['rain_mm'] < $sensor['rain_mm_last'] ) {
                $update_args['rain_mm_offset'] = $sensor['rain_mm_offset'] + $sensor['rain_mm_last'];
            } elseif( $args['rain_mm'] > $sensor['rain_mm_last'] ) {
                $update_args['rain_mm_last'] = $args['rain_mm'];
            }
        }
        if( $dt->format('Y-m-d H:i:s') != $sensor['last_sample_date'] ) {
            $update_args['last_sample_date'] = $dt->format('Y-m-d H:i:s');
        }
    } else {
        //
        // Setup sensor 
        //
        $sensor = array(
            'station_id' => 0,
            'object' => $args['object'],
            'object_id' => $args['object_id'],
            'name' => (isset($args['sensor']) ? $args['sensor'] : ''),
            'flags' => 0,
            'fields' => $fields,
            'rain_mm_offset' => 0,
            'rain_mm_last' => (isset($args['rain_mm']) ? $args['rain_mm'] : 0),
            'last_sample_date' => $dt->format('Y-m-d H:i:s'),
            );

        //
        // Check if station exists
        //
        if( isset($args['station']) && $args['station'] != '' ) {
            $strsql = "SELECT id, name, flags "
                . "FROM qruqsp_weather_stations "
                . "WHERE name = '" . ciniki_core_dbQuote($ciniki, $args['station']) . "' "
                . "AND tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
                . "";
            $rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'qruqsp.weather', 'station');
            if( $rc['stat'] != 'ok' ) {
                ciniki_core_dbTransactionRollback($ciniki, 'qruqsp.weather');
                return array('stat'=>'fail', 'err'=>array('code'=>'qruqsp.weather.12', 'msg'=>'Unable to check for station', 'err'=>$rc['err']));
            }
            if( isset($rc['station']) ) {
                $sensor['station_id'] = $rc['station']['id'];
            } else {
                //
                // Add the station
                //
                $station = array(
                    'name' => $args['station'],
                    'flags' => (isset($args['flags']) ? $args['flags'] : '0'),
                    'latitude' => (isset($args['latitude']) ? $args['latitude'] : '0'),
                    'longitude' => (isset($args['longitude']) ? $args['longitude'] : '0'),
                    'altitude' => (isset($args['altitude']) ? $args['altitude'] : '0'),
                    );
                $rc = ciniki_core_objectAdd($ciniki, $tnid, 'qruqsp.weather.station', $station, 0x04);
                if( $rc['stat'] != 'ok' ) {
                    ciniki_core_dbTransactionRollback($ciniki, 'qruqsp.weather');
                    return array('stat'=>'fail', 'err'=>array('code'=>'qruqsp.weather.13', 'msg'=>'Unable to add station', 'err'=>$rc['err']));
                }
                $sensor['station_id'] = $rc['id'];
            }
        } else {
            ciniki_core_dbTransactionRollback($ciniki, 'qruqsp.weather');
            return array('stat'=>'fail', 'err'=>array('code'=>'qruqsp.weather.66', 'msg'=>'No station specified'));
        }

        //
        // Add the sensor
        //
        $rc = ciniki_core_objectAdd($ciniki, $tnid, 'qruqsp.weather.sensor', $sensor, 0x04);
        if( $rc['stat'] != 'ok' ) {
            ciniki_core_dbTransactionRollback($ciniki, 'qruqsp.weather');
            return array('stat'=>'fail', 'err'=>array('code'=>'qruqsp.weather.14', 'msg'=>'Unable to add sensor', 'err'=>$rc['err']));
        }
        $sensor['id'] = $rc['id'];
    }

    //
    // Check if sensor is to be ignored
    //
    if( ($sensor['flags']&0x01) == 0x01 ) {
        ciniki_core_dbTransactionCommit($ciniki, 'qruqsp.weather');
        return array('stat'=>'ok');
    }

    //
    // Rain calculation needs to happen after sensor load for offset
    //
    if( isset($args['rain_mm']) ) {
        $fields_sql2 .= ", '" . ciniki_core_dbQuote($ciniki, ($args['rain_mm'] + $sensor['rain_mm_offset'])) . "' ";
        $update_sql .= ($update_sql != '' ? ', ' : '') . "rain_mm = '" . ciniki_core_dbQuote($ciniki, $args['rain_mm']) . "'";
    }

    //
    // Add the data
    // Because of acurite split data packet, updates have to be careful not to overwrite data from previous packet for same minute
    //
    $strsql = "INSERT INTO qruqsp_weather_sensor_data (tnid, sensor_id, sample_date, latitude, longitude, altitude"
        . $fields_sql1 
        . ") VALUES ("
        . "'" . ciniki_core_dbQuote($ciniki, $tnid) . "', "
        . "'" . ciniki_core_dbQuote($ciniki, $sensor['id']) . "', "
        . "'" . ciniki_core_dbQuote($ciniki, $dt->format('Y-m-d H:i:s')) . "', "
        . "'" . ciniki_core_dbQuote($ciniki, $args['latitude']) . "', "
        . "'" . ciniki_core_dbQuote($ciniki, $args['longitude']) . "', "
        . "'" . ciniki_core_dbQuote($ciniki, (isset($args['altitude']) ? $args['altitude'] : 0)) . "' "
        . $fields_sql2
        . ") ON DUPLICATE KEY UPDATE "
        . $update_sql
        . " ";
    $rc = ciniki_core_dbInsert($ciniki, $strsql, 'qruqsp.weather');
    if( $rc['stat'] != 'ok' && $rc['stat'] != 'exists' ) {
        ciniki_core_dbTransactionRollback($ciniki, 'qruqsp.weather');
        return array('stat'=>'fail', 'err'=>array('code'=>'qruqsp.weather.16', 'msg'=>'Unable to add data sample', 'err'=>$rc['err']));
    }

    //
    // Check if there was an update to the sensor
    //
    if( isset($update_args) && count($update_args) > 0 ) {
        $rc = ciniki_core_objectUpdate($ciniki, $tnid, 'qruqsp.weather.sensor', $sensor['id'], $update_args, 0x04);
        if( $rc['stat'] != 'ok' ) {
            ciniki_core_dbTransactionRollback($ciniki, 'qruqsp.weather');
            return array('stat'=>'fail', 'err'=>array('code'=>'qruqsp.weather.15', 'msg'=>'Unable to update sensor', 'err'=>$rc['err']));
        }
    }

    //
    // Signal the beacon script if running
    //
    exec('ps ax | grep "php.*qruqsp-mods/weather/scripts/beacon.php" |grep -v grep', $pids);
    foreach($pids as $line) {
        if( preg_match("/^\s*([0-9]+)\s.*php.*qruqsp-mods\/weather\/scripts\/beacon.php/", $line, $m) ) {
            posix_kill($m[1], SIGUSR1);
        }
    }


/*
    //
    // Commit the transaction before we try beaconing incase of any timeouts
    //
    $rc = ciniki_core_dbTransactionCommit($ciniki, 'qruqsp.weather');
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }

    //
    // Disconnect databases in child to stop handles getting messed up,
    // they will be auto reconnect at next call
    //
    foreach($ciniki['databases'] as $did => $db) {
        if( isset($ciniki['databases'][$did]['connection']) ) {
            mysqli_close($ciniki['databases'][$did]['connection']);
        }
        $ciniki['databases'][$did] = array();
    }

    //
    // Fork the process incase the beaconing gets hung up or has long timeout when connecting to other resources
    //
    $pid = pcntl_fork();

    if( $pid > 0 ) {
        return array('stat'=>'ok');
    } else {

        //
        // Check if station should be beaconed
        //
        $strsql = "SELECT flags, "
            . "IFNULL(TIMESTAMPDIFF(SECOND, aprs_last_beacon, UTC_TIMESTAMP()), 999) AS last_beacon_age, "
            . "IFNULL(TIMESTAMPDIFF(SECOND, wu_last_submit, UTC_TIMESTAMP()), 999) AS wu_last_submit_age, "
            . "aprs_frequency, wu_frequency "
            . "FROM qruqsp_weather_stations "
            . "WHERE id = '" . ciniki_core_dbQuote($ciniki, $sensor['station_id']) . "' "
            . "AND tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
            . "";
        $rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'qruqsp.weather', 'station');
        if( $rc['stat'] != 'ok' ) {
            error_log('WARN: Unable to beacon weather: ' . print_r($rc['err'], true));
        }
        if( isset($rc['station']) ) {
            $station = $rc['station'];
          
            //
            // Make sure beaconing is turned on, and enough time since last beacon
            // Apply a random number of seconds to the aprs_frequency to make sure beacon
            // are not always sent at the same seconds offset.
            //
            if( ($station['flags']&0x02) == 0x02 
                && $station['last_beacon_age'] > (($station['aprs_frequency'] * 60) + RAND(5,55))
                && $station['aprs_frequency'] > 0
                ) {
                ciniki_core_loadMethod($ciniki, 'qruqsp', 'weather', 'private', 'beaconSend');
                $rc = qruqsp_weather_beaconSend($ciniki, $tnid, $sensor['station_id']); 
                if( $rc['stat'] != 'ok' ) {
                    error_log('WARN: Unable to beacon weather: ' . print_r($rc['err'], true));
                }
            }

            //
            // Make sure submit to weather underground is enabled, and has been longer than frequency
            //
            if( ($station['flags']&0x04) == 0x04 
                && $station['wu_frequency'] > 0
                && $station['wu_last_submit_age'] > ($station['wu_frequency'] * 60)
                ) {
                ciniki_core_loadMethod($ciniki, 'qruqsp', 'weather', 'private', 'wuSubmit');
                $rc = qruqsp_weather_wuSubmit($ciniki, $tnid, $sensor['station_id']); 
                if( $rc['stat'] != 'ok' ) {
                    //
                    // Don't return error because then the weather data doesn't get saved
                    //
                    error_log('WARN: Unable to submit to weather underground: ' . print_r($rc['err'], true));
                } 
            }
        }
        exit();
    }
*/
    return array('stat'=>'ok');
}
?>
