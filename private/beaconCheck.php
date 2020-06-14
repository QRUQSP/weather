<?php
//
// Description
// -----------
// This function will check to make sure the beacon script is running
// and waiting for signals to beacon weather
// 
// Arguments
// ---------
// ciniki: 
// tnid:            The ID of the current tenant.
// 
// Returns
// ---------
// 
function qruqsp_weather_beaconCheck(&$ciniki) {
error_log('checking for beacon');
    //
    // Get the list of listeners running
    //
    exec('ps ax | grep qruqsp-mods/weather/scripts/beacon.php |grep -v grep ', $pids);
    foreach($pids as $details) {
        if( preg_match("/php.*\/qruqsp-mods\/weather\/scripts\/beacon.php/", $details, $m) ) {
            // Running
            return array('stat'=>'ok');
        }
    }

    //
    // Start the beacon script to listen for signals
    //
    $cmd = $ciniki['config']['qruqsp.core']['modules_dir'] . '/weather/scripts/beacon.php';
    $log_file = $ciniki['config']['qruqsp.core']['log_dir'] . '/weather-beacon.log';
    error_log('starting weather beacon');       
    exec('php ' . $cmd . ' >> ' . $log_file . ' 2>&1 &');

    return array('stat'=>'ok');
}
?>
