#!/usr/bin/php
<?php
//
// Description
// -----------
// This script will listen on a pts for kiss tnc
//

//
// Initialize QRUQSP by including the ciniki-api.ini
//
$start_time = microtime(true);
global $ciniki_root;
$ciniki_root = dirname(__FILE__);
if( !file_exists($ciniki_root . '/ciniki-api.ini') ) {
    $ciniki_root = dirname(dirname(dirname(dirname(__FILE__))));
}

//
// Check to make sure script is not already running
//
exec('ps ax | grep "qruqsp-mods/weather/scripts/beacon.php" |grep -v grep ', $pids);
$parent_pid = getmypid();
foreach($pids as $line) {
    //
    // If any of the pids do not match our process id, then beacon is already running
    //
    if( !preg_match("/^\s*" . $parent_pid . "\s/", $line) ) {
        print "Beacon already running\n";
        exit;
    }
}

error_log('Waiting for weather updates');

require_once($ciniki_root . '/ciniki-mods/core/private/loadMethod.php');
require_once($ciniki_root . '/ciniki-mods/core/private/init.php');

//
// Initialize Ciniki after fork, initializes unique instance for each process
//
$rc = ciniki_core_init($ciniki_root, 'json');
if( $rc['stat'] != 'ok' ) {
    print "ERR: Unable to initialize Ciniki\n";
    exit;
}

//
// Setup the $ciniki variable to hold all things qruqsp.  
//
$ciniki = $rc['ciniki'];

//
// Determine which tnid to use
//
$tnid = $ciniki['config']['ciniki.core']['master_tnid'];
if( isset($ciniki['config']['qruqsp.weather']['tnid']) ) {
    $tnid = $ciniki['config']['qruqsp.weather']['tnid'];
}

ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbQuote');
ciniki_core_loadMethod($ciniki, 'ciniki', 'cron', 'private', 'logMsg');

error_log('starting beacon for ' . $tnid);
//
// Check for any stations that should that are ready to beacon
//

pcntl_sigprocmask(SIG_BLOCK, array(SIGUSR1));
while( $signo = pcntl_sigwaitinfo(array(SIGUSR1)) ) {
    if( $signo == SIGUSR1 ) {
        //
        // Check if station should be beaconed
        //
        $strsql = "SELECT id, flags, "
            . "IFNULL(TIMESTAMPDIFF(SECOND, aprs_last_beacon, UTC_TIMESTAMP()), 999) AS last_beacon_age, "
            . "IFNULL(TIMESTAMPDIFF(SECOND, wu_last_submit, UTC_TIMESTAMP()), 999) AS wu_last_submit_age, "
            . "aprs_frequency, wu_frequency "
            . "FROM qruqsp_weather_stations "
            . "WHERE tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
            . "";
        ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbHashQuery');
        $rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'qruqsp.weather', 'station');
        if( $rc['stat'] != 'ok' ) {
            error_log('WARN: Unable to beacon weather: ' . print_r($rc['err'], true));
        }
        $stations = isset($rc['rows']) ? $rc['rows'] : array();
        foreach($stations as $station) {
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
                $rc = qruqsp_weather_beaconSend($ciniki, $tnid, $station['id']); 
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
                $rc = qruqsp_weather_wuSubmit($ciniki, $tnid, $station['id']); 
                if( $rc['stat'] != 'ok' ) {
                    //
                    // Don't return error because then the weather data doesn't get saved
                    //
                    error_log('WARN: Unable to submit to weather underground: ' . print_r($rc['err'], true));
                } 
            }
        }
    }
}

error_log('exiting beacon');

exit;
?>
