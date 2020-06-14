<?php
//
// Description
// -----------
// This function will check to make sure scripts/check.php is running every minute from cron
// 
// Arguments
// ---------
// ciniki: 
// tnid:            The ID of the current tenant.
// 
// Returns
// ---------
// 
function qruqsp_weather_cronCheck(&$ciniki, $tnid) {

    ciniki_core_loadMethod($ciniki, 'qruqsp', 'piadmin', 'hooks', 'cronAdd');
    $rc = qruqsp_piadmin_hooks_cronAdd($ciniki, $tnid, array(
        'minute' => '*',
        'hour' => '*',
        'day' => '*',
        'month' => '*',
        'weekday' => '*',
        'cmd' => '/usr/bin/php /ciniki/sites/qruqsp.local/site/qruqsp-mods/weather/scripts/check.php',
        'log' => '/ciniki/sites/qruqsp.local/logs/cron.log',
        ));
    if( $rc['stat'] != 'ok' && $rc['stat'] != 'disabled' && $rc['stat'] != 'exists' ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'qruqsp.weather.18', 'msg'=>'Unable to update cron', 'err'=>$rc['err']));
    }

    return array('stat'=>'ok');
}
?>
