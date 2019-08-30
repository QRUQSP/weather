<?php
//
// Description
// -----------
// This hook returns content for a widget to be added to panel in a dashboard.
// 
// Arguments
// ---------
// ciniki: 
// tnid:            The ID of the current tenant.
// 
// Returns
// ---------
// 
function qruqsp_weather_hooks_dashboardWidget(&$ciniki, $tnid, $args) {

    if( !isset($args['widget']['widget_ref']) ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'qruqsp.weather.63', 'msg'=>'No dashboard widget specified'));
    }

    if( !isset($args['widget']['content']) ) {
        $args['widget']['content'] = '';
    }

    //
    // Load the referenced panel
    //
//    list($package, $module, $panel, $num) 
    $pieces = explode('.', $args['widget']['widget_ref']);
    if( !isset($pieces[2]) ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'qruqsp.weather.57', 'msg'=>'No dashboard valid widget specified'));
    }
    $package = $pieces[0];
    $module = $pieces[1];
    $widget = $pieces[2];
    $rc = ciniki_core_loadMethod($ciniki, $package, $module, 'widgets', $widget);
    if( $rc['stat'] == 'ok' && isset($rc['function_call']) && is_callable($rc['function_call']) ) {
        $fn = $rc['function_call'];
        return $fn($ciniki, $tnid, $args);
    } 

    return array('stat'=>'fail', 'err'=>array('code'=>'qruqsp.weather.55', 'msg'=>'Dashboard widget not found'));
}
?>

