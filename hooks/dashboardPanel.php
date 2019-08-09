<?php
//
// Description
// -----------
// This hook returns content for a panel to be added to the dashboard.
// 
// Arguments
// ---------
// ciniki: 
// tnid:            The ID of the current tenant.
// 
// Returns
// ---------
// 
function qruqsp_weather_hooks_dashboardPanel(&$ciniki, $tnid, $args) {

    if( !isset($args['panel']['panel_ref']) ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'qruqsp.weather.32', 'msg'=>'No dashboard panel specified'));
    }

    if( !isset($args['panel']['content']) ) {
        $args['panel']['content'] = '';
    }

    //
    // Load the referenced panel
    //
//    list($package, $module, $panel, $num) 
    $pieces = explode('.', $args['panel']['panel_ref']);
    if( !isset($pieces[2]) ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'qruqsp.weather.32', 'msg'=>'No dashboard valid panel specified'));
    }
    $package = $pieces[0];
    $module = $pieces[1];
    $panel = $pieces[2];
    $rc = ciniki_core_loadMethod($ciniki, $package, $module, 'panels', $panel);
    if( $rc['stat'] == 'ok' ) {
        $fn = $rc['function_call'];
        return $fn($ciniki, $tnid, $args, (isset($pieces[3]) ? $pieces[3] : ''));
    }

    return array('stat'=>'fail', 'err'=>array('code'=>'qruqsp.weather.55', 'msg'=>'Dashboard panel not found'));
}
?>

