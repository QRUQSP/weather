<?php
//
// Description
// -----------
// This script will run from the cron to make sure the beacon script 
// is running and listening for signals to beacon stations
//

//
// Initialize Moss by including the ciniki_api.php
//
global $ciniki_root;
$ciniki_root = dirname(__FILE__);
if( !file_exists($ciniki_root . '/ciniki-api.ini') ) {
    $ciniki_root = dirname(dirname(dirname(dirname(__FILE__))));
}
// loadMethod is required by all function to ensure the functions are dynamically loaded
require_once($ciniki_root . '/ciniki-mods/core/private/loadMethod.php');
require_once($ciniki_root . '/ciniki-mods/core/private/init.php');
require_once($ciniki_root . '/ciniki-mods/core/private/checkModuleFlags.php');

$rc = ciniki_core_init($ciniki_root, 'rest');
if( $rc['stat'] != 'ok' ) {
    error_log("unable to initialize core");
    exit(1);
}

//
// Setup the $ciniki variable to hold all things ciniki.  
//
$ciniki = $rc['ciniki'];
$ciniki['session']['user']['id'] = -3;  // Setup to Ciniki Robot

ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbQuote');
ciniki_core_loadMethod($ciniki, 'ciniki', 'cron', 'private', 'logMsg');

//
// Check for weather beacon.php
//
ciniki_core_loadMethod($ciniki, 'qruqsp', 'weather', 'private', 'beaconCheck');
$rc = qruqsp_weather_beaconCheck($ciniki);
if( $rc['stat'] != 'ok' ) {
    ciniki_cron_logMsg($ciniki, $tnid, array('code'=>'qruqsp.weather.79', 'severity'=>50, 'msg'=>'Unable to check weather beacon', 'err'=>$rc['err']));
}

exit(0);
?>
