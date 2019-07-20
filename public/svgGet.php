<?php
//
// Description
// ===========
// This method returns the SVG for a weather station of one of the graphs.
//
// Arguments
// ---------
// api_key:
// auth_token:
// tnid:         The ID of the tenant the station is attached to.
// station_id:          The ID of the station to get the details for.
//
// Returns
// -------
//
function qruqsp_weather_svgGet($ciniki) {
    //
    // Find all the required and optional arguments
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'prepareArgs');
    $rc = ciniki_core_prepareArgs($ciniki, 'no', array(
        'tnid'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Tenant'),
        'sensor_ids'=>array('required'=>'yes', 'blank'=>'no', 'type'=>'idlist', 'name'=>'Sensors'),
        'prefix'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'ID Prefix'),
        'fields'=>array('required'=>'yes', 'blank'=>'no', 'type'=>'list', 'name'=>'Graph Type'),
        'yaxis_left_min'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Y Axis Left Minimum'),
        'yaxis_left_max'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Y Axis Left Maximum'),
        'yaxis_right_min'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Y Axis Right Minimum'),
        'yaxis_right_max'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Y Axis Right Maximum'),
        'start_ts'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Start Timestamp'),
        'end_ts'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'End Timestamp'),
        'slice_seconds'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Slice width in seconds'),
        'slicesonly'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Slices Only'),
        ));
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    $args = $rc['args'];

    //
    // Make sure this module is activated, and
    // check permission to run this function for this tenant
    //
    ciniki_core_loadMethod($ciniki, 'qruqsp', 'weather', 'private', 'checkAccess');
    $rc = qruqsp_weather_checkAccess($ciniki, $args['tnid'], 'qruqsp.weather.svgGet');
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }

    ciniki_core_loadMethod($ciniki, 'qruqsp', 'weather', 'private', 'svgLoad');
    $rc = qruqsp_weather_svgLoad($ciniki, $args['tnid'], $args);
    if( $rc['stat'] != 'ok' ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'qruqsp.weather.52', 'msg'=>'Unable to load svg', 'err'=>$rc['err']));
    }

    return $rc;
}
?>
