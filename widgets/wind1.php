<?php
//
// Description
// -----------
// This widget displays the wind direction and speed dial
// 
// Arguments
// ---------
// ciniki: 
// tnid:            The ID of the current tenant.
// 
// Returns
// ---------
// 
function qruqsp_weather_widgets_wind1(&$ciniki, $tnid, $args) {

    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbQuoteIDs');

    if( !isset($args['widget']['widget_ref']) ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'qruqsp.weather.72', 'msg'=>'No dashboard widget specified'));
    }

    if( !isset($args['widget']['content']) ) {
        $args['widget']['content'] = '';
    }

    if( !isset($args['widget']['settings']) ) {
        $args['widget']['settings'] = array();
    }

    $widget = $args['widget'];

    $label_font_size = 12;
    $tick_font_size = 10;
    $windspeed_font_size = 80;
    if( isset($_SERVER['HTTP_USER_AGENT']) && stristr($_SERVER['HTTP_USER_AGENT'], ' Gecko/20') !== false ) {
        $label_font_size = 11;
        $tick_font_size = 9;
        $windspeed_font_size = 72;
    }

    //
    // Make sure the sample date is within the last 5 minutes
    //
    $age_dt = new DateTime('now', new DateTimezone('UTC'));
    $age_dt->sub(new DateInterval('PT6M'));

    $sensor_ids = array();
    if( isset($widget['settings']['sid']) && $widget['settings']['sid'] != '' ) {
        $sensor_ids[] = $widget['settings']['sid'];
    }
    if( isset($widget['settings']['did']) && $widget['settings']['did'] != '' ) {
        $sensor_ids[] = $widget['settings']['did'];
    }

    if( count($sensor_ids) > 0 ) {
        $strsql = "SELECT sensors.id, "
            . "sensors.name, "
            . "sensors.flags, "
            . "sensors.fields, "
            . "IFNULL(data.wind_kph, '') AS windspeed, "
            . "IFNULL(data.wind_deg, '') AS wind_deg "
            . "FROM qruqsp_weather_sensors AS sensors "
            . "LEFT JOIN qruqsp_weather_sensor_data AS data ON ("
                . "sensors.id = data.sensor_id "
                . "AND sensors.last_sample_date = data.sample_date "
                . "AND data.tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
                . ") "
            . "WHERE sensors.id IN (" . ciniki_core_dbQuoteIDs($ciniki, $sensor_ids) . ") "
            . "AND sensors.tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
            . "AND sensors.last_sample_date > '" . ciniki_core_dbQuote($ciniki, $age_dt->format('Y-m-d H:i:s')) . "' "
            . "ORDER BY sensors.id ";
        ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbHashQueryIDTree');
        $rc = ciniki_core_dbHashQueryIDTree($ciniki, $strsql, 'qruqsp.weather', array(
            array('container'=>'sensors', 'fname'=>'id', 
                'fields'=>array('id', 'name', 'flags', 'fields', 'windspeed', 'wind_deg'),
                ),
            ));
        if( $rc['stat'] != 'ok' ) {
            return array('stat'=>'fail', 'err'=>array('code'=>'qruqsp.weather.61', 'msg'=>'Unable to load sensors', 'err'=>$rc['err']));
        }
        $sensors = isset($rc['sensors']) ? $rc['sensors'] : array();
    } else {
        $sensors = array();
    }

    //
    // Check if this is a data update
    //
    $widget['data'] = array();

    if( isset($sensors[$widget['settings']['sid']]['windspeed']) ) {
        if( isset($widget['settings']['units']) && $widget['settings']['units'] == 'mph' ) {
            $widget['data']['sid'] = round($sensors[$widget['settings']['sid']]['windspeed'] * 0.62137);
        } else {
            $widget['data']['sid'] = round($sensors[$widget['settings']['sid']]['windspeed']);
        }
    } else {
        $widget['data']['sid'] = '?';
    }
    $widget['data']['did'] = (isset($sensors[$widget['settings']['did']]['wind_deg']) ? $sensors[$widget['settings']['did']]['wind_deg'] : '?');

    if( isset($args['action']) && $args['action'] == 'update' ) {
        return array('stat'=>'ok', 'widget'=>$widget);
    }

    //
    // Setup the HTML for the dial
    //
    $cx = 100;
    $cy = 20;
    $angle = 0;
    if( isset($widget['data']['did']) && $widget['data']['did'] != '' ) {
        $angle = $widget['data']['did'];
    }
    $widget['content'] .= '<svg viewBox="0 0 200 200">'
        // Add tick marks
        . "<circle cx='100' cy='100' r='80' fill='none' stroke='#aaa' stroke-width='4' stroke-dasharray='1,6.854' transform='rotate(-0.35 100 100)'/>"
        . "<circle cx='100' cy='100' r='80' fill='none' stroke='#fff' stroke-width='12' stroke-dasharray='1,61.832' transform='rotate(-0.35 100 100)' />"
        // Add tick labels
        . "<text x='165' y='101' width='10' height='10' font-size='{$tick_font_size}' fill='#888'>"
            . "<tspan dominant-baseline='middle' alignment-baseline='middle' text-anchor='middle'>E</tspan></text>"
        . "<text x='100' y='167' width='10' height='10' font-size='{$tick_font_size}' fill='#888'>"
            . "<tspan dominant-baseline='middle' alignment-baseline='middle' text-anchor='middle'>S</tspan></text>"
        . "<text x='35' y='101' width='10' height='10' font-size='{$tick_font_size}' fill='#888'>"
            . "<tspan dominant-baseline='middle' alignment-baseline='middle' text-anchor='middle'>W</tspan></text>"
        . "<text x='100' y='35' width='10' height='10' font-size='{$tick_font_size}' fill='#888'>"
            . "<tspan dominant-baseline='middle' alignment-baseline='middle' text-anchor='middle'>N</tspan></text>"
        // Add label at top
        . "<text x='100' y='60' width='100' height='12' font-size='{$label_font_size}' fill='#bbb'><tspan text-anchor='middle'>"
            . (isset($widget['settings']['name']) ? $widget['settings']['name'] : '')
            . "</tspan></text>"
        // Add middle text
        . "<text x='100' y='108' width='100' height='100' font-size='{$windspeed_font_size}' fill='white'><tspan id='widget-{$widget['id']}-sid' dominant-baseline='middle' alignment-baseline='middle' text-anchor='middle'>"
            . (isset($widget['data']['sid']) ? $widget['data']['sid'] : '?')
            . "</tspan></text>"
        // Add units text
        . "<text x='100' y='145' width='100' height='12' font-size='{$tick_font_size}' fill='#888'><tspan text-anchor='middle'>"
            . (isset($widget['settings']['units']) ? $widget['settings']['units'] : '')
            . "</tspan></text>"
        // Add arrow
        . "<path id='widget-{$widget['id']}-arrow' d='M100 38 L90 7 L110 7 Z' fill='rgba(9,255,0,0.65)' stroke='white' stroke-width='0.5' transform='rotate($angle 100 100)' />"
        . "</svg>";

    //
    // Prepare update JS
    //
    $widget['js'] = array(
        'update_args' => "function() {};",
        'update' => "function(data) {"
            . "if( data.sid != null ) {"
                . "db_setInnerHtml(this,'sid', data.sid);"
            . "}"
            . "if( data.did != null ) {"
                . "var arrow = db_ge(this,'arrow');"
                . "arrow.setAttributeNS(null,'transform', 'rotate('+data.did+',100,100)');"
            . "}"
            . "};",
        'init' => "function() {};",
        );

    return array('stat'=>'ok', 'widget'=>$widget);
}
?>
